<?php $__env->startSection('content'); ?>
<?php
    $canCreate = auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways();
    $canEdit = auth()->user()->hasPermission('media.edit') && auth()->user()->canMutateGateways();
    $canDelete = auth()->user()->hasPermission('media.delete') && auth()->user()->canMutateGateways();
?>
<div class="page-head">
    <div>
        <h1 class="page-title"><?php echo e($locationName); ?></h1>
        <p class="page-subtitle">Manage GSM gateway records assigned to <?php echo e($locationName); ?>.</p>
    </div>
    <div class="toolbar">
        <form class="search-box media-search-form" id="locationSearchForm" method="GET" action="<?php echo e(route('program-location.show', $locationSlug)); ?>">
            <span class="search-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
            </span>
            <input id="locationSearchInput" name="search" value="<?php echo e($search); ?>" placeholder="Search <?php echo e($locationName); ?>" aria-label="Search <?php echo e($locationName); ?>" autocomplete="off">
            <?php if(request('per_page')): ?><input type="hidden" name="per_page" value="<?php echo e(request('per_page')); ?>"><?php endif; ?>
        </form>
        <button class="btn primary" type="submit" form="locationSearchForm">Search</button>
        <a class="btn secondary" href="<?php echo e(route('program-location')); ?>">All Locations</a>
        <?php if(auth()->user()->hasPermission('media.export')): ?>
        <?php echo $__env->make('partials.data-transfer', [
            'canExport' => true,
            'canImport' => $canCreate,
            'exportUrl' => route('program-location.export', array_merge(['location' => $locationSlug], request()->query())),
            'templateUrl' => $canCreate ? route('program-location.import.template', $locationSlug) : '',
            'previewUrl' => $canCreate ? route('program-location.import.preview', $locationSlug) : '',
            'confirmUrl' => $canCreate ? route('program-location.import.confirm', $locationSlug) : '',
            'errorsUrl' => $canCreate ? route('program-location.import.errors', $locationSlug) : '',
            'previewHeaders' => array_values($columns),
            'entityTitle' => $locationName.' GSM Gateways',
        ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?>
        <?php if($canCreate): ?>
            <button class="plus-btn" type="button" id="locationAddButton" aria-label="Add GSM Gateway" title="Add GSM Gateway">+</button>
        <?php endif; ?>
    </div>
</div>

<div class="table-card table-wrap">
<table aria-label="<?php echo e($locationName); ?> GSM gateways">
<thead>
<tr>
    <th>Id</th>
    <?php $__currentLoopData = $columns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <th><?php echo e($label); ?></th>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <th>Last Updated</th>
    <th class="actions-column">Actions</th>
</tr>
</thead>
<tbody>
<?php $__empty_1 = true; $__currentLoopData = $records; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $record): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<tr <?php if($canDelete): ?> data-bulk-row="main" data-bulk-id="<?php echo e($record->id); ?>" data-bulk-url="<?php echo e(route('program-location.bulk-destroy', $locationSlug)); ?>" <?php endif; ?>>
    <td><?php echo e(($records->firstItem() ?? 1) + $loop->index); ?></td>
    <?php $__currentLoopData = $columns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <td><?php echo e($record->{$field}); ?></td>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <td><?php echo e($record->updated_at?->format('M d, Y h:i A') ?? '—'); ?></td>
    <td class="actions-column">
        <div class="row-actions">
            <?php if($canEdit): ?>
                <button class="action-btn edit" type="button" data-location-edit data-id="<?php echo e($record->id); ?>" data-values='<?php echo json_encode($record->only(array_keys($columns)), 15, 512) ?>' title="Edit GSM Gateway" aria-label="Edit GSM Gateway">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                </button>
            <?php endif; ?>
            <?php if($canDelete): ?>
                <form method="POST" action="<?php echo e(route('program-location.destroy', ['location' => $locationSlug, 'gateway' => $record->id])); ?>" data-confirm="Delete this GSM Gateway record?" data-confirm-title="Delete GSM Gateway" data-confirm-ok="Delete">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('DELETE'); ?>
                    <button class="action-btn delete" type="submit" title="Delete GSM Gateway" aria-label="Delete GSM Gateway">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </td>
</tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
<tr><td colspan="<?php echo e(count($columns) + 3); ?>"><div class="empty-state">No GSM Gateway records found for <?php echo e($locationName); ?>.</div></td></tr>
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
<?php
    $canCreate = auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways();
    $canEdit = auth()->user()->hasPermission('media.edit') && auth()->user()->canMutateGateways();
?>
<?php if($canCreate || $canEdit): ?>
<div class="modal-backdrop" id="locationModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="locationModalTitle">Add GSM Gateway</h3>
            <button type="button" class="close-btn" data-close="locationModal">×</button>
        </div>
        <form id="locationForm" method="POST" action="<?php echo e(route('program-location.store', $locationSlug)); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="_method" id="locationMethod" value="POST">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group full">
                        <label for="field_site_name">Site Name</label>
                        <select class="form-control" name="site_name" id="field_site_name">
                            <?php $__currentLoopData = $locations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slug => $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($name); ?>" <?php echo e($name === $locationName ? 'selected' : ''); ?>><?php echo e($name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div class="form-group"><label for="field_site_code">Site Code</label><input class="form-control" name="site_code" id="field_site_code" required></div>
                    <div class="form-group"><label for="field_ip_address">IP Address</label><input class="form-control" name="ip_address" id="field_ip_address" required></div>
                    <div class="form-group"><label for="field_username">Username</label><input class="form-control" name="username" id="field_username" required></div>
                    <div class="form-group"><label for="field_database">Database</label><input class="form-control" name="database" id="field_database" required></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn secondary" data-close="locationModal">Cancel</button>
                <button class="btn primary" type="submit">Save</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('locationModal');
    const form = document.getElementById('locationForm');
    const method = document.getElementById('locationMethod');
    const storeAction = <?php echo json_encode(route('program-location.store', $locationSlug), 512) ?>;
    const editBase = <?php echo json_encode(url('/program-location/'.$locationSlug), 15, 512) ?>;
    const defaultSiteName = <?php echo json_encode($locationName, 15, 512) ?>;

    document.getElementById('locationAddButton')?.addEventListener('click', () => {
        if (!form || !method) return;
        method.value = 'POST';
        form.action = storeAction;
        document.getElementById('locationModalTitle').textContent = 'Add GSM Gateway';
        form.reset();
        const siteName = document.getElementById('field_site_name');
        if (siteName) siteName.value = defaultSiteName;
        modal?.classList.add('visible');
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-location-edit]');
        if (!button || !form || !method) return;
        const recordId = Number(button.dataset.id);
        if (!Number.isInteger(recordId) || recordId < 1) return;
        const values = JSON.parse(button.dataset.values || '{}');
        document.getElementById('locationModalTitle').textContent = 'Edit GSM Gateway';
        method.value = 'PUT';
        form.action = editBase + '/' + recordId;
        Object.keys(values).forEach((key) => {
            const field = document.getElementById('field_' + key);
            if (field) field.value = values[key] ?? '';
        });
        modal?.classList.add('visible');
    });
});
</script>
<?php echo $__env->make('partials.inventory-import-script', [
    'previewUrl' => $canCreate ? route('program-location.import.preview', $locationSlug) : '',
    'confirmUrl' => $canCreate ? route('program-location.import.confirm', $locationSlug) : '',
    'errorsUrl' => $canCreate ? route('program-location.import.errors', $locationSlug) : '',
    'previewFields' => array_keys($columns),
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\OmniChannel\OmniChannel_Inventory_Production_Updated\resources\views/program-location/show.blade.php ENDPATH**/ ?>