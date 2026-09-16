<?php $__env->startSection('content'); ?>
<div class="page-head">
    <div>
        <h1 class="page-title">Channel Range List</h1>
        <p class="page-subtitle">Manage channel numbers for each SIP channel.</p>
    </div>
    <div class="toolbar">
        <form class="search-box media-search-form" id="crlSearchForm" method="GET" action="<?php echo e(route('channel-range-list')); ?>">
            <span class="search-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
            </span>
            <input id="crlSearchInput" name="search" value="<?php echo e($search); ?>" placeholder="Search Channel Range List" aria-label="Search Channel Range List" autocomplete="off">
            <?php if(request('per_page')): ?><input type="hidden" name="per_page" value="<?php echo e(request('per_page')); ?>"><?php endif; ?>
        </form>
        <button class="btn primary" type="submit" form="crlSearchForm">Search</button>
        <?php if(auth()->user()->hasPermission('media.export')): ?>
        <?php echo $__env->make('partials.data-transfer', [
            'canExport' => true,
            'canImport' => auth()->user()->hasPermission('media.create'),
            'exportUrl' => route('channel-range-list.export', request()->query()),
            'templateUrl' => auth()->user()->hasPermission('media.create') ? route('channel-range-list.import.template') : '',
            'previewUrl' => auth()->user()->hasPermission('media.create') ? route('channel-range-list.import.preview') : '',
            'confirmUrl' => auth()->user()->hasPermission('media.create') ? route('channel-range-list.import.confirm') : '',
            'errorsUrl' => auth()->user()->hasPermission('media.create') ? route('channel-range-list.import.errors') : '',
            'previewHeaders' => array_values(app(\App\Services\ChannelRangeListImportService::class)->fields()),
            'entityTitle' => 'Channel Range List',
        ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?>
        <?php if(auth()->user()->hasPermission('media.create')): ?>
            <button class="plus-btn" type="button" id="crlAddButton" aria-label="Add" title="Add">+</button>
        <?php endif; ?>
    </div>
</div>

<div class="table-card table-wrap">
<table class="ca-table crl-table" aria-label="Channel Range List">
<colgroup>
    <col class="crl-col-toggle">
    <col class="crl-col-campaign">
    <col class="crl-col-channel">
    <col class="crl-col-sip">
    <col class="crl-col-actions">
</colgroup>
<thead>
<tr>
    <th class="crl-toggle-col">
        <span class="ca-toggle" aria-hidden="true"></span>
    </th>
    <th class="crl-campaign-col">Campaign</th>
    <th class="crl-channel-col" aria-hidden="true"></th>
    <th class="crl-sip-col">SIP Name</th>
    <th class="crl-actions-col" aria-hidden="true"></th>
</tr>
</thead>
<tbody>
<?php $__empty_1 = true; $__currentLoopData = $groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sip): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<?php
    $campaignName = $sip->campaign?->name ?: '—';
    $sipName = $sip->etpi_sip_name ?: '—';
?>
<tr class="ca-campaign-row" data-campaign="<?php echo e($sip->id); ?>">
    <td class="crl-toggle-col">
        <button type="button" class="ca-toggle" data-ca-toggle="<?php echo e($sip->id); ?>" aria-expanded="false" aria-controls="crl-panel-<?php echo e($sip->id); ?>" title="Expand <?php echo e($campaignName); ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 6 6 6-6 6"></path></svg>
        </button>
    </td>
    <td class="crl-campaign-col">
        <span class="ca-campaign-identity">
            <button type="button" class="ca-campaign-link" data-ca-toggle="<?php echo e($sip->id); ?>"><?php echo e($campaignName); ?></button>
        </span>
    </td>
    <td class="crl-channel-col"></td>
    <td class="crl-sip-col"><span class="crl-sip-name"><?php echo e($sipName); ?></span></td>
    <td class="crl-actions-col"></td>
</tr>
<tr class="ca-nested-row" id="crl-panel-<?php echo e($sip->id); ?>" hidden>
    <td colspan="5">
        <div class="ca-nested">
            <table class="crl-nested" aria-label="<?php echo e($campaignName); ?> channel numbers">
                <colgroup>
                    <col class="crl-col-toggle">
                    <col class="crl-col-campaign">
                    <col class="crl-col-channel">
                    <col class="crl-col-sip">
                    <col class="crl-col-actions">
                </colgroup>
                <thead>
                    <tr>
                        <th class="crl-toggle-col"></th>
                        <th class="crl-campaign-col"></th>
                        <th class="crl-channel-col">Channel Number</th>
                        <th class="crl-sip-col"></th>
                        <th class="crl-actions-col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php $__empty_2 = true; $__currentLoopData = $sip->channelNumbers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $number): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
                    <tr>
                        <td class="crl-toggle-col"></td>
                        <td class="crl-campaign-col"></td>
                        <td class="crl-channel-col"><span class="crl-channel-number"><?php echo e($number->channel_number); ?></span></td>
                        <td class="crl-sip-col"></td>
                        <td class="crl-actions-col actions-column">
                            <span class="row-actions">
                                <?php if(auth()->user()->hasPermission('media.edit')): ?>
                                    <button class="action-btn edit" type="button" data-crl-edit data-id="<?php echo e($number->id); ?>" data-channel-number="<?php echo e($number->channel_number); ?>" title="Edit" aria-label="Edit">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                                    </button>
                                <?php endif; ?>
                                <?php if(auth()->user()->hasPermission('media.delete')): ?>
                                    <form method="POST" action="<?php echo e(route('channel-range-list.destroy', $number)); ?>" data-confirm="Delete this record?" data-confirm-title="Delete Record" data-confirm-ok="Delete">
                                        <?php echo csrf_field(); ?>
                                        <?php echo method_field('DELETE'); ?>
                                        <button class="action-btn delete" type="submit" title="Delete" aria-label="Delete">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
                    <tr><td colspan="5"><div class="empty-state">No channel numbers for this SIP channel.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </td>
</tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
<tr><td colspan="5"><div class="empty-state">No Channel Range List records found.</div></td></tr>
<?php endif; ?>
</tbody>
</table>
<div class="table-footer">
    <span>Showing <?php echo e($groups->firstItem() ?? 0); ?> to <?php echo e($groups->lastItem() ?? 0); ?> of <?php echo e($groups->total()); ?> entries</span>
    <div class="footer-right">
        <span>Records per page:</span>
        <select class="per-page-select" onchange="location.href=this.value" aria-label="Records per page">
            <?php $__currentLoopData = [5,10,25,50]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $size): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e(request()->fullUrlWithQuery(['per_page'=>$size,'page'=>1])); ?>" <?php echo e($perPage===$size?'selected':''); ?>><?php echo e($size); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <div class="pager">
            <?php if($groups->onFirstPage()): ?>
                <span class="page-number disabled">‹</span>
            <?php else: ?>
                <a class="page-number" href="<?php echo e($groups->previousPageUrl()); ?>">‹</a>
            <?php endif; ?>
            <?php for($page = 1; $page <= max($groups->lastPage(), 1); $page++): ?>
                <?php if($page === $groups->currentPage()): ?>
                    <span class="page-number active"><?php echo e($page); ?></span>
                <?php else: ?>
                    <a class="page-number" href="<?php echo e($groups->url($page)); ?>"><?php echo e($page); ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if($groups->hasMorePages()): ?>
                <a class="page-number" href="<?php echo e($groups->nextPageUrl()); ?>">›</a>
            <?php else: ?>
                <span class="page-number disabled">›</span>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('modals'); ?>
