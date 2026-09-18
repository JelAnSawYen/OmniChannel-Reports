@extends('layouts.app')
@section('content')
@php
    $kpis = $overview['kpis'];
    $icons = [
        'campaigns' => '<path d="m3 11 18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
        'gsm' => '<path d="M4.5 6.5A2.5 2.5 0 0 1 7 4h4.2l3.3 3.3v10.2A2.5 2.5 0 0 1 12 20H7a2.5 2.5 0 0 1-2.5-2.5v-11z"/><rect x="7" y="10.5" width="5" height="5.5" rx="1"/><path d="M17.2 5.4a5.6 5.6 0 0 1 3.3 5"/><path d="M16.8 9.2a2.6 2.6 0 0 1 1.6 2.1"/>',
        'channels' => '<rect x="4" y="14" width="16" height="5" rx="1.2"/><rect x="4" y="8.5" width="16" height="5" rx="1.2"/><rect x="4" y="3" width="16" height="5" rx="1.2"/>',
        'sim' => '<path d="M6 5.5A2.5 2.5 0 0 1 8.5 3h4.7L18 7.8v10.7A2.5 2.5 0 0 1 15.5 21h-7A2.5 2.5 0 0 1 6 18.5v-13z"/><rect x="9" y="10.5" width="6" height="6" rx="1"/>',
        'defective' => '<path d="M10.4 4.3 3 17.4A1.8 1.8 0 0 0 4.6 20h14.8a1.8 1.8 0 0 0 1.6-2.6L13.6 4.3a1.8 1.8 0 0 0-3.2 0z"/><path d="M12 9.5v4"/><path d="M12 16.6h.01"/>',
        'inbound' => '<path d="M20 16.9v2.3a1.7 1.7 0 0 1-1.9 1.7 16.8 16.8 0 0 1-7.3-2.6 16.5 16.5 0 0 1-5.1-5.1A16.8 16.8 0 0 1 3.1 5.9 1.7 1.7 0 0 1 4.8 4h2.3a1.7 1.7 0 0 1 1.7 1.5c.1.8.3 1.6.6 2.3a1.7 1.7 0 0 1-.4 1.8l-1 1a13.5 13.5 0 0 0 5.1 5.1l1-1a1.7 1.7 0 0 1 1.8-.4c.7.3 1.5.5 2.3.6A1.7 1.7 0 0 1 20 16.9z"/>',
        'bars' => '<path d="M4 18V10"/><path d="M10 18V6"/><path d="M16 18v-7"/><path d="M22 18V4"/>',
        'util' => '<rect x="4" y="5" width="16" height="14" rx="2"/><path d="M8 9h8M8 13h5"/>',
        'trend' => '<path d="M4 19h16"/><path d="m4 15 5-5 4 3 7-8"/>',
    ];
    $canViewUtilization = auth()->user()->hasPermission('dashboard.view') && auth()->user()->canAccessModule('dashboard');
