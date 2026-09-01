<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Models\UserType;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserTypeController extends Controller
{
    public const PERMISSIONS=['dashboard.view'=>'View Dashboard','media.view'=>'View Media Gateways','media.create'=>'Add Media Gateways','media.edit'=>'Edit Media Gateways','media.delete'=>'Delete Media Gateways','media.export'=>'Export Data','users.view'=>'View Users','users.manage'=>'Manage Users','roles.view'=>'View User Types','roles.manage'=>'Manage User Types','logs.view'=>'View Activity Logs','maintenance.manage'=>'Manage Maintenance'];
    public const STANDARD_DATA_PERMISSIONS=['media.create','media.edit','media.delete'];
    public const STANDARD_LOCKED_PERMISSIONS=['users.manage','roles.manage'];
    public const EDIT_PERMISSIONS=[
        'media.create'=>['label'=>'Add / Create Records','description'=>'Create new records in the system.','icon'=>'plus'],
        'media.edit'=>['label'=>'Edit Records','description'=>'Modify existing records in the system.','icon'=>'edit'],
        'media.export'=>['label'=>'Export Data','description'=>'Export data from the system.','icon'=>'export'],
        'media.delete'=>['label'=>'Delete Records','description'=>'Delete records from the system.','icon'=>'delete'],
        'logs.view'=>['label'=>'View Activity Logs','description'=>'View system activity and audit logs.','icon'=>'logs'],
        'users.manage'=>['label'=>'Manage Users','description'=>'Create, edit, and manage system users.','icon'=>'users'],
        'roles.manage'=>['label'=>'Manage User Types','description'=>'Create, edit, and manage user types.','icon'=>'roles'],
    ];
    public function index()
    {
        $this->denyStandardUser();
        $userTypes=UserType::withCount('users')->orderBy('id')->get();
        return view('user-types.index',compact('userTypes'));
    }
    public function create()
    {
        if (! Auth::user()?->isSystemAdministrator()) {
            abort(403,'You do not have permission to perform this action.');
        }
        return view('user-types.create',['permissions'=>self::PERMISSIONS]);
    }
    public function store(Request $request)
    {
        if (! $request->user()?->isSystemAdministrator()) {
            abort(403,'You do not have permission to perform this action.');
        }
        $v=$request->validate(['name'=>'required|string|max:255|unique:user_types,name','description'=>'nullable|string|max:1000','permissions'=>'array']);
        if (strcasecmp($v['name'], 'System Administrator') === 0) {
            return back()->with('error','The System Administrator type cannot be duplicated.')->withInput();
        }
        $v['permissions']=array_values(array_intersect($v['permissions']??[],array_keys(self::PERMISSIONS)));
        $ut=UserType::create($v);
        AuditLogger::log('Created','User Types','Created user type '.$ut->name,$ut->id,$request);
        return redirect()->route('user-types.index')->with('success','User type added successfully.');
    }
    public function edit(Request $request, UserType $userType)
    {
        $this->denyStandardUser();
        if ($error=$this->protectType(Auth::user(), $userType)) {
            return redirect()->route('user-types.index')->with('error',$error);
        }
        $assignedQuery=$userType->users()->orderBy('name');
        if ($search=trim((string)$request->query('search'))) {
            $assignedQuery->where(fn ($q) => $q->where('name','like',"%{$search}%")->orWhere('email','like',"%{$search}%"));
        }
        $assignedUsers=$assignedQuery->paginate(10)->withQueryString();
        $selectedUser=null;
        if ($selectedId=(int)$request->query('user')) {
            $selectedUser=$assignedUsers->firstWhere('id', $selectedId)
                ?? $userType->users()->where('id', $selectedId)->first();
        }
        if (! $selectedUser && $assignedUsers->isNotEmpty()) {
            $selectedUser=$assignedUsers->first();
        }
        $selectedPermissions=$selectedUser?->effectivePermissions() ?? [];

        return view('user-types.edit',[
            'userType'=>$userType,
            'pageTitle'=>'Edit User Type: '.$userType->name,
            'permissions'=>self::EDIT_PERMISSIONS,
            'editableKeys'=>$this->editablePermissionKeys(Auth::user(), $userType, $selectedUser),
            'assignedUsers'=>$assignedUsers,
            'selectedUser'=>$selectedUser,
            'selectedPermissions'=>$selectedPermissions,
        ]);
    }
    public function update(Request $request,UserType $userType)
    {
        $this->denyStandardUser();
        if ($error=$this->protectType($request->user(), $userType)) {
            return redirect()->route('user-types.index')->with('error',$error);
        }
        $v=$request->validate([
            'user_id'=>'required|integer|exists:users,id',
            'permissions'=>'nullable|array',
            'permissions.*'=>'string',
        ]);
        $target=User::where('user_type_id', $userType->id)->where('id', $v['user_id'])->first();
        if (! $target) {
            return back()->with('error','Select a user assigned to this user type.');
        }
        $requested=array_values(array_intersect($v['permissions']??[],array_keys(self::PERMISSIONS)));
        if ($target->isSystemAdministrator() && ! $request->user()->isSystemAdministrator()) {
            return redirect()->route('user-types.index')->with('error','You cannot change a System Administrator account.');
        }
        $editable=$this->editablePermissionKeys($request->user(), $userType, $target);
        $permissions=array_values(array_intersect($requested, $editable));
        if ($target->isStandardUser()) {
            $permissions=array_values(array_diff($permissions, self::STANDARD_LOCKED_PERMISSIONS));
        }
        $previous=$target->permissions;
        $target->update(['permissions'=>$permissions]);
        AuditLogger::log('Updated Permissions','Users','Changed permissions for '.$target->name,$target->id,$request);
        if ($previous != $target->permissions) {
            AuditLogger::log(
                'Updated Permissions',
                'User Types',
                'Changed user permissions for '.$target->name.' ('.$userType->name.')',
                $userType->id,
                $request
            );
        }
        return redirect()->route('user-types.edit', array_filter([
            'userType' => $userType,
            'user' => $target->id,
            'search' => $request->filled('search') ? $request->input('search') : null,
            'page' => $request->filled('page') ? $request->input('page') : null,
        ]))->with('success','Permissions updated successfully.');
    }
    public function destroy(Request $request,UserType $userType)
    {
        if (! $request->user()?->isSystemAdministrator()) {
            abort(403,'You do not have permission to perform this action.');
        }
        if ($userType->isSystemAdministrator()) {
            return redirect()->route('user-types.index')->with('error','The System Administrator type cannot be deleted.');
        }
        if($userType->users()->exists())return redirect()->route('user-types.index')->with('error','You cannot delete a user type that is assigned to users.');
        $name=$userType->name;$id=$userType->id;$userType->delete();AuditLogger::log('Deleted','User Types','Deleted user type '.$name,$id,$request);return redirect()->route('user-types.index')->with('success','User type deleted successfully.');
    }

    private function protectType(?User $actor, UserType $userType): ?string
    {
        if (! $actor?->canManageUserType($userType)) {
            if ($userType->isSystemAdministrator()) {
                return 'You cannot change System Administrator permissions.';
            }

            return 'You are not allowed to manage this user type.';
        }

        return null;
    }

    private function editablePermissionKeys(?User $actor, UserType $userType, ?User $target = null): array
    {
        if (! $actor?->canManageUserType($userType)) {
            return [];
        }

        $keys = array_keys(self::EDIT_PERMISSIONS);
        if ($userType->isStandardUser() || $target?->isStandardUser()) {
            return array_values(array_diff($keys, self::STANDARD_LOCKED_PERMISSIONS));
        }

        return $keys;
    }

    private function denyStandardUser(): void
    {
        if (Auth::user()?->isStandardUser()) {
            abort(403, 'You do not have permission to perform this action.');
        }
    }
}
