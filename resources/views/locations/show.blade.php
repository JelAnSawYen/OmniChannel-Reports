@extends('layouts.app')
@section('content')
@php
    $canCreate = auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways();
    $canEdit = auth()->user()->hasPermission('media.edit') && auth()->user()->canMutateGateways();
    $canDelete = auth()->user()->hasPermission('media.delete') && auth()->user()->canMutateGateways();
@endphp
<div class="page-head">
    <div>
        <h1 class="page-title">{{ $locationName }}</h1>
        <p class="page-subtitle">Manage GSM gateway records assigned to {{ $locationName }}.</p>
    </div>
    <div class="toolbar">
        <form class="search-box media-search-form" id="locationSearchForm" method="GET" action="{{ route('program-location.show', $locationSlug) }}">
            <span class="search-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
            </span>
            <input id="locationSearchInput" name="search" value="{{ $search }}" placeholder="Search {{ $locationName }}" aria-label="Search {{ $locationName }}" autocomplete="off">
            <button type="button" class="search-clear" data-clear-search aria-label="Clear search" title="Clear search">×</button>
            @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
        </form>
        <button class="btn primary" type="submit" form="locationSearchForm">Search</button>
        @if($search !== '')
            <a class="btn secondary" href="{{ route('program-location.show', $locationSlug) }}">Reset</a>
        @endif
        <a class="btn secondary" href="{{ route('program-location') }}">All Locations</a>
        @if(auth()->user()->hasPermission('media.export'))
        @include('partials.data-transfer', [
            'canExport' => true,
            'canImport' => $canCreate,
            'exportUrl' => route('program-location.export', array_merge(['location' => $locationSlug], request()->query())),
            'templateUrl' => $canCreate ? route('program-location.import.template', $locationSlug) : '',
            'previewUrl' => $canCreate ? route('program-location.import.preview', $locationSlug) : '',
            'confirmUrl' => $canCreate ? route('program-location.import.confirm', $locationSlug) : '',
            'errorsUrl' => $canCreate ? route('program-location.import.errors', $locationSlug) : '',
            'previewHeaders' => array_values($columns),
            'entityTitle' => $locationName.' GSM Gateways',
        ])
        @endif
        @if($canCreate)
            <button class="plus-btn" type="button" id="locationAddButton" aria-label="Add GSM Gateway" title="Add GSM Gateway">+</button>
        @endif
    </div>
</div>

<div class="table-card table-wrap">
<table aria-label="{{ $locationName }} GSM gateways">
<thead>
<tr>
    <th>Id</th>
    @foreach($columns as $label)
        <th>{{ $label }}</th>
    @endforeach
    <th>Last Updated</th>
    <th class="actions-column">Actions</th>
</tr>
</thead>
<tbody>
@forelse($records as $record)
<tr>
    <td>{{ ($records->firstItem() ?? 1) + $loop->index }}</td>
    @foreach($columns as $field => $label)
        <td>{{ $record->{$field} }}</td>
    @endforeach
    <td>{{ $record->updated_at?->format('M d, Y h:i A') ?? '—' }}</td>
    <td class="actions-column">
        <div class="row-actions">
            @if($canEdit)
                <button class="action-btn edit" type="button" data-location-edit data-id="{{ $record->id }}" data-values='@json($record->only(array_keys($columns)))' title="Edit GSM Gateway" aria-label="Edit GSM Gateway">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                </button>
            @endif
            @if($canDelete)
                <form method="POST" action="{{ route('program-location.destroy', ['location' => $locationSlug, 'gateway' => $record->id]) }}" data-confirm="Delete this GSM Gateway record?" data-confirm-title="Delete GSM Gateway" data-confirm-ok="Delete">
                    @csrf
                    @method('DELETE')
                    <button class="action-btn delete" type="submit" title="Delete GSM Gateway" aria-label="Delete GSM Gateway">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                    </button>
                </form>
            @endif
        </div>
    </td>
</tr>
@empty
<tr><td colspan="{{ count($columns) + 3 }}"><div class="empty-state">No GSM Gateway records found for {{ $locationName }}.</div></td></tr>
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
@php
    $canCreate = auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways();
    $canEdit = auth()->user()->hasPermission('media.edit') && auth()->user()->canMutateGateways();
@endphp
@if($canCreate || $canEdit)
<div class="modal-backdrop" id="locationModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="locationModalTitle">Add GSM Gateway</h3>
            <button type="button" class="close-btn" data-close="locationModal">×</button>
        </div>
        <form id="locationForm" method="POST" action="{{ route('program-location.store', $locationSlug) }}">
            @csrf
            <input type="hidden" name="_method" id="locationMethod" value="POST">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group full">
                        <label for="field_site_name">Site Name</label>
                        <select class="form-control" name="site_name" id="field_site_name">
                            @foreach($locations as $slug => $name)
                                <option value="{{ $name }}" {{ $name === $locationName ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group"><label for="field_site_code">Site Code</label><input class="form-control" name="site_code" id="field_site_code" required></div>
                    <div class="form-group"><label for="field_ip_address">IP Address</label><input class="form-control" name="ip_address" id="field_ip_address" required></div>
                    <div class="form-group"><label for="field_username">Username</label><input class="form-control" name="username" id="field_username" required></div>
                    <div class="form-group"><label for="field_database">Database</label><input class="form-control" name="database" id="field_database" required></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn secondary" data-close="locationModal">Cancel</button>
                <button class="btn primary" type="submit">Save</button>
            </div>
        </form>
    </div>
</div>
@endif

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('locationModal');
    const form = document.getElementById('locationForm');
    const method = document.getElementById('locationMethod');
    const storeAction = @json(route('program-location.store', $locationSlug));
    const editBase = @json(url('/program-location/'.$locationSlug));
    const defaultSiteName = @json($locationName);

    document.getElementById('locationAddButton')?.addEventListener('click', () => {
        if (!form || !method) return;
        method.value = 'POST';
        form.action = storeAction;
        document.getElementById('locationModalTitle').textContent = 'Add GSM Gateway';
        form.reset();
        const siteName = document.getElementById('field_site_name');
        if (siteName) siteName.value = defaultSiteName;
        modal?.classList.add('visible');
    });

    document.querySelectorAll('[data-location-edit]').forEach((button) => button.addEventListener('click', () => {
        if (!form || !method) return;
        const recordId = Number(button.dataset.id);
        if (!Number.isInteger(recordId) || recordId < 1) return;
        const values = JSON.parse(button.dataset.values || '{}');
        document.getElementById('locationModalTitle').textContent = 'Edit GSM Gateway';
        method.value = 'PUT';
        form.action = editBase + '/' + recordId;
        Object.keys(values).forEach((key) => {
            const field = document.getElementById('field_' + key);
            if (field) field.value = values[key] ?? '';
        });
        modal?.classList.add('visible');
    }));
});
</script>
@include('partials.inventory-import-script', [
    'previewUrl' => $canCreate ? route('program-location.import.preview', $locationSlug) : '',
    'confirmUrl' => $canCreate ? route('program-location.import.confirm', $locationSlug) : '',
    'errorsUrl' => $canCreate ? route('program-location.import.errors', $locationSlug) : '',
    'previewFields' => array_keys($columns),
])
@endpush
