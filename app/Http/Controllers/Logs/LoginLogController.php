<?php

namespace App\Http\Controllers\Logs;

use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use App\Models\User;
use App\Services\Logs\AuditLogger;
use App\Services\Logs\LogRetentionService;
use App\Support\PageWindow;
use Illuminate\Http\Request;

class LoginLogController extends Controller
{
    public function index(Request $request)
    {
        LogRetentionService::pruneLoginHistory($request->user());

        $query = LoginLog::with('user')->latest();
        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($logs) use ($search) {
                $logs->where('email', 'like', '%'.$search.'%')
                    ->orWhereHas('user', fn ($users) => $users->where('name', 'like', '%'.$search.'%'));
            });
        }
        $perPage = PageWindow::perPage($request->query('per_page'));
        $logs = $query->paginate($perPage)->withQueryString();
        $canManageLogs = $request->user()?->canManageOwnActivityLogs() ?? false;

        return view('login-history.index', compact('logs', 'canManageLogs', 'perPage'));
    }

    public function clearOlder(Request $request)
    {
        $user = $this->manager($request);
        $deleted = LogRetentionService::pruneLoginHistory();

        if ($deleted > 0) {
            AuditLogger::log(
                'Cleared Logs',
                'Login History',
                $user->name.' cleared '.$deleted.' login history records older than '.LogRetentionService::DAYS.' days.',
                null,
                $request
            );
        }

        return back()->with('success', $deleted
            ? $deleted.' login history records older than '.LogRetentionService::DAYS.' days were cleared.'
            : 'No login history records older than '.LogRetentionService::DAYS.' days were found.');
    }

    private function manager(Request $request): User
    {
        $user = $request->user();
        abort_unless($user?->canManageOwnActivityLogs(), 403);

        return $user;
    }
}
