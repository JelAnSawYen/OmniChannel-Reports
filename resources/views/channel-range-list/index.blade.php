@extends('layouts.app')
@section('content')
<div class="page-head">
    <div>
        <h1 class="page-title">Channel Range</h1>
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
            'previewHeaders' => array_values(app(\App\Services\Sip\ChannelRangeListImportService::class)->fields()),
            'entityTitle' => 'Channel Range List',
        ])
        @endif
    </div>
</div>

<div class="table-card table-wrap">
<table class="ca-table crl-table" aria-label="Channel Range List">
<colgroup>
    <col class="crl-col-campaign">
    <col class="crl-col-channel">
    <col class="crl-col-sip">
    <col class="crl-col-actions">
</colgroup>
<thead>
<tr>
    <th class="crl-campaign-col">
        <span class="ca-campaign-cell">
            <span class="ca-toggle" aria-hidden="true"></span>
            <span class="ca-campaign-identity">Campaign</span>
        </span>
    </th>
    <th class="crl-channel-col">Channel Range</th>
    <th class="crl-sip-col">SIP Name</th>
    <th class="crl-actions-col">Actions</th>
</tr>
</thead>
<tbody>
@forelse($groups as $sip)
@php
    $campaignName = $sip->campaign?->name ?: '—';
    $sipName = $sip->etpi_sip_name ?: '—';
    $channelRange = $sip->channelRangeFromNumbers();
@endphp
<tr class="ca-campaign-row" data-campaign="{{ $sip->id }}" @if(auth()->user()->hasPermission('media.delete')) data-bulk-row="main" data-bulk-ids="{{ $sip->channelNumbers->pluck('id')->implode(',') }}" data-bulk-url="{{ route('channel-range-list.bulk-destroy') }}" @endif>
    <td class="crl-campaign-col">
        <span class="ca-campaign-cell">
            <button type="button" class="ca-toggle" data-ca-toggle="{{ $sip->id }}" aria-expanded="false" aria-controls="crl-panel-{{ $sip->id }}" title="Expand {{ $campaignName }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 6 6 6-6 6"></path></svg>
            </button>
            <span class="ca-campaign-identity">
                <span class="ca-campaign-link campaigns-name">{{ $campaignName }}</span>
            </span>
        </span>
    </td>
    <td class="crl-channel-col"><span class="crl-channel-range">{{ $channelRange !== '' ? $channelRange : '—' }}</span></td>
    <td class="crl-sip-col"><span class="crl-sip-name">{{ $sipName }}</span></td>
    <td class="crl-actions-col actions-column">
        @if(auth()->user()->hasPermission('media.delete'))
            <span class="row-actions">
                <form method="POST" action="{{ route('channel-range-list.bulk-destroy') }}" data-confirm="Delete this record?" data-confirm-title="Delete Record" data-confirm-ok="Delete">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="sip_channel_id" value="{{ $sip->id }}">
                    @foreach($sip->channelNumbers as $number)
                        <input type="hidden" name="ids[]" value="{{ $number->id }}">
                    @endforeach
                    <button class="action-btn delete" type="submit" title="Delete" aria-label="Delete">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                    </button>
                </form>
            </span>
        @endif
    </td>
</tr>
<tr class="ca-nested-row" id="crl-panel-{{ $sip->id }}" hidden>
    <td colspan="4">
        <div class="ca-nested">
            <table class="crl-nested" aria-label="{{ $campaignName }} channel numbers">
                <colgroup>
                    <col class="crl-col-campaign">
                    <col class="crl-col-channel">
                    <col class="crl-col-sip">
                    <col class="crl-col-actions">
                </colgroup>
                <thead>
                    <tr>
                        <th class="crl-campaign-col"></th>
                        <th class="crl-channel-col">Channel Number</th>
                        <th class="crl-sip-col"></th>
                        <th class="crl-actions-col"></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($sip->channelNumbers as $number)
                    <tr @if(auth()->user()->hasPermission('media.delete')) data-bulk-row="nested" data-bulk-id="{{ $number->id }}" data-bulk-url="{{ route('channel-range-list.bulk-destroy') }}" @endif>
                        <td class="crl-campaign-col"></td>
                        <td class="crl-channel-col"><span class="crl-channel-number">{{ $number->channel_number }}</span></td>
                        <td class="crl-sip-col"></td>
                        <td class="crl-actions-col"></td>
                    </tr>
                @empty
                    <tr><td colspan="4"><div class="empty-state">No channel numbers for this SIP channel.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </td>
</tr>
@empty
<tr><td colspan="4"><div class="empty-state">No Channel Range List records found.</div></td></tr>
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
        @include('partials.table-pager', ['paginator' => $groups])
    </div>
</div>
</div>
@endsection

@push('modals')
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
        if (event.ctrlKey || event.metaKey) return;
        if (event.target.closest('.actions-column, .ca-menu, a, input, select, textarea, label, .action-btn, .plus-btn')) return;
        if (event.target.closest('[data-ca-toggle]')) return;
        row.querySelector('[data-ca-toggle]')?.click();
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
    'previewFields' => array_keys(app(\App\Services\Sip\ChannelRangeListImportService::class)->fields()),
])
@endpush
