@extends('layouts.app')
@section('content')
<div class="page-head">
    <div>
        <h1 class="page-title">Activity Logs</h1>
        <p class="page-subtitle">View and manage your activity logs.</p>
    </div>
</div>
<div class="filter-row log-filter-row">
    <form class="search-filter-form log-search-form" method="GET">
        <div class="search-box">
            <span class="search-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg></span>
            <input name="search" value="{{ request('search') }}" placeholder="Search logs..." autocomplete="off">
            <button type="button" class="search-clear" data-clear-search aria-label="Clear search" title="Clear search">×</button>
        </div>
        <select class="select" name="action">
            <option value="">All Actions</option>
            @foreach(['Login','Logout','Added','Updated','Deleted','Imported','Exported','Created Backup','Restore Started','Changed Password','Cleared Logs','Created'] as $a)
                <option value="{{ $a }}" {{ request('action')===$a?'selected':'' }}>{{ $a }}</option>
            @endforeach
        </select>
        <button class="btn primary" type="submit">Search</button>
        @if(request()->hasAny(['search','action']))<a class="btn secondary" href="{{ route('activity-logs') }}">Reset</a>@endif
    </form>
    @if($canManageLogs)
        <form class="log-clear-form" method="POST" action="{{ route('activity-logs.clear-older') }}" data-confirm="Clear all activity logs older than {{ \App\Services\LogRetentionService::DAYS }} days? This cannot be undone." data-confirm-title="Clear Logs Older Than {{ \App\Services\LogRetentionService::DAYS }} Days" data-confirm-ok="Clear">
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
                <th>Action</th>
                <th>Module</th>
                <th>Description</th>
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
                <td>{{ $log->user?->name ?? 'System' }}</td>
                <td><span class="badge">{{ $log->action }}</span></td>
                <td>{{ $log->module }}</td>
                <td class="activity-description">{{ $log->description }}</td>
                <td>{{ $log->ip_address ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty-state">No activity found.</td></tr>
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
