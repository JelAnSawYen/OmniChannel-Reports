<?php

namespace App\Services\Sip;

use App\Models\SipChannel;
use App\Models\SipChannelNumber;
use App\Services\InventoryImportService;
use App\Services\XlsxService;
use App\Support\ImportRowValidationException;
use App\Support\PdcEndorseDate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SipChannelImportService
{
    public const SESSION_KEY = 'sip_channels_import';

    public const CARRY_FIELDS = [];

    /**
     * @return array<string, string>
     */
    public function fields(): array
    {
        return [
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

        $existingNames = SipChannel::query()->whereNotNull('etpi_sip_name')->pluck('etpi_sip_name')
            ->map(fn ($name) => mb_strtolower(trim((string) $name)))
            ->all();
        $existingDigits = SipChannelNumber::query()->pluck('channel_number')
            ->map(fn ($number) => SipChannel::numericKey((string) $number))
            ->filter()
            ->values()
            ->all();
        $fileNames = [];
        $fileDigits = [];
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
                $values[$field] = $rawValues[$field];
            }

            $errors = [];
            $dateIso = null;
            $channelCount = null;

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

            $range = trim((string) $values['channel_range']);
            if ($range !== '') {
                $values['channel_range'] = $range;
                foreach ($this->channelRangeErrors($range, $fileDigits, $existingDigits) as $message) {
                    $errors[] = $message;
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
                    'row' => $excelRow,
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
        $this->revalidate($payload);

        DB::transaction(function () use ($payload, &$count) {
            foreach ($payload as $row) {
                SipChannel::query()->create([
                    'etpi_sip_name' => $row['etpi_sip_name'],
                    'pilot_number' => $row['pilot_number'],
                    'channel_count' => $row['channel_count'],
                    'channel_range' => $this->normalizedRange($row['channel_range'] ?? null),
                    'network' => $row['network'],
                    'date_activation' => $row['date_activation'],
                ])->syncChannelNumbersFromRange();
                $count++;
            }
        });

        return $count;
    }

    /**
     * @param  list<array<string, mixed>>  $payload
     */
    private function revalidate(array $payload): void
    {
        $rowErrors = [];
        $existingNames = SipChannel::query()->whereNotNull('etpi_sip_name')->pluck('etpi_sip_name')
            ->map(fn ($name) => mb_strtolower(trim((string) $name)))
            ->all();
        $existingDigits = SipChannelNumber::query()->pluck('channel_number')
            ->map(fn ($number) => SipChannel::numericKey((string) $number))
            ->filter()
            ->all();
        $fileNames = [];
        $fileDigits = [];

        foreach ($payload as $item) {
            $row = (int) ($item['row'] ?? 0);
            $messages = [];
            $name = mb_strtolower(trim((string) ($item['etpi_sip_name'] ?? '')));
            if ($name === '') {
                $messages[] = 'SIP Name is required';
            } elseif (isset($fileNames[$name]) || in_array($name, $existingNames, true)) {
                $messages[] = 'SIP Name already exists';
            } else {
                $fileNames[$name] = $row;
            }

            $range = trim((string) ($item['channel_range'] ?? ''));
            if ($range !== '') {
                $messages = array_merge($messages, $this->channelRangeErrors($range, $fileDigits, $existingDigits));
            }

            if ($messages !== []) {
                $rowErrors[$row] = array_values(array_unique($messages));
            }
        }

        if ($rowErrors !== []) {
            ksort($rowErrors);
            throw new ImportRowValidationException($rowErrors);
        }
    }

    /**
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

    /**
     * @param  array<string, int>  $fileDigits
     * @param  list<string>  $existingDigits
     * @return list<string>
     */
    private function channelRangeErrors(string $range, array &$fileDigits, array $existingDigits): array
    {
        [$from, $to] = SipChannel::boundsFromRange($range);
        if ($from === '' || $to === '') {
            return ['Channel Range must include valid From and To values'];
        }

        try {
            $numbers = SipChannel::expandRangeNumbers($from, $to);
        } catch (ValidationException $exception) {
            $messages = [];
            foreach ($exception->errors() as $fieldMessages) {
                foreach ((array) $fieldMessages as $message) {
                    $messages[] = (string) $message;
                }
            }

            return $messages !== [] ? $messages : ['Channel Range is invalid'];
        }

        $errors = [];
        $conflicts = [];
        foreach ($numbers as $number) {
            $key = SipChannel::numericKey((string) $number);
            if ($key === '') {
                continue;
            }
            if (isset($fileDigits[$key]) || in_array($key, $existingDigits, true)) {
                $conflicts[] = $number;
            } else {
                $fileDigits[$key] = 1;
            }
        }
        if ($conflicts !== []) {
            $errors[] = 'Channel number already exists: '.implode(', ', array_slice(array_values(array_unique($conflicts)), 0, 8));
        }

        return $errors;
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

    private function normalizedRange(mixed $range): ?string
    {
        $range = trim((string) $range);
        if ($range === '') {
            return null;
        }

        [$from, $to] = SipChannel::boundsFromRange($range);

        return SipChannel::formatRangeFromBounds($from, $to);
    }
}
