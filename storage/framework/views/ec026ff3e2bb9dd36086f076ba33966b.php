<?php $__env->startSection('content'); ?>
<div class="page-head">
    <div>
        <h1 class="page-title">Channel Allocation</h1>
        <p class="page-subtitle">Manage channel allocations by campaign.</p>
    </div>
    <div class="toolbar">
        <form class="search-box media-search-form" id="channelAllocationSearchForm" method="GET" action="<?php echo e(route('channel-allocation')); ?>">
            <span class="search-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
            </span>
            <input id="channelAllocationSearchInput" name="search" value="<?php echo e($search); ?>" placeholder="Search Channels" aria-label="Search Channels" autocomplete="off">
            <?php if(request('per_page')): ?><input type="hidden" name="per_page" value="<?php echo e(request('per_page')); ?>"><?php endif; ?>
        </form>
        <button class="btn primary" type="submit" form="channelAllocationSearchForm">Search</button>
        <?php if(auth()->user()->hasPermission('media.export')): ?>
        <div class="transfer">
            <button class="btn" type="button" id="transferButton" aria-haspopup="true" aria-expanded="false" aria-controls="transferMenu">
                <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4v12"></path><path d="m7 11 5 5 5-5"></path><path d="M5 20h14"></path></svg>
                Data Transfer
            </button>
            <div class="transfer-menu" id="transferMenu" role="menu">
                <button type="button" role="menuitem" id="importDataButton">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20V8"></path><path d="m7 13 5-5 5 5"></path><path d="M5 4h14"></path></svg>
                    Import Data
                </button>
                <a role="menuitem" id="exportDataButton" href="<?php echo e(route('channel-allocation.export', request()->query())); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4v12"></path><path d="m7 11 5 5 5-5"></path><path d="M5 20h14"></path></svg>
                    Export Data
                </a>
            </div>
        </div>
        <?php endif; ?>
        <?php if(auth()->user()->hasPermission('media.create')): ?>
            <button class="plus-btn" type="button" id="campaignAddButton" aria-label="Add Campaign" title="Add Campaign">+</button>
        <?php endif; ?>
    </div>
</div>

<div class="table-card table-wrap">
<table class="ca-table" aria-label="Channel Allocation">
<thead>
<tr>
    <th>
        <span class="ca-campaign-cell">
            <span class="ca-toggle" aria-hidden="true"></span>
            <span class="ca-campaign-identity">Campaign</span>
        </span>
    </th>
    <th class="num-col">Allocations</th>
    <th class="num-col">Total Channels</th>
    <th class="num-col">FTE</th>
    <th>Caller ID</th>
    <th>Prefix</th>
    <th class="ca-remarks">Remarks</th>
    <th class="actions-column">Actions</th>
</tr>
</thead>
<tbody>
<?php $__empty_1 = true; $__currentLoopData = $campaigns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $campaign): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<?php
    $allocCount = $campaign->allocations->count();
    $campaignValues = [
        'id' => $campaign->id,
        'name' => $campaign->name,
        'media_gateway' => $campaign->media_gateway,
        'total_channels_allocated' => $campaign->total_channels_allocated,
        'fte' => $campaign->fte,
        'caller_id' => $campaign->caller_id,
        'prefix' => $campaign->prefix,
        'remarks' => $campaign->remarks,
    ];
