<?php $__env->startSection('content'); ?>
        <p class="hint">Add this account using the key below, then enter the 6-digit code.</p>
        <div class="secret-box"><?php echo e($secret); ?></div>
        <p class="hint">Authenticator URI:</p>
        <div class="secret-box"><?php echo e($otpauth); ?></div>
        <form method="POST" action="<?php echo e(route('mfa.setup.confirm')); ?>">
            <?php echo csrf_field(); ?>
            <input type="text" name="code" placeholder="6-digit code" inputmode="numeric" autocomplete="one-time-code" required>
            <button type="submit" class="login-button">Confirm and continue</button>
        </form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.guest', ['heading' => 'Set up two-factor authentication'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\OmniChannel\OmniChannel_Inventory_Production_Updated\resources\views/auth/mfa-setup.blade.php ENDPATH**/ ?>