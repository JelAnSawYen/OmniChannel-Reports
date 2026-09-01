@extends('layouts.guest', ['heading' => 'Verify your email'])
@section('content')
        <p class="hint">Check your inbox for a verification link before using the system. For this local setup, the link is also written to storage/logs/laravel.log.</p>
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="login-button">Resend verification email</button>
        </form>
        <form method="POST" action="{{ route('logout') }}" style="margin-top:10px">
            @csrf
            <button type="submit" class="login-button">Logout</button>
        </form>
@endsection
