<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HandlesBulkDestroy;
use App\Http\Controllers\HandlesInventoryImport;
use App\Models\ChannelAllocationCampaign;
use App\Services\Logs\AuditLogger;
use App\Services\XlsxService;
use App\Support\InventoryImportCatalog;
use App\Support\OperationCatalog;
use App\Support\PublicError;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CampaignController extends Controller
{
    use HandlesBulkDestroy;
    use HandlesInventoryImport;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $perPage = (int) $request->query('per_page', 10);
        if (! in_array($perPage, [5, 10, 25, 50], true)) {
            $perPage = 10;
        }

        $query = ChannelAllocationCampaign::query()->orderBy('name');
        if ($search !== '') {
            $query->where(function ($campaigns) use ($search) {
                $campaigns->where('name', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('fte', 'like', "%{$search}%");
            });
        }

        $records = $query->paginate($perPage)->withQueryString();

        return view('campaigns.index', [
            'records' => $records,
            'locations' => OperationCatalog::locations(),
            'search' => $search,
            'perPage' => $perPage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateRecord($request);
        $data['sort_order'] = (int) ChannelAllocationCampaign::query()->max('sort_order') + 1;
        $record = ChannelAllocationCampaign::query()->create($data);
        AuditLogger::log('Created', 'Campaigns', 'Campaign added', $record->id, $request);

        return back()->with('success', 'Campaign added successfully.');
    }

    public function update(Request $request, ChannelAllocationCampaign $campaign): RedirectResponse
    {
        $data = $this->validateRecord($request, $campaign->id);
        $campaign->update($data);
        AuditLogger::log('Updated', 'Campaigns', 'Campaign updated', $campaign->id, $request);

        return back()->with('success', 'Campaign updated successfully.');
    }

    public function destroy(Request $request, ChannelAllocationCampaign $campaign): RedirectResponse
    {
        $id = $campaign->id;
        $campaign->delete();
        AuditLogger::log('Deleted', 'Campaigns', 'Campaign deleted', $id, $request);

        return back()->with('success', 'Campaign deleted successfully.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        foreach ($this->validatedBulkIds($request) as $id) {
            $campaign = ChannelAllocationCampaign::query()->find($id);
            if (! $campaign) {
                continue;
            }
            $campaign->delete();
            AuditLogger::log('Deleted', 'Campaigns', 'Campaign deleted', $id, $request);
        }

        return back()->with('success', 'Selected campaigns deleted successfully.');
    }

    public function export(Request $request, XlsxService $xlsx): BinaryFileResponse|RedirectResponse
    {
        $search = trim((string) $request->query('search'));
        $query = ChannelAllocationCampaign::query()->orderBy('name');
        if ($search !== '') {
            $query->where(function ($campaigns) use ($search) {
                $campaigns->where('name', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")
                    ->orWhere('fte', 'like', "%{$search}%");
            });
        }

        $headers = array_values(InventoryImportCatalog::campaigns()['fields']);
        $rows = $query->get()->map(fn (ChannelAllocationCampaign $record) => [
            $record->name,
            $record->fte,
            $record->location,
        ]);

        try {
            $path = $xlsx->export($headers, $rows, 'campaigns.xlsx');
        } catch (\Throwable $exception) {
            return back()->with('error', PublicError::failed('Export', $exception));
        }

        AuditLogger::log('Exported', 'Campaigns', 'Exported Campaigns records', null, $request);

        return response()->download($path, 'campaigns.xlsx')->deleteFileAfterSend(true);
    }

    protected function inventoryImportConfig(Request $request): array
    {
        return InventoryImportCatalog::campaigns();
    }

    /**
     * @return array{name: string, fte: int, location: string}
     */
    private function validateRecord(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('channel_allocation_campaigns', 'name')->ignore($id)],
            'fte' => ['required', 'integer', 'min:0'],
            'location' => ['required', 'string', Rule::in(OperationCatalog::locationNames())],
        ]);

        $data['location'] = OperationCatalog::canonicalLocationName($data['location']) ?? $data['location'];

        return $data;
    }
}
