<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\SystemHealthService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class SystemHealthController extends Controller
{
    public function index(Request $request, SystemHealthService $health)
    {
        $summary = $health->summary();
        $status = strtolower(trim((string) $request->query('status', '')));
        if (! in_array($status, SystemHealthService::TIERS, true)) {
            $status = '';
        }

        $search = trim((string) $request->query('search', ''));
        $perPage = (int) $request->query('per_page', 10);
        if (! in_array($perPage, [10, 25, 50], true)) {
            $perPage = 10;
        }

        $modules = collect($health->modules());
        if ($status !== '') {
            $modules = $modules->where('status', $status)->values();
        }
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $modules = $modules->filter(
                fn (array $module) => str_contains(mb_strtolower($module['module']), $needle)
            )->values();
        }

        $page = max(1, (int) $request->query('page', 1));
        $items = new LengthAwarePaginator(
            $modules->forPage($page, $perPage)->values(),
            $modules->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('system-health.index', [
            'summary' => $summary,
            'status' => $status,
            'search' => $search,
            'perPage' => $perPage,
            'items' => $items,
            'updatedAt' => now(),
        ]);
    }
}
