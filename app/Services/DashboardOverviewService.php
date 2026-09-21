<?php

namespace App\Services;

use App\Models\ChannelAllocation;
use App\Models\ChannelAllocationCampaign;
use App\Models\DefectiveGsm;
use App\Models\GlobeSim;
use App\Models\ProgramInboundNumber;
use App\Models\SipChannel;
use App\Models\SmartSim;
use App\Support\ChannelTypeClassifier;
use App\Support\Inbound\ProgramInboundNumberValidator;
use App\Support\NaturalSort;
use App\Support\PageWindow;
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
        $allocations = ChannelAllocation::query()
            ->select(['id', 'campaign_id', 'network', 'channel_allocation', 'media_gateway', 'total_channel_allocated', 'created_at'])
            ->with(['campaign:id,name,total_channels_allocated'])
            ->get();
        $utilization = $this->utilization($allocations);
        $campaignRows = $this->campaignRows($allocations);
        $campaigns = array_slice($campaignRows, 0, self::TOP_CAMPAIGNS);
        $trend = $this->trend($allocations);

        $campaignCount = ChannelAllocationCampaign::count();
        $typedCounts = $this->summedChannelCountsByType($allocations);
        $gatewayChannels = $typedCounts['gsm'];
        $sipChannels = (int) SipChannel::query()->sum('channel_count');
        $globeCount = GlobeSim::count();
        $smartCount = SmartSim::count();
        $simCount = $globeCount + $smartCount;
        $defective = $this->defectiveGsmCounts();
        $inbound = $this->inboundNumberCounts();

        $kpis = [
            'campaigns' => [
                'value' => $campaignCount,
                'display' => number_format($campaignCount),
            ],
            'gateways' => [
                'value' => $gatewayChannels,
                'display' => number_format($gatewayChannels),
            ],
            'channels' => [
                'value' => $sipChannels,
                'display' => number_format($sipChannels),
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
            'defective' => [
                'value' => $defective['total'],
                'display' => number_format($defective['total']),
            ],
            'defective_open' => [
                'value' => $defective['open'],
                'display' => number_format($defective['open']),
            ],
            'defective_repair' => [
                'value' => $defective['repair'],
                'display' => number_format($defective['repair']),
            ],
            'mobile' => [
                'value' => $inbound['mobile'],
                'display' => number_format($inbound['mobile']),
            ],
            'landline' => [
                'value' => $inbound['landline'],
                'display' => number_format($inbound['landline']),
            ],
            'inbound' => [
                'value' => $inbound['total'],
                'display' => number_format($inbound['total']),
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
     * Sum Channel Allocation `total_channel_allocated` by ChannelAllocation::channelType().
     *
     * @param  Collection<int, ChannelAllocation>  $allocations
     * @return array{sip: int, gsm: int}
     */
    private function summedChannelCountsByType(Collection $allocations): array
    {
        $sip = 0;
        $gsm = 0;
        foreach ($allocations as $row) {
            $amount = (int) $row->total_channel_allocated;
            if ($row->channelType() === 'gsm') {
                $gsm += $amount;
            } else {
                $sip += $amount;
            }
        }

        return ['sip' => $sip, 'gsm' => $gsm];
    }

    /**
     * @return array{open: int, repair: int, total: int}
     */
    private function defectiveGsmCounts(): array
    {
        $open = DefectiveGsm::query()->where('status', 'Open')->count();
        $repair = DefectiveGsm::query()->where('status', 'In Repair')->count();

        return [
            'open' => $open,
            'repair' => $repair,
            'total' => $open + $repair,
        ];
    }

    /**
     * @return array{mobile: int, landline: int, total: int}
     */
    private function inboundNumberCounts(): array
    {
        $mobile = 0;
        $landline = 0;

        ProgramInboundNumber::query()
            ->select(['id', 'mobile_numbers', 'landline_numbers', 'number'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$mobile, &$landline) {
                foreach ($rows as $row) {
                    $mobile += count($this->expandPhoneNumbers($row->mobileList()));
                    $landline += count($this->expandPhoneNumbers($row->landlineList()));
                }
            });

        return [
            'mobile' => $mobile,
            'landline' => $landline,
            'total' => $mobile + $landline,
        ];
    }

    /**
     * @param  list<string>  $items
     * @return list<string>
     */
    private function expandPhoneNumbers(array $items): array
    {
        $numbers = [];
        foreach ($items as $item) {
            foreach (ProgramInboundNumberValidator::normalize($item) as $number) {
                $numbers[] = $number;
            }
        }

        return $numbers;
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
        $typed = $this->summedChannelCountsByType($allocations);

        return [
            'total' => $total,
            'allocated' => $allocated,
            'available' => $available,
            'sip' => $typed['sip'],
            'gsm' => $typed['gsm'],
        ];
    }

    /**
     * @param  Collection<int, ChannelAllocation>  $allocations
     * @return list<array{name: string, total: int, sip: int, gsm: int, total_display: string, sip_display: string, gsm_display: string}>
     */
    public function campaignRows(Collection $allocations): array
    {
        return $allocations
            ->groupBy('campaign_id')
            ->map(function (Collection $items) {
                $campaign = $items->first()?->campaign;
                $counts = $this->summedChannelCountsByType($items);
                $headerTotal = $campaign?->total_channels_allocated;
                $total = $headerTotal !== null
                    ? (int) $headerTotal
                    : (int) $items->sum('total_channel_allocated');

                return [
                    'name' => (string) ($campaign?->name ?: 'Unassigned'),
                    'total' => $total,
                    'sip' => $counts['sip'],
                    'gsm' => $counts['gsm'],
                    'total_display' => number_format($total),
                    'sip_display' => number_format($counts['sip']),
                    'gsm_display' => number_format($counts['gsm']),
                ];
            })
            ->filter(fn (array $row) => $row['total'] > 0)
            ->sort(function (array $left, array $right): int {
                $byTotal = ($right['total'] ?? 0) <=> ($left['total'] ?? 0);
                if ($byTotal !== 0) {
                    return $byTotal;
                }

                return NaturalSort::compare($left['name'] ?? '', $right['name'] ?? '');
            })
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
        $totalRunning = 0;
        $byDay = [];

        foreach ($allocations as $row) {
            $created = $row->created_at;
            if (! $created instanceof Carbon) {
                continue;
            }

            $amount = (int) $row->total_channel_allocated;
            $isGsm = $row->channelType() === 'gsm';
            $sip = $isGsm ? 0 : $amount;
            $gsm = $isGsm ? $amount : 0;
            $bucket = $created->copy()->startOfDay()->toDateString();

            if ($created->lt($start)) {
                $totalRunning += $amount;
                $sipRunning += $sip;
                $gsmRunning += $gsm;
                continue;
            }

            $byDay[$bucket] ??= ['total' => 0, 'sip' => 0, 'gsm' => 0];
            $byDay[$bucket]['total'] += $amount;
            $byDay[$bucket]['sip'] += $sip;
            $byDay[$bucket]['gsm'] += $gsm;
        }

        $labels = [];
        $sip = [];
        $gsm = [];
        $total = [];
        for ($i = 0; $i < self::TREND_DAYS; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->toDateString();
            $totalRunning += (int) ($byDay[$key]['total'] ?? 0);
            $sipRunning += (int) ($byDay[$key]['sip'] ?? 0);
            $gsmRunning += (int) ($byDay[$key]['gsm'] ?? 0);
            $labels[] = $day->format('M j');
            $sip[] = $sipRunning;
            $gsm[] = $gsmRunning;
            $total[] = $totalRunning;
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
                    .'<td><span class="campaigns-name">'.$this->e($row['name']).'</span></td>'
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

        $pages = PageWindow::buttonHtml($page, $lastPage);

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
        $width = 1080;
        $height = 420;
        $padL = 88;
        $padR = 72;
        $padT = 16;
        $padB = 64;
        $plotW = $width - $padL - $padR;
        $plotH = $height - $padT - $padB;
        $max = max(1, (int) $trend['max']);
        $count = count($trend['sip']);
        $totalPath = $this->linePath($trend['total'], $count, $max, $padL, $padT, $plotW, $plotH);
        $sipPath = $this->linePath($trend['sip'], $count, $max, $padL, $padT, $plotW, $plotH);
        $gsmPath = $this->linePath($trend['gsm'], $count, $max, $padL, $padT, $plotW, $plotH);
        $labelIndexes = $this->trendLabelIndexes($count);

        $grid = '';
        foreach ([0, 0.25, 0.5, 0.75, 1] as $step) {
            $y = $padT + ($plotH * (1 - $step));
            $grid .= '<line x1="'.$padL.'" y1="'.$y.'" x2="'.($width - $padR).'" y2="'.$y.'" stroke="#e8eef5" stroke-width="1"/>';
            $grid .= '<text x="'.($padL - 14).'" y="'.($y + 5).'" text-anchor="end" fill="#0f172a" font-size="16" font-weight="700">'.$this->e(number_format((int) round($max * $step))).'</text>';
        }
        foreach ($labelIndexes as $index) {
            $x = $this->trendX($index, $count, $padL, $plotW);
            $grid .= '<line x1="'.$x.'" y1="'.$padT.'" x2="'.$x.'" y2="'.($padT + $plotH).'" stroke="#e8eef5" stroke-width="1"/>';
        }
        $axisY = $padT + ($plotH / 2);
        $grid .= '<text x="18" y="'.$axisY.'" text-anchor="middle" fill="#0f172a" font-size="16" font-weight="700" transform="rotate(-90 18 '.$axisY.')">Allocated Channels</text>';

        $axis = '';
        foreach ($labelIndexes as $index) {
            if (! isset($trend['labels'][$index])) {
                continue;
            }
            $x = $this->trendX($index, $count, $padL, $plotW);
            $axis .= '<text class="dash-trend-date" x="'.$x.'" y="'.($height - 22).'" text-anchor="middle" fill="#000000" font-size="20" font-weight="700">'.$this->e($trend['labels'][$index]).'</text>';
        }

        return '<div class="dash-trend-wrap">'
            .'<svg class="dash-trend-svg" viewBox="0 0 '.$width.' '.$height.'" preserveAspectRatio="xMidYMid meet" role="img" aria-label="Allocation trends for the last '.self::TREND_DAYS.' days">'
            .$grid
            .'<path d="'.$totalPath.'" fill="none" stroke="#2563eb" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/>'
            .'<path d="'.$sipPath.'" fill="none" stroke="#16a34a" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/>'
            .'<path d="'.$gsmPath.'" fill="none" stroke="#ea580c" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/>'
            .$this->trendDots($trend['total'], $labelIndexes, $count, $max, $padL, $padT, $plotW, $plotH, '#2563eb')
            .$this->trendDots($trend['sip'], $labelIndexes, $count, $max, $padL, $padT, $plotW, $plotH, '#16a34a')
            .$this->trendDots($trend['gsm'], $labelIndexes, $count, $max, $padL, $padT, $plotW, $plotH, '#ea580c')
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
            $x = $this->trendX($index, $count, $padL, $plotW);
            $y = $this->trendY((int) $value, $max, $padT, $plotH);
            $parts[] = ($index === 0 ? 'M' : 'L').$x.','.$y;
        }

        return implode(' ', $parts);
    }

    /**
     * @return list<int>
     */
    private function trendLabelIndexes(int $count): array
    {
        if ($count <= 1) {
            return [0];
        }

        $ticks = min(8, $count);
        $indexes = [];
        for ($i = 0; $i < $ticks; $i++) {
            $indexes[] = (int) round($i * ($count - 1) / ($ticks - 1));
        }

        return array_values(array_unique($indexes));
    }

    /**
     * @param  list<int>  $values
     * @param  list<int>  $labelIndexes
     */
    private function trendDots(array $values, array $labelIndexes, int $count, int $max, float $padL, float $padT, float $plotW, float $plotH, string $color): string
    {
        $indexes = $labelIndexes;
        for ($i = 1; $i < $count; $i++) {
            if ((int) ($values[$i] ?? 0) !== (int) ($values[$i - 1] ?? 0)) {
                $indexes[] = $i - 1;
                $indexes[] = $i;
            }
        }
        $indexes[] = 0;
        $indexes[] = max(0, $count - 1);
        $indexes = array_values(array_unique($indexes));
        sort($indexes);

        $dots = '';
        foreach ($indexes as $index) {
            if (! isset($values[$index])) {
                continue;
            }
            $x = $this->trendX($index, $count, $padL, $plotW);
            $y = $this->trendY((int) $values[$index], $max, $padT, $plotH);
            $dots .= '<circle cx="'.$x.'" cy="'.$y.'" r="6" fill="'.$color.'" stroke="#fff" stroke-width="1.5"/>';
        }

        return $dots;
    }

    private function trendX(int $index, int $count, float $padL, float $plotW): float
    {
        return $count <= 1 ? $padL : round($padL + ($index / ($count - 1)) * $plotW, 1);
    }

    private function trendY(int $value, int $max, float $padT, float $plotH): float
    {
        return round($padT + $plotH - (($value / $max) * $plotH), 1);
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