<?php if(auth()->user()->hasPermission('media.create')): ?>
<div class="modal-backdrop" id="crlAddModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="crlAddModalTitle">Add Channel Range List</h3>
            <button type="button" class="close-btn" data-close="crlAddModal">×</button>
        </div>
        <form id="crlAddForm" method="POST" action="<?php echo e(route('channel-range-list.store')); ?>">
            <?php echo csrf_field(); ?>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="crl_sip_channel_id">Campaign</label>
                        <select class="form-control" name="sip_channel_id" id="crl_sip_channel_id" required>
                            <option value="" selected hidden>Select Campaign</option>
                            <?php $__currentLoopData = $sipChannels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $channel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($channel->id); ?>" data-sip-name="<?php echo e($channel->etpi_sip_name); ?>"><?php echo e($channel->campaign?->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="crl_sip_name">SIP Name</label>
                        <input class="form-control" type="text" id="crl_sip_name" readonly disabled tabindex="-1">
                    </div>
                    <div class="form-group">
                        <label for="crl_from">From</label>
                        <input class="form-control" name="from" id="crl_from" inputmode="numeric" autocomplete="off" required>
                    </div>
                    <div class="form-group">
                        <label for="crl_to">To</label>
                        <input class="form-control" name="to" id="crl_to" inputmode="numeric" autocomplete="off" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn secondary" data-close="crlAddModal">Cancel</button>
                <button class="btn primary" type="submit" id="crlAddSubmit">Save</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if(auth()->user()->hasPermission('media.edit')): ?>
