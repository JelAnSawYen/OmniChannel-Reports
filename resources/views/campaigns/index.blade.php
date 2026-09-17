@extends('layouts.app')
@section('content')
<div class="page-head">
    <div>
        <h1 class="page-title">Campaigns</h1>
        <p class="page-subtitle">Manage campaign names, FTE, and program locations.</p>
    </div>
    <div class="toolbar">
        <form class="search-box media-search-form" id="campaignSearchForm" method="GET" action="{{ route('campaigns') }}">
            <span class="search-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
            </span>
            <input id="campaignSearchInput" name="search" value="{{ $search }}" placeholder="Search Campaigns" aria-label="Search Campaigns" autocomplete="off">
            @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
        </form>
        <button class="btn primary" type="submit" form="campaignSearchForm">Search</button>
        @if(auth()->user()->hasPermission('media.export'))
        @include('partials.data-transfer', [
            'canExport' => true,
            'canImport' => auth()->user()->hasPermission('media.create'),
            'exportUrl' => route('campaigns.export', request()->query()),
            'templateUrl' => auth()->user()->hasPermission('media.create') ? route('campaigns.import.template') : '',
            'previewUrl' => auth()->user()->hasPermission('media.create') ? route('campaigns.import.preview') : '',
            'confirmUrl' => auth()->user()->hasPermission('media.create') ? route('campaigns.import.confirm') : '',
            'errorsUrl' => auth()->user()->hasPermission('media.create') ? route('campaigns.import.errors') : '',
            'previewHeaders' => array_values(\App\Support\InventoryImportCatalog::campaigns()['fields']),
            'entityTitle' => 'Campaigns',
        ])
        @endif
        @if(auth()->user()->hasPermission('media.create'))
            <button class="plus-btn" type="button" id="campaignAddButton" aria-label="Add Campaign" title="Add Campaign">+</button>
        @endif
    </div>
</div>

<div class="table-card table-wrap">
<table class="campaigns-table" aria-label="Campaigns">
<thead>
<tr>
    <th>Id</th>
    <th>Campaign</th>
    <th>FTE</th>
    <th>Location</th>
    <th class="actions-column">Actions</th>
</tr>
</thead>
<tbody>
@forelse($records as $record)
@php
    $editValues = [
        'name' => $record->name,
        'fte' => $record->fte,
        'location' => $record->location,
    ];
@endphp
<tr @if(auth()->user()->hasPermission('media.delete')) data-bulk-row="main" data-bulk-id="{{ $record->id }}" data-bulk-url="{{ route('campaigns.bulk-destroy') }}" @endif>
    <td>{{ ($records->firstItem() ?? 1) + $loop->index }}</td>
    <td><span class="campaigns-name">{{ $record->name }}</span></td>
    <td>{{ $record->fte !== null ? $record->fte : '—' }}</td>
    <td>{{ $record->location ?: '—' }}</td>
    <td class="actions-column">
        <div class="row-actions">
            @if(auth()->user()->hasPermission('media.edit'))
                <button class="action-btn edit" type="button" data-campaign-edit data-id="{{ $record->id }}" data-values='@json($editValues)' title="Edit Campaign" aria-label="Edit Campaign">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                </button>
            @endif
            @if(auth()->user()->hasPermission('media.delete'))
                <form method="POST" action="{{ route('campaigns.destroy', $record) }}" data-confirm="Delete this campaign?" data-confirm-title="Delete Campaign" data-confirm-ok="Delete">
                    @csrf
                    @method('DELETE')
                    <button class="action-btn delete" type="submit" title="Delete Campaign" aria-label="Delete Campaign">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                    </button>
                </form>
            @endif
        </div>
    </td>
</tr>
@empty
<tr><td colspan="5"><div class="empty-state">No Campaigns Found</div></td></tr>
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
<div class="modal-backdrop" id="campaignModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="campaignModalTitle">Add Campaign</h3>
            <button type="button" class="close-btn" data-close="campaignModal">×</button>
        </div>
        <form id="campaignForm" method="POST" action="{{ route('campaigns.store') }}">
            @csrf
            <input type="hidden" name="_method" id="campaignMethod" value="POST">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group full">
                        <label for="campaign_name">Campaigns</label>
                        <input class="form-control" id="campaign_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="campaign_fte">FTE</label>
                        <input class="form-control" id="campaign_fte" name="fte" type="number" min="0" step="1" required>
                    </div>
                    <div class="form-group">
                        <label for="campaign_location">Location</label>
                        <select class="form-control" id="campaign_location" name="location" required>
                            <option value="" selected hidden>Select Location</option>
                            @foreach($locations as $slug => $name)
                                <option value="{{ $name }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn secondary" data-close="campaignModal">Cancel</button>
                <button type="submit" class="btn primary">Save</button>
            </div>
        </form>
    </div>
</div>
@endif

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('campaignModal');
    const form = document.getElementById('campaignForm');
    const method = document.getElementById('campaignMethod');
    const storeAction = @json(route('campaigns.store'));
    const editBase = @json(url('/campaigns'));

    document.getElementById('campaignAddButton')?.addEventListener('click', () => {
        if (!form || !method) return;
        method.value = 'POST';
        form.action = storeAction;
        document.getElementById('campaignModalTitle').textContent = 'Add Campaign';
        form.reset();
        modal?.classList.add('visible');
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-campaign-edit]');
        if (!button || !form || !method) return;
        const recordId = Number(button.dataset.id);
        if (!Number.isInteger(recordId) || recordId < 1) return;
        const values = JSON.parse(button.dataset.values || '{}');
        document.getElementById('campaignModalTitle').textContent = 'Edit Campaign';
        method.value = 'PUT';
        form.action = editBase + '/' + recordId;
        document.getElementById('campaign_name').value = values.name ?? '';
        document.getElementById('campaign_fte').value = values.fte ?? '';
        document.getElementById('campaign_location').value = values.location ?? '';
        modal?.classList.add('visible');
    });
});
</script>
@include('partials.inventory-import-script', [
    'previewUrl' => auth()->user()->hasPermission('media.create') ? route('campaigns.import.preview') : '',
    'confirmUrl' => auth()->user()->hasPermission('media.create') ? route('campaigns.import.confirm') : '',
    'errorsUrl' => auth()->user()->hasPermission('media.create') ? route('campaigns.import.errors') : '',
    'previewFields' => ['name', 'fte', 'location'],
])
@endpush
