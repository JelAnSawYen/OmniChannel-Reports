@extends('layouts.app')
@section('content')
<div class="page-head">
    <div><h1 class="page-title">User Types</h1><p class="page-subtitle">Manage roles, descriptions, and permission sets.</p></div>
    @if(auth()->user()->isSystemAdministrator() && auth()->user()->hasPermission('roles.manage'))
        <a href="{{ route('user-types.create') }}" class="plus-btn" title="Add User Type" aria-label="Add User Type">+</a>
    @endif
</div>

<div class="table-card table-wrap">
<table>
<thead><tr><th>Id</th><th>User Type</th><th>Description</th><th>Users</th><th>Permissions</th><th class="actions-column">Actions</th></tr></thead>
<tbody>
@forelse($userTypes as $type)
<tr>
    <td>{{ $loop->iteration }}</td>
    <td><strong>{{ $type->name }}</strong></td>
    <td>{{ $type->description }}</td>
    <td><span class="badge">{{ $type->users_count }} users</span></td>
    <td>{{ count($type->permissions??[]) }} enabled</td>
    <td class="actions-column">
        <div class="row-actions">
            @if(auth()->user()->isSystemAdministrator() && auth()->user()->hasPermission('roles.manage'))
                <a class="action-btn edit" href="{{ route('user-types.edit',$type) }}" title="Edit User Type" aria-label="Edit User Type">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                </a>
                @if($type->users_count===0)
                    <form method="POST" action="{{ route('user-types.destroy',$type) }}" data-confirm="Delete this user type?" data-confirm-title="Delete User Type" data-confirm-ok="Delete">
                        @csrf @method('DELETE')
                        <button class="action-btn delete" type="submit" title="Delete User Type" aria-label="Delete User Type">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                        </button>
                    </form>
                @endif
            @elseif(auth()->user()->canManageUserType($type))
                <a class="action-btn edit" href="{{ route('user-types.edit',$type) }}" title="Edit User Type" aria-label="Edit User Type">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                </a>
            @endif
        </div>
    </td>
</tr>
@empty
<tr><td colspan="6" class="empty-state">No user types found.</td></tr>
@endforelse
</tbody>
</table>
</div>
@endsection
