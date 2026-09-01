<?php

namespace App\Services;

use App\Support\InventoryImportCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class InventoryImportService
{
    public const TEMPLATE_BLANK_ROWS = 10;

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    public function templateHeaders(array $config): array
    {
        return array_merge(['Id'], array_values($config['fields']));
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<list<string>>
     */
    public function templateRows(array $config): array
    {
        $width = count($config['fields']) + 1;
        $rows = [];
        for ($i = 0; $i < self::TEMPLATE_BLANK_ROWS; $i++) {
            $rows[] = array_fill(0, $width, '');
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{valid: bool, summary: array<string, int>, rows: list<array<string, mixed>>, payload: list<array<string, mixed>>, headers: list<string>}
     */
    public function preview(UploadedFile $file, XlsxService $xlsx, array $config): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'xls'], true)) {
            throw new RuntimeException('Only .xlsx and .xls files are allowed.');
        }

        [$headers, $rawRows] = $xlsx->read($file->getRealPath() ?: $file->getPathname());
        $map = $this->headerMap($headers, $config);
        foreach (array_keys($config['fields']) as $field) {
            if (! isset($map[$field])) {
                throw new RuntimeException('The file is missing required columns. Download the official Excel template and try again.');
            }
        }

        $existingIps = InventoryImportCatalog::existingIpv4Addresses();
        $fileIps = [];
        $uniqueTracker = [];
        $carry = [];
        $previewRows = [];
        $payload = [];
        $model = $config['model'];
        $ipFields = $config['ip_fields'] ?? [];
        $required = $config['required'] ?? [];
        $unique = $config['unique'] ?? [];
        $compositeUnique = $config['composite_unique'] ?? [];
        $fixed = $config['fixed'] ?? [];
        $statusOptions = $config['status_options'] ?? [];
        $integerFields = $config['integer_fields'] ?? [];
        $numericFields = $config['numeric_fields'] ?? [];
        $dateFields = $config['date_fields'] ?? [];

        foreach ($rawRows as $offset => $raw) {
            $excelRow = $offset + 2;
            $values = [];
            $rawValues = [];
            foreach (array_keys($config['fields']) as $field) {
                $rawValues[$field] = $this->cell($raw, $map, $field);
            }

            if ($this->rowIsBlank($rawValues)) {
                continue;
            }

            foreach (array_keys($config['fields']) as $field) {
                $value = $rawValues[$field];
                if (isset($fixed[$field]) && $value === '') {
                    $value = (string) $fixed[$field];
                } elseif ($value === '' && ! in_array($field, $ipFields, true)) {
                    $value = $carry[$field] ?? '';
                }
                $values[$field] = $value;
            }

            $errors = [];
            foreach ($fixed as $field => $fixedValue) {
                $current = (string) ($values[$field] ?? '');
                if ($current !== '' && strcasecmp($current, (string) $fixedValue) !== 0) {
                    $errors[] = ($config['fields'][$field] ?? $field).' must be '.$fixedValue;
                }
                $values[$field] = (string) $fixedValue;
            }

            foreach ($required as $field) {
                if (($values[$field] ?? '') === '') {
                    $errors[] = ($config['fields'][$field] ?? $field).' is required';
                }
            }

            foreach ($ipFields as $field) {
                $ip = trim((string) ($values[$field] ?? ''));
                if ($ip === '') {
                    continue;
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                    $errors[] = ($config['fields'][$field] ?? 'IP Address').' must be a valid IPv4 address';
                    continue;
                }
                $normalized = strtolower($ip);
                if (in_array($normalized, $existingIps, true) || isset($fileIps[$normalized])) {
                    $errors[] = ($config['fields'][$field] ?? 'IP Address').' already exists';
                } else {
                    $fileIps[$normalized] = $excelRow;
                }
            }

            foreach ($unique as $field) {
                $value = trim((string) ($values[$field] ?? ''));
                if ($value === '') {
                    continue;
                }
                $key = mb_strtolower($value);
                if (isset($uniqueTracker[$field][$key])) {
                    $errors[] = ($config['fields'][$field] ?? $field).' is duplicated in the file';
                    continue;
                }
                $uniqueTracker[$field][$key] = $excelRow;
                $exists = $model::query()->whereRaw('LOWER('.$field.') = ?', [$key])->exists();
                if ($exists) {
                    $errors[] = ($config['fields'][$field] ?? $field).' already exists';
                }
            }

            foreach ($compositeUnique as $fields) {
                $parts = [];
                $empty = false;
                foreach ($fields as $field) {
                    $part = trim((string) ($values[$field] ?? ''));
                    if ($part === '') {
                        $empty = true;
                        break;
                    }
                    $parts[$field] = $part;
                }
                if ($empty) {
                    continue;
                }
                $comboKey = mb_strtolower(implode('|', $parts));
                $trackerKey = implode('+', $fields);
                if (isset($uniqueTracker[$trackerKey][$comboKey])) {
                    $errors[] = 'This combination already exists in the file';
                    continue;
                }
                $uniqueTracker[$trackerKey][$comboKey] = $excelRow;
                $query = $model::query();
                foreach ($parts as $field => $part) {
                    $query->whereRaw('LOWER('.$field.') = ?', [mb_strtolower($part)]);
                }
                if ($query->exists()) {
                    $errors[] = 'This combination already exists';
                }
            }

            foreach ($integerFields as $field) {
                $value = trim((string) ($values[$field] ?? ''));
                if ($value === '') {
                    continue;
                }
                if (preg_match('/^-?\d+\.0+$/', $value)) {
                    $value = (string) (int) $value;
                    $values[$field] = $value;
                }
                if (! preg_match('/^-?\d+$/', $value) || (int) $value < 0) {
                    $errors[] = ($config['fields'][$field] ?? $field).' must be a whole number';
                }
            }

            foreach ($numericFields as $field) {
                $value = trim((string) ($values[$field] ?? ''));
                if ($value === '') {
                    continue;
                }
                if (! is_numeric($value) || (float) $value < 0) {
                    $errors[] = ($config['fields'][$field] ?? $field).' must be a number';
                }
            }

            foreach ($dateFields as $field) {
                $value = trim((string) ($values[$field] ?? ''));
                if ($value === '') {
                    continue;
                }
                if (strtotime($value) === false) {
                    $errors[] = ($config['fields'][$field] ?? $field).' must be a valid date';
                }
            }

            if ($statusOptions !== [] && ($values['status'] ?? '') !== '' && ! in_array($values['status'], $statusOptions, true)) {
                $errors[] = 'Status is invalid';
            }

            if (isset($values['contract_start'], $values['contract_end'])
                && $values['contract_start'] !== ''
                && $values['contract_end'] !== ''
                && strtotime((string) $values['contract_end']) < strtotime((string) $values['contract_start'])
            ) {
                $errors[] = 'Contract end date must be on or after the contract start date.';
            }

            $ok = $errors === [];
            $preview = [
                'row' => $excelRow,
                'status' => $ok ? 'Valid' : 'Error',
                'error' => implode('; ', $errors),
                'valid' => $ok,
            ];
            foreach (array_keys($config['fields']) as $field) {
                $preview[$field] = $values[$field] ?? '';
            }
            $previewRows[] = $preview;

            if ($ok) {
                $record = [];
                foreach (array_keys($config['fields']) as $field) {
                    $value = $values[$field] ?? '';
                    $record[$field] = $value === '' ? null : $value;
                }
                $payload[] = $record;
            }

            foreach (array_keys($config['fields']) as $field) {
                if (! in_array($field, $ipFields, true) && ($values[$field] ?? '') !== '') {
                    $carry[$field] = $values[$field];
                }
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
            'headers' => array_values($config['fields']),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<array<string, mixed>>  $payload
     */
    public function commit(array $payload, array $config): int
    {
        $model = $config['model'];
        $count = 0;

        DB::transaction(function () use ($payload, $model, &$count) {
            foreach ($payload as $row) {
                $model::query()->create($row);
                $count++;
            }
        });

        return $count;
    }

    /**
     * @param  array<string, mixed>  $preview
     */
    public function storePreview(string $key, array $preview): string
    {
        $token = (string) Str::uuid();
        session([$this->sessionKey($key) => [
            'token' => $token,
            'valid' => $preview['valid'],
            'summary' => $preview['summary'],
            'rows' => $preview['rows'],
            'payload' => $preview['payload'],
            'headers' => $preview['headers'],
        ]]);

        return $token;
    }

    public function previewFromSession(string $key, ?string $token): ?array
    {
        $stored = session($this->sessionKey($key));
        if (! is_array($stored) || ($stored['token'] ?? null) !== $token) {
            return null;
        }

        return $stored;
    }

    public function forget(string $key): void
    {
        session()->forget($this->sessionKey($key));
    }

    public function sessionKey(string $key): string
    {
        return 'inventory_import_'.$key;
    }

    /**
     * @param  list<string>  $headers
     * @param  array<string, mixed>  $config
     * @return array<string, int>
     */
    private function headerMap(array $headers, array $config): array
    {
        $aliases = ['id' => 'id'];
        foreach ($config['fields'] as $field => $label) {
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
}