<div class="modal-backdrop" id="crlEditModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="crlEditModalTitle">Edit Channel Number</h3>
            <button type="button" class="close-btn" data-close="crlEditModal">×</button>
        </div>
        <form id="crlEditForm" method="POST" action="">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="_method" value="PUT">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="crl_channel_number">Channel Number</label>
                        <input class="form-control" name="channel_number" id="crl_channel_number" inputmode="numeric" autocomplete="off" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn secondary" data-close="crlEditModal">Cancel</button>
                <button class="btn primary" type="submit" id="crlEditSubmit">Save</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) return;
        const button = event.target.closest('[data-ca-toggle]');
        if (!button) return;
        const id = button.getAttribute('data-ca-toggle');
        const panel = document.getElementById('crl-panel-' + id);
        const row = document.querySelector('tr.ca-campaign-row[data-campaign="' + id + '"]');
        if (!panel) return;
        const open = panel.hasAttribute('hidden');
        panel.toggleAttribute('hidden', !open);
        row?.classList.toggle('open', open);
        document.querySelectorAll('[data-ca-toggle="' + id + '"]').forEach((el) => {
            el.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) return;
        const row = event.target.closest('tr.ca-campaign-row[data-campaign]');
        if (!row) return;
        if (event.target.closest('.actions-column, .ca-menu, a, input, select, textarea, label, .action-btn, .plus-btn')) return;
        if (event.target.closest('[data-ca-toggle]')) return;
        row.querySelector('[data-ca-toggle]')?.click();
    });

    const campaignSelect = document.getElementById('crl_sip_channel_id');
    const sipNameInput = document.getElementById('crl_sip_name');
    const syncSipName = () => {
        const option = campaignSelect?.selectedOptions?.[0];
        if (sipNameInput) sipNameInput.value = option?.getAttribute('data-sip-name') || '';
    };
    campaignSelect?.addEventListener('change', syncSipName);

    const addModal = document.getElementById('crlAddModal');
    const addForm = document.getElementById('crlAddForm');
    document.getElementById('crlAddButton')?.addEventListener('click', () => {
        addForm?.reset();
        syncSipName();
        addModal?.classList.add('visible');
    });

    const editModal = document.getElementById('crlEditModal');
    const editForm = document.getElementById('crlEditForm');
    const editBase = <?php echo json_encode(url('/channel-range-list'), 15, 512) ?>;
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-crl-edit]');
        if (!button) return;
        const recordId = Number(button.dataset.id);
        if (!Number.isInteger(recordId) || recordId < 1) return;
        if (editForm) editForm.action = editBase + '/' + recordId;
        const field = document.getElementById('crl_channel_number');
        if (field) field.value = button.dataset.channelNumber || '';
        editModal?.classList.add('visible');
    });

    const restoreExpanded = <?php echo json_encode(session('crl_expanded'), 15, 512) ?>;
    if (restoreExpanded) {
        document.querySelector('[data-ca-toggle="' + restoreExpanded + '"]')?.click();
    }
});
</script>
<?php echo $__env->make('partials.inventory-import-script', [
    'previewUrl' => auth()->user()->hasPermission('media.create') ? route('channel-range-list.import.preview') : '',
    'confirmUrl' => auth()->user()->hasPermission('media.create') ? route('channel-range-list.import.confirm') : '',
    'errorsUrl' => auth()->user()->hasPermission('media.create') ? route('channel-range-list.import.errors') : '',
    'previewFields' => array_keys(app(\App\Services\ChannelRangeListImportService::class)->fields()),
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\OmniChannel\OmniChannel_Inventory_Production_Updated\resources\views/channel-range-list/index.blade.php ENDPATH**/ ?>