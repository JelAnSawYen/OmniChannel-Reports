@extends('layouts.app')
@section('content')
@php
    $canCreate = auth()->user()->hasPermission('media.create');
    $canDelete = auth()->user()->hasPermission('media.delete');
    $chevronIcon = '<svg class="ar-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>';
@endphp
<div class="page-head">
    <div>
        <h1 class="page-title">Archive Recordings</h1>
        <p class="page-subtitle">View, manage, and track archived call recordings.</p>
    </div>
    <div class="toolbar">
        <div class="search-box">
            <span class="search-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
            </span>
            <input id="arSearchInput" type="text" placeholder="Search recordings..." aria-label="Search recordings" autocomplete="off">
        </div>
        @if($canCreate)
            <button class="plus-btn" type="button" id="arAddButton" data-open="arAddModal" aria-label="Add Archive Records" title="Add Archive Records">+</button>
        @endif
    </div>
</div>

<div class="table-card table-wrap ar-tree-card">
    @forelse($tree as $campaignNode)
        @php
            $campaign = $campaignNode['campaign'];
            $campaignOpen = (int) $selectedCampaignId === (int) $campaign->id;
        @endphp
        <details class="ar-node ar-node-campaign" data-ar-text="{{ $campaign->name }}" @if($campaignOpen) open @endif>
            <summary class="ar-folder">
                {!! $chevronIcon !!}
                <span>{{ $campaign->name }}</span>
            </summary>
            <div class="ar-children">
                @forelse($campaignNode['years'] as $yearNode)
                    @php $yearOpen = $campaignOpen && (int) $selectedYear === (int) $yearNode['year']; @endphp
                    <details class="ar-node ar-node-year" data-ar-text="{{ $yearNode['year'] }}" @if($yearOpen) open @endif>
                        <summary class="ar-folder">
                            {!! $chevronIcon !!}
                            <span>{{ $yearNode['year'] }}</span>
                        </summary>
                        <div class="ar-children">
                            @if($yearNode['months']->isEmpty())
                                <div class="ar-empty-branch">No months available for this year.</div>
                            @else
                                <div class="ar-month-body">
                                    <table class="ar-files" aria-label="{{ $campaign->name }} {{ $yearNode['year'] }} months">
                                        <colgroup>
                                            <col class="ar-col-name">
                                            <col class="ar-col-status">
                                            <col class="ar-col-location">
                                            <col class="ar-col-actions">
                                        </colgroup>
                                        <thead>
                                            <tr>
                                                <th>Month</th>
                                                <th class="ar-status-column">Status</th>
                                                <th>Location</th>
                                                <th class="actions-column">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($yearNode['months'] as $monthNode)
                                                @php
                                                    $monthSelected = $yearOpen && (int) $selectedMonth === (int) $monthNode['month'];
                                                    $availableRecord = $monthNode['recordings']->first(fn ($recording) => $recording->isAvailable());
                                                    $deletedRecord = $monthNode['recordings']->first(fn ($recording) => $recording->isDeleted());
                                                    $monthAvailable = $availableRecord !== null;
                                                    $monthLocation = trim((string) (
                                                        $availableRecord?->location
                                                        ?? $deletedRecord?->location
                                                        ?? $monthNode['recordings']->pluck('location')->filter()->first()
                                                        ?? ''
                                                    ));
                                                    $monthSearchText = trim($monthNode['label'].' '.$monthNode['month'].' '.$monthLocation.' '.$monthNode['recordings']->pluck('file_name')->filter()->implode(' '));
                                                @endphp
                                                <tr class="ar-month-row{{ $monthSelected ? ' ar-month-selected' : '' }}" data-ar-text="{{ $monthSearchText }}">
                                                    <td>{{ $monthNode['label'] }}</td>
                                                    <td class="ar-status-column">
                                                        <span class="status-pill {{ $monthAvailable ? 'active' : 'inactive' }}">{{ $monthAvailable ? 'Available' : 'Deleted' }}</span>
                                                    </td>
                                                    <td>{{ $monthLocation !== '' ? $monthLocation : '—' }}</td>
                                                    <td class="actions-column">
                                                        <div class="row-actions">
                                                            @if($monthAvailable && $canDelete && $availableRecord)
                                                                <button class="action-btn delete" type="button" data-ar-delete data-id="{{ $availableRecord->id }}" data-name="{{ $availableRecord->file_name }}" data-action="{{ route('archive-recordings.destroy', $availableRecord) }}" title="Delete Recording" aria-label="Delete {{ $monthNode['label'] }} recordings">
                                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                                                                </button>
                                                            @elseif(! $monthAvailable && $deletedRecord)
                                                                <a class="action-btn edit" href="{{ route('archive-recordings.certificate', $deletedRecord) }}" target="_blank" rel="noopener noreferrer" title="View Certificate of Deletion" aria-label="View Certificate of Deletion for {{ $monthNode['label'] }}">
                                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5z"/><path d="M14 3v5h5"/><path d="M8 13h8M8 17h5"/></svg>
                                                                </a>
                                                            @else
                                                                <span class="muted">—</span>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </details>
                @empty
                    <div class="ar-empty-branch">No archived recordings yet.</div>
                @endforelse
            </div>
        </details>
    @empty
        <div class="empty-state">No campaigns found.</div>
    @endforelse
