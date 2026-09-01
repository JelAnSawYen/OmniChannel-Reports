@extends('layouts.guest', ['heading' => 'Two-factor authentication'])
@section('content')
        <p class="hint">Enter the 6-digit code from your authenticator app, or a recovery code.</p>
        <form method="POST" action="{{ route('mfa.challenge.verify') }}">
            @csrf
            <input type="text" name="code" placeholder="Authentication code" autocomplete="one-time-code" required>
            <button type="submit" class="login-button">Verify</button>
        </form>
        <form method="POST" action="{{ route('logout') }}" style="margin-top:10px">
            @csrf
            <button type="submit" class="login-button">Logout</button>
        </form>
@endsection
