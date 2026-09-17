<?php

namespace App\Services\Archive;

use App\Models\ArchiveRecording;
use App\Models\ChannelAllocationCampaign;
use App\Services\InventoryImportService;
use App\Services\XlsxService;
use App\Support\Archive\AudioDuration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ArchiveRecordingImportService
{
    public const SESSION_KEY = 'archive_recordings_import';

    public const CARRY_FIELDS = ['campaign'];

    /**
     * @return array<string, string>
     */
    public function fields(): array
    {
        return [
            'campaign' => 'Campaign',
            'file_name' => 'File Name',
            'called_at' => 'Call Date & Time',
            'caller_number' => 'Caller Number',
            'agent_number' => 'Agent Number',
            'duration' => 'Duration',
            'location' => 'Location',
            'storage_path' => 'Storage Path',
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
            $calledAt = null;

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

            $fileName = trim((string) $values['file_name']);
            if ($fileName === '') {
                $errors[] = 'File Name is required';
            } else {
                $values['file_name'] = $fileName;
            }

            if ($values['called_at'] === '') {
                $errors[] = 'Call Date & Time is required';
            } else {
                $calledAt = $this->parseDateTime($values['called_at']);
                if ($calledAt === null) {
                    $errors[] = 'Call Date & Time must be a valid date and time';
                }
            }

            $duration = $this->normalizeDuration($values['duration']);
            if ($values['duration'] !== '' && $duration === null) {
                $errors[] = 'Duration must be HH:MM:SS or a whole number of seconds';
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
                $storagePath = $this->nullable($values['storage_path']) ?: $fileName;
                $payload[] = [
                    'campaign_id' => $campaignId,
                    'file_name' => $fileName,
                    'called_at' => $calledAt,
                    'caller_number' => $this->nullable($values['caller_number']),
                    'agent_number' => $this->nullable($values['agent_number']),
                    'duration' => $duration,
                    'location' => $this->nullable($values['location'] ?? ''),
                    'storage_path' => $storagePath,
                    'server' => '',
                    'status' => 'Active',
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
                $record = ArchiveRecording::query()->create($row);
                if (trim((string) ($record->duration ?? '')) === '') {
                    $duration = AudioDuration::formatFromRecording($record);
                    if ($duration !== null) {
                        $record->forceFill(['duration' => $duration])->save();
                    }
                }
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
        $aliases['call date'] = 'called_at';
        $aliases['datetime'] = 'called_at';

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

    private function parseDateTime(string $value): ?string
    {
        $value = trim($value);
        $formats = [
            'm/d/Y H:i:s',
            'm/d/Y H:i',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i',
            'Y-m-d',
            'm/d/Y',
        ];
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat('!'.$format, $value);
            if ($date instanceof \DateTime) {
                $errors = \DateTime::getLastErrors();
                if (! is_array($errors) || (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0)) {
                    return $date->format('Y-m-d H:i:s');
                }
            }
        }

        if (preg_match('/^\d+(\.\d+)?$/', $value) === 1) {
            $serial = (float) $value;
            if ($serial > 20000 && $serial < 80000) {
                $unix = (int) round(($serial - 25569) * 86400);

                return gmdate('Y-m-d H:i:s', $unix);
            }
        }

        return null;
    }

    private function normalizeDuration(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d+$/', $value) === 1) {
            $seconds = (int) $value;

            return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
        }
        if (preg_match('/^(\d{1,2}):([0-5]\d)$/', $value, $match) === 1) {
            return sprintf('00:%02d:%02d', (int) $match[1], (int) $match[2]);
        }
        if (preg_match('/^(\d{1,2}):([0-5]\d):([0-5]\d)$/', $value, $match) === 1) {
            return sprintf('%02d:%02d:%02d', (int) $match[1], (int) $match[2], (int) $match[3]);
        }

        return null;
    }
}
