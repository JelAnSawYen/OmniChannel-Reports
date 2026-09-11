@extends('layouts.app')
@section('content')
<div class="page-head">
    <div>
        <h1 class="page-title">PDC Servers</h1>
        <p class="page-subtitle">Manage PDC server inventory, addressing, and operational status.</p>
    </div>
    <div class="toolbar">
        <form class="search-box media-search-form" id="pdcSearchForm" method="GET" action="{{ route('pdc-servers') }}">
            <span class="search-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
            </span>
            <input id="pdcSearchInput" name="search" value="{{ $search }}" placeholder="Search PDC Servers" aria-label="Search PDC Servers" autocomplete="off">
            @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
        </form>
        <button class="btn primary" type="submit" form="pdcSearchForm">Search</button>
        @if(auth()->user()->hasPermission('media.export'))
        @include('partials.data-transfer', [
            'canExport' => true,
            'canImport' => auth()->user()->hasPermission('media.create'),
            'exportUrl' => route('pdc-servers.export', request()->query()),
            'templateUrl' => auth()->user()->hasPermission('media.create') ? route('pdc-servers.import.template') : '',
            'previewUrl' => auth()->user()->hasPermission('media.create') ? route('pdc-servers.import.preview') : '',
            'confirmUrl' => auth()->user()->hasPermission('media.create') ? route('pdc-servers.import.confirm') : '',
            'errorsUrl' => auth()->user()->hasPermission('media.create') ? route('pdc-servers.import.errors') : '',
            'previewHeaders' => array_values(app(\App\Services\PdcServerImportService::class)->fields()),
            'entityTitle' => 'PDC Servers',
        ])
        @endif
        @if(auth()->user()->hasPermission('media.create'))
            <button class="plus-btn" type="button" id="pdcGroupAddButton" aria-label="Add" title="Add">+</button>
        @endif
    </div>
</div>

<div class="table-card table-wrap">
<table class="ca-table pdc-table" aria-label="PDC Servers">
<thead>
<tr>
    <th>
        <span class="ca-campaign-cell">
            <span class="ca-toggle" aria-hidden="true"></span>
            <span class="ca-campaign-identity">Campaign</span>
        </span>
    </th>
    <th>Location</th>
    <th>Date Endorse</th>
    <th>DNS</th>
    <th class="actions-column">Actions</th>
</tr>
</thead>
<tbody>
@forelse($groups as $group)
@php
    $pdcServerDisplayId = 0;
    $serverCount = $group->servers->count();
    $campaignName = $group->campaign?->name ?: '—';
    $dateDisplay = $group->date_endorse ? \App\Support\PdcEndorseDate::display($group->date_endorse->format('Y-m-d')) : '—';
    $dnsLines = preg_split('/\s*[,;\r\n]+\s*/', trim((string) $group->dns)) ?: [];
    $dnsLines = array_values(array_filter($dnsLines, fn ($line) => $line !== ''));
    $groupValues = [
        'campaign_id' => $group->campaign_id,
        'location' => $group->location,
        'date_endorse' => $group->date_endorse ? \App\Support\PdcEndorseDate::display($group->date_endorse->format('Y-m-d')) : '',
        'dns' => $group->dns,
    ];
@endphp
<tr class="ca-campaign-row" data-campaign="{{ $group->id }}">
    <td>
        <span class="ca-campaign-cell">
            <button type="button" class="ca-toggle" data-ca-toggle="{{ $group->id }}" aria-expanded="false" aria-controls="pdc-panel-{{ $group->id }}" title="Expand {{ $campaignName }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 6 6 6-6 6"></path></svg>
            </button>
            <span class="ca-campaign-identity">
                <button type="button" class="ca-campaign-link" data-ca-toggle="{{ $group->id }}">{{ $campaignName }}</button>
                <span class="ca-count">{{ $serverCount }} {{ $serverCount === 1 ? 'server' : 'servers' }}</span>
            </span>
        </span>
    </td>
    <td><span class="pdc-cell-group">{{ $group->location ?: '—' }}</span></td>
    <td><span class="pdc-cell-group">{{ $dateDisplay }}</span></td>
    <td>
        @if($dnsLines === [])
            <span class="pdc-cell-group">—</span>
        @else
            <span class="pdc-cell-group pdc-dns">
                @foreach($dnsLines as $line)
                    <span>{{ $line }}</span>
                @endforeach
            </span>
        @endif
    </td>
    <td class="actions-column">
        @if(auth()->user()->hasPermission('media.edit') || auth()->user()->hasPermission('media.delete'))
        <span class="pdc-cell-group">
        <div class="ca-menu">
            <button class="ca-menu-btn" type="button" aria-haspopup="true" aria-expanded="false" aria-label="Campaign actions" title="Campaign actions">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
            </button>
            <div class="ca-menu-dropdown" role="menu" hidden>
                @if(auth()->user()->hasPermission('media.edit'))
                    <button class="ca-menu-item edit" type="button" role="menuitem" data-pdc-group-edit data-id="{{ $group->id }}" data-values='@json($groupValues)'>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                        Edit
                    </button>
                @endif
                @if(auth()->user()->hasPermission('media.delete'))
                    <form method="POST" action="{{ route('pdc-servers.destroy', $group) }}" data-confirm="Delete this campaign group and all of its servers?" data-confirm-title="Delete" data-confirm-ok="Delete">
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
        </span>
        @endif
    </td>
