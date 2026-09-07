<?php $__env->startSection('content'); ?>
<div class="page-head">
    <div>
        <h1 class="page-title">Operations Reports</h1>
        <p class="page-subtitle">Inventory, capacity, and telco cost summaries for daily operations.</p>
    </div>
</div>

<div class="dashboard-grid">
    <div class="stat-card"><div><div class="stat-label">Media Gateways</div><div class="stat-value"><?php echo e($gatewayTotal); ?></div><div class="stat-note">Active inventory records</div></div><div class="stat-icon">▣</div></div>
    <div class="stat-card"><div><div class="stat-label">Port Utilization</div><div class="stat-value"><?php echo e($ports['utilization']); ?>%</div><div class="stat-note"><?php echo e($ports['in_use']); ?> in use / <?php echo e($ports['total']); ?> ports</div></div><div class="stat-icon">▣</div></div>
    <div class="stat-card"><div><div class="stat-label">Active Monthly Cost</div><div class="stat-value">₱<?php echo e(number_format($activeMonthlyTelco,2)); ?></div><div class="stat-note">Active telco contracts</div></div><div class="stat-icon">$</div></div>
    <div class="stat-card"><div><div class="stat-label">Expiring Contracts</div><div class="stat-value"><?php echo e($expiringContracts->count()); ?></div><div class="stat-note">Next 30 days</div></div><div class="stat-icon">☰</div></div>
</div>

<div class="dashboard-two">
    <div class="panel">
        <div class="panel-head"><h3>Inventory Snapshot</h3><?php if(auth()->user()->hasPermission('media.export')): ?><a class="btn secondary" href="<?php echo e(route('reports.export','inventory')); ?>">Export</a><?php endif; ?></div>
        <div class="panel-body">
            <div class="info-row"><span>Channel Prefixes</span><strong><?php echo e($channelPrefixes); ?></strong></div>
            <div class="info-row"><span>Network Prefixes</span><strong><?php echo e($networkPrefixes); ?></strong></div>
            <div class="info-row"><span>Ports Available</span><strong><?php echo e($ports['available']); ?></strong></div>
            <div class="info-row"><span>Ports In Use</span><strong><?php echo e($ports['in_use']); ?></strong></div>
            <div class="info-row"><span>Ports Disabled</span><strong><?php echo e($ports['disabled']); ?></strong></div>
            <?php ($max=max(1,$ports['total'])); ?>
            <div class="bar-row"><span>In Use</span><div class="bar"><span style="width:<?php echo e(($ports['in_use']/$max)*100); ?>%"></span></div><strong><?php echo e($ports['in_use']); ?></strong></div>
            <div class="bar-row"><span>Available</span><div class="bar"><span style="width:<?php echo e(($ports['available']/$max)*100); ?>%"></span></div><strong><?php echo e($ports['available']); ?></strong></div>
        </div>
    </div>
    <div class="panel">
        <div class="panel-head"><h3>Telco Cost by Provider</h3><?php if(auth()->user()->hasPermission('media.export')): ?><a class="btn secondary" href="<?php echo e(route('reports.export','telco')); ?>">Export</a><?php endif; ?></div>
        <div class="panel-body">
            <?php ($maxCost=max(1,(float)$telcoByProvider->max('monthly_cost'))); ?>
            <?php $__empty_1 = true; $__currentLoopData = $telcoByProvider; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <div class="bar-row"><span><?php echo e($row->provider); ?></span><div class="bar"><span style="width:<?php echo e(((float)$row->monthly_cost/$maxCost)*100); ?>%"></span></div><strong>₱<?php echo e(number_format((float)$row->monthly_cost,2)); ?></strong></div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <p class="muted">No active telco cost records.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="panel" style="margin-top:16px">
    <div class="panel-head"><h3>Contracts Expiring in 30 Days</h3><?php if(auth()->user()->hasPermission('media.export')): ?><a class="btn secondary" href="<?php echo e(route('reports.export','contracts')); ?>">Export</a><?php endif; ?></div>
    <div class="table-wrap"><table><thead><tr><th>Provider</th><th>Site</th><th>Service</th><th>Monthly Cost</th><th>Ends</th><th>Status</th></tr></thead><tbody>
    <?php $__empty_1 = true; $__currentLoopData = $expiringContracts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $contract): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr>
            <td><?php echo e($contract->provider); ?></td>
            <td><?php echo e($contract->site); ?></td>
            <td><?php echo e($contract->service_type); ?></td>
            <td>₱<?php echo e(number_format((float)$contract->monthly_cost,2)); ?></td>
            <td><?php echo e($contract->contract_end?->format('M d, Y')); ?></td>
            <td><span class="status-pill unknown"><?php echo e($contract->status); ?></span></td>
        </tr>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="6" class="empty-state">No contracts expiring in the next 30 days.</td></tr>
    <?php endif; ?>
    </tbody></table></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\OmniChannel\OmniChannel_Inventory_Production_Updated\resources\views/reports/index.blade.php ENDPATH**/ ?>