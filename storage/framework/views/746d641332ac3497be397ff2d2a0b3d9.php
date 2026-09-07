
<?php $__env->startSection('content'); ?>
        <form method="POST" action="<?php echo e(route('login.submit')); ?>">
            <?php echo csrf_field(); ?>
            <input type="email" name="email" placeholder="Email" value="<?php echo e(old('email')); ?>" autocomplete="email" required>
            <div class="password-wrapper">
                <input type="password" name="password" id="password" placeholder="Password" autocomplete="current-password" required>
                <button type="button" class="toggle-password" onclick="togglePassword()">Show</button>
            </div>
            <button type="submit" class="login-button">Login</button>
        </form>
        <p style="margin:14px 0 0"><a class="card-link" href="<?php echo e(route('password.request')); ?>">Forgot password?</a></p>
    <script>
        function togglePassword() {
            const password = document.getElementById('password');
            const button = document.querySelector('.toggle-password');
            if (password.type === 'password') {
                password.type = 'text';
                button.textContent = 'Hide';
            } else {
                password.type = 'password';
                button.textContent = 'Show';
            }
        }
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.guest', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\OmniChannel\OmniChannel_Inventory_Production_Updated\resources\views/auth/login.blade.php ENDPATH**/ ?>