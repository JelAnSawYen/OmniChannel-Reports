@extends('layouts.guest', ['heading' => 'Set up two-factor authentication'])
@section('content')
        <p class="hint">Add this account using the key below, then enter the 6-digit code.</p>
        <div class="secret-box">{{ $secret }}</div>
        <p class="hint">Authenticator URI:</p>
        <div class="secret-box">{{ $otpauth }}</div>
        <form method="POST" action="{{ route('mfa.setup.confirm') }}">
            @csrf
            <input type="text" name="code" placeholder="6-digit code" inputmode="numeric" autocomplete="one-time-code" required>
            <button type="submit" class="login-button">Confirm and continue</button>
        </form>
@endsection
