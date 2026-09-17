@extends('layouts.app')
@section('content')
@php
    $canRevealSecrets = auth()->user()->canExportGatewaySecrets();
    $isGsm = ($resource['index'] ?? '') === 'gsm-gateways.index';
    $gatewayColumns = $isGsm
        ? [
            'hostname' => 'Hostname',
            'ip_address' => 'IP',
            'site_code' => 'Serial Number',
            'channel_count' => 'Channel Count',
            'device_function' => 'Function',
            'site_name' => 'Site',
            'username' => 'User',
            'password' => 'Password',
        ]
        : [
            'ip_address' => 'Hostname IP',
            'site_code' => 'Serial Number',
            'plan' => 'Plan',
            'port' => 'Port',
            'network' => 'Network',
            'device_function' => 'Function',
            'site_name' => 'Site',
            'username' => 'User',
            'password' => 'Password',
        ];
    $previewHeaders = array_values(\App\Support\InventoryImportCatalog::gateway($isGsm ? 'gsm-gateways' : 'media-gateways')['fields']);
    $colspan = count($gatewayColumns) + 1;
@endphp
<div class="page-head">
    <div>
        <h1 class="page-title">{{ $resource['title'] }}</h1>
        <p class="page-subtitle">{{ $resource['subtitle'] }}</p>
    </div>
    <div class="toolbar">
        <form class="search-box media-search-form" id="mediaSearchForm" method="GET" action="{{ route($resource['index']) }}">
            <span class="search-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
            </span>
            <input id="mediaSearchInput" name="search" value="{{ $search }}" placeholder="{{ $resource['search'] }}" aria-label="{{ $resource['search'] }}" autocomplete="off">
        </form>
        <button class="btn primary" type="submit" form="mediaSearchForm">Search</button>
        @if(auth()->user()->hasPermission('media.export'))
        @include('partials.data-transfer', [
            'canExport' => true,
            'canImport' => auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways(),
            'exportUrl' => route($resource['export'], request()->query()),
            'templateUrl' => (auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways()) ? route($isGsm ? 'gsm-gateways.import.template' : 'media-gateways.import.template') : '',
            'previewUrl' => (auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways()) ? route($isGsm ? 'gsm-gateways.import.preview' : 'media-gateways.import.preview') : '',
            'confirmUrl' => (auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways()) ? route($isGsm ? 'gsm-gateways.import.confirm' : 'media-gateways.import.confirm') : '',
            'errorsUrl' => (auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways()) ? route($isGsm ? 'gsm-gateways.import.errors' : 'media-gateways.import.errors') : '',
            'previewHeaders' => $previewHeaders,
            'entityTitle' => $resource['plural'],
        ])
        @endif
        @if(auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways())
            <button class="plus-btn" type="button" data-open-modal="add-media-gateway" aria-label="Add {{ $resource['entity'] }}" title="Add {{ $resource['entity'] }}">+</button>
        @endif
    </div>
</div>

<div class="table-card table-wrap">
<table class="gsm-table" aria-label="{{ $resource['title'] }}">
<thead>
<tr>
    @foreach($gatewayColumns as $field=>$label)
        <th>
            @if($isGsm && $field === 'hostname')
                <span class="gsm-host-cell">
                    <span class="ca-toggle" aria-hidden="true"></span>
                    <button type="button" class="sortable-button" data-sort="{{ $field }}"><span>{{ $label }}</span></button>
                </span>
            @else
                <button type="button" class="sortable-button" data-sort="{{ $field }}"><span>{{ $label }}</span></button>
            @endif
        </th>
    @endforeach
    <th class="actions-column">Actions</th>
