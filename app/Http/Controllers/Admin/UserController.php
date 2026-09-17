<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HandlesBulkDestroy;
use App\Models\MailSetting;
use App\Models\User;
use App\Models\UserType;
use App\Services\Logs\AuditLogger;
use App\Support\MailFailure;
use App\Support\PageWindow;
use App\Support\PasswordRules;
use App\Support\SessionInvalidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    use HandlesBulkDestroy;

    public function index(Request $request)
    {
        $this->denyStandardUser();
        $query = User::with('userType')->orderBy('name');
        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($users) use ($search) {
                $users->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
            });
        }
        if ($type = $request->query('user_type_id')) {
            $query->where('user_type_id', $type);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        $perPage = PageWindow::perPage($request->query('per_page'));
        $users = $query->paginate($perPage)->withQueryString();
        $userTypes = $this->assignableTypes();

        return view('users.index', compact('users', 'userTypes', 'perPage'));
    }

    public function create()
    {
        $this->denyStandardUser();

        return view('users.create', ['userTypes' => $this->assignableTypes()]);
    }

    public function store(Request $request)
    {
        $this->denyStandardUser();
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => PasswordRules::required(),
            'user_type_id' => 'required|exists:user_types,id',
            'status' => 'required|in:Active,Inactive',
        ]);
        if ($error = $this->assignmentError((int) $v['user_type_id'])) {
            return back()->with('error', $error)->withInput();
        }
        $user = User::create($v);
        AuditLogger::log('Created', 'Users', 'Created user '.$user->name, $user->id, $request);

        return redirect()->route('users.index')->with($this->verificationSendResult($user, 'User added successfully.'));
    }

    public function edit(User $user)
    {
        $this->denyStandardUser();
        if ($error = $this->protectManagedUser(Auth::user(), $user)) {
            return redirect()->route('users.index')->with('error', $error);
        }

        return view('users.edit', ['user' => $user, 'userTypes' => $this->assignableTypes()]);
    }

    public function update(Request $request, User $user)
    {
        $this->denyStandardUser();
        if ($error = $this->protectManagedUser($request->user(), $user)) {
            return redirect()->route('users.index')->with('error', $error);
        }
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'user_type_id' => 'required|exists:user_types,id',
            'status' => 'required|in:Active,Inactive',
            'password' => PasswordRules::optional(),
        ]);
        if ($error = $this->assignmentError((int) $v['user_type_id'])) {
            return back()->with('error', $error)->withInput();
        }
        $emailChanged = $user->email !== $v['email'];
        $user->name = $v['name'];
        $user->email = $v['email'];
        $user->user_type_id = $v['user_type_id'];
        $user->status = $v['status'];
        if (! empty($v['password'])) {
            $user->password = $v['password'];
        }
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->save();
        if (! empty($v['password'])) {
            SessionInvalidator::forgetUser((int) $user->id);
        }
        AuditLogger::log('Updated', 'Users', 'Updated user '.$user->name, $user->id, $request);
        if ($emailChanged) {
            return redirect()->route('users.index')->with($this->verificationSendResult($user, 'User updated successfully. The new address must be verified.'));
        }

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user)
    {
        $this->denyStandardUser();
        if ($error = $this->protectManagedUser($request->user(), $user)) {
            return redirect()->route('users.index')->with('error', $error);
        }
        if (Auth::id() === $user->id) {
            return redirect()->route('users.index')->with('error', 'You cannot delete your own account.');
        }
        $name = $user->name;
        $id = $user->id;
        $user->delete();
        AuditLogger::log('Deleted', 'Users', 'Deleted user '.$name, $id, $request);

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $this->denyStandardUser();
        $deleted = 0;
        $error = null;
        foreach ($this->validatedBulkIds($request) as $id) {
            $user = User::query()->find($id);
            if (! $user) {
                continue;
            }
            if ($blocked = $this->protectManagedUser($request->user(), $user)) {
                $error = $blocked;

                continue;
            }
            if (Auth::id() === $user->id) {
                $error = 'You cannot delete your own account.';

                continue;
            }
            $name = $user->name;
            $user->delete();
            AuditLogger::log('Deleted', 'Users', 'Deleted user '.$name, $id, $request);
            $deleted++;
        }

        if ($deleted === 0) {
            return redirect()->route('users.index')->with('error', $error ?: 'No users were deleted.');
        }

        return redirect()->route('users.index')->with('success', 'Selected users deleted successfully.');
    }

    public function resendVerification(Request $request, User $user)
    {
        $this->denyStandardUser();
        if ($error = $this->protectManagedUser($request->user(), $user)) {
            return redirect()->route('users.index')->with('error', $error);
        }
        if ($user->hasVerifiedEmail()) {
            return redirect()->route('users.edit', $user)->with('success', 'This email is already verified.');
        }

        return redirect()->route('users.edit', $user)->with($this->verificationSendResult($user, 'Verification email sent.'));
    }

    private function denyStandardUser(): void
    {
        if (Auth::user()?->isStandardUser()) {
            abort(403, 'You do not have permission to perform this action.');
        }
    }

    private function assignableTypes()
    {
        return UserType::assignable();
    }

    private function protectManagedUser(User $actor, User $target): ?string
    {
        if (! $actor->canManageUser($target)) {
            return 'You do not have permission to manage this user.';
        }

        return null;
    }

    private function assignmentError(int $userTypeId): ?string
    {
        $type = UserType::find($userTypeId);
        if (! $type) {
            return 'The selected user type is invalid.';
        }
        if (! $type->isAssignable()) {
            return 'You can only assign Administrator or Standard User roles.';
        }

        return null;
    }

    /**
     * @return array{success?: string, error?: string}
     */
    private function verificationSendResult(User $user, string $okPrefix): array
    {
        if (! MailSetting::isConfigured()) {
            return ['error' => $okPrefix.' Outgoing email is not configured, so nothing was sent to '.$user->email.'. Save one sending mailbox in Maintenance → Email Delivery.'];
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            return ['error' => $okPrefix.' The verification email to '.$user->email.' was not sent. '.MailFailure::message($e)];
        }

        return ['success' => $okPrefix.' A verification link was sent to '.$user->email.'.'];
    }
}
