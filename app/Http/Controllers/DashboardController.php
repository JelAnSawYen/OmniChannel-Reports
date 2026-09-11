<?php
namespace App\Http\Controllers;

use App\Services\DashboardOverviewService;
use App\Services\LogRetentionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        LogRetentionService::pruneActivityLogs($user);

        $overview = app(DashboardOverviewService::class)->payload();
        $hour = (int) now()->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

        return view('dashboard.index', [
            'overview' => $overview,
            'greeting' => $greeting,
            'roleName' => $user->userType?->name ?? '',
            'currentDate' => now()->format('F j, Y'),
            'globeSims' => $overview['kpis']['globe']['value'],
            'smartSims' => $overview['kpis']['smart']['value'],
        ]);
    }

    public function snapshot(): JsonResponse
    {
        return response()->json(app(DashboardOverviewService::class)->payload());
    }
}
