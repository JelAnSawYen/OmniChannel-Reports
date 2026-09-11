<?php
    $query = array_merge(['tab' => $tab], request()->except(['page']));
    $exportQuery = array_filter($query, fn ($value) => $value !== null && $value !== '');
    $canExport = auth()->user()->hasPermission('media.export');
    $icons = [
        'stack' => '<rect x="4" y="14" width="16" height="5" rx="1.2"/><rect x="4" y="8.5" width="16" height="5" rx="1.2"/><rect x="4" y="3" width="16" height="5" rx="1.2"/>',
        'sip' => '<path d="M20 16.9v2.3a1.7 1.7 0 0 1-1.9 1.7 16.8 16.8 0 0 1-7.3-2.6 16.5 16.5 0 0 1-5.1-5.1A16.8 16.8 0 0 1 3.1 5.9 1.7 1.7 0 0 1 4.8 4h2.3a1.7 1.7 0 0 1 1.7 1.5c.1.8.3 1.6.6 2.3a1.7 1.7 0 0 1-.4 1.8l-1 1a13.5 13.5 0 0 0 5.1 5.1l1-1a1.7 1.7 0 0 1 1.8-.4c.7.3 1.5.5 2.3.6A1.7 1.7 0 0 1 20 16.9z"/>',
        'gsm' => '<path d="M5 18V8"/><path d="M10 18V5"/><path d="M15 18v-8"/><path d="M20 18V3"/>',
    ];
?>
<?php $__env->startSection('content'); ?>
<div class="page-head rpt-head">
    <div>
        <h1 class="page-title">Reports</h1>
        <p class="page-subtitle">Generate and export operational reports from the system.</p>
    </div>
</div>

<div class="rpt-tabs" role="tablist" aria-label="Report types">
    <?php $__currentLoopData = $tabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a class="rpt-tab <?php echo e($tab === $key ? 'active' : ''); ?>" href="<?php echo e(route('reports', ['tab' => $key])); ?>" role="tab" aria-selected="<?php echo e($tab === $key ? 'true' : 'false'); ?>"><?php echo e($label); ?></a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>

<section class="rpt-filters" aria-label="Filters">
    <h2>Filters</h2>
    <form method="GET" action="<?php echo e(route('reports')); ?>" class="rpt-filter-form">
        <input type="hidden" name="tab" value="<?php echo e($tab); ?>">
        <?php if(in_array('date_range', $visible_filters, true)): ?>
            <label class="rpt-field">
                <span>Date Range</span>
                <span class="rpt-dates">
                    <input class="form-control" type="date" name="date_from" value="<?php echo e($filters['date_from']); ?>" min="<?php echo e(\App\Support\PdcEndorseDate::MIN_DATE); ?>" aria-label="From date">
                    <span class="rpt-dates-sep">–</span>
                    <input class="form-control" type="date" name="date_to" value="<?php echo e($filters['date_to']); ?>" min="<?php echo e(\App\Support\PdcEndorseDate::MIN_DATE); ?>" aria-label="To date">
                </span>
            </label>
        <?php endif; ?>
        <?php if(in_array('campaign', $visible_filters, true)): ?>
            <label class="rpt-field">
                <span>Campaign</span>
                <select class="form-control" name="campaign_id">
                    <option value="">All Campaigns</option>
                    <?php $__currentLoopData = $filter_options['campaigns']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $campaign): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($campaign->id); ?>" <?php echo e((int) $filters['campaign_id'] === (int) $campaign->id ? 'selected' : ''); ?>><?php echo e($campaign->name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </label>
        <?php endif; ?>
        <?php if(in_array('channel_type', $visible_filters, true)): ?>
            <label class="rpt-field">
                <span>Channel Type</span>
                <select class="form-control" name="channel_type">
                    <option value="all" <?php echo e($filters['channel_type'] === 'all' ? 'selected' : ''); ?>>All</option>
                    <option value="sip" <?php echo e($filters['channel_type'] === 'sip' ? 'selected' : ''); ?>>SIP</option>
                    <option value="gsm" <?php echo e($filters['channel_type'] === 'gsm' ? 'selected' : ''); ?>>GSM</option>
                </select>
            </label>
        <?php endif; ?>
        <?php if(in_array('gsm_gateway', $visible_filters, true)): ?>
            <label class="rpt-field">
                <span>GSM Gateway</span>
                <select class="form-control" name="gateway_id">
                    <option value="">All</option>
                    <?php $__currentLoopData = $filter_options['gateways']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gateway): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($gateway->id); ?>" <?php echo e((int) $filters['gateway_id'] === (int) $gateway->id ? 'selected' : ''); ?>><?php echo e($gateway->site_name); ?> (<?php echo e($gateway->site_code); ?>)</option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </label>
        <?php endif; ?>
        <?php if(in_array('location', $visible_filters, true)): ?>
            <label class="rpt-field">
                <span>Location</span>
                <select class="form-control" name="location">
                    <option value="">All</option>
                    <?php $__currentLoopData = $filter_options['locations']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $location): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($location); ?>" <?php echo e($filters['location'] === $location ? 'selected' : ''); ?>><?php echo e($location); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </label>
        <?php endif; ?>
        <?php if(in_array('status', $visible_filters, true)): ?>
            <label class="rpt-field">
                <span>Status</span>
                <select class="form-control" name="status">
                    <option value="">All</option>
                    <?php $__currentLoopData = $filter_options['statuses']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($status); ?>" <?php echo e($filters['status'] === $status ? 'selected' : ''); ?>><?php echo e($status); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </label>
        <?php endif; ?>
        <div class="rpt-filter-actions">
            <button class="btn primary" type="submit">
                <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
                Apply
            </button>
        </div>
    </form>
