@extends('layouts.guest', ['heading' => 'Set a new password'])
@section('content')
        <p class="hint">Use at least 12 characters with upper and lower case letters, a number, and a symbol.</p>
        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="email" name="email" placeholder="Email" value="{{ old('email', $email) }}" autocomplete="email" required>
            <input type="password" name="password" placeholder="New password" autocomplete="new-password" required>
            <input type="password" name="password_confirmation" placeholder="Confirm password" autocomplete="new-password" required>
            <button type="submit" class="login-button">Reset password</button>
        </form>
@endsection
