@extends('layouts.app')
@section('content')
<div class="page-head">
    <div>
        <h1 class="page-title">Channel Range List</h1>
        <p class="page-subtitle">Manage channel numbers for each SIP channel.</p>
    </div>
    <div class="toolbar">
        <form class="search-box media-search-form" id="crlSearchForm" method="GET" action="{{ route('channel-range-list') }}">
            <span class="search-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
            </span>
            <input id="crlSearchInput" name="search" value="{{ $search }}" placeholder="Search Channel Range List" aria-label="Search Channel Range List" autocomplete="off">
            @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
        </form>
        <button class="btn primary" type="submit" form="crlSearchForm">Search</button>
        @if(auth()->user()->hasPermission('media.export'))
        @include('partials.data-transfer', [
            'canExport' => true,
            'canImport' => auth()->user()->hasPermission('media.create'),
            'exportUrl' => route('channel-range-list.export', request()->query()),
            'templateUrl' => auth()->user()->hasPermission('media.create') ? route('channel-range-list.import.template') : '',
            'previewUrl' => auth()->user()->hasPermission('media.create') ? route('channel-range-list.import.preview') : '',
            'confirmUrl' => auth()->user()->hasPermission('media.create') ? route('channel-range-list.import.confirm') : '',
            'errorsUrl' => auth()->user()->hasPermission('media.create') ? route('channel-range-list.import.errors') : '',
            'previewHeaders' => array_values(app(\App\Services\ChannelRangeListImportService::class)->fields()),
            'entityTitle' => 'Channel Range List',
        ])
        @endif
        @if(auth()->user()->hasPermission('media.create'))
            <button class="plus-btn" type="button" id="crlAddButton" aria-label="Add" title="Add">+</button>
        @endif
    </div>
</div>

<div class="table-card table-wrap">
<table class="ca-table crl-table" aria-label="Channel Range List">
<colgroup>
    <col class="crl-col-toggle">
    <col class="crl-col-campaign">
    <col class="crl-col-channel">
    <col class="crl-col-sip">
    <col class="crl-col-actions">
</colgroup>
<thead>
<tr>
    <th class="crl-toggle-col">
        <span class="ca-toggle" aria-hidden="true"></span>
    </th>
    <th class="crl-campaign-col">Campaign</th>
    <th class="crl-channel-col" aria-hidden="true"></th>
    <th class="crl-sip-col">SIP Name</th>
    <th class="crl-actions-col" aria-hidden="true"></th>
</tr>
</thead>
<tbody>
@forelse($groups as $sip)
@php
    $campaignName = $sip->campaign?->name ?: '—';
    $sipName = $sip->etpi_sip_name ?: '—';
@endphp
<tr class="ca-campaign-row" data-campaign="{{ $sip->id }}">
    <td class="crl-toggle-col">
        <button type="button" class="ca-toggle" data-ca-toggle="{{ $sip->id }}" aria-expanded="false" aria-controls="crl-panel-{{ $sip->id }}" title="Expand {{ $campaignName }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 6 6 6-6 6"></path></svg>
        </button>
    </td>
    <td class="crl-campaign-col">
        <span class="ca-campaign-identity">
            <button type="button" class="ca-campaign-link" data-ca-toggle="{{ $sip->id }}">{{ $campaignName }}</button>
        </span>
    </td>
    <td class="crl-channel-col"></td>
    <td class="crl-sip-col"><span class="crl-sip-name">{{ $sipName }}</span></td>
    <td class="crl-actions-col"></td>
