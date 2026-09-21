<?php

namespace App\Http\Controllers\Sip;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HandlesBulkDestroy;
use App\Models\ChannelAllocationCampaign;
use App\Models\SipChannel;
use App\Services\Logs\AuditLogger;
use App\Services\Sip\SipChannelImportService;
use App\Services\XlsxService;
use App\Support\NaturalSort;
use App\Support\PdcEndorseDate;
use App\Support\PublicError;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SipChannelController extends Controller
{
    use HandlesBulkDestroy;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $perPage = (int) $request->query('per_page', 10);
        if (! in_array($perPage, [5, 10, 25, 50], true)) {
            $perPage = 10;
        }

        $query = SipChannel::query()->with('campaign');
        NaturalSort::apply($query, 'etpi_sip_name');
        $query->orderBy('id');
        if ($search !== '') {
            $query->where(function ($channels) use ($search) {
                $channels->where('etpi_sip_name', 'like', "%{$search}%")
                    ->orWhere('pilot_number', 'like', "%{$search}%")
                    ->orWhere('channel_count', 'like', "%{$search}%")
                    ->orWhere('channel_range', 'like', "%{$search}%")
                    ->orWhere('network', 'like', "%{$search}%")
                    ->orWhereHas('campaign', fn ($campaigns) => $campaigns->where('name', 'like', "%{$search}%"));
                $parsed = PdcEndorseDate::parse($search);
                if ($parsed['valid'] && ! $parsed['empty'] && $parsed['iso']) {
                    $channels->orWhereDate('date_activation', $parsed['iso']);
                }
            });
        }

        $records = $query->paginate($perPage)->withQueryString();

        return view('sip-channels.index', [
            'records' => $records,
            'search' => $search,
            'perPage' => $perPage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateRecord($request);
        $record = DB::transaction(function () use ($data) {
            $record = SipChannel::query()->create($data);
            $record->syncChannelNumbersFromRange();

            return $record;
        });
        AuditLogger::log('Created', 'SIP Channels', 'SIP Channels record added', $record->id, $request);

        return back()->with('success', 'SIP Channels record added successfully.');
    }

    public function update(Request $request, SipChannel $sipChannel): RedirectResponse
    {
        $data = $this->validateRecord($request, $sipChannel->id);

        DB::transaction(function () use ($sipChannel, $data) {
            $sipChannel->update($data);
            $sipChannel->syncChannelNumbersFromRange();
        });
        AuditLogger::log('Updated', 'SIP Channels', 'SIP Channels record updated', $sipChannel->id, $request);

        return back()->with('success', 'SIP Channels record updated successfully.');
    }

    public function destroy(Request $request, SipChannel $sipChannel): RedirectResponse
    {
        $id = $sipChannel->id;
        $sipChannel->delete();
        AuditLogger::log('Deleted', 'SIP Channels', 'SIP Channels record deleted', $id, $request);

        return back()->with('success', 'SIP Channels record deleted successfully.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        foreach ($this->validatedBulkIds($request) as $id) {
            $sipChannel = SipChannel::query()->find($id);
            if (! $sipChannel) {
                continue;
            }
            $sipChannel->delete();
            AuditLogger::log('Deleted', 'SIP Channels', 'SIP Channels record deleted', $id, $request);
        }

        return back()->with('success', 'Selected SIP Channels records deleted successfully.');
    }

    public function export(Request $request, XlsxService $xlsx): BinaryFileResponse|RedirectResponse
    {
        $search = trim((string) $request->query('search'));
        $query = SipChannel::query()->with('campaign');
        NaturalSort::apply($query, 'etpi_sip_name');
        $query->orderBy('id');
        if ($search !== '') {
            $query->where(function ($channels) use ($search) {
                $channels->where('etpi_sip_name', 'like', "%{$search}%")
                    ->orWhere('pilot_number', 'like', "%{$search}%")
                    ->orWhere('channel_count', 'like', "%{$search}%")
                    ->orWhere('channel_range', 'like', "%{$search}%")
                    ->orWhere('network', 'like', "%{$search}%")
                    ->orWhereHas('campaign', fn ($campaigns) => $campaigns->where('name', 'like', "%{$search}%"));
            });
        }

        $headers = array_values(app(SipChannelImportService::class)->fields());
        $rows = $query->get()->map(function (SipChannel $record) {
            return [
                $record->etpi_sip_name,
                $record->pilot_number,
                $record->channel_count,
                $record->channel_range,
                $record->network,
                $record->date_activation ? PdcEndorseDate::display($record->date_activation->format('Y-m-d')) : '',
            ];
        });

        try {
            $path = $xlsx->export($headers, $rows, 'sip-channels.xlsx');
        } catch (\Throwable $exception) {
            return back()->with('error', PublicError::failed('Export', $exception));
        }

        AuditLogger::log('Exported', 'SIP Channels', 'Exported SIP Channels records', null, $request);

        return response()->download($path, 'sip-channels.xlsx')->deleteFileAfterSend(true);
    }

    public function importTemplate(XlsxService $xlsx, SipChannelImportService $import): BinaryFileResponse|RedirectResponse
    {
        try {
            $path = $xlsx->export($import->templateHeaders(), $import->templateRows(), 'sip-channels-template.xlsx');
        } catch (\Throwable $exception) {
            return back()->with('error', PublicError::failed('Template download', $exception));
        }

        return response()->download($path, 'sip-channels-template.xlsx')->deleteFileAfterSend(true);
    }

    public function importPreview(Request $request, SipChannelImportService $import, XlsxService $xlsx)
    {
        $uploaded = $request->file('file');
        if ($uploaded instanceof UploadedFile && ! $uploaded->isValid()) {
            return response()->json([
                'ok' => false,
                'message' => 'The file failed to upload. '.$uploaded->getErrorMessage(),
            ], 422);
        }

        $request->validate(['file' => ['required', 'file', 'max:5120']]);
        $file = $request->file('file');
        $extension = strtolower((string) $file?->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'xls'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Only .xlsx and .xls files are allowed.',
            ], 422);
        }

        try {
            $preview = $import->preview($file, $xlsx);
        } catch (\Throwable $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception instanceof \RuntimeException
                    ? $exception->getMessage()
                    : PublicError::failed('Preview', $exception),
            ], 422);
        }

        $token = $import->storePreview($preview);

        return response()->json([
            'ok' => true,
            'token' => $token,
            'valid' => $preview['valid'],
            'summary' => $preview['summary'],
            'rows' => $preview['rows'],
            'headers' => $preview['headers'],
        ]);
    }

    public function importConfirm(Request $request, SipChannelImportService $import)
    {
        $request->validate(['token' => ['required', 'string']]);
        $stored = $import->previewFromSession((string) $request->input('token'));
        if (! is_array($stored) || ! ($stored['valid'] ?? false) || ($stored['payload'] ?? []) === []) {
            return response()->json([
                'ok' => false,
                'message' => 'There are errors in some rows. Please review the details below and fix them in your file.',
            ], 422);
        }

        try {
            $count = $import->commit($stored['payload']);
        } catch (\Throwable $exception) {
            return response()->json([
                'ok' => false,
                'message' => PublicError::failed('Import', $exception),
            ], 422);
        }

        $import->forget();
        AuditLogger::log('Imported', 'SIP Channels', 'Imported '.$count.' SIP Channels records', null, $request);

        return response()->json([
            'ok' => true,
            'records' => $count,
        ]);
    }

    public function importErrors(Request $request, SipChannelImportService $import, XlsxService $xlsx): BinaryFileResponse|RedirectResponse
    {
        $stored = $import->previewFromSession((string) $request->query('token'));
        if (! is_array($stored)) {
            return back()->with('error', 'No import preview is available. Upload and validate the file again.');
        }

        $headers = array_merge(['Row #'], $stored['headers'] ?? $import->templateHeaders(), ['Status', 'Error Reason']);
        $rows = [];
        foreach ($stored['rows'] as $row) {
            if ($row['valid']) {
                continue;
            }
            $line = [$row['row']];
            foreach (array_keys($import->fields()) as $field) {
                $line[] = $row[$field] ?? '';
            }
            $line[] = $row['status'];
            $line[] = $row['error'];
            $rows[] = $line;
        }

        try {
            $path = $xlsx->export($headers, $rows, 'sip-channels-import-errors.xlsx');
        } catch (\Throwable $exception) {
            return back()->with('error', PublicError::failed('Error report download', $exception));
        }

        return response()->download($path, 'sip-channels-import-errors.xlsx')->deleteFileAfterSend(true);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRecord(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'campaign' => ['nullable', 'string', 'max:255'],
            'campaign_id' => ['nullable', 'integer'],
            'etpi_sip_name' => ['required', 'string', 'max:255', Rule::unique('sip_channels', 'etpi_sip_name')->ignore($id)],
            'pilot_number' => ['nullable', 'string', 'max:255'],
            'channel_count' => ['nullable', 'integer', 'min:0'],
            'from' => ['nullable', 'string', 'max:255'],
            'to' => ['nullable', 'string', 'max:255'],
            'channel_range' => ['nullable', 'string', 'max:255'],
            'network' => ['nullable', 'string', 'max:255'],
            'date_activation' => ['nullable', 'string'],
        ]);

        unset($data['campaign']);
        $incomingCampaignId = (int) ($data['campaign_id'] ?? 0);
        if ($incomingCampaignId > 0) {
            $existing = ChannelAllocationCampaign::query()->find($incomingCampaignId);
            $data['campaign_id'] = $existing?->id;
        } else {
            unset($data['campaign_id']);
        }

        $parsed = PdcEndorseDate::parse($data['date_activation'] ?? '');
        if (! $parsed['valid']) {
            throw ValidationException::withMessages([
                'date_activation' => 'Date Activation must be a valid date on or after 1/1/2000.',
            ]);
        }
        $data['date_activation'] = $parsed['iso'];
        foreach (['pilot_number', 'network'] as $field) {
            $data[$field] = $this->nullableString($data[$field] ?? null);
        }
        $from = $this->nullableString($data['from'] ?? null);
        $to = $this->nullableString($data['to'] ?? null);
        if ($from !== null || $to !== null) {
            $data['channel_range'] = SipChannel::formatRangeFromBounds($from ?? '', $to ?? '');
        } else {
            $data['channel_range'] = $this->nullableString($data['channel_range'] ?? null);
        }
        unset($data['from'], $data['to']);
        if (($data['channel_count'] ?? '') === '' || $data['channel_count'] === null) {
            $data['channel_count'] = null;
        }

        return $data;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === null || $value === '' ? null : (string) $value;
    }
}
