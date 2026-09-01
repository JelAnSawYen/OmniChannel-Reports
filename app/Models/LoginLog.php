<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LoginLog extends Model
{
    protected $fillable = ['user_id', 'email', 'status', 'ip_address', 'user_agent'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function canBeDeletedBy(?User $user): bool
    {
        return $user
            && $user->canManageOwnActivityLogs()
            && (int) $this->user_id === (int) $user->id;
    }
}
