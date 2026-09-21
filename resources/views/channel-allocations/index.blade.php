@extends('layouts.app')
@section('content')
<div class="page-head">
    <div>
        <h1 class="page-title">Channel Allocation</h1>
        <p class="page-subtitle">Manage channel allocations by campaign.</p>
    </div>
    <div class="toolbar">
        <form class="search-box media-search-form" id="channelAllocationSearchForm" method="GET" action="{{ route('channel-allocation') }}">
            <span class="search-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
            </span>
            <input id="channelAllocationSearchInput" name="search" value="{{ $search }}" placeholder="Search Channels" aria-label="Search Channels" autocomplete="off">
            @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
        </form>
        <button class="btn primary" type="submit" form="channelAllocationSearchForm">Search</button>
        @if(auth()->user()->hasPermission('media.export'))
        <div class="transfer">
            <button class="btn" type="button" id="transferButton" aria-haspopup="true" aria-expanded="false" aria-controls="transferMenu">
                <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4v12"></path><path d="m7 11 5 5 5-5"></path><path d="M5 20h14"></path></svg>
                Data Transfer
            </button>
            <div class="transfer-menu" id="transferMenu" role="menu">
                <button type="button" role="menuitem" id="importDataButton">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20V8"></path><path d="m7 13 5-5 5 5"></path><path d="M5 4h14"></path></svg>
                    Import Data
                </button>
                <a role="menuitem" id="exportDataButton" href="{{ route('channel-allocation.export', request()->query()) }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4v12"></path><path d="m7 11 5 5 5-5"></path><path d="M5 20h14"></path></svg>
                    Export Data
                </a>
            </div>
        </div>
        @endif
        @if(auth()->user()->hasPermission('media.create'))
            <button class="plus-btn" type="button" id="campaignAddButton" aria-label="Add Campaign" title="Add Campaign">+</button>
        @endif
    </div>
</div>

<div class="table-card table-wrap">
<table class="ca-table" aria-label="Channel Allocation">
<thead>
<tr>
    <th>
        <span class="ca-campaign-cell">
            <span class="ca-toggle" aria-hidden="true"></span>
            <span class="ca-campaign-identity">Campaign</span>
        </span>
    </th>
    <th class="num-col">Total Channels</th>
    <th class="num-col">FTE</th>
    <th>Caller ID</th>
    <th>Prefix</th>
    <th class="ca-remarks">Remarks</th>
    <th class="actions-column">Actions</th>
</tr>
</thead>
<tbody>
@forelse($campaigns as $campaign)
@php
    $allocCount = $campaign->allocations->count();
    $campaignValues = [
        'id' => $campaign->id,
        'name' => $campaign->name,
        'media_gateway' => $campaign->media_gateway,
        'total_channels_allocated' => $campaign->total_channels_allocated,
        'fte' => $campaign->fte,
        'caller_id' => $campaign->caller_id,
        'prefix' => $campaign->prefix,
        'remarks' => $campaign->remarks,
    ];
