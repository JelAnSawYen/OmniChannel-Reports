@extends('layouts.app')
@section('content')
@php
    $selectedCampaignId = $selectedCampaign?->id;
    $recordingTotal = $records?->total() ?? 0;
    $canDelete = auth()->user()->hasPermission('media.delete');
    $hasFilters = $search !== '' || $from !== '' || $to !== '' || $caller !== '' || $agent !== '';
    $pagerPages = [];
    if ($records) {
        $current = $records->currentPage();
        $last = $records->lastPage();
        if ($last <= 7) {
            $pagerPages = range(1, max(1, $last));
        } else {
            $window = $current <= 3 ? range(1, 5) : ($current >= $last - 2 ? range($last - 4, $last) : range($current - 2, $current + 2));
            if ($window[0] > 1) {
                $pagerPages[] = 1;
                if ($window[0] > 2) {
                    $pagerPages[] = null;
                }
            }
            foreach ($window as $page) {
                $pagerPages[] = $page;
            }
            $windowLast = $window[count($window) - 1];
            if ($windowLast < $last) {
                if ($windowLast < $last - 1) {
                    $pagerPages[] = null;
                }
                $pagerPages[] = $last;
            }
        }
    }
@endphp
<div class="page-head">
    <div>
        <h1 class="page-title">Archived Recordings</h1>
        <p class="page-subtitle">Search, view, and manage historical call recordings.</p>
    </div>
    @if(auth()->user()->hasPermission('media.create'))
        <button class="btn ar-import-btn" type="button" id="arImportAudioBtn" data-open="arImportModal">
            <svg class="btn-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 16V7"/><path d="m8 11 4-4 4 4"/><path d="M5 19h14"/></svg>
            Import Audio Logs
        </button>
    @endif
</div>

<form class="ar-filter-card" id="archiveSearchForm" method="GET" action="{{ route('archive-recordings') }}" aria-label="Recording filters">
    @if($perPage !== 10)<input type="hidden" name="per_page" value="{{ $perPage }}">@endif
    @if(($sort ?? 'oldest') !== 'oldest')<input type="hidden" name="sort" value="{{ $sort }}">@endif
    <div class="ar-filter-row ar-filter-row-primary">
        <label class="ar-field">
            <span>Campaign</span>
            <div class="ar-dd" data-ar-dd>
                <input type="hidden" name="campaign" value="{{ $selectedCampaignId ?: '' }}">
                <button class="ar-filter-control ar-dd-toggle {{ $selectedCampaignId ? 'has-value' : '' }}" type="button" aria-haspopup="listbox" aria-expanded="false" aria-label="Campaign">
                    <span>{{ $selectedCampaign?->name ?: 'Select campaign' }}</span>
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="ar-dd-menu" role="listbox" hidden>
                    @foreach($campaigns as $campaign)
                        <button class="ar-dd-option {{ (int) $selectedCampaignId === (int) $campaign->id ? 'is-selected' : '' }}" type="button" role="option" data-value="{{ $campaign->id }}">{{ $campaign->name }}</button>
                    @endforeach
                </div>
            </div>
        </label>
        <label class="ar-field">
            <span>Year</span>
            <div class="ar-dd" data-ar-dd>
                <input type="hidden" name="year" value="{{ $selectedYear ?: '' }}">
                <button class="ar-filter-control ar-dd-toggle {{ $selectedYear ? 'has-value' : '' }}" type="button" aria-haspopup="listbox" aria-expanded="false" aria-label="Year">
                    <span>{{ $selectedYear ?: 'Year' }}</span>
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="ar-dd-menu" role="listbox" hidden>
                    @foreach($yearOptions as $yearValue)
                        <button class="ar-dd-option {{ (int) $selectedYear === (int) $yearValue ? 'is-selected' : '' }}" type="button" role="option" data-value="{{ $yearValue }}">{{ $yearValue }}</button>
                    @endforeach
                </div>
            </div>
        </label>
        <label class="ar-field">
            <span>Month</span>
            <div class="ar-dd" data-ar-dd>
                <input type="hidden" name="month" value="{{ $selectedMonth ?: '' }}">
                <button class="ar-filter-control ar-dd-toggle {{ $selectedMonth ? 'has-value' : '' }}" type="button" aria-haspopup="listbox" aria-expanded="false" aria-label="Month">
                    <span>{{ $selectedMonth ? ($monthNames[$selectedMonth] ?? 'Month') : 'Month' }}</span>
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="ar-dd-menu" role="listbox" hidden>
                    @foreach($monthNames as $monthNumber => $monthLabel)
                        <button class="ar-dd-option {{ (int) $selectedMonth === (int) $monthNumber ? 'is-selected' : '' }}" type="button" role="option" data-value="{{ $monthNumber }}">{{ $monthLabel }}</button>
                    @endforeach
                </div>
            </div>
        </label>
        <label class="ar-field">
            <span>Date From</span>
            <span class="ar-date-field">
                <input class="select ar-filter-control" type="date" name="from" value="{{ $from }}" aria-label="Date From">
                <span class="ar-date-icon" aria-hidden="true">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/></svg>
                </span>
            </span>
        </label>
        <label class="ar-field">
            <span>Date To</span>
            <span class="ar-date-field">
                <input class="select ar-filter-control" type="date" name="to" value="{{ $to }}" aria-label="Date To">
                <span class="ar-date-icon" aria-hidden="true">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/></svg>
                </span>
            </span>
        </label>
    </div>
    <div class="ar-filter-row ar-filter-row-secondary">
        <label class="ar-field">
            <span>Caller Number</span>
            <input class="select ar-filter-control" type="text" name="caller" value="{{ $caller }}" placeholder="Enter caller number" aria-label="Caller Number" autocomplete="off">
        </label>
        <label class="ar-field">
            <span>Agent Number</span>
            <input class="select ar-filter-control" type="text" name="agent" value="{{ $agent }}" placeholder="Enter agent number" aria-label="Agent Number" autocomplete="off">
        </label>
        <label class="ar-field ar-field-search">
            <span class="sr-only">Search</span>
            <div class="search-box media-search-form">
                <span class="search-icon" aria-hidden="true">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
                </span>
                <input id="archiveSearchInput" name="search" value="{{ $search }}" placeholder="Search recordings (file name, caller, agent, etc...)" aria-label="Search recordings" autocomplete="off">
                <button type="button" class="search-clear" data-clear-search aria-label="Clear search" title="Clear search">×</button>
            </div>
        </label>
        <div class="ar-filter-actions">
            <button class="btn primary ar-search-btn" type="submit">Search</button>
            <a class="btn secondary ar-clear-btn" href="{{ route('archive-recordings') }}" id="archiveReset">
                <svg class="btn-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 3-6.7"></path><path d="M3 4v5h5"></path></svg>
                Clear Filters
            </a>
        </div>
    </div>
