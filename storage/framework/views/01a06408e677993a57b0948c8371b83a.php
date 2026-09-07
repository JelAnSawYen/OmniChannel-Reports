<?php $__env->startSection('content'); ?>
<div class="page-head">
    <div>
        <h1 class="page-title">Program Location</h1>
        <p class="page-subtitle">GSM gateway inventory grouped by program location.</p>
    </div>
</div>

<form class="loc-filters" id="programLocationFilters" method="GET" action="<?php echo e(route('program-location')); ?>">
    <div class="search-box">
        <span class="search-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
        </span>
        <input name="search" value="<?php echo e($search); ?>" placeholder="Search location or code..." aria-label="Search location or code" autocomplete="off">
        <button type="button" class="search-clear" data-clear-search aria-label="Clear search" title="Clear search">×</button>
    </div>
    <label class="loc-filter">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.5 5h17l-6.6 7.8V19l-3.8-2v-4.2L3.5 5z"></path></svg>
        <select name="status" aria-label="Filter by gateway status" onchange="this.form.submit()">
            <option value="">All Gateway Status</option>
            <option value="active" <?php echo e($status === 'active' ? 'selected' : ''); ?>>Active</option>
            <option value="none" <?php echo e($status === 'none' ? 'selected' : ''); ?>>None</option>
        </select>
    </label>
    <label class="loc-filter">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3.5 5h17l-6.6 7.8V19l-3.8-2v-4.2L3.5 5z"></path></svg>
        <select name="location" aria-label="Filter by location" onchange="this.form.submit()">
            <option value="">All Locations</option>
            <?php $__currentLoopData = $locationOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slug => $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($slug); ?>" <?php echo e($locationFilter === $slug ? 'selected' : ''); ?>><?php echo e($name); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
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
<?php $__empty_1 = true; $__currentLoopData = $locations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $location): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<tr>
    <td><?php echo e(($locations->firstItem() ?? 1) + $loop->index); ?></td>
    <td>
        <span class="loc-cell">
            <span><?php echo e($location['name']); ?></span>
        </span>
    </td>
    <td><?php echo e($location['code']); ?></td>
    <td class="num-col loc-records-col"><span class="num-align" data-label="GSM Gateway Records"><?php echo e($location['gateways']); ?></span></td>
    <td>
        <span class="loc-badge <?php echo e($location['gateways'] > 0 ? 'on' : 'off'); ?>">
            <span class="loc-badge-dot" aria-hidden="true"></span>
            <?php echo e($location['gateways'] > 0 ? $location['gateways'].' Active' : '0 None'); ?>

        </span>
    </td>
    <td><?php echo e($location['updated_at'] ? \Illuminate\Support\Carbon::parse($location['updated_at'])->format('M d, Y h:i A') : '—'); ?></td>
    <td class="actions-column">
        <div class="row-actions">
            <?php $manageActive = ($locationFilter ?? '') === $location['slug']; ?>
            <a class="loc-manage<?php echo e($manageActive ? ' active' : ''); ?>" href="<?php echo e(route('program-location.show', $location['slug'])); ?>" title="Manage <?php echo e($location['name']); ?>" <?php if($manageActive): ?> aria-current="page" <?php endif; ?>>Manage</a>
        </div>
    </td>
</tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
<tr><td colspan="7"><div class="empty-state">No program locations found.</div></td></tr>
<?php endif; ?>
</tbody>
</table>
<div class="table-footer">
    <span>Showing <?php echo e($locations->firstItem() ?? 0); ?> to <?php echo e($locations->lastItem() ?? 0); ?> of <?php echo e($locations->total()); ?> entries</span>
    <div class="footer-right">
        <div class="pager">
            <?php if($locations->onFirstPage()): ?>
                <span class="page-number disabled">‹</span>
            <?php else: ?>
                <a class="page-number" href="<?php echo e($locations->previousPageUrl()); ?>">‹</a>
            <?php endif; ?>
            <?php for($page = 1; $page <= max($locations->lastPage(), 1); $page++): ?>
                <?php if($page === $locations->currentPage()): ?>
                    <span class="page-number active"><?php echo e($page); ?></span>
                <?php else: ?>
                    <a class="page-number" href="<?php echo e($locations->url($page)); ?>"><?php echo e($page); ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if($locations->hasMorePages()): ?>
                <a class="page-number" href="<?php echo e($locations->nextPageUrl()); ?>">›</a>
            <?php else: ?>
                <span class="page-number disabled">›</span>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\OmniChannel\OmniChannel_Inventory_Production_Updated\resources\views/locations/index.blade.php ENDPATH**/ ?>