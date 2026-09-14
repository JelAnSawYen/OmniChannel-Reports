<?php $__env->startSection('content'); ?>
<div class="page-head">
    <div>
        <h1 class="page-title">Channel Utilization</h1>
        <p class="page-subtitle">Breakdown of allocated channels by campaign and channel type.</p>
    </div>
    <div class="toolbar">
        <form class="search-box media-search-form" id="channelUtilizationSearchForm" method="GET" action="<?php echo e(route('channel-utilization')); ?>">
            <span class="search-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
            </span>
            <input id="channelUtilizationSearchInput" name="search" value="<?php echo e($search); ?>" placeholder="Search campaign..." aria-label="Search campaign" autocomplete="off">
            <?php if(request('per_page')): ?><input type="hidden" name="per_page" value="<?php echo e(request('per_page')); ?>"><?php endif; ?>
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
<?php $__empty_1 = true; $__currentLoopData = $records; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<tr>
    <td><?php echo e($row['name']); ?></td>
    <td class="num-col"><?php echo e($row['total_display']); ?></td>
    <td class="num-col"><?php echo e($row['sip_display']); ?></td>
    <td class="num-col"><?php echo e($row['gsm_display']); ?></td>
</tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
<tr><td colspan="4"><div class="empty-state">No campaigns match this search.</div></td></tr>
<?php endif; ?>
<?php if($records->total() > 0): ?>
<tr class="dash-total-row">
    <td>Total</td>
    <td class="num-col"><?php echo e(number_format($totals['total'])); ?></td>
    <td class="num-col"><?php echo e(number_format($totals['sip'])); ?></td>
    <td class="num-col"><?php echo e(number_format($totals['gsm'])); ?></td>
</tr>
<?php endif; ?>
</tbody>
</table>
<div class="table-footer">
    <span>Showing <?php echo e($records->firstItem() ?? 0); ?> to <?php echo e($records->lastItem() ?? 0); ?> of <?php echo e($records->total()); ?> entries</span>
    <div class="footer-right">
        <span>Records per page:</span>
        <select class="per-page-select" onchange="location.href=this.value" aria-label="Records per page">
            <?php $__currentLoopData = [5, 10, 25, 50]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $size): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e(request()->fullUrlWithQuery(['per_page' => $size, 'page' => 1])); ?>" <?php echo e($perPage === $size ? 'selected' : ''); ?>><?php echo e($size); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <div class="pager">
            <?php for($page = 1; $page <= max($records->lastPage(), 1); $page++): ?>
                <?php if($page === $records->currentPage()): ?>
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

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\OmniChannel\OmniChannel_Inventory_Production_Updated\resources\views/channel-utilization/index.blade.php ENDPATH**/ ?>