<?php $__env->startSection('content'); ?>
<?php
    $selected = old('permissions', $selectedPermissions ?? []);
    $canSave = $selectedUser && count($editableKeys) > 0;
    $permissionIcons = [
        'plus' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"></rect><path d="M12 8v8"></path><path d="M8 12h8"></path></svg>',
        'edit' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>',
        'export' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21V9"></path><path d="m8 13 4-4 4 4"></path><path d="M4 5h16"></path></svg>',
        'delete' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>',
        'logs' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 6h13"></path><path d="M8 12h13"></path><path d="M8 18h13"></path><path d="M3 6h.01"></path><path d="M3 12h.01"></path><path d="M3 18h.01"></path></svg>',
        'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
        'roles' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3 4 7v6c0 5 3.4 7.8 8 9 4.6-1.2 8-4 8-9V7Z"></path></svg>',
    ];
?>
<div class="page-head">
    <div>
        <h1 class="page-title user-type-edit-title">
            <a class="page-back" href="<?php echo e(route('user-types.index')); ?>" aria-label="Back to User Types" title="Back to User Types">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18 9 12l6-6"></path></svg>
            </a>
            Edit User Type: <span class="title-accent"><?php echo e($userType->name); ?></span>
        </h1>
    </div>
</div>

<div class="user-type-edit-layout">
    <div class="table-card user-type-assigned-card">
        <form class="assigned-users-toolbar search-filter-form" method="GET" data-live-search>
            <?php if($selectedUser): ?><input type="hidden" name="user" value="<?php echo e($selectedUser->id); ?>"><?php endif; ?>
            <div class="search-box">
                <span class="search-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg></span>
                <input name="search" value="<?php echo e(request('search')); ?>" placeholder="Search by name or email..." autocomplete="off">
                <button type="button" class="search-clear" data-clear-search aria-label="Clear search" title="Clear search">×</button>
            </div>
        </form>
        <form method="GET" data-select-user>
            <?php if(request('search') !== null && request('search') !== ''): ?><input type="hidden" name="search" value="<?php echo e(request('search')); ?>"><?php endif; ?>
            <div class="table-wrap">
                <table class="user-pick-table">
                    <thead><tr><th></th><th>Name / Email</th><th>Role</th></tr></thead>
                    <tbody>
                    <?php $__empty_1 = true; $__currentLoopData = $assignedUsers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $assigned): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <tr class="<?php echo e($selectedUser && $selectedUser->id === $assigned->id ? 'is-selected' : ''); ?>">
                        <td>
                            <input type="radio" name="user" value="<?php echo e($assigned->id); ?>" <?php echo e($selectedUser && $selectedUser->id === $assigned->id ? 'checked' : ''); ?> aria-label="Select <?php echo e($assigned->name); ?>">
                        </td>
                        <td>
                            <span class="user-name-cell">
                                <span class="user-mini-avatar avatar-tone-<?php echo e($assigned->id % 5); ?>" aria-hidden="true"><?php echo e($assigned->initials()); ?></span>
                                <span class="user-name-copy">
                                    <strong><?php echo e($assigned->name); ?></strong>
                                    <small><?php echo e($assigned->email); ?></small>
                                </span>
                            </span>
                        </td>
                        <td><?php echo e($userType->name); ?></td>
                    </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <tr><td colspan="3" class="empty-state">No users assigned to this user type.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                <div class="table-footer">
                    <span>Showing <?php echo e($assignedUsers->firstItem() ?? 0); ?> to <?php echo e($assignedUsers->lastItem() ?? 0); ?> of <?php echo e($assignedUsers->total()); ?> results</span>
                    <div class="pager">
                        <?php if($assignedUsers->onFirstPage()): ?><span class="page-number">‹</span><?php else: ?><a class="page-number" href="<?php echo e($assignedUsers->previousPageUrl()); ?>">‹</a><?php endif; ?>
                        <?php for($p=1;$p<=$assignedUsers->lastPage();$p++): ?><?php if($p===$assignedUsers->currentPage()): ?><span class="page-number active"><?php echo e($p); ?></span><?php else: ?><a class="page-number" href="<?php echo e($assignedUsers->url($p)); ?>"><?php echo e($p); ?></a><?php endif; ?> <?php endfor; ?>
                        <?php if($assignedUsers->hasMorePages()): ?><a class="page-number" href="<?php echo e($assignedUsers->nextPageUrl()); ?>">›</a><?php else: ?><span class="page-number">›</span><?php endif; ?>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="panel form-card user-type-permissions-card">
        <?php if($selectedUser): ?>
            <div class="selected-user-head">
                <span class="user-mini-avatar large avatar-tone-<?php echo e($selectedUser->id % 5); ?>" aria-hidden="true"><?php echo e($selectedUser->initials()); ?></span>
                <div>
                    <strong><?php echo e($selectedUser->name); ?></strong>
                    <small class="muted"><?php echo e($selectedUser->email); ?></small>
                </div>
            </div>
        <?php else: ?>
            <div class="selected-user-head">
                <div>
                    <strong>No user selected</strong>
                    <small class="muted">Select a user to view and edit permissions.</small>
                </div>
            </div>
        <?php endif; ?>
        <form method="POST" action="<?php echo e(route('user-types.update',$userType)); ?>" class="user-type-edit-form">
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>
            <?php if($selectedUser): ?><input type="hidden" name="user_id" value="<?php echo e($selectedUser->id); ?>"><?php endif; ?>
            <input type="hidden" name="search" value="<?php echo e(request('search')); ?>">
            <input type="hidden" name="page" value="<?php echo e(request('page')); ?>">
            <h2 class="permission-list-title">User Permissions</h2>
            <div class="permission-list">
                <?php $__currentLoopData = $permissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key=>$meta): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $canEdit = $canSave && in_array($key, $editableKeys, true);
                    $isChecked = $canEdit && in_array($key, $selected, true);
                ?>
                <label class="permission-item<?php echo e($canEdit ? '' : ' is-locked'); ?>" <?php if(! $canEdit): ?> aria-disabled="true" <?php endif; ?>>
                    <input type="checkbox" name="permissions[]" value="<?php echo e($key); ?>" <?php echo e($isChecked ? 'checked' : ''); ?> <?php echo e($canEdit ? '' : 'disabled'); ?> <?php if(! $canEdit): ?> tabindex="-1" <?php endif; ?>>
                    <span class="permission-icon"><?php echo $permissionIcons[$meta['icon']] ?? ''; ?></span>
                    <span><span><?php echo e($meta['label']); ?></span></span>
                </label>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
            <div class="form-actions">
                <a class="btn secondary" href="<?php echo e($selectedUser ? route('user-types.edit', array_filter(['userType'=>$userType,'user'=>$selectedUser->id,'search'=>request('search'),'page'=>request('page')])) : route('user-types.index')); ?>">Cancel</a>
                <button class="btn primary" type="submit" <?php echo e($canSave ? '' : 'disabled'); ?>>
                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v13a2 2 0 0 1-2 2Z"></path><path d="M17 21v-8H7v8"></path><path d="M7 3v5h8"></path></svg>
                    Save
                </button>
            </div>
        </form>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\OmniChannel\OmniChannel_Inventory_Production_Updated\resources\views/user-types/edit.blade.php ENDPATH**/ ?>