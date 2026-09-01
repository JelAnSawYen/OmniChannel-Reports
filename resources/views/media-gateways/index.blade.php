@extends('layouts.app')
@section('content')
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
            <button type="button" class="search-clear" id="mediaSearchClear" aria-label="Clear search" title="Clear search">×</button>
        </form>
        <button class="btn primary" type="submit" form="mediaSearchForm">Search</button>
        @if($search !== '')<a class="btn secondary" href="{{ route($resource['index']) }}" id="mediaSearchReset">Reset</a>@endif
        @if(auth()->user()->hasPermission('media.export'))
        @include('partials.data-transfer', [
            'canExport' => true,
            'canImport' => auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways(),
            'exportUrl' => route($resource['export'], request()->query()),
            'templateUrl' => (auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways()) ? route($resource['index'] === 'gsm-gateways.index' ? 'gsm-gateways.import.template' : 'media-gateways.import.template') : '',
            'previewUrl' => (auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways()) ? route($resource['index'] === 'gsm-gateways.index' ? 'gsm-gateways.import.preview' : 'media-gateways.import.preview') : '',
            'confirmUrl' => (auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways()) ? route($resource['index'] === 'gsm-gateways.index' ? 'gsm-gateways.import.confirm' : 'media-gateways.import.confirm') : '',
            'errorsUrl' => (auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways()) ? route($resource['index'] === 'gsm-gateways.index' ? 'gsm-gateways.import.errors' : 'media-gateways.import.errors') : '',
            'previewHeaders' => ['Site Name', 'Site Code', 'IP Address', 'Username', 'Database'],
            'entityTitle' => $resource['plural'],
        ])
        @endif
        @if(auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways())
            <button class="plus-btn" type="button" data-open-modal="add-media-gateway" aria-label="Add {{ $resource['entity'] }}" title="Add {{ $resource['entity'] }}">+</button>
        @endif
    </div>
</div>

<div class="table-card table-wrap">
<table aria-label="{{ $resource['title'] }}">
<thead>
<tr>
    @foreach(['id'=>'Id','site_name'=>'Site Name','site_code'=>'Site Code','ip_address'=>'IP Address','username'=>'Username','database'=>'Database'] as $field=>$label)
        <th><button type="button" class="sortable-button" data-sort="{{ $field }}"><span>{{ $label }}</span></button></th>
    @endforeach
    <th>Last Updated</th>
    <th class="actions-column">Actions</th>
</tr>
</thead>
<tbody id="mediaGatewayRows">
@forelse($mediaGateways as $gateway)
<tr>
    <td>{{ ($mediaGateways->firstItem() ?? 1) + $loop->index }}</td>
    <td>{{ $gateway->site_name }}</td>
    <td>{{ $gateway->site_code }}</td>
    <td>{{ $gateway->ip_address }}</td>
    <td>{{ $gateway->username }}</td>
    <td>{{ $gateway->database }}</td>
    <td>{{ $gateway->updated_at?->format('M d, Y h:i A') ?? '—' }}</td>
    <td class="actions-column">
        <div class="row-actions">
            @if(auth()->user()->hasPermission('media.edit') && auth()->user()->canMutateGateways())
                <button type="button" class="action-btn edit" data-edit-id="{{ $gateway->id }}" data-edit-site_name="{{ $gateway->site_name }}" data-edit-site_code="{{ $gateway->site_code }}" data-edit-ip_address="{{ $gateway->ip_address }}" data-edit-username="{{ $gateway->username }}" data-edit-database="{{ $gateway->database }}" title="Edit {{ $resource['entity'] }}" aria-label="Edit {{ $resource['entity'] }}">
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
@empty
<tr><td colspan="8"><div class="empty-state">{{ $resource['empty'] }}</div></td></tr>
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
        <div class="pager" id="paginationLinks">
            @for($page=1;$page<=$mediaGateways->lastPage();$page++)
                @if($page===$mediaGateways->currentPage())
                    <span class="page-number active">{{ $page }}</span>
                @else
                    <a class="page-number" data-page="{{ $page }}" href="{{ $mediaGateways->url($page) }}">{{ $page }}</a>
                @endif
            @endfor
        </div>
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
                <div class="form-grid">
                    <div class="form-group full"><label for="site_name">Site Name</label><input class="form-control" id="site_name" name="site_name" required></div>
                    <div class="form-group"><label for="site_code">Site Code</label><input class="form-control" id="site_code" name="site_code" required></div>
                    <div class="form-group"><label for="ip_address">IP Address</label><input class="form-control" id="ip_address" name="ip_address" required></div>
                    <div class="form-group"><label for="username">Username</label><input class="form-control" id="username" name="username" required></div>
                    <div class="form-group"><label for="database">Database</label><input class="form-control" id="database" name="database" required></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn secondary" data-close="mediaGatewayModal">Cancel</button><button type="submit" class="btn primary">Save {{ $resource['entity'] }}</button></div>
        </form>
    </div>
</div>
@endif

@if(auth()->user()->canMutateGateways() && auth()->user()->hasPermission('media.delete'))
<div class="modal-backdrop" id="deleteModal">
    <div class="modal small">
        <div class="modal-header"><h3>Delete {{ $resource['entity'] }}</h3><button type="button" class="close-btn" data-close="deleteModal">×</button></div>
        <div class="modal-body"><p>Are you sure you want to delete this {{ $resource['entity'] }}?</p></div>
        <div class="modal-footer"><button type="button" class="btn secondary" data-close="deleteModal">Cancel</button><button type="button" class="btn danger" id="confirmDeleteBtn">Delete</button></div>
    </div>
</div>
@endif
@include('partials.inventory-import-script', [
    'previewUrl' => (auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways()) ? route($resource['index'] === 'gsm-gateways.index' ? 'gsm-gateways.import.preview' : 'media-gateways.import.preview') : '',
    'confirmUrl' => (auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways()) ? route($resource['index'] === 'gsm-gateways.index' ? 'gsm-gateways.import.confirm' : 'media-gateways.import.confirm') : '',
    'errorsUrl' => (auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways()) ? route($resource['index'] === 'gsm-gateways.index' ? 'gsm-gateways.import.errors' : 'media-gateways.import.errors') : '',
    'previewFields' => ['site_name', 'site_code', 'ip_address', 'username', 'database'],
])
@endpush
