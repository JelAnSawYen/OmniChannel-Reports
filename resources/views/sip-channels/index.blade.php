@extends('layouts.app')
@section('content')
<div class="page-head">
    <div>
        <h1 class="page-title">SIP Channels</h1>
        <p class="page-subtitle">Manage SIP channel campaigns, ETPI names, ranges, and activation dates.</p>
    </div>
    <div class="toolbar">
        <form class="search-box media-search-form" id="sipSearchForm" method="GET" action="{{ route('sip-channels') }}">
            <span class="search-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
            </span>
            <input id="sipSearchInput" name="search" value="{{ $search }}" placeholder="Search SIP Channels" aria-label="Search SIP Channels" autocomplete="off">
            @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
        </form>
        <button class="btn primary" type="submit" form="sipSearchForm">Search</button>
        @if(auth()->user()->hasPermission('media.export'))
        @include('partials.data-transfer', [
            'canExport' => true,
            'canImport' => auth()->user()->hasPermission('media.create'),
            'exportUrl' => route('sip-channels.export', request()->query()),
            'templateUrl' => auth()->user()->hasPermission('media.create') ? route('sip-channels.import.template') : '',
            'previewUrl' => auth()->user()->hasPermission('media.create') ? route('sip-channels.import.preview') : '',
            'confirmUrl' => auth()->user()->hasPermission('media.create') ? route('sip-channels.import.confirm') : '',
            'errorsUrl' => auth()->user()->hasPermission('media.create') ? route('sip-channels.import.errors') : '',
            'previewHeaders' => array_values(app(\App\Services\SipChannelImportService::class)->fields()),
            'entityTitle' => 'SIP Channels',
        ])
        @endif
        @if(auth()->user()->hasPermission('media.create'))
            <button class="plus-btn" type="button" id="sipAddButton" aria-label="Add" title="Add">+</button>
        @endif
    </div>
</div>

<div class="table-card table-wrap">
<table class="sip-table" aria-label="SIP Channels">
<thead>
<tr>
    <th>Campaign</th>
    <th>ETPI SIP NAME</th>
    <th>Pilot Number</th>
    <th>Channel Count</th>
    <th>Channel Range</th>
    <th>Network</th>
    <th>Date Activation</th>
    <th class="actions-column">Actions</th>
</tr>
</thead>
<tbody>
@forelse($records as $record)
@php
    $campaignName = $record->campaign?->name ?: '—';
    $dateDisplay = $record->date_activation ? \App\Support\PdcEndorseDate::display($record->date_activation->format('Y-m-d')) : '—';
    $editValues = [
        'campaign_id' => $record->campaign_id,
        'etpi_sip_name' => $record->etpi_sip_name,
        'pilot_number' => $record->pilot_number,
        'channel_count' => $record->channel_count,
        'channel_range' => $record->channel_range,
        'network' => $record->network,
        'date_activation' => $record->date_activation ? \App\Support\PdcEndorseDate::display($record->date_activation->format('Y-m-d')) : '',
    ];
@endphp
<tr>
    <td><span class="sip-cell sip-campaign">{{ $campaignName }}</span></td>
    <td><span class="sip-cell">{{ $record->etpi_sip_name ?: '—' }}</span></td>
    <td><span class="sip-cell">{{ $record->pilot_number ?: '—' }}</span></td>
    <td><span class="sip-cell">{{ $record->channel_count !== null ? $record->channel_count : '—' }}</span></td>
    <td><span class="sip-cell sip-range">{{ $record->channel_range ?: '—' }}</span></td>
    <td><span class="sip-cell">{{ $record->network ?: '—' }}</span></td>
    <td><span class="sip-cell">{{ $dateDisplay }}</span></td>
    <td class="actions-column">
        <div class="row-actions">
            @if(auth()->user()->hasPermission('media.edit'))
                <button class="action-btn edit" type="button" data-sip-edit data-id="{{ $record->id }}" data-values='@json($editValues)' title="Edit" aria-label="Edit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                </button>
            @endif
            @if(auth()->user()->hasPermission('media.delete'))
                <form method="POST" action="{{ route('sip-channels.destroy', $record) }}" data-confirm="Delete this record?" data-confirm-title="Delete Record" data-confirm-ok="Delete">
                    @csrf
                    @method('DELETE')
                    <button class="action-btn delete" type="submit" title="Delete" aria-label="Delete">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                    </button>
                </form>
            @endif
        </div>
    </td>