</tr>
</thead>
<tbody id="mediaGatewayRows">
@forelse($mediaGateways as $gateway)
@if($isGsm)
@php $assignments = $gateway->assignmentPayload(); @endphp
<tr class="gsm-gateway-row" data-gateway="{{ $gateway->id }}" @if(auth()->user()->hasPermission('media.delete') && auth()->user()->canMutateGateways()) data-bulk-row="main" data-bulk-id="{{ $gateway->id }}" data-bulk-url="{{ route($isGsm ? 'gsm-gateways.bulk-destroy' : 'media-gateways.bulk-destroy') }}" data-bulk-ajax="1" @endif>
    <td>
        <span class="gsm-host-cell">
            <button type="button" class="ca-toggle" data-ca-toggle="{{ $gateway->id }}" aria-expanded="false" aria-controls="gsm-panel-{{ $gateway->id }}" title="Expand {{ $gateway->hostname ?: $gateway->site_code }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 6 6 6-6 6"></path></svg>
            </button>
            <span>{{ $gateway->hostname ?: '—' }}</span>
        </span>
    </td>
    <td>{{ $gateway->ip_address }}</td>
    <td>{{ $gateway->site_code }}</td>
    <td>{{ $gateway->channel_count ?: '—' }}</td>
    <td>{{ $gateway->device_function ?: '—' }}</td>
    <td>{{ $gateway->site_name }}</td>
    <td>{{ $gateway->username }}</td>
    <td>
        <span class="pdc-secret">
            <span class="pdc-secret-mask">••••••</span>
            @if($canRevealSecrets && $gateway->password)
                <span class="pdc-secret-value" hidden>{{ $gateway->password }}</span>
                <button type="button" class="pdc-secret-toggle" title="Show password" aria-label="Show password">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            @endif
        </span>
    </td>
    <td class="actions-column">
        @if((auth()->user()->hasPermission('media.edit') || auth()->user()->hasPermission('media.delete')) && auth()->user()->canMutateGateways())
        <div class="ca-menu">
            <button class="ca-menu-btn" type="button" aria-haspopup="true" aria-expanded="false" aria-label="{{ $resource['entity'] }} actions" title="{{ $resource['entity'] }} actions">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
            </button>
            <div class="ca-menu-dropdown" role="menu" hidden>
                @if(auth()->user()->hasPermission('media.edit') && auth()->user()->canMutateGateways())
                    <button class="ca-menu-item edit" type="button" role="menuitem" data-edit-id="{{ $gateway->id }}" data-edit-hostname="{{ $gateway->hostname }}" data-edit-site_name="{{ $gateway->site_name }}" data-edit-site_code="{{ $gateway->site_code }}" data-edit-ip_address="{{ $gateway->ip_address }}" data-edit-channel_count="{{ $gateway->channel_count }}" data-edit-device_function="{{ $gateway->device_function }}" data-edit-username="{{ $gateway->username }}" @if($canRevealSecrets) data-edit-password="{{ $gateway->password }}" @endif title="Edit {{ $resource['entity'] }}" aria-label="Edit {{ $resource['entity'] }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                        Edit
                    </button>
                @endif
                @if(auth()->user()->hasPermission('media.delete') && auth()->user()->canMutateGateways())
                    <button class="ca-menu-item delete" type="button" role="menuitem" data-delete-id="{{ $gateway->id }}" title="Delete {{ $resource['entity'] }}" aria-label="Delete {{ $resource['entity'] }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                        Delete
                    </button>
                @endif
            </div>
        </div>
        @endif
    </td>
