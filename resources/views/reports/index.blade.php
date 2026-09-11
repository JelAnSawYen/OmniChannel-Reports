@extends('layouts.app')
@php
    $query = array_merge(['tab' => $tab], request()->except(['page']));
    $exportQuery = array_filter($query, fn ($value) => $value !== null && $value !== '');
    $canExport = auth()->user()->hasPermission('media.export');
    $icons = [
        'stack' => '<rect x="4" y="14" width="16" height="5" rx="1.2"/><rect x="4" y="8.5" width="16" height="5" rx="1.2"/><rect x="4" y="3" width="16" height="5" rx="1.2"/>',
        'sip' => '<path d="M20 16.9v2.3a1.7 1.7 0 0 1-1.9 1.7 16.8 16.8 0 0 1-7.3-2.6 16.5 16.5 0 0 1-5.1-5.1A16.8 16.8 0 0 1 3.1 5.9 1.7 1.7 0 0 1 4.8 4h2.3a1.7 1.7 0 0 1 1.7 1.5c.1.8.3 1.6.6 2.3a1.7 1.7 0 0 1-.4 1.8l-1 1a13.5 13.5 0 0 0 5.1 5.1l1-1a1.7 1.7 0 0 1 1.8-.4c.7.3 1.5.5 2.3.6A1.7 1.7 0 0 1 20 16.9z"/>',
        'gsm' => '<path d="M5 18V8"/><path d="M10 18V5"/><path d="M15 18v-8"/><path d="M20 18V3"/>',
    ];
@endphp
@section('content')
<div class="page-head rpt-head">
    <div>
        <h1 class="page-title">Reports</h1>
        <p class="page-subtitle">Generate and export operational reports from the system.</p>
    </div>
</div>

<div class="rpt-tabs" role="tablist" aria-label="Report types">
    @foreach($tabs as $key => $label)
        <a class="rpt-tab {{ $tab === $key ? 'active' : '' }}" href="{{ route('reports', ['tab' => $key]) }}" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}">{{ $label }}</a>
    @endforeach
</div>

