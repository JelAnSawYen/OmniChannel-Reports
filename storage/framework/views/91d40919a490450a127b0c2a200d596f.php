<?php $__env->startSection('content'); ?>
<?php
    $canRevealSecrets = auth()->user()->canExportGatewaySecrets();
    $isGsm = ($resource['index'] ?? '') === 'gsm-gateways.index';
    $gatewayColumns = $isGsm
        ? [
            'hostname' => 'Hostname',
            'ip_address' => 'IP',
            'site_code' => 'Serial Number',
            'channel_count' => 'Channel Count',
            'network' => 'Network',
            'device_function' => 'Function',
            'site_name' => 'Site',
            'username' => 'User',
            'password' => 'Password',
        ]
        : [
            'ip_address' => 'Hostname IP',
            'site_code' => 'Serial Number',
            'plan' => 'Plan',
            'port' => 'Port',
            'network' => 'Network',
            'device_function' => 'Function',
            'site_name' => 'Site',
            'username' => 'User',
            'password' => 'Password',
        ];
    $previewHeaders = array_values(\App\Support\InventoryImportCatalog::gateway($isGsm ? 'gsm-gateways' : 'media-gateways')['fields']);
    $colspan = count($gatewayColumns) + 1;
?>
<div class="page-head">
    <div>
        <h1 class="page-title"><?php echo e($resource['title']); ?></h1>
        <p class="page-subtitle"><?php echo e($resource['subtitle']); ?></p>
    </div>
    <div class="toolbar">
        <form class="search-box media-search-form" id="mediaSearchForm" method="GET" action="<?php echo e(route($resource['index'])); ?>">
            <span class="search-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
            </span>
            <input id="mediaSearchInput" name="search" value="<?php echo e($search); ?>" placeholder="<?php echo e($resource['search']); ?>" aria-label="<?php echo e($resource['search']); ?>" autocomplete="off">
        </form>
        <button class="btn primary" type="submit" form="mediaSearchForm">Search</button>
        <?php if(auth()->user()->hasPermission('media.export')): ?>
        <?php echo $__env->make('partials.data-transfer', [
            'canExport' => true,
            'canImport' => auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways(),
            'exportUrl' => route($resource['export'], request()->query()),
            'templateUrl' => (auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways()) ? route($isGsm ? 'gsm-gateways.import.template' : 'media-gateways.import.template') : '',
            'previewUrl' => (auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways()) ? route($isGsm ? 'gsm-gateways.import.preview' : 'media-gateways.import.preview') : '',
            'confirmUrl' => (auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways()) ? route($isGsm ? 'gsm-gateways.import.confirm' : 'media-gateways.import.confirm') : '',
            'errorsUrl' => (auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways()) ? route($isGsm ? 'gsm-gateways.import.errors' : 'media-gateways.import.errors') : '',
            'previewHeaders' => $previewHeaders,
            'entityTitle' => $resource['plural'],
        ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?>
        <?php if(auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways()): ?>
            <button class="plus-btn" type="button" data-open-modal="add-media-gateway" aria-label="Add <?php echo e($resource['entity']); ?>" title="Add <?php echo e($resource['entity']); ?>">+</button>
        <?php endif; ?>
    </div>
</div>

<div class="table-card table-wrap">
<table class="gsm-table" aria-label="<?php echo e($resource['title']); ?>">
<thead>
<tr>
    <?php $__currentLoopData = $gatewayColumns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <th>
            <?php if($isGsm && $field === 'hostname'): ?>
                <span class="gsm-host-cell">
                    <span class="ca-toggle" aria-hidden="true"></span>
                    <button type="button" class="sortable-button" data-sort="<?php echo e($field); ?>"><span><?php echo e($label); ?></span></button>
                </span>
            <?php else: ?>
                <button type="button" class="sortable-button" data-sort="<?php echo e($field); ?>"><span><?php echo e($label); ?></span></button>
            <?php endif; ?>
        </th>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <th class="actions-column">Actions</th>
</tr>
</thead>
<tbody id="mediaGatewayRows">
<?php $__empty_1 = true; $__currentLoopData = $mediaGateways; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gateway): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<?php if($isGsm): ?>
<?php $assignments = $gateway->assignmentPayload(); ?>
<tr class="gsm-gateway-row" data-gateway="<?php echo e($gateway->id); ?>" <?php if(auth()->user()->hasPermission('media.delete') && auth()->user()->canMutateGateways()): ?> data-bulk-row="main" data-bulk-id="<?php echo e($gateway->id); ?>" data-bulk-url="<?php echo e(route($isGsm ? 'gsm-gateways.bulk-destroy' : 'media-gateways.bulk-destroy')); ?>" data-bulk-ajax="1" <?php endif; ?>>
    <td>
        <span class="gsm-host-cell">
            <button type="button" class="ca-toggle" data-ca-toggle="<?php echo e($gateway->id); ?>" aria-expanded="false" aria-controls="gsm-panel-<?php echo e($gateway->id); ?>" title="Expand <?php echo e($gateway->hostname ?: $gateway->site_code); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 6 6 6-6 6"></path></svg>
            </button>
            <span><?php echo e($gateway->hostname ?: '—'); ?></span>
        </span>
    </td>
    <td><?php echo e($gateway->ip_address); ?></td>
    <td><?php echo e($gateway->site_code); ?></td>
    <td><?php echo e($gateway->channel_count ?: '—'); ?></td>
    <td><?php echo e($gateway->network ?: '—'); ?></td>
    <td><?php echo e($gateway->device_function ?: '—'); ?></td>
    <td><?php echo e($gateway->site_name); ?></td>
    <td><?php echo e($gateway->username); ?></td>
    <td>
        <span class="pdc-secret">
            <span class="pdc-secret-mask">••••••</span>
            <?php if($canRevealSecrets && $gateway->password): ?>
                <span class="pdc-secret-value" hidden><?php echo e($gateway->password); ?></span>
                <button type="button" class="pdc-secret-toggle" title="Show password" aria-label="Show password">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            <?php endif; ?>
        </span>
    </td>
    <td class="actions-column">
        <?php if((auth()->user()->hasPermission('media.edit') || auth()->user()->hasPermission('media.delete')) && auth()->user()->canMutateGateways()): ?>
        <div class="ca-menu">
            <button class="ca-menu-btn" type="button" aria-haspopup="true" aria-expanded="false" aria-label="<?php echo e($resource['entity']); ?> actions" title="<?php echo e($resource['entity']); ?> actions">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
            </button>
            <div class="ca-menu-dropdown" role="menu" hidden>
                <?php if(auth()->user()->hasPermission('media.edit') && auth()->user()->canMutateGateways()): ?>
                    <button class="ca-menu-item edit" type="button" role="menuitem" data-edit-id="<?php echo e($gateway->id); ?>" data-edit-hostname="<?php echo e($gateway->hostname); ?>" data-edit-site_name="<?php echo e($gateway->site_name); ?>" data-edit-site_code="<?php echo e($gateway->site_code); ?>" data-edit-ip_address="<?php echo e($gateway->ip_address); ?>" data-edit-channel_count="<?php echo e($gateway->channel_count); ?>" data-edit-network="<?php echo e($gateway->network); ?>" data-edit-device_function="<?php echo e($gateway->device_function); ?>" data-edit-username="<?php echo e($gateway->username); ?>" <?php if($canRevealSecrets): ?> data-edit-password="<?php echo e($gateway->password); ?>" <?php endif; ?> title="Edit <?php echo e($resource['entity']); ?>" aria-label="Edit <?php echo e($resource['entity']); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                        Edit
                    </button>
                <?php endif; ?>
                <?php if(auth()->user()->hasPermission('media.delete') && auth()->user()->canMutateGateways()): ?>
                    <button class="ca-menu-item delete" type="button" role="menuitem" data-delete-id="<?php echo e($gateway->id); ?>" title="Delete <?php echo e($resource['entity']); ?>" aria-label="Delete <?php echo e($resource['entity']); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                        Delete
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </td>
</tr>
<tr class="ca-nested-row" id="gsm-panel-<?php echo e($gateway->id); ?>" hidden>
    <td colspan="<?php echo e($colspan); ?>">
        <div class="ca-nested">
            <table class="gsm-sim-nested" aria-label="SIM assignments">
                <colgroup>
                    <col class="gsm-sim-col-imei">
                    <col class="gsm-sim-col-mobile">
                    <col class="gsm-sim-col-plan">
                    <col class="gsm-sim-col-ip">
                    <col class="gsm-sim-col-port">
                </colgroup>
                <thead>
                    <tr>
                        <th>IMEI</th>
                        <th>Mobile Number</th>
                        <th>Plan</th>
                        <th>IP</th>
                        <th>Port</th>
                    </tr>
                </thead>
                <tbody>
                <?php $__empty_2 = true; $__currentLoopData = $assignments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $assignment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
                    <?php
                        $nestedBulkId = ! empty($assignment['assignment_id'])
                            ? (string) $assignment['assignment_id']
                            : ((string) ($assignment['sim_type'] ?? '').'-'.(string) ($assignment['id'] ?? ''));
                    ?>
                    <tr <?php if(auth()->user()->hasPermission('media.delete') && auth()->user()->canMutateGateways() && $nestedBulkId !== '-' && $nestedBulkId !== ''): ?> data-bulk-row="nested" data-bulk-id="<?php echo e($nestedBulkId); ?>" data-bulk-url="<?php echo e(route('gsm-gateways.assignments.bulk-destroy', $gateway)); ?>" data-bulk-ajax="1" <?php endif; ?>>
                        <td><?php echo e($assignment['imei'] ?: '—'); ?></td>
                        <td><?php echo e($assignment['mobile_number'] ?: '—'); ?></td>
                        <td><?php echo e($assignment['plan'] ?: '—'); ?></td>
                        <td><?php echo e($gateway->ip_address); ?></td>
                        <td><?php echo e($assignment['port'] !== '' && $assignment['port'] !== null ? $assignment['port'] : '—'); ?></td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
                    <tr><td colspan="5"><div class="empty-state">No SIM assignments.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </td>
</tr>
<?php else: ?>
<tr <?php if(auth()->user()->hasPermission('media.delete') && auth()->user()->canMutateGateways()): ?> data-bulk-row="main" data-bulk-id="<?php echo e($gateway->id); ?>" data-bulk-url="<?php echo e(route($isGsm ? 'gsm-gateways.bulk-destroy' : 'media-gateways.bulk-destroy')); ?>" data-bulk-ajax="1" <?php endif; ?>>
    <td><?php echo e($gateway->ip_address); ?></td>
    <td><?php echo e($gateway->site_code); ?></td>
    <td><?php echo e($gateway->plan ?: '—'); ?></td>
    <td><?php echo e($gateway->port ?: '—'); ?></td>
    <td><?php echo e($gateway->network ?: '—'); ?></td>
    <td><?php echo e($gateway->device_function ?: '—'); ?></td>
    <td><?php echo e($gateway->site_name); ?></td>
    <td><?php echo e($gateway->username); ?></td>
    <td>
        <span class="pdc-secret">
            <span class="pdc-secret-mask">••••••</span>
            <?php if($canRevealSecrets && $gateway->password): ?>
                <span class="pdc-secret-value" hidden><?php echo e($gateway->password); ?></span>
                <button type="button" class="pdc-secret-toggle" title="Show password" aria-label="Show password">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
            <?php endif; ?>
        </span>
    </td>
    <td class="actions-column">
        <div class="row-actions">
            <?php if(auth()->user()->hasPermission('media.edit') && auth()->user()->canMutateGateways()): ?>
                <button type="button" class="action-btn edit" data-edit-id="<?php echo e($gateway->id); ?>" data-edit-site_name="<?php echo e($gateway->site_name); ?>" data-edit-site_code="<?php echo e($gateway->site_code); ?>" data-edit-ip_address="<?php echo e($gateway->ip_address); ?>" data-edit-plan="<?php echo e($gateway->plan); ?>" data-edit-port="<?php echo e($gateway->port); ?>" data-edit-network="<?php echo e($gateway->network); ?>" data-edit-device_function="<?php echo e($gateway->device_function); ?>" data-edit-username="<?php echo e($gateway->username); ?>" <?php if($canRevealSecrets): ?> data-edit-password="<?php echo e($gateway->password); ?>" <?php endif; ?> title="Edit <?php echo e($resource['entity']); ?>" aria-label="Edit <?php echo e($resource['entity']); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                </button>
            <?php endif; ?>
            <?php if(auth()->user()->hasPermission('media.delete') && auth()->user()->canMutateGateways()): ?>
                <button type="button" class="action-btn delete" data-delete-id="<?php echo e($gateway->id); ?>" title="Delete <?php echo e($resource['entity']); ?>" aria-label="Delete <?php echo e($resource['entity']); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                </button>
            <?php endif; ?>
        </div>
    </td>
</tr>
<?php endif; ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
<tr><td colspan="<?php echo e($colspan); ?>"><div class="empty-state"><?php echo e($resource['empty']); ?></div></td></tr>
<?php endif; ?>
</tbody>
</table>
<div class="table-footer">
    <span id="recordSummary">Showing <?php echo e($mediaGateways->firstItem() ?? 0); ?> to <?php echo e($mediaGateways->lastItem() ?? 0); ?> of <?php echo e($mediaGateways->total()); ?> entries</span>
    <div class="footer-right">
        <span>Records per page:</span>
        <select id="perPageSelect" class="per-page-select">
            <option value="5" <?php echo e($perPage===5?'selected':''); ?>>5</option>
            <option value="10" <?php echo e($perPage===10?'selected':''); ?>>10</option>
            <option value="25" <?php echo e($perPage===25?'selected':''); ?>>25</option>
            <option value="50" <?php echo e($perPage===50?'selected':''); ?>>50</option>
        </select>
        <?php echo $__env->make('partials.table-pager', ['paginator' => $mediaGateways, 'pagerId' => 'paginationLinks', 'dataPage' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>
</div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('modals'); ?>
<?php if(auth()->user()->canMutateGateways() && (auth()->user()->hasPermission('media.create') || auth()->user()->hasPermission('media.edit'))): ?>
<div class="modal-backdrop" id="mediaGatewayModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="mediaGatewayModalTitle">Add <?php echo e($resource['entity']); ?></h3>
            <button type="button" class="close-btn" data-close="mediaGatewayModal">×</button>
        </div>
        <form id="mediaGatewayForm" method="POST" action="<?php echo e(route($resource['store'])); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="_method" id="formMethod" value="POST">
            <input type="hidden" id="gatewayId">
            <div class="modal-body">
                <div id="formErrors"></div>
                <?php if($isGsm): ?>
                    <div class="form-grid">
                        <div class="form-group"><label for="hostname">Hostname</label><input class="form-control" id="hostname" name="hostname" required></div>
                        <div class="form-group">
                            <label for="device_function">Function</label>
                            <select class="form-control" id="device_function" name="device_function" required>
                                <option value="" selected hidden>Select Function</option>
                                <option value="Inbound">Inbound</option>
                                <option value="Outbound">Outbound</option>
                            </select>
                        </div>
                        <div class="form-group"><label for="ip_address">IP Address</label><input class="form-control" id="ip_address" name="ip_address" required></div>
                        <div class="form-group">
                            <label for="site_name">Site</label>
                            <select class="form-control" id="site_name" name="site_name" required>
                                <option value="" selected hidden>Select Site</option>
                                <?php $__currentLoopData = ($locations ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slug => $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($name); ?>"><?php echo e($name); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <div class="form-group"><label for="site_code">Serial Number</label><input class="form-control" id="site_code" name="site_code" required></div>
                        <div class="form-group"><label for="username">User</label><input class="form-control" id="username" name="username" required></div>
                        <div class="form-group"><label for="channel_count">Channel Count</label><input class="form-control" id="channel_count" name="channel_count" type="number" min="1" max="512" required></div>
                        <div class="form-group"><label for="network">Network</label><input class="form-control" id="network" name="network"></div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="pdc-password-field">
                                <input class="form-control" type="password" id="password" name="password" autocomplete="new-password">
                                <?php if(auth()->user()->canExportGatewaySecrets()): ?>
                                    <button type="button" class="pdc-secret-toggle" data-toggle-input="password" title="Show password" aria-label="Show password">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="form-grid">
                        <div class="form-group"><label for="ip_address">Hostname IP</label><input class="form-control" id="ip_address" name="ip_address" required></div>
                        <div class="form-group"><label for="site_code">Serial Number</label><input class="form-control" id="site_code" name="site_code" required></div>
                        <div class="form-group"><label for="plan">Plan</label><input class="form-control" id="plan" name="plan"></div>
                        <div class="form-group"><label for="port">Port</label><input class="form-control" id="port" name="port"></div>
                        <div class="form-group"><label for="network">Network</label><input class="form-control" id="network" name="network"></div>
                        <div class="form-group"><label for="device_function">Function</label><input class="form-control" id="device_function" name="device_function"></div>
                        <div class="form-group"><label for="site_name">Site</label><input class="form-control" id="site_name" name="site_name" required></div>
                        <div class="form-group"><label for="username">User</label><input class="form-control" id="username" name="username" required></div>
                        <div class="form-group full">
                            <label for="password">Password</label>
                            <div class="pdc-password-field">
                                <input class="form-control" type="password" id="password" name="password" autocomplete="new-password">
                                <?php if(auth()->user()->canExportGatewaySecrets()): ?>
                                    <button type="button" class="pdc-secret-toggle" data-toggle-input="password" title="Show password" aria-label="Show password">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer"><button type="button" class="btn secondary" data-close="mediaGatewayModal">Cancel</button><button type="submit" class="btn primary">Save</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if(auth()->user()->canMutateGateways() && auth()->user()->hasPermission('media.delete')): ?>
<div class="modal-backdrop" id="deleteModal">
    <div class="modal small">
        <div class="modal-header"><h3 id="deleteModalTitle">Delete <?php echo e($resource['entity']); ?></h3><button type="button" class="close-btn" data-close="deleteModal">×</button></div>
        <div class="modal-body"><p id="deleteModalBody">Are you sure you want to delete this <?php echo e($resource['entity']); ?>?</p></div>
        <div class="modal-footer"><button type="button" class="btn secondary" data-close="deleteModal">Cancel</button><button type="button" class="btn danger" id="confirmDeleteBtn">Delete</button></div>
    </div>
</div>
<?php endif; ?>
<?php echo $__env->make('partials.inventory-import-script', [
    'previewUrl' => (auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways()) ? route($isGsm ? 'gsm-gateways.import.preview' : 'media-gateways.import.preview') : '',
    'confirmUrl' => (auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways()) ? route($isGsm ? 'gsm-gateways.import.confirm' : 'media-gateways.import.confirm') : '',
    'errorsUrl' => (auth()->user()->hasPermission('media.create') && auth()->user()->canMutateGateways()) ? route($isGsm ? 'gsm-gateways.import.errors' : 'media-gateways.import.errors') : '',
    'previewFields' => $isGsm
        ? ['hostname', 'ip_address', 'site_code', 'channel_count', 'device_function', 'site_name', 'username', 'password', 'assignment_port', 'imei', 'mobile_number', 'assignment_network', 'assignment_plan']
        : ['ip_address', 'site_code', 'plan', 'port', 'network', 'device_function', 'site_name', 'username', 'password'],
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\OmniChannel\OmniChannel_Inventory_Production_Updated\resources\views/media-gateways/index.blade.php ENDPATH**/ ?>