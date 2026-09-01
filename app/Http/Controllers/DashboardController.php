<?php
namespace App\Http\Controllers;

use App\Models\ArchiveRecording;
use App\Models\AuditLog;
use App\Models\DefectiveGsm;
use App\Models\GlobeSim;
use App\Models\MediaGateway;
use App\Models\PdcServer;
use App\Models\ProgramInboundNumber;
use App\Models\SignalBooster;
use App\Models\SmartSim;
use App\Models\User;
use App\Services\LogRetentionService;
use App\Services\SystemHealthService;
use App\Support\OperationCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $hour = (int) now()->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

        $gatewayTotal = MediaGateway::count();
        $pdcServers = PdcServer::count();
        $pdcActive = PdcServer::where('status', 'Active')->count();
        $globeSims = GlobeSim::count();
        $smartSims = SmartSim::count();
        $inboundNumbers = ProgramInboundNumber::count();
        $signalBoosters = SignalBooster::count();
        $signalActive = SignalBooster::where('status', 'Active')->count();
        $defectiveGsm = DefectiveGsm::count();
        $defectiveOpen = DefectiveGsm::whereIn('status', ['Open', 'In Repair'])->count();
        $archiveRecordings = ArchiveRecording::count();
        $archiveActive = ArchiveRecording::where('status', 'Active')->count();
        $users = User::count();
        $activeUsers = User::where('status', 'Active')->count();

        $kpis = [
            [
                'label' => 'GSM Gateways',
                'value' => $gatewayTotal,
                'note' => $gatewayTotal.' Configured',
                'tone' => 'blue',
                'href' => $user->hasPermission('media.view') ? route('gsm-gateways.index') : null,
                'spark' => $this->sparkline(MediaGateway::query()),
                'icon' => 'gsm',
            ],
            [
                'label' => 'PDC Servers',
                'value' => $pdcServers,
                'note' => $pdcActive.' Active',
                'tone' => 'purple',
                'href' => $user->hasPermission('media.view') ? route('pdc-servers') : null,
                'spark' => $this->sparkline(PdcServer::query()),
                'icon' => 'server',
            ],
            [
                'label' => 'SIM Inventory',
                'value' => $globeSims + $smartSims,
                'note' => $globeSims.' Globe / '.$smartSims.' Smart',
                'tone' => 'green',
                'href' => null,
                'modal' => $user->hasPermission('media.view') ? 'simInventoryModal' : null,
                'spark' => $this->sparkline(GlobeSim::query()),
                'icon' => 'sim',
            ],
            [
                'label' => 'Users',
                'value' => $users,
                'note' => $activeUsers.' Active Accounts',
                'tone' => 'orange',
                'href' => $user->hasPermission('users.view') ? route('users.index') : null,
                'spark' => $this->sparkline(User::query()),
                'icon' => 'users',
            ],
            [
                'label' => 'Inbound Numbers',
                'value' => $inboundNumbers,
                'note' => $inboundNumbers === 0 ? 'No Records' : $inboundNumbers.' Assigned',
                'tone' => 'sky',
                'href' => $user->hasPermission('media.view') ? route('program-inbound-numbers') : null,
                'spark' => $this->sparkline(ProgramInboundNumber::query()),
                'icon' => 'inbound',
            ],
            [
                'label' => 'Signal Boosters',
                'value' => $signalBoosters,
                'note' => $signalActive.' Active',
                'tone' => 'pink',
                'href' => $user->hasPermission('media.view') ? route('signal-boosters') : null,
                'spark' => $this->sparkline(SignalBooster::query()),
                'icon' => 'signal',
            ],
            [
                'label' => 'Defective GSM',
                'value' => $defectiveGsm,
                'note' => $defectiveOpen > 0 ? 'Requires Attention' : 'No Open Issues',
                'tone' => 'amber',
                'href' => $user->hasPermission('media.view') ? route('defective-gsm') : null,
                'spark' => $this->sparkline(DefectiveGsm::query()),
                'icon' => 'alert',
            ],
            [
                'label' => 'Archive Recordings',
                'value' => $archiveRecordings,
                'note' => $archiveActive.' Archived',
                'tone' => 'navy',
                'href' => $user->hasPermission('media.view') ? route('archive-recordings') : null,
                'spark' => $this->sparkline(ArchiveRecording::query()),
                'icon' => 'archive',
            ],
        ];

        $locationOverview = collect(OperationCatalog::locations())
            ->map(function (string $name, string $slug) use ($user) {
                $sites = MediaGateway::where('site_name', $name)->count();

                return [
                    'slug' => $slug,
                    'name' => $name,
                    'value' => $sites,
                    'tone' => $sites > 0 ? 'green' : 'amber',
                    'href' => $user->hasPermission('media.view') ? route('program-location.show', $slug) : null,
                ];
            })
            ->values()
            ->all();

        $networkItems = [
            ['label' => 'GSM Gateways', 'value' => $gatewayTotal, 'color' => '#3b82f6'],
            ['label' => 'PDC Servers', 'value' => $pdcServers, 'color' => '#8b5cf6'],
            ['label' => 'SIM Inventory', 'value' => $globeSims + $smartSims, 'color' => '#22c55e'],
            ['label' => 'Users', 'value' => $users, 'color' => '#f97316'],
            ['label' => 'Inbound Numbers', 'value' => $inboundNumbers, 'color' => '#38bdf8'],
            ['label' => 'Signal Boosters', 'value' => $signalBoosters, 'color' => '#ec4899'],
            ['label' => 'Defective GSM', 'value' => $defectiveGsm, 'color' => '#dc2626'],
            ['label' => 'Archive Recordings', 'value' => $archiveRecordings, 'color' => '#92400e'],
        ];
        $totalAssets = (int) collect($networkItems)->sum('value');
        $networkItems = collect($networkItems)->map(function ($item) use ($totalAssets) {
            $item['percent'] = $totalAssets > 0 ? round(($item['value'] / $totalAssets) * 100, 1) : 0;

            return $item;
        })->all();

        $health = app(SystemHealthService::class)->summary();
        $healthItems = $health['items'];
        $healthTotal = $health['total'];
        $healthPercent = $health['percent'];
        $healthTier = $health['tier'];

        LogRetentionService::pruneActivityLogs($user);

        $recentActivities = AuditLog::with('user')
            ->where('module', '!=', 'Authentication')
            ->whereNotIn('action', ['Login', 'Logout'])
            ->latest()
            ->limit(6)
            ->get();

        return view('dashboard.index', compact(
            'greeting', 'kpis', 'locationOverview', 'networkItems', 'totalAssets',
            'healthItems', 'healthTotal', 'healthPercent', 'healthTier', 'recentActivities',
            'globeSims', 'smartSims'
        ));
    }

    private function sparkline(Builder $query): array
    {
        $days = 7;
        $start = now()->subDays($days - 1)->startOfDay();
        $counts = $query->clone()
            ->where('created_at', '>=', $start)
            ->selectRaw('date(created_at) as day, count(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $values = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now()->subDays($i)->toDateString();
            $values[] = (int) ($counts[$day] ?? 0);
        }

        $width = 72;
        $height = 28;
        $max = max(1, max($values));
        $count = count($values);
        $parts = [];
        foreach ($values as $index => $value) {
            $x = $count === 1 ? $width / 2 : ($index / ($count - 1)) * $width;
            $y = $height - (($value / $max) * ($height - 6)) - 3;
            $parts[] = ($index === 0 ? 'M' : 'L').round($x, 1).','.round($y, 1);
        }

        return ['values' => $values, 'path' => implode(' ', $parts)];
    }
}