</tr>
<tr class="ca-nested-row" id="pdc-panel-{{ $group->id }}" hidden>
    <td colspan="5">
        <div class="ca-nested">
            <table class="pdc-servers-nested" aria-label="{{ $campaignName }} servers">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Hostname</th>
                        <th>Source IP</th>
                        <th>OS</th>
                        <th>RAM</th>
                        <th>CPU</th>
                        <th>Storage</th>
                        <th>Admin Username</th>
                        <th>Password</th>
                        <th>SQL DB Password</th>
                        <th class="actions-column">
                            <span class="ca-actions-head">
                                Actions
                                @if(auth()->user()->hasPermission('media.create'))
                                    <button class="plus-btn" type="button" data-pdc-server-add data-group="{{ $group->id }}" title="Add" aria-label="Add">+</button>
                                @endif
                            </span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                @forelse($group->servers as $server)
                    @php
                        $serverValues = [
                            'hostname' => $server->hostname,
                            'ip_address' => $server->ip_address,
                            'os' => $server->os,
                            'ram' => $server->ram,
                            'cpu' => $server->cpu,
                            'storage' => $server->storage,
                            'admin_username' => $server->admin_username,
                        ];
                        if ($canRevealSecrets) {
                            $serverValues['password'] = $server->password;
                            $serverValues['sql_db_password'] = $server->sql_db_password;
                        }
                    @endphp
                    <tr>
                        @php $pdcServerDisplayId++; @endphp
                        <td><span class="pdc-cell-group pdc-server-id">{{ $pdcServerDisplayId }}</span></td>
                        <td><span class="pdc-cell-group">{{ $server->hostname }}</span></td>
                        <td><span class="pdc-cell-group">{{ $server->ip_address }}</span></td>
                        <td><span class="pdc-cell-group">{{ $server->os ?: '—' }}</span></td>
                        <td><span class="pdc-cell-group">{{ $server->ram ?: '—' }}</span></td>
                        <td><span class="pdc-cell-group">{{ $server->cpu ?: '—' }}</span></td>
                        <td><span class="pdc-cell-group">{{ $server->storage ?: '—' }}</span></td>
                        <td><span class="pdc-cell-group">{{ $server->admin_username ?: '—' }}</span></td>
                        <td>
                            <span class="pdc-cell-group pdc-secret">
                                <span class="pdc-secret-mask">••••••••</span>
                                @if($canRevealSecrets && $server->password)
                                    <span class="pdc-secret-value" hidden>{{ $server->password }}</span>
                                    <button type="button" class="pdc-secret-toggle" title="Show password" aria-label="Show password">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                @endif
                            </span>
                        </td>
                        <td>
                            <span class="pdc-cell-group pdc-secret">
                                <span class="pdc-secret-mask">••••••••</span>
                                @if($canRevealSecrets && $server->sql_db_password)
                                    <span class="pdc-secret-value" hidden>{{ $server->sql_db_password }}</span>
                                    <button type="button" class="pdc-secret-toggle" title="Show password" aria-label="Show password">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                @endif
                            </span>
                        </td>
                        <td class="actions-column">
                            <span class="pdc-cell-group row-actions">
                                @if(auth()->user()->hasPermission('media.edit'))
                                    <button class="action-btn edit" type="button" data-pdc-server-edit data-group="{{ $group->id }}" data-id="{{ $server->id }}" data-values='@json($serverValues)' title="Edit" aria-label="Edit">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                                    </button>
                                @endif
                                @if(auth()->user()->hasPermission('media.delete'))
                                    <form method="POST" action="{{ route('pdc-servers.servers.destroy', [$group, $server]) }}" data-confirm="Delete this record?" data-confirm-title="Delete Record" data-confirm-ok="Delete">
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
                    <tr><td colspan="11"><div class="empty-state">No servers for this campaign.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </td>
