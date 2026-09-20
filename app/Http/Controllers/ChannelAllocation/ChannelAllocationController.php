<?php

namespace App\Http\Controllers\ChannelAllocation;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HandlesBulkDestroy;
use App\Models\ChannelAllocation;
use App\Models\ChannelAllocationCampaign;
use App\Models\MediaGateway;
use App\Models\SipChannel;
use App\Services\ChannelAllocation\ChannelAllocationImportService;
use App\Services\Logs\AuditLogger;
use App\Services\XlsxService;
use App\Support\ChannelAllocationResolver;
use App\Support\PublicError;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ChannelAllocationController extends Controller
{
    use HandlesBulkDestroy;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $perPage = (int) $request->query('per_page', 10);
        if (! in_array($perPage, [5, 10, 25, 50], true)) {
            $perPage = 10;
        }

        $query = ChannelAllocationCampaign::query()->with('allocations')->orderBy('sort_order')->orderBy('name');
        if ($search !== '') {
            $query->where(function ($campaigns) use ($search) {
                $campaigns->where('name', 'like', "%{$search}%")
                    ->orWhere('media_gateway', 'like', "%{$search}%")
                    ->orWhere('caller_id', 'like', "%{$search}%")
                    ->orWhere('prefix', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('allocations', function ($allocations) use ($search) {
                        $allocations->where('channel_allocation', 'like', "%{$search}%")
                            ->orWhere('media_gateway', 'like', "%{$search}%")
                            ->orWhere('network', 'like', "%{$search}%")
                            ->orWhere('remarks', 'like', "%{$search}%");
                    });
            });
        }

        $campaigns = $query->paginate($perPage)->withQueryString();
        $masterCampaigns = ChannelAllocationCampaign::optionsForDropdown();
        $sipChannelOptions = SipChannel::query()
            ->whereNotNull('etpi_sip_name')
            ->where('etpi_sip_name', '!=', '')
            ->orderBy('etpi_sip_name')
            ->get(['etpi_sip_name', 'network', 'channel_count'])
            ->map(fn (SipChannel $sip) => [
                'value' => $sip->etpi_sip_name,
                'network' => $sip->network,
                'count' => $sip->channel_count,
            ])
            ->values();
        $gsmChannelOptions = MediaGateway::query()
            ->whereNotNull('hostname')
            ->where('hostname', '!=', '')
            ->orderBy('hostname')
            ->get(['hostname', 'network', 'channel_count'])
            ->map(fn (MediaGateway $gateway) => [
                'value' => $gateway->hostname,
                'network' => $gateway->network,
                'count' => $gateway->channel_count,
            ])
            ->values();

        return view('channel-allocations.index', [
            'campaigns' => $campaigns,
            'masterCampaigns' => $masterCampaigns,
            'sipChannelOptions' => $sipChannelOptions,
            'gsmChannelOptions' => $gsmChannelOptions,
            'search' => $search,
            'perPage' => $perPage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->campaignRules(true));
        $allocation = $this->optionalAllocation($request);
        $campaignRecord = ChannelAllocationCampaign::fromFormValue($data['campaign'] ?? null, $data['campaign_id'] ?? null);
        if ($campaignRecord === null) {
            return back()->withErrors(['campaign' => 'Please select or type a campaign.'])->withInput();
        }
        unset($data['campaign'], $data['campaign_id']);

        try {
            $campaign = DB::transaction(function () use ($data, $allocation, $campaignRecord) {
                $campaign = ChannelAllocationCampaign::query()
                    ->whereKey($campaignRecord->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $campaign->update([
                    'media_gateway' => $data['media_gateway'] ?? null,
                    'caller_id' => $data['caller_id'] ?? null,
                    'prefix' => $data['prefix'] ?? null,
                    'remarks' => $data['remarks'] ?? null,
                ]);

                if ($allocation !== null) {
                    $allocation['sort_order'] = (int) $campaign->allocations()->max('sort_order') + 1;
                    $campaign->allocations()->create($allocation);
                    $campaign->refreshTotalChannelsAllocated();
                }

                return $campaign;
            });
        } catch (\Throwable $exception) {
            return back()->with('error', PublicError::failed('Save', $exception))->withInput();
        }

        AuditLogger::log('Created', 'Channel Allocation', 'Campaign added', $campaign->id, $request);

        return back()->with('success', 'Campaign added successfully.');
    }

    public function update(Request $request, ChannelAllocationCampaign $campaign): RedirectResponse
    {
        $data = $request->validate(array_merge($this->campaignRules(), [
            'campaign' => ['nullable', 'string', 'max:255', Rule::unique('channel_allocation_campaigns', 'name')->ignore($campaign->id)],
        ]));

        try {
            DB::transaction(function () use ($campaign, $data) {
                $locked = ChannelAllocationCampaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
                $payload = [
                    'caller_id' => $data['caller_id'] ?? null,
                    'prefix' => $data['prefix'] ?? null,
                    'remarks' => $data['remarks'] ?? null,
                ];
                $name = trim((string) ($data['campaign'] ?? ''));
                if ($name !== '') {
                    $payload['name'] = $name;
                }
                $locked->update($payload);
            });
        } catch (\Throwable $exception) {
            return back()->with('error', PublicError::failed('Update', $exception))->withInput();
        }

        AuditLogger::log('Updated', 'Channel Allocation', 'Campaign updated', $campaign->id, $request);

        return back()->with('success', 'Campaign updated successfully.');
    }

    public function destroy(Request $request, ChannelAllocationCampaign $campaign): RedirectResponse
    {
        $id = $campaign->id;
        $campaign->delete();
        AuditLogger::log('Deleted', 'Channel Allocation', 'Campaign deleted', $id, $request);

        return back()->with('success', 'Campaign and associated allocations deleted successfully.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        foreach ($this->validatedBulkIds($request) as $id) {
            $campaign = ChannelAllocationCampaign::query()->find($id);
            if (! $campaign) {
                continue;
            }
            $campaign->delete();
            AuditLogger::log('Deleted', 'Channel Allocation', 'Campaign deleted', $id, $request);
        }

        return back()->with('success', 'Selected campaigns deleted successfully.');
    }

    public function storeAllocation(Request $request, ChannelAllocationCampaign $campaign): RedirectResponse
    {
        $data = $this->validatedAllocationFromSip($request);

        try {
            DB::transaction(function () use ($campaign, $data) {
                $locked = ChannelAllocationCampaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
                $data['sort_order'] = (int) $locked->allocations()->max('sort_order') + 1;
                $locked->allocations()->create($data);
                $locked->refreshTotalChannelsAllocated();
            });
        } catch (\Throwable $exception) {
            return back()->with('error', PublicError::failed('Save', $exception))->withInput();
        }

        AuditLogger::log('Added', 'Channel Allocation', 'Allocation added', $campaign->id, $request);

        return back()
            ->with('success', 'Channel allocation added successfully.')
            ->with('ca_expanded', $campaign->id);
    }

    public function updateAllocation(Request $request, ChannelAllocationCampaign $campaign, ChannelAllocation $allocation): RedirectResponse
    {
        abort_unless($allocation->campaign_id === $campaign->id, 404);
        $data = $this->validatedAllocationFromSip($request);

        try {
            DB::transaction(function () use ($campaign, $allocation, $data) {
                ChannelAllocationCampaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
                $locked = ChannelAllocation::query()
                    ->whereKey($allocation->id)
                    ->where('campaign_id', $campaign->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $locked->update($data);
                $campaign->refreshTotalChannelsAllocated();
            });
        } catch (\Throwable $exception) {
            return back()->with('error', PublicError::failed('Update', $exception))->withInput();
        }

        AuditLogger::log('Updated', 'Channel Allocation', 'Allocation updated', $allocation->id, $request);

        return back()
            ->with('success', 'Channel allocation updated successfully.')
            ->with('ca_expanded', $campaign->id)
            ->with('ca_edit_allocation', $allocation->id);
    }

    public function destroyAllocation(Request $request, ChannelAllocationCampaign $campaign, ChannelAllocation $allocation): RedirectResponse
    {
        abort_unless($allocation->campaign_id === $campaign->id, 404);
        $id = $allocation->id;

        try {
            DB::transaction(function () use ($campaign, $allocation) {
                ChannelAllocationCampaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
                $locked = ChannelAllocation::query()
                    ->whereKey($allocation->id)
                    ->where('campaign_id', $campaign->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $locked->delete();
                $campaign->refreshTotalChannelsAllocated();
            });
        } catch (\Throwable $exception) {
            return back()->with('error', PublicError::failed('Delete', $exception));
        }

        AuditLogger::log('Deleted', 'Channel Allocation', 'Allocation deleted', $id, $request);

        return back()
            ->with('success', 'Channel allocation deleted successfully.')
            ->with('ca_expanded', $campaign->id);
    }

    public function bulkDestroyAllocations(Request $request, ChannelAllocationCampaign $campaign): RedirectResponse
    {
        $deleted = 0;
        foreach ($this->validatedBulkIds($request) as $id) {
            $allocation = ChannelAllocation::query()
                ->whereKey($id)
                ->where('campaign_id', $campaign->id)
                ->first();
            if (! $allocation) {
                continue;
            }

            try {
                DB::transaction(function () use ($campaign, $allocation) {
                    ChannelAllocationCampaign::query()->whereKey($campaign->id)->lockForUpdate()->firstOrFail();
                    $locked = ChannelAllocation::query()
                        ->whereKey($allocation->id)
                        ->where('campaign_id', $campaign->id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    $locked->delete();
                });
            } catch (\Throwable $exception) {
                return back()->with('error', PublicError::failed('Delete', $exception));
            }

            AuditLogger::log('Deleted', 'Channel Allocation', 'Allocation deleted', $id, $request);
            $deleted++;
        }

        if ($deleted > 0) {
            $campaign->refreshTotalChannelsAllocated();
        }

        return back()
            ->with('success', 'Selected channel allocations deleted successfully.')
            ->with('ca_expanded', $campaign->id);
    }

    public function export(Request $request, XlsxService $xlsx): BinaryFileResponse|RedirectResponse
    {
        $search = trim((string) $request->query('search'));
        $query = ChannelAllocationCampaign::query()->with('allocations')->orderBy('sort_order')->orderBy('name');
        if ($search !== '') {
            $query->where(function ($campaigns) use ($search) {
                $campaigns->where('name', 'like', "%{$search}%")
                    ->orWhere('media_gateway', 'like', "%{$search}%")
                    ->orWhere('caller_id', 'like', "%{$search}%")
                    ->orWhere('prefix', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('allocations', function ($allocations) use ($search) {
                        $allocations->where('channel_allocation', 'like', "%{$search}%")
                            ->orWhere('media_gateway', 'like', "%{$search}%")
                            ->orWhere('network', 'like', "%{$search}%")
                            ->orWhere('remarks', 'like', "%{$search}%");
                    });
            });
        }

        $headers = [
            'Campaign',
            'Channel',
            'Network',
            'Line Priority',
            'Channel Count',
            'Total Channels',
            'FTE',
            'Caller ID',
            'Prefix',
            'Remarks',
        ];

        $rows = [];
        foreach ($query->get() as $campaign) {
            if ($campaign->allocations->isEmpty()) {
                $rows[] = [
                    $campaign->name,
                    '',
                    '',
                    '',
                    '',
                    $campaign->total_channels_allocated,
                    $campaign->fte,
                    $campaign->caller_id,
                    $campaign->prefix,
                    $campaign->remarks,
                ];

                continue;
            }

            foreach ($campaign->allocations as $index => $allocation) {
                $rows[] = [
                    $index === 0 ? $campaign->name : '',
                    $allocation->channelLabel() === '—' ? '' : $allocation->channelLabel(),
                    $allocation->network,
                    $allocation->line_priority,
                    $allocation->total_channel_allocated,
                    $index === 0 ? $campaign->total_channels_allocated : '',
                    $index === 0 ? $campaign->fte : '',
                    $index === 0 ? $campaign->caller_id : '',
                    $index === 0 ? $campaign->prefix : '',
                    $index === 0 ? $campaign->remarks : $allocation->remarks,
                ];
            }
        }

        try {
            $path = $xlsx->export($headers, $rows, 'channel-allocation.xlsx');
        } catch (\Throwable $exception) {
            return back()->with('error', PublicError::failed('Export', $exception));
        }

        AuditLogger::log('Exported', 'Channel Allocation', 'Exported Channel Allocation records', null, $request);

        return response()->download($path, 'channel-allocation.xlsx')->deleteFileAfterSend(true);
    }

    public function importTemplate(XlsxService $xlsx, ChannelAllocationImportService $import): BinaryFileResponse|RedirectResponse
    {
        try {
            $path = $xlsx->export($import->templateHeaders(), $import->templateRows(), 'channel-allocation-template.xlsx');
        } catch (\Throwable $exception) {
            return back()->with('error', PublicError::failed('Template download', $exception));
        }

        return response()->download($path, 'channel-allocation-template.xlsx')->deleteFileAfterSend(true);
    }

    public function importPreview(Request $request, ChannelAllocationImportService $import, XlsxService $xlsx)
    {
        $uploaded = $request->file('file');
        if ($uploaded instanceof UploadedFile && ! $uploaded->isValid()) {
            return response()->json([
                'ok' => false,
                'message' => 'The file failed to upload. '.$uploaded->getErrorMessage(),
            ], 422);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
        ]);

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
        ]);
    }

    public function importConfirm(Request $request, ChannelAllocationImportService $import)
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
            $result = $import->commit($stored['payload']);
        } catch (\Throwable $exception) {
            return response()->json([
                'ok' => false,
                'message' => PublicError::failed('Import', $exception),
            ], 422);
        }

        $import->forget();
        AuditLogger::log('Imported', 'Channel Allocation', 'Imported '.$result['allocations'].' channel allocations', null, $request);

        return response()->json([
            'ok' => true,
            'allocations' => $result['allocations'],
            'campaigns' => $result['campaigns'],
        ]);
    }

    public function importErrors(Request $request, ChannelAllocationImportService $import, XlsxService $xlsx): BinaryFileResponse|RedirectResponse
    {
        $stored = $import->previewFromSession((string) $request->query('token'));
        if (! is_array($stored)) {
            return back()->with('error', 'No import preview is available. Upload and validate the file again.');
        }

        $headers = ['Row #', 'Campaign', 'FTE', 'Caller ID', 'Prefix', 'Channel', 'Network', 'Line Priority', 'Channel Count', 'Status', 'Error Reason'];
        $rows = [];
        foreach ($stored['rows'] as $row) {
            if ($row['valid']) {
                continue;
            }
            $rows[] = [
                $row['row'],
                $row['campaign'],
                $row['fte'],
                $row['caller_id'],
                $row['prefix'],
                $row['channel'],
                $row['network'],
                $row['line_priority'],
                $row['total_channel_allocated'],
                $row['status'],
                $row['error'],
            ];
        }

        try {
            $path = $xlsx->export($headers, $rows, 'channel-allocation-import-errors.xlsx');
        } catch (\Throwable $exception) {
            return back()->with('error', PublicError::failed('Error report download', $exception));
        }

        return response()->download($path, 'channel-allocation-import-errors.xlsx')->deleteFileAfterSend(true);
    }

    /**
     * @return array<string, mixed>
     */
    private function campaignRules(bool $creating = false): array
    {
        $rules = [
            'media_gateway' => 'nullable|string|max:255',
            'caller_id' => 'nullable|string|max:255',
            'prefix' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:2000',
        ];
        if ($creating) {
            $rules['campaign'] = ['required_without:campaign_id', 'nullable', 'string', 'max:255'];
            $rules['campaign_id'] = ['required_without:campaign', 'nullable', 'integer'];
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    private function allocationRules(): array
    {
        return [
            'channel_type' => 'nullable|in:sip,gsm',
            'channel' => 'required|string|max:255',
            'line_priority' => 'nullable|integer|min:0',
            'remarks' => 'nullable|string|max:2000',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAllocationFromSip(Request $request): array
    {
        if (! $request->filled('channel') && $request->filled('channel_allocation')) {
            $request->merge(['channel' => $request->input('channel_allocation')]);
        }
        $data = $request->validate($this->allocationRules());
        $resolved = ChannelAllocationResolver::resolve(
            (string) $data['channel'],
            $data['channel_type'] ?? null
        );
        if (! ($resolved['ok'] ?? false)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'channel' => [$resolved['error'] ?? 'Channel is invalid'],
            ]);
        }

        return [
            'channel_allocation' => $resolved['channel'],
            'media_gateway' => $resolved['media_gateway'],
            'network' => $resolved['network'],
            'line_priority' => $data['line_priority'] ?? null,
            'total_channel_allocated' => $resolved['total_channel_allocated'],
            'remarks' => $data['remarks'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function optionalAllocation(Request $request): ?array
    {
        $channel = trim((string) ($request->input('channel') ?: $request->input('channel_allocation')));
        if ($channel === '') {
            return null;
        }

        $data = $this->validatedAllocationFromSip($request);
        if ($request->filled('allocation_remarks') && empty($data['remarks'])) {
            $data['remarks'] = $request->input('allocation_remarks');
        }

        return $data;
    }
}
