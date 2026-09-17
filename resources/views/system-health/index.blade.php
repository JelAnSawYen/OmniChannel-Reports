@extends('layouts.app')
@section('content')
@php
    $queryBase = array_filter([
        'search' => $search !== '' ? $search : null,
        'per_page' => $perPage !== 10 ? $perPage : null,
    ]);
    $moduleIcons = [
        'gsm' => '<path d="M4.5 6.5A2.5 2.5 0 0 1 7 4h4.2l3.3 3.3v10.2A2.5 2.5 0 0 1 12 20H7a2.5 2.5 0 0 1-2.5-2.5v-11z"/><rect x="7" y="10.5" width="5" height="5.5" rx="1"/><path d="M17.2 5.4a5.6 5.6 0 0 1 3.3 5"/><path d="M16.8 9.2a2.6 2.6 0 0 1 1.6 2.1"/>',
        'server' => '<rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><circle cx="7" cy="7.5" r=".9" fill="currentColor" stroke="none"/><circle cx="7" cy="16.5" r=".9" fill="currentColor" stroke="none"/>',
        'sim' => '<path d="M6 5.5A2.5 2.5 0 0 1 8.5 3h4.7L18 7.8v10.7A2.5 2.5 0 0 1 15.5 21h-7A2.5 2.5 0 0 1 6 18.5v-13z"/><rect x="9" y="10.5" width="6" height="6" rx="1"/>',
        'inbound' => '<path d="M20.5 3.5 15.7 8.3"/><path d="M15.7 4v4.6h4.6"/><path d="M20 16.9v2.3a1.7 1.7 0 0 1-1.9 1.7 16.8 16.8 0 0 1-7.3-2.6 16.5 16.5 0 0 1-5.1-5.1A16.8 16.8 0 0 1 3.1 5.9 1.7 1.7 0 0 1 4.8 4h2.3a1.7 1.7 0 0 1 1.7 1.5c.1.8.3 1.6.6 2.3a1.7 1.7 0 0 1-.4 1.8l-1 1a13.5 13.5 0 0 0 5.1 5.1l1-1a1.7 1.7 0 0 1 1.8-.4c.7.3 1.5.5 2.3.6A1.7 1.7 0 0 1 20 16.9z"/>',
        'signal' => '<circle cx="12" cy="9" r="1.8"/><path d="M8.6 5.6a4.8 4.8 0 0 0 0 6.8"/><path d="M15.4 5.6a4.8 4.8 0 0 1 0 6.8"/><path d="M6.2 3.2a8.2 8.2 0 0 0 0 11.6"/><path d="M17.8 3.2a8.2 8.2 0 0 1 0 11.6"/><path d="M12 10.8V21"/><path d="M9 21l3-5 3 5"/>',
        'alert' => '<path d="M10.4 4.3 3 17.4A1.8 1.8 0 0 0 4.6 20h14.8a1.8 1.8 0 0 0 1.6-2.6L13.6 4.3a1.8 1.8 0 0 0-3.2 0z"/><path d="M12 9.5v4"/><path d="M12 16.6h.01"/>',
        'archive' => '<rect x="3.5" y="4" width="17" height="4.5" rx="1.5"/><path d="M5 8.5v9A2.5 2.5 0 0 0 7.5 20h9a2.5 2.5 0 0 0 2.5-2.5v-9"/><path d="M10 12.5h4"/>',
        'network' => '<rect x="3" y="4" width="7.5" height="6" rx="1.4"/><rect x="13.5" y="4" width="7.5" height="6" rx="1.4"/><rect x="8.2" y="14" width="7.6" height="6" rx="1.4"/><path d="M6.8 10v2.2h10.4V10"/><path d="M12 12.2V14"/>',
    ];
    $cardIcons = [
        'excellent' => '<path d="M20 6.5 9.5 17 4 11.5"/>',
        'good' => '<path d="M7 11v8H4.8A1.8 1.8 0 0 1 3 17.2v-4.4A1.8 1.8 0 0 1 4.8 11H7z"/><path d="M7 11.2 10.2 5a2.2 2.2 0 0 1 2.1-1.4h.2A1.7 1.7 0 0 1 14.2 5.4V8h4.2a2 2 0 0 1 2 2.3l-1 7.2A2 2 0 0 1 17.4 19H9"/>',
        'warning' => '<path d="M10.4 4.3 3 17.4A1.8 1.8 0 0 0 4.6 20h14.8a1.8 1.8 0 0 0 1.6-2.6L13.6 4.3a1.8 1.8 0 0 0-3.2 0z"/><path d="M12 9.5v4"/><path d="M12 16.6h.01"/>',
        'critical' => '<path d="m7 7 10 10"/><path d="m17 7-10 10"/>',
    ];
