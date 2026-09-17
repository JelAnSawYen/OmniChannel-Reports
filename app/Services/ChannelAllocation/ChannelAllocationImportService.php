<?php

namespace App\Services\ChannelAllocation;

use App\Models\ChannelAllocationCampaign;
use App\Services\XlsxService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ChannelAllocationImportService
{
    public const SESSION_KEY = 'channel_allocation_import';

    /**
     * @return list<string>
     */
    public function templateHeaders(): array
    {
        return [
            'Campaign',
            'Media Gateway',
            'FTE',
            'Caller ID',
            'Prefix',
            'Remarks',
            'Channel Allocation',
            'Network',
            'Line Priority',
            'Total Channel Allocated',
        ];
    }

    /**
     * @return list<list<string|int>>
     */
    public function templateRows(): array
    {
        $width = count($this->templateHeaders());
        $rows = [];
        for ($i = 0; $i < 10; $i++) {
            $rows[] = array_fill(0, $width, '');
        }

        return $rows;
    }

    /**
     * @return array{valid: bool, summary: array<string, int>, rows: list<array<string, mixed>>, payload: list<array<string, mixed>>}
     */
    public function preview(UploadedFile $file, XlsxService $xlsx): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'xls'], true)) {
            throw new RuntimeException('Only .xlsx and .xls files are allowed.');
        }

        [$headers, $rawRows] = $xlsx->read($file->getRealPath() ?: $file->getPathname());
        $map = $this->headerMap($headers);
        if (! isset($map['campaign']) || ! isset($map['channel_allocation'])) {
            throw new RuntimeException('The file is missing required columns. Download the official Excel template and try again.');
        }

        $existingCampaigns = ChannelAllocationCampaign::query()
            ->with('allocations')
            ->get()
            ->keyBy(fn (ChannelAllocationCampaign $campaign) => mb_strtolower($campaign->name));
        $fileAllocations = [];
        $previewRows = [];
        $payloadCampaigns = [];

        $carryCampaign = '';
        $carryGateway = null;
        $carryCaller = null;
        $carryPrefix = null;
        $carryNetwork = null;
        $carryRemarks = '';

        foreach ($rawRows as $offset => $raw) {
            $excelRow = $offset + 2;
            $campaignName = $this->cell($raw, $map, 'campaign');
            $rawGateway = $this->cell($raw, $map, 'media_gateway');
            $rawCaller = $this->cell($raw, $map, 'caller_id');
            $rawPrefix = $this->cell($raw, $map, 'prefix');
            $remarks = $this->cell($raw, $map, 'remarks');
            $channelAllocation = $this->cell($raw, $map, 'channel_allocation');
            $rawNetwork = $this->cell($raw, $map, 'network');
            $linePriority = $this->cell($raw, $map, 'line_priority');
            $totalAllocated = $this->cell($raw, $map, 'total_channel_allocated');

            if ($campaignName === '') {
                $campaignName = $carryCampaign;
            }
            $mediaGateway = $this->applyCarryForward($carryGateway, $rawGateway);
            $callerId = $this->applyCarryForward($carryCaller, $rawCaller);
            $prefix = $this->applyCarryForward($carryPrefix, $rawPrefix);
            $network = $this->applyCarryForward($carryNetwork, $rawNetwork);
            if ($remarks === '' && $campaignName === $carryCampaign) {
                $remarks = $carryRemarks;
            }

            $errors = [];
            if ($campaignName === '') {
                $errors[] = 'Campaign is required';
            } elseif (mb_strlen($campaignName) > 255) {
                $errors[] = 'Campaign must be 255 characters or fewer';
            }

            if ($mediaGateway === '' && $rawGateway !== '-' && $carryGateway === null) {
                $errors[] = 'Missing Media Gateway';
            } elseif ($mediaGateway !== '') {
                $this->validateMediaGateway($mediaGateway, $errors);
            }

            $linePriorityValue = $this->parseInteger($linePriority, 'Line Priority', false, $errors);
            $totalValue = $this->parseInteger($totalAllocated, 'Total Channel Allocated', true, $errors);

            if ($channelAllocation === '') {
                $errors[] = 'Channel Allocation is required';
            } elseif ($channelAllocation === '-') {
                $errors[] = 'Channel Allocation cannot be -';
            } elseif (mb_strlen($channelAllocation) > 255) {
                $errors[] = 'Channel Allocation must be 255 characters or fewer';
            }

            if (mb_strlen($network) > 255) {
                $errors[] = 'Network must be 255 characters or fewer';
            }

            $campaignKey = mb_strtolower($campaignName);
            $allocationKey = $campaignKey.'|'.mb_strtolower($channelAllocation);
            if ($campaignName !== '' && $channelAllocation !== '') {
                if (isset($fileAllocations[$allocationKey])) {
                    $errors[] = 'Duplicate Channel Allocation in file';
                } else {
                    $fileAllocations[$allocationKey] = $excelRow;
                }

                $existing = $existingCampaigns->get($campaignKey);
                if ($existing && $existing->allocations->contains(fn ($allocation) => strcasecmp((string) $allocation->channel_allocation, $channelAllocation) === 0)) {
                    $errors[] = 'Channel Allocation already exists';
                }
            }

            $existing = $campaignName !== '' ? $existingCampaigns->get($campaignKey) : null;
            $fteDisplay = ($existing && $existing->fte !== null) ? (string) $existing->fte : '';

            $ok = $errors === [];
            $previewRows[] = [
                'row' => $excelRow,
                'campaign' => $campaignName,
                'media_gateway' => $mediaGateway,
                'fte' => $fteDisplay,
                'caller_id' => $callerId,
                'prefix' => $prefix,
                'channel_allocation' => $channelAllocation,
                'network' => $network,
                'line_priority' => $linePriority,
                'total_channel_allocated' => $totalAllocated,
                'status' => $ok ? 'Valid' : 'Error',
                'error' => implode('; ', $errors),
                'valid' => $ok,
            ];

            if ($ok) {
                if (! isset($payloadCampaigns[$campaignKey])) {
                    $payloadCampaigns[$campaignKey] = [
                        'name' => $campaignName,
                        'media_gateway' => $mediaGateway !== '' ? $mediaGateway : null,
                        'caller_id' => $callerId !== '' ? $callerId : null,
                        'prefix' => $prefix !== '' ? $prefix : null,
                        'remarks' => $remarks !== '' ? $remarks : null,
                        'allocations' => [],
                    ];
                } else {
                    if ($rawPrefix === '-') {
                        $payloadCampaigns[$campaignKey]['prefix'] = null;
                    } elseif ($prefix !== '') {
                        $payloadCampaigns[$campaignKey]['prefix'] = $prefix;
                    }
                    if ($rawCaller === '-') {
                        $payloadCampaigns[$campaignKey]['caller_id'] = null;
                    } elseif ($callerId !== '') {
                        $payloadCampaigns[$campaignKey]['caller_id'] = $callerId;
                    }
                }
                $payloadCampaigns[$campaignKey]['allocations'][] = [
                    'media_gateway' => $mediaGateway !== '' ? $mediaGateway : null,
                    'channel_allocation' => $channelAllocation,
                    'network' => $network !== '' ? $network : null,
                    'line_priority' => $linePriorityValue,
                    'total_channel_allocated' => $totalValue,
                ];
            }

            if ($campaignName !== '') {
                $carryCampaign = $campaignName;
                $carryGateway = $this->nextCarry($carryGateway, $rawGateway, $mediaGateway);
                $carryCaller = $this->nextCarry($carryCaller, $rawCaller, $callerId);
                $carryPrefix = $this->nextCarry($carryPrefix, $rawPrefix, $prefix);
                $carryNetwork = $this->nextCarry($carryNetwork, $rawNetwork, $network);
                $carryRemarks = $remarks;
            }
        }

        if ($previewRows === []) {
            throw new RuntimeException('The Excel file does not contain any data rows.');
        }

        $errorCount = count(array_filter($previewRows, fn ($row) => ! $row['valid']));
        $validCount = count($previewRows) - $errorCount;
        $allValid = $errorCount === 0;

        return [
            'valid' => $allValid,
            'summary' => [
                'total' => count($previewRows),
                'campaigns' => count(array_unique(array_filter(array_column($previewRows, 'campaign')))),
                'allocations' => count(array_filter($previewRows, fn ($row) => $row['channel_allocation'] !== '')),
                'valid' => $validCount,
                'errors' => $errorCount,
            ],
            'rows' => $previewRows,
            'payload' => $allValid ? array_values($payloadCampaigns) : [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $payload
     * @return array{campaigns: int, allocations: int}
     */
    public function commit(array $payload): array
    {
        $campaignCount = 0;
        $allocationCount = 0;

        DB::transaction(function () use ($payload, &$campaignCount, &$allocationCount) {
            foreach ($payload as $item) {
                $campaign = ChannelAllocationCampaign::query()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $item['name'])])
                    ->lockForUpdate()
                    ->first();

                if (! $campaign) {
                    try {
                        $campaign = ChannelAllocationCampaign::query()->create([
                            'name' => $item['name'],
                            'media_gateway' => $item['media_gateway'],
                            'caller_id' => $item['caller_id'],
                            'prefix' => $item['prefix'],
                            'remarks' => $item['remarks'],
                            'total_channels_allocated' => 0,
                            'sort_order' => (int) ChannelAllocationCampaign::query()->max('sort_order') + 1,
                        ]);
                        $campaignCount++;
                    } catch (UniqueConstraintViolationException) {
                        $campaign = ChannelAllocationCampaign::query()
                            ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $item['name'])])
                            ->lockForUpdate()
                            ->firstOrFail();
                    }
                }

                $sort = (int) $campaign->allocations()->lockForUpdate()->max('sort_order');
                foreach ($item['allocations'] as $allocation) {
                    $sort++;
                    $campaign->allocations()->create([
                        'media_gateway' => $allocation['media_gateway'],
                        'channel_allocation' => $allocation['channel_allocation'],
                        'network' => $allocation['network'],
                        'line_priority' => $allocation['line_priority'],
                        'total_channel_allocated' => $allocation['total_channel_allocated'],
                        'sort_order' => $sort,
                    ]);
                    $allocationCount++;
                }

                $campaign->refreshTotalChannelsAllocated();
            }
        });

        return ['campaigns' => $campaignCount, 'allocations' => $allocationCount];
    }

    public function storePreview(array $preview): string
    {
        $token = (string) Str::uuid();
        session([self::SESSION_KEY => [
            'token' => $token,
            'valid' => $preview['valid'],
            'summary' => $preview['summary'],
            'rows' => $preview['rows'],
            'payload' => $preview['payload'],
        ]]);

        return $token;
    }

    public function previewFromSession(?string $token): ?array
    {
        $stored = session(self::SESSION_KEY);
        if (! is_array($stored) || ($stored['token'] ?? null) !== $token) {
            return null;
        }

        return $stored;
    }

    public function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, int>
     */
    private function headerMap(array $headers): array
    {
        $aliases = [
            'campaign' => 'campaign',
            'media gateway' => 'media_gateway',
            'fte' => 'fte',
            'caller id' => 'caller_id',
            'prefix' => 'prefix',
            'remarks' => 'remarks',
            'channel allocation' => 'channel_allocation',
            'network' => 'network',
            'line priority' => 'line_priority',
            'total channel allocated' => 'total_channel_allocated',
        ];

        $map = [];
        foreach ($headers as $index => $header) {
            $key = strtolower(trim((string) $header));
            if (isset($aliases[$key])) {
                $map[$aliases[$key]] = $index;
            }
        }

        return $map;
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int>  $map
     */
    private function cell(array $row, array $map, string $field): string
    {
        if (! isset($map[$field])) {
            return '';
        }

        return trim((string) ($row[$map[$field]] ?? ''));
    }

    private function applyCarryForward(?string $carry, string $raw): string
    {
        if ($raw === '-') {
            return '';
        }
        if ($raw === '') {
            return $carry ?? '';
        }

        return $raw;
    }

    private function nextCarry(?string $carry, string $raw, string $resolved): ?string
    {
        if ($raw === '-') {
            return '';
        }
        if ($raw !== '' || $carry !== null) {
            return $resolved;
        }

        return $carry;
    }

    /**
     * @param  list<string>  $errors
     */
    private function validateMediaGateway(string $value, array &$errors): void
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $value)), fn (string $part) => $part !== ''));
        if ($parts === [] || count($parts) > 2) {
            $errors[] = 'Media Gateway must contain 1 or 2 IPv4 addresses';

            return;
        }

        foreach ($parts as $part) {
            if (filter_var($part, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                $errors[] = 'Media Gateway must be a valid IPv4 address';

                return;
            }
        }
    }

    /**
     * @param  list<string>  $errors
     */
    private function parseInteger(string $value, string $label, bool $required, array &$errors): ?int
    {
        if ($value === '') {
            if ($required) {
                $errors[] = $label.' is required';
            }

            return null;
        }

        if (preg_match('/^-?\d+\.0+$/', $value)) {
            $value = (string) (int) $value;
        }

        if (! preg_match('/^-?\d+$/', $value)) {
            $errors[] = $label.' must be a whole number';

            return null;
        }

        $number = (int) $value;
        if ($number < 0) {
            $errors[] = $label.' must be 0 or greater';

            return null;
        }

        return $number;
    }
}
