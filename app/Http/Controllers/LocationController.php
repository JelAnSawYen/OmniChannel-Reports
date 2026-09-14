<?php
namespace App\Http\Controllers;

use App\Models\MediaGateway;
use App\Services\AuditLogger;
use App\Services\XlsxService;
use App\Support\InventoryImportCatalog;
use App\Support\OperationCatalog;
use App\Support\ProgramLocationStatus;
use App\Support\PublicError;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LocationController extends Controller
{
    use HandlesInventoryImport;
    private const COLUMNS = [
        'site_name' => 'Site Name',
        'site_code' => 'Site Code',
        'ip_address' => 'IP Address',
        'username' => 'Username',
        'database' => 'Database',
    ];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $sites = collect(OperationCatalog::locationMapSites())->map(function (array $site, string $slug) {
            return [
                'slug' => $slug,
                'name' => $site['name'],
                'address' => $site['address'],
                'lat' => $site['lat'],
                'lng' => $site['lng'],
                'available' => ProgramLocationStatus::assigned($slug, (bool) ($site['assigned'] ?? false)),
            ];
        })->values();

        return view('locations.index', [
            'sites' => $sites,
            'search' => $search,
            'canConfigure' => (bool) $request->user()?->hasPermission('media.edit'),
            'statusUpdateUrlTemplate' => url('/program-location/__SLUG__/status'),
        ]);
    }

    public function show(string $location): View
    {
        $name = $this->locationName($location);
        $search = trim((string) request('search'));
        $perPage = (int) request('per_page', 10);
        if (! in_array($perPage, [5, 10, 25, 50], true)) {
            $perPage = 10;
        }

        $records = $this->query($name, $search)
            ->orderBy('site_code')
            ->paginate($perPage)
            ->withQueryString();

        return view('locations.show', [
            'locationSlug' => $location,
            'locationName' => $name,
            'locations' => OperationCatalog::locations(),
            'columns' => self::COLUMNS,
            'records' => $records,
            'search' => $search,
            'perPage' => $perPage,
        ]);
    }

    public function updateStatus(Request $request, string $location)
    {
        $this->locationName($location);
        $this->guardMutation('media.edit');

        $data = $request->validate([
            'assigned' => ['required', 'boolean'],
        ]);

        ProgramLocationStatus::set($location, (bool) $data['assigned']);
        AuditLogger::log(
            'Updated',
            'Program Location',
            'Updated gateway assignment status for '.$location,
            null,
            $request
        );

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'assigned' => (bool) $data['assigned'],
            ]);
        }

        return back()->with('success', 'Location status updated successfully.');
    }

    public function store(Request $request, string $location)
    {
        $name = $this->locationName($location);
        $this->guardMutation('media.create');

        $data = $this->validatePayload($request, $name);

        if ($error = $this->duplicateError($data)) {
            return back()->with('error', $error)->withInput();
        }

        $gateway = MediaGateway::create($data);
        AuditLogger::log('Added', 'GSM Gateways', 'Added gateway '.$gateway->site_code.' to '.$data['site_name'], $gateway->id, $request);

        return back()->with('success', 'GSM Gateway added to '.$data['site_name'].' successfully.');
    }

    public function update(Request $request, string $location, string $gateway)
    {
        $name = $this->locationName($location);
        $this->guardMutation('media.edit');

        $record = $this->gatewayInLocation($name, (int) $gateway);
        $data = $this->validatePayload($request, $name, $record->id);

        if ($error = $this->duplicateError($data, $record->id)) {
            return back()->with('error', $error)->withInput();
        }

        $record->update($data);
        AuditLogger::log('Updated', 'GSM Gateways', 'Updated gateway '.$record->site_code.' in '.$data['site_name'], $record->id, $request);

        return back()->with('success', 'GSM Gateway updated successfully.');
    }

    public function destroy(Request $request, string $location, string $gateway)
    {
        $name = $this->locationName($location);
        $this->guardMutation('media.delete');

        $record = $this->gatewayInLocation($name, (int) $gateway);
        $code = $record->site_code;
        $id = $record->id;
        $record->delete();

        AuditLogger::log('Deleted', 'GSM Gateways', 'Deleted gateway '.$code, $id, $request);

        return back()->with('success', 'GSM Gateway deleted successfully.');
    }

    public function export(Request $request, string $location, XlsxService $xlsx)
    {
        $name = $this->locationName($location);
        $search = trim((string) $request->query('search'));

        try {
            $columns = $this->exportColumns($request->user());
            $sequence = 0;
            $path = $xlsx->export(
                array_merge(['Id'], array_values($columns)),
                $this->query($name, $search)
                    ->orderBy('site_code')
                    ->get()
                    ->map(function (MediaGateway $gateway) use ($columns, &$sequence) {
                        $sequence++;

                        return array_merge([$sequence], collect(array_keys($columns))->map(fn ($field) => $gateway->{$field})->all());
                    }),
                $location.'.xlsx'
            );
        } catch (\Throwable $exception) {
            return back()->with('error', PublicError::failed('Export', $exception));
        }

        AuditLogger::log('Exported', 'GSM Gateways', 'Exported '.$name.' GSM Gateway records', null, $request);

        return response()->download($path, $location.'-gsm-gateways.xlsx')->deleteFileAfterSend(true);
    }

    private function query(string $name, string $search)
    {
        $query = MediaGateway::query()->where('site_name', $name);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                foreach (array_keys(self::COLUMNS) as $index => $field) {
                    $index === 0
                        ? $q->where($field, 'like', "%{$search}%")
                        : $q->orWhere($field, 'like', "%{$search}%");
                }
            });
        }

        return $query;
    }

    private function locationName(string $location): string
    {
        $names = OperationCatalog::locations();
        abort_unless(isset($names[$location]), 404);

        return $names[$location];
    }

    private function gatewayInLocation(string $locationName, int $id): MediaGateway
    {
        return MediaGateway::query()
            ->where('site_name', $locationName)
            ->whereKey($id)
            ->firstOrFail();
    }

    private function exportColumns($user): array
    {
        if ($user?->canExportGatewaySecrets()) {
            return self::COLUMNS;
        }

        return array_diff_key(self::COLUMNS, array_flip(['username', 'database']));
    }

    private function guardMutation(string $permission): void
    {
        $user = auth()->user();
        abort_unless($user && $user->hasPermission($permission), 403, 'You do not have permission to perform this action.');
    }

    private function validatePayload(Request $request, string $locationName, ?int $id = null): array
    {
        $request->merge([
            'site_name' => trim((string) $request->input('site_name')) !== ''
                ? $request->input('site_name')
                : $locationName,
        ]);

        return $request->validate($this->rules($id));
    }

    private function rules(?int $id = null): array
    {
        return [
            'site_name' => ['required', 'string', 'max:255', Rule::in(array_values(OperationCatalog::locations()))],
            'site_code' => ['required', 'string', 'max:255', Rule::unique('media_gateways', 'site_code')->ignore($id)],
            'ip_address' => ['required', 'ip', Rule::unique('media_gateways', 'ip_address')->ignore($id)],
            'username' => ['required', 'string', 'max:255'],
            'database' => ['required', 'string', 'max:255'],
        ];
    }

    private function duplicateError(array $data, ?int $id = null): ?string
    {
        $exists = MediaGateway::query()
            ->where(function ($query) use ($data) {
                $query->where('site_code', $data['site_code'] ?? '')
                    ->orWhere('ip_address', $data['ip_address'] ?? '');
            })
            ->when($id, fn ($query) => $query->where('id', '!=', $id))
            ->exists();

        return $exists
            ? 'A GSM Gateway with the same Site Code or IP Address already exists.'
            : null;
    }

    protected function inventoryImportConfig(Request $request): array
    {
        $slug = (string) $request->route('location');

        return InventoryImportCatalog::location($slug, $this->locationName($slug));
    }
}