</form>

<div class="ar-workspace">
    <section class="ar-panel ar-recordings" aria-label="Call Recordings">
        <div class="ar-table-toolbar">
            <div class="ar-table-toolbar-left">
                <label class="ar-select-all">
                    <input type="checkbox" id="arSelectAll" {{ ($records && $records->count()) ? '' : 'disabled' }}>
                    Select All
                </label>
                <span class="ar-selected-count" id="arSelectedCount">0 selected</span>
                @if($canDelete)
                    <button class="btn ar-bulk-delete" type="submit" form="arBulkForm" id="arBulkDelete" disabled>
                        <svg class="btn-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="m6 7 1 14h10l1-14"/><path d="M9 7V4h6v3"/></svg>
                        Delete Selected
                    </button>
                @endif
            </div>
            <div class="ar-table-toolbar-right">
                <span class="ar-total">{{ number_format($recordingTotal) }} {{ $recordingTotal === 1 ? 'recording' : 'recordings' }}</span>
                <label class="ar-toolbar-field">
                    <span>Sort by</span>
                    <select class="select ar-toolbar-select" aria-label="Sort by" onchange="location.href=this.value">
                        <option value="{{ request()->fullUrlWithQuery(['sort'=>'newest','page'=>1]) }}" {{ ($sort ?? 'oldest') === 'newest' ? 'selected' : '' }}>Date (Newest)</option>
                        <option value="{{ request()->fullUrlWithQuery(['sort'=>'oldest','page'=>1]) }}" {{ ($sort ?? 'oldest') === 'oldest' ? 'selected' : '' }}>Date (Oldest)</option>
                    </select>
                </label>
                <label class="ar-toolbar-field">
                    <span>Show</span>
                    <select class="select ar-toolbar-select" aria-label="Records per page" onchange="location.href=this.value">
                        @foreach([5,10,25,50] as $size)
                            <option value="{{ request()->fullUrlWithQuery(['per_page'=>$size,'page'=>1]) }}" {{ $perPage===$size?'selected':'' }}>{{ $size }}</option>
                        @endforeach
                    </select>
                    <span>per page</span>
                </label>
            </div>
        </div>
        @if($canDelete)
            <form id="arBulkForm" method="POST" action="{{ route('archive-recordings.bulk-destroy') }}" data-confirm="Delete the selected recordings?" data-confirm-title="Delete Selected" data-confirm-ok="Delete">
                @csrf
                @method('DELETE')
            </form>
        @endif
        <div class="table-card table-wrap">
            <table class="ar-table" aria-label="Call Recordings">
                <thead>
                    <tr>
                        <th class="ar-check-col"><span class="sr-only">Select</span></th>
                        <th class="ar-index-col">#</th>
                        <th>File Name</th>
                        <th>
                            <span class="ar-sort-head">Call Date &amp; Time
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m7 10 5-5 5 5"/><path d="m7 14 5 5 5-5"/></svg>
                            </span>
                        </th>
                        <th>Caller Number</th>
                        <th>Agent Number</th>
                        <th>Duration</th>
                        <th class="actions-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @if(! $records)
                    <tr><td colspan="8"><div class="empty-state">Select a campaign, year, and month to view call recordings.</div></td></tr>
                @else
                    @forelse($records as $record)
                        <tr data-recording-id="{{ $record->id }}">
                            <td class="ar-check-col">
                                <input class="ar-row-check" type="checkbox" name="ids[]" value="{{ $record->id }}" form="arBulkForm" {{ $canDelete ? '' : 'disabled' }} aria-label="Select {{ $record->file_name }}">
                            </td>
                            <td class="ar-index-col">{{ ($records->firstItem() ?? 1) + $loop->index }}</td>
                            <td>
                                <button class="ar-file-link" type="button" data-ar-play="{{ route('archive-recordings.play', $record) }}" data-ar-name="{{ $record->file_name }}" data-ar-download="{{ route('archive-recordings.download', $record) }}" @if($canDelete) data-ar-delete="{{ route('archive-recordings.destroy', $record) }}" @endif>{{ $record->file_name ?: '—' }}</button>
                            </td>
                            <td>
                                @if($record->called_at)
                                    <span class="ar-date-stack">
                                        <strong>{{ $record->called_at->format('M j, Y') }}</strong>
                                        <small>{{ $record->called_at->format('g:i A') }}</small>
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $record->caller_number ?: '—' }}</td>
                            <td>{{ $record->agent_number ?: '—' }}</td>
                            <td class="ar-duration-cell">{{ $record->durationDisplay() }}</td>
                            <td class="actions-column">
                                <button class="ar-play-circle" type="button" data-ar-play="{{ route('archive-recordings.play', $record) }}" data-ar-name="{{ $record->file_name }}" data-ar-download="{{ route('archive-recordings.download', $record) }}" @if($canDelete) data-ar-delete="{{ route('archive-recordings.destroy', $record) }}" @endif title="Play" aria-label="Play {{ $record->file_name }}">
                                    <svg class="ar-icon-play ar-play-icon" width="12" height="12" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><polygon points="9,6 9,18 19,12"/></svg>
                                    <svg class="ar-icon-pause" width="12" height="12" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="empty-state">{{ $hasFilters ? 'No recordings match the current search or filters.' : 'No call recordings found for this campaign, year, and month.' }}</div></td></tr>
                    @endforelse
                @endif
                </tbody>
            </table>
            <div class="table-footer">
                <span>
                    @if($records)
                        Showing {{ $records->firstItem() ?? 0 }} to {{ $records->lastItem() ?? 0 }} of {{ number_format($records->total()) }} recordings
                    @else
                        Showing 0 to 0 of 0 recordings
                    @endif
                </span>
                <div class="pager">
                    @if($records && $records->lastPage() > 1)
                        @if($records->onFirstPage())
                            <span class="page-number" aria-disabled="true">‹</span>
                        @else
                            <a class="page-number" href="{{ $records->previousPageUrl() }}" aria-label="Previous">‹</a>
                        @endif
                        @foreach($pagerPages as $page)
                            @if($page === null)
                                <span class="page-number ar-page-ellipsis">…</span>
                            @elseif($page === $records->currentPage())
                                <span class="page-number active">{{ $page }}</span>
                            @else
                                <a class="page-number" href="{{ $records->url($page) }}">{{ $page }}</a>
                            @endif
                        @endforeach
                        @if($records->hasMorePages())
                            <a class="page-number" href="{{ $records->nextPageUrl() }}" aria-label="Next">›</a>
                        @else
                            <span class="page-number" aria-disabled="true">›</span>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </section>

    <aside class="ar-panel ar-player" id="arPlayer" aria-label="Now Playing">
        <div class="ar-player-head">
            <h2>
                <span class="ar-player-icon" aria-hidden="true">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 12c1.6-4 3.4-6 5.4-6s3.8 2 5.4 6-3.4 6-5.4 6-3.8-2-5.4-6z"/><path d="M14 12c1.2-3 2.5-4.5 4-4.5S20.8 9 22 12s-2.5 4.5-4 4.5S15.2 15 14 12z"/></svg>
                </span>
                Now Playing
            </h2>
            <button class="ar-player-close" type="button" id="arPlayerClose" aria-label="Close player">×</button>
        </div>
        <div class="ar-player-empty" id="arPlayerEmpty">Select a recording to play.</div>
        <div class="ar-player-active" id="arPlayerActive" hidden>
            <div class="ar-player-track">
                <span class="ar-player-art" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 18V6l10-2v12"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="16" r="2"/></svg>
                </span>
                <div class="ar-player-copy">
                    <strong id="arPlayerName">Recording</strong>
                </div>
            </div>
            <div class="ar-waveform" id="arWaveform" role="slider" aria-label="Seek recording" tabindex="0">
                <canvas id="arWaveformCanvas" width="280" height="56"></canvas>
            </div>
            <div class="ar-player-times">
                <span id="arPlayerCurrent">00:00:00</span>
                <span id="arPlayerDuration">00:00:00</span>
            </div>
            <div class="ar-player-controls">
                <button class="ar-skip" type="button" id="arSkipBack" aria-label="Skip back 10 seconds">−10</button>
                <button class="ar-play-toggle" type="button" id="arPlayPause" aria-label="Play">
                    <svg class="ar-icon-play" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><polygon points="9,6 9,18 19,12"/></svg>
                    <svg class="ar-icon-pause" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/></svg>
                </button>
                <button class="ar-skip" type="button" id="arSkipForward" aria-label="Skip forward 10 seconds">+10</button>
            </div>
            <div class="ar-player-volume">
                <span class="ar-volume-icon" aria-hidden="true">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 10v4h3l4 3V7L7 10H4z"/><path d="M16 9.5a4 4 0 0 1 0 5"/><path d="M18.2 7.5a7 7 0 0 1 0 9"/></svg>
                </span>
                <input id="arVolume" type="range" min="0" max="100" value="80" aria-label="Volume">
                <span id="arVolumePct">80%</span>
            </div>
            <a class="btn ar-player-download" id="arPlayerDownload" href="#">
                <svg class="btn-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 4v11"/><path d="m7.5 11 4.5 4.5 4.5-4.5"/><path d="M5 19h14"/></svg>
                Download Recording
            </a>
            @if($canDelete)
                <form id="arPlayerDeleteForm" method="POST" action="#" data-confirm="Delete this recording?" data-confirm-title="Delete Recording" data-confirm-ok="Delete">
                    @csrf
                    @method('DELETE')
                    <button class="btn ar-player-delete" type="submit">
                        <svg class="btn-icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="m6 7 1 14h10l1-14"/><path d="M9 7V4h6v3"/></svg>
                        Delete Recording
                    </button>
                </form>
            @endif
            <audio id="arPlayAudio" preload="metadata"></audio>
        </div>
    </aside>
