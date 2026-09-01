<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Support\Carbon;

class LogRetentionService
{
    public const DAYS = 3;

    public static function cutoff(): Carbon
    {
        return now()->subDays(self::DAYS);
    }

    public static function prune(): array
    {
        return [
            'activity' => self::pruneActivityLogs(),
            'login' => self::pruneLoginHistory(),
        ];
    }

    public static function pruneActivityLogs(?User $user = null): int
    {
        $query = AuditLog::query()->where('created_at', '<', self::cutoff());
        if ($user) {
            $query->where('user_id', $user->id);
        }

        return $query->delete();
    }

    public static function pruneLoginHistory(?User $user = null): int
    {
        $query = LoginLog::query()->where('created_at', '<', self::cutoff());
        if ($user) {
            $query->where('user_id', $user->id);
        }

        return $query->delete();
    }
}
