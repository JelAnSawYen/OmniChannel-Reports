<?php

namespace App\Http\Controllers;

use App\Models\ChannelAllocationCampaign;
use App\Models\PdcGroup;
use App\Models\PdcServer;
use App\Services\AuditLogger;
use App\Services\PdcServerImportService;
use App\Services\XlsxService;
use App\Support\OperationCatalog;
use App\Support\PdcEndorseDate;
use App\Support\PublicError;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PdcServerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $perPage = (int) $request->query('per_page', 10);
        if (! in_array($perPage, [5, 10, 25, 50], true)) {
            $perPage = 10;
        }

        $query = PdcGroup::query()->with(['campaign', 'servers'])->orderBy('id');
        if ($search !== '') {
            $query->where(function ($groups) use ($search) {
                $groups->where('location', 'like', "%{$search}%")
                    ->orWhere('dns', 'like', "%{$search}%")
                    ->orWhereHas('campaign', fn ($campaigns) => $campaigns->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('servers', function ($servers) use ($search) {
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
        $campaigns = ChannelAllocationCampaign::optionsForDropdown();
        $locations = OperationCatalog::locations();
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
        AuditLogger::log('Added', 'PDC Servers', 'PDC campaign group added', null, $request);

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

    public function export(Request $request, XlsxService $xlsx): BinaryFileResponse|RedirectResponse
    {
        $search = trim((string) $request->query('search'));
        $query = PdcGroup::query()->with(['campaign', 'servers'])->orderBy('id');
        if ($search !== '') {
            $query->where(function ($groups) use ($search) {
                $groups->where('location', 'like', "%{$search}%")
                    ->orWhere('dns', 'like', "%{$search}%")
                    ->orWhereHas('campaign', fn ($campaigns) => $campaigns->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('servers', function ($servers) use ($search) {
                        $servers->where('hostname', 'like', "%{$search}%")
                            ->orWhere('ip_address', 'like', "%{$search}%");
                    });
            });
        }

        $includeSecrets = $request->user()?->canExportGatewaySecrets() ?? false;
        $headers = array_values(app(PdcServerImportService::class)->fields());
        $rows = [];

        foreach ($query->get() as $group) {
            $campaignName = $group->campaign?->name;
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
                'message' => $exception instanceof \RuntimeException
                    ? $exception->getMessage()
                    : PublicError::failed('Preview', $exception),
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
                'message' => PublicError::failed('Import', $exception),
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
            'location' => ['nullable', 'string', Rule::in(array_values(OperationCatalog::locations()))],
            'date_endorse' => ['nullable', 'string'],
            'dns' => ['nullable', 'string', 'max:2000'],
        ]);

        $campaign = ChannelAllocationCampaign::fromFormValue(
            $request->input('campaign'),
            $request->input('campaign_id')
        );
        if ($campaign === null) {
            throw ValidationException::withMessages([
                'campaign' => 'Please select or type a campaign.',
            ]);
        }

        validator(
            ['campaign_id' => $campaign->id],
            ['campaign_id' => [Rule::unique('pdc_groups', 'campaign_id')->ignore($id)]]
        )->validate();

        $data = [
            'campaign_id' => $campaign->id,
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
