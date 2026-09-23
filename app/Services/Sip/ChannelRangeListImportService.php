<?php

namespace App\Services\Sip;

use App\Models\SipChannel;
use App\Models\SipChannelNumber;
use App\Services\InventoryImportService;
use App\Services\XlsxService;
use App\Support\ImportCell;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ChannelRangeListImportService
{
    public const SESSION_KEY = 'channel_range_list_import';

    public const CARRY_FIELDS = ['sip_name'];

    /**
     * @return array<string, string>
     */
    public function fields(): array
    {
        return [
            'sip_name' => 'SIP Name',
            'channel_number' => 'Channel Number',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function exportFields(): array
    {
        return [
            'sip_name' => 'SIP Name',
            'channel_range' => 'Channel Range',
            'channel_number' => 'Channel Numbers/Range Entries',
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

        $sipByName = [];
        foreach (SipChannel::query()->orderBy('id')->get() as $sip) {
            $name = mb_strtolower(trim((string) $sip->etpi_sip_name));
            if ($name === '') {
                continue;
            }
            $sipByName[$name][] = $sip;
        }

        $existingNumbers = SipChannelNumber::query()->pluck('channel_number')
            ->map(fn ($number) => (string) $number)
            ->all();
        $fileNumbers = [];
        $carry = ['sip_name' => null];
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
                    $values[$field] = ImportCell::isClear($rawValues[$field]) ? '' : $rawValues[$field];
                }
            }

            $errors = [];
            $sipChannelId = null;

            if ($values['sip_name'] === '') {
                $errors[] = 'SIP Name is required';
            } else {
                $matches = $sipByName[mb_strtolower($values['sip_name'])] ?? [];
                if ($matches === []) {
                    $errors[] = 'SIP Name does not match an existing SIP Channel';
                } elseif (count($matches) > 1) {
                    $errors[] = 'SIP Name matches more than one SIP Channel';
                } else {
                    $sip = $matches[0];
                    $sipChannelId = (int) $sip->id;
                    $values['sip_name'] = (string) ($sip->etpi_sip_name ?: $values['sip_name']);
                }
            }

            $channelNumber = trim((string) $values['channel_number']);
            if ($channelNumber === '') {
                $errors[] = 'Channel Number is required';
            } elseif (! preg_match('/^\d+$/', $channelNumber)) {
                $errors[] = 'Channel Number must be a whole number';
            } else {
                $values['channel_number'] = $channelNumber;
                if (isset($fileNumbers[$channelNumber]) || in_array($channelNumber, $existingNumbers, true)) {
                    $errors[] = 'Channel number already exists';
                } else {
                    $fileNumbers[$channelNumber] = $excelRow;
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
                    'sip_channel_id' => $sipChannelId,
                    'channel_number' => $channelNumber,
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
                SipChannelNumber::query()->create([
                    'sip_channel_id' => $row['sip_channel_id'],
                    'channel_number' => $row['channel_number'],
                ]);
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
        $aliases[strtolower('Channel Numbers/Range Entries')] = 'channel_number';

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
        return ImportCell::isBlankRow($values);
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
}
