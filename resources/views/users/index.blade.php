@extends('layouts.app')
@section('content')
<div class="page-head">
    <div><h1 class="page-title">Users</h1><p class="page-subtitle">Manage system accounts, status, and access levels.</p></div>
</div>

<form class="filter-row search-filter-form" method="GET">
    <div class="search-box">
        <span class="search-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg></span>
        <input name="search" value="{{ request('search') }}" placeholder="Search Users" autocomplete="off">
    </div>
    <select class="select" name="user_type_id"><option value="">All User Types</option>@foreach($userTypes as $type)<option value="{{ $type->id }}" {{ request('user_type_id')==$type->id?'selected':'' }}>{{ $type->name }}</option>@endforeach</select>
    <select class="select" name="status"><option value="">All Statuses</option><option value="Active" {{ request('status')==='Active'?'selected':'' }}>Active</option><option value="Inactive" {{ request('status')==='Inactive'?'selected':'' }}>Inactive</option></select>
    <button class="btn primary" type="submit">Search</button>
    @if(auth()->user()->hasPermission('users.manage'))
        <a href="{{ route('users.create') }}" class="plus-btn" title="Add User" aria-label="Add User">+</a>
    @endif
</form>

<div class="table-card table-wrap">
<table>
<thead><tr><th>Id</th><th>Name</th><th>Email</th><th>User Type</th><th>Status</th><th>Last Login</th><th>Created</th><th class="actions-column">Actions</th></tr></thead>
<tbody>
@forelse($users as $user)
<tr>
    <td>{{ ($users->firstItem() ?? 1) + $loop->index }}</td>
    <td><strong>{{ $user->name }}</strong></td>
    <td>{{ $user->email }}</td>
    <td><span class="badge">{{ $user->userType?->name ?? 'Unassigned' }}</span></td>
    <td><span class="status-pill {{ strtolower($user->status)==='active'?'active':'inactive' }}">{{ $user->status }}</span></td>
    <td>{{ $user->last_login_at?->format('M d, Y h:i A') ?? 'Never' }}</td>
    <td>{{ $user->created_at->format('M d, Y') }}</td>
    <td class="actions-column">
        <div class="row-actions">
            @if(auth()->user()->hasPermission('users.manage') && auth()->user()->canManageUser($user))
                <a class="action-btn edit" href="{{ route('users.edit',$user) }}" title="Edit User" aria-label="Edit User">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                </a>
            @endif
            @if(auth()->user()->hasPermission('users.manage') && auth()->id()!==$user->id && auth()->user()->canManageUser($user))
                <form method="POST" action="{{ route('users.destroy',$user) }}" data-confirm="Delete this user?" data-confirm-title="Delete User" data-confirm-ok="Delete">
                    @csrf @method('DELETE')
                    <button class="action-btn delete" type="submit" title="Delete User" aria-label="Delete User">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                    </button>
                </form>
            @endif
        </div>
    </td>
</tr>
@empty
<tr><td colspan="8" class="empty-state">No users found.</td></tr>
@endforelse
</tbody>
</table>
<div class="table-footer">
    <span>{{ $users->firstItem()??0 }}-{{ $users->lastItem()??0 }} / {{ $users->total() }}</span>
    <div class="pager">
        @if($users->onFirstPage())<span class="page-number">‹</span>@else<a class="page-number" href="{{ $users->previousPageUrl() }}">‹</a>@endif
        @for($p=1;$p<=$users->lastPage();$p++)@if($p===$users->currentPage())<span class="page-number active">{{ $p }}</span>@else<a class="page-number" href="{{ $users->url($p) }}">{{ $p }}</a>@endif @endfor
        @if($users->hasMorePages())<a class="page-number" href="{{ $users->nextPageUrl() }}">›</a>@else<span class="page-number">›</span>@endif
    </div>
</div>
</div>
@endsection
