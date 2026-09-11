<?php

namespace App\Services;

use App\Models\ChannelAllocation;
use App\Models\ChannelAllocationCampaign;
use App\Models\DefectiveGsm;
use App\Models\MediaGateway;
use App\Models\SipChannel;
use App\Support\ChannelTypeClassifier;
use App\Support\OperationCatalog;
use App\Support\PdcEndorseDate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class OperationalReportService
{
    public const TABS = [
        'campaign-allocation' => 'Campaign Allocation',
        'channel-utilization' => 'Channel Utilization',
        'channel-allocation' => 'Channel Allocation',
        'gsm-gateway' => 'GSM Gateway',
        'defective-gsm' => 'Defective GSM',
        'campaign-summary' => 'Campaign Summary',
    ];

    /**
     * @return array<string, mixed>
     */
    public function page(Request $request): array
    {
        return $this->compile($request, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function exportPayload(Request $request): array
    {
        return $this->compile($request, false);
    }

    /**
     * @return array<string, mixed>
     */
    private function compile(Request $request, bool $paginate): array
    {
        $tab = $this->tab($request);
        $filters = $this->filters($request);
        $built = match ($tab) {
            'channel-utilization' => $this->channelUtilization($filters),
            'channel-allocation' => $this->channelAllocation($filters),
            'gsm-gateway' => $this->gsmGateway($filters),
            'defective-gsm' => $this->defectiveGsm($filters),
            'campaign-summary' => $this->campaignSummary($filters),
            default => $this->campaignAllocation($filters),
        };

        $perPage = $filters['per_page'];
        $page = max(1, (int) $request->query('page', 1));
        $rows = $built['rows'];
        $paginator = null;
        if ($paginate) {
            $paginator = new LengthAwarePaginator(
                $rows->forPage($page, $perPage)->values(),
                $rows->count(),
                $perPage,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
            $displayRows = $paginator->getCollection();
        } else {
            $displayRows = $rows->values();
        }

        $user = $request->user();

        return [
            'tab' => $tab,
            'tabs' => self::TABS,
            'title' => $built['title'],
            'description' => $built['description'],
            'headers' => $built['headers'],
            'rows' => $displayRows,
            'all_rows' => $rows,
            'totals' => $built['totals'],
            'summary' => $built['summary'],
            'insight' => $built['insight'],
            'visible_filters' => $built['visible_filters'],
            'filters' => $filters,
            'filter_options' => $this->filterOptions($built['visible_filters']),
            'applied_filters' => $this->appliedFilterLabels($filters, $built['visible_filters']),
            'paginator' => $paginator,
            'per_page' => $perPage,
            'generated_at' => now(),
            'generated_by' => $user?->name ?? 'System',
        ];
    }

    public function tab(Request $request): string
    {
        $tab = (string) $request->query('tab', 'campaign-allocation');

        return array_key_exists($tab, self::TABS) ? $tab : 'campaign-allocation';
    }

    /**
     * @return array{
     *     date_from: string,
     *     date_to: string,
     *     campaign_id: int|null,
     *     channel_type: string,
     *     status: string,
     *     gateway_id: int|null,
     *     location: string,
     *     per_page: int
     * }
     */
    public function filters(Request $request): array
    {
        $from = $this->dateValue($request->query('date_from'), $this->defaultDateFrom());
        $to = $this->dateValue($request->query('date_to'), now()->toDateString());
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $perPage = (int) $request->query('per_page', 10);
        if (! in_array($perPage, [5, 10, 25, 50], true)) {
            $perPage = 10;
        }

        $channelType = strtolower((string) $request->query('channel_type', 'all'));
        if (! in_array($channelType, ['all', 'sip', 'gsm'], true)) {
            $channelType = 'all';
        }

        $campaignId = $request->query('campaign_id');
        $gatewayId = $request->query('gateway_id');

        return [
            'date_from' => $from,
            'date_to' => $to,
            'campaign_id' => is_numeric($campaignId) ? (int) $campaignId : null,
            'channel_type' => $channelType,
            'status' => trim((string) $request->query('status', '')),
            'gateway_id' => is_numeric($gatewayId) ? (int) $gatewayId : null,
            'location' => trim((string) $request->query('location', '')),
            'per_page' => $perPage,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function campaignAllocation(array $filters): array
    {
        $grouped = $this->campaignGroups($filters);
        $total = (int) $grouped->sum('total');
        $sip = (int) $grouped->sum('sip');
        $gsm = (int) $grouped->sum('gsm');
        $rows = $grouped->values()->map(function (array $row, int $index) use ($total) {
            $percent = $total > 0 ? round(($row['total'] / $total) * 100, 1) : 0.0;

            return [
                $index + 1,
                $row['name'],
                number_format($row['total']),
                number_format($row['sip']),
                number_format($row['gsm']),
                number_format($percent, 1).'%',
            ];
        });

        $top = $grouped->first();
        $insight = 'No allocation records match the selected filters.';
        if ($top && $total > 0) {
            $share = round(($top['total'] / $total) * 100, 1);
            $insight = $top['name'].' has the highest number of allocated channels with '
                .number_format($top['total']).' channels, representing '
                .number_format($share, 1).'% of total allocations.';
        }

        return [
            'title' => 'Campaign Allocation Report',
            'description' => 'Shows total allocated channels per campaign with SIP and GSM breakdown.',
            'headers' => ['#', 'Campaign', 'Total Allocated', 'SIP', 'GSM', '% of Total'],
            'rows' => $rows,
            'totals' => $total > 0 || $rows->isNotEmpty() ? [
                '',
                'TOTAL',
                number_format($total),
                number_format($sip),
                number_format($gsm),
                $total > 0 ? '100.0%' : '0.0%',
            ] : null,
            'summary' => [
                ['label' => 'Total Allocated Channels', 'value' => number_format($total), 'note' => null, 'tone' => 'blue', 'icon' => 'stack'],
                ['label' => 'SIP Channels', 'value' => number_format($sip), 'note' => $this->percentNote($sip, $total), 'tone' => 'green', 'icon' => 'sip'],
                ['label' => 'GSM Channels', 'value' => number_format($gsm), 'note' => $this->percentNote($gsm, $total), 'tone' => 'orange', 'icon' => 'gsm'],
            ],
            'insight' => $insight,
            'visible_filters' => ['date_range', 'campaign', 'channel_type'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function channelUtilization(array $filters): array
    {
        $allocations = $this->filteredAllocations($filters);
        $sipAllocated = (int) $allocations->filter(fn ($row) => ChannelTypeClassifier::isSip($row->network, $row->channel_allocation))->sum('total_channel_allocated');
        $gsmAllocated = (int) $allocations->filter(fn ($row) => ChannelTypeClassifier::isGsm($row->network, $row->channel_allocation))->sum('total_channel_allocated');
        $otherAllocated = (int) $allocations->reject(function ($row) {
            $type = ChannelTypeClassifier::classify($row->network, $row->channel_allocation);

            return $type === ChannelTypeClassifier::SIP || $type === ChannelTypeClassifier::GSM;
        })->sum('total_channel_allocated');
        $allocated = $sipAllocated + $gsmAllocated + $otherAllocated;

        $sipQuery = SipChannel::query();
        if ($filters['campaign_id']) {
            $sipQuery->where('campaign_id', $filters['campaign_id']);
        }
        $sipInventory = (int) $sipQuery->sum('channel_count');
        $sipCapacity = max($sipInventory, $sipAllocated);
        $total = $sipCapacity + $gsmAllocated + $otherAllocated;
        $available = max(0, $total - $allocated);

        $rows = $allocations
            ->sortByDesc(fn ($row) => (int) $row->total_channel_allocated)
            ->values()
            ->map(function ($row) {
                return [
                    (string) $row->channel_allocation,
                    ChannelTypeClassifier::classify($row->network, $row->channel_allocation),
                    (string) ($row->campaign?->name ?: '—'),
                    (string) ($row->media_gateway ?: '—'),
                    $this->formatDate($row->created_at),
                ];
            });

        return [
            'title' => 'Channel Utilization Report',
            'description' => 'Detailed channel usage and capacity by campaign, type, and related resource.',
            'headers' => ['Channel', 'Channel Type', 'Campaign', 'Gateway / Related Resource', 'Allocation Date'],
            'rows' => $rows,
            'totals' => null,
            'summary' => [
                ['label' => 'Total Channels', 'value' => number_format($total), 'note' => null, 'tone' => 'blue', 'icon' => 'stack'],
                ['label' => 'Allocated Channels', 'value' => number_format($allocated), 'note' => $this->percentNote($allocated, $total), 'tone' => 'green', 'icon' => 'sip'],
                ['label' => 'Available Channels', 'value' => number_format($available), 'note' => $this->percentNote($available, $total), 'tone' => 'orange', 'icon' => 'gsm'],
                ['label' => 'SIP Channels', 'value' => number_format($sipAllocated), 'note' => $this->percentNote($sipAllocated, $allocated ?: $total), 'tone' => 'blue', 'icon' => 'sip'],
                ['label' => 'GSM Channels', 'value' => number_format($gsmAllocated), 'note' => $this->percentNote($gsmAllocated, $allocated ?: $total), 'tone' => 'orange', 'icon' => 'gsm'],
            ],
            'insight' => $this->utilizationInsight($total, $allocated, $available),
            'visible_filters' => ['date_range', 'campaign', 'channel_type'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function channelAllocation(array $filters): array
    {
        $allocations = $this->filteredAllocations($filters)
            ->sortByDesc(fn ($row) => optional($row->updated_at)?->timestamp ?? 0)
            ->values();

        $rows = $allocations->map(function ($row) {
            return [
                (string) ($row->campaign?->name ?: '—'),
                (string) $row->channel_allocation,
                ChannelTypeClassifier::classify($row->network, $row->channel_allocation),
                (string) ($row->media_gateway ?: '—'),
                '—',
                '—',
                $this->formatDate($row->created_at),
                $this->formatDate($row->updated_at),
            ];
        });

        $sip = (int) $allocations->filter(fn ($row) => ChannelTypeClassifier::isSip($row->network, $row->channel_allocation))->sum('total_channel_allocated');
        $gsm = (int) $allocations->filter(fn ($row) => ChannelTypeClassifier::isGsm($row->network, $row->channel_allocation))->sum('total_channel_allocated');
        $total = (int) $allocations->sum('total_channel_allocated');

        return [
            'title' => 'Channel Allocation Report',
            'description' => 'Detailed allocation records by campaign, channel, gateway, and date.',
            'headers' => ['Campaign', 'Channel', 'Channel Type', 'Gateway', 'Status', 'Allocated By', 'Allocation Date', 'Updated Date'],
            'rows' => $rows,
            'totals' => null,
            'summary' => [
                ['label' => 'Allocation Records', 'value' => number_format($allocations->count()), 'note' => null, 'tone' => 'blue', 'icon' => 'stack'],
                ['label' => 'SIP Channels', 'value' => number_format($sip), 'note' => $this->percentNote($sip, $total), 'tone' => 'green', 'icon' => 'sip'],
                ['label' => 'GSM Channels', 'value' => number_format($gsm), 'note' => $this->percentNote($gsm, $total), 'tone' => 'orange', 'icon' => 'gsm'],
            ],
            'insight' => $allocations->isEmpty()
                ? 'No allocation records match the selected filters.'
                : 'This report lists '.$allocations->count().' allocation records totaling '.number_format($total).' channels.',
            'visible_filters' => ['date_range', 'campaign', 'channel_type'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function gsmGateway(array $filters): array
    {
        $query = MediaGateway::query()->orderBy('site_name')->orderBy('site_code');
        $this->constrainDate($query, $filters, 'updated_at');
        if ($filters['location'] !== '') {
            $query->where('site_name', $filters['location']);
        }
        if ($filters['gateway_id']) {
            $query->where('id', $filters['gateway_id']);
        }
        $gateways = $query->get();

        $assigned = ChannelAllocation::query()
            ->selectRaw('media_gateway, SUM(total_channel_allocated) as assigned')
            ->whereNotNull('media_gateway')
            ->where('media_gateway', '!=', '')
            ->groupBy('media_gateway')
            ->pluck('assigned', 'media_gateway');

        $rows = $gateways->map(function (MediaGateway $gateway) use ($assigned) {
            $keys = array_values(array_filter([
                (string) $gateway->ip_address,
                (string) $gateway->site_code,
                (string) $gateway->site_name,
            ]));
            $count = 0;
            foreach ($keys as $key) {
                $count += (int) ($assigned[$key] ?? 0);
            }

            return [
                (string) $gateway->site_name,
                (string) $gateway->site_code,
                (string) $gateway->site_name,
                '—',
                number_format($count),
                $this->formatDate($gateway->updated_at),
            ];
        });

        $assignedTotal = (int) $rows->sum(fn ($row) => (int) str_replace(',', '', (string) $row[4]));

        return [
            'title' => 'GSM Gateway Report',
            'description' => 'Operational gateway inventory with assigned channel counts from live allocation records.',
            'headers' => ['Gateway', 'Identifier', 'Location', 'Status', 'Assigned Channels', 'Last Updated'],
            'rows' => $rows,
            'totals' => null,
            'summary' => [
                ['label' => 'GSM Gateways', 'value' => number_format($gateways->count()), 'note' => null, 'tone' => 'blue', 'icon' => 'gsm'],
                ['label' => 'Assigned Channels', 'value' => number_format($assignedTotal), 'note' => null, 'tone' => 'green', 'icon' => 'stack'],
            ],
            'insight' => $gateways->isEmpty()
                ? 'No GSM gateway records match the selected filters.'
                : $gateways->count().' GSM gateways are in this report, with '.number_format($assignedTotal).' assigned channels.',
            'visible_filters' => ['date_range', 'location', 'gsm_gateway'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function defectiveGsm(array $filters): array
    {
        $query = DefectiveGsm::query()->orderByDesc('reported_on')->orderByDesc('updated_at');
        if ($filters['location'] !== '') {
            $query->where('location', $filters['location']);
        }
        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        $query->where(function ($inner) use ($filters) {
            $inner->whereDate('reported_on', '>=', $filters['date_from'])
                ->whereDate('reported_on', '<=', $filters['date_to'])
                ->orWhere(function ($missing) use ($filters) {
                    $missing->whereNull('reported_on')
                        ->whereDate('created_at', '>=', $filters['date_from'])
                        ->whereDate('created_at', '<=', $filters['date_to']);
                });
        });
        $records = $query->get();
        $open = $records->whereIn('status', ['Open', 'In Repair'])->count();

        $rows = $records->map(function (DefectiveGsm $row) {
            return [
                (string) $row->asset_code,
                (string) ($row->location ?: '—'),
                (string) ($row->issue ?: '—'),
                $row->reported_on ? $row->reported_on->format('M d, Y') : $this->formatDate($row->created_at),
                (string) ($row->status ?: '—'),
                $this->formatDate($row->updated_at),
            ];
        });

        $topStatus = $records->groupBy(fn ($row) => (string) $row->status)
            ->sortByDesc(fn (Collection $group) => $group->count())
            ->keys()
            ->first();

        return [
            'title' => 'Defective GSM Report',
            'description' => 'Maintenance and defect records from existing Defective GSM inventory.',
            'headers' => ['Serial Tag', 'Location', 'Issue', 'Reported On', 'Status', 'Last Updated'],
            'rows' => $rows,
            'totals' => null,
            'summary' => [
                ['label' => 'Reported Issues', 'value' => number_format($records->count()), 'note' => null, 'tone' => 'blue', 'icon' => 'stack'],
                ['label' => 'Open / In Repair', 'value' => number_format($open), 'note' => null, 'tone' => 'orange', 'icon' => 'gsm'],
            ],
            'insight' => $records->isEmpty()
                ? 'No defective GSM records match the selected filters.'
                : ($topStatus
                    ? 'Most records in this view are '.$topStatus.', with '.$open.' still open or in repair.'
                    : 'This report includes '.$records->count().' defective GSM records.'),
            'visible_filters' => ['date_range', 'location', 'status'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function campaignSummary(array $filters): array
    {
        $grouped = $this->campaignGroups($filters);
        $campaignDates = ChannelAllocationCampaign::query()
            ->get(['id', 'created_at', 'updated_at'])
            ->keyBy('id');

        $rows = $grouped->values()->map(function (array $row) use ($campaignDates) {
            $campaign = $campaignDates->get($row['id']);

            return [
                $row['name'],
                number_format($row['total']),
                number_format($row['sip']),
                number_format($row['gsm']),
                '—',
                $this->formatDate($campaign?->created_at),
                $this->formatDate($campaign?->updated_at),
            ];
        });

        $total = (int) $grouped->sum('total');
        $top = $grouped->first();

        return [
            'title' => 'Campaign Summary Report',
            'description' => 'Management-level campaign totals with SIP and GSM allocation breakdown.',
            'headers' => ['Campaign', 'Total Allocated', 'SIP', 'GSM', 'Status', 'Created Date', 'Last Updated'],
            'rows' => $rows,
            'totals' => $rows->isNotEmpty() ? [
                'TOTAL',
                number_format($total),
                number_format((int) $grouped->sum('sip')),
                number_format((int) $grouped->sum('gsm')),
                '',
                '',
                '',
            ] : null,
            'summary' => [
                ['label' => 'Campaigns', 'value' => number_format($grouped->count()), 'note' => null, 'tone' => 'blue', 'icon' => 'stack'],
                ['label' => 'Total Allocated', 'value' => number_format($total), 'note' => null, 'tone' => 'green', 'icon' => 'sip'],
            ],
            'insight' => ($top && $total > 0)
                ? $top['name'].' leads this summary with '.number_format($top['total']).' allocated channels.'
                : 'No campaign records match the selected filters.',
            'visible_filters' => ['date_range', 'campaign', 'channel_type'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{id:int,name:string,total:int,sip:int,gsm:int}>
     */
    private function campaignGroups(array $filters): Collection
    {
        return $this->filteredAllocations($filters)
            ->groupBy('campaign_id')
            ->map(function (Collection $items) {
                $campaign = $items->first()?->campaign;

                return [
                    'id' => (int) $items->first()?->campaign_id,
                    'name' => (string) ($campaign?->name ?: 'Unassigned'),
                    'total' => (int) $items->sum('total_channel_allocated'),
                    'sip' => (int) $items->filter(fn ($row) => ChannelTypeClassifier::isSip($row->network, $row->channel_allocation))->sum('total_channel_allocated'),
                    'gsm' => (int) $items->filter(fn ($row) => ChannelTypeClassifier::isGsm($row->network, $row->channel_allocation))->sum('total_channel_allocated'),
                ];
            })
            ->filter(fn (array $row) => $row['total'] > 0)
            ->sortByDesc('total')
            ->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, ChannelAllocation>
     */
    private function filteredAllocations(array $filters): Collection
    {
        $query = ChannelAllocation::query()->with('campaign');
        if ($filters['campaign_id']) {
            $query->where('campaign_id', $filters['campaign_id']);
        }
        $this->constrainDate($query, $filters, 'created_at');
        $rows = $query->get();

        if ($filters['channel_type'] === 'sip') {
            return $rows->filter(fn ($row) => ChannelTypeClassifier::isSip($row->network, $row->channel_allocation))->values();
        }
        if ($filters['channel_type'] === 'gsm') {
            return $rows->filter(fn ($row) => ChannelTypeClassifier::isGsm($row->network, $row->channel_allocation))->values();
        }

        return $rows->values();
    }

    /**
     * @param  list<string>  $visible
     * @return array<string, mixed>
     */
    private function filterOptions(array $visible): array
    {
        $options = [
            'campaigns' => collect(),
            'locations' => collect(),
            'statuses' => collect(),
            'gateways' => collect(),
        ];

        if (in_array('campaign', $visible, true)) {
            $options['campaigns'] = ChannelAllocationCampaign::optionsForDropdown();
        }
        if (in_array('location', $visible, true) || in_array('gsm_gateway', $visible, true)) {
            $catalog = array_values(OperationCatalog::locations());
            $fromGateways = MediaGateway::query()->whereNotNull('site_name')->where('site_name', '!=', '')->orderBy('site_name')->pluck('site_name')->all();
            $fromDefects = DefectiveGsm::query()->whereNotNull('location')->where('location', '!=', '')->orderBy('location')->pluck('location')->all();
            $options['locations'] = collect(array_values(array_unique(array_merge($catalog, $fromGateways, $fromDefects))))->sort()->values();
        }
        if (in_array('status', $visible, true)) {
            $options['statuses'] = collect(['Open', 'In Repair', 'Replaced', 'Closed']);
        }
        if (in_array('gsm_gateway', $visible, true)) {
            $options['gateways'] = MediaGateway::query()->orderBy('site_name')->orderBy('site_code')->get(['id', 'site_name', 'site_code']);
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $visible
     * @return array<string, string>
     */
    private function appliedFilterLabels(array $filters, array $visible): array
    {
        $labels = [];
        if (in_array('date_range', $visible, true)) {
            $labels['Date Range'] = Carbon::parse($filters['date_from'])->format('M d, Y')
                .' – '.Carbon::parse($filters['date_to'])->format('M d, Y');
        }
        if (in_array('campaign', $visible, true)) {
            $name = 'All Campaigns';
            if ($filters['campaign_id']) {
                $name = ChannelAllocationCampaign::query()->whereKey($filters['campaign_id'])->value('name') ?: 'All Campaigns';
            }
            $labels['Campaign'] = $name;
        }
        if (in_array('channel_type', $visible, true)) {
            $labels['Channel Type'] = match ($filters['channel_type']) {
                'sip' => 'SIP',
                'gsm' => 'GSM',
                default => 'All',
            };
        }
        if (in_array('status', $visible, true)) {
            $labels['Status'] = $filters['status'] !== '' ? $filters['status'] : 'All';
        }
        if (in_array('location', $visible, true)) {
            $labels['Location'] = $filters['location'] !== '' ? $filters['location'] : 'All';
        }
        if (in_array('gsm_gateway', $visible, true)) {
            $label = 'All';
            if ($filters['gateway_id']) {
                $gateway = MediaGateway::query()->find($filters['gateway_id']);
                $label = $gateway ? ($gateway->site_name.' ('.$gateway->site_code.')') : 'All';
            }
            $labels['GSM Gateway'] = $label;
        }

        return $labels;
    }

    private function constrainDate($query, array $filters, string $column): void
    {
        $query->whereDate($column, '>=', $filters['date_from'])
            ->whereDate($column, '<=', $filters['date_to']);
    }

    private function defaultDateFrom(): string
    {
        $candidates = array_filter([
            ChannelAllocation::query()->min('created_at'),
            ChannelAllocationCampaign::query()->min('created_at'),
            MediaGateway::query()->min('created_at'),
            DefectiveGsm::query()->min('reported_on'),
            DefectiveGsm::query()->min('created_at'),
        ]);
        if ($candidates === []) {
            return now()->copy()->startOfMonth()->toDateString();
        }

        $from = Carbon::parse(min($candidates))->toDateString();

        return $from < PdcEndorseDate::MIN_DATE ? PdcEndorseDate::MIN_DATE : $from;
    }

    private function dateValue(mixed $value, string $fallback): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return $fallback;
        }
        $parsed = PdcEndorseDate::parse($raw);
        if (! $parsed['valid'] || $parsed['empty'] || $parsed['iso'] === null) {
            return $fallback;
        }

        return $parsed['iso'];
    }

    private function formatDate(mixed $value): string
    {
        if (! $value) {
            return '—';
        }
        if ($value instanceof Carbon) {
            return $value->format('M d, Y');
        }

        try {
            return Carbon::parse((string) $value)->format('M d, Y');
        } catch (\Throwable) {
            return '—';
        }
    }

    private function percentNote(int $part, int $total): string
    {
        $percent = $total > 0 ? round(($part / $total) * 100, 1) : 0.0;

        return number_format($percent, 1).'% of total';
    }

    private function utilizationInsight(int $total, int $allocated, int $available): string
    {
        if ($total < 1) {
            return 'No channel records match the selected filters.';
        }
        $used = $total > 0 ? round(($allocated / $total) * 100, 1) : 0.0;

        return 'Allocated channels are '.number_format($allocated).' of '.number_format($total)
            .' ('.number_format($used, 1).'%), leaving '.number_format($available).' available.';
    }
}
