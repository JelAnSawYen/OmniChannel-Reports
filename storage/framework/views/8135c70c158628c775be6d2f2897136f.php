<?php $__env->startSection('content'); ?>
<div class="page-head">
    <div><h1 class="page-title">Users</h1><p class="page-subtitle">Manage system accounts, status, and access levels.</p></div>
    <?php if(auth()->user()->hasPermission('users.manage')): ?>
        <a href="<?php echo e(route('users.create')); ?>" class="plus-btn" title="Add User" aria-label="Add User">+</a>
    <?php endif; ?>
</div>

<form class="filter-row search-filter-form" method="GET">
    <div class="search-box">
        <span class="search-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg></span>
        <input name="search" value="<?php echo e(request('search')); ?>" placeholder="Search Users" autocomplete="off">
        <button type="button" class="search-clear" data-clear-search aria-label="Clear search" title="Clear search">×</button>
    </div>
    <select class="select" name="user_type_id"><option value="">All User Types</option><?php $__currentLoopData = $userTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($type->id); ?>" <?php echo e(request('user_type_id')==$type->id?'selected':''); ?>><?php echo e($type->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select>
    <select class="select" name="status"><option value="">All Statuses</option><option value="Active" <?php echo e(request('status')==='Active'?'selected':''); ?>>Active</option><option value="Inactive" <?php echo e(request('status')==='Inactive'?'selected':''); ?>>Inactive</option></select>
    <button class="btn primary" type="submit">Search</button>
    <?php if(request()->hasAny(['search','user_type_id','status'])): ?><a class="btn secondary" href="<?php echo e(route('users.index')); ?>">Reset</a><?php endif; ?>
</form>

<div class="table-card table-wrap">
<table>
<thead><tr><th>Id</th><th>Name</th><th>Email</th><th>User Type</th><th>Status</th><th>Last Login</th><th>Created</th><th class="actions-column">Actions</th></tr></thead>
<tbody>
<?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<tr>
    <td><?php echo e(($users->firstItem() ?? 1) + $loop->index); ?></td>
    <td><strong><?php echo e($user->name); ?></strong></td>
    <td><?php echo e($user->email); ?></td>
    <td><span class="badge"><?php echo e($user->userType?->name ?? 'Unassigned'); ?></span></td>
    <td><span class="status-pill <?php echo e(strtolower($user->status)==='active'?'active':'inactive'); ?>"><?php echo e($user->status); ?></span></td>
    <td><?php echo e($user->last_login_at?->format('M d, Y h:i A') ?? 'Never'); ?></td>
    <td><?php echo e($user->created_at->format('M d, Y')); ?></td>
    <td class="actions-column">
        <div class="row-actions">
            <?php if(auth()->user()->hasPermission('users.manage') && auth()->user()->canManageUser($user)): ?>
                <a class="action-btn edit" href="<?php echo e(route('users.edit',$user)); ?>" title="Edit User" aria-label="Edit User">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                </a>
            <?php endif; ?>
            <?php if(auth()->user()->hasPermission('users.manage') && auth()->id()!==$user->id && auth()->user()->canManageUser($user)): ?>
                <form method="POST" action="<?php echo e(route('users.destroy',$user)); ?>" data-confirm="Delete this user?" data-confirm-title="Delete User" data-confirm-ok="Delete">
                    <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                    <button class="action-btn delete" type="submit" title="Delete User" aria-label="Delete User">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </td>
</tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
<tr><td colspan="8" class="empty-state">No users found.</td></tr>
<?php endif; ?>
</tbody>
</table>
<div class="table-footer">
    <span><?php echo e($users->firstItem()??0); ?>-<?php echo e($users->lastItem()??0); ?> / <?php echo e($users->total()); ?></span>
    <div class="pager">
        <?php if($users->onFirstPage()): ?><span class="page-number">‹</span><?php else: ?><a class="page-number" href="<?php echo e($users->previousPageUrl()); ?>">‹</a><?php endif; ?>
        <?php for($p=1;$p<=$users->lastPage();$p++): ?><?php if($p===$users->currentPage()): ?><span class="page-number active"><?php echo e($p); ?></span><?php else: ?><a class="page-number" href="<?php echo e($users->url($p)); ?>"><?php echo e($p); ?></a><?php endif; ?> <?php endfor; ?>
        <?php if($users->hasMorePages()): ?><a class="page-number" href="<?php echo e($users->nextPageUrl()); ?>">›</a><?php else: ?><span class="page-number">›</span><?php endif; ?>
    </div>
</div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\OmniChannel\OmniChannel_Inventory_Production_Updated\resources\views/users/index.blade.php ENDPATH**/ ?>