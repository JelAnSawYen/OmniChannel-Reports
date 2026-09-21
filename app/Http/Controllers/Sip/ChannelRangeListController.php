<?php

namespace App\Http\Controllers\Sip;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HandlesBulkDestroy;
use App\Models\SipChannel;
use App\Models\SipChannelNumber;
use App\Services\Logs\AuditLogger;
use App\Services\Sip\ChannelRangeListImportService;
use App\Services\XlsxService;
use App\Support\ExportRows;
use App\Support\NaturalSort;
use App\Support\PublicError;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ChannelRangeListController extends Controller
{
    use HandlesBulkDestroy;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $perPage = (int) $request->query('per_page', 10);
        if (! in_array($perPage, [5, 10, 25, 50], true)) {
            $perPage = 10;
        }

        $query = SipChannel::query()
            ->with(['channelNumbers'])
            ->whereHas('channelNumbers');
        NaturalSort::apply($query, 'etpi_sip_name');
        $query->orderBy('id');

        if ($search !== '') {
            $query->where(function ($channels) use ($search) {
                $channels->where('sip_channels.etpi_sip_name', 'like', "%{$search}%")
                    ->orWhereHas('channelNumbers', fn ($numbers) => $numbers->where('channel_number', 'like', "%{$search}%"));
            });
        }

        $groups = $query->paginate($perPage)->withQueryString();

        return view('channel-range-list.index', [
            'groups' => $groups,
            'search' => $search,
            'perPage' => $perPage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sip_channel_id' => ['required', 'integer', Rule::exists('sip_channels', 'id')],
            'from' => ['required', 'string', 'max:32'],
            'to' => ['required', 'string', 'max:32'],
        ]);

        $sipChannel = SipChannel::query()->with('campaign')->findOrFail((int) $data['sip_channel_id']);
        if ($sipChannel->campaign_id === null) {
            throw ValidationException::withMessages([
                'sip_channel_id' => 'Campaign must match an existing SIP Channel.',
            ]);
        }

        $numbers = $this->expandRange((string) $data['from'], (string) $data['to']);
        $this->assertUniqueNumbers($numbers);

        DB::transaction(function () use ($sipChannel, $numbers) {
            foreach ($numbers as $number) {
                SipChannelNumber::query()->create([
                    'sip_channel_id' => $sipChannel->id,
                    'channel_number' => $number,
                ]);
            }
        });

        AuditLogger::log('Created', 'Channel Range List', 'Channel Range List records added', $sipChannel->id, $request);

        return back()->with('success', 'Channel Range List records added successfully.')->with('crl_expanded', $sipChannel->id);
    }

    public function update(Request $request, SipChannelNumber $sipChannelNumber): RedirectResponse
    {
        $data = $request->validate([
            'channel_number' => [
                'required',
                'string',
                'max:32',
                'regex:/^\d+$/',
                Rule::unique('sip_channel_numbers', 'channel_number')->ignore($sipChannelNumber->id),
            ],
        ]);

        $sipChannelNumber->update([
            'channel_number' => $data['channel_number'],
        ]);
        AuditLogger::log('Updated', 'Channel Range List', 'Channel Range List record updated', $sipChannelNumber->id, $request);

        return back()->with('success', 'Channel Range List record updated successfully.')
            ->with('crl_expanded', $sipChannelNumber->sip_channel_id);
    }

    public function destroy(Request $request, SipChannelNumber $sipChannelNumber): RedirectResponse
    {
        $id = $sipChannelNumber->id;
        $sipChannelId = $sipChannelNumber->sip_channel_id;
        $sipChannelNumber->delete();
        AuditLogger::log('Deleted', 'Channel Range List', 'Channel Range List record deleted', $id, $request);

        return back()->with('success', 'Channel Range List record deleted successfully.')->with('crl_expanded', $sipChannelId);
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer'],
            'sip_channel_id' => ['nullable', 'integer'],
        ]);
        $ids = array_values(array_unique(array_map('intval', $data['ids'] ?? [])));
        $sipChannelId = isset($data['sip_channel_id']) ? (int) $data['sip_channel_id'] : 0;
        $numbers = $sipChannelId > 0
            ? SipChannelNumber::query()->where('sip_channel_id', $sipChannelId)->get()
            : collect($ids)->map(fn (int $id) => SipChannelNumber::query()->find($id))->filter();

        foreach ($numbers as $sipChannelNumber) {
            $id = $sipChannelNumber->id;
            $sipChannelId = $sipChannelNumber->sip_channel_id;
            $sipChannelNumber->delete();
            AuditLogger::log('Deleted', 'Channel Range List', 'Channel Range List record deleted', $id, $request);
        }

        return back()->with('success', 'Selected Channel Range List records deleted successfully.')->with('crl_expanded', $sipChannelId ?: null);
    }

    public function export(Request $request, XlsxService $xlsx): BinaryFileResponse|RedirectResponse
    {
        $search = trim((string) $request->query('search'));
        $query = SipChannelNumber::query()
            ->with(['sipChannel.channelNumbers'])
            ->leftJoin('sip_channels', 'sip_channels.id', '=', 'sip_channel_numbers.sip_channel_id')
            ->select('sip_channel_numbers.*');
        NaturalSort::apply($query, 'sip_channels.etpi_sip_name');
        $query->orderBy('sip_channel_numbers.channel_number');

        if ($search !== '') {
            $query->where(function ($numbers) use ($search) {
                $numbers->where('sip_channel_numbers.channel_number', 'like', "%{$search}%")
                    ->orWhere('sip_channels.etpi_sip_name', 'like', "%{$search}%");
            });
        }

        $headers = array_values(app(ChannelRangeListImportService::class)->exportFields());
        $rows = ExportRows::blankRepeatedParents(
            $query->get()->map(function (SipChannelNumber $record) {
                $sip = $record->sipChannel;
                $range = $sip?->channelRangeFromNumbers() ?: '';

                return [
                    $sip?->etpi_sip_name,
                    $range,
                    $record->channel_number,
                ];
            })->all(),
            [0, 1]
        );

        try {
            $path = $xlsx->export($headers, $rows, 'channel-range-list.xlsx');
        } catch (\Throwable $exception) {
            return back()->with('error', PublicError::failed('Export', $exception));
        }

        AuditLogger::log('Exported', 'Channel Range List', 'Exported Channel Range List records', null, $request);

        return response()->download($path, 'channel-range-list.xlsx')->deleteFileAfterSend(true);
    }

    public function importTemplate(XlsxService $xlsx, ChannelRangeListImportService $import): BinaryFileResponse|RedirectResponse
    {
        try {
            $path = $xlsx->export($import->templateHeaders(), $import->templateRows(), 'channel-range-list-template.xlsx');
        } catch (\Throwable $exception) {
            return back()->with('error', PublicError::failed('Template download', $exception));
        }

        return response()->download($path, 'channel-range-list-template.xlsx')->deleteFileAfterSend(true);
    }

    public function importPreview(Request $request, ChannelRangeListImportService $import, XlsxService $xlsx)
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

    public function importConfirm(Request $request, ChannelRangeListImportService $import)
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
        AuditLogger::log('Imported', 'Channel Range List', 'Imported '.$count.' Channel Range List records', null, $request);

        return response()->json([
            'ok' => true,
            'records' => $count,
        ]);
    }

    public function importErrors(Request $request, ChannelRangeListImportService $import, XlsxService $xlsx): BinaryFileResponse|RedirectResponse
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
            $path = $xlsx->export($headers, $rows, 'channel-range-list-import-errors.xlsx');
        } catch (\Throwable $exception) {
            return back()->with('error', PublicError::failed('Error report download', $exception));
        }

        return response()->download($path, 'channel-range-list-import-errors.xlsx')->deleteFileAfterSend(true);
    }

    /**
     * @return list<string>
     */
    private function expandRange(string $from, string $to): array
    {
        $from = trim($from);
        $to = trim($to);
        if (! preg_match('/^\d+$/', $from)) {
            throw ValidationException::withMessages([
                'from' => 'From must be a whole number.',
            ]);
        }
        if (! preg_match('/^\d+$/', $to)) {
            throw ValidationException::withMessages([
                'to' => 'To must be a whole number.',
            ]);
        }

        $start = (int) $from;
        $end = (int) $to;
        if ($end < $start) {
            throw ValidationException::withMessages([
                'to' => 'To must be greater than or equal to From.',
            ]);
        }
        if (($end - $start + 1) > 10000) {
            throw ValidationException::withMessages([
                'to' => 'The range cannot exceed 10000 channel numbers.',
            ]);
        }

        $numbers = [];
        for ($number = $start; $number <= $end; $number++) {
            $numbers[] = (string) $number;
        }

        return $numbers;
    }

    /**
     * @param  list<string>  $numbers
     */
    private function assertUniqueNumbers(array $numbers): void
    {
        $existing = SipChannelNumber::query()
            ->whereIn('channel_number', $numbers)
            ->orderBy('channel_number')
            ->pluck('channel_number');

        if ($existing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'from' => 'Channel number already exists: '.$existing->take(8)->implode(', '),
            ]);
        }
    }
}