</tr>
<tr class="ca-nested-row" id="crl-panel-{{ $sip->id }}" hidden>
    <td colspan="5">
        <div class="ca-nested">
            <table class="crl-nested" aria-label="{{ $campaignName }} channel numbers">
                <colgroup>
                    <col class="crl-col-toggle">
                    <col class="crl-col-campaign">
                    <col class="crl-col-channel">
                    <col class="crl-col-sip">
                    <col class="crl-col-actions">
                </colgroup>
                <thead>
                    <tr>
                        <th class="crl-toggle-col"></th>
                        <th class="crl-campaign-col"></th>
                        <th class="crl-channel-col">Channel Number</th>
                        <th class="crl-sip-col"></th>
                        <th class="crl-actions-col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($sip->channelNumbers as $number)
                    <tr>
                        <td class="crl-toggle-col"></td>
                        <td class="crl-campaign-col"></td>
                        <td class="crl-channel-col"><span class="crl-channel-number">{{ $number->channel_number }}</span></td>
                        <td class="crl-sip-col"></td>
                        <td class="crl-actions-col actions-column">
                            <span class="row-actions">
                                @if(auth()->user()->hasPermission('media.edit'))
                                    <button class="action-btn edit" type="button" data-crl-edit data-id="{{ $number->id }}" data-channel-number="{{ $number->channel_number }}" title="Edit" aria-label="Edit">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                                    </button>
                                @endif
                                @if(auth()->user()->hasPermission('media.delete'))
                                    <form method="POST" action="{{ route('channel-range-list.destroy', $number) }}" data-confirm="Delete this record?" data-confirm-title="Delete Record" data-confirm-ok="Delete">
                                        @csrf
                                        @method('DELETE')
                                        <button class="action-btn delete" type="submit" title="Delete" aria-label="Delete">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                                        </button>
                                    </form>
                                @endif
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="empty-state">No channel numbers for this SIP channel.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </td>
</tr>
@empty
<tr><td colspan="5"><div class="empty-state">No Channel Range List records found.</div></td></tr>
@endforelse
</tbody>
</table>
<div class="table-footer">
    <span>Showing {{ $groups->firstItem() ?? 0 }} to {{ $groups->lastItem() ?? 0 }} of {{ $groups->total() }} entries</span>
    <div class="footer-right">
        <span>Records per page:</span>
        <select class="per-page-select" onchange="location.href=this.value" aria-label="Records per page">
            @foreach([5,10,25,50] as $size)
                <option value="{{ request()->fullUrlWithQuery(['per_page'=>$size,'page'=>1]) }}" {{ $perPage===$size?'selected':'' }}>{{ $size }}</option>
            @endforeach
        </select>
        <div class="pager">
            @if($groups->onFirstPage())
                <span class="page-number disabled">‹</span>
            @else
                <a class="page-number" href="{{ $groups->previousPageUrl() }}">‹</a>
            @endif
            @for($page = 1; $page <= max($groups->lastPage(), 1); $page++)
                @if($page === $groups->currentPage())
                    <span class="page-number active">{{ $page }}</span>
                @else
                    <a class="page-number" href="{{ $groups->url($page) }}">{{ $page }}</a>
                @endif
            @endfor
            @if($groups->hasMorePages())
                <a class="page-number" href="{{ $groups->nextPageUrl() }}">›</a>
            @else
                <span class="page-number disabled">›</span>
            @endif
        </div>
    </div>
</div>
</div>
@endsection

