<?php
namespace App\Http\Controllers;
use App\Models\LoginLog;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LogRetentionService;
use Illuminate\Http\Request;

class LoginLogController extends Controller
{
    public function index(Request $request)
    {
        LogRetentionService::pruneLoginHistory($request->user());

        $query = LoginLog::with('user')->latest();
        if ($search = trim((string)$request->query('search'))) $query->where('email','like',"%$search%");
        $logs = $query->paginate(15)->withQueryString();
        $canManageLogs = $request->user()?->canManageOwnActivityLogs() ?? false;
        return view('login-logs.index', compact('logs', 'canManageLogs'));
    }

    public function clearOlder(Request $request)
    {
        $user = $this->manager($request);
        $deleted = LogRetentionService::pruneLoginHistory($user);

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
