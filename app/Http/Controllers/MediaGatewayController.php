<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMediaGatewayRequest;
use App\Http\Requests\UpdateMediaGatewayRequest;
use App\Models\MediaGateway;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use App\Services\XlsxService;
use App\Support\InventoryImportCatalog;
use App\Support\PublicError;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Route;

class MediaGatewayController extends Controller
{
    use HandlesInventoryImport;
    private const SORTABLE_COLUMNS = ['id', 'ip_address', 'site_code', 'plan', 'port', 'network', 'device_function', 'site_name', 'username'];

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $sortBy = in_array($request->query('sort_by'), self::SORTABLE_COLUMNS, true)
            ? $request->query('sort_by')
            : 'id';
        $sortDir = strtolower((string) $request->query('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $perPage = in_array((int) $request->query('per_page', 10), [5, 10, 25, 50], true)
            ? (int) $request->query('per_page', 10)
            : 10;

        $mediaGateways = $this->buildQuery($request)
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->appends($request->query());

        $resource = $this->resource();
        $data = compact('mediaGateways', 'search', 'sortBy', 'sortDir', 'perPage', 'resource');
        if ($this->isGsm()) {
            $data['locations'] = \App\Support\OperationCatalog::locations();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'records' => $mediaGateways->getCollection()
                    ->values()
                    ->map(fn (MediaGateway $gateway) => $this->jsonRecord($gateway))
                    ->values(),
                'pagination' => [
                    'current_page' => $mediaGateways->currentPage(),
                    'last_page' => $mediaGateways->lastPage(),
                    'per_page' => $mediaGateways->perPage(),
                    'total' => $mediaGateways->total(),
                    'from' => $mediaGateways->firstItem() ?? 0,
                    'to' => $mediaGateways->lastItem() ?? 0,
                ],
            ]);
        }

