<?php

namespace App\Http\Controllers\Logs;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Logs\AuditLogger;
use App\Services\Logs\LogRetentionService;
use App\Support\PageWindow;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        LogRetentionService::pruneActivityLogs($request->user());

        $query = AuditLog::with('user')
            ->whereNotIn('action', AuditLog::EXCLUDED_ACTIONS)
            ->latest();
        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%$search%")
                    ->orWhere('action', 'like', "%$search%")
                    ->orWhere('module', 'like', "%$search%");
            });
        }
        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }
        $perPage = PageWindow::perPage($request->query('per_page'));
        $logs = $query->paginate($perPage)->withQueryString();
        $canManageLogs = $request->user()?->canManageOwnActivityLogs() ?? false;

        return view('audit-logs.index', compact('logs', 'canManageLogs', 'perPage'));
    }

    public function clearOlder(Request $request)
    {
        $user = $this->manager($request);
        $deleted = LogRetentionService::pruneActivityLogs();

        if ($deleted > 0) {
            AuditLogger::log(
                'Cleared Logs',
                'Activity Logs',
                $user->name.' cleared '.$deleted.' activity logs older than '.LogRetentionService::DAYS.' days.',
                null,
                $request
            );
        }

        return back()->with('success', $deleted
            ? $deleted.' activity logs older than '.LogRetentionService::DAYS.' days were cleared.'
            : 'No activity logs older than '.LogRetentionService::DAYS.' days were found.');
    }

    private function manager(Request $request): User
    {
        $user = $request->user();
        abort_unless($user?->canManageOwnActivityLogs(), 403);

        return $user;
    }
}