@endphp
<tr class="ca-campaign-row" data-campaign="{{ $campaign->id }}" @if(auth()->user()->hasPermission('media.delete')) data-bulk-row="main" data-bulk-id="{{ $campaign->id }}" data-bulk-url="{{ route('channel-allocation.bulk-destroy') }}" @endif>
    <td>
        <span class="ca-campaign-cell">
            <button type="button" class="ca-toggle" data-ca-toggle="{{ $campaign->id }}" aria-expanded="false" aria-controls="ca-panel-{{ $campaign->id }}" title="Expand {{ $campaign->name }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 6 6 6-6 6"></path></svg>
            </button>
            <span class="ca-campaign-identity">
                <button type="button" class="ca-campaign-link" data-ca-toggle="{{ $campaign->id }}">{{ $campaign->name }}</button>
                <span class="ca-count">{{ $allocCount }} {{ $allocCount === 1 ? 'allocation' : 'allocations' }}</span>
            </span>
        </span>
    </td>
    <td class="num-col"><span class="num-align ca-total" data-label="Total Channels">{{ $campaign->total_channels_allocated ?? '—' }}</span></td>
    <td class="num-col"><span class="num-align" data-label="FTE">{{ $campaign->fte ?? '—' }}</span></td>
    <td><span class="num-align" data-label="Caller ID">{{ $campaign->caller_id ?: '—' }}</span></td>
    <td><span class="num-align" data-label="Prefix">{{ $campaign->prefix ?: '—' }}</span></td>
    <td class="ca-remarks"><span class="num-align" data-label="Remarks">{{ $campaign->remarks ?: '—' }}</span></td>
    <td class="actions-column">
        @if(auth()->user()->hasPermission('media.edit') || auth()->user()->hasPermission('media.delete'))
        <div class="ca-menu">
            <button class="ca-menu-btn" type="button" aria-haspopup="true" aria-expanded="false" aria-label="Campaign actions" title="Campaign actions">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
            </button>
            <div class="ca-menu-dropdown" role="menu" hidden>
                @if(auth()->user()->hasPermission('media.edit'))
                    <button class="ca-menu-item edit" type="button" role="menuitem" data-campaign-edit data-id="{{ $campaign->id }}" data-values='@json($campaignValues)'>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                        Edit
                    </button>
                @endif
                @if(auth()->user()->hasPermission('media.delete'))
                    <form method="POST" action="{{ route('channel-allocation.destroy', $campaign) }}" data-confirm="Delete this campaign's allocations? The master Campaign will not be deleted." data-confirm-title="Delete Allocations" data-confirm-ok="Delete">
                        @csrf
                        @method('DELETE')
                        <button class="ca-menu-item delete" type="submit" role="menuitem">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                            Delete
                        </button>
                    </form>
                @endif
            </div>
        </div>
        @endif
    </td>
</tr>
<tr class="ca-nested-row" id="ca-panel-{{ $campaign->id }}" hidden>
    <td colspan="7">
        <div class="ca-nested">
            <table aria-label="{{ $campaign->name }} allocations">
                <thead>
                    <tr>
                        <th>Channel</th>
                        <th>Network</th>
                        <th class="num-col">Line Priority</th>
                        <th class="num-col">Channel Count</th>
                        <th class="actions-column">
                            <span class="ca-actions-head">
                                Actions
                                @if(auth()->user()->hasPermission('media.create'))
                                    <button class="plus-btn" type="button" data-allocation-add data-campaign="{{ $campaign->id }}" data-campaign-name="{{ $campaign->name }}" title="Add allocation" aria-label="Add allocation">+</button>
                                @endif
                            </span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                @forelse($campaign->allocations as $allocation)
                    @php
                        $allocationValues = [
                            'channel' => $allocation->channelLabel() === '—' ? '' : $allocation->channelLabel(),
                            'channel_type' => $allocation->channelType(),
                            'network' => $allocation->network,
                            'line_priority' => $allocation->line_priority,
                            'total_channel_allocated' => $allocation->total_channel_allocated,
                        ];
                    @endphp
                    <tr @if(auth()->user()->hasPermission('media.delete')) data-bulk-row="nested" data-bulk-id="{{ $allocation->id }}" data-bulk-url="{{ route('channel-allocation.allocations.bulk-destroy', $campaign) }}" @endif>
                        <td><span class="num-align" data-label="Channel">{{ $allocation->channelLabel() }}</span></td>
                        <td><span class="num-align" data-label="Network">{{ $allocation->network ?: '—' }}</span></td>
                        <td class="num-col"><span class="num-align" data-label="Line Priority">{{ $allocation->line_priority ?? '—' }}</span></td>
                        <td class="num-col"><span class="num-align" data-label="Channel Count">{{ $allocation->total_channel_allocated ?? '—' }}</span></td>
                        <td class="actions-column">
                            <div class="row-actions">
                                @if(auth()->user()->hasPermission('media.edit'))
                                    <button class="action-btn edit" type="button" data-allocation-edit data-campaign="{{ $campaign->id }}" data-id="{{ $allocation->id }}" data-values='@json($allocationValues)' title="Edit Allocation" aria-label="Edit Allocation">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                                    </button>
                                @endif
                                @if(auth()->user()->hasPermission('media.delete'))
                                    <form method="POST" action="{{ route('channel-allocation.allocations.destroy', [$campaign, $allocation]) }}" data-confirm="Delete this channel allocation?" data-confirm-title="Delete Allocation" data-confirm-ok="Delete">
                                        @csrf
                                        @method('DELETE')
                                        <button class="action-btn delete" type="submit" title="Delete Allocation" aria-label="Delete Allocation">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="empty-state">No allocations for this campaign.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </td>
