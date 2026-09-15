@extends('layouts.app')
@section('content')
<div class="page-head"><div><h1 class="page-title">My Profile</h1><p class="page-subtitle">Manage your account information and password.</p></div></div>
<div class="dashboard-two">
    <div class="panel form-card">
        <h3 style="margin-top:0">Account Information</h3>
        <form method="POST" action="{{ route('profile.update') }}">
            @csrf @method('PUT')
            <div class="form-group"><label>Full Name</label><input class="form-control" name="name" value="{{ old('name',$user->name) }}" required></div>
            <div class="form-group" style="margin-top:18.75px"><label>Email</label><input class="form-control" type="email" name="email" value="{{ old('email',$user->email) }}" required></div>
            <div class="form-group" style="margin-top:18.75px"><label>Current Password</label><input class="form-control" type="password" name="current_password" autocomplete="current-password"><p class="muted" style="margin:7.5px 0 0">Required only if you change your email address.</p></div>
            <div class="form-group" style="margin-top:18.75px"><label>User Type</label><input class="form-control" value="{{ $user->userType?->name ?? 'Unassigned' }}" disabled></div>
            @if($user->requiresMfa())
                <p class="muted" style="margin-top:15px">{{ $user->mfa_confirmed_at ? 'Two-factor authentication is enabled for this account.' : 'This role requires two-factor authentication at login.' }}</p>
            @endif
            <div class="form-actions">
                <a class="btn secondary" href="{{ $profileReturnUrl }}">Cancel</a>
                <button class="btn primary" type="submit">Save</button>
            </div>
        </form>
    </div>
    <div class="panel form-card">
        <h3 style="margin-top:0">Change Password</h3>
        <form method="POST" action="{{ route('profile.password') }}">
            @csrf @method('PUT')
            <div class="form-group"><label>Current Password</label><input class="form-control" type="password" name="current_password" required></div>
            <div class="form-group" style="margin-top:18.75px"><label>New Password</label><input class="form-control" type="password" name="password" required><p class="muted" style="margin:7.5px 0 0">At least 12 characters, with upper and lower case letters, a number, and a symbol.</p></div>
            <div class="form-group" style="margin-top:18.75px"><label>Confirm New Password</label><input class="form-control" type="password" name="password_confirmation" required></div>
            <div class="form-actions"><button class="btn primary">Change Password</button></div>
        </form>
    </div>
</div>
@endsection
