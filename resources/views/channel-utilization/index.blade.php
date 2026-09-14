@extends('layouts.app')
@section('content')
<div class="page-head">
    <div>
        <h1 class="page-title">Channel Utilization</h1>
        <p class="page-subtitle">Breakdown of allocated channels by campaign and channel type.</p>
    </div>
    <div class="toolbar">
        <form class="search-box media-search-form" id="channelUtilizationSearchForm" method="GET" action="{{ route('channel-utilization') }}">
            <span class="search-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
            </span>
            <input id="channelUtilizationSearchInput" name="search" value="{{ $search }}" placeholder="Search campaign..." aria-label="Search campaign" autocomplete="off">
            @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
        </form>
        <button class="btn primary" type="submit" form="channelUtilizationSearchForm">Search</button>
    </div>
</div>

<div class="table-card table-wrap">
<table aria-label="Channel Utilization">
<thead>
<tr>
    <th>Campaign</th>
    <th class="num-col">Total Channels</th>
    <th class="num-col">SIP</th>
    <th class="num-col">GSM</th>
</tr>
</thead>
<tbody>
@forelse($records as $row)
<tr>
    <td>{{ $row['name'] }}</td>
    <td class="num-col">{{ $row['total_display'] }}</td>
    <td class="num-col">{{ $row['sip_display'] }}</td>
    <td class="num-col">{{ $row['gsm_display'] }}</td>
</tr>
@empty
<tr><td colspan="4"><div class="empty-state">No campaigns match this search.</div></td></tr>
@endforelse
@if($records->total() > 0)
<tr class="dash-total-row">
    <td>Total</td>
    <td class="num-col">{{ number_format($totals['total']) }}</td>
    <td class="num-col">{{ number_format($totals['sip']) }}</td>
    <td class="num-col">{{ number_format($totals['gsm']) }}</td>
</tr>
@endif
</tbody>
</table>
<div class="table-footer">
    <span>Showing {{ $records->firstItem() ?? 0 }} to {{ $records->lastItem() ?? 0 }} of {{ $records->total() }} entries</span>
    <div class="footer-right">
        <span>Records per page:</span>
        <select class="per-page-select" onchange="location.href=this.value" aria-label="Records per page">
            @foreach([5, 10, 25, 50] as $size)
                <option value="{{ request()->fullUrlWithQuery(['per_page' => $size, 'page' => 1]) }}" {{ $perPage === $size ? 'selected' : '' }}>{{ $size }}</option>
            @endforeach
        </select>
        <div class="pager">
            @for($page = 1; $page <= max($records->lastPage(), 1); $page++)
                @if($page === $records->currentPage())
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