@endphp
<div class="health-page">
    <div class="health-head">
        <h1 class="page-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12h3.2l2.3-5.5 4 12.5 2.4-7h6.1"/></svg>
            System Health
        </h1>
        <p class="page-subtitle">Overview of the current system status across all equipment and modules.</p>
        <p class="health-live">
            <span class="health-live-dot" data-tier="{{ $summary['tier'] }}"></span>
            Live Score: <strong>{{ $summary['percent'] }}% {{ $summary['tier'] }}</strong>
            • Based on {{ $summary['total'] }} equipment and module records
        </p>
    </div>

    <div class="health-summary" role="list">
        @foreach($summary['items'] as $item)
            @php
                $isActive = $status === $item['key'];
                $cardQuery = $queryBase;
                if (! $isActive) {
                    $cardQuery['status'] = $item['key'];
                }
            @endphp
            <a class="health-card health-card-{{ $item['key'] }}{{ $isActive ? ' is-active' : '' }}" href="{{ route('system-health', $cardQuery) }}" role="listitem" aria-current="{{ $isActive ? 'true' : 'false' }}">
                <span class="health-card-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $cardIcons[$item['key']] !!}</svg>
                </span>
                <span class="health-card-label">{{ $item['label'] }}</span>
                <span class="health-card-count">{{ $item['value'] }} ({{ $item['percent'] }}%)</span>
                <span class="health-card-bar" aria-hidden="true"><span style="width: {{ $item['percent'] }}%"></span></span>
                <span class="health-card-copy">{{ $item['description'] }}</span>
            </a>
        @endforeach
    </div>

    <div class="table-card health-module-card">
        <div class="health-module-head">
            <div>
                <h2>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19V9"/><path d="M10 19V5"/><path d="M16 19v-7"/><path d="M22 19V8"/></svg>
                    Health by Module
                </h2>
                <p>Status, record count, and health score for each inventory module.</p>
            </div>
            <form class="search-box media-search-form" method="GET" action="{{ route('system-health') }}" role="search">
                <span class="search-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
                </span>
                <input name="search" value="{{ $search }}" placeholder="Search module..." aria-label="Search module" autocomplete="off">
                @if($status !== '')<input type="hidden" name="status" value="{{ $status }}">@endif
                @if($perPage !== 10)<input type="hidden" name="per_page" value="{{ $perPage }}">@endif
            </form>
        </div>
        <div class="table-wrap">
            <table aria-label="Health by module">
                <thead>
                    <tr>
                        <th>Module</th>
                        <th>Status</th>
                        <th>Records</th>
                        <th>Health</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $module)
                        <tr>
                            <td>
                                <div class="health-module-cell">
                                    <span class="health-module-ico" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $moduleIcons[$module['icon']] ?? $moduleIcons['network'] !!}</svg>
                                    </span>
                                    <span>
                                        <strong>{{ $module['module'] }}</strong>
                                        <small>{{ $module['total'] }} equipment</small>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div class="health-status-cell">
                                    <span class="health-status-dot health-{{ $module['status'] }}"></span>
                                    <span>
                                        <strong>{{ $module['status_label'] }}</strong>
                                        <small>{{ $module['status_copy'] }}</small>
                                    </span>
                                </div>
                            </td>
                            <td>{{ $module['total'] }} of {{ $module['total'] }}</td>
                            <td>
                                <div class="health-score-cell">
                                    <span>{{ $module['percent'] }}%</span>
                                    <span class="health-score-bar" aria-hidden="true"><span class="health-{{ $module['status'] }}" style="width: {{ $module['percent'] }}%"></span></span>
                                </div>
                            </td>
                            <td>
                                <a class="health-view-btn" href="{{ $module['href'] }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7z"/><circle cx="12" cy="12" r="3"/></svg>
                                    View Details
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">{{ $search !== '' ? 'No modules match your search.' : 'No modules in this health status.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-footer">
            <span>Showing {{ $items->firstItem() ?? 0 }} to {{ $items->lastItem() ?? 0 }} of {{ $items->total() }} modules</span>
            <div class="footer-right">
                <select class="per-page-select" onchange="location.href=this.value" aria-label="Modules per page">
                    @foreach([10, 25, 50] as $size)
                        <option value="{{ request()->fullUrlWithQuery(['per_page' => $size, 'page' => 1]) }}" {{ $perPage === $size ? 'selected' : '' }}>{{ $size }} per page</option>
                    @endforeach
                </select>
                @include('partials.table-pager', ['paginator' => $items])
            </div>
        </div>
        <div class="health-module-note">
            <span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 10.5V17"/><path d="M12 7.2h.01"/></svg>
                Health score is calculated based on the status of all equipment and modules in the system.
            </span>
            <span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7.4V12l3.2 1.9"/></svg>
                Last updated: {{ $updatedAt->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
            </span>
        </div>
    </div>
</div>
@endsection