</tr>
<tr class="ca-nested-row" id="gsm-panel-{{ $gateway->id }}" hidden>
    <td colspan="{{ $colspan }}">
        <div class="ca-nested">
            <table class="gsm-sim-nested" aria-label="SIM assignments">
                <thead>
                    <tr>
                        <th>IMEI</th>
                        <th>Mobile Number</th>
                        <th>Plan</th>
                        <th>IP</th>
                        <th>Port</th>
                        <th class="actions-column">
                            <span class="ca-actions-head">
                                Actions
                                @if(auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways())
                                    <button class="plus-btn" type="button" data-gsm-sim-add="{{ $gateway->id }}" data-channel-count="{{ $gateway->channel_count }}" data-assignment-count="{{ count($assignments) }}" title="Add SIM Assignment" aria-label="Add SIM Assignment">+</button>
                                @endif
                            </span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                @forelse($assignments as $assignment)
                    <tr @if(auth()->user()->hasPermission('media.delete') && auth()->user()->canMutateGateways()) data-bulk-row="nested" data-bulk-id="{{ $assignment['assignment_id'] }}" data-bulk-url="{{ route('gsm-gateways.assignments.bulk-destroy', $gateway) }}" data-bulk-ajax="1" @endif>
                        <td>{{ $assignment['imei'] ?: '—' }}</td>
                        <td>{{ $assignment['mobile_number'] ?: '—' }}</td>
                        <td>{{ $assignment['plan'] ?: '—' }}</td>
                        <td>{{ $gateway->ip_address }}</td>
                        <td>{{ $assignment['port'] ?: '—' }}</td>
                        <td class="actions-column">
                            <div class="row-actions">
                                @if(auth()->user()->hasPermission('media.edit') && auth()->user()->canMutateGateways())
                                    <button class="action-btn edit" type="button" data-gsm-sim-edit data-gateway-id="{{ $gateway->id }}" data-assignment-id="{{ $assignment['assignment_id'] }}" data-sim-type="{{ $assignment['sim_type'] }}" data-sim-id="{{ $assignment['id'] }}" data-network="{{ $assignment['network'] }}" data-port="{{ $assignment['port'] }}" title="Edit" aria-label="Edit">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                                    </button>
                                @endif
                                @if(auth()->user()->hasPermission('media.delete') && auth()->user()->canMutateGateways())
                                    <button class="action-btn delete" type="button" data-gsm-sim-delete data-gateway-id="{{ $gateway->id }}" data-assignment-id="{{ $assignment['assignment_id'] }}" title="Delete" aria-label="Delete">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="empty-state">No SIM assignments.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </td>
</tr>
@else
<tr @if(auth()->user()->hasPermission('media.delete') && auth()->user()->canMutateGateways()) data-bulk-row="main" data-bulk-id="{{ $gateway->id }}" data-bulk-url="{{ route($isGsm ? 'gsm-gateways.bulk-destroy' : 'media-gateways.bulk-destroy') }}" data-bulk-ajax="1" @endif>
    <td>{{ $gateway->ip_address }}</td>
    <td>{{ $gateway->site_code }}</td>
    <td>{{ $gateway->plan ?: '—' }}</td>
    <td>{{ $gateway->port ?: '—' }}</td>
    <td>{{ $gateway->network ?: '—' }}</td>
    <td>{{ $gateway->device_function ?: '—' }}</td>
    <td>{{ $gateway->site_name }}</td>
    <td>{{ $gateway->username }}</td>
    <td>
        <span class="pdc-secret">
            <span class="pdc-secret-mask">••••••</span>
            @if($canRevealSecrets && $gateway->password)
                <span class="pdc-secret-value" hidden>{{ $gateway->password }}</span>
                <button type="button" class="pdc-secret-toggle" title="Show password" aria-label="Show password">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            @endif
        </span>
    </td>
    <td class="actions-column">
        <div class="row-actions">
            @if(auth()->user()->hasPermission('media.edit') && auth()->user()->canMutateGateways())
                <button type="button" class="action-btn edit" data-edit-id="{{ $gateway->id }}" data-edit-site_name="{{ $gateway->site_name }}" data-edit-site_code="{{ $gateway->site_code }}" data-edit-ip_address="{{ $gateway->ip_address }}" data-edit-plan="{{ $gateway->plan }}" data-edit-port="{{ $gateway->port }}" data-edit-network="{{ $gateway->network }}" data-edit-device_function="{{ $gateway->device_function }}" data-edit-username="{{ $gateway->username }}" @if($canRevealSecrets) data-edit-password="{{ $gateway->password }}" @endif title="Edit {{ $resource['entity'] }}" aria-label="Edit {{ $resource['entity'] }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                </button>
            @endif
            @if(auth()->user()->hasPermission('media.delete') && auth()->user()->canMutateGateways())
                <button type="button" class="action-btn delete" data-delete-id="{{ $gateway->id }}" title="Delete {{ $resource['entity'] }}" aria-label="Delete {{ $resource['entity'] }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                </button>
            @endif
        </div>
    </td>