</tr>
@empty
<tr><td colspan="5"><div class="empty-state">No PDC Servers records found.</div></td></tr>
@endforelse
</tbody>
</table>
<div class="table-footer">
    <span>Showing {{ $groups->firstItem() ?? 0 }} to {{ $groups->lastItem() ?? 0 }} of {{ $groups->total() }} campaigns</span>
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
@if(auth()->user()->hasPermission('media.create') || auth()->user()->hasPermission('media.edit'))
<div class="modal-backdrop" id="pdcGroupModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="pdcGroupModalTitle">Add PDC Servers</h3>
            <button type="button" class="close-btn" data-close="pdcGroupModal">×</button>
        </div>
        <form id="pdcGroupForm" method="POST" action="{{ route('pdc-servers.store') }}">
            @csrf
            <input type="hidden" name="_method" id="pdcGroupMethod" value="POST">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="pdc_campaign_id">Campaign</label>
                        <select class="form-control" name="campaign_id" id="pdc_campaign_id" required>
                            <option value="">Select Campaign</option>
                            @foreach($campaigns as $campaign)
                                <option value="{{ $campaign->id }}">{{ $campaign->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="pdc_location">Location</label>
                        <select class="form-control" name="location" id="pdc_location">
                            <option value="">Select Location</option>
                            @foreach($locations as $name)
                                <option value="{{ $name }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="pdc_date_endorse">Date Endorse</label>
                        <div class="pdc-date-field">
                            <input class="form-control" type="text" name="date_endorse" id="pdc_date_endorse" placeholder="M/D/YYYY" autocomplete="off">
                            <span class="pdc-date-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/></svg>
                            </span>
                            <input type="date" id="pdc_date_picker" class="pdc-date-native" min="{{ \App\Support\PdcEndorseDate::MIN_DATE }}" tabindex="-1" aria-label="Choose date">
                            <div class="pdc-cal" id="pdcCal" hidden>
                                <div class="pdc-cal-head">
                                    <button type="button" class="pdc-cal-nav" data-pdc-cal-prev aria-label="Previous">‹</button>
                                    <div class="pdc-cal-caption">
                                        <button type="button" class="pdc-cal-month" data-pdc-cal-month></button>
                                        <button type="button" class="pdc-cal-year" data-pdc-cal-year></button>
                                    </div>
                                    <button type="button" class="pdc-cal-nav" data-pdc-cal-next aria-label="Next">›</button>
                                </div>
                                <div class="pdc-cal-body" data-pdc-cal-body></div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group full">
                        <label for="pdc_dns">DNS</label>
                        <textarea class="form-control" name="dns" id="pdc_dns" rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn secondary" data-close="pdcGroupModal">Cancel</button>
                <button class="btn primary" type="submit" id="pdcGroupSubmit">Save</button>
            </div>
        </form>
    </div>
</div>
@endif

@if(auth()->user()->hasPermission('media.create') || auth()->user()->hasPermission('media.edit') || auth()->user()->hasPermission('media.delete'))
<div class="modal-backdrop" id="pdcServerModal" data-mode="add">
    <div class="modal">
        <div class="modal-header">
            <h3 id="pdcServerModalTitle">Server Information</h3>
            <button type="button" class="close-btn" data-close="pdcServerModal">×</button>
        </div>
        <form id="pdcServerForm" method="POST" action="">
            @csrf
            <input type="hidden" name="_method" id="pdcServerMethod" value="POST">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group"><label for="pdc_hostname">Hostname</label><input class="form-control" name="hostname" id="pdc_hostname" required></div>
                    <div class="form-group"><label for="pdc_ip_address">Source IP</label><input class="form-control" name="ip_address" id="pdc_ip_address" required placeholder="e.g. 10.24.28.57"></div>
                    <div class="form-group"><label for="pdc_os">OS</label><input class="form-control" name="os" id="pdc_os" placeholder="e.g. cpe:/o:opensuse:leap:15.3"></div>
                    <div class="form-group"><label for="pdc_ram">RAM</label><input class="form-control" name="ram" id="pdc_ram" placeholder="e.g. 12GB"></div>
                    <div class="form-group"><label for="pdc_cpu">CPU</label><input class="form-control" name="cpu" id="pdc_cpu" placeholder="e.g. 8cores"></div>
                    <div class="form-group"><label for="pdc_storage">Storage</label><input class="form-control" name="storage" id="pdc_storage" placeholder="e.g. 120GB"></div>
                    <div class="form-group"><label for="pdc_admin_username">Admin Username</label><input class="form-control" name="admin_username" id="pdc_admin_username"></div>
                    <div class="form-group">
                        <label for="pdc_password">Password</label>
                        <div class="pdc-password-field">
                            <input class="form-control" type="password" name="password" id="pdc_password" autocomplete="new-password">
                            @if($canRevealSecrets)
                                <button type="button" class="pdc-secret-toggle" data-toggle-input="pdc_password" title="Show password" aria-label="Show password">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="pdc_sql_db_password">SQL DB Password</label>
                        <div class="pdc-password-field">
                            <input class="form-control" type="password" name="sql_db_password" id="pdc_sql_db_password" autocomplete="new-password">
                            @if($canRevealSecrets)
                                <button type="button" class="pdc-secret-toggle" data-toggle-input="pdc_sql_db_password" title="Show password" aria-label="Show password">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            @endif
                        </div>
                    </div>
                    <p class="muted pdc-password-note">Password fields allow special characters.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn secondary" data-close="pdcServerModal">Cancel</button>
                <button class="btn primary" type="submit" id="pdcServerSubmit">Save</button>
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
        const panel = document.getElementById('pdc-panel-' + id);
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
        if (!(event.target instanceof Element) || !event.target.closest('.ca-menu')) closeCaMenus();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeCaMenus();
    });
    window.addEventListener('scroll', () => closeCaMenus(), true);

    const dateText = document.getElementById('pdc_date_endorse');
    const datePicker = document.getElementById('pdc_date_picker');
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
        if (year < 2000 || month < 1 || month > 12 || day < 1 || day > 31) return '';
        return year + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');
    };
    datePicker?.addEventListener('change', () => {
        if (datePicker.value) dateText.value = toDisplay(datePicker.value);
    });
    dateText?.addEventListener('blur', () => {
        const iso = toIso(dateText.value);
        if (iso) datePicker.value = iso;
    });
    dateText?.addEventListener('input', () => dateText.setCustomValidity(''));

    const cal = document.getElementById('pdcCal');
    const calBody = cal?.querySelector('[data-pdc-cal-body]');
    const calMonthBtn = cal?.querySelector('[data-pdc-cal-month]');
    const calYearBtn = cal?.querySelector('[data-pdc-cal-year]');
    const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const monthShort = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const minYear = 2000;
    const maxYear = new Date().getFullYear();
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

    const closeCal = () => cal?.setAttribute('hidden', '');
    const setPicked = (year, month, day) => {
        const iso = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
        if (datePicker) datePicker.value = iso;
        if (dateText) {
            dateText.value = toDisplay(iso);
            dateText.setCustomValidity('');
        }
        closeCal();
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
                btn.addEventListener('click', () => {
                    calYear = year;
                    calView = 'months';
                    renderCal();
                });
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
                btn.addEventListener('click', () => {
                    calMonth = month;
                    calView = 'days';
                    renderCal();
                });
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
        renderCal();
        cal?.removeAttribute('hidden');
    };

    ['pointerdown', 'mousedown', 'click'].forEach((evt) => {
        datePicker?.addEventListener(evt, (event) => {
            event.preventDefault();
            event.stopPropagation();
            if (evt === 'click') {
                if (cal && !cal.hasAttribute('hidden')) closeCal();
                else openCal();
            }
        });
    });
    cal?.addEventListener('click', (event) => event.stopPropagation());
    calMonthBtn?.addEventListener('click', () => {
        calView = 'months';
        renderCal();
    });
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
    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) return;
        if (event.target.closest('#pdcCal') || event.target.closest('#pdc_date_picker') || event.target.closest('.pdc-date-field')) return;
        closeCal();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeCal();
    });

    const groupModal = document.getElementById('pdcGroupModal');
    const groupForm = document.getElementById('pdcGroupForm');
    const groupMethod = document.getElementById('pdcGroupMethod');
    const groupStore = @json(route('pdc-servers.store'));
    const groupBase = @json(url('/pdc-servers'));

    function resetGroupAdd() {
        if (!groupForm || !groupMethod) return;
        groupMethod.value = 'POST';
        groupForm.action = groupStore;
        document.getElementById('pdcGroupModalTitle').textContent = 'Add PDC Servers';
        document.getElementById('pdcGroupSubmit').textContent = 'Save';
        groupForm.reset();
        if (datePicker) datePicker.value = '';
    }

    document.getElementById('pdcGroupAddButton')?.addEventListener('click', () => {
        resetGroupAdd();
        closeCal();
        groupModal?.classList.add('visible');
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-pdc-group-edit]');
        if (!button) return;
        const values = JSON.parse(button.dataset.values || '{}');
        groupMethod.value = 'PUT';
        groupForm.action = groupBase + '/' + button.dataset.id;
        document.getElementById('pdcGroupModalTitle').textContent = 'Edit PDC Servers';
        document.getElementById('pdcGroupSubmit').textContent = 'Save';
        document.getElementById('pdc_campaign_id').value = values.campaign_id ?? '';
        document.getElementById('pdc_location').value = values.location ?? '';
        document.getElementById('pdc_date_endorse').value = values.date_endorse ?? '';
        document.getElementById('pdc_dns').value = values.dns ?? '';
        if (datePicker) datePicker.value = toIso(values.date_endorse || '');
        groupModal?.classList.add('visible');
    });

    groupForm?.addEventListener('submit', (event) => {
        const raw = String(dateText?.value || '').trim();
        if (!dateText || raw === '') {
            dateText?.setCustomValidity('');
            return;
        }
        const iso = toIso(raw);
        if (!iso) {
            event.preventDefault();
            dateText.setCustomValidity('Date Endorse must be a valid date on or after 1/1/2000.');
            dateText.reportValidity();
            return;
        }
        dateText.setCustomValidity('');
    });

    const serverModal = document.getElementById('pdcServerModal');
    const serverForm = document.getElementById('pdcServerForm');
    const serverMethod = document.getElementById('pdcServerMethod');
    const serverSubmit = document.getElementById('pdcServerSubmit');
    const canReveal = @json($canRevealSecrets);

    function serverStoreAction(groupId) {
        return groupBase + '/' + groupId + '/servers';
    }

    function resetServerAdd(groupId) {
        if (!serverForm || !serverMethod) return;
        serverMethod.value = 'POST';
        serverForm.action = serverStoreAction(groupId);
        serverSubmit.textContent = 'Save';
        serverSubmit.hidden = false;
        serverModal?.setAttribute('data-mode', 'add');
        serverForm.reset();
        ['pdc_password', 'pdc_sql_db_password'].forEach((id) => {
            const input = document.getElementById(id);
            if (input) input.type = 'password';
        });
    }

    document.addEventListener('click', (event) => {
        const addButton = event.target.closest('[data-pdc-server-add]');
        if (addButton) {
            resetServerAdd(addButton.dataset.group);
            serverModal?.classList.add('visible');
            return;
        }
        const button = event.target.closest('[data-pdc-server-edit]');
        if (button) {
            const values = JSON.parse(button.dataset.values || '{}');
            const groupId = button.dataset.group;
            const serverId = button.dataset.id;
            serverMethod.value = 'PUT';
            serverForm.action = serverStoreAction(groupId) + '/' + serverId;
            serverSubmit.textContent = 'Save';
            serverModal?.setAttribute('data-mode', 'edit');
            ['hostname', 'ip_address', 'os', 'ram', 'cpu', 'storage', 'admin_username'].forEach((key) => {
                const field = document.getElementById('pdc_' + key);
                if (field) field.value = values[key] ?? '';
            });
            const password = document.getElementById('pdc_password');
            const sql = document.getElementById('pdc_sql_db_password');
            if (password) password.value = canReveal ? (values.password ?? '') : '';
            if (sql) sql.value = canReveal ? (values.sql_db_password ?? '') : '';
            serverModal?.classList.add('visible');
            return;
        }
        const toggle = event.target.closest('.pdc-secret-toggle');
        if (!toggle) return;
        const inputId = toggle.getAttribute('data-toggle-input');
        if (inputId) {
            const input = document.getElementById(inputId);
            if (!input || !canReveal) return;
            input.type = input.type === 'password' ? 'text' : 'password';
            return;
        }
        if (!canReveal) return;
        const wrap = toggle.closest('.pdc-secret');
        const mask = wrap?.querySelector('.pdc-secret-mask');
        const value = wrap?.querySelector('.pdc-secret-value');
        if (!mask || !value) return;
        const showing = !value.hasAttribute('hidden');
        value.toggleAttribute('hidden', showing);
        mask.toggleAttribute('hidden', !showing);
    });
});
</script>
@include('partials.inventory-import-script', [
    'previewUrl' => auth()->user()->hasPermission('media.create') ? route('pdc-servers.import.preview') : '',
    'confirmUrl' => auth()->user()->hasPermission('media.create') ? route('pdc-servers.import.confirm') : '',
    'errorsUrl' => auth()->user()->hasPermission('media.create') ? route('pdc-servers.import.errors') : '',
    'previewFields' => array_keys(app(\App\Services\PdcServerImportService::class)->fields()),
])
@endpush
