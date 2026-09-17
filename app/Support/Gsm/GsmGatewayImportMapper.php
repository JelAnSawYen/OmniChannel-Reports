<?php

namespace App\Support\Gsm;

use App\Models\GatewaySimAssignment;
use App\Models\MediaGateway;
use App\Support\GsmSimInventory;
use App\Support\InventoryImportCatalog;
use Illuminate\Support\Facades\DB;

class GsmGatewayImportMapper
{
    /**
     * @param  array<string, string>  $values
     * @return array{errors: list<string>, record: ?array<string, mixed>}
     */
    public static function map(array $values, object $context): array
    {
        if (! isset($context->gateways) || ! is_array($context->gateways)) {
            $context->gateways = [];
        }
        if (! isset($context->fileIps) || ! is_array($context->fileIps)) {
            $context->fileIps = [];
        }
        if (! isset($context->existingIps) || ! is_array($context->existingIps)) {
            $context->existingIps = array_fill_keys(InventoryImportCatalog::existingIpv4Addresses(), true);
        }
        if (! isset($context->usedSims) || ! is_array($context->usedSims)) {
            $context->usedSims = self::existingSimKeys();
        }

        $errors = [];
        $hostname = trim((string) ($values['hostname'] ?? ''));
        $ip = strtolower(trim((string) ($values['ip_address'] ?? '')));
        $serial = trim((string) ($values['site_code'] ?? ''));
        $channelCount = (int) ($values['channel_count'] ?? 0);
        $function = trim((string) ($values['device_function'] ?? ''));
        $site = trim((string) ($values['site_name'] ?? ''));
        $username = trim((string) ($values['username'] ?? ''));
        $password = (string) ($values['password'] ?? '');
        $portRaw = trim((string) ($values['assignment_port'] ?? ''));
        $imei = trim((string) ($values['imei'] ?? ''));

        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            $errors[] = 'IP must be a valid IPv4 address';
        }

        $serialKey = mb_strtolower($serial);
        $existing = $context->gateways[$serialKey] ?? null;
        if ($existing) {
            foreach ([
                'hostname' => $hostname,
                'ip_address' => $ip,
                'channel_count' => (string) $channelCount,
                'device_function' => $function,
                'site_name' => $site,
                'username' => $username,
            ] as $field => $value) {
                if ($value !== '' && strcasecmp((string) $existing[$field], (string) $value) !== 0) {
                    $errors[] = ($field === 'ip_address' ? 'IP' : ucfirst(str_replace('_', ' ', $field))).' does not match the earlier row for this Serial Number';
                }
            }
        } else {
            if (isset($context->fileIps[$ip]) || isset($context->existingIps[$ip])) {
                $errors[] = 'IP already exists';
            } elseif ($ip !== '') {
                $context->fileIps[$ip] = true;
            }
            if (MediaGateway::query()->whereRaw('LOWER(site_code) = ?', [$serialKey])->exists()) {
                $errors[] = 'Serial Number already exists';
            }
        }

        $assignment = null;
        if ($portRaw !== '' || $imei !== '') {
            if ($portRaw === '' || $imei === '') {
                $errors[] = 'Port and IMEI are both required for a SIM assignment';
            } else {
                if (! preg_match('/^-?\d+$/', $portRaw) && ! preg_match('/^-?\d+\.0+$/', $portRaw)) {
                    $errors[] = 'Port must be a whole number';
                }
                $port = (int) $portRaw;
                if ($port < 1) {
                    $errors[] = 'Port must be a whole number';
                }
                if ($channelCount > 0 && $port > $channelCount) {
                    $errors[] = 'Port must not exceed Channel Count';
                }

                $found = GsmSimInventory::findByImei($imei);
                if (! $found) {
                    $errors[] = 'IMEI must match an existing Globe SIM or Smart SIM record';
                } else {
                    $simKey = $found['type'].':'.$found['sim']->getKey();
                    $ports = $existing['ports'] ?? [];
                    if (isset($ports[$port])) {
                        $errors[] = 'Port is already assigned on this gateway';
                    }
                    if (isset($existing['sims'][$simKey]) || (isset($context->usedSims[$simKey]) && ! isset($existing['sims'][$simKey]))) {
                        $errors[] = 'This SIM is already assigned to a GSM Gateway';
                    }
                    $assignment = [
                        'sim_type' => $found['type'],
                        'sim_id' => (int) $found['sim']->getKey(),
                        'port' => $port,
                    ];
                }
            }
        }

        if ($errors !== []) {
            return ['errors' => $errors, 'record' => null];
        }

        if (! $existing) {
            $existing = [
                'hostname' => $hostname,
                'ip_address' => $ip,
                'site_code' => $serial,
                'channel_count' => (string) $channelCount,
                'device_function' => $function,
                'site_name' => $site,
                'username' => $username,
                'password' => $password,
                'ports' => [],
                'sims' => [],
            ];
        }
        if ($assignment) {
            $simKey = $assignment['sim_type'].':'.$assignment['sim_id'];
            $existing['ports'][$assignment['port']] = true;
            $existing['sims'][$simKey] = true;
            $context->usedSims[$simKey] = true;
            if (count($existing['ports']) > $channelCount) {
                return [
                    'errors' => ['SIM assignments cannot exceed the Channel Count'],
                    'record' => null,
                ];
            }
        }
        $context->gateways[$serialKey] = $existing;

        return [
            'errors' => [],
            'record' => [
                'hostname' => $hostname,
                'ip_address' => $ip,
                'site_code' => $serial,
                'channel_count' => $channelCount,
                'device_function' => $function,
                'site_name' => $site,
                'username' => $username,
                'password' => $password,
                'assignment' => $assignment,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $payload
     */
    public static function commit(array $payload): int
    {
        $groups = [];
        foreach ($payload as $row) {
            $key = mb_strtolower((string) ($row['site_code'] ?? ''));
            if ($key === '') {
                continue;
            }
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'gateway' => $row,
                    'assignments' => [],
                ];
            }
            if (is_array($row['assignment'] ?? null)) {
                $groups[$key]['assignments'][] = $row['assignment'];
            }
        }

        $count = 0;
        DB::transaction(function () use ($groups, &$count) {
            foreach ($groups as $group) {
                $row = $group['gateway'];
                $assignments = $group['assignments'];
                $data = [
                    'hostname' => $row['hostname'],
                    'ip_address' => $row['ip_address'],
                    'site_code' => $row['site_code'],
                    'channel_count' => (int) $row['channel_count'],
                    'device_function' => $row['device_function'],
                    'site_name' => $row['site_name'],
                    'username' => $row['username'],
                    'password' => $row['password'] ?: null,
                    'network' => self::networkFromAssignments($assignments),
                ];
                GsmGatewayWriter::create($data, $assignments);
                $count++;
            }
        });

        return $count;
    }

    /**
     * @return array<string, true>
     */
    private static function existingSimKeys(): array
    {
        $keys = [];
        foreach (GatewaySimAssignment::query()->get(['sim_type', 'sim_id']) as $assignment) {
            $keys[$assignment->sim_type.':'.$assignment->sim_id] = true;
        }

        return $keys;
    }

    /**
     * @param  list<array<string, mixed>>  $assignments
     */
    private static function networkFromAssignments(array $assignments): ?string
    {
        $first = $assignments[0] ?? null;
        if (! is_array($first)) {
            return null;
        }

        return GsmSimInventory::networkForType((string) ($first['sim_type'] ?? 'globe'));
    }
}