</tr>
@empty
<tr><td colspan="7"><div class="empty-state">No Channel Allocation records found.</div></td></tr>
@endforelse
</tbody>
</table>
<div class="table-footer">
    <span>Showing {{ $campaigns->firstItem() ?? 0 }} to {{ $campaigns->lastItem() ?? 0 }} of {{ $campaigns->total() }} campaigns</span>
    <div class="footer-right">
        <span>Records per page:</span>
        <select class="per-page-select" onchange="location.href=this.value" aria-label="Records per page">
            @foreach([5,10,25,50] as $size)
                <option value="{{ request()->fullUrlWithQuery(['per_page'=>$size,'page'=>1]) }}" {{ $perPage===$size?'selected':'' }}>{{ $size }}</option>
            @endforeach
        </select>
        @include('partials.table-pager', ['paginator' => $campaigns])
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
        <form id="campaignForm" method="POST" action="{{ route('channel-allocation.store') }}">
            @csrf
            <input type="hidden" name="_method" id="campaignMethod" value="POST">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="campaign_id">Campaign</label>
                        @include('partials.campaign-combo', [
                            'inputId' => 'campaign_id',
                            'inputName' => 'campaign',
                            'menuId' => 'caCampaignMenu',
                            'campaigns' => $masterCampaigns,
                        ])
                    </div>
                    <div class="form-group"><label for="campaign_fte">FTE</label><input class="form-control" type="number" min="0" id="campaign_fte" readonly tabindex="-1"></div>
                    <div class="form-group"><label for="campaign_caller_id">Caller ID</label><input class="form-control" name="caller_id" id="campaign_caller_id"></div>
                    <div class="form-group"><label for="campaign_prefix">Prefix</label><input class="form-control" name="prefix" id="campaign_prefix"></div>
                    <div class="form-group full"><label for="campaign_remarks">Remarks</label><textarea class="form-control" name="remarks" id="campaign_remarks"></textarea></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn secondary" data-close="campaignModal">Cancel</button>
                <button class="btn primary" type="submit">Save</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="allocationModal" data-mode="add">
    <div class="modal">
        <div class="modal-header">
            <h3 id="allocationModalTitle">Add Allocation</h3>
            <button type="button" class="close-btn" data-close="allocationModal">×</button>
        </div>
        <form id="allocationForm" method="POST" action="">
            @csrf
            <input type="hidden" name="_method" id="allocationMethod" value="POST">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group" data-alloc-campaign>
                        <label for="alloc_campaign_name">Campaign</label>
                        <input class="form-control" id="alloc_campaign_name" readonly tabindex="-1">
                    </div>
                    <div class="form-group" data-alloc-channel-type>
                        <label for="alloc_channel_type">Channel Type</label>
                        <select class="form-control" name="channel_type" id="alloc_channel_type" required>
                            <option value="" selected hidden>Select Channel Type</option>
                            <option value="sip">SIP Channel</option>
                            <option value="gsm">GSM Gateway</option>
                        </select>
                    </div>
                    <div class="form-group" data-alloc-channel>
                        <label for="alloc_channel">Channel <span class="req" data-alloc-required-mark>*</span></label>
                        <select class="form-control" name="channel" id="alloc_channel" required>
                            <option value="" selected hidden>Select Channel</option>
                        </select>
                    </div>
                    <div class="form-group" data-alloc-network>
                        <label for="alloc_network">Network</label>
                        <input class="form-control" id="alloc_network" readonly tabindex="-1">
                    </div>
                    <div class="form-group" data-alloc-channel-count>
                        <label for="alloc_channel_count">Channel Count</label>
                        <input class="form-control" type="number" min="0" id="alloc_channel_count" readonly tabindex="-1">
                    </div>
                    <div class="form-group" data-alloc-line-priority>
                        <label for="alloc_line_priority">Line Priority</label>
                        <input class="form-control" type="number" min="0" name="line_priority" id="alloc_line_priority">
                    </div>
                    <div class="form-group full" data-alloc-remarks><label for="alloc_remarks">Remarks</label><textarea class="form-control" name="remarks" id="alloc_remarks"></textarea></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn secondary" data-close="allocationModal">Cancel</button>
                <button class="btn primary" type="submit">Save</button>
            </div>
        </form>
    </div>
