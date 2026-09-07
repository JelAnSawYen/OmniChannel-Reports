<?php $__env->startSection('content'); ?>
        <p class="hint">Enter the email for your account. If it exists, a reset link will be sent.</p>
        <form method="POST" action="<?php echo e(route('password.email')); ?>">
            <?php echo csrf_field(); ?>
            <input type="email" name="email" placeholder="Email" value="<?php echo e(old('email')); ?>" autocomplete="email" required>
            <button type="submit" class="login-button">Send reset link</button>
        </form>
        <p style="margin:14px 0 0"><a class="card-link" href="<?php echo e(route('login')); ?>">Back to login</a></p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.guest', ['heading' => 'Reset password'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\OmniChannel\OmniChannel_Inventory_Production_Updated\resources\views/auth/forgot-password.blade.php ENDPATH**/ ?>