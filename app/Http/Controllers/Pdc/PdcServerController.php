<?php

namespace App\Http\Controllers\Pdc;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HandlesBulkDestroy;
use App\Models\ChannelAllocationCampaign;
use App\Models\PdcGroup;
use App\Models\PdcServer;
use App\Services\Logs\AuditLogger;
use App\Services\Pdc\PdcServerImportService;
use App\Services\XlsxService;
use App\Support\NaturalSort;
use App\Support\OperationCatalog;
use App\Support\PdcEndorseDate;
use App\Support\PublicError;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PdcServerController extends Controller
{
    use HandlesBulkDestroy;

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $perPage = (int) $request->query('per_page', 10);
        if (! in_array($perPage, [5, 10, 25, 50], true)) {
            $perPage = 10;
        }

        $query = PdcGroup::query()->with(['campaign', 'servers']);
        $this->applyGroupOrder($query);
        if ($search !== '') {
            $query->where(function ($groups) use ($search) {
                $groups->where('location', 'like', "%{$search}%")
                    ->orWhere('dns', 'like', "%{$search}%")
                    ->orWhereHas('campaign', fn ($campaigns) => $campaigns->where('name', 'like', "%{$search}%"));
                if ($this->pdcGroupsHaveCampaignName()) {
                    $groups->orWhere('campaign_name', 'like', "%{$search}%");
                }
                $groups->orWhereHas('servers', function ($servers) use ($search) {
                    $servers->where('hostname', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhere('os', 'like', "%{$search}%")
                        ->orWhere('ram', 'like', "%{$search}%")
                        ->orWhere('cpu', 'like', "%{$search}%")
                        ->orWhere('storage', 'like', "%{$search}%")
                        ->orWhere('admin_username', 'like', "%{$search}%");
                });
            });
        }

        $groups = (clone $query)->paginate($perPage)->withQueryString();
        $campaigns = ChannelAllocationCampaign::masterOptionsForDropdown();
        $locations = OperationCatalog::pdcSiteNames();
        $canRevealSecrets = (bool) $request->user()?->canExportGatewaySecrets();

        return view('pdc-servers.index', [
            'groups' => $groups,
            'campaigns' => $campaigns,
            'locations' => $locations,
            'search' => $search,
            'perPage' => $perPage,
            'canRevealSecrets' => $canRevealSecrets,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateGroup($request);
        PdcGroup::query()->create($data);
        AuditLogger::log('Created', 'PDC Servers', 'PDC campaign group added', null, $request);

        return back()->with('success', 'PDC campaign added successfully.');
    }

    public function update(Request $request, PdcGroup $group): RedirectResponse
    {
        $data = $this->validateGroup($request, $group->id);
        $group->update($data);
        $group->servers()->update(['location' => $data['location']]);
        AuditLogger::log('Updated', 'PDC Servers', 'PDC campaign group updated', $group->id, $request);

        return back()->with('success', 'PDC campaign updated successfully.');
    }

    public function destroy(Request $request, PdcGroup $group): RedirectResponse
    {
        $id = $group->id;
        $group->delete();
        AuditLogger::log('Deleted', 'PDC Servers', 'PDC campaign group deleted', $id, $request);

        return back()->with('success', 'PDC campaign deleted successfully.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        foreach ($this->validatedBulkIds($request) as $id) {
            $group = PdcGroup::query()->find($id);
            if (! $group) {
                continue;
            }
            $group->delete();
            AuditLogger::log('Deleted', 'PDC Servers', 'PDC campaign group deleted', $id, $request);
        }

        return back()->with('success', 'Selected PDC campaigns deleted successfully.');
    }

    public function storeServer(Request $request, PdcGroup $group): RedirectResponse
    {
        $data = $this->validateServer($request);
        $data['pdc_group_id'] = $group->id;
        $data['location'] = $group->location;
        $data['status'] = $data['status'] ?? 'Active';
        PdcServer::query()->create($data);
        AuditLogger::log('Added', 'PDC Servers', 'PDC server added', $group->id, $request);

        return back()->with('success', 'Record added successfully.');
    }

    public function updateServer(Request $request, PdcGroup $group, PdcServer $server): RedirectResponse
    {
        abort_unless($server->pdc_group_id === $group->id, 404);
        $data = $this->validateServer($request, $server->id);
        unset($data['pdc_group_id']);
        $data['location'] = $group->location;
        if ($this->passwordUnchanged($request, 'password')) {
            unset($data['password']);
        }
        if ($this->passwordUnchanged($request, 'sql_db_password')) {
            unset($data['sql_db_password']);
        }
        $server->update($data);
        AuditLogger::log('Updated', 'PDC Servers', 'PDC server updated', $server->id, $request);

        return back()->with('success', 'Record updated successfully.');
    }

    public function destroyServer(Request $request, PdcGroup $group, PdcServer $server): RedirectResponse
    {
        abort_unless($server->pdc_group_id === $group->id, 404);
        $id = $server->id;
        $server->delete();
        AuditLogger::log('Deleted', 'PDC Servers', 'PDC server deleted', $id, $request);

        return back()->with('success', 'Record deleted successfully.');
    }

    public function bulkDestroyServers(Request $request, PdcGroup $group): RedirectResponse
    {
        foreach ($this->validatedBulkIds($request) as $id) {
            $server = PdcServer::query()->whereKey($id)->where('pdc_group_id', $group->id)->first();
            if (! $server) {
                continue;
            }
            $server->delete();
            AuditLogger::log('Deleted', 'PDC Servers', 'PDC server deleted', $id, $request);
        }

        return back()->with('success', 'Selected records deleted successfully.');
    }

    public function export(Request $request, XlsxService $xlsx): BinaryFileResponse|RedirectResponse
    {
        $search = trim((string) $request->query('search'));
        $query = PdcGroup::query()->with(['campaign', 'servers']);
        $this->applyGroupOrder($query);
        if ($search !== '') {
            $query->where(function ($groups) use ($search) {
                $groups->where('location', 'like', "%{$search}%")
                    ->orWhere('dns', 'like', "%{$search}%")
                    ->orWhereHas('campaign', fn ($campaigns) => $campaigns->where('name', 'like', "%{$search}%"));
                if ($this->pdcGroupsHaveCampaignName()) {
                    $groups->orWhere('campaign_name', 'like', "%{$search}%");
                }
                $groups->orWhereHas('servers', function ($servers) use ($search) {
                    $servers->where('hostname', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%");
                });
            });
        }

        $includeSecrets = $request->user()?->canExportGatewaySecrets() ?? false;
        $headers = array_values(app(PdcServerImportService::class)->fields());
        $rows = [];

        foreach ($query->get() as $group) {
            $campaignName = $group->campaignName();
            $date = $group->date_endorse ? PdcEndorseDate::display($group->date_endorse->format('Y-m-d')) : '';
            if ($group->servers->isEmpty()) {
                $rows[] = $this->exportRow($campaignName, $group, $date, null, $includeSecrets);

                continue;
            }
            foreach ($group->servers as $index => $server) {
                $rows[] = $this->exportRow(
                    $index === 0 ? $campaignName : '',
                    $index === 0 ? $group : null,
                    $index === 0 ? $date : '',
                    $server,
                    $includeSecrets
                );
            }
        }

        try {
            $path = $xlsx->export($headers, $rows, 'pdc-servers.xlsx');
        } catch (\Throwable $exception) {
            return back()->with('error', PublicError::failed('Export', $exception));
        }

        AuditLogger::log('Exported', 'PDC Servers', 'Exported PDC Servers records', null, $request);

        return response()->download($path, 'pdc-servers.xlsx')->deleteFileAfterSend(true);
    }

    public function importTemplate(XlsxService $xlsx, PdcServerImportService $import): BinaryFileResponse|RedirectResponse
    {
        try {
            $path = $xlsx->export($import->templateHeaders(), $import->templateRows(), 'pdc-servers-template.xlsx');
        } catch (\Throwable $exception) {
            return back()->with('error', PublicError::failed('Template download', $exception));
        }

        return response()->download($path, 'pdc-servers-template.xlsx')->deleteFileAfterSend(true);
    }

    public function importPreview(Request $request, PdcServerImportService $import, XlsxService $xlsx)
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
                'message' => PublicError::validationOrFailed('Preview', $exception),
            ], 422);
        }

        if (! ($request->user()?->canExportGatewaySecrets() ?? false)) {
            foreach ($preview['rows'] as &$row) {
                $row['password'] = '';
                $row['sql_db_password'] = '';
            }
            unset($row);
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

    public function importConfirm(Request $request, PdcServerImportService $import)
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
                'message' => PublicError::validationOrFailed('Import', $exception),
            ], 422);
        }

        $import->forget();
        AuditLogger::log('Imported', 'PDC Servers', 'Imported '.$count.' PDC Servers records', null, $request);

        return response()->json([
            'ok' => true,
            'records' => $count,
        ]);
    }

    public function importErrors(Request $request, PdcServerImportService $import, XlsxService $xlsx): BinaryFileResponse|RedirectResponse
    {
        $stored = $import->previewFromSession((string) $request->query('token'));
        if (! is_array($stored)) {
            return back()->with('error', 'No import preview is available. Upload and validate the file again.');
        }

        $headers = array_merge(['Row #'], $stored['headers'] ?? $import->templateHeaders(), ['Status', 'Error Reason']);
        $rows = [];
        $includeSecrets = $request->user()?->canExportGatewaySecrets() ?? false;
        foreach ($stored['rows'] as $row) {
            if ($row['valid']) {
                continue;
            }
            $line = [$row['row']];
            foreach (array_keys($import->fields()) as $field) {
                if (! $includeSecrets && in_array($field, PdcServerImportService::PASSWORD_FIELDS, true)) {
                    $line[] = '';

                    continue;
                }
                $line[] = $row[$field] ?? '';
            }
            $line[] = $row['status'];
            $line[] = $row['error'];
            $rows[] = $line;
        }

        try {
            $path = $xlsx->export($headers, $rows, 'pdc-servers-import-errors.xlsx');
        } catch (\Throwable $exception) {
            return back()->with('error', PublicError::failed('Error report download', $exception));
        }

        return response()->download($path, 'pdc-servers-import-errors.xlsx')->deleteFileAfterSend(true);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateGroup(Request $request, ?int $id = null): array
    {
        $request->validate([
            'campaign' => ['required_without:campaign_id', 'nullable', 'string', 'max:255'],
            'campaign_id' => ['required_without:campaign', 'nullable', 'integer'],
            'location' => ['nullable', 'string', Rule::in(OperationCatalog::pdcSiteNames())],
            'date_endorse' => ['nullable', 'string'],
            'dns' => ['nullable', 'string', 'max:2000'],
        ]);

        $campaign = $this->pdcCampaignFields(
            $request->input('campaign'),
            $request->input('campaign_id'),
            $id
        );

        $data = [
            'campaign_id' => $campaign['campaign_id'],
            'campaign_name' => $campaign['campaign_name'],
            'location' => $request->input('location'),
            'date_endorse' => $request->input('date_endorse'),
            'dns' => $request->input('dns'),
        ];

        $parsed = PdcEndorseDate::parse($data['date_endorse'] ?? '');
        if (! $parsed['valid']) {
            throw ValidationException::withMessages([
                'date_endorse' => 'Date Endorse must be a valid date on or after 1/1/2000.',
            ]);
        }
        $data['date_endorse'] = $parsed['iso'];
        $data['dns'] = $this->nullableString($request->input('dns'));
        $data['location'] = $this->nullableString($data['location'] ?? null);

        return $data;
    }

    /**
     * PDC Servers may use a Master Campaign or a name that exists only here.
     * A missing Master Campaign is never created.
     *
     * @return array{campaign_id: int|null, campaign_name: string|null}
     */
    private function pdcCampaignFields(?string $name, mixed $id, ?int $ignoreGroupId = null): array
    {
        $name = trim((string) $name);
        $master = null;
        if ($name !== '') {
            $master = ChannelAllocationCampaign::masterByName($name);
        } elseif ((int) $id > 0) {
            $master = ChannelAllocationCampaign::query()->listedInCampaigns()->find((int) $id);
        }

        if ($master) {
            $this->assertPdcCampaignAvailable($master->name, $master->id, $ignoreGroupId);

            return [
                'campaign_id' => $master->id,
                'campaign_name' => null,
            ];
        }

        if ($name === '') {
            throw ValidationException::withMessages([
                'campaign' => 'Please select or type a campaign.',
            ]);
        }

        $this->assertPdcCampaignAvailable($name, null, $ignoreGroupId);

        return [
            'campaign_id' => null,
            'campaign_name' => $name,
        ];
    }

    private function assertPdcCampaignAvailable(string $name, ?int $campaignId, ?int $ignoreGroupId): void
    {
        $taken = PdcGroup::query()
            ->with('campaign')
            ->when($ignoreGroupId, fn ($groups) => $groups->whereKeyNot($ignoreGroupId))
            ->get()
            ->contains(function (PdcGroup $group) use ($name, $campaignId) {
                if ($campaignId && (int) $group->campaign_id === $campaignId) {
                    return true;
                }

                return strcasecmp($group->campaignName(), $name) === 0;
            });

        if ($taken) {
            throw ValidationException::withMessages([
                'campaign' => 'Campaign already exists on PDC Servers.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateServer(Request $request, ?int $id = null): array
    {
        $validator = validator($request->all(), [
            'hostname' => ['required', 'string', 'max:255', Rule::unique('pdc_servers', 'hostname')->ignore($id)],
            'ip_address' => ['required', 'ipv4', Rule::unique('pdc_servers', 'ip_address')->ignore($id)],
            'os' => ['nullable', 'string', 'max:255'],
            'ram' => ['nullable', 'string', 'max:100'],
            'cpu' => ['nullable', 'string', 'max:100'],
            'storage' => ['nullable', 'string', 'max:100'],
            'admin_username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:2000'],
            'sql_db_password' => ['nullable', 'string', 'max:2000'],
        ], [
            'ip_address.ipv4' => 'Source IP must be a valid IPv4 address.',
            'ip_address.unique' => 'Source IP already exists. Each PDC server must have a unique Source IP.',
        ]);

        $data = $validator->validate();
        foreach (['os', 'ram', 'cpu', 'storage', 'admin_username'] as $field) {
            $data[$field] = $this->nullableString($data[$field] ?? null);
        }
        if (array_key_exists('password', $data) && $data['password'] === '') {
            $data['password'] = null;
        }
        if (array_key_exists('sql_db_password', $data) && $data['sql_db_password'] === '') {
            $data['sql_db_password'] = null;
        }

        return $data;
    }

    private function passwordUnchanged(Request $request, string $field): bool
    {
        $value = $request->input($field);

        return $value === null || $value === '';
    }

    private function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return $value === null || $value === '' ? null : (string) $value;
    }

    private function applyGroupOrder($query): void
    {
        $grammar = $query->getQuery()->getGrammar();
        $campaignNameSql = '(SELECT '.$grammar->wrap('channel_allocation_campaigns.name')
            .' FROM '.$grammar->wrapTable('channel_allocation_campaigns')
            .' WHERE '.$grammar->wrap('channel_allocation_campaigns.id')
            .' = '.$grammar->wrap('pdc_groups.campaign_id').')';
        if ($this->pdcGroupsHaveCampaignName()) {
            NaturalSort::applyRaw($query, 'COALESCE('.$campaignNameSql.', '.$grammar->wrap('pdc_groups.campaign_name').')');
        } else {
            NaturalSort::applyRelated($query, 'channel_allocation_campaigns', 'name', 'pdc_groups.campaign_id');
        }
        NaturalSort::apply($query, 'pdc_groups.location');
        $query->orderBy('pdc_groups.id');
    }

    private function pdcGroupsHaveCampaignName(): bool
    {
        return Schema::hasColumn((new PdcGroup)->getTable(), 'campaign_name');
    }

    /**
     * @return list<mixed>
     */
    private function exportRow(?string $campaignName, ?PdcGroup $group, string $date, ?PdcServer $server, bool $includeSecrets): array
    {
        return [
            $campaignName,
            $group?->location,
            $date,
            $group?->dns,
            $server?->hostname,
            $server?->ip_address,
            $server?->os,
            $server?->ram,
            $server?->cpu,
            $server?->storage,
            $server?->admin_username,
            $includeSecrets ? $server?->password : '',
            $includeSecrets ? $server?->sql_db_password : '',
        ];
    }
}