        return view('media-gateways.index', $data);
    }

    private function jsonRecord(MediaGateway $gateway): array
    {
        return [
            'id' => $gateway->id,
            'ip_address' => $gateway->ip_address,
            'site_code' => $gateway->site_code,
            'plan' => $gateway->plan,
            'port' => $gateway->port,
            'network' => $gateway->network,
            'device_function' => $gateway->device_function,
            'site_name' => $gateway->site_name,
            'username' => $gateway->username,
            'password' => $gateway->password,
            'database' => $gateway->database,
        ];
    }

    public function store(StoreMediaGatewayRequest $request)
    {
        $this->denyStandardMutation();
        $data = $request->validated();

        if (
            MediaGateway::query()
                ->where(function ($query) use ($data) {
                    $query->where('site_code', $data['site_code'])
                        ->orWhere('ip_address', $data['ip_address']);
                })
                ->exists()
        ) {
            $message = 'A Media Gateway with the same Site Code or IP Address already exists.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 409);
            }

            return back()->with('error', $message)->withInput();
        }

        $gateway = MediaGateway::create($data);

        AuditLogger::log(
            'Added',
            'Media Gateways',
            'Added gateway '.$gateway->site_code,
            $gateway->id,
            $request
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Media Gateway added successfully.',
                'record' => $gateway,
            ], 201);
        }

        return Redirect::route($this->indexRoute())
            ->with('success', $this->resource()['entity'].' added successfully.');
    }

    public function update(UpdateMediaGatewayRequest $request, MediaGateway $mediaGateway)
    {
        $this->denyStandardMutation();
        $data = $request->validated();

        $duplicate = MediaGateway::where(function ($query) use ($data) {
            $query->where('site_code', $data['site_code'])
                ->orWhere('ip_address', $data['ip_address']);
        })
            ->where('id', '!=', $mediaGateway->id)
            ->exists();

        if ($duplicate) {
            return response()->json([
                'message' => 'Another Media Gateway already uses that Site Code or IP Address.',
            ], 409);
        }

        $mediaGateway->update($data);

        AuditLogger::log(
            'Updated',
            'Media Gateways',
            'Updated gateway '.$mediaGateway->site_code,
            $mediaGateway->id,
            $request
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Media Gateway updated successfully.',
                'record' => $mediaGateway->fresh(),
            ]);
        }

        return Redirect::route($this->indexRoute())
            ->with('success', $this->resource()['entity'].' updated successfully.');
    }

    public function destroy(Request $request, MediaGateway $mediaGateway)
    {
        abort_unless($request->user()?->hasPermission('media.delete'), 403, 'You do not have permission to perform this action.');
        $code = $mediaGateway->site_code;
        $id = $mediaGateway->id;

        $mediaGateway->delete();

        AuditLogger::log(
            'Deleted',
            'Media Gateways',
            'Deleted gateway '.$code,
            $id,
            $request
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Media Gateway deleted successfully.',
                'total' => MediaGateway::count(),
            ]);
        }

        return Redirect::route($this->indexRoute())
            ->with('success', $this->resource()['entity'].' deleted successfully.');
    }

    public function export(Request $request, XlsxService $xlsx)
    {
        $query = $this->buildQuery($request)
            ->orderBy($this->resolveSortColumn($request), $this->resolveSortDirection($request));

        try {
            $includeSecrets = $request->user()?->canExportGatewaySecrets() ?? false;
            $headers = [
                'Hostname IP',
                'Serial Number',
                'Plan',
                'Port',
                'Network',
                'Function',
                'Site',
            ];
            if ($includeSecrets) {
                $headers[] = 'User';
                $headers[] = 'Password';
            }
            $path = $xlsx->export(
                $headers,
                $query->cursor()->map(function ($gateway) use ($includeSecrets) {
                    $row = [
                        $gateway->ip_address,
                        $gateway->site_code,
                        $gateway->plan,
                        $gateway->port,
                        $gateway->network,
                        $gateway->device_function,
                        $gateway->site_name,
                    ];
                    if ($includeSecrets) {
                        $row[] = $gateway->username;
                        $row[] = $gateway->password;
                    }

                    return $row;
                }),
                $this->isGsm() ? 'gsm-gateways.xlsx' : 'media-gateways.xlsx'
            );
        } catch (\Throwable $exception) {
            NotificationService::exportFailed($this->resource()['plural'], 'Export failed.', $this->indexRoute());
            return back()->with('error', PublicError::failed('Export', $exception));
        }

        AuditLogger::log(
            'Exported',
            'Media Gateways',
            'Exported Media Gateway data to Excel',
            null,
            $request
        );

        return response()
            ->download($path, $this->isGsm() ? 'gsm-gateways.xlsx' : 'media-gateways.xlsx')
            ->deleteFileAfterSend(true);
    }

    private function denyStandardMutation(): void
    {
        if ($requestUser = auth()->user()) {
            if (! $requestUser->canMutateGateways()) {
                abort(403, 'You do not have permission to perform this action.');
            }
        }
    }

    private function isGsm(): bool
    {
        return str_starts_with((string) Route::currentRouteName(), 'gsm-gateways');
    }

    private function indexRoute(): string
    {
        return $this->isGsm() ? 'gsm-gateways.index' : 'media-gateways.index';
    }

    private function resource(): array
    {
        if ($this->isGsm()) {
            return [
                'entity' => 'GSM Gateway',
                'plural' => 'GSM Gateways',
                'title' => 'GSM Gateway Server List',
                'subtitle' => 'Manage and monitor all GSM gateways in the system.',
                'search' => 'Search GSM Gateways',
                'empty' => 'No GSM Gateways Found',
                'index' => 'gsm-gateways.index',
                'store' => 'gsm-gateways.store',
                'export' => 'gsm-gateways.export',
                'base' => '/gsm-gateways',
            ];
        }

        return [
            'entity' => 'Media Gateway',
            'plural' => 'Media Gateways',
            'title' => 'Media Gateway Server List',
            'subtitle' => 'Manage and monitor all media gateways in the system.',
            'search' => 'Search Media Gateways',
            'empty' => 'No Media Gateways Found',
            'index' => 'media-gateways.index',
            'store' => 'media-gateways.store',
            'export' => 'media-gateways.export',
            'base' => '/media-gateways',
        ];
    }

    private function buildQuery(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $query = MediaGateway::query();

        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('site_name', 'like', "%{$search}%")
                    ->orWhere('site_code', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
                    ->orWhere('plan', 'like', "%{$search}%")
                    ->orWhere('port', 'like', "%{$search}%")
                    ->orWhere('network', 'like', "%{$search}%")
                    ->orWhere('device_function', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    private function resolveSortColumn(Request $request): string
    {
        $sort = $request->query('sort_by');

        return in_array($sort, self::SORTABLE_COLUMNS, true) ? $sort : 'id';
    }

    protected function inventoryImportConfig(Request $request): array
    {
        return InventoryImportCatalog::gateway($this->isGsm() ? 'gsm-gateways' : 'media-gateways');
    }

    private function resolveSortDirection(Request $request): string
    {
        return strtolower((string) $request->query('sort_dir', 'asc')) === 'desc'
            ? 'desc'
            : 'asc';
    }
}
