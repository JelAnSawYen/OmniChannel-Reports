<?php

namespace App\Services\ChannelAllocation;

use App\Models\ChannelAllocationCampaign;
use App\Support\ChannelAllocationResolver;
use App\Support\ChannelAllocationRules;
use App\Support\ImportRowValidationException;
use App\Services\XlsxService;
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
            'Channel',
            'Network',
            'Line Priority',
            'Channel Count',
            'FTE',
            'Caller ID',
            'Prefix',
            'Remarks',
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
        if (! isset($map['campaign']) || ! isset($map['channel'])) {
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
        $carryCaller = null;
        $carryPrefix = null;
        $carryRemarks = '';

        foreach ($rawRows as $offset => $raw) {
            $excelRow = $offset + 2;
            $campaignName = $this->cell($raw, $map, 'campaign');
            $rawCaller = $this->cell($raw, $map, 'caller_id');
            $rawPrefix = $this->cell($raw, $map, 'prefix');
            $remarks = $this->cell($raw, $map, 'remarks');
            $channel = $this->cell($raw, $map, 'channel');
            $linePriority = $this->cell($raw, $map, 'line_priority');

            if ($campaignName === '') {
                $campaignName = $carryCampaign;
            }
            $callerId = $this->applyCarryForward($carryCaller, $rawCaller);
            $prefix = $this->applyCarryForward($carryPrefix, $rawPrefix);
            if ($remarks === '' && $campaignName === $carryCampaign) {
                $remarks = $carryRemarks;
            }

            $normalizedPriority = $this->normalizeInteger($linePriority);
            $ruleErrors = $this->ruleErrors([
                'campaign' => $campaignName,
                'caller_id' => $callerId,
                'prefix' => $prefix,
                'remarks' => $remarks,
                'line_priority' => $normalizedPriority === '' ? null : $normalizedPriority,
            ]);
            $errors = array_merge(...array_values($ruleErrors ?: [[]]));

            $linePriorityValue = $normalizedPriority === '' || isset($ruleErrors['line_priority'])
                ? null
                : (int) $normalizedPriority;
            $resolved = ChannelAllocationResolver::resolve($channel);
            if (! ($resolved['ok'] ?? false)) {
                $errors[] = $this->fieldError('channel', $resolved['error'] ?? 'Channel is invalid');
            }

            $campaignKey = mb_strtolower($campaignName);
            $allocationKey = $campaignKey.'|'.mb_strtolower($channel);
            if ($campaignName !== '' && $channel !== '' && $channel !== '-') {
                if (isset($fileAllocations[$allocationKey])) {
                    $errors[] = $this->fieldError('channel', 'Duplicate Channel in file');
                } else {
                    $fileAllocations[$allocationKey] = $excelRow;
                }

                $existing = $existingCampaigns->get($campaignKey);
                if ($existing && $existing->allocations->contains(function ($allocation) use ($channel) {
                    return strcasecmp($allocation->channelLabel(), $channel) === 0;
                })) {
                    $errors[] = $this->fieldError('channel', 'Channel already exists');
                }
            }

            $existing = $campaignName !== '' ? $existingCampaigns->get($campaignKey) : null;
            $fteDisplay = ($existing && $existing->fte !== null) ? (string) $existing->fte : '';
            $network = ($resolved['ok'] ?? false) ? (string) ($resolved['network'] ?? '') : '';
            $channelCount = ($resolved['ok'] ?? false) ? $resolved['total_channel_allocated'] : null;

            $ok = $errors === [];
            $previewRows[] = [
                'row' => $excelRow,
                'campaign' => $campaignName,
                'fte' => $fteDisplay,
                'caller_id' => $callerId,
                'prefix' => $prefix,
                'channel' => $channel,
                'network' => $network,
                'line_priority' => $linePriority,
                'total_channel_allocated' => $channelCount === null ? '' : (string) $channelCount,
                'status' => $ok ? 'Valid' : 'Error',
                'error' => implode('; ', $errors),
                'valid' => $ok,
            ];

            if ($ok) {
                if (! isset($payloadCampaigns[$campaignKey])) {
                    $payloadCampaigns[$campaignKey] = [
                        'name' => $campaignName,
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
                    'row' => $excelRow,
                    'media_gateway' => $resolved['media_gateway'],
                    'channel_allocation' => $resolved['channel'],
                    'network' => $resolved['network'],
                    'line_priority' => $linePriorityValue,
                    'total_channel_allocated' => $resolved['total_channel_allocated'],
                ];
            }

            if ($campaignName !== '') {
                $carryCampaign = $campaignName;
                $carryCaller = $this->nextCarry($carryCaller, $rawCaller, $callerId);
                $carryPrefix = $this->nextCarry($carryPrefix, $rawPrefix, $prefix);
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
                'allocations' => count(array_filter($previewRows, fn ($row) => $row['channel'] !== '')),
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

        $this->revalidate($payload);

        DB::transaction(function () use ($payload, &$campaignCount, &$allocationCount) {
            foreach ($payload as $item) {
                $campaign = ChannelAllocationCampaign::query()
                    ->listedInCampaigns()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $item['name'])])
                    ->lockForUpdate()
                    ->first();

                if (! $campaign) {
                    $rowErrors = [];
                    foreach ($item['allocations'] as $allocation) {
                        $row = (int) ($allocation['row'] ?? 0);
                        $rowErrors[$row][] = $this->fieldError('campaign', ChannelAllocationRules::CAMPAIGN_NOT_IN_MASTER);
                    }
                    throw new ImportRowValidationException($rowErrors);
                }

                $wasListed = (bool) $campaign->listed_in_channel_allocation;
                if (! $wasListed) {
                    $campaign->update([
                        'caller_id' => $item['caller_id'],
                        'prefix' => $item['prefix'],
                        'remarks' => $item['remarks'],
                    ]);
                    $campaignCount++;
                }
                $campaign->markListedInChannelAllocation();

                $sort = (int) $campaign->allocations()->max('sort_order');
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

    /**
     * Re-run the row rules on the server before writing, so data that changed
     * between Preview and Confirm cannot slip through.
     *
     * @param  list<array<string, mixed>>  $payload
     */
    private function revalidate(array $payload): void
    {
        $rowErrors = [];

        foreach ($payload as $item) {
            $campaignName = (string) ($item['name'] ?? '');
            $campaign = ChannelAllocationCampaign::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($campaignName)])
                ->with('allocations')
                ->first();
            $taken = $campaign
                ? $campaign->allocations->map(fn ($allocation) => mb_strtolower((string) $allocation->channelLabel()))->all()
                : [];

            foreach ($item['allocations'] as $allocation) {
                $row = (int) ($allocation['row'] ?? 0);
                $channel = (string) ($allocation['channel_allocation'] ?? '');
                $messages = $this->ruleErrors([
                    'campaign' => $campaignName,
                    'caller_id' => $item['caller_id'],
                    'prefix' => $item['prefix'],
                    'remarks' => $item['remarks'],
                    'line_priority' => $allocation['line_priority'],
                ]);
                $messages = array_merge(...array_values($messages ?: [[]]));

                $resolved = ChannelAllocationResolver::resolve($channel);
                if (! ($resolved['ok'] ?? false)) {
                    $messages[] = $this->fieldError('channel', $resolved['error'] ?? 'Channel is invalid');
                } elseif (in_array(mb_strtolower($channel), $taken, true)) {
                    $messages[] = $this->fieldError('channel', 'Channel already exists');
                }

                if ($messages !== []) {
                    $rowErrors[$row] = array_values(array_unique(array_merge($rowErrors[$row] ?? [], $messages)));

                    continue;
                }

                $taken[] = mb_strtolower($channel);
            }
        }

        if ($rowErrors !== []) {
            ksort($rowErrors);
            throw new ImportRowValidationException($rowErrors);
        }
    }

    /**
     * Flag the stored preview rows with the exact messages found during Confirm.
     *
     * @param  array<int, list<string>>  $rowErrors
     * @return array{valid: bool, summary: array<string, int>, rows: list<array<string, mixed>>}
     */
    public function applyRowErrors(?string $token, array $rowErrors): array
    {
        $stored = $this->previewFromSession($token) ?? ['rows' => [], 'summary' => []];
        $rows = [];
        foreach ($stored['rows'] ?? [] as $row) {
            $messages = $rowErrors[(int) ($row['row'] ?? 0)] ?? [];
            if ($messages !== []) {
                $row['valid'] = false;
                $row['status'] = 'Error';
                $row['error'] = implode('; ', array_values(array_unique(array_filter(
                    array_merge($row['error'] !== '' ? [$row['error']] : [], $messages)
                ))));
            }
            $rows[] = $row;
        }

        $errorCount = count(array_filter($rows, fn ($row) => ! ($row['valid'] ?? false)));
        $summary = array_merge($stored['summary'] ?? [], [
            'total' => count($rows),
            'valid' => count($rows) - $errorCount,
            'errors' => $errorCount,
        ]);

        session([self::SESSION_KEY => array_merge($stored, [
            'valid' => false,
            'summary' => $summary,
            'rows' => $rows,
            'payload' => [],
        ])]);

        return ['valid' => false, 'summary' => $summary, 'rows' => $rows];
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
            'fte' => 'fte',
            'caller id' => 'caller_id',
            'prefix' => 'prefix',
            'remarks' => 'remarks',
            'channel' => 'channel',
            'channel allocation' => 'channel',
            'line priority' => 'line_priority',
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
     * Validate a row with the same rules the Add / Edit forms use.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, list<string>>
     */
    private function ruleErrors(array $values): array
    {
        $validator = validator(
            $values,
            ChannelAllocationRules::importRow(),
            ChannelAllocationRules::importMessages()
        );

        $errors = [];
        foreach ($validator->errors()->messages() as $field => $messages) {
            foreach ((array) $messages as $message) {
                $errors[$field][] = $this->fieldError($field, (string) $message);
            }
        }

        $name = trim((string) ($values['campaign'] ?? ''));
        if ($name !== '' && empty($errors['campaign']) && ChannelAllocationCampaign::masterByName($name) === null) {
            $errors['campaign'][] = $this->fieldError('campaign', ChannelAllocationRules::CAMPAIGN_NOT_IN_MASTER);
        }

        return $errors;
    }

    /**
     * Name the column the problem belongs to without repeating it when the
     * message already starts with that label.
     */
    private function fieldError(string $field, string $message): string
    {
        $label = ChannelAllocationRules::label($field);
        $message = trim($message);
        if (stripos($message, $label) === 0) {
            return $message;
        }

        return $label.': '.$message;
    }

    private function normalizeInteger(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^-?\d+\.0+$/', $value)) {
            return (string) (int) $value;
        }

        return $value;
    }
}
