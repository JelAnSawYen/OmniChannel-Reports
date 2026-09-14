<?php

namespace App\Http\Controllers;

use App\Services\DashboardOverviewService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class ChannelUtilizationController extends Controller
{
    public function index(Request $request, DashboardOverviewService $dashboard): View
    {
        $search = trim((string) $request->query('search'));
        $perPage = (int) $request->query('per_page', 10);
        if (! in_array($perPage, [5, 10, 25, 50], true)) {
            $perPage = 10;
        }

        $rows = $dashboard->payload()['utilization_rows'];
        if ($search !== '') {
            $rows = array_values(array_filter($rows, function (array $row) use ($search) {
                return mb_stripos((string) $row['name'], $search) !== false;
            }));
        }

        $page = max(1, (int) $request->query('page', 1));
        $paginator = new LengthAwarePaginator(
            collect($rows)->forPage($page, $perPage)->values(),
            count($rows),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('channel-utilization.index', [
            'records' => $paginator,
            'totals' => [
                'total' => (int) array_sum(array_column($rows, 'total')),
                'sip' => (int) array_sum(array_column($rows, 'sip')),
                'gsm' => (int) array_sum(array_column($rows, 'gsm')),
            ],
            'search' => $search,
            'perPage' => $perPage,
        ]);
    }
}
