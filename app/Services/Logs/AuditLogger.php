<?php

namespace App\Services\Logs;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public static function log(string $action, string $module, string $description, $recordId = null, ?Request $request = null): void
    {
        if (in_array($action, AuditLog::EXCLUDED_ACTIONS, true)) {
            return;
        }

        try {
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => $action,
                'module' => $module,
                'record_id' => $recordId === null ? null : (string) $recordId,
                'description' => $description,
                'ip_address' => $request?->ip() ?? request()->ip(),
            ]);
        } catch (\Throwable) {
            // Audit logging must never break the user's original action.
        }
    }
}
