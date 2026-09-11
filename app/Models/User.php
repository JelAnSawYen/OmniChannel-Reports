<?php

namespace App\Models;

use App\Notifications\ResetUserPassword;
use App\Notifications\VerifyUserEmail;
use App\Support\RolePermissions;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'user_type_id',
        'permissions',
        'status',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
        'mfa_recovery_codes',
    ];

    public function userType()
    {
        return $this->belongsTo(UserType::class);
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->effectivePermissions(), true);
    }

    public function effectivePermissions(): array
    {
        $typePermissions = $this->userType?->permissions ?? [];
        $editable = array_keys(RolePermissions::EDIT_PERMISSIONS);
        $locked = $this->isStandardUser()
            ? RolePermissions::STANDARD_LOCKED_PERMISSIONS
            : [];

        if (! is_array($this->permissions)) {
            return array_values(array_diff($typePermissions, $locked));
        }

        $kept = array_values(array_diff($typePermissions, $editable));
        $custom = array_values(array_intersect($this->permissions, $editable));
        $effective = array_values(array_unique(array_merge($kept, $custom)));

        return array_values(array_diff($effective, $locked));
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];
        $letters = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $letters !== '' ? $letters : '?';
    }

    public function requiresMfa(): bool
    {
        if (! config('security.mfa_for_system_admin')) {
            return false;
        }

        if ($this->isAdministrator()) {
            return false;
        }

        return $this->hasPermission('users.manage')
            || $this->hasPermission('roles.manage')
            || $this->hasPermission('maintenance.manage');
    }

    public function canExportGatewaySecrets(): bool
    {
        return ! $this->isStandardUser();
    }

    public function isAdministrator(): bool
    {
        return $this->userType?->name === 'Administrator';
    }

    public function isStandardUser(): bool
    {
        return $this->userType?->name === 'Standard User';
    }

    public function canAccessAdministration(): bool
    {
        return $this->isAdministrator();
    }

    public function canAccessModule(string $module): bool
    {
        if (! $this->isStandardUser()) {
            return true;
        }

        return in_array($module, RolePermissions::STANDARD_ALLOWED_MODULES, true);
    }

    public function canManageOwnActivityLogs(): bool
    {
        return $this->canAccessAdministration() && $this->hasPermission('logs.view');
    }

    public function canMutateGateways(): bool
    {
        return $this->hasPermission('media.create')
            || $this->hasPermission('media.edit')
            || $this->hasPermission('media.delete');
    }

    public function canManageUser(User $target): bool
    {
        if ($this->isStandardUser() || ! $this->hasPermission('users.manage')) {
            return false;
        }

        return true;
    }

    public function routeNotificationForMail($notification = null): string
    {
        return $this->email;
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyUserEmail);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetUserPassword($token));
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'mfa_confirmed_at' => 'datetime',
            'mfa_secret' => 'encrypted',
            'mfa_recovery_codes' => 'array',
            'permissions' => 'array',
        ];
    }
}
