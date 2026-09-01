@extends('layouts.guest', ['heading' => 'Recovery codes'])
@section('content')
        <p class="hint">Store these codes in a safe place. Each code can be used once if you lose access to your authenticator app.</p>
        <ul class="code-list">
            @foreach ($codes as $code)
                <li>{{ $code }}</li>
            @endforeach
        </ul>
        <form method="POST" action="{{ route('mfa.recovery.ack') }}">
            @csrf
            <button type="submit" class="login-button">I have saved these codes</button>
        </form>
@endsection
