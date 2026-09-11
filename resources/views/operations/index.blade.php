@extends('layouts.app')
@php
    $isSim = \App\Support\OperationCatalog::isSim($module);
    $isInbound = \App\Support\OperationCatalog::isInbound($module);
    $isBooster = \App\Support\OperationCatalog::isBooster($module);
    $isDefective = \App\Support\OperationCatalog::isDefective($module);
    $useMdyDate = $isSim || $isDefective;
    $hideMeta = $isSim || $isInbound;
    $hideLastUpdated = $hideMeta || $isBooster;
    $tableColumns = $isInbound ? ($config['table_columns'] ?? $config['columns']) : $config['columns'];
    $transferColumns = $isSim
        ? \App\Support\OperationCatalog::simTransferColumns()
        : ($isInbound ? ($config['table_columns'] ?? $config['columns']) : $config['columns']);
    $numericColumns = $isSim ? [] : ['monthly_cost', 'retention_days', 'port_number', 'number'];
    $emptyColspan = count($tableColumns) + ($hideMeta ? 1 : ($hideLastUpdated ? 2 : 3));
@endphp
@section('content')
<div class="page-head">
    <div>
        <h1 class="page-title">{{ $config['title'] }}</h1>
        <p class="page-subtitle">{{ $config['description'] }}</p>
    </div>
    <div class="toolbar">
        <form class="search-box media-search-form" id="operationSearchForm" method="GET" action="{{ route($module) }}">
            <span class="search-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
            </span>
            <input id="operationSearchInput" name="search" value="{{ $search }}" placeholder="{{ $isInbound ? 'Search Program Inbound Numbers...' : 'Search '.$config['title'] }}" aria-label="{{ $isInbound ? 'Search Program Inbound Numbers' : 'Search '.$config['title'] }}" autocomplete="off">
            @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
            @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
        </form>
        @if(count($statusOptions))
            <form method="GET" action="{{ route($module) }}">
                @if($search !== '')<input type="hidden" name="search" value="{{ $search }}">@endif
                @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                <select class="select" name="status" onchange="this.form.submit()" aria-label="Filter by status">
                    <option value="">All Statuses</option>
                    @foreach($statusOptions as $opt)
                        <option value="{{ $opt }}" {{ request('status')===$opt?'selected':'' }}>{{ $opt }}</option>
                    @endforeach
                </select>
            </form>
        @endif
        <button class="btn primary" type="submit" form="operationSearchForm">Search</button>
        @if(auth()->user()->hasPermission('media.export'))
        @include('partials.data-transfer', [
            'canExport' => true,
            'canImport' => auth()->user()->hasPermission('media.create'),
            'exportUrl' => route($module.'.export', request()->query()),
            'templateUrl' => auth()->user()->hasPermission('media.create') ? route($module.'.import.template') : '',
            'previewUrl' => auth()->user()->hasPermission('media.create') ? route($module.'.import.preview') : '',
            'confirmUrl' => auth()->user()->hasPermission('media.create') ? route($module.'.import.confirm') : '',
            'errorsUrl' => auth()->user()->hasPermission('media.create') ? route($module.'.import.errors') : '',
            'previewHeaders' => array_values($transferColumns),
            'entityTitle' => $config['title'],
        ])
        @endif
        @if(auth()->user()->hasPermission('media.create'))
            <button class="plus-btn" type="button" id="operationAddButton" aria-label="{{ $isInbound ? 'Add' : 'Add '.$config['title'] }}" title="{{ $isInbound ? 'Add' : 'Add '.$config['title'] }}">+</button>
        @endif
    </div>
</div>

<div class="table-card table-wrap">
<table @class(['sim-table' => $isSim, 'pin-table' => $isInbound, 'sb-table' => $isBooster, 'dg-table' => $isDefective]) aria-label="{{ $config['title'] }}">
<thead>
<tr>
    @unless($hideMeta)
        <th>Id</th>
    @endunless
    @foreach($tableColumns as $field => $label)
        <th @if($isInbound && $field === 'campaign') class="pin-campaign-col" @elseif($isBooster && $field === 'specs') class="sb-specs-col" @elseif($isDefective && $field === 'issue') class="dg-issue-col" @elseif(in_array($field, $numericColumns, true)) class="num-col" @endif>{{ $label }}</th>
    @endforeach
    @unless($hideLastUpdated)
        <th>Last Updated</th>
    @endunless
    <th class="actions-column">Actions</th>
