<?php

namespace App\Services;

use App\Models\MediaGateway;
use App\Support\OperationCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SystemHealthService
{
    public const TIERS = ['excellent', 'good', 'warning', 'critical'];

    public const LABELS = [
        'excellent' => 'Excellent',
        'good' => 'Good',
        'warning' => 'Warning',
        'critical' => 'Critical',
    ];

    public const DESCRIPTIONS = [
        'excellent' => 'Systems operating normally',
        'good' => 'Minor issues detected',
        'warning' => 'Requires attention',
        'critical' => 'Immediate action required',
    ];

    public const MODULE_STATUS_COPY = [
        'excellent' => 'All systems are operating normally.',
        'good' => 'Minor issues detected.',
        'warning' => 'Requires attention.',
        'critical' => 'Immediate action required.',
    ];

    public const COLORS = [
        'excellent' => '#22c55e',
        'good' => '#3b82f6',
        'warning' => '#f59e0b',
        'critical' => '#ef4444',
    ];

    private const WEIGHTS = [
        'excellent' => 100,
        'good' => 80,
        'warning' => 40,
        'critical' => 0,
    ];

    /**
     * Status values mapped onto health tiers. Anything unrecognised is treated
     * as "good" so a new status never inflates the excellent or critical count.
     */
    private const STATUS_MAP = [
        'active' => 'excellent',
        'archived' => 'excellent',
        'assigned' => 'excellent',
        'closed' => 'excellent',
        'in use' => 'excellent',
        'online' => 'excellent',
        'repaired' => 'excellent',
        'replaced' => 'excellent',
        'resolved' => 'excellent',
        'available' => 'good',
        'pending' => 'good',
        'reserved' => 'good',
        'spare' => 'good',
        'standby' => 'good',
        'unassigned' => 'good',
        'expiring' => 'warning',
        'for repair' => 'warning',
        'in repair' => 'warning',
        'inactive' => 'warning',
        'maintenance' => 'warning',
        'servicing' => 'warning',
        'suspended' => 'warning',
        'under maintenance' => 'warning',
        'blocked' => 'critical',
        'critical' => 'critical',
        'defective' => 'critical',
        'disabled' => 'critical',
        'down' => 'critical',
        'faulty' => 'critical',
        'lost' => 'critical',
        'offline' => 'critical',
        'open' => 'critical',
    ];

    /**
     * @return array{items:array<int,array<string,mixed>>,total:int,percent:int,tier:string,counts:array<string,int>}
     */
    public function summary(): array
    {
        $counts = ['excellent' => 0, 'good' => 0, 'warning' => 0, 'critical' => 0];

        foreach ($this->sources() as $source) {
            if (! Schema::hasTable($source['table'])) {
                continue;
            }

            $model = $source['model'];
            if ($source['status'] === null) {
                $counts['excellent'] += $model::query()->count();

                continue;
            }

            foreach ($model::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status') as $status => $total) {
                $counts[$this->tier((string) $status)] += (int) $total;
            }
        }

        $total = array_sum($counts);
        $percent = $this->score($counts, $total);
        $items = [];

        foreach (self::TIERS as $key) {
            $items[] = [
                'key' => $key,
                'label' => self::LABELS[$key],
                'color' => self::COLORS[$key],
                'value' => $counts[$key],
                'percent' => $total > 0 ? (int) round(($counts[$key] / $total) * 100) : 0,
                'href' => route('system-health', ['status' => $key]),
                'description' => self::DESCRIPTIONS[$key],
            ];
        }

        return [
            'items' => $items,
            'total' => $total,
            'percent' => $percent,
            'tier' => $this->tierFromScore($percent),
            'counts' => $counts,
        ];
    }

    /**
     * @return Collection<int,array<string,mixed>>
     */
    public function records(?string $tier = null): Collection
    {
        $records = collect();

        foreach ($this->sources() as $source) {
            if (! Schema::hasTable($source['table'])) {
                continue;
            }

            $model = $source['model'];
            foreach ($model::query()->orderByDesc('id')->get() as $record) {
                $itemTier = $source['status'] === null
                    ? 'excellent'
                    : $this->tier((string) ($record->{$source['status']} ?? ''));

                if ($tier && $itemTier !== $tier) {
                    continue;
                }

                $name = $source['name']($record);
                $search = ['search' => $name];
                $href = $source['route'] === 'gsm-gateways.index'
                    ? route('gsm-gateways.index', $search)
                    : route($source['route'], $search);

                $records->push([
                    'module' => $source['module'],
                    'name' => $name,
                    'location' => $source['location']($record),
                    'status' => $source['status'] === null ? 'Configured' : (string) ($record->{$source['status']} ?: 'Unknown'),
                    'tier' => $itemTier,
                    'tier_label' => self::LABELS[$itemTier],
                    'href' => $href,
                ]);
            }
        }

        return $records->sortBy([
            ['module', 'asc'],
            ['name', 'asc'],
        ])->values();
    }

    /**
     * One row per inventory module that currently has records.
     *
     * @return array<int,array<string,mixed>>
     */
    public function modules(): array
    {
        $grouped = $this->records()->groupBy('module');
        $rows = [];

        foreach ($this->sources() as $source) {
            $items = $grouped->get($source['module'], collect());
            if ($items->isEmpty()) {
                continue;
            }

            $counts = ['excellent' => 0, 'good' => 0, 'warning' => 0, 'critical' => 0];
            foreach ($items as $item) {
                $counts[$item['tier']]++;
            }

            $total = $items->count();
            $percent = $this->score($counts, $total);
            $statusKey = strtolower($this->tierFromScore($percent));
            $href = $source['route'] === 'gsm-gateways.index'
                ? route('gsm-gateways.index')
                : route($source['route']);

            $rows[] = [
                'module' => $source['module'],
                'icon' => $source['icon'],
                'href' => $href,
                'excellent' => $counts['excellent'],
                'good' => $counts['good'],
                'warning' => $counts['warning'],
                'critical' => $counts['critical'],
                'total' => $total,
                'percent' => $percent,
                'status' => $statusKey,
                'status_label' => self::LABELS[$statusKey],
                'status_copy' => self::MODULE_STATUS_COPY[$statusKey],
                'color' => self::COLORS[$statusKey],
            ];
        }

        return $rows;
    }

    public function tier(string $status): string
    {
        $key = strtolower(trim($status));

        return self::STATUS_MAP[$key] ?? 'good';
    }

    /**
     * @param  array<string,int>  $counts
     */
    public function score(array $counts, int $total): int
    {
        if ($total < 1) {
            return 100;
        }

        $weighted = 0;
        foreach (self::WEIGHTS as $tier => $weight) {
            $weighted += ($counts[$tier] ?? 0) * $weight;
        }

        return (int) round($weighted / $total);
    }

    public function tierFromScore(int $percent): string
    {
        return match (true) {
            $percent >= 90 => 'Excellent',
            $percent >= 75 => 'Good',
            $percent >= 50 => 'Warning',
            default => 'Critical',
        };
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function sources(): array
    {
        $sources = [[
            'module' => 'GSM Gateways',
            'route' => 'gsm-gateways.index',
            'icon' => 'gsm',
            'model' => MediaGateway::class,
            'table' => (new MediaGateway)->getTable(),
            'status' => null,
            'name' => fn (MediaGateway $record) => $record->site_code ?: $record->site_name,
            'location' => fn (MediaGateway $record) => $record->site_name,
        ]];

        $icons = [
            'telco-cost' => 'network',
            'channel-prefix' => 'network',
            'channel-port' => 'network',
            'network-prefix' => 'network',
            'pdc-servers' => 'server',
            'sip-channels' => 'inbound',
            'archive-recordings' => 'archive',
            'globe-sim' => 'sim',
            'smart-sim' => 'sim',
            'program-inbound-numbers' => 'inbound',
            'signal-boosters' => 'signal',
            'defective-gsm' => 'alert',
        ];

        foreach (OperationCatalog::modules() as $slug => $config) {
            $model = $config['model'];
            $nameField = $slug === 'sip-channels' ? 'etpi_sip_name' : $config['fields'][0];
            $sources[] = [
                'module' => $config['title'],
                'route' => $slug,
                'icon' => $icons[$slug] ?? 'network',
                'model' => $model,
                'table' => (new $model)->getTable(),
                'status' => $slug === 'sip-channels' ? null : 'status',
                'name' => function ($record) use ($nameField) {
                    $value = trim((string) ($record->{$nameField} ?? ''));
                    if ($value === '') {
                        return '#'.$record->id;
                    }

                    return $nameField === 'port_number' ? 'Port '.$value : $value;
                },
                'location' => function ($record) {
                    foreach (['location', 'site', 'gateway'] as $field) {
                        $value = trim((string) ($record->{$field} ?? ''));
                        if ($value !== '') {
                            return $value;
                        }
                    }

                    return '—';
                },
            ];
        }

        return $sources;
    }
}
