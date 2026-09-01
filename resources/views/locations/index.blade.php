@extends('layouts.app')
@section('content')
<div class="page-head">
    <div>
        <h1 class="page-title">Program Location</h1>
        <p class="page-subtitle">GSM gateway inventory grouped by program location.</p>
    </div>
</div>

<form class="loc-filters" id="programLocationFilters" method="GET" action="{{ route('program-location') }}">
    <div class="search-box">
        <span class="search-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
        </span>
        <input name="search" value="{{ $search }}" placeholder="Search location or code..." aria-label="Search location or code" autocomplete="off">
        <button type="button" class="search-clear" data-clear-search aria-label="Clear search" title="Clear search">×</button>
    </div>
    <label class="loc-filter">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.5 5h17l-6.6 7.8V19l-3.8-2v-4.2L3.5 5z"></path></svg>
        <select name="status" aria-label="Filter by gateway status" onchange="this.form.submit()">
            <option value="">All Gateway Status</option>
            <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active</option>
            <option value="none" {{ $status === 'none' ? 'selected' : '' }}>None</option>
        </select>
    </label>
    <label class="loc-filter">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.5 5h17l-6.6 7.8V19l-3.8-2v-4.2L3.5 5z"></path></svg>
        <select name="location" aria-label="Filter by location" onchange="this.form.submit()">
            <option value="">All Locations</option>
            @foreach($locationOptions as $slug => $name)
                <option value="{{ $slug }}" {{ $locationFilter === $slug ? 'selected' : '' }}>{{ $name }}</option>
            @endforeach
        </select>
    </label>
</form>

<div class="table-card table-wrap">
<table aria-label="Program Location">
<thead>
<tr>
    <th>ID</th>
    <th>Location</th>
    <th>Code</th>
    <th class="num-col">GSM Gateway Records</th>
    <th>Gateway Status</th>
    <th>Last Updated</th>
    <th class="actions-column">Actions</th>
</tr>
</thead>
<tbody>
@forelse($locations as $location)
<tr>
    <td>{{ ($locations->firstItem() ?? 1) + $loop->index }}</td>
    <td>
        <span class="loc-cell">
            <span>{{ $location['name'] }}</span>
        </span>
    </td>
    <td>{{ $location['code'] }}</td>
    <td class="num-col loc-records-col"><span class="num-align" data-label="GSM Gateway Records">{{ $location['gateways'] }}</span></td>
    <td>
        <span class="loc-badge {{ $location['gateways'] > 0 ? 'on' : 'off' }}">
            <span class="loc-badge-dot" aria-hidden="true"></span>
            {{ $location['gateways'] > 0 ? $location['gateways'].' Active' : '0 None' }}
        </span>
    </td>
    <td>{{ $location['updated_at'] ? \Illuminate\Support\Carbon::parse($location['updated_at'])->format('M d, Y h:i A') : '—' }}</td>
    <td class="actions-column">
        <div class="row-actions">
            @php $manageActive = ($locationFilter ?? '') === $location['slug']; @endphp
            <a class="loc-manage{{ $manageActive ? ' active' : '' }}" href="{{ route('program-location.show', $location['slug']) }}" title="Manage {{ $location['name'] }}" @if($manageActive) aria-current="page" @endif>Manage</a>
        </div>
    </td>
</tr>
@empty
<tr><td colspan="7"><div class="empty-state">No program locations found.</div></td></tr>
@endforelse
</tbody>
</table>
<div class="table-footer">
    <span>Showing {{ $locations->firstItem() ?? 0 }} to {{ $locations->lastItem() ?? 0 }} of {{ $locations->total() }} entries</span>
    <div class="footer-right">
        <div class="pager">
            @if($locations->onFirstPage())
                <span class="page-number disabled">‹</span>
            @else
                <a class="page-number" href="{{ $locations->previousPageUrl() }}">‹</a>
            @endif
            @for($page = 1; $page <= max($locations->lastPage(), 1); $page++)
                @if($page === $locations->currentPage())
                    <span class="page-number active">{{ $page }}</span>
                @else
                    <a class="page-number" href="{{ $locations->url($page) }}">{{ $page }}</a>
                @endif
            @endfor
            @if($locations->hasMorePages())
                <a class="page-number" href="{{ $locations->nextPageUrl() }}">›</a>
            @else
                <span class="page-number disabled">›</span>
            @endif
        </div>
    </div>
</div>
</div>
@endsection
