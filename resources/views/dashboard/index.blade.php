@extends('layouts.app')
@section('content')
@php
    $dashIcons = [
        'gsm' => '<path d="M4.5 6.5A2.5 2.5 0 0 1 7 4h4.2l3.3 3.3v10.2A2.5 2.5 0 0 1 12 20H7a2.5 2.5 0 0 1-2.5-2.5v-11z"/><rect x="7" y="10.5" width="5" height="5.5" rx="1"/><path d="M17.2 5.4a5.6 5.6 0 0 1 3.3 5"/><path d="M16.8 9.2a2.6 2.6 0 0 1 1.6 2.1"/>',
        'server' => '<rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><circle cx="7" cy="7.5" r=".9" fill="currentColor" stroke="none"/><circle cx="7" cy="16.5" r=".9" fill="currentColor" stroke="none"/>',
        'sim' => '<path d="M6 5.5A2.5 2.5 0 0 1 8.5 3h4.7L18 7.8v10.7A2.5 2.5 0 0 1 15.5 21h-7A2.5 2.5 0 0 1 6 18.5v-13z"/><rect x="9" y="10.5" width="6" height="6" rx="1"/>',
        'users' => '<circle cx="9.5" cy="8" r="3.2"/><path d="M3.5 20a6 6 0 0 1 12 0"/><path d="M16.5 5.2a3.2 3.2 0 0 1 0 5.6"/><path d="M18 14.4A6 6 0 0 1 21 20"/>',
        'inbound' => '<path d="M20.5 3.5 15.7 8.3"/><path d="M15.7 4v4.6h4.6"/><path d="M20 16.9v2.3a1.7 1.7 0 0 1-1.9 1.7 16.8 16.8 0 0 1-7.3-2.6 16.5 16.5 0 0 1-5.1-5.1A16.8 16.8 0 0 1 3.1 5.9 1.7 1.7 0 0 1 4.8 4h2.3a1.7 1.7 0 0 1 1.7 1.5c.1.8.3 1.6.6 2.3a1.7 1.7 0 0 1-.4 1.8l-1 1a13.5 13.5 0 0 0 5.1 5.1l1-1a1.7 1.7 0 0 1 1.8-.4c.7.3 1.5.5 2.3.6A1.7 1.7 0 0 1 20 16.9z"/>',
        'signal' => '<circle cx="12" cy="9" r="1.8"/><path d="M8.6 5.6a4.8 4.8 0 0 0 0 6.8"/><path d="M15.4 5.6a4.8 4.8 0 0 1 0 6.8"/><path d="M6.2 3.2a8.2 8.2 0 0 0 0 11.6"/><path d="M17.8 3.2a8.2 8.2 0 0 1 0 11.6"/><path d="M12 10.8V21"/><path d="M9 21l3-5 3 5"/>',
        'alert' => '<path d="M10.4 4.3 3 17.4A1.8 1.8 0 0 0 4.6 20h14.8a1.8 1.8 0 0 0 1.6-2.6L13.6 4.3a1.8 1.8 0 0 0-3.2 0z"/><path d="M12 9.5v4"/><path d="M12 16.6h.01"/>',
        'archive' => '<path d="M13.5 3H7.5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V8l-5-5z"/><path d="M13.5 3v5h5"/><path d="M10 12.8v4l3.4-2-3.4-2z"/>',
        'pin' => '<path d="M12 21c4.4-4 7-7.2 7-10.4A7 7 0 0 0 5 10.6C5 13.8 7.6 17 12 21z"/><circle cx="12" cy="10.2" r="2.4"/>',
        'building' => '<path d="M4 21V6a1.5 1.5 0 0 1 1.5-1.5h7A1.5 1.5 0 0 1 14 6v15"/><path d="M14 10h4.5A1.5 1.5 0 0 1 20 11.5V21"/><path d="M3 21h18"/><path d="M7 8.5h1.5M7 12h1.5M7 15.5h1.5M11 8.5h1.5M11 12h1.5M11 15.5h1.5M17 14h1"/>',
        'pulse' => '<path d="M3 12h3.2l2.3-5.5 4 12.5 2.4-7h6.1"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7.4V12l3.2 1.9"/>',
        'network' => '<circle cx="12" cy="12" r="3"/><path d="M5 12a7 7 0 0 1 7-7"/><path d="M19 12a7 7 0 0 1-7 7"/><circle cx="12" cy="5" r="1.4" fill="currentColor" stroke="none"/><circle cx="12" cy="19" r="1.4" fill="currentColor" stroke="none"/>',
    ];
    $chevron = '<path d="m9 6 6 6-6 6"/>';
    $activityStyle = function (?string $module, ?string $action = null) {
        $module = (string) $module;
        $action = (string) $action;
        if (str_contains($module, 'GSM Gateway') || str_contains($module, 'Media Gateway')) return ['gsm', 'blue'];
        if (str_contains($module, 'PDC')) return ['server', 'purple'];
        if (str_contains($module, 'SIM')) return ['sim', 'green'];
        if (str_contains($module, 'User')) return ['users', 'orange'];
        if (str_contains($module, 'Inbound')) return ['inbound', 'sky'];
        if (str_contains($module, 'Booster')) return ['signal', 'pink'];
        if (str_contains($module, 'Defective')) return ['alert', 'amber'];
        if (str_contains($module, 'Archive')) return ['archive', 'navy'];
        if (str_contains($module, 'Location')) return ['pin', 'sky'];
        if ($action === 'Exported' || str_contains($module, 'Export')) return ['archive', 'green'];
        return ['clock', 'sky'];
    };
    $donutC = 2 * M_PI * 52;
