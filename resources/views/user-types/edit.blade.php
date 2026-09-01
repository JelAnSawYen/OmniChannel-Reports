@extends('layouts.app')
@section('content')
@php
    $selected = old('permissions', $selectedPermissions ?? []);
    $canSave = $selectedUser && count($editableKeys) > 0;
    $permissionIcons = [
        'plus' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"></rect><path d="M12 8v8"></path><path d="M8 12h8"></path></svg>',
        'edit' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>',
        'export' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21V9"></path><path d="m8 13 4-4 4 4"></path><path d="M4 5h16"></path></svg>',
        'delete' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>',
        'logs' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 6h13"></path><path d="M8 12h13"></path><path d="M8 18h13"></path><path d="M3 6h.01"></path><path d="M3 12h.01"></path><path d="M3 18h.01"></path></svg>',
        'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
        'roles' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3 4 7v6c0 5 3.4 7.8 8 9 4.6-1.2 8-4 8-9V7Z"></path></svg>',
    ];
@endphp
<div class="page-head">
    <div>
        <h1 class="page-title user-type-edit-title">
            <a class="page-back" href="{{ route('user-types.index') }}" aria-label="Back to User Types" title="Back to User Types">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18 9 12l6-6"></path></svg>
            </a>
            Edit User Type: <span class="title-accent">{{ $userType->name }}</span>
        </h1>
    </div>
</div>

<div class="user-type-edit-layout">
    <div class="table-card user-type-assigned-card">
        <form class="assigned-users-toolbar search-filter-form" method="GET" data-live-search>
            @if($selectedUser)<input type="hidden" name="user" value="{{ $selectedUser->id }}">@endif
            <div class="search-box">
                <span class="search-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg></span>
                <input name="search" value="{{ request('search') }}" placeholder="Search by name or email..." autocomplete="off">
                <button type="button" class="search-clear" data-clear-search aria-label="Clear search" title="Clear search">×</button>
            </div>
        </form>
        <form method="GET" data-select-user>
            @if(request('search') !== null && request('search') !== '')<input type="hidden" name="search" value="{{ request('search') }}">@endif
            <div class="table-wrap">
                <table class="user-pick-table">
                    <thead><tr><th></th><th>Name / Email</th><th>Role</th></tr></thead>
                    <tbody>
                    @forelse($assignedUsers as $assigned)
                    <tr class="{{ $selectedUser && $selectedUser->id === $assigned->id ? 'is-selected' : '' }}">
                        <td>
                            <input type="radio" name="user" value="{{ $assigned->id }}" {{ $selectedUser && $selectedUser->id === $assigned->id ? 'checked' : '' }} aria-label="Select {{ $assigned->name }}">
                        </td>
                        <td>
                            <span class="user-name-cell">
                                <span class="user-mini-avatar avatar-tone-{{ $assigned->id % 5 }}" aria-hidden="true">{{ $assigned->initials() }}</span>
                                <span class="user-name-copy">
                                    <strong>{{ $assigned->name }}</strong>
                                    <small>{{ $assigned->email }}</small>
                                </span>
                            </span>
                        </td>
                        <td>{{ $userType->name }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="empty-state">No users assigned to this user type.</td></tr>
                    @endforelse
                    </tbody>
                </table>
                <div class="table-footer">
                    <span>Showing {{ $assignedUsers->firstItem() ?? 0 }} to {{ $assignedUsers->lastItem() ?? 0 }} of {{ $assignedUsers->total() }} results</span>
                    <div class="pager">
                        @if($assignedUsers->onFirstPage())<span class="page-number">‹</span>@else<a class="page-number" href="{{ $assignedUsers->previousPageUrl() }}">‹</a>@endif
                        @for($p=1;$p<=$assignedUsers->lastPage();$p++)@if($p===$assignedUsers->currentPage())<span class="page-number active">{{ $p }}</span>@else<a class="page-number" href="{{ $assignedUsers->url($p) }}">{{ $p }}</a>@endif @endfor
                        @if($assignedUsers->hasMorePages())<a class="page-number" href="{{ $assignedUsers->nextPageUrl() }}">›</a>@else<span class="page-number">›</span>@endif
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="panel form-card user-type-permissions-card">
        @if($selectedUser)
            <div class="selected-user-head">
                <span class="user-mini-avatar large avatar-tone-{{ $selectedUser->id % 5 }}" aria-hidden="true">{{ $selectedUser->initials() }}</span>
                <div>
                    <strong>{{ $selectedUser->name }}</strong>
                    <small class="muted">{{ $selectedUser->email }}</small>
                </div>
            </div>
        @else
            <div class="selected-user-head">
                <div>
                    <strong>No user selected</strong>
                    <small class="muted">Select a user to view and edit permissions.</small>
                </div>
            </div>
        @endif
        <form method="POST" action="{{ route('user-types.update',$userType) }}" class="user-type-edit-form">
            @csrf
            @method('PUT')
            @if($selectedUser)<input type="hidden" name="user_id" value="{{ $selectedUser->id }}">@endif
            <input type="hidden" name="search" value="{{ request('search') }}">
            <input type="hidden" name="page" value="{{ request('page') }}">
            <h2 class="permission-list-title">User Permissions</h2>
            <div class="permission-list">
                @foreach($permissions as $key=>$meta)
                @php
                    $canEdit = $canSave && in_array($key, $editableKeys, true);
                    $isChecked = $canEdit && in_array($key, $selected, true);
                @endphp
                <label class="permission-item{{ $canEdit ? '' : ' is-locked' }}" @if(! $canEdit) aria-disabled="true" @endif>
                    <input type="checkbox" name="permissions[]" value="{{ $key }}" {{ $isChecked ? 'checked' : '' }} {{ $canEdit ? '' : 'disabled' }} @if(! $canEdit) tabindex="-1" @endif>
                    <span class="permission-icon">{!! $permissionIcons[$meta['icon']] ?? '' !!}</span>
                    <span><span>{{ $meta['label'] }}</span></span>
                </label>
                @endforeach
            </div>
            <div class="form-actions">
                <a class="btn secondary" href="{{ $selectedUser ? route('user-types.edit', array_filter(['userType'=>$userType,'user'=>$selectedUser->id,'search'=>request('search'),'page'=>request('page')])) : route('user-types.index') }}">Cancel</a>
                <button class="btn primary" type="submit" {{ $canSave ? '' : 'disabled' }}>
                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v13a2 2 0 0 1-2 2Z"></path><path d="M17 21v-8H7v8"></path><path d="M7 3v5h8"></path></svg>
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