</div>
@endsection

@push('modals')
@if($canCreate)
<div class="modal-backdrop" id="arAddModal">
    <div class="modal" role="dialog" aria-labelledby="arAddTitle" aria-modal="true">
        <div class="modal-header">
            <h3 id="arAddTitle">Add Archive Records</h3>
            <button type="button" class="close-btn" data-close="arAddModal" aria-label="Close">×</button>
        </div>
        <div class="modal-body">
            <div class="form-grid">
                <div class="form-group full">
                    <label for="arAddCampaign">Campaign</label>
                    @include('partials.campaign-combo', [
                        'inputId' => 'arAddCampaign',
                        'inputName' => 'campaign',
                        'menuId' => 'arCampaignMenu',
                        'campaigns' => $campaigns,
                    ])
                </div>
                <div class="form-group">
                    <label for="arAddYear">Year</label>
                    <select class="form-control" id="arAddYear" required>
                        <option value="" selected disabled hidden>Select Year</option>
                        @foreach($yearOptions as $yearValue)
                            <option value="{{ $yearValue }}">{{ $yearValue }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group ar-add-month-group">
                    <label for="arAddMonthToggle">Month</label>
                    <div class="ar-add-dd" id="arAddMonthWrap">
                        <button class="form-control ar-add-dd-toggle" type="button" id="arAddMonthToggle" aria-haspopup="listbox" aria-expanded="false" aria-controls="arAddMonthMenu">
                            <span id="arAddMonthLabel">Select Month</span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="ar-add-dd-menu" id="arAddMonthMenu" hidden role="listbox" aria-labelledby="arAddMonthToggle">
                            @foreach($monthNames as $monthNumber => $monthLabel)
                                <button class="ar-add-dd-option" type="button" role="option" data-value="{{ $monthNumber }}">{{ $monthLabel }}</button>
                            @endforeach
                        </div>
                        <select class="ar-add-dd-native" id="arAddMonth" required tabindex="-1" aria-hidden="true">
                            <option value="" selected disabled hidden>Select Month</option>
                            @foreach($monthNames as $monthNumber => $monthLabel)
                                <option value="{{ $monthNumber }}">{{ $monthLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group full ar-add-location-group">
                    <label for="arAddLocation">Location</label>
                    <div class="ar-add-dd" id="arAddLocationWrap">
                        <input class="form-control" id="arAddLocation" type="text" placeholder="Search or type a location" autocomplete="off" aria-haspopup="listbox" aria-expanded="false" aria-controls="arAddLocationMenu">
                        <div class="ar-add-dd-menu" id="arAddLocationMenu" hidden role="listbox" aria-labelledby="arAddLocation">
                            @foreach(($locationOptions ?? []) as $locationOption)
                                <button class="ar-add-dd-option" type="button" role="option" data-value="{{ $locationOption['value'] }}">{{ $locationOption['label'] }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="form-group full">
                    <label for="arAddFiles">Recording Files</label>
                    <input class="form-control" id="arAddFiles" type="file" accept=".wav,.mp3,.mpeg,.mpga,.ogg,.oga,.webm,.m4a,.aac,.flac,.wma,audio/*" multiple>
                    <div class="ar-selected-files" id="arAddFileList" hidden></div>
                </div>
            </div>
            <p class="ar-import-error" id="arAddError" hidden></p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn secondary" data-close="arAddModal">Cancel</button>
            <button type="button" class="btn primary" id="arAddSubmit">Save</button>
        </div>
    </div>
</div>
@endif

@if($canDelete)
<div class="modal-backdrop" id="arDeleteModal">
    <div class="modal ar-delete-modal" role="dialog" aria-labelledby="arDeleteTitle" aria-modal="true">
        <div class="modal-header ar-delete-header">
            <h3 id="arDeleteTitle">
                <span class="ar-delete-header-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                </span>
                Delete Recording
            </h3>
            <button type="button" class="close-btn" data-close="arDeleteModal" aria-label="Close">×</button>
        </div>
        <form id="arDeleteForm" method="POST" action="#" enctype="multipart/form-data">
            @csrf
            @method('DELETE')
            <div class="modal-body">
                <p class="ar-delete-lead">A Certificate of Deletion (PDF) is required before this recording can be marked as deleted.</p>
                <div class="ar-delete-fields">
                    <div class="form-group">
                        <label for="arDeleteFileName">File Name</label>
                        <input class="form-control ar-delete-filename" id="arDeleteFileName" type="text" readonly>
                    </div>
                    <div class="form-group">
                        <span class="ar-delete-label">Certificate of Deletion (PDF)</span>
                        <label class="ar-delete-picker" for="arDeleteCertificate">
                            <input class="ar-delete-picker-input" id="arDeleteCertificate" name="certificate" type="file" accept="application/pdf,.pdf">
                            <svg class="ar-delete-picker-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5z"/><path d="M14 3v5h5"/><path d="M8 13h8M8 17h5"/></svg>
                            <span class="ar-delete-picker-name" id="arDeletePickerName">Choose a PDF file</span>
                            <span class="ar-delete-picker-browse">Browse</span>
                        </label>
                        <p class="ar-delete-hint">Only PDF files are allowed.</p>
                    </div>
                </div>
                <p class="ar-import-error" id="arDeleteError" hidden></p>
                <div class="ar-delete-info">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01"/><path d="M11 12h1v4h1"/></svg>
                    <span>This certificate will be permanently stored with the record for audit purposes.</span>
                </div>
            </div>
            <div class="modal-footer ar-delete-footer">
                <button type="button" class="btn secondary" data-close="arDeleteModal">Cancel</button>
                <button type="submit" class="btn danger" id="arDeleteSubmit" disabled>Confirm Deletion</button>
            </div>
        </form>
    </div>
</div>
@endif

<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('arSearchInput');
    const treeCard = document.querySelector('.ar-tree-card');
    const matches = (el, query) => String(el?.dataset.arText || '').toLowerCase().includes(query);
    const snapshotOpen = () => {
        treeCard?.querySelectorAll('details.ar-node').forEach((node) => {
            if (node.dataset.arWasOpen === undefined) {
                node.dataset.arWasOpen = node.open ? '1' : '0';
            }
        });
    };
    const restoreTree = () => {
        treeCard?.querySelectorAll('details.ar-node').forEach((node) => {
            if (node.dataset.arWasOpen !== undefined) {
                node.open = node.dataset.arWasOpen === '1';
                delete node.dataset.arWasOpen;
            }
            node.classList.remove('ar-search-hidden');
        });
        treeCard?.querySelectorAll('.ar-files tbody tr').forEach((row) => {
            row.hidden = false;
        });
    };
    const filterTree = () => {
        if (!treeCard) return;
        const query = String(searchInput?.value || '').trim().toLowerCase();
        if (!query) {
            restoreTree();
            return;
        }
        snapshotOpen();
        treeCard.querySelectorAll('.ar-node-campaign').forEach((campaign) => {
            const campaignMatch = matches(campaign, query);
            let campaignVisible = campaignMatch;
            campaign.querySelectorAll(':scope > .ar-children > .ar-node-year').forEach((year) => {
                const yearTextMatch = matches(year, query);
                let yearVisible = campaignMatch || yearTextMatch;
                year.querySelectorAll('.ar-files tbody tr').forEach((row) => {
                    const thisRow = matches(row, query);
                    row.hidden = !(campaignMatch || yearTextMatch || thisRow);
                    if (thisRow) yearVisible = true;
                    if (!campaignMatch && thisRow) {
                        year.open = true;
                        campaign.open = true;
                    }
                });
                year.classList.toggle('ar-search-hidden', !yearVisible);
                if (yearVisible) campaignVisible = true;
                if (!campaignMatch && yearTextMatch) {
                    campaign.open = true;
                    year.open = true;
                }
            });
            campaign.classList.toggle('ar-search-hidden', !campaignVisible);
        });
    };
    searchInput?.addEventListener('input', filterTree);

    const maxAudioBytes = 100 * 1024 * 1024;
    const allowedAudio = ['wav', 'mp3', 'mpeg', 'mpga', 'ogg', 'oga', 'webm', 'm4a', 'aac', 'flac', 'wma'];
    const addCampaign = document.getElementById('arAddCampaign');
    const addYear = document.getElementById('arAddYear');
    const addMonth = document.getElementById('arAddMonth');
    const addFiles = document.getElementById('arAddFiles');
    const addFileList = document.getElementById('arAddFileList');
    const addError = document.getElementById('arAddError');
    const addSubmit = document.getElementById('arAddSubmit');
    let selectedAudio = [];

    const audioExtension = (name) => {
        const parts = String(name || '').toLowerCase().split('.');
        return parts.length > 1 ? parts.pop() : '';
    };
    const showError = (el, message) => {
        if (!el) return;
        if (!message) {
            el.hidden = true;
            el.textContent = '';
            return;
        }
        el.hidden = false;
        el.textContent = message;
    };
    const renderSelectedAudio = () => {
        if (!addFileList) return;
        addFileList.innerHTML = '';
        if (selectedAudio.length === 0) {
            addFileList.hidden = true;
            return;
        }
        addFileList.hidden = false;
        selectedAudio.forEach((file) => {
            const item = document.createElement('div');
            item.textContent = file.name;
            addFileList.appendChild(item);
        });
    };

    const monthWrap = document.getElementById('arAddMonthWrap');
    const monthToggle = document.getElementById('arAddMonthToggle');
    const monthMenu = document.getElementById('arAddMonthMenu');
    const monthLabel = document.getElementById('arAddMonthLabel');
    const closeMonthMenu = () => {
        monthWrap?.classList.remove('is-open');
        if (monthMenu) monthMenu.hidden = true;
        monthToggle?.setAttribute('aria-expanded', 'false');
    };
    const openMonthMenu = () => {
        closeLocationMenu();
        monthWrap?.classList.add('is-open');
        if (monthMenu) monthMenu.hidden = false;
        monthToggle?.setAttribute('aria-expanded', 'true');
    };
    monthToggle?.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        if (monthWrap?.classList.contains('is-open')) closeMonthMenu();
        else openMonthMenu();
    });
    monthMenu?.querySelectorAll('.ar-add-dd-option').forEach((option) => {
        option.addEventListener('click', () => {
            const value = option.getAttribute('data-value') || '';
            if (addMonth) addMonth.value = value;
            if (monthLabel) monthLabel.textContent = option.textContent.trim();
            monthMenu.querySelectorAll('.ar-add-dd-option').forEach((item) => item.classList.toggle('is-selected', item === option));
            closeMonthMenu();
        });
    });
    const locationWrap = document.getElementById('arAddLocationWrap');
    const locationInput = document.getElementById('arAddLocation');
    const locationMenu = document.getElementById('arAddLocationMenu');
    const closeLocationMenu = () => {
        locationWrap?.classList.remove('is-open');
        if (locationMenu) locationMenu.hidden = true;
        locationInput?.setAttribute('aria-expanded', 'false');
    };
    const openLocationMenu = () => {
        closeMonthMenu();
        locationWrap?.classList.add('is-open');
        if (locationMenu) locationMenu.hidden = false;
        locationInput?.setAttribute('aria-expanded', 'true');
    };
    const filterLocationOptions = () => {
        const query = String(locationInput?.value || '').trim().toLowerCase();
        locationMenu?.querySelectorAll('.ar-add-dd-option').forEach((option) => {
            const text = String(option.textContent || '').trim().toLowerCase();
            option.hidden = query !== '' && !text.includes(query);
        });
    };
    locationInput?.addEventListener('click', (event) => {
        event.stopPropagation();
        if (locationWrap?.classList.contains('is-open')) closeLocationMenu();
        else openLocationMenu();
    });
    locationInput?.addEventListener('input', () => {
        if (!locationWrap?.classList.contains('is-open')) openLocationMenu();
        filterLocationOptions();
    });
    locationMenu?.querySelectorAll('.ar-add-dd-option').forEach((option) => {
        option.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            if (locationInput) locationInput.value = option.getAttribute('data-value') || option.textContent.trim();
            locationMenu.querySelectorAll('.ar-add-dd-option').forEach((item) => item.classList.toggle('is-selected', item === option));
            closeLocationMenu();
        });
    });

    document.addEventListener('click', (event) => {
        if (monthWrap && !monthWrap.contains(event.target)) closeMonthMenu();
        if (locationWrap && !locationWrap.contains(event.target)) closeLocationMenu();
    });
    document.getElementById('arAddModal')?.addEventListener('click', (event) => {
        if (event.target.closest('[data-close="arAddModal"]')) {
            closeMonthMenu();
            closeLocationMenu();
        }
    });

    addFiles?.addEventListener('change', () => {
        selectedAudio = [];
        const messages = [];
        Array.from(addFiles.files || []).forEach((file) => {
            if (file.size > maxAudioBytes) {
                messages.push(file.name + ' exceeds the 100 MB limit.');
                return;
            }
            if (!allowedAudio.includes(audioExtension(file.name))) {
                messages.push(file.name + ' is not a supported audio format.');
                return;
            }
            selectedAudio.push(file);
        });
        showError(addError, messages[0] || '');
        renderSelectedAudio();
    });

    addSubmit?.addEventListener('click', async () => {
        showError(addError, '');
        if (!addCampaign?.value || !addYear?.value || !addMonth?.value) {
            showError(addError, 'Please select a campaign, year, and month.');
            return;
        }
        if (selectedAudio.length === 0) {
            showError(addError, 'Select at least one recording file.');
            return;
        }
        const formData = new FormData();
        formData.append('campaign', addCampaign.value);
        formData.append('year', addYear.value);
        formData.append('month', addMonth.value);
        formData.append('location', document.getElementById('arAddLocation')?.value || '');
        selectedAudio.forEach((file) => formData.append('files[]', file));
        addSubmit.disabled = true;
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
                showError(addError, data.message || 'Unable to add records.');
                addSubmit.disabled = false;
                return;
            }
            window.location.href = data.redirect || window.location.href;
        } catch (error) {
            showError(addError, 'Unable to add records.');
            addSubmit.disabled = false;
        }
    });

    const deleteModal = document.getElementById('arDeleteModal');
    const deleteForm = document.getElementById('arDeleteForm');
    const deleteFileName = document.getElementById('arDeleteFileName');
    const deleteCertificate = document.getElementById('arDeleteCertificate');
    const deleteSubmit = document.getElementById('arDeleteSubmit');
    const deleteError = document.getElementById('arDeleteError');
    const deletePickerName = document.getElementById('arDeletePickerName');
    const deletePicker = deleteModal?.querySelector('.ar-delete-picker');
    let deleteBusy = false;
    const isPdf = (file) => {
        if (!file) return false;
        const name = String(file.name || '').toLowerCase();
        const type = String(file.type || '').toLowerCase();
        return name.endsWith('.pdf') || type === 'application/pdf';
    };
    const resetDeletePicker = () => {
        if (deleteCertificate) deleteCertificate.value = '';
        if (deletePickerName) deletePickerName.textContent = 'Choose a PDF file';
        deletePicker?.classList.remove('has-file');
        if (deleteSubmit) deleteSubmit.disabled = true;
        deleteBusy = false;
        showError(deleteError, '');
    };
    const syncDeleteSubmit = () => {
        if (deleteBusy) return;
        const file = deleteCertificate?.files?.[0];
        if (!file) {
            if (deletePickerName) deletePickerName.textContent = 'Choose a PDF file';
            deletePicker?.classList.remove('has-file');
            if (deleteSubmit) deleteSubmit.disabled = true;
            return;
        }
        if (!isPdf(file)) {
            if (deleteCertificate) deleteCertificate.value = '';
            if (deletePickerName) deletePickerName.textContent = 'Choose a PDF file';
            deletePicker?.classList.remove('has-file');
            if (deleteSubmit) deleteSubmit.disabled = true;
            showError(deleteError, 'Only PDF files are allowed.');
            return;
        }
        if (deletePickerName) deletePickerName.textContent = file.name;
        deletePicker?.classList.add('has-file');
        if (deleteSubmit) deleteSubmit.disabled = false;
        showError(deleteError, '');
    };
    document.querySelectorAll('[data-ar-delete]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!deleteForm || !deleteModal) return;
            deleteForm.action = button.getAttribute('data-action') || '#';
            if (deleteFileName) deleteFileName.value = button.getAttribute('data-name') || '';
            resetDeletePicker();
            deleteModal.classList.add('visible');
        });
    });
    deleteCertificate?.addEventListener('change', syncDeleteSubmit);
    deleteForm?.addEventListener('submit', (event) => {
        const file = deleteCertificate?.files?.[0];
        if (deleteBusy || !isPdf(file)) {
            event.preventDefault();
            if (!isPdf(file)) {
                if (deleteSubmit) deleteSubmit.disabled = true;
                showError(deleteError, 'Only PDF files are allowed.');
            }
            return;
        }
        deleteBusy = true;
        if (deleteSubmit) deleteSubmit.disabled = true;
    });
});
</script>
@endpush