@endphp
<div class="dashboard-home">
    <div class="dash-hero">
        <div>
            <h1 class="dash-greeting">{{ $greeting }}, {{ auth()->user()->name }}!</h1>
            <p class="dash-meta">{{ auth()->user()->userType?->name }} • {{ now()->format('l, F d, Y') }}</p>
        </div>
        <div class="dash-updated">
            <span>Last updated {{ now()->format('g:i A') }}</span>
            <a class="dash-refresh" href="{{ route('dashboard') }}" title="Refresh dashboard" aria-label="Refresh dashboard">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg>
            </a>
        </div>
    </div>

    <div class="dash-kpis">
        @foreach($kpis as $kpi)
            <div class="dash-kpi dash-kpi-{{ $kpi['tone'] }}">
                <div class="dash-kpi-head">
                    <span class="dash-kpi-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $dashIcons[$kpi['icon']] ?? '' !!}</svg>
                    </span>
                    <span class="dash-kpi-label">{{ $kpi['label'] }}</span>
                </div>
                <div class="dash-kpi-value">{{ $kpi['value'] }}</div>
                <div class="dash-kpi-foot">
                    <span class="dash-kpi-note"><span class="dash-kpi-dot"></span><span>{{ $kpi['note'] }}</span></span>
                    <svg class="dash-spark" viewBox="0 0 72 28" aria-hidden="true">
                        <path class="dash-spark-line" d="{{ $kpi['spark']['path'] }}" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </div>
            </div>
        @endforeach
    </div>

    <div class="dash-mid">
        <section class="dash-card dash-locations">
            <div class="dash-card-head">
                <h3>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $dashIcons['pin'] !!}</svg>
                    Program Location Overview
                </h3>
            </div>
            <div class="dash-loc-grid">
                @foreach($locationOverview as $location)
                    <div class="dash-loc-tile">
                        <span class="dash-loc-ico" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $dashIcons['building'] !!}</svg>
                        </span>
                        <span class="dash-loc-name">{{ $location['name'] }}</span>
                        <span class="dash-loc-value">{{ $location['value'] }}</span>
                        <span class="dash-loc-label">Active Sites</span>
                        <span class="dash-loc-bar tone-{{ $location['tone'] }}"></span>
                    </div>
                @endforeach
            </div>
            @if(auth()->user()->hasPermission('media.view'))
                <a class="dash-card-foot" href="{{ route('program-location') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $dashIcons['pin'] !!}</svg>
                    <span>View all program locations</span>
                    <svg class="dash-foot-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $chevron !!}</svg>
                </a>
            @endif
        </section>

        <section class="dash-card dash-health">
            <div class="dash-card-head">
                <h3>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $dashIcons['pulse'] !!}</svg>
                    System Health
                </h3>
            </div>
            <div class="dash-overview-body">
                <div class="dash-donut-wrap">
                    @php $healthOffset = 0; @endphp
                    <svg class="dash-donut" viewBox="0 0 140 140" aria-label="System health {{ $healthPercent }} percent {{ $healthTier }}">
                        <circle cx="70" cy="70" r="52" fill="none" stroke="#eef2f7" stroke-width="16"/>
                        @foreach($healthItems as $item)
                            @if($item['value'] > 0 && $healthTotal > 0)
                                @php $len = ($item['value'] / $healthTotal) * $donutC; @endphp
                                <circle cx="70" cy="70" r="52" fill="none" stroke="{{ $item['color'] }}" stroke-width="16" stroke-linecap="butt" stroke-dasharray="{{ $len }} {{ $donutC }}" stroke-dashoffset="{{ -$healthOffset }}" transform="rotate(-90 70 70)"/>
                                @php $healthOffset += $len; @endphp
                            @endif
                        @endforeach
                    </svg>
                    <div class="dash-donut-center">
                        <strong class="dash-health-percent">{{ $healthPercent }}%</strong>
                        <span class="dash-health-tier" data-tier="{{ $healthTier }}">{{ $healthTier }}</span>
                    </div>
                </div>
                <ul class="dash-legend dash-health-legend-links">
                    @foreach($healthItems as $item)
                        <li>
                            <a class="dash-legend-link" href="{{ $item['href'] }}">
                                <span class="dash-legend-dot" style="background:{{ $item['color'] }}"></span>
                                <span class="dash-legend-label">{{ $item['label'] }}</span>
                                <span class="dash-legend-value">{{ $item['value'] }} ({{ $item['percent'] }}%)</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
            @if(auth()->user()->hasPermission('dashboard.view'))
                <a class="dash-card-foot" href="{{ route('system-health') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $dashIcons['pulse'] !!}</svg>
                    <span>View detailed Health</span>
                    <svg class="dash-foot-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $chevron !!}</svg>
                </a>
            @endif
        </section>
    </div>

    <div class="dash-bottom">
        <section class="dash-card dash-activity">
            <div class="dash-card-head">
                <h3>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $dashIcons['clock'] !!}</svg>
                    Recent System Activity
                </h3>
            </div>
            <div class="dash-activity-list">
                @forelse($recentActivities as $activity)
                    @php [$icon, $tone] = $activityStyle($activity->module, $activity->action); @endphp
                    <div class="dash-activity-row">
                        <span class="dash-activity-ico tone-{{ $tone }}" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $dashIcons[$icon] !!}</svg>
                        </span>
                        <div class="dash-activity-copy">
                            <div class="dash-activity-text">{{ $activity->description }}</div>
                            <div class="dash-activity-module">{{ $activity->module }}</div>
                        </div>
                        <div class="dash-activity-meta">
                            <span class="dash-activity-user">{{ $activity->user?->name ?? 'System' }}</span>
                            <span class="dash-activity-time">{{ $activity->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                @empty
                    <p class="muted">No activity recorded yet.</p>
                @endforelse
            </div>
            @if(auth()->user()->hasPermission('logs.view'))
                <a class="dash-card-foot" href="{{ route('activity-logs') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $dashIcons['clock'] !!}</svg>
                    <span>View all activity</span>
                    <svg class="dash-foot-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $chevron !!}</svg>
                </a>
            @endif
        </section>

        <section class="dash-card dash-overview">
            <div class="dash-card-head">
                <h3>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $dashIcons['network'] !!}</svg>
                    Network Overview
                </h3>
            </div>
            <div class="dash-overview-body">
                <div class="dash-donut-wrap">
                    @php $assetOffset = 0; @endphp
                    <svg class="dash-donut" viewBox="0 0 140 140" aria-label="Asset distribution">
                        <circle cx="70" cy="70" r="52" fill="none" stroke="#eef2f7" stroke-width="16"/>
                        @foreach($networkItems as $item)
                            @if($item['value'] > 0 && $totalAssets > 0)
                                @php $len = ($item['value'] / $totalAssets) * $donutC; @endphp
                                <circle cx="70" cy="70" r="52" fill="none" stroke="{{ $item['color'] }}" stroke-width="16" stroke-linecap="butt" stroke-dasharray="{{ $len }} {{ $donutC }}" stroke-dashoffset="{{ -$assetOffset }}" transform="rotate(-90 70 70)"/>
                                @php $assetOffset += $len; @endphp
                            @endif
                        @endforeach
                    </svg>
                    <div class="dash-donut-center">
                        <small>Total Assets</small>
                        <strong>{{ $totalAssets }}</strong>
                        <span>Across all modules</span>
                    </div>
                </div>
                <ul class="dash-legend">
                    @foreach($networkItems as $item)
                        <li @if(in_array($item['label'], ['Defective GSM', 'Archive Recordings'], true)) data-accent="1" style="--item-color: {{ $item['color'] }}" @endif>
                            <span class="dash-legend-dot" style="background:{{ $item['color'] }}"></span>
                            <span class="dash-legend-label">{{ $item['label'] }}</span>
                            <span class="dash-legend-value">{{ $item['value'] }} ({{ $item['percent'] }}%)</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>
    </div>
</div>
@endsection
@if(auth()->user()->hasPermission('media.view'))
@push('modals')
<div class="modal-backdrop" id="simInventoryModal">
    <div class="modal small" role="dialog" aria-modal="true" aria-labelledby="simInventoryModalTitle">
        <div class="modal-header">
            <h3 id="simInventoryModalTitle">SIM Inventory</h3>
            <button type="button" class="close-btn" data-close="simInventoryModal" aria-label="Close">×</button>
        </div>
        <div class="modal-body">
            <p class="sim-inventory-hint">Select which SIM inventory you want to view.</p>
            <div class="sim-pick-list">
                <a class="sim-pick sim-pick-globe" href="{{ route('globe-sim') }}">
                    <span class="sim-pick-icon" aria-hidden="true">
                        <span class="nav-icon"><img class="nav-icon-img" src="{{ asset('icons/globe-sim.png') }}" alt=""></span>
                    </span>
                    <span class="sim-pick-copy">
                        <strong>Globe SIM</strong>
                        <small>Total Records</small>
                    </span>
                    <span class="sim-pick-count">{{ $globeSims }}</span>
                </a>
                <a class="sim-pick sim-pick-smart" href="{{ route('smart-sim') }}">
                    <span class="sim-pick-icon" aria-hidden="true">
                        <span class="nav-icon"><img class="nav-icon-img" src="{{ asset('icons/smart-sim.png') }}" alt=""></span>
                    </span>
                    <span class="sim-pick-copy">
                        <strong>Smart SIM</strong>
                        <small>Total Records</small>
                    </span>
                    <span class="sim-pick-count">{{ $smartSims }}</span>
                </a>
            </div>
        </div>
    </div>
</div>
@endpush
@endif