</tr>
@empty
<tr><td colspan="8"><div class="empty-state">No SIP Channels records found.</div></td></tr>
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
<div class="modal-backdrop" id="sipModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="sipModalTitle">Add SIP Channels</h3>
            <button type="button" class="close-btn" data-close="sipModal">×</button>
        </div>
        <form id="sipForm" method="POST" action="{{ route('sip-channels.store') }}">
            @csrf
            <input type="hidden" name="_method" id="sipMethod" value="POST">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="sip_campaign_id">Campaign</label>
                        <select class="form-control" name="campaign_id" id="sip_campaign_id" required>
                            <option value="">Select Campaign</option>
                            @foreach($campaigns as $campaign)
                                <option value="{{ $campaign->id }}">{{ $campaign->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="sip_etpi_sip_name">ETPI SIP NAME</label>
                        <input class="form-control" name="etpi_sip_name" id="sip_etpi_sip_name" required>
                    </div>
                    <div class="form-group">
                        <label for="sip_pilot_number">Pilot Number</label>
                        <input class="form-control" name="pilot_number" id="sip_pilot_number">
                    </div>
                    <div class="form-group">
                        <label for="sip_channel_count">Channel Count</label>
                        <input class="form-control" type="number" min="0" step="1" name="channel_count" id="sip_channel_count">
                    </div>
                    <div class="form-group">
                        <label for="sip_channel_range">Channel Range</label>
                        <input class="form-control" name="channel_range" id="sip_channel_range">
                    </div>
                    <div class="form-group">
                        <label for="sip_network">Network</label>
                        <input class="form-control" name="network" id="sip_network">
                    </div>
                    <div class="form-group">
                        <label for="sip_date_activation">Date Activation</label>
                        <div class="pdc-date-field">
                            <input class="form-control" type="text" name="date_activation" id="sip_date_activation" placeholder="M/D/YYYY" autocomplete="off">
                            <span class="pdc-date-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/></svg>
                            </span>
                            <input type="date" id="sip_date_picker" class="pdc-date-native" min="{{ \App\Support\PdcEndorseDate::MIN_DATE }}" tabindex="-1" aria-label="Choose date">
                            <div class="pdc-cal" id="sipCal" hidden>
                                <div class="pdc-cal-head">
                                    <button type="button" class="pdc-cal-nav" data-sip-cal-prev aria-label="Previous">‹</button>
                                    <div class="pdc-cal-caption">
                                        <button type="button" class="pdc-cal-month" data-sip-cal-month></button>
                                        <button type="button" class="pdc-cal-year" data-sip-cal-year></button>
                                    </div>
                                    <button type="button" class="pdc-cal-nav" data-sip-cal-next aria-label="Next">›</button>
                                </div>
                                <div class="pdc-cal-body" data-sip-cal-body></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn secondary" data-close="sipModal">Cancel</button>
                <button class="btn primary" type="submit" id="sipSubmit">Save</button>
            </div>
        </form>
    </div>
</div>
@endif

<script>
document.addEventListener('DOMContentLoaded', () => {
    const dateText = document.getElementById('sip_date_activation');
    const datePicker = document.getElementById('sip_date_picker');
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

    const cal = document.getElementById('sipCal');
    const calBody = cal?.querySelector('[data-sip-cal-body]');
    const calMonthBtn = cal?.querySelector('[data-sip-cal-month]');
    const calYearBtn = cal?.querySelector('[data-sip-cal-year]');
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
    cal?.querySelector('[data-sip-cal-prev]')?.addEventListener('click', () => {
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
    cal?.querySelector('[data-sip-cal-next]')?.addEventListener('click', () => {
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
        if (event.target.closest('#sipCal') || event.target.closest('#sip_date_picker') || event.target.closest('.pdc-date-field')) return;
        closeCal();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeCal();
    });

    const modal = document.getElementById('sipModal');
    const form = document.getElementById('sipForm');
    const method = document.getElementById('sipMethod');
    const storeAction = @json(route('sip-channels.store'));
    const baseUrl = @json(url('/sip-channels'));

    function resetAdd() {
        if (!form || !method) return;
        method.value = 'POST';
        form.action = storeAction;
        document.getElementById('sipModalTitle').textContent = 'Add SIP Channels';
        document.getElementById('sipSubmit').textContent = 'Save';
        form.reset();
        if (datePicker) datePicker.value = '';
        closeCal();
    }

    document.getElementById('sipAddButton')?.addEventListener('click', () => {
        resetAdd();
        modal?.classList.add('visible');
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-sip-edit]');
        if (!button) return;
        const recordId = Number(button.dataset.id);
        if (!Number.isInteger(recordId) || recordId < 1) return;
        const values = JSON.parse(button.dataset.values || '{}');
        document.getElementById('sipModalTitle').textContent = 'Edit SIP Channels';
        document.getElementById('sipSubmit').textContent = 'Save';
        method.value = 'PUT';
        form.action = baseUrl + '/' + recordId;
        ['campaign_id', 'etpi_sip_name', 'pilot_number', 'channel_count', 'channel_range', 'network', 'date_activation'].forEach((key) => {
            const field = document.getElementById('sip_' + key);
            if (field) field.value = values[key] ?? '';
        });
        if (datePicker) datePicker.value = toIso(values.date_activation || '');
        modal?.classList.add('visible');
    });

    form?.addEventListener('submit', (event) => {
        const raw = String(dateText?.value || '').trim();
        if (!dateText || raw === '') {
            dateText?.setCustomValidity('');
            return;
        }
        const iso = toIso(raw);
        if (!iso) {
            event.preventDefault();
            dateText.setCustomValidity('Date Activation must be a valid date on or after 1/1/2000.');
            dateText.reportValidity();
            return;
        }
        dateText.setCustomValidity('');
    });

    const restoreSipEdit = @json(session('sip_edit'));
    if (restoreSipEdit) {
        document.querySelector('[data-sip-edit][data-id="' + restoreSipEdit + '"]')?.click();
    }
});
</script>
@include('partials.inventory-import-script', [
    'previewUrl' => auth()->user()->hasPermission('media.create') ? route('sip-channels.import.preview') : '',
    'confirmUrl' => auth()->user()->hasPermission('media.create') ? route('sip-channels.import.confirm') : '',
    'errorsUrl' => auth()->user()->hasPermission('media.create') ? route('sip-channels.import.errors') : '',
    'previewFields' => array_keys(app(\App\Services\SipChannelImportService::class)->fields()),
])
@endpush
