<?php $__env->startSection('content'); ?>
<div class="page-head">
    <div><h1 class="page-title">User Types</h1><p class="page-subtitle">Manage roles, descriptions, and permission sets.</p></div>
    <?php if(auth()->user()->isSystemAdministrator() && auth()->user()->hasPermission('roles.manage')): ?>
        <a href="<?php echo e(route('user-types.create')); ?>" class="plus-btn" title="Add User Type" aria-label="Add User Type">+</a>
    <?php endif; ?>
</div>

<div class="table-card table-wrap">
<table>
<thead><tr><th>Id</th><th>User Type</th><th>Description</th><th>Users</th><th>Permissions</th><th class="actions-column">Actions</th></tr></thead>
<tbody>
<?php $__empty_1 = true; $__currentLoopData = $userTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<tr>
    <td><?php echo e($loop->iteration); ?></td>
    <td><strong><?php echo e($type->name); ?></strong></td>
    <td><?php echo e($type->description); ?></td>
    <td><span class="badge"><?php echo e($type->users_count); ?> users</span></td>
    <td><?php echo e(count($type->permissions??[])); ?> enabled</td>
    <td class="actions-column">
        <div class="row-actions">
            <?php if(auth()->user()->isSystemAdministrator() && auth()->user()->hasPermission('roles.manage')): ?>
                <a class="action-btn edit" href="<?php echo e(route('user-types.edit',$type)); ?>" title="Edit User Type" aria-label="Edit User Type">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                </a>
                <?php if($type->users_count===0): ?>
                    <form method="POST" action="<?php echo e(route('user-types.destroy',$type)); ?>" data-confirm="Delete this user type?" data-confirm-title="Delete User Type" data-confirm-ok="Delete">
                        <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                        <button class="action-btn delete" type="submit" title="Delete User Type" aria-label="Delete User Type">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                        </button>
                    </form>
                <?php endif; ?>
            <?php elseif(auth()->user()->canManageUserType($type)): ?>
                <a class="action-btn edit" href="<?php echo e(route('user-types.edit',$type)); ?>" title="Edit User Type" aria-label="Edit User Type">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                </a>
            <?php endif; ?>
        </div>
    </td>
</tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
<tr><td colspan="6" class="empty-state">No user types found.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\OmniChannel\OmniChannel_Inventory_Production_Updated\resources\views/user-types/index.blade.php ENDPATH**/ ?>