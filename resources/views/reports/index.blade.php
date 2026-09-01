@extends('layouts.app')
@section('content')
<div class="page-head">
    <div>
        <h1 class="page-title">Operations Reports</h1>
        <p class="page-subtitle">Inventory, capacity, and telco cost summaries for daily operations.</p>
    </div>
</div>

<div class="dashboard-grid">
    <div class="stat-card"><div><div class="stat-label">Media Gateways</div><div class="stat-value">{{ $gatewayTotal }}</div><div class="stat-note">Active inventory records</div></div><div class="stat-icon">▣</div></div>
    <div class="stat-card"><div><div class="stat-label">Port Utilization</div><div class="stat-value">{{ $ports['utilization'] }}%</div><div class="stat-note">{{ $ports['in_use'] }} in use / {{ $ports['total'] }} ports</div></div><div class="stat-icon">▣</div></div>
    <div class="stat-card"><div><div class="stat-label">Active Monthly Cost</div><div class="stat-value">₱{{ number_format($activeMonthlyTelco,2) }}</div><div class="stat-note">Active telco contracts</div></div><div class="stat-icon">$</div></div>
    <div class="stat-card"><div><div class="stat-label">Expiring Contracts</div><div class="stat-value">{{ $expiringContracts->count() }}</div><div class="stat-note">Next 30 days</div></div><div class="stat-icon">☰</div></div>
</div>

<div class="dashboard-two">
    <div class="panel">
        <div class="panel-head"><h3>Inventory Snapshot</h3>@if(auth()->user()->hasPermission('media.export'))<a class="btn secondary" href="{{ route('reports.export','inventory') }}">Export</a>@endif</div>
        <div class="panel-body">
            <div class="info-row"><span>Channel Prefixes</span><strong>{{ $channelPrefixes }}</strong></div>
            <div class="info-row"><span>Network Prefixes</span><strong>{{ $networkPrefixes }}</strong></div>
            <div class="info-row"><span>Ports Available</span><strong>{{ $ports['available'] }}</strong></div>
            <div class="info-row"><span>Ports In Use</span><strong>{{ $ports['in_use'] }}</strong></div>
            <div class="info-row"><span>Ports Disabled</span><strong>{{ $ports['disabled'] }}</strong></div>
            @php($max=max(1,$ports['total']))
            <div class="bar-row"><span>In Use</span><div class="bar"><span style="width:{{ ($ports['in_use']/$max)*100 }}%"></span></div><strong>{{ $ports['in_use'] }}</strong></div>
            <div class="bar-row"><span>Available</span><div class="bar"><span style="width:{{ ($ports['available']/$max)*100 }}%"></span></div><strong>{{ $ports['available'] }}</strong></div>
        </div>
    </div>
    <div class="panel">
        <div class="panel-head"><h3>Telco Cost by Provider</h3>@if(auth()->user()->hasPermission('media.export'))<a class="btn secondary" href="{{ route('reports.export','telco') }}">Export</a>@endif</div>
        <div class="panel-body">
            @php($maxCost=max(1,(float)$telcoByProvider->max('monthly_cost')))
            @forelse($telcoByProvider as $row)
                <div class="bar-row"><span>{{ $row->provider }}</span><div class="bar"><span style="width:{{ ((float)$row->monthly_cost/$maxCost)*100 }}%"></span></div><strong>₱{{ number_format((float)$row->monthly_cost,2) }}</strong></div>
            @empty
                <p class="muted">No active telco cost records.</p>
            @endforelse
        </div>
    </div>
</div>

<div class="panel" style="margin-top:16px">
    <div class="panel-head"><h3>Contracts Expiring in 30 Days</h3>@if(auth()->user()->hasPermission('media.export'))<a class="btn secondary" href="{{ route('reports.export','contracts') }}">Export</a>@endif</div>
    <div class="table-wrap"><table><thead><tr><th>Provider</th><th>Site</th><th>Service</th><th>Monthly Cost</th><th>Ends</th><th>Status</th></tr></thead><tbody>
    @forelse($expiringContracts as $contract)
        <tr>
            <td>{{ $contract->provider }}</td>
            <td>{{ $contract->site }}</td>
            <td>{{ $contract->service_type }}</td>
            <td>₱{{ number_format((float)$contract->monthly_cost,2) }}</td>
            <td>{{ $contract->contract_end?->format('M d, Y') }}</td>
            <td><span class="status-pill unknown">{{ $contract->status }}</span></td>
        </tr>
    @empty
        <tr><td colspan="6" class="empty-state">No contracts expiring in the next 30 days.</td></tr>
    @endforelse
    </tbody></table></div>
</div>
@endsection
