<?php $__env->startSection('content'); ?>
<?php
    $canCreate = auth()->user()->hasPermission('media.create');
    $canDelete = auth()->user()->hasPermission('media.delete');
    $chevronIcon = '<svg class="ar-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>';
?>
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
        <?php if($canCreate): ?>
            <button class="plus-btn" type="button" id="arAddButton" data-open="arAddModal" aria-label="Add Archive Records" title="Add Archive Records">+</button>
        <?php endif; ?>
    </div>
</div>

<div class="table-card table-wrap ar-tree-card">
    <?php $__empty_1 = true; $__currentLoopData = $tree; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $campaignNode): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <?php
            $campaign = $campaignNode['campaign'];
            $campaignOpen = (int) $selectedCampaignId === (int) $campaign->id;
        ?>
        <details class="ar-node ar-node-campaign" data-ar-text="<?php echo e($campaign->name); ?>" <?php if($campaignOpen): ?> open <?php endif; ?>>
            <summary class="ar-folder">
                <?php echo $chevronIcon; ?>

                <span><?php echo e($campaign->name); ?></span>
            </summary>
            <div class="ar-children">
                <?php $__empty_2 = true; $__currentLoopData = $campaignNode['years']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $yearNode): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
                    <?php $yearOpen = $campaignOpen && (int) $selectedYear === (int) $yearNode['year']; ?>
                    <details class="ar-node ar-node-year" data-ar-text="<?php echo e($yearNode['year']); ?>" <?php if($yearOpen): ?> open <?php endif; ?>>
                        <summary class="ar-folder">
                            <?php echo $chevronIcon; ?>

                            <span><?php echo e($yearNode['year']); ?></span>
                        </summary>
                        <div class="ar-children">
                            <?php if($yearNode['months']->isEmpty()): ?>
                                <div class="ar-empty-branch">No months available for this year.</div>
                            <?php else: ?>
                                <div class="ar-month-body">
                                    <table class="ar-files" aria-label="<?php echo e($campaign->name); ?> <?php echo e($yearNode['year']); ?> months">
                                        <colgroup>
                                            <col class="ar-col-name">
                                            <col class="ar-col-status">
                                            <col class="ar-col-actions">
                                        </colgroup>
                                        <thead>
                                            <tr>
                                                <th>Month</th>
                                                <th>Status</th>
                                                <th class="actions-column">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $__currentLoopData = $yearNode['months']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $monthNode): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <?php
                                                    $monthSelected = $yearOpen && (int) $selectedMonth === (int) $monthNode['month'];
                                                    $availableRecord = $monthNode['recordings']->first(fn ($recording) => $recording->isAvailable());
                                                    $deletedRecord = $monthNode['recordings']->first(fn ($recording) => $recording->isDeleted());
                                                    $monthAvailable = $availableRecord !== null;
                                                    $monthSearchText = trim($monthNode['label'].' '.$monthNode['month'].' '.$monthNode['recordings']->pluck('file_name')->filter()->implode(' '));
                                                ?>
                                                <tr class="ar-month-row<?php echo e($monthSelected ? ' ar-month-selected' : ''); ?>" data-ar-text="<?php echo e($monthSearchText); ?>">
                                                    <td><?php echo e($monthNode['label']); ?></td>
                                                    <td>
                                                        <span class="status-pill <?php echo e($monthAvailable ? 'active' : 'inactive'); ?>"><?php echo e($monthAvailable ? 'Available' : 'Deleted'); ?></span>
                                                    </td>
                                                    <td class="actions-column">
                                                        <div class="row-actions">
                                                            <?php if($monthAvailable && $canDelete && $availableRecord): ?>
                                                                <button class="action-btn delete" type="button" data-ar-delete data-id="<?php echo e($availableRecord->id); ?>" data-name="<?php echo e($availableRecord->file_name); ?>" data-action="<?php echo e(route('archive-recordings.destroy', $availableRecord)); ?>" title="Delete Recording" aria-label="Delete <?php echo e($monthNode['label']); ?> recordings">
                                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                                                                </button>
                                                            <?php elseif(! $monthAvailable && $deletedRecord): ?>
                                                                <a class="action-btn edit" href="<?php echo e(route('archive-recordings.certificate', $deletedRecord)); ?>" title="View Certificate of Deletion" aria-label="View Certificate of Deletion for <?php echo e($monthNode['label']); ?>">
                                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5z"/><path d="M14 3v5h5"/><path d="M8 13h8M8 17h5"/></svg>
                                                                </a>
                                                            <?php else: ?>
                                                                <span class="muted">—</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </details>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
                    <div class="ar-empty-branch">No archived recordings yet.</div>
                <?php endif; ?>
            </div>
        </details>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <div class="empty-state">No campaigns found.</div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('modals'); ?>
<?php if($canCreate): ?>
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
                    <select class="form-control" id="arAddCampaign" required>
                        <option value="" selected disabled hidden>Select Campaign</option>
                        <?php $__currentLoopData = $campaigns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $campaign): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($campaign->id); ?>"><?php echo e($campaign->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="arAddYear">Year</label>
                    <select class="form-control" id="arAddYear" required>
                        <option value="" selected disabled hidden>Select Year</option>
                        <?php $__currentLoopData = $yearOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $yearValue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($yearValue); ?>"><?php echo e($yearValue); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
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
                            <?php $__currentLoopData = $monthNames; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $monthNumber => $monthLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <button class="ar-add-dd-option" type="button" role="option" data-value="<?php echo e($monthNumber); ?>"><?php echo e($monthLabel); ?></button>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                        <select class="ar-add-dd-native" id="arAddMonth" required tabindex="-1" aria-hidden="true">
                            <option value="" selected disabled hidden>Select Month</option>
                            <?php $__currentLoopData = $monthNames; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $monthNumber => $monthLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($monthNumber); ?>"><?php echo e($monthLabel); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
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
            <button type="button" class="btn primary" id="arAddSubmit">Add Records</button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if($canDelete): ?>
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
            <?php echo csrf_field(); ?>
            <?php echo method_field('DELETE'); ?>
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
<?php endif; ?>

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
    document.addEventListener('click', (event) => {
        if (monthWrap && !monthWrap.contains(event.target)) closeMonthMenu();
    });
    document.getElementById('arAddModal')?.addEventListener('click', (event) => {
        if (event.target.closest('[data-close="arAddModal"]')) closeMonthMenu();
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
        formData.append('campaign_id', addCampaign.value);
        formData.append('year', addYear.value);
        formData.append('month', addMonth.value);
        selectedAudio.forEach((file) => formData.append('files[]', file));
        addSubmit.disabled = true;
        try {
            const response = await fetch(<?php echo json_encode(route('archive-recordings.import.audio'), 15, 512) ?>, {
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
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\OmniChannel\OmniChannel_Inventory_Production_Updated\resources\views/archive-recordings/index.blade.php ENDPATH**/ ?>