</div>
@endsection

@push('modals')
@if(auth()->user()->hasPermission('media.create'))
<div class="modal-backdrop" id="arImportModal">
    <div class="modal ar-import-modal" role="dialog" aria-labelledby="arImportTitle" aria-modal="true">
        <div class="modal-header ar-import-header">
            <h3 id="arImportTitle">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 20V8"/><path d="m7 13 5-5 5 5"/><path d="M5 4h14"/></svg>
                Import Audio Logs
            </h3>
            <button type="button" class="close-btn" data-close="arImportModal" aria-label="Close">×</button>
        </div>
        <div class="modal-body ar-import-body">
            <p class="ar-import-lead">Bulk upload call recordings and assign them to a campaign, year, and month.</p>
            <div class="ar-import-selects">
                <label class="ar-import-field">
                    <span>Campaign <em>*</em></span>
                    <select class="form-control" id="arImportCampaign" required>
                        <option value="">Select campaign</option>
                        @foreach($campaigns as $campaign)
                            <option value="{{ $campaign->id }}" {{ (int) $selectedCampaignId === (int) $campaign->id ? 'selected' : '' }}>{{ $campaign->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ar-import-field">
                    <span>Year <em>*</em></span>
                    <select class="form-control" id="arImportYear" required>
                        <option value="">Select year</option>
                        @foreach($yearOptions as $yearValue)
                            <option value="{{ $yearValue }}" {{ (int) $selectedYear === (int) $yearValue ? 'selected' : '' }}>{{ $yearValue }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="ar-import-field">
                    <span>Month <em>*</em></span>
                    <select class="form-control" id="arImportMonth" required>
                        <option value="">Select month</option>
                        @foreach($monthNames as $monthNumber => $monthLabel)
                            <option value="{{ $monthNumber }}" {{ (int) $selectedMonth === (int) $monthNumber ? 'selected' : '' }}>{{ $monthLabel }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <div class="ar-import-drop" id="arImportDrop" tabindex="0">
                <input type="file" id="arImportFileInput" accept=".wav,.mp3,.ogg,.oga,.webm,.m4a,.aac,.flac,.wma,audio/*" multiple hidden>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M12 16V7"/><path d="m8 11 4-4 4 4"/><path d="M4 16.5V18a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-1.5"/></svg>
                <p>Drag &amp; Drop Audio Files Here or <button type="button" class="ar-import-browse" id="arImportBrowse">Browse Files</button></p>
                <small>You can select multiple audio files for bulk upload. Supported formats: WAV, MP3 (and other allowed formats).</small>
            </div>
            <div class="ar-import-files" id="arImportFiles" hidden>
                <div class="ar-import-files-head">
                    <strong>Selected Audio Logs</strong>
                    <span id="arImportFilesMeta"></span>
                </div>
                <ul id="arImportFileList"></ul>
            </div>
            <p class="ar-import-error" id="arImportError" hidden></p>
            <div class="ar-import-destination" id="arImportDestination">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7.5A1.5 1.5 0 0 1 5.5 6H10l2 2h6.5A1.5 1.5 0 0 1 20 9.5v8A1.5 1.5 0 0 1 18.5 19h-13A1.5 1.5 0 0 1 4 17.5v-10z"/></svg>
                <span><strong>Import destination:</strong> <span id="arImportDestinationText">Select a campaign, year, and month.</span></span>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn secondary" data-close="arImportModal">Cancel</button>
            <button type="button" class="btn primary" id="arImportSubmit" disabled>
                <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 20V8"/><path d="m7 13 5-5 5 5"/><path d="M5 4h14"/></svg>
                <span id="arImportSubmitLabel">Import Audio Logs</span>
            </button>
        </div>
    </div>
</div>
@endif
<script>
document.addEventListener('DOMContentLoaded', () => {
    const closeDropdowns = (except) => {
        document.querySelectorAll('[data-ar-dd].is-open').forEach((dropdown) => {
            if (dropdown === except) return;
            dropdown.classList.remove('is-open');
            const toggle = dropdown.querySelector('.ar-dd-toggle');
            const menu = dropdown.querySelector('.ar-dd-menu');
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
            if (menu) menu.hidden = true;
        });
    };
    document.querySelectorAll('[data-ar-dd]').forEach((dropdown) => {
        const toggle = dropdown.querySelector('.ar-dd-toggle');
        const menu = dropdown.querySelector('.ar-dd-menu');
        const input = dropdown.querySelector('input[type="hidden"]');
        const label = toggle?.querySelector('span');
        toggle?.addEventListener('click', (event) => {
            event.preventDefault();
            const willOpen = !dropdown.classList.contains('is-open');
            closeDropdowns();
            if (!willOpen) return;
            dropdown.classList.add('is-open');
            toggle.setAttribute('aria-expanded', 'true');
            if (menu) menu.hidden = false;
        });
        menu?.querySelectorAll('.ar-dd-option').forEach((option) => {
            option.addEventListener('click', () => {
                const value = option.getAttribute('data-value') || '';
                if (input) input.value = value;
                if (label) label.textContent = option.textContent.trim();
                toggle?.classList.toggle('has-value', value !== '');
                menu.querySelectorAll('.ar-dd-option').forEach((item) => item.classList.remove('is-selected'));
                option.classList.add('is-selected');
                closeDropdowns();
            });
        });
    });
    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-ar-dd]')) closeDropdowns();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeDropdowns();
    });

    const audio = document.getElementById('arPlayAudio');
    const playerEmpty = document.getElementById('arPlayerEmpty');
    const playerActive = document.getElementById('arPlayerActive');
    const playerName = document.getElementById('arPlayerName');
    const playerCurrent = document.getElementById('arPlayerCurrent');
    const playerDuration = document.getElementById('arPlayerDuration');
    const playPause = document.getElementById('arPlayPause');
    const skipBack = document.getElementById('arSkipBack');
    const skipForward = document.getElementById('arSkipForward');
    const volume = document.getElementById('arVolume');
    const volumePct = document.getElementById('arVolumePct');
    const downloadLink = document.getElementById('arPlayerDownload');
    const deleteForm = document.getElementById('arPlayerDeleteForm');
    const waveform = document.getElementById('arWaveform');
    const canvas = document.getElementById('arWaveformCanvas');
    const closePlayer = document.getElementById('arPlayerClose');
    let peaks = [];

    const formatTime = (seconds) => {
        if (!Number.isFinite(seconds) || seconds < 0) return '00:00:00';
        const total = Math.floor(seconds);
        const h = String(Math.floor(total / 3600)).padStart(2, '0');
        const m = String(Math.floor((total % 3600) / 60)).padStart(2, '0');
        const s = String(total % 60).padStart(2, '0');
        return h + ':' + m + ':' + s;
    };
    const setPlayingUi = (playing) => {
        playPause?.classList.toggle('is-playing', playing);
        playPause?.setAttribute('aria-label', playing ? 'Pause' : 'Play');
        document.querySelectorAll('.ar-play-circle').forEach((button) => {
            const active = playing && button.closest('tr')?.classList.contains('is-playing');
            button.classList.toggle('is-playing', active);
            const name = button.getAttribute('data-ar-name') || 'recording';
            button.setAttribute('aria-label', (active ? 'Pause ' : 'Play ') + name);
            button.setAttribute('title', active ? 'Pause' : 'Play');
        });
    };
    const drawWaveform = () => {
        if (!canvas || !waveform) return;
        const ctx = canvas.getContext('2d');
        const rect = waveform.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;
        const cssW = Math.max(1, Math.floor(rect.width || 280));
        const cssH = 56;
        if (canvas.width !== Math.floor(cssW * dpr) || canvas.height !== Math.floor(cssH * dpr)) {
            canvas.style.width = cssW + 'px';
            canvas.style.height = cssH + 'px';
            canvas.width = Math.floor(cssW * dpr);
            canvas.height = Math.floor(cssH * dpr);
        }
        const width = canvas.width;
        const height = canvas.height;
        ctx.clearRect(0, 0, width, height);
        const duration = audio?.duration || 0;
        const progress = duration > 0 ? (audio.currentTime || 0) / duration : 0;
        const barCount = peaks.length || 64;
        const gap = Math.max(1, Math.round(dpr));
        const barWidth = Math.max(dpr, (width - gap * (barCount - 1)) / barCount);
        for (let i = 0; i < barCount; i++) {
            const amplitude = peaks[i] ?? 0.14;
            const barHeight = Math.max(4 * dpr, amplitude * (height - 8 * dpr));
            const x = i * (barWidth + gap);
            const y = (height - barHeight) / 2;
            ctx.fillStyle = (i / barCount) <= progress ? '#0b70f7' : '#bfdbfe';
            ctx.fillRect(x, y, barWidth, barHeight);
        }
        const playheadX = Math.min(width - dpr, Math.max(0, progress * width));
        ctx.fillStyle = '#1d4ed8';
        ctx.fillRect(playheadX, 0, Math.max(2, dpr), height);
    };
    const loadPeaksFromAudio = async (url) => {
        peaks = [];
        drawWaveform();
        try {
            const response = await fetch(url, { credentials: 'same-origin' });
            const buffer = await response.arrayBuffer();
            const context = new (window.AudioContext || window.webkitAudioContext)();
            const decoded = await context.decodeAudioData(buffer.slice(0));
            const channel = decoded.getChannelData(0);
            const count = 72;
            const block = Math.max(1, Math.floor(channel.length / count));
            const values = [];
            for (let i = 0; i < count; i++) {
                let peak = 0;
                const start = i * block;
                for (let j = 0; j < block; j += 4) {
                    const sample = Math.abs(channel[start + j] || 0);
                    if (sample > peak) peak = sample;
                }
                values.push(peak);
            }
            const max = Math.max(...values, 0.0001);
            peaks = values.map((value) => 0.12 + 0.88 * Math.pow(value / max, 0.62));
            if (context.state !== 'closed') context.close();
        } catch (error) {
            peaks = [];
        }
        drawWaveform();
    };
    const seekFromClientX = (clientX) => {
        if (!audio || !waveform || !Number.isFinite(audio.duration) || audio.duration <= 0) return;
        const rect = waveform.getBoundingClientRect();
        const ratio = Math.min(1, Math.max(0, (clientX - rect.left) / rect.width));
        audio.currentTime = ratio * audio.duration;
        drawWaveform();
    };
    const openRecording = (button) => {
        const url = button.getAttribute('data-ar-play');
        const name = button.getAttribute('data-ar-name') || 'Recording';
        if (!url || !audio) return;
        if (playerName) playerName.textContent = name;
        if (downloadLink) downloadLink.href = button.getAttribute('data-ar-download') || '#';
        if (deleteForm) {
            const action = button.getAttribute('data-ar-delete') || '';
            deleteForm.setAttribute('action', action || '#');
            deleteForm.hidden = !action;
        }
        playerEmpty.hidden = true;
        playerActive.hidden = false;
        document.querySelectorAll('.ar-table tbody tr').forEach((row) => row.classList.remove('is-playing'));
        button.closest('tr')?.classList.add('is-playing');
        audio.src = url;
        audio.volume = (Number(volume?.value) || 80) / 100;
        audio.play().catch(() => {});
        setPlayingUi(true);
        loadPeaksFromAudio(url);
    };
    const stopPlayer = () => {
        if (!audio) return;
        audio.pause();
        audio.removeAttribute('src');
        audio.load();
        peaks = [];
        if (playerEmpty) playerEmpty.hidden = false;
        if (playerActive) playerActive.hidden = true;
        document.querySelectorAll('.ar-table tbody tr').forEach((row) => row.classList.remove('is-playing'));
        setPlayingUi(false);
        drawWaveform();
    };

    document.querySelectorAll('[data-ar-play]').forEach((button) => {
        button.addEventListener('click', () => {
            if (button.classList.contains('ar-play-circle')) {
                const row = button.closest('tr');
                const isCurrent = Boolean(row?.classList.contains('is-playing') && audio?.src);
                if (isCurrent) {
                    if (audio.paused) {
                        audio.play().catch(() => {});
                        setPlayingUi(true);
                    } else {
                        audio.pause();
                        setPlayingUi(false);
                    }
                    return;
                }
            }
            openRecording(button);
        });
    });
    playPause?.addEventListener('click', () => {
        if (!audio?.src) return;
        if (audio.paused) {
            audio.play().catch(() => {});
            setPlayingUi(true);
        } else {
            audio.pause();
            setPlayingUi(false);
        }
    });
    skipBack?.addEventListener('click', () => {
        if (!audio) return;
        audio.currentTime = Math.max(0, (audio.currentTime || 0) - 10);
        drawWaveform();
    });
    skipForward?.addEventListener('click', () => {
        if (!audio) return;
        audio.currentTime = Math.min(audio.duration || 0, (audio.currentTime || 0) + 10);
        drawWaveform();
    });
    volume?.addEventListener('input', () => {
        const value = Number(volume.value) || 0;
        if (audio) audio.volume = value / 100;
        if (volumePct) volumePct.textContent = value + '%';
    });
    audio?.addEventListener('timeupdate', () => {
        if (playerCurrent) playerCurrent.textContent = formatTime(audio.currentTime);
        drawWaveform();
    });
    audio?.addEventListener('loadedmetadata', () => {
        if (playerDuration) playerDuration.textContent = formatTime(audio.duration);
        const durationCell = document.querySelector('tr.is-playing .ar-duration-cell');
        if (durationCell && Number.isFinite(audio.duration) && audio.duration > 0) {
            durationCell.textContent = formatTime(audio.duration);
        }
        drawWaveform();
    });
    audio?.addEventListener('play', () => setPlayingUi(true));
    audio?.addEventListener('pause', () => setPlayingUi(false));
    audio?.addEventListener('ended', () => setPlayingUi(false));
    waveform?.addEventListener('click', (event) => seekFromClientX(event.clientX));
    closePlayer?.addEventListener('click', stopPlayer);
    waveform?.addEventListener('keydown', (event) => {
        if (!audio || !Number.isFinite(audio.duration) || audio.duration <= 0) return;
        if (event.key === 'ArrowLeft') {
            audio.currentTime = Math.max(0, (audio.currentTime || 0) - 5);
            drawWaveform();
        } else if (event.key === 'ArrowRight') {
            audio.currentTime = Math.min(audio.duration, (audio.currentTime || 0) + 5);
            drawWaveform();
        }
    });
    window.addEventListener('resize', drawWaveform);
    if (audio) audio.volume = 0.8;
    drawWaveform();

    const selectAll = document.getElementById('arSelectAll');
    const selectedCount = document.getElementById('arSelectedCount');
    const bulkDelete = document.getElementById('arBulkDelete');
    const rowChecks = () => Array.from(document.querySelectorAll('.ar-row-check:not(:disabled)'));
    const syncSelection = () => {
        const boxes = rowChecks();
        const checked = boxes.filter((box) => box.checked);
        boxes.forEach((box) => box.closest('tr')?.classList.toggle('is-checked', box.checked));
        if (selectedCount) selectedCount.textContent = checked.length + ' selected';
        if (bulkDelete) bulkDelete.disabled = checked.length === 0;
        if (selectAll && boxes.length) {
            selectAll.checked = checked.length === boxes.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < boxes.length;
        }
    };
    selectAll?.addEventListener('change', () => {
        rowChecks().forEach((box) => { box.checked = selectAll.checked; });
        syncSelection();
    });
    document.querySelectorAll('.ar-row-check').forEach((box) => box.addEventListener('change', syncSelection));
    syncSelection();

    const importModal = document.getElementById('arImportModal');
    const importCampaign = document.getElementById('arImportCampaign');
    const importYear = document.getElementById('arImportYear');
    const importMonth = document.getElementById('arImportMonth');
    const importDrop = document.getElementById('arImportDrop');
    const importInput = document.getElementById('arImportFileInput');
    const importBrowse = document.getElementById('arImportBrowse');
    const importFilesWrap = document.getElementById('arImportFiles');
    const importFileList = document.getElementById('arImportFileList');
    const importFilesMeta = document.getElementById('arImportFilesMeta');
    const importError = document.getElementById('arImportError');
    const importDestinationText = document.getElementById('arImportDestinationText');
    const importSubmit = document.getElementById('arImportSubmit');
    const importSubmitLabel = document.getElementById('arImportSubmitLabel');
    const maxAudioBytes = 100 * 1024 * 1024;
    const allowedAudio = ['wav', 'mp3', 'mpeg', 'mpga', 'ogg', 'oga', 'webm', 'm4a', 'aac', 'flac', 'wma'];
    let selectedAudio = [];

    const audioExtension = (name) => {
        const parts = String(name || '').toLowerCase().split('.');
        return parts.length > 1 ? parts.pop() : '';
    };
    const formatSize = (bytes) => {
        if (bytes >= 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
        if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return bytes + ' B';
    };
    const showImportError = (message) => {
        if (!importError) return;
        if (!message) {
            importError.hidden = true;
            importError.textContent = '';
            return;
        }
        importError.hidden = false;
        importError.textContent = message;
    };
    const updateDestination = () => {
        if (!importDestinationText) return;
        const campaign = importCampaign?.selectedOptions[0]?.textContent?.trim() || '';
        const year = importYear?.value || '';
        const month = importMonth?.selectedOptions[0]?.textContent?.trim() || '';
        if (importCampaign?.value && year && importMonth?.value) {
            importDestinationText.textContent = campaign + ' → ' + year + ' → ' + month;
            return;
        }
        importDestinationText.textContent = 'Select a campaign, year, and month.';
    };
    const renderSelectedAudio = () => {
        if (!importFileList || !importFilesWrap || !importFilesMeta || !importSubmit || !importSubmitLabel) return;
        importFileList.innerHTML = '';
        const total = selectedAudio.reduce((sum, file) => sum + file.size, 0);
        if (selectedAudio.length === 0) {
            importFilesWrap.hidden = true;
            importSubmit.disabled = true;
            importSubmitLabel.textContent = 'Import Audio Logs';
            return;
        }
        importFilesWrap.hidden = false;
        importFilesMeta.textContent = selectedAudio.length + (selectedAudio.length === 1 ? ' file' : ' files') + ' (' + formatSize(total) + ')';
        importSubmit.disabled = false;
        importSubmitLabel.textContent = 'Import ' + selectedAudio.length + ' Audio Log' + (selectedAudio.length === 1 ? '' : 's');
        selectedAudio.forEach((file, index) => {
            const item = document.createElement('li');
            item.innerHTML = '<span class="ar-import-file-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5z"/><path d="M14 3v5h5"/></svg></span><span class="ar-import-file-copy"><strong></strong><small></small></span><button type="button" class="ar-import-file-remove" aria-label="Remove file">×</button>';
            item.querySelector('strong').textContent = file.name;
            item.querySelector('small').textContent = formatSize(file.size);
            item.querySelector('button').addEventListener('click', () => {
                selectedAudio.splice(index, 1);
                renderSelectedAudio();
            });
            importFileList.appendChild(item);
        });
    };
    const addAudioFiles = (fileList) => {
        const incoming = Array.from(fileList || []);
        const messages = [];
        incoming.forEach((file) => {
            if (file.size > maxAudioBytes) {
                messages.push(file.name + ' exceeds the 100 MB limit.');
                return;
            }
            if (!allowedAudio.includes(audioExtension(file.name))) {
                messages.push(file.name + ' is not a supported audio format. Use WAV, MP3, or another allowed audio format.');
                return;
            }
            selectedAudio.push(file);
        });
        showImportError(messages[0] || '');
        renderSelectedAudio();
    };

    if (importModal && importInput) {
        ['change', 'input'].forEach((eventName) => {
            importCampaign?.addEventListener(eventName, updateDestination);
            importYear?.addEventListener(eventName, updateDestination);
            importMonth?.addEventListener(eventName, updateDestination);
        });
        updateDestination();
        importBrowse?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            importInput.click();
        });
        importDrop?.addEventListener('click', (event) => {
            if (event.target.closest('.ar-import-browse')) return;
            importInput.click();
        });
        importInput.addEventListener('change', () => {
            addAudioFiles(importInput.files);
            importInput.value = '';
        });
        ['dragenter', 'dragover'].forEach((eventName) => {
            importDrop?.addEventListener(eventName, (event) => {
                event.preventDefault();
                importDrop.classList.add('is-dragover');
            });
        });
        ['dragleave', 'drop'].forEach((eventName) => {
            importDrop?.addEventListener(eventName, (event) => {
                event.preventDefault();
                if (eventName === 'drop') {
                    importDrop.classList.remove('is-dragover');
                    addAudioFiles(event.dataTransfer?.files);
                    return;
                }
                if (!importDrop.contains(event.relatedTarget)) {
                    importDrop.classList.remove('is-dragover');
                }
            });
        });
        importSubmit?.addEventListener('click', async () => {
            showImportError('');
            if (!importCampaign?.value || !importYear?.value || !importMonth?.value) {
                showImportError('Please select a campaign, year, and month.');
                return;
            }
            if (selectedAudio.length === 0) {
                showImportError('Select at least one audio file to import.');
                return;
            }
            const formData = new FormData();
            formData.append('campaign_id', importCampaign.value);
            formData.append('year', importYear.value);
            formData.append('month', importMonth.value);
            selectedAudio.forEach((file) => formData.append('files[]', file));
            importSubmit.disabled = true;
            try {
                const response = await fetch(@json(route('archive-recordings.import.audio')), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: formData,
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.ok) {
                    showImportError(data.message || 'Import failed. Please try again.');
                    importSubmit.disabled = selectedAudio.length === 0;
                    return;
                }
                window.location.href = data.redirect || window.location.href;
            } catch (error) {
                showImportError('Import failed. Please try again.');
                importSubmit.disabled = selectedAudio.length === 0;
            }
        });
    }
});
</script>
@endpush
