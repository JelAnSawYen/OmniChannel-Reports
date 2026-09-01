<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SessionInvalidator
{
    public static function forgetUser(int $userId, ?string $keepSessionId = null): void
    {
        if (config('session.driver') !== 'database' || ! Schema::hasTable(config('session.table', 'sessions'))) {
            return;
        }

        $query = DB::table(config('session.table', 'sessions'))->where('user_id', $userId);
        if ($keepSessionId) {
            $query->where('id', '!=', $keepSessionId);
        }
        $query->delete();
    }
}
