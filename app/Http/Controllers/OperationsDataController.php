<?php
namespace App\Http\Controllers;

use App\Models\ChannelAllocationCampaign;
use App\Models\MediaGateway;
use App\Services\AuditLogger;
use App\Services\XlsxService;
use App\Support\InventoryImportCatalog;
use App\Support\OperationCatalog;
use App\Support\PdcEndorseDate;
use App\Support\ProgramInboundNumberValidator;
use App\Support\PublicError;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OperationsDataController extends Controller
{
    use HandlesInventoryImport;
    public function index(Request $request, string $module)
    {
        $config=$this->config($module); $query=$config['model']::query();
        if (OperationCatalog::isInbound($module)) {
            $query->with(['campaign:id,name', 'mediaGateway:id,ip_address,port,network']);
        }
        $this->applyInventorySearch($query, $request, $config, $module);
        $perPage=(int)$request->query('per_page',10);
        if (!in_array($perPage,[5,10,25,50],true)) {
            $perPage=10;
        }
        $search=trim((string)$request->query('search'));
        $records=$query->latest()->paginate($perPage)->withQueryString();
        $gateways=MediaGateway::orderBy('site_code')->get(['site_code','site_name']);
        $statusOptions=$this->statusOptions($module);
        $campaigns = OperationCatalog::isInbound($module)
            ? ChannelAllocationCampaign::optionsForDropdown()
            : collect();
        $gsmGateways = OperationCatalog::isInbound($module)
            ? MediaGateway::query()->orderBy('ip_address')->get(['id', 'ip_address', 'port', 'network'])
            : collect();
        return view('operations.index', compact('config','module','records','gateways','statusOptions','search','perPage','campaigns','gsmGateways'));
    }

    public function store(Request $request, string $module)
    {
        $config=$this->config($module); $data=$request->validate($this->rules($module), $this->messages($module));
        if (OperationCatalog::isSim($module)) {
            $data = $this->parseSimDates($data);
        }
        if (OperationCatalog::isDefective($module)) {
            $data = $this->parseReportedOn($data);
        }
        if (OperationCatalog::isInbound($module)) {
            $data = $this->syncInboundNumbersAndGateway($data);
            $data = $this->syncInboundCampaign($data);
        }
        if ($error=$this->integrityError($module,$data)) {
            return back()->with('error',$error)->withInput();
        }
        $record=$config['model']::create($data); AuditLogger::log('Added',$config['title'],$config['title'].' record added', $record->id,$request);
        return back()->with('success',$config['title'].' record added successfully.');
    }

    public function update(Request $request)
    {
        [$config, $module, $id, $record] = $this->resolveRecord($request);
        $data = $request->validate($this->rules($module, $id), $this->messages($module));
        if (OperationCatalog::isSim($module)) {
            $data = $this->parseSimDates($data);
        }
        if (OperationCatalog::isDefective($module)) {
            $data = $this->parseReportedOn($data);
        }
        if (OperationCatalog::isInbound($module)) {
            $data = $this->syncInboundNumbersAndGateway($data, $id);
            $data = $this->syncInboundCampaign($data);
        }
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
        $isSim = OperationCatalog::isSim($module);
        $isInbound = OperationCatalog::isInbound($module);
        $isDefective = OperationCatalog::isDefective($module);
        if ($isInbound) {
            $query->with(['campaign:id,name', 'mediaGateway:id,ip_address,port,network']);
        }
        $headers = $isInbound
            ? array_values($config['table_columns'] ?? $config['columns'])
            : ($isSim
                ? array_values(OperationCatalog::simTransferColumns())
                : array_merge(['Id'], array_values($config['columns'])));
        $fields = array_keys($config['columns']);
        try {
            $sequence = 0;
            $path = $xlsx->export(
                $headers,
                $query->latest()->get()->map(function ($record) use ($fields, $isSim, $isInbound, $isDefective, &$sequence) {
                    if ($isInbound) {
                        return [
                            $record->campaign?->name ?: $record->program ?: '',
                            implode("\n", $record->mobileList()),
                            implode("\n", $record->landlineList()),
                            trim((string) ($record->mediaGateway?->ip_address ?? '')),
                            trim((string) ($record->port ?: $record->mediaGateway?->port ?: '')),
                            trim((string) ($record->network ?: $record->mediaGateway?->network ?: '')),
                            trim((string) ($record->remarks ?? '')),
                        ];
                    }

                    $sequence++;
                    $values = collect($fields)->map(function ($field) use ($record, $isSim, $isDefective) {
                        $value = $record->{$field};
                        if ($value instanceof \DateTimeInterface) {
                            return ($isSim || $isDefective)
                                ? (PdcEndorseDate::display($value->format('Y-m-d')) ?: '')
                                : $value->format('Y-m-d');
                        }

                        return $value;
                    })->all();

                    if ($isSim) {
                        return $values;
                    }

                    return array_merge([$sequence], $values);
                }),
                $module.'.xlsx'
            );
        } catch (\Throwable $exception) {
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
            'globe-sim','smart-sim','program-inbound-numbers'=>[],
            default=>['Active','Inactive'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function simRules(string $table, ?int $id = null): array
    {
        return [
            'imei' => ['required', 'string', 'max:50', Rule::unique($table, 'imei')->ignore($id)],
            'mobile_number' => ['required', 'string', 'max:50', Rule::unique($table, 'mobile_number')->ignore($id)],
            'network' => 'nullable|string|max:255',
            'plan' => 'nullable|string|max:255',
            'ip_address' => 'nullable|ipv4',
            'account_number' => 'nullable|string|max:255',
            'contract_start' => 'nullable|string',
            'contract_end' => 'nullable|string',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function parseSimDates(array $data): array
    {
        foreach (['contract_start' => 'Contract Start', 'contract_end' => 'Contract End'] as $field => $label) {
            $parsed = PdcEndorseDate::parse($data[$field] ?? '');
            if (! $parsed['valid']) {
                throw ValidationException::withMessages([
                    $field => $label.' must be a valid date on or after 1/1/2000.',
                ]);
            }
            $data[$field] = $parsed['iso'];
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function parseReportedOn(array $data): array
    {
        $parsed = PdcEndorseDate::parse($data['reported_on'] ?? '');
        if (! $parsed['valid']) {
            throw ValidationException::withMessages([
                'reported_on' => 'Reported On must be a valid date on or after 1/1/2000.',
            ]);
        }
        $data['reported_on'] = $parsed['iso'];

        return $data;
    }

    private function applyInventorySearch($query, Request $request, array $config, string $module): void
    {
        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search, $config, $module) {
                foreach (array_keys($config['columns']) as $i => $field) {
                    $i === 0 ? $q->where($field, 'like', "%$search%") : $q->orWhere($field, 'like', "%$search%");
                }
                if (OperationCatalog::isSim($module)) {
                    $parsed = PdcEndorseDate::parse($search);
                    if ($parsed['valid'] && ! $parsed['empty']) {
                        $q->orWhereDate('contract_start', $parsed['iso'])->orWhereDate('contract_end', $parsed['iso']);
                    }
                }
                if (OperationCatalog::isDefective($module)) {
                    $parsed = PdcEndorseDate::parse($search);
                    if ($parsed['valid'] && ! $parsed['empty']) {
                        $q->orWhereDate('reported_on', $parsed['iso']);
                    }
                }
            });
        }
        if ($status = trim((string) $request->query('status'))) {
            if (array_key_exists('status', $config['columns'])) {
                $query->where('status', $status);
            }
        }
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
            'archive-recordings'=>[],
            'globe-sim'=>$this->simRules('globe_sims', $id),
            'smart-sim'=>$this->simRules('smart_sims', $id),
            'program-inbound-numbers'=>[
                'campaign'=>'required|string|max:255',
                'mobile_numbers'=>'nullable|array',
                'mobile_numbers.*'=>'nullable|string|max:50',
                'landline_numbers'=>'nullable|array',
                'landline_numbers.*'=>'nullable|string|max:50',
                'media_gateway_id'=>'nullable|integer|exists:media_gateways,id',
                'port'=>'nullable|string|max:50',
                'network'=>'nullable|string|max:255',
                'remarks'=>'nullable|string|max:1000',
            ],
            'signal-boosters'=>[
                'model'=>'required|string|max:255',
                'specs'=>'nullable|string|max:5000',
                'serial_number'=>['required','string','max:255',Rule::unique('signal_boosters','serial_number')->ignore($id)],
                'location'=>'nullable|string|max:255','status'=>['required',Rule::in($this->statusOptions($module))],
            ],
            'defective-gsm'=>[
                'asset_code'=>'required|string|max:255','location'=>'nullable|string|max:255','issue'=>'nullable|string|max:5000','reported_on'=>'nullable|string','status'=>['required',Rule::in($this->statusOptions($module))],
            ],
            default=>[]
        };
    }

    private function integrityError(string $module, array $data): ?string
    {
        if (in_array($module, ['telco-cost', 'globe-sim', 'smart-sim'], true) && !empty($data['contract_start']) && !empty($data['contract_end']) && $data['contract_end'] < $data['contract_start']) {
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function syncInboundCampaign(array $data): array
    {
        $campaign = ChannelAllocationCampaign::findOrCreateByName((string) ($data['campaign'] ?? ''));
        $data['campaign_id'] = $campaign->id;
        $data['program'] = $campaign->name;
        unset($data['campaign']);

        return $data;
    }

    /**
     * @return array<string, string>
     */
    private function messages(string $module): array
    {
        if (! OperationCatalog::isInbound($module)) {
            return [];
        }

        return [
            'campaign.required' => ProgramInboundNumberValidator::CAMPAIGN_REQUIRED,
            'media_gateway_id.exists' => ProgramInboundNumberValidator::GSM_MUST_EXIST,
            'media_gateway_id.integer' => ProgramInboundNumberValidator::GSM_REQUIRED,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function syncInboundNumbersAndGateway(array $data, ?int $ignoreId = null): array
    {
        $mobiles = ProgramInboundNumberValidator::normalize($data['mobile_numbers'] ?? []);
        $landlines = ProgramInboundNumberValidator::normalize($data['landline_numbers'] ?? []);
        $data['mobile_numbers'] = $mobiles === [] ? null : $mobiles;
        $data['landline_numbers'] = $landlines === [] ? null : $landlines;
        $data['number'] = $mobiles[0] ?? $landlines[0] ?? '';
        $data['status'] = $data['status'] ?? 'Active';

        $messages = [];
        if ($data['number'] === '') {
            $messages['mobile_numbers'] = ProgramInboundNumberValidator::NEED_NUMBER;
        }
        foreach (ProgramInboundNumberValidator::formatErrors($mobiles, $landlines) as $error) {
            $field = $error === ProgramInboundNumberValidator::LANDLINE_DIGITS ? 'landline_numbers' : 'mobile_numbers';
            $messages[$field] = $error;
        }
        $seen = [];
        foreach (ProgramInboundNumberValidator::uniquenessErrors($mobiles, $landlines, $seen, $ignoreId) as $error) {
            $messages['number'] = $error;
        }

        if ($mobiles === []) {
            if ($messages !== []) {
                throw ValidationException::withMessages($messages);
            }
            $data['media_gateway_id'] = null;
            $data['port'] = null;
            $data['network'] = null;

            return $data;
        }

        $gateway = MediaGateway::query()->find($data['media_gateway_id'] ?? null);
        if (! $gateway) {
            $messages['media_gateway_id'] = ProgramInboundNumberValidator::GSM_REQUIRED;
        } else {
            $data['media_gateway_id'] = $gateway->id;
            $data['port'] = $gateway->port;
            $data['network'] = $gateway->network;
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }

        return $data;
    }
}
