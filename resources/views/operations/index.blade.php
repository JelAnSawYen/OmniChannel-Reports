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
        if ($isInbound && $field === 'network') {
            $editValues['network'] = \App\Support\GsmSimInventory::canonicalNetwork($record->network) ?: ($record->network ?: '');
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
    if ($isInbound) {
        $editValues['mobile_assignments'] = $record->mobileDisplayRows();
    }
@endphp
<tr @if(auth()->user()->hasPermission('media.delete')) data-bulk-row="main" data-bulk-id="{{ $recordId }}" data-bulk-url="{{ route($module.'.bulk-destroy') }}" @endif>
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
                    @foreach($record->mobileDisplayRows() as $mobileRow)
                        <span>{{ $mobileRow['mobile'] }}</span>
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
        <td>
            @if($record->mobileList() === [])
                —
            @else
                <span class="pin-stack">
                    @foreach($record->mobileDisplayRows() as $mobileRow)
                        <span>{{ $mobileRow['hostname'] !== '' ? $mobileRow['hostname'] : '—' }}</span>
                    @endforeach
                </span>
            @endif
        </td>
        <td>
            @if($record->mobileList() === [])
                —
            @else
                <span class="pin-stack">
                    @foreach($record->mobileDisplayRows() as $mobileRow)
                        <span>{{ $mobileRow['port'] !== '' ? $mobileRow['port'] : '—' }}</span>
                    @endforeach
                </span>
            @endif
        </td>
        <td>{{ $record->networkLabel() }}</td>
        <td class="pin-remarks-col">{{ $record->remarksLabel() }}</td>
    @else
    @foreach($config['columns'] as $field=>$label)
        @php $isNumericCol = in_array($field, $numericColumns, true); @endphp
        <td @class(['num-col' => $isNumericCol, 'sb-specs-col' => $isBooster && $field === 'specs', 'dg-issue-col' => $isDefective && $field === 'issue'])>
            @if($field==='monthly_cost')
                <span class="num-align" data-label="{{ $label }}">₱{{ number_format((float) $record->$field, 2) }}</span>
            @elseif($isSim && $field === 'port')
                {{ $record->displayPort() }}
            @elseif($isSim && $field === 'ip_address')
                {{ $record->displayIp() }}
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
        @include('partials.table-pager', ['paginator' => $records])
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
                            <label for="field_network">Network</label>
                            <select class="form-control" name="network" id="field_network">
                                <option value="" selected hidden>Select Network</option>
                                @foreach(($pinNetworks ?? []) as $pinNetwork)
                                    <option value="{{ $pinNetwork }}">{{ $pinNetwork }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="pinMobileInput">Mobile Number</label>
                            <div class="pin-campaign-combo pin-search-combo">
                                <input class="form-control" id="pinMobileInput" placeholder="Search or type a mobile number..." autocomplete="off" disabled>
                                <div class="pin-campaign-menu" id="pinMobileMenu" hidden role="listbox"></div>
                            </div>
                            <div id="pinMobileHidden"></div>
                        </div>
                        <div class="form-group full pin-selected">
                            <label class="pin-selected-title" for="pinSelectedTable">Selected Mobile Numbers</label>
                            <table class="pin-selected-table" id="pinSelectedTable">
                                <thead>
                                    <tr>
                                        <th>Mobile Number</th>
                                        <th>GSM Gateway</th>
                                        <th>Port</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="pinSelectedBody"></tbody>
                            </table>
                        </div>
                        <div class="form-group">
                            <label for="pinLandlineInput">Landline</label>
                            <div class="pin-campaign-combo pin-search-combo">
                                <input class="form-control" id="pinLandlineInput" placeholder="Search landline..." autocomplete="off">
                                <div class="pin-campaign-menu" id="pinLandlineMenu" hidden role="listbox"></div>
                            </div>
                            <div class="pin-chips" id="pinLandlineChips" data-pin-chips="landline"></div>
                            <div id="pinLandlineHidden"></div>
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
                                <option value="" selected hidden>Select Media Gateway</option>
                                @foreach($gateways as $gateway)
                                    <option value="{{ $gateway->site_code }}">{{ $gateway->site_code }} — {{ $gateway->site_name }}</option>
                                @endforeach
                            </select>
                        @elseif($field==='location')
                            <select class="form-control" name="{{ $field }}" id="field_{{ $field }}">
                                <option value="" selected hidden></option>
                                @foreach(($locations ?? []) as $locationName)
                                    <option value="{{ $locationName }}">{{ $locationName }}</option>
                                @endforeach
                            </select>
                        @elseif($isSim && $field === 'ip_address')
                            <select class="form-control" name="{{ $field }}" id="field_{{ $field }}" required>
                                <option value="" selected hidden></option>
                                @foreach($gsmGateways->unique('ip_address') as $gateway)
                                    @if($gateway->ip_address)
                                        <option value="{{ $gateway->ip_address }}">{{ $gateway->ip_address }}</option>
                                    @endif
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
            if (!field) return;
            const value = values[key] ?? '';
            if (field.tagName === 'SELECT' && value && ![...field.options].some((option) => option.value === String(value))) {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = value;
                field.appendChild(option);
            }
            field.value = value;
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
    const network = document.getElementById('field_network');
    const mobileInput = document.getElementById('pinMobileInput');
    const mobileMenu = document.getElementById('pinMobileMenu');
    const landlineInput = document.getElementById('pinLandlineInput');
    const landlineMenu = document.getElementById('pinLandlineMenu');
    const inboundForm = document.getElementById('moduleForm');
    const simDirectory = @json($pinSimDirectory ?? []);
    const channelNumbers = @json($pinChannelNumbers ?? []);
    const trashIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>';
    const selected = [];
    const lists = { landline: [] };

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    }[char]));

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

    const currentNetwork = () => String(network?.value || '').trim();
    const networkMobiles = () => simDirectory[currentNetwork()] || [];
    const lookupMobile = (value) => networkMobiles().find((row) => row.mobile === value) || null;
    const dash = (value) => String(value || '').trim() || '—';

    const setMobileEnabled = () => {
        if (!mobileInput) return;
        mobileInput.disabled = currentNetwork() === '';
        if (mobileInput.disabled) {
            mobileInput.value = '';
            if (mobileMenu) mobileMenu.hidden = true;
        }
    };

    const renderSearchMenu = (list, query, menuEl, emptyText) => {
        if (!menuEl) return;
        const needle = String(query || '').trim().toLowerCase();
        const matches = list.filter((item) => !needle || item.toLowerCase().includes(needle));
        menuEl.innerHTML = matches.length
            ? matches.map((item) => '<button class="pin-campaign-option" type="button" role="option" data-value="' + escapeHtml(item) + '">' + escapeHtml(item) + '</button>').join('')
            : '<div class="pin-campaign-empty">' + escapeHtml(emptyText) + '</div>';
        menuEl.hidden = false;
    };

    const renderSelected = () => {
        const body = document.getElementById('pinSelectedBody');
        const hidden = document.getElementById('pinMobileHidden');
        if (body) {
            body.innerHTML = selected.map((row, index) => (
                '<tr>' +
                '<td>' + escapeHtml(row.mobile) + '</td>' +
                '<td>' + escapeHtml(dash(row.hostname)) + '</td>' +
                '<td>' + escapeHtml(dash(row.port)) + '</td>' +
                '<td><div class="row-actions"><button class="action-btn delete" type="button" data-pin-remove-mobile="' + index + '" title="Delete" aria-label="Delete">' + trashIcon + '</button></div></td>' +
                '</tr>'
            )).join('');
        }
        if (hidden) {
            hidden.innerHTML = selected.map((row) => (
                '<input type="hidden" name="mobile_numbers[]" value="' + escapeHtml(row.mobile) + '">'
            )).join('');
        }
    };

    const renderLandlineChips = () => {
        const chips = document.getElementById('pinLandlineChips');
        const hidden = document.getElementById('pinLandlineHidden');
        if (chips) {
            chips.innerHTML = lists.landline.map((value, index) => (
                '<span class="pin-chip">' + escapeHtml(value) +
                '<button type="button" class="pin-chip-remove" data-pin-remove="landline" data-index="' + index + '" aria-label="Delete">×</button></span>'
            )).join('');
        }
        if (hidden) {
            hidden.innerHTML = lists.landline.map((value) => (
                '<input type="hidden" name="landline_numbers[]" value="' + escapeHtml(value) + '">'
            )).join('');
        }
    };

    const refreshAssignments = () => {
        selected.forEach((row) => {
            const hit = lookupMobile(row.mobile);
            row.hostname = hit?.hostname || '';
            row.port = hit?.port || '';
        });
        renderSelected();
    };

    const addMobile = (raw) => {
        const value = String(raw || '').trim();
        if (!value) return;
        if (currentNetwork() === '') {
            network?.setCustomValidity('Network is required when Mobile numbers are entered.');
            network?.reportValidity();
            return;
        }
        network?.setCustomValidity('');
        if (!/^[0-9]{1,50}$/.test(value)) {
            mobileInput?.setCustomValidity('Mobile must contain only digits.');
            mobileInput?.reportValidity();
            return;
        }
        if (selected.some((row) => row.mobile === value) || lists.landline.includes(value)) {
            mobileInput?.setCustomValidity('Number is duplicated.');
            mobileInput?.reportValidity();
            return;
        }
        mobileInput?.setCustomValidity('');
        const hit = lookupMobile(value);
        selected.push({
            mobile: value,
            hostname: hit?.hostname || '',
            port: hit?.port || '',
        });
        renderSelected();
    };

    const addLandline = (raw) => {
        const value = String(raw || '').trim();
        if (!value) return;
        if (!/^[0-9]{1,50}$/.test(value)) {
            landlineInput?.setCustomValidity('Landline must contain only digits.');
            landlineInput?.reportValidity();
            return;
        }
        if (!channelNumbers.includes(value)) {
            landlineInput?.setCustomValidity('Landline must match an existing Channel Number.');
            landlineInput?.reportValidity();
            return;
        }
        if (lists.landline.includes(value) || selected.some((row) => row.mobile === value)) {
            landlineInput?.setCustomValidity('Number is duplicated.');
            landlineInput?.reportValidity();
            return;
        }
        landlineInput?.setCustomValidity('');
        lists.landline.push(value);
        renderLandlineChips();
    };

    mobileInput?.addEventListener('focus', () => {
        if (mobileInput.disabled) return;
        renderSearchMenu(networkMobiles().map((row) => row.mobile), mobileInput.value, mobileMenu, 'No mobile numbers found.');
    });
    mobileInput?.addEventListener('input', () => {
        mobileInput.setCustomValidity('');
        if (mobileInput.disabled) return;
        renderSearchMenu(networkMobiles().map((row) => row.mobile), mobileInput.value, mobileMenu, 'No mobile numbers found.');
    });
    mobileInput?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        addMobile(mobileInput.value);
        if (mobileInput.validity.valid) {
            mobileInput.value = '';
            if (mobileMenu) mobileMenu.hidden = true;
        }
    });
    mobileMenu?.addEventListener('click', (event) => {
        const option = event.target.closest('.pin-campaign-option');
        if (!option) return;
        addMobile(option.dataset.value || '');
        if (mobileInput) mobileInput.value = '';
        mobileMenu.hidden = true;
    });

    landlineInput?.addEventListener('focus', () => {
        renderSearchMenu(channelNumbers, landlineInput.value, landlineMenu, 'No channel numbers found.');
    });
    landlineInput?.addEventListener('input', () => {
        landlineInput.setCustomValidity('');
        renderSearchMenu(channelNumbers, landlineInput.value, landlineMenu, 'No channel numbers found.');
    });
    landlineInput?.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') return;
        event.preventDefault();
        addLandline(landlineInput.value);
        if (landlineInput.validity.valid) {
            landlineInput.value = '';
            if (landlineMenu) landlineMenu.hidden = true;
        }
    });
    landlineMenu?.addEventListener('click', (event) => {
        const option = event.target.closest('.pin-campaign-option');
        if (!option) return;
        addLandline(option.dataset.value || '');
        if (landlineInput) landlineInput.value = '';
        landlineMenu.hidden = true;
    });

    network?.addEventListener('change', () => {
        network.setCustomValidity('');
        setMobileEnabled();
        refreshAssignments();
    });

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) return;
        if (!event.target.closest('.pin-campaign-combo')) {
            closeMenu();
            if (mobileMenu) mobileMenu.hidden = true;
            if (landlineMenu) landlineMenu.hidden = true;
        }
        const mobileTrash = event.target.closest('[data-pin-remove-mobile]');
        if (mobileTrash) {
            const index = Number(mobileTrash.getAttribute('data-pin-remove-mobile'));
            if (!Number.isNaN(index)) selected.splice(index, 1);
            renderSelected();
            return;
        }
        const chipTrash = event.target.closest('[data-pin-remove]');
        if (!chipTrash) return;
        const index = Number(chipTrash.getAttribute('data-index'));
        if (Number.isNaN(index)) return;
        lists.landline.splice(index, 1);
        renderLandlineChips();
    });

    inboundForm?.addEventListener('submit', (event) => {
        mobileInput?.setCustomValidity('');
        landlineInput?.setCustomValidity('');
        network?.setCustomValidity('');
        if (mobileInput?.value.trim()) addMobile(mobileInput.value);
        if (landlineInput?.value.trim()) addLandline(landlineInput.value);
        if (mobileInput && mobileInput.validity.valid) mobileInput.value = '';
        if (landlineInput && landlineInput.validity.valid) landlineInput.value = '';
        if ((mobileInput && !mobileInput.validity.valid) || (landlineInput && !landlineInput.validity.valid) || (network && !network.validity.valid)) {
            event.preventDefault();
            return;
        }
        if (selected.length === 0 && lists.landline.length === 0) {
            event.preventDefault();
            const target = landlineInput || mobileInput;
            target?.setCustomValidity('Enter at least one Mobile or Landline number.');
            target?.reportValidity();
            return;
        }
        if (selected.length > 0 && currentNetwork() === '') {
            event.preventDefault();
            network?.setCustomValidity('Network is required when Mobile numbers are entered.');
            network?.reportValidity();
        }
    });

    const resetNumbers = () => {
        selected.splice(0, selected.length);
        lists.landline = [];
        renderSelected();
        renderLandlineChips();
        if (mobileInput) mobileInput.value = '';
        if (landlineInput) landlineInput.value = '';
        network?.setCustomValidity('');
        setMobileEnabled();
    };
    document.getElementById('operationAddButton')?.addEventListener('click', resetNumbers);
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-operation-edit]');
        if (!button) return;
        const values = JSON.parse(button.dataset.values || '{}');
        selected.splice(0, selected.length);
        const assignments = Array.isArray(values.mobile_assignments) ? values.mobile_assignments : [];
        const mobiles = Array.isArray(values.mobile_numbers) ? values.mobile_numbers.map(String) : [];
        (assignments.length ? assignments : mobiles.map((mobile) => ({ mobile, hostname: '', port: '' }))).forEach((row) => {
            const mobile = String(row.mobile || row);
            const hit = lookupMobile(mobile);
            selected.push({
                mobile,
                hostname: hit?.hostname || row.hostname || '',
                port: hit?.port || row.port || '',
            });
        });
        lists.landline = Array.isArray(values.landline_numbers) ? values.landline_numbers.map(String) : [];
        renderSelected();
        renderLandlineChips();
        setMobileEnabled();
        refreshAssignments();
    });
    setMobileEnabled();
    renderSelected();
    renderLandlineChips();
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
