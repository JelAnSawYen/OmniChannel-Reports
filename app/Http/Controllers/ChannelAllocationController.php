<?php

namespace App\Http\Controllers;

use App\Models\ChannelAllocation;
use App\Models\ChannelAllocationCampaign;
use App\Models\MediaGateway;
use App\Models\SipChannel;
use App\Services\ChannelAllocationImportService;
use App\Services\AuditLogger;
use App\Services\XlsxService;
use App\Support\PublicError;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ChannelAllocationController extends Controller
{
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
        $sipChannels = SipChannel::query()
            ->whereNotNull('etpi_sip_name')
            ->where('etpi_sip_name', '!=', '')
            ->orderBy('etpi_sip_name')
            ->get(['etpi_sip_name', 'network', 'channel_count']);
        $gsmGateways = MediaGateway::query()
            ->orderBy('site_code')
            ->get(['site_code', 'site_name']);

        return view('channel-allocations.index', [
            'campaigns' => $campaigns,
            'masterCampaigns' => $masterCampaigns,
            'sipChannels' => $sipChannels,
            'gsmGateways' => $gsmGateways,
            'search' => $search,
            'perPage' => $perPage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->campaignRules(true));
        $allocation = $this->optionalAllocation($request);
        $campaign = ChannelAllocationCampaign::query()->findOrFail((int) $data['campaign_id']);

        $campaign->update([
            'media_gateway' => $data['media_gateway'] ?? null,
            'total_channels_allocated' => $data['total_channels_allocated'] ?? $campaign->total_channels_allocated,
            'caller_id' => $data['caller_id'] ?? null,
            'prefix' => $data['prefix'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ]);

        if ($allocation !== null) {
            $allocation['sort_order'] = (int) $campaign->allocations()->max('sort_order') + 1;
            $campaign->allocations()->create($allocation);
            $campaign->refreshTotalChannelsAllocated();
        }

        AuditLogger::log('Added', 'Channel Allocation', 'Campaign added', $campaign->id, $request);

        return back()->with('success', 'Campaign added successfully.');
    }

    public function update(Request $request, ChannelAllocationCampaign $campaign): RedirectResponse
    {
        $data = $request->validate($this->campaignRules());
        $campaign->update([
            'media_gateway' => $data['media_gateway'] ?? null,
            'total_channels_allocated' => $data['total_channels_allocated'] ?? null,
            'caller_id' => $data['caller_id'] ?? null,
            'prefix' => $data['prefix'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ]);
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

    public function storeAllocation(Request $request, ChannelAllocationCampaign $campaign): RedirectResponse
    {
        $data = $this->validatedAllocationFromSip($request);
        $data['media_gateway'] = ($data['media_gateway'] ?? null) ?: $campaign->media_gateway;
        $data['sort_order'] = (int) $campaign->allocations()->max('sort_order') + 1;
        $campaign->allocations()->create($data);
        $campaign->refreshTotalChannelsAllocated();
        AuditLogger::log('Added', 'Channel Allocation', 'Allocation added', $campaign->id, $request);

        return back()
            ->with('success', 'Channel allocation added successfully.')
            ->with('ca_expanded', $campaign->id);
    }

    public function updateAllocation(Request $request, ChannelAllocationCampaign $campaign, ChannelAllocation $allocation): RedirectResponse
    {
        abort_unless($allocation->campaign_id === $campaign->id, 404);
        $data = $this->validatedAllocationFromSip($request);
        $allocation->update($data);
        $campaign->refreshTotalChannelsAllocated();
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
        $allocation->delete();
        $campaign->refreshTotalChannelsAllocated();
        AuditLogger::log('Deleted', 'Channel Allocation', 'Allocation deleted', $id, $request);

        return back()
            ->with('success', 'Channel allocation deleted successfully.')
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
            'Media Gateway',
            'Channel Allocation',
            'Network',
            'Line Priority',
            'Total Channel Allocated',
            'Total Channels Allocated',
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
                    $campaign->media_gateway,
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
                    $allocation->media_gateway,
                    $allocation->channel_allocation,
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

        $headers = ['Row #', 'Campaign', 'Media Gateway', 'FTE', 'Caller ID', 'Prefix', 'Channel Allocation', 'Network', 'Line Priority', 'Total Channel Allocated', 'Status', 'Error Reason'];
        $rows = [];
        foreach ($stored['rows'] as $row) {
            if ($row['valid']) {
                continue;
            }
            $rows[] = [
                $row['row'],
                $row['campaign'],
                $row['media_gateway'],
                $row['fte'],
                $row['caller_id'],
                $row['prefix'],
                $row['channel_allocation'],
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
            'total_channels_allocated' => 'nullable|integer|min:0',
            'caller_id' => 'nullable|string|max:255',
            'prefix' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:2000',
        ];
        if ($creating) {
            $rules['campaign_id'] = ['required', 'integer', Rule::exists('channel_allocation_campaigns', 'id')];
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    private function allocationRules(): array
    {
        return [
            'media_gateway' => 'nullable|string|max:255',
            'channel_allocation' => 'required|string|max:255',
            'network' => 'nullable|string|max:255',
            'line_priority' => 'nullable|integer|min:0',
            'total_channel_allocated' => 'nullable|integer|min:0',
            'remarks' => 'nullable|string|max:2000',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAllocationFromSip(Request $request): array
    {
        $data = $request->validate([
            'media_gateway' => 'nullable|string|max:255',
            'channel_allocation' => ['required', 'string', 'max:255', Rule::exists('sip_channels', 'etpi_sip_name')],
            'line_priority' => 'nullable|integer|min:0',
        ]);

        $sip = SipChannel::query()
            ->where('etpi_sip_name', $data['channel_allocation'])
            ->first();

        $data['network'] = $sip?->network ?: null;
        $data['total_channel_allocated'] = $sip?->channel_count;

        return $data;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function optionalAllocation(Request $request): ?array
    {
        if (trim((string) $request->input('channel_allocation')) === '') {
            return null;
        }

        $data = $this->validatedAllocationFromSip($request);
        if ($request->filled('allocation_remarks') && empty($data['remarks'])) {
            $data['remarks'] = $request->input('allocation_remarks');
        }

        return $data;
    }
}