</section>

<section class="rpt-report" aria-labelledby="reportTitle">
    <div class="rpt-report-head">
        <div>
            <h2 id="reportTitle"><?php echo e($title); ?></h2>
            <p><?php echo e($description); ?></p>
        </div>
        <div class="rpt-report-meta">
            <span>Generated on <?php echo e($generated_at->format('M d, Y g:i A')); ?></span>
            <span class="rpt-meta-sep">|</span>
            <span>By <?php echo e($generated_by); ?></span>
            <?php if($canExport): ?>
            <div class="transfer rpt-export">
                <button class="btn rpt-export-btn" type="button" id="reportExportButton" aria-haspopup="true" aria-expanded="false" aria-controls="reportExportMenu">
                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4v12"></path><path d="m7 11 5 5 5-5"></path><path d="M5 20h14"></path></svg>
                    Export
                    <span aria-hidden="true">▾</span>
                </button>
                <div class="transfer-menu" id="reportExportMenu" role="menu">
                    <a role="menuitem" href="<?php echo e(route('reports.export', array_merge($exportQuery, ['format' => 'xlsx']))); ?>">Excel (.xlsx)</a>
                    <a role="menuitem" href="<?php echo e(route('reports.export', array_merge($exportQuery, ['format' => 'pdf']))); ?>">PDF (.pdf)</a>
                    <a role="menuitem" href="<?php echo e(route('reports.export', array_merge($exportQuery, ['format' => 'csv']))); ?>">CSV (.csv)</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if(count($summary)): ?>
    <div class="rpt-summary rpt-summary-<?php echo e(count($summary)); ?>">
        <?php $__currentLoopData = $summary; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $card): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <article class="rpt-kpi tone-<?php echo e($card['tone']); ?>">
                <span class="rpt-kpi-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?php echo $icons[$card['icon']] ?? $icons['stack']; ?></svg>
                </span>
                <div>
                    <div class="rpt-kpi-label"><?php echo e($card['label']); ?></div>
                    <div class="rpt-kpi-value"><?php echo e($card['value']); ?></div>
                    <?php if(!empty($card['note'])): ?><div class="rpt-kpi-note"><?php echo e($card['note']); ?></div><?php endif; ?>
                </div>
            </article>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
    <?php endif; ?>

    <div class="rpt-table-wrap">
        <table class="rpt-table" aria-label="<?php echo e($title); ?>">
            <thead>
                <tr>
                    <?php $__currentLoopData = $headers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $header): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <th><?php echo e($header); ?></th>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tr>
            </thead>
            <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <?php $__currentLoopData = $row; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cell): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <td><?php echo e($cell); ?></td>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="<?php echo e(max(1, count($headers))); ?>" class="empty-state">No records match the selected filters.</td></tr>
            <?php endif; ?>
            <?php if($totals): ?>
                <tr class="rpt-total">
                    <?php $__currentLoopData = $totals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cell): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <td><?php echo e($cell); ?></td>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if($insight): ?>
        <div class="rpt-insight">
            <span class="rpt-insight-ico" aria-hidden="true">★</span>
            <div>
                <strong>Key Insight</strong>
                <p><?php echo e($insight); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <?php if($paginator): ?>
    <div class="table-footer rpt-footer">
        <span>Showing <?php echo e($paginator->firstItem() ?? 0); ?> to <?php echo e($paginator->lastItem() ?? 0); ?> of <?php echo e($paginator->total()); ?> entries</span>
        <div class="footer-right">
            <span>Rows per page</span>
            <select class="per-page-select" onchange="location.href=this.value" aria-label="Rows per page">
                <?php $__currentLoopData = [5, 10, 25, 50]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $size): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e(request()->fullUrlWithQuery(['per_page' => $size, 'page' => 1])); ?>" <?php echo e($per_page === $size ? 'selected' : ''); ?>><?php echo e($size); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
            <div class="pager">
                <?php if($paginator->onFirstPage()): ?>
                    <span class="page-number disabled">‹</span>
                <?php else: ?>
                    <a class="page-number" href="<?php echo e($paginator->previousPageUrl()); ?>">‹</a>
                <?php endif; ?>
                <?php for($page = 1; $page <= max($paginator->lastPage(), 1); $page++): ?>
                    <?php if($page === $paginator->currentPage()): ?>
                        <span class="page-number active"><?php echo e($page); ?></span>
                    <?php else: ?>
                        <a class="page-number" href="<?php echo e($paginator->url($page)); ?>"><?php echo e($page); ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if($paginator->hasMorePages()): ?>
                    <a class="page-number" href="<?php echo e($paginator->nextPageUrl()); ?>">›</a>
                <?php else: ?>
                    <span class="page-number disabled">›</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\OmniChannel\OmniChannel_Inventory_Production_Updated\resources\views/reports/index.blade.php ENDPATH**/ ?>