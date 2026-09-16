<?php $__env->startSection('content'); ?>
<div class="page-head">
    <div>
        <h1 class="page-title">Campaigns</h1>
        <p class="page-subtitle">Manage campaign names, FTE, and program locations.</p>
    </div>
    <div class="toolbar">
        <form class="search-box media-search-form" id="campaignSearchForm" method="GET" action="<?php echo e(route('campaigns')); ?>">
            <span class="search-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
            </span>
            <input id="campaignSearchInput" name="search" value="<?php echo e($search); ?>" placeholder="Search Campaigns" aria-label="Search Campaigns" autocomplete="off">
            <?php if(request('per_page')): ?><input type="hidden" name="per_page" value="<?php echo e(request('per_page')); ?>"><?php endif; ?>
        </form>
        <button class="btn primary" type="submit" form="campaignSearchForm">Search</button>
        <?php if(auth()->user()->hasPermission('media.export')): ?>
        <?php echo $__env->make('partials.data-transfer', [
            'canExport' => true,
            'canImport' => auth()->user()->hasPermission('media.create'),
            'exportUrl' => route('campaigns.export', request()->query()),
            'templateUrl' => auth()->user()->hasPermission('media.create') ? route('campaigns.import.template') : '',
            'previewUrl' => auth()->user()->hasPermission('media.create') ? route('campaigns.import.preview') : '',
            'confirmUrl' => auth()->user()->hasPermission('media.create') ? route('campaigns.import.confirm') : '',
            'errorsUrl' => auth()->user()->hasPermission('media.create') ? route('campaigns.import.errors') : '',
            'previewHeaders' => array_values(\App\Support\InventoryImportCatalog::campaigns()['fields']),
            'entityTitle' => 'Campaigns',
        ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?>
        <?php if(auth()->user()->hasPermission('media.create')): ?>
            <button class="plus-btn" type="button" id="campaignAddButton" aria-label="Add Campaign" title="Add Campaign">+</button>
        <?php endif; ?>
    </div>
</div>

<div class="table-card table-wrap">
<table class="campaigns-table" aria-label="Campaigns">
<thead>
<tr>
    <th>Id</th>
    <th>Campaign</th>
    <th>FTE</th>
    <th>Location</th>
    <th class="actions-column">Actions</th>
</tr>
</thead>
<tbody>
<?php $__empty_1 = true; $__currentLoopData = $records; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $record): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<?php
    $editValues = [
        'name' => $record->name,
        'fte' => $record->fte,
        'location' => $record->location,
    ];
?>
<tr>
    <td><?php echo e(($records->firstItem() ?? 1) + $loop->index); ?></td>
    <td><span class="campaigns-name"><?php echo e($record->name); ?></span></td>
    <td><?php echo e($record->fte !== null ? $record->fte : '—'); ?></td>
    <td><?php echo e($record->location ?: '—'); ?></td>
    <td class="actions-column">
        <div class="row-actions">
            <?php if(auth()->user()->hasPermission('media.edit')): ?>
                <button class="action-btn edit" type="button" data-campaign-edit data-id="<?php echo e($record->id); ?>" data-values='<?php echo json_encode($editValues, 15, 512) ?>' title="Edit Campaign" aria-label="Edit Campaign">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                </button>
            <?php endif; ?>
            <?php if(auth()->user()->hasPermission('media.delete')): ?>
                <form method="POST" action="<?php echo e(route('campaigns.destroy', $record)); ?>" data-confirm="Delete this campaign?" data-confirm-title="Delete Campaign" data-confirm-ok="Delete">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('DELETE'); ?>
                    <button class="action-btn delete" type="submit" title="Delete Campaign" aria-label="Delete Campaign">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </td>
</tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
<tr><td colspan="5"><div class="empty-state">No Campaigns Found</div></td></tr>
<?php endif; ?>
</tbody>
</table>
<div class="table-footer">
    <span>Showing <?php echo e($records->firstItem() ?? 0); ?> to <?php echo e($records->lastItem() ?? 0); ?> of <?php echo e($records->total()); ?> entries</span>
    <div class="footer-right">
        <span>Records per page:</span>
        <select class="per-page-select" onchange="location.href=this.value" aria-label="Records per page">
            <?php $__currentLoopData = [5,10,25,50]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $size): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e(request()->fullUrlWithQuery(['per_page'=>$size,'page'=>1])); ?>" <?php echo e($perPage===$size?'selected':''); ?>><?php echo e($size); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <div class="pager">
            <?php for($page=1;$page<=$records->lastPage();$page++): ?>
                <?php if($page===$records->currentPage()): ?>
                    <span class="page-number active"><?php echo e($page); ?></span>
                <?php else: ?>
                    <a class="page-number" href="<?php echo e($records->url($page)); ?>"><?php echo e($page); ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    </div>
