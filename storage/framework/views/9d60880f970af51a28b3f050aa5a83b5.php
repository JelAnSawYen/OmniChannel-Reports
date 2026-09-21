<?php $__env->startSection('content'); ?>
<div class="page-head"><div><h1 class="page-title">My Profile</h1><p class="page-subtitle">Manage your account information and password.</p></div></div>
<div class="dashboard-two">
    <div class="panel form-card">
        <h3 style="margin-top:0">Account Information</h3>
        <form method="POST" action="<?php echo e(route('profile.update')); ?>">
            <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
            <div class="form-group"><label>Full Name</label><input class="form-control" name="name" value="<?php echo e(old('name',$user->name)); ?>" required></div>
            <div class="form-group" style="margin-top:18.75px"><label>Email</label><input class="form-control" type="email" name="email" value="<?php echo e(old('email',$user->email)); ?>" required></div>
            <div class="form-group" style="margin-top:18.75px"><label>Current Password</label><input class="form-control" type="password" name="current_password" autocomplete="current-password"><p class="muted" style="margin:7.5px 0 0">Required only if you change your email address.</p></div>
            <div class="form-group" style="margin-top:18.75px"><label>User Type</label><input class="form-control" value="<?php echo e($user->userType?->name ?? 'Unassigned'); ?>" disabled></div>
            <?php if($user->requiresMfa()): ?>
                <p class="muted" style="margin-top:15px"><?php echo e($user->mfa_confirmed_at ? 'Two-factor authentication is enabled for this account.' : 'This role requires two-factor authentication at login.'); ?></p>
            <?php endif; ?>
            <div class="form-actions">
                <a class="btn secondary" href="<?php echo e($profileReturnUrl); ?>">Cancel</a>
                <button class="btn primary" type="submit">Save</button>
            </div>
        </form>
    </div>
    <div class="panel form-card">
        <h3 style="margin-top:0">Change Password</h3>
        <form method="POST" action="<?php echo e(route('profile.password')); ?>">
            <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
            <div class="form-group"><label>Current Password</label><input class="form-control" type="password" name="current_password" required></div>
            <div class="form-group" style="margin-top:18.75px"><label>New Password</label><input class="form-control" type="password" name="password" required><p class="muted" style="margin:7.5px 0 0">At least 12 characters, with upper and lower case letters, a number, and a symbol.</p></div>
            <div class="form-group" style="margin-top:18.75px"><label>Confirm New Password</label><input class="form-control" type="password" name="password_confirmation" required></div>
            <div class="form-actions"><button class="btn primary">Change Password</button></div>
        </form>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\OmniChannel\OmniChannel_Inventory_Production_Updated\resources\views/profile/index.blade.php ENDPATH**/ ?>