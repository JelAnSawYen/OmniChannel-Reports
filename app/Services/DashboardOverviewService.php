<?php

namespace App\Services;

use App\Models\ChannelAllocation;
use App\Models\ChannelAllocationCampaign;
use App\Models\GlobeSim;
use App\Models\MediaGateway;
use App\Models\SipChannel;
use App\Models\SmartSim;
use App\Support\ChannelTypeClassifier;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardOverviewService
{
    public const TREND_DAYS = 30;

    public const TOP_CAMPAIGNS = 10;

    public const TABLE_PER_PAGE = 10;

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $allocations = ChannelAllocation::query()->with('campaign')->get();
        $utilization = $this->utilization($allocations);
        $campaignRows = $this->campaignRows($allocations);
        $campaigns = array_slice($campaignRows, 0, self::TOP_CAMPAIGNS);
        $trend = $this->trend($allocations);

        $campaignCount = ChannelAllocationCampaign::count();
        $gatewayCount = MediaGateway::count();
        $globeCount = GlobeSim::count();
        $smartCount = SmartSim::count();
        $simCount = $globeCount + $smartCount;

        $kpis = [
            'campaigns' => [
                'value' => $campaignCount,
                'display' => number_format($campaignCount),
            ],
            'gateways' => [
                'value' => $gatewayCount,
                'display' => number_format($gatewayCount),
            ],
            'channels' => [
                'value' => $utilization['total'],
                'display' => number_format($utilization['total']),
            ],
            'sims' => [
                'value' => $simCount,
                'display' => number_format($simCount),
            ],
            'globe' => [
                'value' => $globeCount,
                'display' => number_format($globeCount),
            ],
            'smart' => [
                'value' => $smartCount,
                'display' => number_format($smartCount),
            ],
        ];

        $generatedAt = now();
        $payload = [
            'kpis' => $kpis,
            'utilization' => $utilization,
            'campaigns' => $campaigns,
            'utilization_rows' => $campaignRows,
            'campaign_insight' => $this->campaignInsight($campaignRows),
            'trend' => [
                'days' => self::TREND_DAYS,
                'labels' => $trend['labels'],
                'total' => $trend['total'],
                'sip' => $trend['sip'],
                'gsm' => $trend['gsm'],
            ],
            'generated_at_label' => $generatedAt->format('g:i A'),
        ];

        $payload['html'] = [
            'bars' => $this->barsHtml($campaigns),
            'utilization' => $this->utilizationTableHtml($campaignRows, 1, self::TABLE_PER_PAGE, ''),
            'trend' => $this->trendHtml($trend),
        ];
        $payload['fingerprint'] = $this->fingerprint($payload);

        return $payload;
    }

    /**
     * @param  Collection<int, ChannelAllocation>  $allocations
     * @return array<string, mixed>
     */
    private function utilization(Collection $allocations): array
    {
        $sipAllocated = (int) $allocations->filter(
            fn ($row) => ChannelTypeClassifier::isSip($row->network, $row->channel_allocation)
        )->sum('total_channel_allocated');
        $gsmAllocated = (int) $allocations->filter(
            fn ($row) => ChannelTypeClassifier::isGsm($row->network, $row->channel_allocation)
        )->sum('total_channel_allocated');
        $otherAllocated = (int) $allocations->reject(function ($row) {
            $type = ChannelTypeClassifier::classify($row->network, $row->channel_allocation);

            return $type === ChannelTypeClassifier::SIP || $type === ChannelTypeClassifier::GSM;
        })->sum('total_channel_allocated');
        $allocated = $sipAllocated + $gsmAllocated + $otherAllocated;

        $sipInventory = (int) SipChannel::query()->sum('channel_count');
        $sipCapacity = max($sipInventory, $sipAllocated);
        $total = $sipCapacity + $gsmAllocated + $otherAllocated;
        $available = max(0, $total - $allocated);

        return [
            'total' => $total,
            'allocated' => $allocated,
            'available' => $available,
            'sip' => $sipAllocated,
            'gsm' => $gsmAllocated,
        ];
    }

    /**
     * @param  Collection<int, ChannelAllocation>  $allocations
     * @return list<array{name: string, total: int, sip: int, gsm: int, total_display: string, sip_display: string, gsm_display: string}>
     */
    private function campaignRows(Collection $allocations): array
    {
        return $allocations
            ->groupBy('campaign_id')
            ->map(function (Collection $items) {
                $campaign = $items->first()?->campaign;
                $sip = (int) $items->filter(
                    fn ($row) => ChannelTypeClassifier::isSip($row->network, $row->channel_allocation)
                )->sum('total_channel_allocated');
                $gsm = (int) $items->filter(
                    fn ($row) => ChannelTypeClassifier::isGsm($row->network, $row->channel_allocation)
                )->sum('total_channel_allocated');
                $total = (int) $items->sum('total_channel_allocated');

                return [
                    'name' => (string) ($campaign?->name ?: 'Unassigned'),
                    'total' => $total,
                    'sip' => $sip,
                    'gsm' => $gsm,
                    'total_display' => number_format($total),
                    'sip_display' => number_format($sip),
                    'gsm_display' => number_format($gsm),
                ];
            })
            ->filter(fn (array $row) => $row['total'] > 0)
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * @param  list<array{name: string, total: int}>  $campaigns
     */
    private function campaignInsight(array $campaigns): string
    {
        $top = $campaigns[0] ?? null;
        if (! $top) {
            return 'No allocation records are available yet.';
        }

        return $top['name'].' has the highest number of allocated channels with '
            .number_format($top['total']).' channels.';
    }

    /**
     * @param  Collection<int, ChannelAllocation>  $allocations
     * @return array{labels: list<string>, total: list<int>, sip: list<int>, gsm: list<int>, max: int}
     */
    private function trend(Collection $allocations): array
    {
        $start = now()->subDays(self::TREND_DAYS - 1)->startOfDay();
        $sipRunning = 0;
        $gsmRunning = 0;
        $otherRunning = 0;
        $byDay = [];

        foreach ($allocations as $row) {
            $created = $row->created_at;
            if (! $created instanceof Carbon) {
                continue;
            }

            $amount = (int) $row->total_channel_allocated;
            $type = ChannelTypeClassifier::classify($row->network, $row->channel_allocation);
            $bucket = $created->copy()->startOfDay()->toDateString();
            $key = match ($type) {
                ChannelTypeClassifier::SIP => 'sip',
                ChannelTypeClassifier::GSM => 'gsm',
                default => 'other',
            };

            if ($created->lt($start)) {
                if ($key === 'sip') {
                    $sipRunning += $amount;
                } elseif ($key === 'gsm') {
                    $gsmRunning += $amount;
                } else {
                    $otherRunning += $amount;
                }

                continue;
            }

            $byDay[$bucket] ??= ['sip' => 0, 'gsm' => 0, 'other' => 0];
            $byDay[$bucket][$key] += $amount;
        }

        $labels = [];
        $sip = [];
        $gsm = [];
        $total = [];
        for ($i = 0; $i < self::TREND_DAYS; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->toDateString();
            $sipRunning += (int) ($byDay[$key]['sip'] ?? 0);
            $gsmRunning += (int) ($byDay[$key]['gsm'] ?? 0);
            $otherRunning += (int) ($byDay[$key]['other'] ?? 0);
            $labels[] = $day->format('M j');
            $sip[] = $sipRunning;
            $gsm[] = $gsmRunning;
            $total[] = $sipRunning + $gsmRunning + $otherRunning;
        }

        return [
            'labels' => $labels,
            'total' => $total,
            'sip' => $sip,
            'gsm' => $gsm,
            'max' => max(1, (int) max(array_merge($total, $sip, $gsm, [0]))),
        ];
    }

    /**
     * @param  list<array{name: string, total: int}>  $campaigns
     */
    private function barsHtml(array $campaigns): string
    {
        if ($campaigns === []) {
            return '<p class="muted dash-empty">No allocation records are available yet.</p>';
        }

        $width = 920;
        $height = 280;
        $padL = 48;
        $padR = 16;
        $padT = 24;
        $padB = 44;
        $plotW = $width - $padL - $padR;
        $plotH = $height - $padT - $padB;
        $max = max(1, (int) max(array_column($campaigns, 'total')));
        $count = count($campaigns);
        $slot = $plotW / $count;
        $barW = min(48, max(18, $slot * 0.46));

        $grid = '';
        foreach ([0, 0.25, 0.5, 0.75, 1] as $step) {
            $y = $padT + ($plotH * (1 - $step));
            $grid .= '<line x1="'.$padL.'" y1="'.$y.'" x2="'.($width - $padR).'" y2="'.$y.'" stroke="#eef2f7" stroke-width="1"/>';
            $grid .= '<text x="'.($padL - 8).'" y="'.($y + 3).'" text-anchor="end" fill="#94a3b8" font-size="9">'.$this->e(number_format((int) round($max * $step))).'</text>';
        }

        $bars = '';
        $labels = '';
        $rotate = $count > 6;
        foreach ($campaigns as $index => $row) {
            $value = (int) $row['total'];
            $barH = ($value / $max) * $plotH;
            $x = $padL + ($slot * $index) + (($slot - $barW) / 2);
            $y = $padT + $plotH - $barH;
            $cx = $x + ($barW / 2);
            $name = $this->e($row['name']);
            $bars .= '<rect x="'.round($x, 1).'" y="'.round($y, 1).'" width="'.round($barW, 1).'" height="'.round(max($barH, 1), 1).'" rx="3" fill="#2563eb"/>';
            $bars .= '<text x="'.round($cx, 1).'" y="'.round($y - 6, 1).'" text-anchor="middle" fill="#0f172a" font-size="10" font-weight="700">'.$this->e(number_format($value)).'</text>';
            if ($rotate) {
                $labels .= '<text x="'.round($cx, 1).'" y="'.($height - 14).'" text-anchor="end" fill="#475569" font-size="9" transform="rotate(-32 '.round($cx, 1).' '.($height - 14).')">'.$name.'</text>';
            } else {
                $labels .= '<text x="'.round($cx, 1).'" y="'.($height - 18).'" text-anchor="middle" fill="#475569" font-size="10">'.$name.'</text>';
            }
        }

        return '<div class="dash-vbar-wrap">'
            .'<svg class="dash-vbar-svg" viewBox="0 0 '.$width.' '.$height.'" role="img" aria-label="Campaigns with most allocations">'
            .'<text x="14" y="'.($padT + ($plotH / 2)).'" text-anchor="middle" fill="#94a3b8" font-size="9" transform="rotate(-90 14 '.($padT + ($plotH / 2)).')">Allocated Channels</text>'
            .$grid.$bars.$labels
            .'</svg></div>';
    }

    /**
     * @param  list<array{name: string, total: int, sip: int, gsm: int, total_display: string, sip_display: string, gsm_display: string}>  $rows
     */
    public function utilizationTableHtml(array $rows, int $page = 1, int $perPage = self::TABLE_PER_PAGE, string $search = ''): string
    {
        $search = trim($search);
        $filtered = $search === ''
            ? $rows
            : array_values(array_filter($rows, function (array $row) use ($search) {
                return mb_stripos($row['name'], $search) !== false;
            }));

        $totalCount = count($filtered);
        $perPage = in_array($perPage, [5, 10, 25, 50], true) ? $perPage : self::TABLE_PER_PAGE;
        $lastPage = max(1, (int) ceil(($totalCount ?: 1) / $perPage));
        $page = max(1, min($page, $lastPage));
        $slice = array_slice($filtered, ($page - 1) * $perPage, $perPage);
        $from = $totalCount === 0 ? 0 : (($page - 1) * $perPage) + 1;
        $to = min($page * $perPage, $totalCount);

        $sumTotal = (int) array_sum(array_column($filtered, 'total'));
        $sumSip = (int) array_sum(array_column($filtered, 'sip'));
        $sumGsm = (int) array_sum(array_column($filtered, 'gsm'));

        $body = '';
        if ($slice === []) {
            $body = '<tr><td colspan="4"><div class="empty-state">No campaigns match this search.</div></td></tr>';
        } else {
            foreach ($slice as $row) {
                $body .= '<tr>'
                    .'<td>'.$this->e($row['name']).'</td>'
                    .'<td class="num">'.$this->e($row['total_display']).'</td>'
                    .'<td class="num">'.$this->e($row['sip_display']).'</td>'
                    .'<td class="num">'.$this->e($row['gsm_display']).'</td>'
                    .'</tr>';
            }
            $body .= '<tr class="dash-total-row">'
                .'<td>Total</td>'
                .'<td class="num">'.number_format($sumTotal).'</td>'
                .'<td class="num">'.number_format($sumSip).'</td>'
                .'<td class="num">'.number_format($sumGsm).'</td>'
                .'</tr>';
        }

        $pages = '';
        for ($i = 1; $i <= $lastPage; $i++) {
            $active = $i === $page ? ' active' : '';
            $pages .= '<button type="button" class="page-number'.$active.'" data-dash-page="'.$i.'">'.$i.'</button>';
        }

        $options = '';
        foreach ([5, 10, 25, 50] as $size) {
            $selected = $size === $perPage ? ' selected' : '';
            $options .= '<option value="'.$size.'"'.$selected.'>'.$size.'</option>';
        }

        return '<div class="table-card table-wrap dash-util-table">'
            .'<table>'
            .'<thead><tr><th>Campaign</th><th class="num">Total Channels</th><th class="num">SIP</th><th class="num">GSM</th></tr></thead>'
            .'<tbody>'.$body.'</tbody>'
            .'</table>'
            .'<div class="table-footer">'
            .'<span>Showing '.$from.' to '.$to.' of '.$totalCount.' entries</span>'
            .'<div class="footer-right">'
            .'<span>Records per page:</span>'
            .'<select class="per-page-select" data-dash-per-page aria-label="Records per page">'.$options.'</select>'
            .'<div class="pager">'.$pages.'</div>'
            .'</div></div></div>';
    }

    /**
     * @param  array{labels: list<string>, total: list<int>, sip: list<int>, gsm: list<int>, max: int}  $trend
     */
    private function trendHtml(array $trend): string
    {
        $width = 920;
        $height = 260;
        $padL = 44;
        $padR = 16;
        $padT = 16;
        $padB = 32;
        $plotW = $width - $padL - $padR;
        $plotH = $height - $padT - $padB;
        $max = max(1, (int) $trend['max']);
        $count = count($trend['sip']);
        $totalPath = $this->linePath($trend['total'], $count, $max, $padL, $padT, $plotW, $plotH);
        $sipPath = $this->linePath($trend['sip'], $count, $max, $padL, $padT, $plotW, $plotH);
        $gsmPath = $this->linePath($trend['gsm'], $count, $max, $padL, $padT, $plotW, $plotH);

        $grid = '';
        foreach ([0, 0.25, 0.5, 0.75, 1] as $step) {
            $y = $padT + ($plotH * (1 - $step));
            $grid .= '<line x1="'.$padL.'" y1="'.$y.'" x2="'.($width - $padR).'" y2="'.$y.'" stroke="#eef2f7" stroke-width="1"/>';
            $grid .= '<text x="'.($padL - 8).'" y="'.($y + 3).'" text-anchor="end" fill="#94a3b8" font-size="9">'.$this->e(number_format((int) round($max * $step))).'</text>';
        }

        $labelIndexes = [0, 7, 14, 21, $count - 1];
        $axis = '';
        foreach (array_unique($labelIndexes) as $index) {
            if (! isset($trend['labels'][$index])) {
                continue;
            }
            $x = $count <= 1 ? $padL : $padL + ($index / ($count - 1)) * $plotW;
            $axis .= '<text x="'.$x.'" y="'.($height - 10).'" text-anchor="middle" fill="#94a3b8" font-size="9">'.$this->e($trend['labels'][$index]).'</text>';
        }

        return '<div class="dash-trend-wrap">'
            .'<svg class="dash-trend-svg" viewBox="0 0 '.$width.' '.$height.'" role="img" aria-label="Allocation trends for the last '.self::TREND_DAYS.' days">'
            .$grid
            .'<path d="'.$totalPath.'" fill="none" stroke="#2563eb" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>'
            .'<path d="'.$sipPath.'" fill="none" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
            .'<path d="'.$gsmPath.'" fill="none" stroke="#ea580c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
            .$axis
            .'</svg></div>';
    }

    /**
     * @param  list<int>  $values
     */
    private function linePath(array $values, int $count, int $max, float $padL, float $padT, float $plotW, float $plotH): string
    {
        $parts = [];
        foreach ($values as $index => $value) {
            $x = $count <= 1 ? $padL : $padL + ($index / ($count - 1)) * $plotW;
            $y = $padT + $plotH - (($value / $max) * $plotH);
            $parts[] = ($index === 0 ? 'M' : 'L').round($x, 1).','.round($y, 1);
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fingerprint(array $payload): string
    {
        return hash('sha256', json_encode([
            $payload['kpis'],
            $payload['utilization'],
            $payload['campaigns'],
            $payload['utilization_rows'],
            $payload['campaign_insight'],
            $payload['trend'],
        ], JSON_UNESCAPED_UNICODE));
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