</tr>
@endif
@empty
<tr><td colspan="{{ $colspan }}"><div class="empty-state">{{ $resource['empty'] }}</div></td></tr>
@endforelse
</tbody>
</table>
<div class="table-footer">
    <span id="recordSummary">Showing {{ $mediaGateways->firstItem() ?? 0 }} to {{ $mediaGateways->lastItem() ?? 0 }} of {{ $mediaGateways->total() }} entries</span>
    <div class="footer-right">
        <span>Records per page:</span>
        <select id="perPageSelect" class="per-page-select">
            <option value="5" {{ $perPage===5?'selected':'' }}>5</option>
            <option value="10" {{ $perPage===10?'selected':'' }}>10</option>
            <option value="25" {{ $perPage===25?'selected':'' }}>25</option>
            <option value="50" {{ $perPage===50?'selected':'' }}>50</option>
        </select>
        @include('partials.table-pager', ['paginator' => $mediaGateways, 'pagerId' => 'paginationLinks', 'dataPage' => true])
    </div>
</div>
</div>
@endsection

@push('modals')
@if(auth()->user()->canMutateGateways() && (auth()->user()->hasPermission('media.create') || auth()->user()->hasPermission('media.edit')))
<div class="modal-backdrop" id="mediaGatewayModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="mediaGatewayModalTitle">Add {{ $resource['entity'] }}</h3>
            <button type="button" class="close-btn" data-close="mediaGatewayModal">×</button>
        </div>
        <form id="mediaGatewayForm" method="POST" action="{{ route($resource['store']) }}">
            @csrf
            <input type="hidden" name="_method" id="formMethod" value="POST">
            <input type="hidden" id="gatewayId">
            <div class="modal-body">
                <div id="formErrors"></div>
                @if($isGsm)
                    <div class="form-grid">
                        <div class="form-group"><label for="hostname">Hostname</label><input class="form-control" id="hostname" name="hostname" required></div>
                        <div class="form-group">
                            <label for="device_function">Function</label>
                            <select class="form-control" id="device_function" name="device_function" required>
                                <option value="" selected hidden>Select Function</option>
                                <option value="Inbound">Inbound</option>
                                <option value="Outbound">Outbound</option>
                            </select>
                        </div>
                        <div class="form-group"><label for="ip_address">IP Address</label><input class="form-control" id="ip_address" name="ip_address" required></div>
                        <div class="form-group">
                            <label for="site_name">Site</label>
                            <select class="form-control" id="site_name" name="site_name" required>
                                <option value="" selected hidden>Select Site</option>
                                @foreach(($locations ?? []) as $slug => $name)
                                    <option value="{{ $name }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group"><label for="site_code">Serial Number</label><input class="form-control" id="site_code" name="site_code" required></div>
                        <div class="form-group"><label for="username">User</label><input class="form-control" id="username" name="username" required></div>
                        <div class="form-group"><label for="channel_count">Channel Count</label><input class="form-control" id="channel_count" name="channel_count" type="number" min="1" max="512" required></div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="pdc-password-field">
                                <input class="form-control" type="password" id="password" name="password" autocomplete="new-password">
                                @if(auth()->user()->canExportGatewaySecrets())
                                    <button type="button" class="pdc-secret-toggle" data-toggle-input="password" title="Show password" aria-label="Show password">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @else
                    <div class="form-grid">
                        <div class="form-group"><label for="ip_address">Hostname IP</label><input class="form-control" id="ip_address" name="ip_address" required></div>
                        <div class="form-group"><label for="site_code">Serial Number</label><input class="form-control" id="site_code" name="site_code" required></div>
                        <div class="form-group"><label for="plan">Plan</label><input class="form-control" id="plan" name="plan"></div>
                        <div class="form-group"><label for="port">Port</label><input class="form-control" id="port" name="port"></div>
                        <div class="form-group"><label for="network">Network</label><input class="form-control" id="network" name="network"></div>
                        <div class="form-group"><label for="device_function">Function</label><input class="form-control" id="device_function" name="device_function"></div>
                        <div class="form-group"><label for="site_name">Site</label><input class="form-control" id="site_name" name="site_name" required></div>
                        <div class="form-group"><label for="username">User</label><input class="form-control" id="username" name="username" required></div>
                        <div class="form-group full">
                            <label for="password">Password</label>
                            <div class="pdc-password-field">
                                <input class="form-control" type="password" id="password" name="password" autocomplete="new-password">
                                @if(auth()->user()->canExportGatewaySecrets())
                                    <button type="button" class="pdc-secret-toggle" data-toggle-input="password" title="Show password" aria-label="Show password">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
            <div class="modal-footer"><button type="button" class="btn secondary" data-close="mediaGatewayModal">Cancel</button><button type="submit" class="btn primary">Save</button></div>
        </form>
    </div>