@endphp
<div class="dashboard-home" id="dashboardHome" data-snapshot-url="{{ route('dashboard.snapshot') }}" data-fingerprint="{{ $overview['fingerprint'] }}">
    <script type="application/json" id="dashOverviewData">@json($overview)</script>
    <div class="dash-first">
    <div class="dash-hero">
        <div>
            <h1 class="dash-greeting">{{ $greeting }}, {{ auth()->user()->name }}!</h1>
            <p class="dash-meta">{{ $roleName }} • {{ $currentDate }}</p>
        </div>
        <div class="dash-updated">
            <span>Updated <span data-dash-updated>{{ $overview['generated_at_label'] }}</span></span>
            <button type="button" class="dash-refresh" data-dash-refresh title="Refresh dashboard" aria-label="Refresh dashboard">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg>
            </button>
        </div>
    </div>

    <div class="dash-kpis">
        <div class="dash-kpi dash-kpi-blue">
            <span class="dash-kpi-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons['campaigns'] !!}</svg>
            </span>
            <div class="dash-kpi-body">
                <span class="dash-kpi-label">Total Campaigns</span>
                <div class="dash-kpi-value" data-dash-kpi="campaigns">{{ $kpis['campaigns']['display'] }}</div>
            </div>
        </div>
        <div class="dash-kpi dash-kpi-green">
            <span class="dash-kpi-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons['gsm'] !!}</svg>
            </span>
            <div class="dash-kpi-body">
                <span class="dash-kpi-label">Total GSM Gateway</span>
                <div class="dash-kpi-value" data-dash-kpi="gateways">{{ $kpis['gateways']['display'] }}</div>
            </div>
        </div>
        <div class="dash-kpi dash-kpi-purple">
            <span class="dash-kpi-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons['channels'] !!}</svg>
            </span>
            <div class="dash-kpi-body">
                <span class="dash-kpi-label">Total SIP Channels</span>
                <div class="dash-kpi-value" data-dash-kpi="channels">{{ $kpis['channels']['display'] }}</div>
            </div>
        </div>
        <div class="dash-kpi dash-kpi-orange dash-kpi-sims">
            <span class="dash-kpi-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons['sim'] !!}</svg>
            </span>
            <div class="dash-kpi-body">
                <span class="dash-kpi-label">Total SIMs</span>
                <div class="dash-kpi-value" data-dash-kpi="sims">{{ $kpis['sims']['display'] }}</div>
                <div class="dash-sim-inline">
                    <span>Globe</span> <strong data-dash-kpi="globe">{{ $kpis['globe']['display'] }}</strong>
                    <span class="dash-sim-sep">|</span>
                    <span>Smart</span> <strong data-dash-kpi="smart">{{ $kpis['smart']['display'] }}</strong>
                </div>
            </div>
        </div>
        <div class="dash-kpi dash-kpi-pink">
            <span class="dash-kpi-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons['defective'] !!}</svg>
            </span>
            <div class="dash-kpi-body">
                <span class="dash-kpi-label">Defective GSM</span>
                <div class="dash-kpi-value" data-dash-kpi="defective">{{ $kpis['defective']['display'] }}</div>
                <div class="dash-sim-inline">
                    <span>Open</span> <strong data-dash-kpi="defective_open">{{ $kpis['defective_open']['display'] }}</strong>
                    <span class="dash-sim-sep">|</span>
                    <span>In Repair</span> <strong data-dash-kpi="defective_repair">{{ $kpis['defective_repair']['display'] }}</strong>
                </div>
            </div>
        </div>
        <div class="dash-kpi dash-kpi-sky">
            <span class="dash-kpi-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons['inbound'] !!}</svg>
            </span>
            <div class="dash-kpi-body">
                <span class="dash-kpi-label">Inbound Numbers</span>
                <div class="dash-kpi-value" data-dash-kpi="inbound">{{ $kpis['inbound']['display'] }}</div>
                <div class="dash-sim-inline">
                    <span>Mobile</span> <strong data-dash-kpi="mobile">{{ $kpis['mobile']['display'] }}</strong>
                    <span class="dash-sim-sep">|</span>
                    <span>Landline</span> <strong data-dash-kpi="landline">{{ $kpis['landline']['display'] }}</strong>
                </div>
            </div>
        </div>
    </div>

    <section class="dash-card dash-campaigns">
        <div class="dash-card-head dash-card-head-stack">
            <div>
                <h3>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons['bars'] !!}</svg>
                    Campaigns with Most Allocations
                </h3>
                <p class="dash-card-sub">Top campaigns based on total allocated channels.</p>
            </div>
        </div>
        <div data-dash-panel="bars">{!! $overview['html']['bars'] !!}</div>
        <div class="dash-campaign-insight">
            <span class="dash-campaign-insight-ico" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 4h8v4a4 4 0 0 1-8 0V4z"/><path d="M8 6H6a3 3 0 0 0 3 3"/><path d="M16 6h2a3 3 0 0 1-3 3"/><path d="M12 12v3"/><path d="M9 20h6"/><path d="M10 17h4v3h-4z"/></svg>
            </span>
            <p data-dash-insight>{{ $overview['campaign_insight'] }}</p>
        </div>
    </section>
    </div>

    <div class="dash-bottom">
    <section class="dash-card dash-utilization">
        <div class="dash-card-head dash-card-head-stack">
            <div>
                <h3>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons['util'] !!}</svg>
                    Channel Utilization
                </h3>
                <p class="dash-card-sub">Breakdown of allocated channels by campaign and channel type.</p>
            </div>
            <div class="dash-util-tools">
                <label class="search-box dash-util-search">
                    <span class="search-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
                    </span>
                    <input type="search" data-dash-util-search placeholder="Search campaign..." aria-label="Search campaign" autocomplete="off">
                </label>
                @if($canViewUtilization)
                    <a class="btn secondary dash-view-all-btn" href="{{ route('channel-utilization') }}">View All</a>
                @endif
            </div>
        </div>
        <div data-dash-panel="utilization">{!! $overview['html']['utilization'] !!}</div>
    </section>

    <section class="dash-card dash-trends">
        <div class="dash-card-head dash-card-head-stack">
            <div>
                <h3>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icons['trend'] !!}</svg>
                    Allocation Trends
                </h3>
                <p class="dash-card-sub">Trends of allocated channels over time.</p>
            </div>
            <span class="dash-card-meta">
                Last {{ \App\Services\DashboardOverviewService::TREND_DAYS }} Days
                <span class="dash-trend-key"><i class="total"></i> Total Channels</span>
                <span class="dash-trend-key"><i class="sip"></i> SIP</span>
                <span class="dash-trend-key"><i class="gsm"></i> GSM</span>
            </span>
        </div>
        <div data-dash-panel="trend">{!! $overview['html']['trend'] !!}</div>
    </section>
    </div>
</div>
@endsection
@if(auth()->user()->hasPermission('media.view') && auth()->user()->canAccessModule('globe-sim'))
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
