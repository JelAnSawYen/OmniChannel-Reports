@extends('layouts.guest', ['heading' => 'Reset password'])
@section('content')
        <p class="hint">Enter the email for your account. If it exists, a reset link will be sent.</p>
        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <input type="email" name="email" placeholder="Email" value="{{ old('email') }}" autocomplete="email" required>
            <button type="submit" class="login-button">Send reset link</button>
        </form>
        <p style="margin:14px 0 0"><a class="card-link" href="{{ route('login') }}">Back to login</a></p>
@endsection