</div>
@endif

@if($isGsm && auth()->user()->canMutateGateways() && (auth()->user()->hasPermission('media.create') || auth()->user()->hasPermission('media.edit')))
<div class="modal-backdrop" id="gsmSimModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="gsmSimModalTitle">Add SIM Assignment</h3>
            <button type="button" class="close-btn" data-close="gsmSimModal">×</button>
        </div>
        <form id="gsmSimForm">
            <input type="hidden" id="gsmSimGatewayId">
            <input type="hidden" id="gsmSimAssignmentId">
            <div class="modal-body">
                <div id="gsmSimFormErrors"></div>
                <div class="form-group">
                    <label for="gsmSimNetwork">Network <span class="req">*</span></label>
                    <select class="form-control" id="gsmSimNetwork" name="network" required>
                        <option value="" selected hidden>Select Network</option>
                        @foreach(($simNetworks ?? []) as $networkName)
                            <option value="{{ $networkName }}">{{ $networkName }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="gsm-sim-block">
                    <label for="gsmSimSearch">SIM Selection <span class="req">*</span></label>
                    <input class="form-control" id="gsmSimSearch" type="search" placeholder="Search SIM (IMEI or Mobile Number)..." autocomplete="off">
                    <div class="gsm-sim-list" id="gsmSimList">
                        <table>
                            <thead>
                                <tr>
                                    <th>IMEI</th>
                                    <th>Mobile Number</th>
                                    <th>Plan</th>
                                    <th>Port</th>
                                </tr>
                            </thead>
                            <tbody id="gsmSimRows">
                                <tr><td colspan="4">Select a network to load SIM records.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn secondary" data-close="gsmSimModal">Cancel</button><button type="submit" class="btn primary">Save</button></div>
        </form>
    </div>
</div>
@endif

@if(auth()->user()->canMutateGateways() && auth()->user()->hasPermission('media.delete'))
<div class="modal-backdrop" id="deleteModal">
    <div class="modal small">
        <div class="modal-header"><h3 id="deleteModalTitle">Delete {{ $resource['entity'] }}</h3><button type="button" class="close-btn" data-close="deleteModal">×</button></div>
        <div class="modal-body"><p id="deleteModalBody">Are you sure you want to delete this {{ $resource['entity'] }}?</p></div>
        <div class="modal-footer"><button type="button" class="btn secondary" data-close="deleteModal">Cancel</button><button type="button" class="btn danger" id="confirmDeleteBtn">Delete</button></div>
    </div>
</div>
@endif
@include('partials.inventory-import-script', [
    'previewUrl' => (auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways()) ? route($isGsm ? 'gsm-gateways.import.preview' : 'media-gateways.import.preview') : '',
    'confirmUrl' => (auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways()) ? route($isGsm ? 'gsm-gateways.import.confirm' : 'media-gateways.import.confirm') : '',
    'errorsUrl' => (auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways()) ? route($isGsm ? 'gsm-gateways.import.errors' : 'media-gateways.import.errors') : '',
    'previewFields' => $isGsm
        ? ['hostname', 'ip_address', 'site_code', 'channel_count', 'device_function', 'site_name', 'username', 'password', 'assignment_port', 'imei', 'mobile_number', 'assignment_network', 'assignment_plan']
        : ['ip_address', 'site_code', 'plan', 'port', 'network', 'device_function', 'site_name', 'username', 'password'],
])
@endpush