</tr>
</thead>
<tbody>
@forelse($records as $record)
@php
    $recordId = (int) $record->getKey();
    $editValues = [];
    foreach ($config['fields'] as $field) {
        if ($isInbound && $field === 'campaign') {
            $editValues['campaign'] = $record->campaign?->name ?: $record->program ?: '';
            continue;
        }
        if ($isInbound && $field === 'mobile_numbers') {
            $editValues['mobile_numbers'] = $record->mobileList();
            continue;
        }
        if ($isInbound && $field === 'landline_numbers') {
            $editValues['landline_numbers'] = $record->landlineList();
            continue;
        }
        $value = $record->{$field};
        if ($value instanceof \DateTimeInterface) {
            $value = $useMdyDate
                ? \App\Support\PdcEndorseDate::display($value->format('Y-m-d'))
                : $value->format('Y-m-d');
        }
        $editValues[$field] = $value;
    }
@endphp
<tr>
    @unless($hideMeta)
        <td>{{ ($records->firstItem() ?? 1) + $loop->index }}</td>
    @endunless
    @if($isInbound)
        <td class="pin-campaign-col"><span class="pin-campaign">{{ $record->campaignLabel() }}</span></td>
        <td>
            @if($record->mobileList() === [])
                —
            @else
                <span class="pin-stack">
                    @foreach($record->mobileList() as $mobile)
                        <span>{{ $mobile }}</span>
                    @endforeach
                </span>
            @endif
        </td>
        <td>
            @if($record->landlineList() === [])
                —
            @else
                <span class="pin-stack">
                    @foreach($record->landlineList() as $landline)
                        <span>{{ $landline }}</span>
                    @endforeach
                </span>
            @endif
        </td>
        <td>{{ $record->gatewayLabel() }}</td>
        <td>{{ $record->portLabel() }}</td>
        <td>{{ $record->networkLabel() }}</td>
        <td class="pin-remarks-col">{{ $record->remarksLabel() }}</td>
    @else
    @foreach($config['columns'] as $field=>$label)
        @php $isNumericCol = in_array($field, $numericColumns, true); @endphp
        <td @class(['num-col' => $isNumericCol, 'sb-specs-col' => $isBooster && $field === 'specs', 'dg-issue-col' => $isDefective && $field === 'issue'])>
            @if($field==='monthly_cost')
                <span class="num-align" data-label="{{ $label }}">₱{{ number_format((float) $record->$field, 2) }}</span>
            @elseif($field==='status')
                <span class="status-pill {{ in_array($record->$field, ['Active', 'Available']) ? 'online' : (in_array($record->$field, ['In Use', 'Expiring']) ? 'unknown' : 'offline') }}">{{ $record->$field }}</span>
            @elseif(in_array($field, ['contract_start', 'contract_end', 'reported_on'], true))
                @php
                    $dateValue = $record->$field;
                    if ($dateValue instanceof \DateTimeInterface) {
                        $dateValue = $dateValue->format('Y-m-d');
                    }
                    $dateValue = $useMdyDate ? \App\Support\PdcEndorseDate::display($dateValue) : $dateValue;
                @endphp
                {{ $dateValue }}
            @elseif($isDefective && $field === 'issue')
                {{ $record->$field }}
            @elseif($isNumericCol)
                <span class="num-align" data-label="{{ $label }}">{{ $record->$field }}</span>
            @else
                {{ $record->$field }}
            @endif
        </td>
    @endforeach
    @endif
    @unless($hideLastUpdated)
        <td>{{ $record->updated_at?->format('M d, Y h:i A') ?? '—' }}</td>
    @endunless
    <td class="actions-column">
        <div class="row-actions">
            @if(auth()->user()->hasPermission('media.edit'))
                <button class="action-btn edit" type="button" data-operation-edit data-id="{{ $recordId }}" data-values='@json($editValues)' title="Edit {{ $config['title'] }}" aria-label="Edit {{ $config['title'] }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                </button>
            @endif
            @if(auth()->user()->hasPermission('media.delete'))
                <form method="POST" action="{{ route($module.'.destroy', ['id' => $recordId]) }}" data-confirm="Delete this record?" data-confirm-title="Delete Record" data-confirm-ok="Delete">
                    @csrf
                    @method('DELETE')
                    <button class="action-btn delete" type="submit" title="Delete {{ $config['title'] }}" aria-label="Delete {{ $config['title'] }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                    </button>
                </form>
            @endif
        </div>
    </td>
