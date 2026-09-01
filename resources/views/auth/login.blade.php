@extends('layouts.guest')
@section('content')
        <form method="POST" action="{{ route('login.submit') }}">
            @csrf
            <input type="email" name="email" placeholder="Email" value="{{ old('email') }}" autocomplete="email" required>
            <div class="password-wrapper">
                <input type="password" name="password" id="password" placeholder="Password" autocomplete="current-password" required>
                <button type="button" class="toggle-password" onclick="togglePassword()">Show</button>
            </div>
            <button type="submit" class="login-button">Login</button>
        </form>
        <p style="margin:14px 0 0"><a class="card-link" href="{{ route('password.request') }}">Forgot password?</a></p>
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
@endsection