?>
<tr class="ca-campaign-row" data-campaign="<?php echo e($campaign->id); ?>">
    <td>
        <span class="ca-campaign-cell">
            <button type="button" class="ca-toggle" data-ca-toggle="<?php echo e($campaign->id); ?>" aria-expanded="false" aria-controls="ca-panel-<?php echo e($campaign->id); ?>" title="Expand <?php echo e($campaign->name); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 6 6 6-6 6"></path></svg>
            </button>
            <span class="ca-campaign-identity">
                <button type="button" class="ca-campaign-link" data-ca-toggle="<?php echo e($campaign->id); ?>"><?php echo e($campaign->name); ?></button>
            </span>
        </span>
    </td>
    <td class="num-col"><span class="num-align" data-label="Allocations"><?php echo e($allocCount); ?></span></td>
    <td class="num-col"><span class="num-align ca-total" data-label="Total Channels"><?php echo e($campaign->total_channels_allocated ?? '—'); ?></span></td>
    <td class="num-col"><span class="num-align" data-label="FTE"><?php echo e($campaign->fte ?? '—'); ?></span></td>
    <td><span class="num-align" data-label="Caller ID"><?php echo e($campaign->caller_id ?: '—'); ?></span></td>
    <td><span class="num-align" data-label="Prefix"><?php echo e($campaign->prefix ?: '—'); ?></span></td>
    <td class="ca-remarks"><span class="num-align" data-label="Remarks"><?php echo e($campaign->remarks ?: '—'); ?></span></td>
    <td class="actions-column">
        <?php if(auth()->user()->hasPermission('media.edit') || auth()->user()->hasPermission('media.delete')): ?>
        <div class="ca-menu">
            <button class="ca-menu-btn" type="button" aria-haspopup="true" aria-expanded="false" aria-label="Campaign actions" title="Campaign actions">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
            </button>
            <div class="ca-menu-dropdown" role="menu" hidden>
                <?php if(auth()->user()->hasPermission('media.edit')): ?>
                    <button class="ca-menu-item edit" type="button" role="menuitem" data-campaign-edit data-id="<?php echo e($campaign->id); ?>" data-values='<?php echo json_encode($campaignValues, 15, 512) ?>'>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                        Edit
                    </button>
                <?php endif; ?>
                <?php if(auth()->user()->hasPermission('media.delete')): ?>
                    <form method="POST" action="<?php echo e(route('channel-allocation.destroy', $campaign)); ?>" data-confirm="Delete this campaign and all of its allocations?" data-confirm-title="Delete Campaign" data-confirm-ok="Delete">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('DELETE'); ?>
                        <button class="ca-menu-item delete" type="submit" role="menuitem">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                            Delete
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </td>
</tr>
<tr class="ca-nested-row" id="ca-panel-<?php echo e($campaign->id); ?>" hidden>
    <td colspan="8">
        <div class="ca-nested">
            <table aria-label="<?php echo e($campaign->name); ?> allocations">
                <thead>
                    <tr>
                        <th>SIP Channel</th>
                        <th>GSM Gateway</th>
                        <th>Network</th>
                        <th class="num-col">Line Priority</th>
                        <th class="num-col">Total Channel Allocated</th>
                        <th class="actions-column">
                            <span class="ca-actions-head">
                                Actions
                                <?php if(auth()->user()->hasPermission('media.create')): ?>
                                    <button class="plus-btn" type="button" data-allocation-add data-campaign="<?php echo e($campaign->id); ?>" data-gateway="" title="Add allocation" aria-label="Add allocation">+</button>
                                <?php endif; ?>
                            </span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                <?php $__empty_2 = true; $__currentLoopData = $campaign->allocations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $allocation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
                    <?php
                        $allocationValues = [
                            'media_gateway' => $allocation->media_gateway,
                            'channel_allocation' => $allocation->channel_allocation,
                            'network' => $allocation->network,
                            'line_priority' => $allocation->line_priority,
                            'total_channel_allocated' => $allocation->total_channel_allocated,
                            'remarks' => $allocation->remarks,
                        ];
                    ?>
                    <tr>
                        <td><span class="num-align" data-label="SIP Channel"><?php echo e($allocation->channel_allocation); ?></span></td>
                        <td><span class="num-align" data-label="GSM Gateway"><?php echo e($allocation->media_gateway ?: '—'); ?></span></td>
                        <td><span class="num-align" data-label="Network"><?php echo e($allocation->network ?: '—'); ?></span></td>
                        <td class="num-col"><span class="num-align" data-label="Line Priority"><?php echo e($allocation->line_priority ?? '—'); ?></span></td>
                        <td class="num-col"><span class="num-align" data-label="Total Channel Allocated"><?php echo e($allocation->total_channel_allocated ?? '—'); ?></span></td>
                        <td class="actions-column">
                            <div class="row-actions">
                                <?php if(auth()->user()->hasPermission('media.edit')): ?>
                                    <button class="action-btn edit" type="button" data-allocation-edit data-campaign="<?php echo e($campaign->id); ?>" data-id="<?php echo e($allocation->id); ?>" data-values='<?php echo json_encode($allocationValues, 15, 512) ?>' title="Edit Allocation" aria-label="Edit Allocation">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                                    </button>
                                <?php endif; ?>
                                <?php if(auth()->user()->hasPermission('media.delete')): ?>
                                    <form method="POST" action="<?php echo e(route('channel-allocation.allocations.destroy', [$campaign, $allocation])); ?>" data-confirm="Delete this channel allocation?" data-confirm-title="Delete Allocation" data-confirm-ok="Delete">
                                        <?php echo csrf_field(); ?>
                                        <?php echo method_field('DELETE'); ?>
                                        <button class="action-btn delete" type="submit" title="Delete Allocation" aria-label="Delete Allocation">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
                    <tr><td colspan="6"><div class="empty-state">No allocations for this campaign.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </td>
</tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
<tr><td colspan="8"><div class="empty-state">No Channel Allocation records found.</div></td></tr>
<?php endif; ?>
</tbody>
</table>
<div class="table-footer">
    <span>Showing <?php echo e($campaigns->firstItem() ?? 0); ?> to <?php echo e($campaigns->lastItem() ?? 0); ?> of <?php echo e($campaigns->total()); ?> campaigns</span>
    <div class="footer-right">
        <span>Records per page:</span>
        <select class="per-page-select" onchange="location.href=this.value" aria-label="Records per page">
            <?php $__currentLoopData = [5,10,25,50]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $size): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e(request()->fullUrlWithQuery(['per_page'=>$size,'page'=>1])); ?>" <?php echo e($perPage===$size?'selected':''); ?>><?php echo e($size); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <div class="pager">
            <?php if($campaigns->onFirstPage()): ?>
                <span class="page-number disabled">‹</span>
            <?php else: ?>
                <a class="page-number" href="<?php echo e($campaigns->previousPageUrl()); ?>">‹</a>
            <?php endif; ?>
            <?php for($page = 1; $page <= max($campaigns->lastPage(), 1); $page++): ?>
                <?php if($page === $campaigns->currentPage()): ?>
                    <span class="page-number active"><?php echo e($page); ?></span>
                <?php else: ?>
                    <a class="page-number" href="<?php echo e($campaigns->url($page)); ?>"><?php echo e($page); ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if($campaigns->hasMorePages()): ?>
                <a class="page-number" href="<?php echo e($campaigns->nextPageUrl()); ?>">›</a>
            <?php else: ?>
                <span class="page-number disabled">›</span>
            <?php endif; ?>
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
        <form id="campaignForm" method="POST" action="<?php echo e(route('channel-allocation.store')); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="_method" id="campaignMethod" value="POST">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="campaign_id">Campaign</label>
                        <?php echo $__env->make('partials.campaign-combo', [
                            'inputId' => 'campaign_id',
                            'inputName' => 'campaign',
                            'menuId' => 'caCampaignMenu',
                            'campaigns' => $masterCampaigns,
                        ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                    </div>
                    <div class="form-group" data-campaign-media-gateway><label for="campaign_media_gateway">Media Gateway</label><input class="form-control" name="media_gateway" id="campaign_media_gateway"></div>
                    <div class="form-group" data-campaign-total-channels><label for="campaign_total_channels_allocated">Total Channels Allocated</label><input class="form-control" type="number" min="0" name="total_channels_allocated" id="campaign_total_channels_allocated"></div>
                    <div class="form-group"><label for="campaign_fte">FTE</label><input class="form-control" type="number" min="0" id="campaign_fte" readonly tabindex="-1"></div>
                    <div class="form-group"><label for="campaign_caller_id">Caller ID</label><input class="form-control" name="caller_id" id="campaign_caller_id"></div>
                    <div class="form-group"><label for="campaign_prefix">Prefix</label><input class="form-control" name="prefix" id="campaign_prefix"></div>
                    <div class="form-group full"><label for="campaign_remarks">Remarks</label><textarea class="form-control" name="remarks" id="campaign_remarks"></textarea></div>
                    <div class="form-group" data-first-allocation>
                        <label for="campaign_channel_allocation">SIP Channel</label>
                        <select class="form-control" name="channel_allocation" id="campaign_channel_allocation">
                            <option value="" selected hidden>Select SIP Channel</option>
                            <?php $__currentLoopData = $sipChannels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sip): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($sip->etpi_sip_name); ?>" data-network="<?php echo e($sip->network); ?>" data-channel-count="<?php echo e($sip->channel_count); ?>"><?php echo e($sip->etpi_sip_name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div class="form-group" data-first-allocation>
                        <label for="campaign_alloc_media_gateway">GSM Gateway</label>
                        <select class="form-control" name="media_gateway" id="campaign_alloc_media_gateway">
                            <option value="" selected hidden>Select GSM Gateway</option>
                            <?php $__currentLoopData = $gsmGateways; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gateway): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php $gatewayLabel = $gateway->site_code ?: $gateway->site_name; ?>
                                <?php if($gatewayLabel): ?>
                                    <option value="<?php echo e($gatewayLabel); ?>"><?php echo e($gatewayLabel); ?></option>
                                <?php endif; ?>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div class="form-group" data-first-allocation>
                        <label for="campaign_network">Network</label>
                        <input class="form-control" name="network" id="campaign_network" readonly tabindex="-1">
                    </div>
                    <div class="form-group" data-first-allocation>
                        <label for="campaign_line_priority">Line Priority</label>
                        <input class="form-control" type="number" min="0" name="line_priority" id="campaign_line_priority">
                    </div>
                    <div class="form-group" data-first-allocation>
                        <label for="campaign_total_channel_allocated">Total Channel Allocated</label>
                        <input class="form-control" type="number" min="0" name="total_channel_allocated" id="campaign_total_channel_allocated" readonly tabindex="-1">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn secondary" data-close="campaignModal">Cancel</button>
                <button class="btn primary" type="submit">Save</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="allocationModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="allocationModalTitle">Add Allocation</h3>
            <button type="button" class="close-btn" data-close="allocationModal">×</button>
        </div>
        <form id="allocationForm" method="POST" action="">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="_method" id="allocationMethod" value="POST">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="alloc_channel_allocation">SIP Channel</label>
                        <select class="form-control" name="channel_allocation" id="alloc_channel_allocation" required>
                            <option value="" selected hidden>Select SIP Channel</option>
                            <?php $__currentLoopData = $sipChannels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sip): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($sip->etpi_sip_name); ?>" data-network="<?php echo e($sip->network); ?>" data-channel-count="<?php echo e($sip->channel_count); ?>"><?php echo e($sip->etpi_sip_name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="alloc_media_gateway">GSM Gateway</label>
                        <select class="form-control" name="media_gateway" id="alloc_media_gateway">
                            <option value="" selected hidden>Select GSM Gateway</option>
                            <?php $__currentLoopData = $gsmGateways; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gateway): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php $gatewayLabel = $gateway->site_code ?: $gateway->site_name; ?>
                                <?php if($gatewayLabel): ?>
                                    <option value="<?php echo e($gatewayLabel); ?>"><?php echo e($gatewayLabel); ?></option>
                                <?php endif; ?>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="alloc_network">Network</label>
                        <input class="form-control" name="network" id="alloc_network" readonly tabindex="-1">
                    </div>
                    <div class="form-group">
                        <label for="alloc_line_priority">Line Priority</label>
                        <input class="form-control" type="number" min="0" name="line_priority" id="alloc_line_priority">
                    </div>
                    <div class="form-group">
                        <label for="alloc_total_channel_allocated">Total Channel Allocated</label>
                        <input class="form-control" type="number" min="0" name="total_channel_allocated" id="alloc_total_channel_allocated" readonly tabindex="-1">
                    </div>
                    <div class="form-group full" data-alloc-remarks><label for="alloc_remarks">Remarks</label><textarea class="form-control" name="remarks" id="alloc_remarks"></textarea></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn secondary" data-close="allocationModal">Cancel</button>
                <button class="btn primary" type="submit">Save</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if(auth()->user()->hasPermission('media.create')): ?>