<section class="rpt-filters" aria-label="Filters">
    <h2>Filters</h2>
    <form method="GET" action="{{ route('reports') }}" class="rpt-filter-form">
        <input type="hidden" name="tab" value="{{ $tab }}">
        @if(in_array('date_range', $visible_filters, true))
            <label class="rpt-field">
                <span>Date Range</span>
                <span class="rpt-dates">
                    <input class="form-control" type="date" name="date_from" value="{{ $filters['date_from'] }}" min="{{ \App\Support\PdcEndorseDate::MIN_DATE }}" aria-label="From date">
                    <span class="rpt-dates-sep">–</span>
                    <input class="form-control" type="date" name="date_to" value="{{ $filters['date_to'] }}" min="{{ \App\Support\PdcEndorseDate::MIN_DATE }}" aria-label="To date">
                </span>
            </label>
        @endif
        @if(in_array('campaign', $visible_filters, true))
            <label class="rpt-field">
                <span>Campaign</span>
                <select class="form-control" name="campaign_id">
                    <option value="">All Campaigns</option>
                    @foreach($filter_options['campaigns'] as $campaign)
                        <option value="{{ $campaign->id }}" {{ (int) $filters['campaign_id'] === (int) $campaign->id ? 'selected' : '' }}>{{ $campaign->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        @if(in_array('channel_type', $visible_filters, true))
            <label class="rpt-field">
                <span>Channel Type</span>
                <select class="form-control" name="channel_type">
                    <option value="all" {{ $filters['channel_type'] === 'all' ? 'selected' : '' }}>All</option>
                    <option value="sip" {{ $filters['channel_type'] === 'sip' ? 'selected' : '' }}>SIP</option>
                    <option value="gsm" {{ $filters['channel_type'] === 'gsm' ? 'selected' : '' }}>GSM</option>
                </select>
            </label>
        @endif
        @if(in_array('gsm_gateway', $visible_filters, true))
            <label class="rpt-field">
                <span>GSM Gateway</span>
                <select class="form-control" name="gateway_id">
                    <option value="">All</option>
                    @foreach($filter_options['gateways'] as $gateway)
                        <option value="{{ $gateway->id }}" {{ (int) $filters['gateway_id'] === (int) $gateway->id ? 'selected' : '' }}>{{ $gateway->site_name }} ({{ $gateway->site_code }})</option>
                    @endforeach
                </select>
            </label>
        @endif
        @if(in_array('location', $visible_filters, true))
            <label class="rpt-field">
                <span>Location</span>
                <select class="form-control" name="location">
                    <option value="">All</option>
                    @foreach($filter_options['locations'] as $location)
                        <option value="{{ $location }}" {{ $filters['location'] === $location ? 'selected' : '' }}>{{ $location }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        @if(in_array('status', $visible_filters, true))
            <label class="rpt-field">
                <span>Status</span>
                <select class="form-control" name="status">
                    <option value="">All</option>
                    @foreach($filter_options['statuses'] as $status)
                        <option value="{{ $status }}" {{ $filters['status'] === $status ? 'selected' : '' }}>{{ $status }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <div class="rpt-filter-actions">
            <button class="btn primary" type="submit">
                <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
                Apply
            </button>
        </div>
    </form>
</section>

<section class="rpt-report" aria-labelledby="reportTitle">
    <div class="rpt-report-head">
        <div>
            <h2 id="reportTitle">{{ $title }}</h2>
            <p>{{ $description }}</p>
        </div>
        <div class="rpt-report-meta">
            <span>Generated on {{ $generated_at->format('M d, Y g:i A') }}</span>
            <span class="rpt-meta-sep">|</span>
            <span>By {{ $generated_by }}</span>
            @if($canExport)
            <div class="transfer rpt-export">
                <button class="btn rpt-export-btn" type="button" id="reportExportButton" aria-haspopup="true" aria-expanded="false" aria-controls="reportExportMenu">
                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4v12"></path><path d="m7 11 5 5 5-5"></path><path d="M5 20h14"></path></svg>
                    Export
                    <span aria-hidden="true">▾</span>
                </button>
                <div class="transfer-menu" id="reportExportMenu" role="menu">
                    <a role="menuitem" href="{{ route('reports.export', array_merge($exportQuery, ['format' => 'xlsx'])) }}">Excel (.xlsx)</a>
                    <a role="menuitem" href="{{ route('reports.export', array_merge($exportQuery, ['format' => 'pdf'])) }}">PDF (.pdf)</a>
                    <a role="menuitem" href="{{ route('reports.export', array_merge($exportQuery, ['format' => 'csv'])) }}">CSV (.csv)</a>
                </div>
            </div>
            @endif
        </div>
    </div>

    @if(count($summary))
    <div class="rpt-summary rpt-summary-{{ count($summary) }}">
        @foreach($summary as $card)
            <article class="rpt-kpi tone-{{ $card['tone'] }}">
                <span class="rpt-kpi-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$card['icon']] ?? $icons['stack'] !!}</svg>
                </span>
                <div>
                    <div class="rpt-kpi-label">{{ $card['label'] }}</div>
                    <div class="rpt-kpi-value">{{ $card['value'] }}</div>
                    @if(!empty($card['note']))<div class="rpt-kpi-note">{{ $card['note'] }}</div>@endif
                </div>
            </article>
        @endforeach
    </div>
    @endif

    <div class="rpt-table-wrap">
        <table class="rpt-table" aria-label="{{ $title }}">
            <thead>
                <tr>
                    @foreach($headers as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ max(1, count($headers)) }}" class="empty-state">No records match the selected filters.</td></tr>
            @endforelse
            @if($totals)
                <tr class="rpt-total">
                    @foreach($totals as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @endif
            </tbody>
        </table>
    </div>

    @if($insight)
        <div class="rpt-insight">
            <span class="rpt-insight-ico" aria-hidden="true">★</span>
            <div>
                <strong>Key Insight</strong>
                <p>{{ $insight }}</p>
            </div>
        </div>
    @endif

    @if($paginator)
    <div class="table-footer rpt-footer">
        <span>Showing {{ $paginator->firstItem() ?? 0 }} to {{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }} entries</span>
        <div class="footer-right">
            <span>Rows per page</span>
            <select class="per-page-select" onchange="location.href=this.value" aria-label="Rows per page">
                @foreach([5, 10, 25, 50] as $size)
                    <option value="{{ request()->fullUrlWithQuery(['per_page' => $size, 'page' => 1]) }}" {{ $per_page === $size ? 'selected' : '' }}>{{ $size }}</option>
                @endforeach
            </select>
            <div class="pager">
                @if($paginator->onFirstPage())
                    <span class="page-number disabled">‹</span>
                @else
                    <a class="page-number" href="{{ $paginator->previousPageUrl() }}">‹</a>
                @endif
                @for($page = 1; $page <= max($paginator->lastPage(), 1); $page++)
                    @if($page === $paginator->currentPage())
                        <span class="page-number active">{{ $page }}</span>
                    @else
                        <a class="page-number" href="{{ $paginator->url($page) }}">{{ $page }}</a>
                    @endif
                @endfor
                @if($paginator->hasMorePages())
                    <a class="page-number" href="{{ $paginator->nextPageUrl() }}">›</a>
                @else
                    <span class="page-number disabled">›</span>
                @endif
            </div>
        </div>
    </div>
    @endif
</section>
@endsection
