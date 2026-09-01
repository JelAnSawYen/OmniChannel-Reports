<?php
namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class AuditLogger
{
    public static function log(string $action, string $module, string $description, $recordId = null, ?Request $request = null): void
    {
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
