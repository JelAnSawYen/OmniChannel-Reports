<?php

namespace App\Services\Pdc;

use App\Models\ChannelAllocationCampaign;
use App\Models\PdcGroup;
use App\Models\PdcServer;
use App\Services\InventoryImportService;
use App\Services\XlsxService;
use App\Support\ImportCell;
use App\Support\OperationCatalog;
use App\Support\PdcEndorseDate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PdcServerImportService
{
    public const SESSION_KEY = 'pdc_servers_import';

    public const CARRY_FIELDS = ['campaign', 'location', 'date_endorse', 'dns'];

    public const PASSWORD_FIELDS = ['password', 'sql_db_password'];

    /**
     * @return array<string, string>
     */
    public function fields(): array
    {
        return [
            'campaign' => 'Campaign',
            'location' => 'Site',
            'date_endorse' => 'Date Endorse',
            'dns' => 'DNS',
            'hostname' => 'Hostname',
            'ip_address' => 'Source IP',
            'os' => 'OS',
            'ram' => 'RAM',
            'cpu' => 'CPU',
            'storage' => 'Storage',
            'admin_username' => 'Admin Username',
            'password' => 'Password',
            'sql_db_password' => 'SQL DB Password',
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

        $locations = $this->locationLookup();
        $existingIps = PdcServer::query()->whereNotNull('ip_address')->pluck('ip_address')
            ->map(fn ($ip) => strtolower(trim((string) $ip)))
            ->all();
        $existingHostnames = PdcServer::query()->whereNotNull('hostname')->pluck('hostname')
            ->map(fn ($name) => mb_strtolower(trim((string) $name)))
            ->all();
        $fileIps = [];
        $fileHostnames = [];
        $carry = [
            'campaign' => null,
            'location' => null,
            'date_endorse' => null,
            'dns' => null,
        ];
        $previewRows = [];
        $payload = [];

        foreach ($rawRows as $offset => $raw) {
            $excelRow = $offset + 2;
            $rawValues = [];
            foreach (array_keys($this->fields()) as $field) {
                $rawValues[$field] = $this->cell($raw, $map, $field, ! in_array($field, self::PASSWORD_FIELDS, true));
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
            $campaignId = null;
            $locationName = null;
            $dateIso = null;

            // PDC Servers may use a campaign that is not in Master Campaign.
            // Existing master campaigns are linked; unknown names stay on the PDC group only.
            if ($values['campaign'] === '') {
                $errors[] = 'Campaign is required';
            } elseif (mb_strlen($values['campaign']) > 255) {
                $errors[] = 'Campaign must be 255 characters or fewer';
            } else {
                $campaign = ChannelAllocationCampaign::masterByName($values['campaign']);
                if ($campaign) {
                    $campaignId = (int) $campaign->id;
                    $values['campaign'] = $campaign->name;
                }
            }

            if ($values['location'] !== '') {
                $matched = $locations[mb_strtolower($values['location'])] ?? null;
                if ($matched === null) {
                    $errors[] = 'Site does not exist';
                } else {
                    $locationName = $matched;
                    $values['location'] = $matched;
                }
            }

            if ($values['date_endorse'] !== '') {
                $parsed = PdcEndorseDate::parse($values['date_endorse']);
                if (! $parsed['valid'] || $parsed['empty']) {
                    $errors[] = 'Date Endorse must be a valid date on or after 1/1/2000';
                } else {
                    $dateIso = $parsed['iso'];
                    $values['date_endorse'] = $parsed['display'];
                }
            }

            $hasServer = $this->rowHasServerFields($values);
            if ($hasServer) {
                if (trim((string) $values['hostname']) === '') {
                    $errors[] = 'Hostname is required';
                }
                $ip = trim((string) $values['ip_address']);
                if ($ip === '') {
                    $errors[] = 'Source IP is required';
                } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                    $errors[] = 'Source IP must be a valid IPv4 address';
                } else {
                    $normalized = strtolower($ip);
                    $values['ip_address'] = $ip;
                    if (in_array($normalized, $existingIps, true) || isset($fileIps[$normalized])) {
                        $errors[] = 'Source IP already exists. Each PDC server must have a unique Source IP.';
                    } else {
                        $fileIps[$normalized] = $excelRow;
                    }
                }

                $hostname = trim((string) $values['hostname']);
                if ($hostname !== '') {
                    $key = mb_strtolower($hostname);
                    if (isset($fileHostnames[$key]) || in_array($key, $existingHostnames, true)) {
                        $errors[] = 'Hostname already exists';
                    } else {
                        $fileHostnames[$key] = $excelRow;
                    }
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
                    'campaign_name' => $values['campaign'],
                    'location' => $locationName,
                    'location_cleared' => $rawValues['location'] === '-',
                    'date_endorse' => $dateIso,
                    'date_cleared' => $rawValues['date_endorse'] === '-',
                    'dns' => $values['dns'] === '' ? null : $values['dns'],
                    'dns_cleared' => $rawValues['dns'] === '-',
                    'has_server' => $hasServer,
                    'hostname' => $hasServer ? trim((string) $values['hostname']) : null,
                    'ip_address' => $hasServer ? trim((string) $values['ip_address']) : null,
                    'os' => $this->nullable($values['os']),
                    'ram' => $this->nullable($values['ram']),
                    'cpu' => $this->nullable($values['cpu']),
                    'storage' => $this->nullable($values['storage']),
                    'admin_username' => $this->nullable($values['admin_username']),
                    'password' => $hasServer ? $this->nullableExact($values['password']) : null,
                    'sql_db_password' => $hasServer ? $this->nullableExact($values['sql_db_password']) : null,
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
                $group = $this->groupForRow($row);
                $isNew = ! $group->exists;

                if ($row['location'] !== null) {
                    $group->location = $row['location'];
                } elseif ($isNew || $row['location_cleared']) {
                    $group->location = null;
                }

                if ($row['date_endorse'] !== null) {
                    $group->date_endorse = $row['date_endorse'];
                } elseif ($isNew || $row['date_cleared']) {
                    $group->date_endorse = null;
                }

                if ($row['dns'] !== null) {
                    $group->dns = $row['dns'];
                } elseif ($isNew || $row['dns_cleared']) {
                    $group->dns = null;
                }

                $group->save();
                $count++;

                if (! $row['has_server']) {
                    continue;
                }

                PdcServer::query()->create([
                    'pdc_group_id' => $group->id,
                    'hostname' => $row['hostname'],
                    'ip_address' => $row['ip_address'],
                    'location' => $group->location,
                    'status' => 'Active',
                    'os' => $row['os'],
                    'ram' => $row['ram'],
                    'cpu' => $row['cpu'],
                    'storage' => $row['storage'],
                    'admin_username' => $row['admin_username'],
                    'password' => $row['password'],
                    'sql_db_password' => $row['sql_db_password'],
                ]);
            }
        });

        return $count;
    }

    /**
     * Link to Master Campaign when the name already exists there. Otherwise keep
     * the name on the PDC group only — never insert a Master Campaign record.
     *
     * @param  array<string, mixed>  $row
     */
    private function groupForRow(array $row): PdcGroup
    {
        $campaignId = $row['campaign_id'] ? (int) $row['campaign_id'] : null;
        $name = trim((string) ($row['campaign_name'] ?? ''));
        if ($campaignId) {
            $group = PdcGroup::query()->firstOrNew(['campaign_id' => $campaignId]);
            $group->campaign_name = null;

            return $group;
        }

        if ($name === '') {
            throw new RuntimeException('Campaign is required.');
        }

        $group = PdcGroup::query()
            ->whereNull('campaign_id')
            ->whereRaw('LOWER(campaign_name) = ?', [mb_strtolower($name)])
            ->first();

        if ($group) {
            return $group;
        }

        return new PdcGroup([
            'campaign_id' => null,
            'campaign_name' => $name,
        ]);
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
     * @return array<string, string>
     */
    private function locationLookup(): array
    {
        $lookup = [];
        foreach (OperationCatalog::pdcSiteNames() as $name) {
            $lookup[mb_strtolower($name)] = $name;
        }
        $lookup['scs'] = 'SC5';
        $lookup['cg3'] = 'CG3';
        $lookup['pdc'] = 'PDC';
        $lookup['wfh'] = 'WFH';

        return $lookup;
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
        $aliases['ip address'] = 'ip_address';
        $aliases['source ip'] = 'ip_address';
        $aliases['sql db password'] = 'sql_db_password';
        $aliases['sqldb password'] = 'sql_db_password';

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
    private function cell(array $row, array $map, string $field, bool $trim): string
    {
        if (! isset($map[$field])) {
            return '';
        }

        $value = (string) ($row[$map[$field]] ?? '');

        return $trim ? trim($value) : $value;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function rowIsBlank(array $values): bool
    {
        return ImportCell::isBlankRow($values);
    }

    /**
     * @param  array<string, string>  $values
     */
    private function rowHasServerFields(array $values): bool
    {
        foreach (['hostname', 'ip_address', 'os', 'ram', 'cpu', 'storage', 'admin_username', 'password', 'sql_db_password'] as $field) {
            if (trim((string) ($values[$field] ?? '')) !== '') {
                return true;
            }
        }

        return false;
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

    private function nullableExact(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value;
    }
}