<div class="modal-backdrop" id="importModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Import Data</h3>
            <button type="button" class="close-btn" data-close="importModal">×</button>
        </div>
        <div class="modal-body">
            <p class="import-lead">Upload the official Excel template to import campaigns and allocations in bulk.</p>
            <div class="import-section">
                <h4>1. Download Template</h4>
                <a class="btn primary" href="<?php echo e(route('channel-allocation.import.template')); ?>">
                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4v12"></path><path d="m7 11 5 5 5-5"></path><path d="M5 20h14"></path></svg>
                    Download Excel Template
                </a>
            </div>
            <div class="import-section">
                <h4>2. Upload File</h4>
                <label class="import-file-label" for="importFileInput">Choose Excel File</label>
                <div class="import-file-row">
                    <input class="form-control" type="file" id="importFileInput" accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel">
                </div>
                <p class="muted import-file-hint">Only .xlsx, .xls files are allowed.</p>
                <p class="import-upload-error" id="importUploadError" hidden></p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn secondary" data-close="importModal">Cancel</button>
            <button type="button" class="btn primary" id="importPreviewButton" disabled>Preview &amp; Validate</button>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="importPreviewModal">
    <div class="modal import-wide">
        <div class="modal-header">
            <h3>Import Data - Preview</h3>
            <button type="button" class="close-btn" data-close="importPreviewModal">×</button>
        </div>
        <div class="modal-body">
            <div class="import-stats" id="importStats"></div>
            <div class="import-banner error" id="importErrorBanner" hidden>There are errors in some rows. Please review the details below and fix them in your file.</div>
            <div class="import-banner success" id="importSuccessBanner" hidden>All rows are valid and ready to import.</div>
            <div class="table-wrap import-preview-wrap">
                <table class="import-preview-table" aria-label="Import preview">
                    <thead>
                        <tr>
                            <th>Row #</th>
                            <th>Campaign</th>
                            <th>Media Gateway</th>
                            <th>FTE</th>
                            <th>Caller ID</th>
                            <th>Prefix</th>
                            <th>Channel Allocation</th>
                            <th>Network</th>
                            <th>Line Priority</th>
                            <th>Total Channel Allocated</th>
                            <th>Status</th>
                            <th>Error Reason</th>
                        </tr>
                    </thead>
                    <tbody id="importPreviewRows"></tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer import-preview-footer">
            <a class="btn secondary" id="importErrorReport" href="#">Download Error Report</a>
            <div class="import-preview-actions">
                <button type="button" class="btn secondary" id="importBackButton">Back to Upload</button>
                <button type="button" class="btn success" id="importConfirmButton" disabled>Confirm Import</button>
            </div>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="importSuccessModal">
    <div class="modal small">
        <div class="modal-header">
            <h3>Import Data</h3>
            <button type="button" class="close-btn" data-close="importSuccessModal" id="importSuccessDismiss">×</button>
        </div>
        <div class="modal-body import-success-body">
            <div class="import-success-icon" aria-hidden="true">✓</div>
            <p class="import-success-message" id="importSuccessMessage">Import completed successfully!</p>
            <div class="import-banner info">The new data will now appear in the Channel Allocation list.</div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn primary" id="importSuccessClose">Close</button>
        </div>
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
        const panel = document.getElementById('ca-panel-' + id);
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

    const closeCaMenus = (except) => {
        document.querySelectorAll('.ca-menu.open').forEach((menu) => {
            if (menu === except) return;
            menu.classList.remove('open');
            menu.querySelector('.ca-menu-dropdown')?.setAttribute('hidden', '');
            menu.querySelector('.ca-menu-btn')?.setAttribute('aria-expanded', 'false');
        });
    };

    document.addEventListener('click', (event) => {
        const button = event.target.closest('.ca-menu-btn');
        if (!button) return;
        event.stopPropagation();
        const menu = button.closest('.ca-menu');
        const dropdown = menu?.querySelector('.ca-menu-dropdown');
        if (!menu || !dropdown) return;
        const willOpen = !menu.classList.contains('open');
        closeCaMenus();
        if (!willOpen) return;
        menu.classList.add('open');
        dropdown.removeAttribute('hidden');
        button.setAttribute('aria-expanded', 'true');
        const rect = button.getBoundingClientRect();
        dropdown.style.position = 'fixed';
        dropdown.style.top = (rect.bottom + 4) + 'px';
        dropdown.style.right = Math.max(8, window.innerWidth - rect.right) + 'px';
        dropdown.style.left = 'auto';
    });

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element) || !event.target.closest('.ca-menu')) {
            closeCaMenus();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeCaMenus();
    });
    window.addEventListener('scroll', () => closeCaMenus(), true);

    const transferButton = document.getElementById('transferButton');
    const transferMenu = document.getElementById('transferMenu');
    const closeTransferMenu = () => {
        transferMenu?.classList.remove('open');
        transferButton?.setAttribute('aria-expanded', 'false');
    };
    transferButton?.addEventListener('click', (event) => {
        event.stopPropagation();
        const willOpen = !transferMenu?.classList.contains('open');
        closeTransferMenu();
        if (!willOpen || !transferMenu) return;
        transferMenu.classList.add('open');
        transferButton.setAttribute('aria-expanded', 'true');
    });
    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element) || !event.target.closest('.transfer')) {
            closeTransferMenu();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeTransferMenu();
    });

    const campaignModal = document.getElementById('campaignModal');
    const campaignForm = document.getElementById('campaignForm');
    const campaignMethod = document.getElementById('campaignMethod');
    const storeAction = <?php echo json_encode(route('channel-allocation.store'), 15, 512) ?>;
    const updateBase = <?php echo json_encode(url('/channel-allocation'), 15, 512) ?>;
    const campaignSelect = document.getElementById('campaign_id');
    const campaignFteField = document.getElementById('campaign_fte');

    function fillMasterFte(option) {
        if (!campaignFteField) return;
        if (option) {
            campaignFteField.value = option.getAttribute('data-fte') || '';
            return;
        }
        const query = String(campaignSelect?.value || '').trim().toLowerCase();
        const exact = [...document.querySelectorAll('#caCampaignMenu .pin-campaign-option')].find((item) => (item.dataset.name || '').toLowerCase() === query);
        campaignFteField.value = exact?.getAttribute('data-fte') || '';
    }

    campaignSelect?.addEventListener('campaign-combo-change', (event) => fillMasterFte(event.detail?.option || null));
    campaignSelect?.addEventListener('input', () => fillMasterFte());

    const campaignSipSelect = document.getElementById('campaign_channel_allocation');
    const campaignNetworkField = document.getElementById('campaign_network');
    const campaignTotalField = document.getElementById('campaign_total_channel_allocated');

    function fillSipDerived(sipSelect, networkField, totalField) {
        const option = sipSelect?.selectedOptions?.[0];
        if (networkField) networkField.value = option?.getAttribute('data-network') || '';
        if (totalField) totalField.value = option?.getAttribute('data-channel-count') || '';
    }

    function showFirstAllocation(show) {
        document.querySelectorAll('[data-first-allocation]').forEach((el) => {
            el.hidden = !show;
            el.querySelectorAll('input, select, textarea').forEach((field) => {
                field.disabled = !show;
                if (!show) field.value = '';
            });
        });
        if (show) fillSipDerived(campaignSipSelect, campaignNetworkField, campaignTotalField);
    }

    function setCampaignTotalChannelsVisible(show) {
        const group = document.querySelector('[data-campaign-total-channels]');
        const field = document.getElementById('campaign_total_channels_allocated');
        if (!group) return;
        group.hidden = !show;
        group.style.display = show ? '' : 'none';
        if (field) {
            field.disabled = !show;
            if (!show) field.value = '';
        }
    }

    function setCampaignMediaGatewayVisible(show) {
        const group = document.querySelector('[data-campaign-media-gateway]');
        const field = document.getElementById('campaign_media_gateway');
        if (!group) return;
        group.hidden = !show;
        group.style.display = show ? '' : 'none';
        if (field) {
            field.disabled = !show;
            if (!show) field.value = '';
        }
    }

    document.getElementById('campaignAddButton')?.addEventListener('click', () => {
        if (!campaignForm || !campaignMethod) return;
        campaignMethod.value = 'POST';
        campaignForm.action = storeAction;
        document.getElementById('campaignModalTitle').textContent = 'Add Campaign';
        campaignForm.reset();
        if (campaignSelect) campaignSelect.disabled = false;
        fillMasterFte();
        showFirstAllocation(true);
        setCampaignTotalChannelsVisible(false);
        setCampaignMediaGatewayVisible(false);
        campaignModal?.classList.add('visible');
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-campaign-edit]');
        if (!button) return;
        closeCaMenus();
        const recordId = Number(button.dataset.id);
        if (!Number.isInteger(recordId) || recordId < 1) return;
        const values = JSON.parse(button.dataset.values || '{}');
        document.getElementById('campaignModalTitle').textContent = 'Edit Campaign';
        campaignMethod.value = 'PUT';
        campaignForm.action = updateBase + '/' + recordId;
        showFirstAllocation(false);
        setCampaignTotalChannelsVisible(true);
        setCampaignMediaGatewayVisible(true);
        if (campaignSelect) {
            campaignSelect.disabled = true;
            campaignSelect.value = values.name ?? '';
        }
        fillMasterFte();
        ['media_gateway','total_channels_allocated','caller_id','prefix','remarks'].forEach((key) => {
            const field = document.getElementById('campaign_' + key);
            if (field) field.value = values[key] ?? '';
        });
        campaignModal?.classList.add('visible');
    });

    const allocationModal = document.getElementById('allocationModal');
    const allocationForm = document.getElementById('allocationForm');
    const allocationMethod = document.getElementById('allocationMethod');
    const allocSipSelect = document.getElementById('alloc_channel_allocation');
    const allocGatewaySelect = document.getElementById('alloc_media_gateway');
    const allocNetworkField = document.getElementById('alloc_network');
    const allocTotalField = document.getElementById('alloc_total_channel_allocated');

    function setAllocRemarksVisible(show) {
        const remarksGroup = document.querySelector('[data-alloc-remarks]');
        if (!remarksGroup) return;
        remarksGroup.hidden = !show;
        remarksGroup.style.display = show ? '' : 'none';
        if (!show) {
            const field = document.getElementById('alloc_remarks');
            if (field) field.value = '';
        }
    }

    function ensureSelectValue(select, value, extra = {}) {
        if (!select) return;
        const next = value == null ? '' : String(value);
        if (next === '') {
            select.value = '';
            return;
        }
        const exists = Array.from(select.options).some((option) => option.value === next);
        if (!exists) {
            const option = document.createElement('option');
            option.value = next;
            option.textContent = next;
            if (extra.network != null) option.setAttribute('data-network', extra.network);
            if (extra.channelCount != null) option.setAttribute('data-channel-count', extra.channelCount);
            select.appendChild(option);
        }
        select.value = next;
    }

    function fillAllocationFromSip() {
        fillSipDerived(allocSipSelect, allocNetworkField, allocTotalField);
    }

    allocSipSelect?.addEventListener('change', fillAllocationFromSip);
    campaignSipSelect?.addEventListener('change', () => fillSipDerived(campaignSipSelect, campaignNetworkField, campaignTotalField));
    ['alloc_network', 'alloc_total_channel_allocated', 'campaign_network', 'campaign_total_channel_allocated'].forEach((id) => {
        document.getElementById(id)?.addEventListener('keydown', (event) => event.preventDefault());
        document.getElementById(id)?.addEventListener('paste', (event) => event.preventDefault());
    });

    document.addEventListener('click', (event) => {
        const addButton = event.target.closest('[data-allocation-add]');
        if (addButton) {
            const campaignId = Number(addButton.dataset.campaign);
            if (!Number.isInteger(campaignId) || campaignId < 1) return;
            allocationMethod.value = 'POST';
            allocationForm.action = updateBase + '/' + campaignId + '/allocations';
            document.getElementById('allocationModalTitle').textContent = 'Add Allocation';
            allocationForm.reset();
            ensureSelectValue(allocGatewaySelect, addButton.dataset.gateway || '');
            fillAllocationFromSip();
            setAllocRemarksVisible(false);
            allocationModal?.classList.add('visible');
            return;
        }
        const button = event.target.closest('[data-allocation-edit]');
        if (!button) return;
        const campaignId = Number(button.dataset.campaign);
        const recordId = Number(button.dataset.id);
        if (!Number.isInteger(campaignId) || !Number.isInteger(recordId) || campaignId < 1 || recordId < 1) return;
        const values = JSON.parse(button.dataset.values || '{}');
        allocationMethod.value = 'PUT';
        allocationForm.action = updateBase + '/' + campaignId + '/allocations/' + recordId;
        document.getElementById('allocationModalTitle').textContent = 'Edit Allocation';
        setAllocRemarksVisible(false);
        ensureSelectValue(allocSipSelect, values.channel_allocation || '', {
            network: values.network || '',
            channelCount: values.total_channel_allocated ?? ''
        });
        ensureSelectValue(allocGatewaySelect, values.media_gateway || '');
        const linePriority = document.getElementById('alloc_line_priority');
        if (linePriority) linePriority.value = values.line_priority ?? '';
        fillAllocationFromSip();
        allocationModal?.classList.add('visible');
    });

    const importModal = document.getElementById('importModal');
    const importPreviewModal = document.getElementById('importPreviewModal');
    const importSuccessModal = document.getElementById('importSuccessModal');
    const importFileInput = document.getElementById('importFileInput');
    const importPreviewButton = document.getElementById('importPreviewButton');
    const importConfirmButton = document.getElementById('importConfirmButton');
    const importFileStatus = document.getElementById('importFileStatus');
    const importUploadError = document.getElementById('importUploadError');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const previewUrl = <?php echo json_encode(auth()->user()->hasPermission('media.create') ? route('channel-allocation.import.preview') : '', 15, 512) ?>;
    const confirmUrl = <?php echo json_encode(auth()->user()->hasPermission('media.create') ? route('channel-allocation.import.confirm') : '', 15, 512) ?>;
    const errorsUrl = <?php echo json_encode(auth()->user()->hasPermission('media.create') ? route('channel-allocation.import.errors') : '', 15, 512) ?>;
    let importToken = '';

    function resetImportUpload() {
        importToken = '';
        if (importFileInput) importFileInput.value = '';
        if (importFileStatus) {
            importFileStatus.textContent = 'No file chosen';
            importFileStatus.classList.remove('ready');
        }
        if (importPreviewButton) importPreviewButton.disabled = true;
        if (importUploadError) {
            importUploadError.hidden = true;
            importUploadError.textContent = '';
        }
    }

    function showImportError(message) {
        if (!importUploadError) return;
        importUploadError.hidden = !message;
        importUploadError.textContent = message || '';
    }

    async function readJsonResponse(response) {
        const text = await response.text();
        const start = text.indexOf('{');
        if (start < 0) {
            throw new Error('invalid-json');
        }
        return JSON.parse(text.slice(start));
    }

    function importFailureMessage(data, fallback) {
        return data?.message || data?.errors?.file?.[0] || fallback;
    }

    document.getElementById('importDataButton')?.addEventListener('click', () => {
        closeTransferMenu();
        if (!importModal) return;
        resetImportUpload();
        importPreviewModal?.classList.remove('visible');
        importSuccessModal?.classList.remove('visible');
        importModal.classList.add('visible');
    });

    importFileInput?.addEventListener('change', () => {
        const file = importFileInput.files && importFileInput.files[0];
        showImportError('');
        if (!file) {
            resetImportUpload();
            return;
        }
        const name = file.name || '';
        const ok = /\.(xlsx|xls)$/i.test(name);
        if (importFileStatus) {
            importFileStatus.textContent = name;
            importFileStatus.classList.toggle('ready', ok);
        }
        importPreviewButton.disabled = !ok;
        if (!ok) showImportError('Only .xlsx, .xls files are allowed.');
    });

    importPreviewButton?.addEventListener('click', async () => {
        const file = importFileInput?.files && importFileInput.files[0];
        if (!file || !previewUrl) return;
        showImportError('');
        importPreviewButton.disabled = true;
        const body = new FormData();
        body.append('file', file);
        try {
            const response = await fetch(previewUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body
            });
            const data = await readJsonResponse(response);
            if (!response.ok || !data.ok) {
                showImportError(importFailureMessage(data, 'Preview failed. Please try again.'));
                importPreviewButton.disabled = false;
                return;
            }
            importToken = data.token || '';
            renderImportPreview(data);
            importModal?.classList.remove('visible');
            importPreviewModal?.classList.add('visible');
        } catch (error) {
            showImportError('Preview failed. Please try again.');
        }
        importPreviewButton.disabled = !(importFileInput?.files && importFileInput.files[0]);
    });

    function renderImportPreview(data) {
        const summary = data.summary || {};
        const stats = document.getElementById('importStats');
        if (stats) {
            stats.innerHTML = [
                ['Total Rows', summary.total ?? 0, ''],
                ['Campaigns Detected', summary.campaigns ?? 0, ''],
                ['Allocations Detected', summary.allocations ?? 0, ''],
                ['Valid Rows', summary.valid ?? 0, 'ok'],
                ['Error Rows', summary.errors ?? 0, (summary.errors ?? 0) > 0 ? 'bad' : 'ok']
            ].map(([label, value, tone]) => `<div class="import-stat ${tone}"><span>${label}</span><strong>${value}</strong></div>`).join('');
        }
        const hasErrors = !data.valid;
        document.getElementById('importErrorBanner')?.toggleAttribute('hidden', !hasErrors);
        document.getElementById('importSuccessBanner')?.toggleAttribute('hidden', hasErrors);
        const report = document.getElementById('importErrorReport');
        if (report) {
            report.href = errorsUrl + (importToken ? ('?token=' + encodeURIComponent(importToken)) : '');
            report.style.visibility = hasErrors ? 'visible' : 'hidden';
        }
        if (importConfirmButton) {
            importConfirmButton.disabled = hasErrors;
            importConfirmButton.classList.toggle('success', !hasErrors);
        }
        const tbody = document.getElementById('importPreviewRows');
        if (!tbody) return;
        tbody.innerHTML = (data.rows || []).map((row) => {
            const err = row.valid ? '' : 'import-row-error';
            return `<tr class="${err}">
                <td>${row.row}</td>
                <td>${escapeImport(row.campaign)}</td>
                <td>${escapeImport(row.media_gateway)}</td>
                <td>${escapeImport(row.fte)}</td>
                <td>${escapeImport(row.caller_id)}</td>
                <td>${escapeImport(row.prefix)}</td>
                <td>${escapeImport(row.channel_allocation)}</td>
                <td>${escapeImport(row.network)}</td>
                <td>${escapeImport(row.line_priority)}</td>
                <td>${escapeImport(row.total_channel_allocated)}</td>
                <td class="${row.valid ? 'import-status-ok' : 'import-status-bad'}">${escapeImport(row.status)}</td>
                <td>${escapeImport(row.error)}</td>
            </tr>`;
        }).join('');
    }

    function escapeImport(value) {
        const replacements = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(value ?? '').replace(/[&<>"']/g, (char) => replacements[char] || char);
    }

    document.getElementById('importBackButton')?.addEventListener('click', () => {
        importPreviewModal?.classList.remove('visible');
        importModal?.classList.add('visible');
        if (importConfirmButton) importConfirmButton.disabled = true;
    });

    importConfirmButton?.addEventListener('click', async () => {
        if (!importToken || importConfirmButton.disabled || !confirmUrl) return;
        importConfirmButton.disabled = true;
        try {
            const response = await fetch(confirmUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ token: importToken })
            });
            const data = await readJsonResponse(response);
            if (!response.ok || !data.ok) {
                importConfirmButton.disabled = false;
                const banner = document.getElementById('importErrorBanner');
                if (banner) {
                    banner.hidden = false;
                    banner.textContent = importFailureMessage(data, 'Import failed. Please try again.');
                }
                return;
            }
            importPreviewModal?.classList.remove('visible');
            const message = document.getElementById('importSuccessMessage');
            if (message) {
                message.textContent = 'Import completed successfully! ' + (data.allocations ?? 0) + ' allocations imported.';
            }
            importSuccessModal?.classList.add('visible');
        } catch (error) {
            importConfirmButton.disabled = false;
        }
    });

    function finishImportSuccess() {
        window.location.reload();
    }
    document.getElementById('importSuccessClose')?.addEventListener('click', finishImportSuccess);
    document.getElementById('importSuccessDismiss')?.addEventListener('click', finishImportSuccess);

    const restoreExpanded = <?php echo json_encode(session('ca_expanded'), 15, 512) ?>;
    const restoreEditAllocation = <?php echo json_encode(session('ca_edit_allocation'), 15, 512) ?>;
    if (restoreExpanded) {
        document.querySelector('[data-ca-toggle="' + restoreExpanded + '"]')?.click();
    }
    if (restoreEditAllocation) {
        document.querySelector('[data-allocation-edit][data-id="' + restoreEditAllocation + '"]')?.click();
    }
});
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\OmniChannel\OmniChannel_Inventory_Production_Updated\resources\views/channel-allocations/index.blade.php ENDPATH**/ ?>