</div>
@endif

@if(auth()->user()->hasPermission('media.create'))
<div class="modal-backdrop" id="importModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Import Data</h3>
            <button type="button" class="close-btn" data-close="importModal">×</button>
        </div>
        <div class="modal-body">
            <p class="import-lead">Upload the official Excel template to import campaigns and allocations in bulk.</p>
            <div class="import-section">
                <h4>1. Download Template</h4>
                <a class="btn primary" href="{{ route('channel-allocation.import.template') }}">
                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4v12"></path><path d="m7 11 5 5 5-5"></path><path d="M5 20h14"></path></svg>
                    Download Excel Template
                </a>
            </div>
            <div class="import-section">
                <h4>2. Upload File</h4>
                <label class="import-file-label" for="importFileInput">Choose Excel File</label>
                <div class="import-file-row">
                    <input class="form-control" type="file" id="importFileInput" accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel">
                </div>
                <p class="muted import-file-hint">Only .xlsx, .xls files are allowed.</p>
                <p class="import-upload-error" id="importUploadError" hidden></p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn secondary" data-close="importModal">Cancel</button>
            <button type="button" class="btn primary" id="importPreviewButton" disabled>Preview &amp; Validate</button>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="importPreviewModal">
    <div class="modal import-wide">
        <div class="modal-header">
            <h3>Import Data - Preview</h3>
            <button type="button" class="close-btn" data-close="importPreviewModal">×</button>
        </div>
        <div class="modal-body">
            <div class="import-stats" id="importStats"></div>
            <div class="import-banner error" id="importErrorBanner" hidden>There are errors in some rows. Please review the details below and fix them in your file.</div>
            <div class="import-banner success" id="importSuccessBanner" hidden>All rows are valid and ready to import.</div>
            <div class="table-wrap import-preview-wrap">
                <table class="import-preview-table" aria-label="Import preview">
                    <thead>
                        <tr>
                            <th>Row #</th>
                            <th>Campaign</th>
                            <th>FTE</th>
                            <th>Caller ID</th>
                            <th>Prefix</th>
                            <th>Channel</th>
                            <th>Network</th>
                            <th>Line Priority</th>
                            <th>Channel Count</th>
                            <th>Status</th>
                            <th>Error Reason</th>
                        </tr>
                    </thead>
                    <tbody id="importPreviewRows"></tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer import-preview-footer">
            <a class="btn secondary" id="importErrorReport" href="#">Download Error Report</a>
            <div class="import-preview-actions">
                <button type="button" class="btn secondary" id="importBackButton">Back to Upload</button>
                <button type="button" class="btn success" id="importConfirmButton" disabled>Confirm Import</button>
            </div>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="importSuccessModal">
    <div class="modal small">
        <div class="modal-header">
            <h3>Import Data</h3>
            <button type="button" class="close-btn" data-close="importSuccessModal" id="importSuccessDismiss">×</button>
        </div>
        <div class="modal-body import-success-body">
            <div class="import-success-icon" aria-hidden="true">✓</div>
            <p class="import-success-message" id="importSuccessMessage">Import completed successfully!</p>
            <div class="import-banner info">The new data will now appear in the Channel Allocation list.</div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn primary" id="importSuccessClose">Close</button>
        </div>
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
        const panel = document.getElementById('ca-panel-' + id);
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

    const closeCaMenus = (except) => {
        document.querySelectorAll('.ca-menu.open').forEach((menu) => {
            if (menu === except) return;
            menu.classList.remove('open');
            menu.querySelector('.ca-menu-dropdown')?.setAttribute('hidden', '');
            menu.querySelector('.ca-menu-btn')?.setAttribute('aria-expanded', 'false');
        });
    };

    document.addEventListener('click', (event) => {
        const button = event.target.closest('.ca-menu-btn');
        if (!button) return;
        event.stopPropagation();
        const menu = button.closest('.ca-menu');
        const dropdown = menu?.querySelector('.ca-menu-dropdown');
        if (!menu || !dropdown) return;
        const willOpen = !menu.classList.contains('open');
        closeCaMenus();
        if (!willOpen) return;
        menu.classList.add('open');
        dropdown.removeAttribute('hidden');
        button.setAttribute('aria-expanded', 'true');
        const rect = button.getBoundingClientRect();
        dropdown.style.position = 'fixed';
        dropdown.style.top = (rect.bottom + 4) + 'px';
        dropdown.style.right = Math.max(8, window.innerWidth - rect.right) + 'px';
        dropdown.style.left = 'auto';
    });

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element) || !event.target.closest('.ca-menu')) {
            closeCaMenus();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeCaMenus();
    });
    window.addEventListener('scroll', () => closeCaMenus(), true);

    const transferButton = document.getElementById('transferButton');
    const transferMenu = document.getElementById('transferMenu');
    const closeTransferMenu = () => {
        transferMenu?.classList.remove('open');
        transferButton?.setAttribute('aria-expanded', 'false');
    };
    transferButton?.addEventListener('click', (event) => {
        event.stopPropagation();
        const willOpen = !transferMenu?.classList.contains('open');
        closeTransferMenu();
        if (!willOpen || !transferMenu) return;
        transferMenu.classList.add('open');
        transferButton.setAttribute('aria-expanded', 'true');
    });
    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element) || !event.target.closest('.transfer')) {
            closeTransferMenu();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeTransferMenu();
    });

    const campaignModal = document.getElementById('campaignModal');
    const campaignForm = document.getElementById('campaignForm');
    const campaignMethod = document.getElementById('campaignMethod');
    const storeAction = @json(route('channel-allocation.store'));
    const updateBase = @json(url('/channel-allocation'));
    const campaignSelect = document.getElementById('campaign_id');
    const campaignFteField = document.getElementById('campaign_fte');

    function fillMasterFte(option) {
        if (!campaignFteField) return;
        if (option) {
            campaignFteField.value = option.getAttribute('data-fte') || '';
            return;
        }
        const query = String(campaignSelect?.value || '').trim().toLowerCase();
        const exact = [...document.querySelectorAll('#caCampaignMenu .pin-campaign-option')].find((item) => (item.dataset.name || '').toLowerCase() === query);
        campaignFteField.value = exact?.getAttribute('data-fte') || '';
    }

    campaignSelect?.addEventListener('campaign-combo-change', (event) => fillMasterFte(event.detail?.option || null));
    campaignSelect?.addEventListener('input', () => fillMasterFte());

    document.getElementById('campaignAddButton')?.addEventListener('click', () => {
        if (!campaignForm || !campaignMethod) return;
        campaignMethod.value = 'POST';
        campaignForm.action = storeAction;
        document.getElementById('campaignModalTitle').textContent = 'Add Campaign';
        campaignForm.reset();
        if (campaignSelect) campaignSelect.disabled = false;
        fillMasterFte();
        campaignModal?.classList.add('visible');
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-campaign-edit]');
        if (!button) return;
        closeCaMenus();
        const recordId = Number(button.dataset.id);
        if (!Number.isInteger(recordId) || recordId < 1) return;
        const values = JSON.parse(button.dataset.values || '{}');
        document.getElementById('campaignModalTitle').textContent = 'Edit Campaign';
        campaignMethod.value = 'PUT';
        campaignForm.action = updateBase + '/' + recordId;
        if (campaignSelect) {
            campaignSelect.disabled = false;
            campaignSelect.value = values.name ?? '';
        }
        fillMasterFte();
        ['caller_id','prefix','remarks'].forEach((key) => {
            const field = document.getElementById('campaign_' + key);
            if (field) field.value = values[key] ?? '';
        });
        campaignModal?.classList.add('visible');
    });

    const allocationModal = document.getElementById('allocationModal');
    const allocationForm = document.getElementById('allocationForm');
    const allocationMethod = document.getElementById('allocationMethod');
    const allocChannelSelect = document.getElementById('alloc_channel');
    const allocChannelType = document.getElementById('alloc_channel_type');
    const allocNetworkField = document.getElementById('alloc_network');
    const allocCountField = document.getElementById('alloc_channel_count');
    const allocCampaignName = document.getElementById('alloc_campaign_name');
    const sipChannelOptions = @json($sipChannelOptions);
    const gsmChannelOptions = @json($gsmChannelOptions);

    function setAllocRemarksVisible(show) {
        const remarksGroup = document.querySelector('[data-alloc-remarks]');
        if (!remarksGroup) return;
        remarksGroup.hidden = !show;
        remarksGroup.style.display = show ? '' : 'none';
        if (!show) {
            const field = document.getElementById('alloc_remarks');
            if (field) field.value = '';
        }
    }

    function clearChannelDerived() {
        if (allocNetworkField) allocNetworkField.value = '';
        if (allocCountField) allocCountField.value = '';
    }

    function fillChannelDerived(option) {
        if (allocNetworkField) allocNetworkField.value = option?.getAttribute('data-network') || '';
        if (allocCountField) allocCountField.value = option?.getAttribute('data-channel-count') || '';
    }

    function addChannelOption(select, item) {
        const option = document.createElement('option');
        option.value = item.value;
        option.textContent = item.value;
        option.setAttribute('data-network', item.network ?? '');
        option.setAttribute('data-channel-count', item.count ?? '');
        select.appendChild(option);
        return option;
    }

    function channelPlaceholder(type) {
        if (type === 'sip') return 'Select SIP Channel';
        if (type === 'gsm') return 'Select GSM Gateway';
        return 'Select Channel';
    }

    function showChannelList(type, selectedValue = '') {
        if (!allocChannelSelect) return;
        const items = type === 'sip' ? sipChannelOptions : (type === 'gsm' ? gsmChannelOptions : []);
        allocChannelSelect.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.selected = true;
        placeholder.hidden = true;
        placeholder.textContent = channelPlaceholder(type);
        allocChannelSelect.appendChild(placeholder);
        items.forEach((item) => addChannelOption(allocChannelSelect, item));
        if (selectedValue) {
            const exists = Array.from(allocChannelSelect.options).some((option) => option.value === selectedValue);
            if (!exists) {
                addChannelOption(allocChannelSelect, { value: selectedValue, network: '', count: '' });
            }
            allocChannelSelect.value = selectedValue;
            fillChannelDerived(allocChannelSelect.selectedOptions[0]);
            return;
        }
        allocChannelSelect.value = '';
        clearChannelDerived();
    }

    allocChannelType?.addEventListener('change', () => {
        showChannelList(allocChannelType.value || '');
    });
    allocChannelSelect?.addEventListener('change', () => {
        fillChannelDerived(allocChannelSelect.selectedOptions[0]);
    });
    ['alloc_network', 'alloc_channel_count', 'alloc_campaign_name'].forEach((id) => {
        document.getElementById(id)?.addEventListener('keydown', (event) => event.preventDefault());
        document.getElementById(id)?.addEventListener('paste', (event) => event.preventDefault());
    });

    document.addEventListener('click', (event) => {
        const addButton = event.target.closest('[data-allocation-add]');
        if (addButton) {
            const campaignId = Number(addButton.dataset.campaign);
            if (!Number.isInteger(campaignId) || campaignId < 1) return;
            allocationMethod.value = 'POST';
            allocationForm.action = updateBase + '/' + campaignId + '/allocations';
            document.getElementById('allocationModalTitle').textContent = 'Add Allocation';
            allocationForm.reset();
            if (allocationModal) allocationModal.dataset.mode = 'add';
            if (allocCampaignName) allocCampaignName.value = addButton.dataset.campaignName || '';
            if (allocChannelType) allocChannelType.value = '';
            showChannelList('');
            setAllocRemarksVisible(false);
            allocationModal?.classList.add('visible');
            return;
        }
        const button = event.target.closest('[data-allocation-edit]');
        if (!button) return;
        const campaignId = Number(button.dataset.campaign);
        const recordId = Number(button.dataset.id);
        if (!Number.isInteger(campaignId) || !Number.isInteger(recordId) || campaignId < 1 || recordId < 1) return;
        const values = JSON.parse(button.dataset.values || '{}');
        allocationMethod.value = 'PUT';
        allocationForm.action = updateBase + '/' + campaignId + '/allocations/' + recordId;
        document.getElementById('allocationModalTitle').textContent = 'Edit Allocation';
        if (allocationModal) allocationModal.dataset.mode = 'edit';
        setAllocRemarksVisible(false);
        const campaignName = button.closest('.ca-nested-row')?.previousElementSibling?.querySelector('.ca-campaign-link')?.textContent?.trim() || '';
        if (allocCampaignName) allocCampaignName.value = campaignName;
        const editType = values.channel_type === 'gsm' ? 'gsm' : 'sip';
        if (allocChannelType) allocChannelType.value = editType;
        showChannelList(editType, values.channel || '');
        const linePriority = document.getElementById('alloc_line_priority');
        if (linePriority) linePriority.value = values.line_priority ?? '';
        if (allocNetworkField) allocNetworkField.value = values.network || allocNetworkField.value;
        if (allocCountField) allocCountField.value = values.total_channel_allocated ?? allocCountField.value;
        allocationModal?.classList.add('visible');
    });

    const importModal = document.getElementById('importModal');
    const importPreviewModal = document.getElementById('importPreviewModal');
    const importSuccessModal = document.getElementById('importSuccessModal');
    const importFileInput = document.getElementById('importFileInput');
    const importPreviewButton = document.getElementById('importPreviewButton');
    const importConfirmButton = document.getElementById('importConfirmButton');
    const importFileStatus = document.getElementById('importFileStatus');
    const importUploadError = document.getElementById('importUploadError');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const previewUrl = @json(auth()->user()->hasPermission('media.create') ? route('channel-allocation.import.preview') : '');
    const confirmUrl = @json(auth()->user()->hasPermission('media.create') ? route('channel-allocation.import.confirm') : '');
    const errorsUrl = @json(auth()->user()->hasPermission('media.create') ? route('channel-allocation.import.errors') : '');
    let importToken = '';

    function resetImportUpload() {
        importToken = '';
        if (importFileInput) importFileInput.value = '';
        if (importFileStatus) {
            importFileStatus.textContent = 'No file chosen';
            importFileStatus.classList.remove('ready');
        }
        if (importPreviewButton) importPreviewButton.disabled = true;
        if (importUploadError) {
            importUploadError.hidden = true;
            importUploadError.textContent = '';
        }
    }

    function showImportError(message) {
        if (!importUploadError) return;
        importUploadError.hidden = !message;
        importUploadError.textContent = message || '';
    }

    async function readJsonResponse(response) {
        const text = await response.text();
        const start = text.indexOf('{');
        if (start < 0) {
            throw new Error('invalid-json');
        }
        return JSON.parse(text.slice(start));
    }

    function importFailureMessage(data, fallback) {
        return data?.message || data?.errors?.file?.[0] || fallback;
    }

    document.getElementById('importDataButton')?.addEventListener('click', () => {
        closeTransferMenu();
        if (!importModal) return;
        resetImportUpload();
        importPreviewModal?.classList.remove('visible');
        importSuccessModal?.classList.remove('visible');
        importModal.classList.add('visible');
    });

    importFileInput?.addEventListener('change', () => {
        const file = importFileInput.files && importFileInput.files[0];
        showImportError('');
        if (!file) {
            resetImportUpload();
            return;
        }
        const name = file.name || '';
        const ok = /\.(xlsx|xls)$/i.test(name);
        if (importFileStatus) {
            importFileStatus.textContent = name;
            importFileStatus.classList.toggle('ready', ok);
        }
        importPreviewButton.disabled = !ok;
        if (!ok) showImportError('Only .xlsx, .xls files are allowed.');
    });

    importPreviewButton?.addEventListener('click', async () => {
        const file = importFileInput?.files && importFileInput.files[0];
        if (!file || !previewUrl) return;
        showImportError('');
        importPreviewButton.disabled = true;
        const body = new FormData();
        body.append('file', file);
        try {
            const response = await fetch(previewUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body
            });
            const data = await readJsonResponse(response);
            if (!response.ok || !data.ok) {
                showImportError(importFailureMessage(data, 'Preview failed. Please try again.'));
                importPreviewButton.disabled = false;
                return;
            }
            importToken = data.token || '';
            renderImportPreview(data);
            importModal?.classList.remove('visible');
            importPreviewModal?.classList.add('visible');
        } catch (error) {
            showImportError('Preview failed. Please try again.');
        }
        importPreviewButton.disabled = !(importFileInput?.files && importFileInput.files[0]);
    });

    function renderImportPreview(data) {
        const summary = data.summary || {};
        const stats = document.getElementById('importStats');
        if (stats) {
            stats.innerHTML = [
                ['Total Rows', summary.total ?? 0, ''],
                ['Campaigns Detected', summary.campaigns ?? 0, ''],
                ['Allocations Detected', summary.allocations ?? 0, ''],
                ['Valid Rows', summary.valid ?? 0, 'ok'],
                ['Error Rows', summary.errors ?? 0, (summary.errors ?? 0) > 0 ? 'bad' : 'ok']
            ].map(([label, value, tone]) => `<div class="import-stat ${tone}"><span>${label}</span><strong>${value}</strong></div>`).join('');
        }
        const hasErrors = !data.valid;
        document.getElementById('importErrorBanner')?.toggleAttribute('hidden', !hasErrors);
        document.getElementById('importSuccessBanner')?.toggleAttribute('hidden', hasErrors);
        const report = document.getElementById('importErrorReport');
        if (report) {
            report.href = errorsUrl + (importToken ? ('?token=' + encodeURIComponent(importToken)) : '');
            report.style.visibility = hasErrors ? 'visible' : 'hidden';
        }
        if (importConfirmButton) {
            importConfirmButton.disabled = hasErrors;
            importConfirmButton.classList.toggle('success', !hasErrors);
        }
        const tbody = document.getElementById('importPreviewRows');
        if (!tbody) return;
        tbody.innerHTML = (data.rows || []).map((row) => {
            const err = row.valid ? '' : 'import-row-error';
            return `<tr class="${err}">
                <td>${row.row}</td>
                <td>${escapeImport(row.campaign)}</td>
                <td>${escapeImport(row.fte)}</td>
                <td>${escapeImport(row.caller_id)}</td>
                <td>${escapeImport(row.prefix)}</td>
                <td>${escapeImport(row.channel)}</td>
                <td>${escapeImport(row.network)}</td>
                <td>${escapeImport(row.line_priority)}</td>
                <td>${escapeImport(row.total_channel_allocated)}</td>
                <td class="${row.valid ? 'import-status-ok' : 'import-status-bad'}">${escapeImport(row.status)}</td>
                <td>${escapeImport(row.error)}</td>
            </tr>`;
        }).join('');
    }

    function escapeImport(value) {
        const replacements = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(value ?? '').replace(/[&<>"']/g, (char) => replacements[char] || char);
    }

    document.getElementById('importBackButton')?.addEventListener('click', () => {
        importPreviewModal?.classList.remove('visible');
        importModal?.classList.add('visible');
        if (importConfirmButton) importConfirmButton.disabled = true;
    });

    importConfirmButton?.addEventListener('click', async () => {
        if (!importToken || importConfirmButton.disabled || !confirmUrl) return;
        importConfirmButton.disabled = true;
        try {
            const response = await fetch(confirmUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ token: importToken })
            });
            const data = await readJsonResponse(response);
            if (!response.ok || !data.ok) {
                importConfirmButton.disabled = false;
                const banner = document.getElementById('importErrorBanner');
                if (banner) {
                    banner.hidden = false;
                    banner.textContent = importFailureMessage(data, 'Import failed. Please try again.');
                }
                return;
            }
            importPreviewModal?.classList.remove('visible');
            const message = document.getElementById('importSuccessMessage');
            if (message) {
                message.textContent = 'Import completed successfully! ' + (data.allocations ?? 0) + ' allocations imported.';
            }
            importSuccessModal?.classList.add('visible');
        } catch (error) {
            importConfirmButton.disabled = false;
        }
    });

    function finishImportSuccess() {
        window.location.reload();
    }
    document.getElementById('importSuccessClose')?.addEventListener('click', finishImportSuccess);
    document.getElementById('importSuccessDismiss')?.addEventListener('click', finishImportSuccess);

    const restoreExpanded = @json(session('ca_expanded'));
    if (restoreExpanded) {
        document.querySelector('[data-ca-toggle="' + restoreExpanded + '"]')?.click();
    }
});
</script>
@endpush