@push('modals')
@if(auth()->user()->hasPermission('media.create'))
<div class="modal-backdrop" id="crlAddModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="crlAddModalTitle">Add Channel Range List</h3>
            <button type="button" class="close-btn" data-close="crlAddModal">×</button>
        </div>
        <form id="crlAddForm" method="POST" action="{{ route('channel-range-list.store') }}">
            @csrf
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="crl_sip_channel_id">Campaign</label>
                        <select class="form-control" name="sip_channel_id" id="crl_sip_channel_id" required>
                            <option value="" selected hidden>Select Campaign</option>
                            @foreach($sipChannels as $channel)
                                <option value="{{ $channel->id }}" data-sip-name="{{ $channel->etpi_sip_name }}">{{ $channel->campaign?->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="crl_sip_name">SIP Name</label>
                        <input class="form-control" type="text" id="crl_sip_name" readonly disabled tabindex="-1">
                    </div>
                    <div class="form-group">
                        <label for="crl_from">From</label>
                        <input class="form-control" name="from" id="crl_from" inputmode="numeric" autocomplete="off" required>
                    </div>
                    <div class="form-group">
                        <label for="crl_to">To</label>
                        <input class="form-control" name="to" id="crl_to" inputmode="numeric" autocomplete="off" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn secondary" data-close="crlAddModal">Cancel</button>
                <button class="btn primary" type="submit" id="crlAddSubmit">Save</button>
            </div>
        </form>
    </div>
</div>
@endif

@if(auth()->user()->hasPermission('media.edit'))
<div class="modal-backdrop" id="crlEditModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="crlEditModalTitle">Edit Channel Number</h3>
            <button type="button" class="close-btn" data-close="crlEditModal">×</button>
        </div>
        <form id="crlEditForm" method="POST" action="">
            @csrf
            <input type="hidden" name="_method" value="PUT">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="crl_channel_number">Channel Number</label>
                        <input class="form-control" name="channel_number" id="crl_channel_number" inputmode="numeric" autocomplete="off" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn secondary" data-close="crlEditModal">Cancel</button>
                <button class="btn primary" type="submit" id="crlEditSubmit">Save</button>
            </div>
        </form>
    </div>
</div>
@endif

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) return;
        const button = event.target.closest('[data-ca-toggle]');
        if (!button) return;
        const id = button.getAttribute('data-ca-toggle');
        const panel = document.getElementById('crl-panel-' + id);
        const row = document.querySelector('tr.ca-campaign-row[data-campaign="' + id + '"]');
        if (!panel) return;
        const open = panel.hasAttribute('hidden');
        panel.toggleAttribute('hidden', !open);
        row?.classList.toggle('open', open);
        document.querySelectorAll('[data-ca-toggle="' + id + '"]').forEach((el) => {
            el.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) return;
        const row = event.target.closest('tr.ca-campaign-row[data-campaign]');
        if (!row) return;
        if (event.target.closest('.actions-column, .ca-menu, a, input, select, textarea, label, .action-btn, .plus-btn')) return;
        if (event.target.closest('[data-ca-toggle]')) return;
        row.querySelector('[data-ca-toggle]')?.click();
    });

    const campaignSelect = document.getElementById('crl_sip_channel_id');
    const sipNameInput = document.getElementById('crl_sip_name');
    const syncSipName = () => {
        const option = campaignSelect?.selectedOptions?.[0];
        if (sipNameInput) sipNameInput.value = option?.getAttribute('data-sip-name') || '';
    };
    campaignSelect?.addEventListener('change', syncSipName);

    const addModal = document.getElementById('crlAddModal');
    const addForm = document.getElementById('crlAddForm');
    document.getElementById('crlAddButton')?.addEventListener('click', () => {
        addForm?.reset();
        syncSipName();
        addModal?.classList.add('visible');
    });

    const editModal = document.getElementById('crlEditModal');
    const editForm = document.getElementById('crlEditForm');
    const editBase = @json(url('/channel-range-list'));
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-crl-edit]');
        if (!button) return;
        const recordId = Number(button.dataset.id);
        if (!Number.isInteger(recordId) || recordId < 1) return;
        if (editForm) editForm.action = editBase + '/' + recordId;
        const field = document.getElementById('crl_channel_number');
        if (field) field.value = button.dataset.channelNumber || '';
        editModal?.classList.add('visible');
    });

    const restoreExpanded = @json(session('crl_expanded'));
    if (restoreExpanded) {
        document.querySelector('[data-ca-toggle="' + restoreExpanded + '"]')?.click();
    }
});
</script>
@include('partials.inventory-import-script', [
    'previewUrl' => auth()->user()->hasPermission('media.create') ? route('channel-range-list.import.preview') : '',
    'confirmUrl' => auth()->user()->hasPermission('media.create') ? route('channel-range-list.import.confirm') : '',
    'errorsUrl' => auth()->user()->hasPermission('media.create') ? route('channel-range-list.import.errors') : '',
    'previewFields' => array_keys(app(\App\Services\ChannelRangeListImportService::class)->fields()),
])
@endpush