</div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('modals'); ?>
<?php if(auth()->user()->hasPermission('media.create') || auth()->user()->hasPermission('media.edit')): ?>
<div class="modal-backdrop" id="campaignModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="campaignModalTitle">Add Campaign</h3>
            <button type="button" class="close-btn" data-close="campaignModal">×</button>
        </div>
        <form id="campaignForm" method="POST" action="<?php echo e(route('campaigns.store')); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="_method" id="campaignMethod" value="POST">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group full">
                        <label for="campaign_name">Campaigns</label>
                        <input class="form-control" id="campaign_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="campaign_fte">FTE</label>
                        <input class="form-control" id="campaign_fte" name="fte" type="number" min="0" step="1" required>
                    </div>
                    <div class="form-group">
                        <label for="campaign_location">Location</label>
                        <select class="form-control" id="campaign_location" name="location" required>
                            <option value="" selected hidden>Select Location</option>
                            <?php $__currentLoopData = $locations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slug => $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($name); ?>"><?php echo e($name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn secondary" data-close="campaignModal">Cancel</button>
                <button type="submit" class="btn primary">Save</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('campaignModal');
    const form = document.getElementById('campaignForm');
    const method = document.getElementById('campaignMethod');
    const storeAction = <?php echo json_encode(route('campaigns.store'), 15, 512) ?>;
    const editBase = <?php echo json_encode(url('/campaigns'), 15, 512) ?>;

    document.getElementById('campaignAddButton')?.addEventListener('click', () => {
        if (!form || !method) return;
        method.value = 'POST';
        form.action = storeAction;
        document.getElementById('campaignModalTitle').textContent = 'Add Campaign';
        form.reset();
        modal?.classList.add('visible');
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-campaign-edit]');
        if (!button || !form || !method) return;
        const recordId = Number(button.dataset.id);
        if (!Number.isInteger(recordId) || recordId < 1) return;
        const values = JSON.parse(button.dataset.values || '{}');
        document.getElementById('campaignModalTitle').textContent = 'Edit Campaign';
        method.value = 'PUT';
        form.action = editBase + '/' + recordId;
        document.getElementById('campaign_name').value = values.name ?? '';
        document.getElementById('campaign_fte').value = values.fte ?? '';
        document.getElementById('campaign_location').value = values.location ?? '';
        modal?.classList.add('visible');
    });
});
</script>
<?php echo $__env->make('partials.inventory-import-script', [
    'previewUrl' => auth()->user()->hasPermission('media.create') ? route('campaigns.import.preview') : '',
    'confirmUrl' => auth()->user()->hasPermission('media.create') ? route('campaigns.import.confirm') : '',
    'errorsUrl' => auth()->user()->hasPermission('media.create') ? route('campaigns.import.errors') : '',
    'previewFields' => ['name', 'fte', 'location'],
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\OmniChannel\OmniChannel_Inventory_Production_Updated\resources\views/campaigns/index.blade.php ENDPATH**/ ?>