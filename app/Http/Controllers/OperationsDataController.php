<?php
namespace App\Http\Controllers;

use App\Models\MediaGateway;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use App\Services\XlsxService;
use App\Support\InventoryImportCatalog;
use App\Support\OperationCatalog;
use App\Support\PublicError;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OperationsDataController extends Controller
{
    use HandlesInventoryImport;
    public function index(Request $request, string $module)
    {
        $config=$this->config($module); $query=$config['model']::query();
        if ($search=trim((string)$request->query('search'))) {
            $query->where(function($q) use ($search,$config){ foreach(array_keys($config['columns']) as $i=>$field){ $i===0?$q->where($field,'like',"%$search%"): $q->orWhere($field,'like',"%$search%"); }});
        }
        if ($status=trim((string)$request->query('status'))) {
            $query->where('status', $status);
        }
        $perPage=(int)$request->query('per_page',10);
        if (!in_array($perPage,[5,10,25,50],true)) {
            $perPage=10;
        }
        $search=$search ?: '';
        $records=$query->latest()->paginate($perPage)->withQueryString();
        $gateways=MediaGateway::orderBy('site_code')->get(['site_code','site_name']);
        $statusOptions=$this->statusOptions($module);
        return view('operations.index', compact('config','module','records','gateways','statusOptions','search','perPage'));
    }

    public function store(Request $request, string $module)
    {
        $config=$this->config($module); $data=$request->validate($this->rules($module));
        if ($error=$this->integrityError($module,$data)) {
            return back()->with('error',$error)->withInput();
        }
        $record=$config['model']::create($data); AuditLogger::log('Added',$config['title'],$config['title'].' record added', $record->id,$request);
        return back()->with('success',$config['title'].' record added successfully.');
    }

    public function update(Request $request)
    {
        [$config, $module, $id, $record] = $this->resolveRecord($request);
        $data = $request->validate($this->rules($module, $id));
        if ($error = $this->integrityError($module, $data)) {
            return back()->with('error', $error)->withInput();
        }
        $record->update($data);
        AuditLogger::log('Updated', $config['title'], $config['title'].' record updated', $record->id, $request);

        return back()->with('success', $config['title'].' record updated successfully.');
    }

    public function destroy(Request $request)
    {
        [$config, $module, $id, $record] = $this->resolveRecord($request);
        $record->delete();
        AuditLogger::log('Deleted', $config['title'], $config['title'].' record deleted', $id, $request);

        return back()->with('success', $config['title'].' record deleted successfully.');
    }

    public function export(Request $request, string $module, XlsxService $xlsx)
    {
        $config=$this->config($module);
        $query=$config['model']::query();
        if ($search=trim((string)$request->query('search'))) {
            $query->where(function($q) use ($search,$config){ foreach(array_keys($config['columns']) as $i=>$field){ $i===0?$q->where($field,'like',"%$search%"): $q->orWhere($field,'like',"%$search%"); }});
        }
        if ($status=trim((string)$request->query('status'))) {
            $query->where('status', $status);
        }

        $headers = array_merge(['Id'], array_values($config['columns']));
        $fields = array_keys($config['columns']);
        try {
            $sequence = 0;
            $path = $xlsx->export(
                $headers,
                $query->latest()->get()->map(function ($record) use ($fields, &$sequence) {
                    $sequence++;

                    return array_merge([$sequence], collect($fields)->map(fn ($field) => $record->{$field})->all());
                }),
                $module.'.xlsx'
            );
        } catch (\Throwable $exception) {
            NotificationService::exportFailed($config['title'], 'Export failed.', $module);
            return back()->with('error', PublicError::failed('Export', $exception));
        }
        AuditLogger::log('Exported',$config['title'],'Exported '.$config['title'].' records',null,$request);

        return response()->download($path, $module.'.xlsx')->deleteFileAfterSend(true);
    }

    protected function inventoryImportConfig(Request $request): array
    {
        $module = (string) ($request->route()?->defaults['module'] ?? $request->route('module') ?? '');

        return InventoryImportCatalog::operation($module);
    }

    private function config(string $module): array
    {
        $modules = OperationCatalog::modules();
        abort_unless(isset($modules[$module]),404);
        return $modules[$module];
    }

    /**
     * Laravel injects {id} before the module default, so typed $module/$id
     * parameters were swapped (numeric id as string, module slug as $id).
     */
    private function resolveRecord(Request $request): array
    {
        $route = $request->route();
        $parameters = array_values($route?->parameters() ?? []);
        $defaults = $route?->defaults ?? [];
        $candidates = array_merge($parameters, array_values($defaults), [
            $route?->parameter('id'),
            $route?->parameter('module'),
            $defaults['module'] ?? null,
        ]);

        $modules = OperationCatalog::modules();
        $module = '';
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && isset($modules[$candidate])) {
                $module = $candidate;
                break;
            }
        }

        $id = 0;
        foreach ($candidates as $candidate) {
            if (is_numeric($candidate) && (int) $candidate > 0) {
                $id = (int) $candidate;
                break;
            }
        }

        abort_unless($module !== '' && $id > 0, 404);
        $config = $this->config($module);
        $record = $config['model']::query()->findOrFail($id);

        return [$config, $module, $id, $record];
    }

    private function statusOptions(string $module): array
    {
        return match($module){
            'telco-cost'=>['Active','Inactive','Expiring'],
            'channel-port'=>['Available','In Use','Disabled'],
            'defective-gsm'=>['Open','In Repair','Replaced','Closed'],
            default=>['Active','Inactive'],
        };
    }

    private function rules(string $module, ?int $id = null, array $context = []): array
    {
        $scope = fn (string $field) => array_key_exists($field, $context) ? $context[$field] : request($field);

        return match($module){
            'telco-cost'=>['provider'=>'required|string|max:255','site'=>'required|string|max:255','service_type'=>'required|string|max:255','monthly_cost'=>'required|numeric|min:0','contract_start'=>'nullable|date','contract_end'=>'nullable|date','status'=>['required',Rule::in($this->statusOptions($module))]],
            'channel-prefix'=>[
                'prefix'=>['required','string','max:100',Rule::unique('channel_prefixes','prefix')->ignore($id)],
                'channel'=>'nullable|string|max:100','description'=>'nullable|string|max:1000','status'=>['required',Rule::in($this->statusOptions($module))],
            ],
            'channel-port'=>[
                'port_number'=>['required','integer','min:1','max:65535',Rule::unique('channel_ports','port_number')->where(fn ($q) => $q->where('gateway', $scope('gateway')))->ignore($id)],
                'gateway'=>'nullable|string|max:255','channel'=>'nullable|string|max:100','status'=>['required',Rule::in($this->statusOptions($module))],'description'=>'nullable|string|max:1000',
            ],
            'network-prefix'=>[
                'network'=>'required|string|max:255',
                'prefix'=>['required','string','max:100',Rule::unique('network_prefixes','prefix')->where(fn ($q) => $q->where('network', $scope('network')))->ignore($id)],
                'gateway'=>'nullable|string|max:255','status'=>['required',Rule::in($this->statusOptions($module))],'description'=>'nullable|string|max:1000',
            ],
            'pdc-servers'=>[
                'hostname'=>['required','string','max:255',Rule::unique('pdc_servers','hostname')->ignore($id)],
                'ip_address'=>['required','ip',Rule::unique('pdc_servers','ip_address')->ignore($id)],
                'location'=>'nullable|string|max:255','role'=>'nullable|string|max:255','status'=>['required',Rule::in($this->statusOptions($module))],
            ],
            'sip-channels'=>[
                'channel'=>['required','string','max:255',Rule::unique('sip_channels','channel')->ignore($id)],
                'peer'=>'nullable|string|max:255','context'=>'nullable|string|max:255','codec'=>'nullable|string|max:100','status'=>['required',Rule::in($this->statusOptions($module))],
            ],
            'archive-recordings'=>[
                'server'=>'required|string|max:255','storage_path'=>'required|string|max:500','retention_days'=>'nullable|integer|min:1|max:3650','status'=>['required',Rule::in($this->statusOptions($module))],
            ],
            'globe-sim'=>[
                'sim_number'=>['required','string','max:50',Rule::unique('globe_sims','sim_number')->ignore($id)],
                'imsi'=>'nullable|string|max:50','assigned_to'=>'nullable|string|max:255','location'=>'nullable|string|max:255','status'=>['required',Rule::in($this->statusOptions($module))],
            ],
            'smart-sim'=>[
                'sim_number'=>['required','string','max:50',Rule::unique('smart_sims','sim_number')->ignore($id)],
                'imsi'=>'nullable|string|max:50','assigned_to'=>'nullable|string|max:255','location'=>'nullable|string|max:255','status'=>['required',Rule::in($this->statusOptions($module))],
            ],
            'program-inbound-numbers'=>[
                'number'=>['required','string','max:50',Rule::unique('program_inbound_numbers','number')->ignore($id)],
                'program'=>'nullable|string|max:255','location'=>'nullable|string|max:255','assigned_channel'=>'nullable|string|max:255','status'=>['required',Rule::in($this->statusOptions($module))],
            ],
            'signal-boosters'=>[
                'model'=>'required|string|max:255',
                'serial_number'=>['required','string','max:255',Rule::unique('signal_boosters','serial_number')->ignore($id)],
                'location'=>'nullable|string|max:255','status'=>['required',Rule::in($this->statusOptions($module))],
            ],
            'defective-gsm'=>[
                'asset_code'=>'required|string|max:255','location'=>'nullable|string|max:255','issue'=>'nullable|string|max:1000','reported_on'=>'nullable|date','status'=>['required',Rule::in($this->statusOptions($module))],
            ],
            default=>[]
        };
    }

    private function integrityError(string $module, array $data): ?string
    {
        if ($module === 'telco-cost' && !empty($data['contract_start']) && !empty($data['contract_end']) && $data['contract_end'] < $data['contract_start']) {
            return 'Contract end date must be on or after the contract start date.';
        }

        if (in_array($module, ['channel-port','network-prefix'], true) && !empty($data['gateway'])) {
            $exists = MediaGateway::query()
                ->where('site_code', $data['gateway'])
                ->orWhere('site_name', $data['gateway'])
                ->exists();
            if (! $exists) {
                return 'Gateway must match an existing Media Gateway site code or site name.';
            }
        }

        return null;
    }
}
