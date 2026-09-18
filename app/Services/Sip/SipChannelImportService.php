<?php

namespace App\Services\Sip;

use App\Models\ChannelAllocationCampaign;
use App\Models\SipChannel;
use App\Services\InventoryImportService;
use App\Services\XlsxService;
use App\Support\PdcEndorseDate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SipChannelImportService
{
    public const SESSION_KEY = 'sip_channels_import';

    public const CARRY_FIELDS = ['campaign'];

    /**
     * @return array<string, string>
     */
    public function fields(): array
    {
        return [
            'campaign' => 'Campaign',
            'etpi_sip_name' => 'SIP Name',
            'pilot_number' => 'Pilot Number',
            'channel_count' => 'Channel Count',
            'channel_range' => 'Channel Range',
            'network' => 'Network',
            'date_activation' => 'Date Activation',
        ];
    }

    /**
     * @return list<string>
     */
    public function templateHeaders(): array
    {
        return array_values($this->fields());
    }

    /**
     * @return list<list<string>>
     */
    public function templateRows(): array
    {
        $width = count($this->templateHeaders());
        $rows = [];
        for ($i = 0; $i < InventoryImportService::TEMPLATE_BLANK_ROWS; $i++) {
            $rows[] = array_fill(0, $width, '');
        }

        return $rows;
    }

    /**
     * @return array{valid: bool, summary: array<string, int>, rows: list<array<string, mixed>>, payload: list<array<string, mixed>>, headers: list<string>}
     */
    public function preview(UploadedFile $file, XlsxService $xlsx): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'xls'], true)) {
            throw new RuntimeException('Only .xlsx and .xls files are allowed.');
        }

        [$headers, $rawRows] = $xlsx->read($file->getRealPath() ?: $file->getPathname());
        $map = $this->headerMap($headers);
        foreach (array_keys($this->fields()) as $field) {
            if (! isset($map[$field])) {
                throw new RuntimeException('The file is missing required columns. Download the official Excel template and try again.');
            }
        }

        $campaigns = ChannelAllocationCampaign::keyedByName();
        $existingNames = SipChannel::query()->whereNotNull('etpi_sip_name')->pluck('etpi_sip_name')
            ->map(fn ($name) => mb_strtolower(trim((string) $name)))
            ->all();
        $fileNames = [];
        $carry = ['campaign' => null];
        $previewRows = [];
        $payload = [];

        foreach ($rawRows as $offset => $raw) {
            $excelRow = $offset + 2;
            $rawValues = [];
            foreach (array_keys($this->fields()) as $field) {
                $rawValues[$field] = $this->cell($raw, $map, $field);
            }

            if ($this->rowIsBlank($rawValues)) {
                continue;
            }

            $values = [];
            foreach (array_keys($this->fields()) as $field) {
                if (in_array($field, self::CARRY_FIELDS, true)) {
                    [$resolved, $next] = $this->applyCarry($carry[$field], $rawValues[$field]);
                    $values[$field] = $resolved;
                    $carry[$field] = $next;
                } else {
                    $values[$field] = $rawValues[$field];
                }
            }

            $errors = [];
            $campaignId = null;
            $dateIso = null;
            $channelCount = null;

            if ($values['campaign'] === '') {
                $errors[] = 'Campaign is required';
            } else {
                $campaign = $campaigns->get(mb_strtolower($values['campaign']));
                if (! $campaign) {
                    $errors[] = 'Campaign does not exist';
                } else {
                    $campaignId = (int) $campaign->id;
                    $values['campaign'] = $campaign->name;
                }
            }

            $etpiName = trim((string) $values['etpi_sip_name']);
            if ($etpiName === '') {
                $errors[] = 'SIP Name is required';
            } else {
                $key = mb_strtolower($etpiName);
                $values['etpi_sip_name'] = $etpiName;
                if (isset($fileNames[$key]) || in_array($key, $existingNames, true)) {
                    $errors[] = 'SIP Name already exists';
                } else {
                    $fileNames[$key] = $excelRow;
                }
            }

            $countRaw = trim((string) $values['channel_count']);
            if ($countRaw !== '') {
                if (! preg_match('/^\d+$/', $countRaw)) {
                    $errors[] = 'Channel Count must be a whole number';
                } else {
                    $channelCount = (int) $countRaw;
                    $values['channel_count'] = (string) $channelCount;
                }
            }

            if ($values['date_activation'] !== '') {
                $parsed = PdcEndorseDate::parse($values['date_activation']);
                if (! $parsed['valid'] || $parsed['empty']) {
                    $errors[] = 'Date Activation must be a valid date on or after 1/1/2000';
                } else {
                    $dateIso = $parsed['iso'];
                    $values['date_activation'] = $parsed['display'];
                }
            }

            $ok = $errors === [];
            $preview = [
                'row' => $excelRow,
                'status' => $ok ? 'Valid' : 'Error',
                'error' => implode('; ', $errors),
                'valid' => $ok,
            ];
            foreach (array_keys($this->fields()) as $field) {
                $preview[$field] = $values[$field] ?? '';
            }
            $previewRows[] = $preview;

            if ($ok) {
                $payload[] = [
                    'campaign_id' => $campaignId,
                    'etpi_sip_name' => $etpiName,
                    'pilot_number' => $this->nullable($values['pilot_number']),
                    'channel_count' => $channelCount,
                    'channel_range' => $this->nullable($values['channel_range']),
                    'network' => $this->nullable($values['network']),
                    'date_activation' => $dateIso,
                ];
            }
        }

        if ($previewRows === []) {
            throw new RuntimeException('The Excel file does not contain any data rows.');
        }

        $errorCount = count(array_filter($previewRows, fn ($row) => ! $row['valid']));
        $validCount = count($previewRows) - $errorCount;

        return [
            'valid' => $errorCount === 0,
            'summary' => [
                'total' => count($previewRows),
                'valid' => $validCount,
                'errors' => $errorCount,
            ],
            'rows' => $previewRows,
            'payload' => $errorCount === 0 ? $payload : [],
            'headers' => $this->templateHeaders(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $payload
     */
    public function commit(array $payload): int
    {
        $count = 0;

        DB::transaction(function () use ($payload, &$count) {
            foreach ($payload as $row) {
                SipChannel::query()->create([
                    'campaign_id' => $row['campaign_id'],
                    'etpi_sip_name' => $row['etpi_sip_name'],
                    'pilot_number' => $row['pilot_number'],
                    'channel_count' => $row['channel_count'],
                    'channel_range' => $row['channel_range'],
                    'network' => $row['network'],
                    'date_activation' => $row['date_activation'],
                ])->syncChannelNumbersFromRange();
                $count++;
            }
        });

        return $count;
    }

    /**
     * @param  array<string, mixed>  $preview
     */
    public function storePreview(array $preview): string
    {
        $token = (string) Str::uuid();
        session([self::SESSION_KEY => [
            'token' => $token,
            'valid' => $preview['valid'],
            'summary' => $preview['summary'],
            'rows' => $preview['rows'],
            'payload' => $preview['payload'],
            'headers' => $preview['headers'],
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
        $aliases = [];
        foreach ($this->fields() as $field => $label) {
            $aliases[strtolower($label)] = $field;
            $aliases[strtolower(str_replace('_', ' ', $field))] = $field;
        }

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

    /**
     * @param  array<string, string>  $values
     */
    private function rowIsBlank(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function applyCarry(?string $carry, string $raw): array
    {
        if ($raw === '-') {
            return ['', ''];
        }
        if (trim($raw) === '') {
            return [$carry ?? '', $carry];
        }

        return [$raw, $raw];
    }

    private function nullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
