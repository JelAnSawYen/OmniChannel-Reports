<?php

namespace App\Http\Controllers;

use App\Models\ChannelPort;
use App\Models\ChannelPrefix;
use App\Models\MediaGateway;
use App\Models\NetworkPrefix;
use App\Models\TelcoCost;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use App\Services\OperationalAlerts;
use App\Services\XlsxService;
use App\Support\PublicError;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index()
    {
        return view('reports.index', $this->snapshot());
    }

    public function export(Request $request, XlsxService $xlsx, string $type)
    {
        abort_unless(in_array($type, ['inventory', 'telco', 'contracts'], true), 404);

        $data = $this->snapshot();

        [$headers, $rows, $filename, $label] = match ($type) {
            'inventory' => [
                ['Item', 'Count'],
                [
                    ['Media Gateways', $data['gatewayTotal']],
                    ['Channel Prefixes', $data['channelPrefixes']],
                    ['Channel Ports', $data['ports']['total']],
                    ['Ports In Use', $data['ports']['in_use']],
                    ['Ports Available', $data['ports']['available']],
                    ['Ports Disabled', $data['ports']['disabled']],
                    ['Port Utilization %', $data['ports']['utilization']],
                    ['Network Prefixes', $data['networkPrefixes']],
                ],
                'operations-inventory.xlsx',
                'inventory snapshot',
            ],
            'telco' => [
                ['Provider', 'Active Monthly Cost', 'Services'],
                $data['telcoByProvider']->map(fn ($row) => [
                    $row->provider,
                    number_format((float) $row->monthly_cost, 2, '.', ''),
                    $row->services,
                ]),
                'telco-cost-by-provider.xlsx',
                'telco costs by provider',
            ],
            default => [
                ['Provider', 'Site', 'Service Type', 'Monthly Cost', 'Contract End', 'Status'],
                $data['expiringContracts']->map(fn (TelcoCost $row) => [
                    $row->provider,
                    $row->site,
                    $row->service_type,
                    number_format((float) $row->monthly_cost, 2, '.', ''),
                    optional($row->contract_end)?->format('Y-m-d'),
                    $row->status,
                ]),
                'expiring-telco-contracts.xlsx',
                'expiring telco contracts',
            ],
        };

        try {
            $path = $xlsx->export($headers, $rows, $filename);
        } catch (\Throwable $exception) {
            NotificationService::exportFailed('Reports', 'Export failed.', 'reports');
            return back()->with('error', PublicError::failed('Export', $exception));
        }
        AuditLogger::log('Exported', 'Reports', 'Exported '.$label, null, $request);

        return response()->download($path, $filename)->deleteFileAfterSend(true);
    }

    private function snapshot(): array
    {
        $telcoByProvider = TelcoCost::query()
            ->where('status', 'Active')
            ->selectRaw('provider, SUM(monthly_cost) as monthly_cost, COUNT(*) as services')
            ->groupBy('provider')
            ->orderByDesc('monthly_cost')
            ->get();

        $expiringContracts = TelcoCost::query()
            ->whereNotNull('contract_end')
            ->whereBetween('contract_end', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->orderBy('contract_end')
            ->get();

        return [
            'gatewayTotal' => MediaGateway::count(),
            'channelPrefixes' => ChannelPrefix::count(),
            'networkPrefixes' => NetworkPrefix::count(),
            'ports' => OperationalAlerts::portCapacity(),
            'activeMonthlyTelco' => (float) TelcoCost::where('status', 'Active')->sum('monthly_cost'),
            'telcoByProvider' => $telcoByProvider,
            'expiringContracts' => $expiringContracts,
        ];
    }
}
