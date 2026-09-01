@extends('layouts.app')
@section('content')
<div class="page-head">
    <div>
        <h1 class="page-title">Login History</h1>
        <p class="page-subtitle">View and manage your login history.</p>
    </div>
</div>
<div class="filter-row log-filter-row">
    <form class="search-filter-form log-search-form" method="GET">
        <div class="search-box">
            <span class="search-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg></span>
            <input name="search" value="{{ request('search') }}" placeholder="Search email" autocomplete="off">
            <button type="button" class="search-clear" data-clear-search aria-label="Clear search" title="Clear search">×</button>
        </div>
        <button class="btn primary" type="submit">Search</button>
        @if(request()->has('search'))<a class="btn secondary" href="{{ route('login-history') }}">Reset</a>@endif
    </form>
    @if($canManageLogs)
        <form class="log-clear-form" method="POST" action="{{ route('login-history.clear-older') }}" data-confirm="Clear all login history older than {{ \App\Services\LogRetentionService::DAYS }} days? This cannot be undone." data-confirm-title="Clear Logs Older Than {{ \App\Services\LogRetentionService::DAYS }} Days" data-confirm-ok="Clear">
            @csrf
            @method('DELETE')
            <button class="btn danger-outline" type="submit">Clear Logs Older Than {{ \App\Services\LogRetentionService::DAYS }} Days</button>
        </form>
    @endif
</div>
<div class="table-card table-wrap activity-log-card">
    <table class="activity-log-table">
        <thead>
            <tr>
                <th>Date/Time</th>
                <th>User</th>
                <th>Email</th>
                <th>Status</th>
                <th>IP Address</th>
            </tr>
        </thead>
        <tbody>
        @forelse($logs as $log)
            <tr>
                <td>
                    <span class="log-datetime">
                        <strong>{{ $log->created_at->format('M d, Y') }}</strong>
                        <span>{{ $log->created_at->format('h:i A') }}</span>
                    </span>
                </td>
                <td>{{ $log->user?->name ?? 'Unknown' }}</td>
                <td>{{ $log->email }}</td>
                <td><span class="status-pill {{ strtolower($log->status)==='success'?'online':'offline' }}">{{ $log->status }}</span></td>
                <td>{{ $log->ip_address ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty-state">No login records.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="table-footer">
        <span>{{ $logs->firstItem()??0 }}-{{ $logs->lastItem()??0 }} of {{ $logs->total() }} logs</span>
        <div class="pager">
            @if($logs->onFirstPage())<span class="page-number">‹</span>@else<a class="page-number" href="{{ $logs->previousPageUrl() }}">‹</a>@endif
            @for($p=1;$p<=$logs->lastPage();$p++)
                @if($p===$logs->currentPage())<span class="page-number active">{{ $p }}</span>@else<a class="page-number" href="{{ $logs->url($p) }}">{{ $p }}</a>@endif
            @endfor
            @if($logs->hasMorePages())<a class="page-number" href="{{ $logs->nextPageUrl() }}">›</a>@endif
        </div>
    </div>
</div>
@endsection
