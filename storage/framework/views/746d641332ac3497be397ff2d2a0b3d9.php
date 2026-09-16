
<?php $__env->startSection('content'); ?>
        <form method="POST" action="<?php echo e(route('login.submit')); ?>">
            <?php echo csrf_field(); ?>
            <div class="input-with-icon">
                <svg class="field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                <input type="email" name="email" placeholder="Email" value="<?php echo e(old('email')); ?>" autocomplete="email" required>
            </div>
            <div class="password-wrapper input-with-icon">
                <svg class="field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
                <input type="password" name="password" id="password" placeholder="Password" autocomplete="current-password" required>
                <button type="button" class="toggle-password" id="togglePassword" aria-label="Show password" hidden>
                    <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/><path d="M21 3L3 21"/></svg>
                </button>
            </div>
            <button type="submit" class="login-button">Login</button>
        </form>
        <p style="margin:17.5px 0 0"><a class="card-link" href="<?php echo e(route('password.request')); ?>">Forgot password?</a></p>
    <script>
        function syncPasswordToggle() {
            const password = document.getElementById('password');
            const button = document.getElementById('togglePassword');
            const hasValue = password.value.length > 0;
            button.classList.toggle('has-value', hasValue);
            button.hidden = !hasValue;
            if (!hasValue && password.type !== 'password') {
                password.type = 'password';
            }
            syncPasswordIcon(password, button);
        }
        function syncPasswordIcon(password, button) {
            const visible = password.type === 'text';
            button.classList.toggle('is-visible', visible);
            button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
        }
        function toggleLoginPassword(event) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            const password = document.getElementById('password');
            const button = document.getElementById('togglePassword');
            if (!password.value.length) return;
            password.type = password.type === 'password' ? 'text' : 'password';
            syncPasswordIcon(password, button);
        }
        document.getElementById('togglePassword').addEventListener('click', toggleLoginPassword);
        document.getElementById('password').addEventListener('input', syncPasswordToggle);
        document.getElementById('password').addEventListener('change', syncPasswordToggle);
        document.getElementById('password').addEventListener('keyup', syncPasswordToggle);
        syncPasswordToggle();
        setTimeout(syncPasswordToggle, 200);
    </script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.guest', ['title' => 'OmniChannel Inventory', 'heading' => 'Support Services Group'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\OmniChannel\OmniChannel_Inventory_Production_Updated\resources\views/auth/login.blade.php ENDPATH**/ ?>