</tr>
@empty
<tr><td colspan="{{ $emptyColspan }}"><div class="empty-state">No {{ $config['title'] }} records found.</div></td></tr>
@endforelse
</tbody>
</table>
<div class="table-footer">
    <span>Showing {{ $records->firstItem() ?? 0 }} to {{ $records->lastItem() ?? 0 }} of {{ $records->total() }} entries</span>
    <div class="footer-right">
        <span>Records per page:</span>
        <select class="per-page-select" onchange="location.href=this.value" aria-label="Records per page">
            @foreach([5,10,25,50] as $size)
                <option value="{{ request()->fullUrlWithQuery(['per_page'=>$size,'page'=>1]) }}" {{ $perPage===$size?'selected':'' }}>{{ $size }}</option>
            @endforeach
        </select>
        <div class="pager">
            @for($page=1;$page<=$records->lastPage();$page++)
                @if($page===$records->currentPage())
                    <span class="page-number active">{{ $page }}</span>
                @else
                    <a class="page-number" href="{{ $records->url($page) }}">{{ $page }}</a>
                @endif
            @endfor
        </div>
    </div>
</div>
</div>
@endsection

@push('modals')
@if(auth()->user()->hasPermission('media.create') || auth()->user()->hasPermission('media.edit'))
<div class="modal-backdrop{{ $isInbound ? ' pin-modal-backdrop' : '' }}" id="moduleModal">
    <div class="modal{{ $isInbound ? ' pin-modal' : '' }}">
        <div class="modal-header">
            <h3 id="moduleModalTitle">{{ $isInbound ? 'Add Program Inbound Number' : 'Add '.$config['title'] }}</h3>
            <button type="button" class="close-btn" data-close="moduleModal">×</button>
        </div>
        <form id="moduleForm" method="POST" action="{{ route($module.'.store') }}">
            @csrf
            <input type="hidden" name="_method" id="moduleMethod" value="POST">
            <div class="modal-body">
                <div class="form-grid">
                    @if($isInbound)
                        <div class="form-group full">
                            <label for="field_campaign">Campaign</label>
                            <div class="pin-campaign-combo">
                                <input class="form-control" name="campaign" id="field_campaign" placeholder="Select or type a campaign..." autocomplete="off" required aria-autocomplete="list" aria-controls="pinCampaignMenu">
                                <div class="pin-campaign-menu" id="pinCampaignMenu" hidden role="listbox">
                                    @forelse($campaigns as $campaign)
                                        <button class="pin-campaign-option" type="button" role="option" data-name="{{ $campaign->name }}">{{ $campaign->name }}</button>
                                    @empty
                                        <div class="pin-campaign-empty">No campaigns yet. Type a new name.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="pinMobileInput">Mobile Number(s)</label>
                            <input class="form-control" id="pinMobileInput" autocomplete="off">
                            <small class="muted pin-number-hint">You can enter multiple mobile numbers.</small>
                            <div class="pin-chips" id="pinMobileChips" data-pin-chips="mobile"></div>
                            <div id="pinMobileHidden"></div>
                        </div>
                        <div class="form-group">
                            <label for="pinLandlineInput">Landline Number(s)</label>
                            <input class="form-control" id="pinLandlineInput" autocomplete="off">
                            <small class="muted pin-number-hint">You can enter multiple landline numbers.</small>
                            <div class="pin-chips" id="pinLandlineChips" data-pin-chips="landline"></div>
                            <div id="pinLandlineHidden"></div>
                        </div>
                        <div class="form-group pin-gsm-wrap" id="pinGsmWrap">
                            <label for="field_media_gateway_id">GSM Gateway</label>
                            <select class="form-control" name="media_gateway_id" id="field_media_gateway_id" disabled>
                                <option value="">Select GSM Gateway</option>
                                @foreach($gsmGateways as $gateway)
                                    <option value="{{ $gateway->id }}" data-port="{{ $gateway->port }}" data-network="{{ $gateway->network }}">{{ $gateway->ip_address }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group pin-gsm-wrap">
                            <label for="field_port">Port</label>
                            <input class="form-control" name="port" id="field_port" readonly tabindex="-1" disabled>
                        </div>
                        <div class="form-group pin-gsm-wrap">
                            <label for="field_network">Network</label>
                            <input class="form-control" name="network" id="field_network" readonly tabindex="-1" disabled>
                        </div>
                        <div class="form-group full">
                            <label for="field_remarks">Remarks</label>
                            <textarea class="form-control" name="remarks" id="field_remarks" placeholder="Enter remarks (optional)..."></textarea>
                        </div>
                    @else
                    @foreach($config['fields'] as $field)
                    <div class="form-group {{ in_array($field, ['description', 'specs', 'issue'], true) ? 'full' : '' }}">
                        <label for="field_{{ $field }}">{{ $config['columns'][$field] ?? ucwords(str_replace('_', ' ', $field)) }}</label>
                        @if($field==='status')
                            <select class="form-control" name="{{ $field }}" id="field_{{ $field }}">
                                @foreach($statusOptions as $opt)
                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                @endforeach
                            </select>
                        @elseif($field==='gateway')
                            <select class="form-control" name="{{ $field }}" id="field_{{ $field }}">
                                <option value="">Select Media Gateway</option>
                                @foreach($gateways as $gateway)
                                    <option value="{{ $gateway->site_code }}">{{ $gateway->site_code }} — {{ $gateway->site_name }}</option>
                                @endforeach
                            </select>
                        @elseif($field==='description')
                            <textarea class="form-control" name="{{ $field }}" id="field_{{ $field }}"></textarea>
                        @elseif($field==='specs')
                            <textarea class="form-control sb-specs-field" name="{{ $field }}" id="field_{{ $field }}" rows="6"></textarea>
                        @elseif($field==='issue' && $isDefective)
                            <textarea class="form-control dg-issue-field" name="{{ $field }}" id="field_{{ $field }}" rows="6"></textarea>
                        @elseif($useMdyDate && in_array($field, ['contract_start','contract_end','reported_on'], true))
                            @include('partials.mdy-date-field', ['field' => $field, 'fieldId' => 'field_'.$field])
                        @elseif(in_array($field, ['contract_start','contract_end','reported_on']))
                            <input class="form-control" type="date" name="{{ $field }}" id="field_{{ $field }}">
                        @elseif(in_array($field, ['monthly_cost','retention_days']))
                            <input class="form-control" type="number" step="0.01" min="0" name="{{ $field }}" id="field_{{ $field }}">
                        @else
                            <input class="form-control" name="{{ $field }}" id="field_{{ $field }}" {{ in_array($field, ['description','channel','gateway','peer','context','codec','imsi','assigned_to','location','role','program','assigned_channel','issue','reported_on','retention_days','network','plan','ip_address','account_number']) ? '' : 'required' }}>
                        @endif
                    </div>
                    @endforeach
                    @endif
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn secondary" data-close="moduleModal">Cancel</button>
                <button class="btn primary" type="submit">Save</button>
            </div>
        </form>
    </div>
</div>
@endif

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('moduleModal');
    const form = document.getElementById('moduleForm');
    const method = document.getElementById('moduleMethod');
    const storeAction = @json(route($module.'.store'));
    function resetAdd() {
        if (!form || !method) return;
        method.value = 'POST';
        form.action = storeAction;
        document.getElementById('moduleModalTitle').textContent = @json($isInbound ? 'Add Program Inbound Number' : 'Add '.$config['title']);
        form.reset();
    }
    document.getElementById('operationAddButton')?.addEventListener('click', () => {
        resetAdd();
        modal?.classList.add('visible');
    });
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-operation-edit]');
        if (!button) return;
        const recordId = Number(button.dataset.id);
        if (!Number.isInteger(recordId) || recordId < 1) return;
        const values = JSON.parse(button.dataset.values || '{}');
        document.getElementById('moduleModalTitle').textContent = @json($isInbound ? 'Edit Program Inbound Number' : 'Edit '.$config['title']);
        method.value = 'PUT';
        form.action = @json(url('/'.$module)) + '/' + recordId;
        Object.keys(values).forEach((key) => {
            if (Array.isArray(values[key])) return;
            const field = document.getElementById('field_' + key);
            if (field) field.value = values[key] ?? '';
        });
        modal?.classList.add('visible');
    });
});
</script>
@if($isInbound)
<script>
document.addEventListener('DOMContentLoaded', () => {
    const input = document.getElementById('field_campaign');
    const menu = document.getElementById('pinCampaignMenu');
    const gateway = document.getElementById('field_media_gateway_id');
    const port = document.getElementById('field_port');
    const network = document.getElementById('field_network');
    const inboundForm = document.getElementById('moduleForm');
    const lists = {
        mobile: [],
        landline: [],
    };

    const options = () => [...(menu?.querySelectorAll('.pin-campaign-option') || [])];
    const filterMenu = () => {
        if (!input || !menu) return;
        const query = input.value.trim().toLowerCase();
        let visible = 0;
        options().forEach((option) => {
            const match = !query || option.dataset.name.toLowerCase().includes(query);
            option.hidden = !match;
            if (match) visible += 1;
        });
        const empty = menu.querySelector('.pin-campaign-empty');
        if (empty && options().length) empty.hidden = visible > 0;
    };
    const openMenu = () => {
        filterMenu();
        if (menu) menu.hidden = false;
        input?.setAttribute('aria-expanded', 'true');
    };
    const closeMenu = () => {
        if (menu) menu.hidden = true;
        input?.setAttribute('aria-expanded', 'false');
    };
    input?.addEventListener('focus', openMenu);
    input?.addEventListener('input', openMenu);
    menu?.addEventListener('click', (event) => {
        const option = event.target.closest('.pin-campaign-option');
        if (!option) return;
        input.value = option.dataset.name || '';
        closeMenu();
    });
    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element) || !event.target.closest('.pin-campaign-combo')) {
            closeMenu();
        }
    });

    const fillGatewayDetails = () => {
        const selected = gateway?.selectedOptions?.[0];
        if (port) port.value = selected?.dataset.port || '';
        if (network) network.value = selected?.dataset.network || '';
    };
    const toggleGsm = () => {
        const hasMobile = lists.mobile.length > 0;
        if (gateway) {
            gateway.disabled = !hasMobile;
            gateway.required = hasMobile;
            gateway.setCustomValidity('');
        }
        if (port) port.disabled = !hasMobile;
        if (network) network.disabled = !hasMobile;
        if (!hasMobile) {
            if (gateway) gateway.value = '';
            if (port) port.value = '';
            if (network) network.value = '';
        }
    };
    const renderChips = (kind) => {
        const chips = document.getElementById(kind === 'mobile' ? 'pinMobileChips' : 'pinLandlineChips');
        const hidden = document.getElementById(kind === 'mobile' ? 'pinMobileHidden' : 'pinLandlineHidden');
        const name = kind === 'mobile' ? 'mobile_numbers[]' : 'landline_numbers[]';
        if (!chips || !hidden) return;
        chips.innerHTML = lists[kind].map((value, index) => (
            '<span class="pin-chip">' + value.replace(/</g, '') +
            '<button type="button" class="pin-chip-remove" data-pin-remove="' + kind + '" data-index="' + index + '" aria-label="Remove ' + value + '">×</button></span>'
        )).join('');
        hidden.innerHTML = lists[kind].map((value) => (
            '<input type="hidden" name="' + name + '" value="' + String(value).replace(/"/g, '&quot;') + '">'
        )).join('');
        if (kind === 'mobile') toggleGsm();
    };
    const addNumber = (kind, raw) => {
        const field = document.getElementById(kind === 'mobile' ? 'pinMobileInput' : 'pinLandlineInput');
        const value = String(raw || '').trim();
        if (!value) return;
        if (!/^[0-9]{1,50}$/.test(value)) {
            field?.setCustomValidity((kind === 'mobile' ? 'Mobile' : 'Landline') + ' must contain only digits.');
            field?.reportValidity();
            return;
        }
        const other = kind === 'mobile' ? lists.landline : lists.mobile;
        if (lists[kind].includes(value) || other.includes(value)) {
            field?.setCustomValidity('Number is duplicated.');
            field?.reportValidity();
            return;
        }
        field?.setCustomValidity('');
        lists[kind].push(value);
        renderChips(kind);
    };
    const bindChipInput = (kind, fieldId) => {
        const field = document.getElementById(fieldId);
        field?.addEventListener('input', () => field.setCustomValidity(''));
        field?.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            addNumber(kind, field.value);
            if (field.validity.valid) field.value = '';
        });
    };
    bindChipInput('mobile', 'pinMobileInput');
    bindChipInput('landline', 'pinLandlineInput');
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-pin-remove]');
        if (!button) return;
        const kind = button.getAttribute('data-pin-remove');
        const index = Number(button.getAttribute('data-index'));
        if (!lists[kind] || Number.isNaN(index)) return;
        lists[kind].splice(index, 1);
        renderChips(kind);
    });
    gateway?.addEventListener('change', fillGatewayDetails);
    inboundForm?.addEventListener('submit', (event) => {
        const mobileField = document.getElementById('pinMobileInput');
        const landlineField = document.getElementById('pinLandlineInput');
        mobileField?.setCustomValidity('');
        landlineField?.setCustomValidity('');
        if (mobileField?.value.trim()) addNumber('mobile', mobileField.value);
        if (landlineField?.value.trim()) addNumber('landline', landlineField.value);
        if (mobileField && mobileField.validity.valid) mobileField.value = '';
        if (landlineField && landlineField.validity.valid) landlineField.value = '';
        if ((mobileField && !mobileField.validity.valid) || (landlineField && !landlineField.validity.valid)) {
            event.preventDefault();
            return;
        }
        toggleGsm();
        if (lists.mobile.length === 0 && lists.landline.length === 0) {
            event.preventDefault();
            const target = landlineField || mobileField;
            target?.setCustomValidity('Enter at least one Mobile or Landline number.');
            target?.reportValidity();
            return;
        }
        mobileField?.setCustomValidity('');
        landlineField?.setCustomValidity('');
        if (lists.mobile.length > 0 && gateway && !String(gateway.value || '').trim()) {
            event.preventDefault();
            gateway.setCustomValidity('Please select a GSM Gateway.');
            gateway.reportValidity();
            return;
        }
        gateway?.setCustomValidity('');
    });

    const resetNumbers = () => {
        lists.mobile = [];
        lists.landline = [];
        renderChips('mobile');
        renderChips('landline');
        const mobileInput = document.getElementById('pinMobileInput');
        const landlineInput = document.getElementById('pinLandlineInput');
        if (mobileInput) mobileInput.value = '';
        if (landlineInput) landlineInput.value = '';
        toggleGsm();
    };
    document.getElementById('operationAddButton')?.addEventListener('click', resetNumbers);
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-operation-edit]');
        if (!button) return;
        const values = JSON.parse(button.dataset.values || '{}');
        lists.mobile = Array.isArray(values.mobile_numbers) ? values.mobile_numbers.map(String) : [];
        lists.landline = Array.isArray(values.landline_numbers) ? values.landline_numbers.map(String) : [];
        renderChips('mobile');
        renderChips('landline');
        if (gateway) gateway.value = values.media_gateway_id ?? '';
        fillGatewayDetails();
        toggleGsm();
    });
});
</script>
@endif
@include('partials.inventory-import-script', [
    'previewUrl' => auth()->user()->hasPermission('media.create') ? route($module.'.import.preview') : '',
    'confirmUrl' => auth()->user()->hasPermission('media.create') ? route($module.'.import.confirm') : '',
    'errorsUrl' => auth()->user()->hasPermission('media.create') ? route($module.'.import.errors') : '',
    'previewFields' => array_keys($transferColumns),
])
@if($useMdyDate)
<script>
document.addEventListener('DOMContentLoaded', () => {
    const minDate = @json(\App\Support\PdcEndorseDate::MIN_DATE);
    const minYear = Number(minDate.slice(0, 4));
    const maxYear = new Date().getFullYear();
    const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const monthShort = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const toDisplay = (iso) => {
        if (!iso) return '';
        const [year, month, day] = iso.split('-');
        if (!year || !month || !day) return '';
        return Number(month) + '/' + Number(day) + '/' + year;
    };
    const toIso = (display) => {
        const match = String(display || '').trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
        if (!match) return '';
        const month = Number(match[1]);
        const day = Number(match[2]);
        const year = Number(match[3]);
        if (year < minYear || month < 1 || month > 12 || day < 1 || day > 31) return '';
        const probe = new Date(year, month - 1, day);
        if (probe.getFullYear() !== year || probe.getMonth() !== month - 1 || probe.getDate() !== day) return '';
        return year + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');
    };
    const closeAllCals = () => document.querySelectorAll('#moduleForm .pdc-cal').forEach((cal) => cal.setAttribute('hidden', ''));

    function bindMdyDateField(textId, invalidMessage) {
        const dateText = document.getElementById(textId);
        const datePicker = document.getElementById(textId + '_picker');
        const cal = document.getElementById(textId + '_cal');
        const calBody = cal?.querySelector('[data-pdc-cal-body]');
        const calMonthBtn = cal?.querySelector('[data-pdc-cal-month]');
        const calYearBtn = cal?.querySelector('[data-pdc-cal-year]');
        let calView = 'days';
        let calYear = maxYear;
        let calMonth = new Date().getMonth();
        let yearPageStart = minYear;

        const parseSelected = () => {
            const iso = toIso(dateText?.value) || datePicker?.value || '';
            const parts = iso.split('-').map(Number);
            if (parts.length === 3 && parts[0] >= minYear) {
                return { year: parts[0], month: parts[1] - 1, day: parts[2] };
            }
            const now = new Date();
            return { year: now.getFullYear(), month: now.getMonth(), day: now.getDate() };
        };
        const setPicked = (year, month, day) => {
            const iso = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
            if (datePicker) datePicker.value = iso;
            if (dateText) {
                dateText.value = toDisplay(iso);
                dateText.setCustomValidity('');
            }
            cal?.setAttribute('hidden', '');
        };
        const renderCal = () => {
            if (!cal || !calBody || !calMonthBtn || !calYearBtn) return;
            const selected = parseSelected();
            calMonthBtn.hidden = calView === 'years';
            if (calView === 'days') {
                calMonthBtn.textContent = monthNames[calMonth];
                calYearBtn.textContent = String(calYear);
            } else if (calView === 'months') {
                calMonthBtn.textContent = '';
                calMonthBtn.hidden = true;
                calYearBtn.textContent = String(calYear);
            } else {
                const end = Math.min(yearPageStart + 11, maxYear);
                calYearBtn.textContent = yearPageStart === end ? String(yearPageStart) : (yearPageStart + ' – ' + end);
            }
            if (calView === 'years') {
                calBody.innerHTML = '';
                const grid = document.createElement('div');
                grid.className = 'pdc-cal-grid';
                for (let year = yearPageStart; year <= Math.min(yearPageStart + 11, maxYear); year++) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'pdc-cal-cell' + (year === selected.year ? ' selected' : '');
                    btn.textContent = String(year);
                    btn.addEventListener('click', () => { calYear = year; calView = 'months'; renderCal(); });
                    grid.appendChild(btn);
                }
                calBody.appendChild(grid);
                return;
            }
            if (calView === 'months') {
                calBody.innerHTML = '';
                const grid = document.createElement('div');
                grid.className = 'pdc-cal-grid';
                monthShort.forEach((label, month) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'pdc-cal-cell' + (month === selected.month && calYear === selected.year ? ' selected' : '');
                    btn.textContent = label;
                    btn.addEventListener('click', () => { calMonth = month; calView = 'days'; renderCal(); });
                    grid.appendChild(btn);
                });
                calBody.appendChild(grid);
                return;
            }
            const first = new Date(calYear, calMonth, 1);
            const startWeekday = first.getDay();
            const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
            const prevDays = new Date(calYear, calMonth, 0).getDate();
            calBody.innerHTML = '';
            const grid = document.createElement('div');
            grid.className = 'pdc-cal-grid days';
            ['S','M','T','W','T','F','S'].forEach((dow) => {
                const el = document.createElement('div');
                el.className = 'pdc-cal-dow';
                el.textContent = dow;
                grid.appendChild(el);
            });
            for (let i = 0; i < 42; i++) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'pdc-cal-cell';
                let year = calYear;
                let month = calMonth;
                let day;
                if (i < startWeekday) {
                    month -= 1;
                    if (month < 0) { month = 11; year -= 1; }
                    day = prevDays - startWeekday + i + 1;
                    btn.classList.add('muted');
                } else if (i >= startWeekday + daysInMonth) {
                    day = i - startWeekday - daysInMonth + 1;
                    month += 1;
                    if (month > 11) { month = 0; year += 1; }
                    btn.classList.add('muted');
                } else {
                    day = i - startWeekday + 1;
                }
                if (year < minYear || year > maxYear) btn.disabled = true;
                if (year === selected.year && month === selected.month && day === selected.day) btn.classList.add('selected');
                btn.textContent = String(day);
                btn.addEventListener('click', () => setPicked(year, month, day));
                grid.appendChild(btn);
            }
            calBody.appendChild(grid);
        };
        const openCal = () => {
            const selected = parseSelected();
            calYear = Math.min(Math.max(selected.year, minYear), maxYear);
            calMonth = selected.month;
            calView = 'days';
            yearPageStart = minYear + Math.floor((calYear - minYear) / 12) * 12;
            closeAllCals();
            renderCal();
            cal?.removeAttribute('hidden');
        };
        ['pointerdown', 'mousedown', 'click'].forEach((evt) => {
            datePicker?.addEventListener(evt, (event) => {
                event.preventDefault();
                event.stopPropagation();
                if (evt === 'click') {
                    if (cal && !cal.hasAttribute('hidden')) cal.setAttribute('hidden', '');
                    else openCal();
                }
            });
        });
        cal?.addEventListener('click', (event) => event.stopPropagation());
        calMonthBtn?.addEventListener('click', () => { calView = 'months'; renderCal(); });
        calYearBtn?.addEventListener('click', () => {
            if (calView === 'years') return;
            yearPageStart = minYear + Math.floor((calYear - minYear) / 12) * 12;
            calView = 'years';
            renderCal();
        });
        cal?.querySelector('[data-pdc-cal-prev]')?.addEventListener('click', () => {
            if (calView === 'days') {
                calMonth -= 1;
                if (calMonth < 0) { calMonth = 11; calYear -= 1; }
                if (calYear < minYear) { calYear = minYear; calMonth = 0; }
            } else if (calView === 'months') {
                calYear = Math.max(minYear, calYear - 1);
            } else {
                yearPageStart = Math.max(minYear, yearPageStart - 12);
            }
            renderCal();
        });
        cal?.querySelector('[data-pdc-cal-next]')?.addEventListener('click', () => {
            if (calView === 'days') {
                calMonth += 1;
                if (calMonth > 11) { calMonth = 0; calYear += 1; }
                if (calYear > maxYear) { calYear = maxYear; calMonth = 11; }
            } else if (calView === 'months') {
                calYear = Math.min(maxYear, calYear + 1);
            } else {
                yearPageStart = Math.min(minYear + Math.floor((maxYear - minYear) / 12) * 12, yearPageStart + 12);
            }
            renderCal();
        });
        datePicker?.addEventListener('change', () => {
            if (datePicker.value) dateText.value = toDisplay(datePicker.value);
        });
        dateText?.addEventListener('blur', () => {
            const iso = toIso(dateText.value);
            if (iso) datePicker.value = iso;
        });
        dateText?.addEventListener('input', () => dateText.setCustomValidity(''));
        return {
            setValue(display) {
                if (!dateText) return;
                dateText.value = display ?? '';
                dateText.setCustomValidity('');
                if (datePicker) datePicker.value = toIso(display || '');
            },
            validate() {
                const raw = String(dateText?.value || '').trim();
                if (!dateText || raw === '') {
                    dateText?.setCustomValidity('');
                    return true;
                }
                const iso = toIso(raw);
                if (!iso) {
                    dateText.setCustomValidity(invalidMessage || 'Enter a valid date on or after 1/1/2000.');
                    dateText.reportValidity();
                    return false;
                }
                dateText.setCustomValidity('');
                return true;
            }
        };
    }

    const startField = bindMdyDateField('field_contract_start', 'Contract date must be a valid date on or after 1/1/2000.');
    const endField = bindMdyDateField('field_contract_end', 'Contract date must be a valid date on or after 1/1/2000.');
    const reportedField = bindMdyDateField('field_reported_on', 'Reported On must be a valid date on or after 1/1/2000.');
    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) return;
        if (event.target.closest('#moduleForm .pdc-date-field')) return;
        closeAllCals();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeAllCals();
    });
    document.getElementById('moduleForm')?.addEventListener('submit', (event) => {
        if (!startField.validate() || !endField.validate() || !reportedField.validate()) event.preventDefault();
    });
    document.getElementById('operationAddButton')?.addEventListener('click', () => {
        startField.setValue('');
        endField.setValue('');
        reportedField.setValue('');
        closeAllCals();
    });
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-operation-edit]');
        if (!button) return;
        const values = JSON.parse(button.dataset.values || '{}');
        startField.setValue(values.contract_start || '');
        endField.setValue(values.contract_end || '');
        reportedField.setValue(values.reported_on || '');
        closeAllCals();
    });
});
</script>
@endif
@endpush
