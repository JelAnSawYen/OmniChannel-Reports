<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const PROTECTED_MODULES = ['Authentication', 'Users', 'User Types', 'Maintenance', 'Profile'];

    public const EXCLUDED_ACTIONS = ['Login', 'Logout', 'Failed Login', 'Created Backup'];

    protected $fillable = ['user_id', 'action', 'module', 'record_id', 'description', 'ip_address'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isProtected(): bool
    {
        return in_array($this->module, self::PROTECTED_MODULES, true);
    }

    public function canBeDeletedBy(?User $user): bool
    {
        return $user
            && $user->canManageOwnActivityLogs()
            && (int) $this->user_id === (int) $user->id;
    }
}
