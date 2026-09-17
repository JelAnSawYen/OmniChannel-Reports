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
        </div>
        <select class="select" name="action">
            <option value="" {{ request('action') ? '' : 'selected' }} hidden>All Actions</option>
            @foreach(['Created','Added','Updated','Deleted','Imported','Exported','Changed Password','Cleared Logs'] as $a)
                <option value="{{ $a }}" {{ request('action')===$a?'selected':'' }}>{{ $a }}</option>
            @endforeach
        </select>
        @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
        <button class="btn primary" type="submit">Search</button>
    </form>
    @if($canManageLogs)
        <form class="log-clear-form" method="POST" action="{{ route('activity-logs.clear-older') }}" data-confirm="Clear all activity logs older than {{ \App\Services\Logs\LogRetentionService::DAYS }} days? This cannot be undone." data-confirm-title="Clear Logs Older Than {{ \App\Services\Logs\LogRetentionService::DAYS }} Days" data-confirm-ok="Clear">
            @csrf
            @method('DELETE')
            <button class="btn danger-outline" type="submit">Clear Logs Older Than {{ \App\Services\Logs\LogRetentionService::DAYS }} Days</button>
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
                <td>{{ $log->user?->email ?? '—' }}</td>
                <td><span class="badge">{{ $log->action }}</span></td>
                <td>{{ $log->module }}</td>
                <td class="activity-description">{{ $log->description }}</td>
                <td>{{ $log->ip_address ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty-state">No activity found.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="table-footer">
        <span>Showing {{ $logs->firstItem() ?? 0 }} to {{ $logs->lastItem() ?? 0 }} of {{ $logs->total() }} logs</span>
        <div class="footer-right">
            <span>Records per page:</span>
            <select class="per-page-select" onchange="location.href=this.value" aria-label="Records per page">
                @foreach([5,10,25,50] as $size)
                    <option value="{{ request()->fullUrlWithQuery(['per_page'=>$size,'page'=>1]) }}" {{ $perPage===$size?'selected':'' }}>{{ $size }}</option>
                @endforeach
            </select>
            @include('partials.table-pager', ['paginator' => $logs])
        </div>
    </div>
</div>
@endsection
