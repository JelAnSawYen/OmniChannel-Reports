<?php $__env->startSection('content'); ?>
<div class="page-head">
    <div>
        <h1 class="page-title">Activity Logs</h1>
        <p class="page-subtitle">View and manage your activity logs.</p>
    </div>
</div>
<div class="filter-row log-filter-row">
    <form class="search-filter-form log-search-form" method="GET">
        <div class="search-box">
            <span class="search-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg></span>
            <input name="search" value="<?php echo e(request('search')); ?>" placeholder="Search logs..." autocomplete="off">
        </div>
        <select class="select" name="action">
            <option value="" <?php echo e(request('action') ? '' : 'selected'); ?> hidden>All Actions</option>
            <?php $__currentLoopData = ['Created','Added','Updated','Deleted','Imported','Exported','Changed Password','Cleared Logs']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($a); ?>" <?php echo e(request('action')===$a?'selected':''); ?>><?php echo e($a); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <?php if(request('per_page')): ?><input type="hidden" name="per_page" value="<?php echo e(request('per_page')); ?>"><?php endif; ?>
        <button class="btn primary" type="submit">Search</button>
    </form>
    <?php if($canManageLogs): ?>
        <form class="log-clear-form" method="POST" action="<?php echo e(route('activity-logs.clear-older')); ?>" data-confirm="Clear all activity logs older than <?php echo e(\App\Services\Logs\LogRetentionService::DAYS); ?> days? This cannot be undone." data-confirm-title="Clear Logs Older Than <?php echo e(\App\Services\Logs\LogRetentionService::DAYS); ?> Days" data-confirm-ok="Clear">
            <?php echo csrf_field(); ?>
            <?php echo method_field('DELETE'); ?>
            <button class="btn danger-outline" type="submit">Clear Logs Older Than <?php echo e(\App\Services\Logs\LogRetentionService::DAYS); ?> Days</button>
        </form>
    <?php endif; ?>
</div>
<div class="table-card table-wrap activity-log-card">
    <table class="activity-log-table">
        <thead>
            <tr>
                <th>Date/Time</th>
                <th>User</th>
                <th>Email</th>
                <th>Action</th>
                <th>Module</th>
                <th>Description</th>
                <th>IP Address</th>
            </tr>
        </thead>
        <tbody>
        <?php $__empty_1 = true; $__currentLoopData = $logs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr>
                <td>
                    <span class="log-datetime">
                        <strong><?php echo e($log->created_at->format('M d, Y')); ?></strong>
                        <span><?php echo e($log->created_at->format('h:i A')); ?></span>
                    </span>
                </td>
                <td><?php echo e($log->user?->name ?? 'System'); ?></td>
                <td><?php echo e($log->user?->email ?? '—'); ?></td>
                <td><span class="badge"><?php echo e($log->action); ?></span></td>
                <td><?php echo e($log->module); ?></td>
                <td class="activity-description"><?php echo e($log->description); ?></td>
                <td><?php echo e($log->ip_address ?? '—'); ?></td>
            </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="7" class="empty-state">No activity found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <div class="table-footer">
        <span>Showing <?php echo e($logs->firstItem() ?? 0); ?> to <?php echo e($logs->lastItem() ?? 0); ?> of <?php echo e($logs->total()); ?> logs</span>
        <div class="footer-right">
            <span>Records per page:</span>
            <select class="per-page-select" onchange="location.href=this.value" aria-label="Records per page">
                <?php $__currentLoopData = [5,10,25,50]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $size): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e(request()->fullUrlWithQuery(['per_page'=>$size,'page'=>1])); ?>" <?php echo e($perPage===$size?'selected':''); ?>><?php echo e($size); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <?php echo $__env->make('partials.table-pager', ['paginator' => $logs], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\OmniChannel\OmniChannel_Inventory_Production_Updated\resources\views/audit-logs/index.blade.php ENDPATH**/ ?>