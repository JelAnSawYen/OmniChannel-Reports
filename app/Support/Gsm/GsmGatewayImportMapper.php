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
        if (! isset($context->gatewaysByIp) || ! is_array($context->gatewaysByIp)) {
            $context->gatewaysByIp = [];
        }
        if (! isset($context->gateways) || ! is_array($context->gateways)) {
            $context->gateways = [];
        }
        if (! isset($context->existingIps) || ! is_array($context->existingIps)) {
            $context->existingIps = array_fill_keys(InventoryImportCatalog::existingGsmGatewayIps(), true);
        }
        if (! isset($context->usedSims) || ! is_array($context->usedSims)) {
            $context->usedSims = self::existingSimKeys();
        }

        $errors = [];
        $hostname = trim((string) ($values['hostname'] ?? ''));
        $ip = strtolower(trim((string) ($values['ip_address'] ?? '')));
        $serial = trim((string) ($values['site_code'] ?? ''));
        $serialKey = mb_strtolower($serial);
        $channelCount = (int) ($values['channel_count'] ?? 0);
        $function = trim((string) ($values['device_function'] ?? ''));
        $site = trim((string) ($values['site_name'] ?? ''));
        $username = trim((string) ($values['username'] ?? ''));
        $password = (string) ($values['password'] ?? '');
        $imei = trim((string) ($values['imei'] ?? ''));

        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            $errors[] = 'IP must be a valid IPv4 address';
        }

        $existing = ($ip !== '' && isset($context->gatewaysByIp[$ip]))
            ? $context->gatewaysByIp[$ip]
            : null;

        if ($serialKey !== '' && MediaGateway::query()->whereRaw('LOWER(site_code) = ?', [$serialKey])->exists()) {
            $errors[] = 'Serial Number already exists';
        }

        if ($existing) {
            foreach ([
                'hostname' => $hostname,
                'site_code' => $serial,
                'channel_count' => $channelCount > 0 ? (string) $channelCount : '',
                'device_function' => $function,
                'site_name' => $site,
                'username' => $username,
            ] as $field => $value) {
                if ($value === '') {
                    continue;
                }
                $prior = (string) ($existing[$field] ?? '');
                if ($prior !== '' && strcasecmp($prior, (string) $value) !== 0) {
                    $label = $field === 'site_code' ? 'Serial Number' : ucfirst(str_replace('_', ' ', $field));
                    $errors[] = $label.' does not match the earlier row for this IP';
                }
            }
        } elseif ($ip !== '' && ! in_array('IP must be a valid IPv4 address', $errors, true)) {
            if (isset($context->existingIps[$ip])) {
                $errors[] = 'IP already exists';
            } else {
                if ($serialKey !== '' && isset($context->gateways[$serialKey])) {
                    $priorIp = (string) ($context->gateways[$serialKey]['ip_address'] ?? '');
                    if ($priorIp !== '' && $priorIp !== $ip) {
                        $errors[] = 'Serial Number already exists';
                    }
                }

                // Register gateway identity by IP before row-level SIM checks so
                // continuation rows with the same IP are not treated as duplicates.
                $existing = [
                    'hostname' => $hostname,
                    'ip_address' => $ip,
                    'site_code' => $serial,
                    'channel_count' => $channelCount > 0 ? (string) $channelCount : '',
                    'device_function' => $function,
                    'site_name' => $site,
                    'username' => $username,
                    'password' => $password,
                    'ports' => [],
                    'sims' => [],
                ];
                $context->gatewaysByIp[$ip] = $existing;
                if ($serialKey !== '') {
                    $context->gateways[$serialKey] = $existing;
                }
            }
        }

        $assignment = null;
        $postedNetwork = trim((string) ($values['assignment_network'] ?? ''));
        $canonicalNetwork = $postedNetwork === '' ? null : GsmSimInventory::canonicalNetwork($postedNetwork);
        if ($postedNetwork !== '' && $canonicalNetwork === null) {
            $errors[] = 'Network must be Globe SIM or Smart SIM';
        }

        // Empty IMEI = empty port row. Port column is ignored for sequencing/requirements.
        if ($imei !== '') {
            $effectiveChannelCount = is_array($existing) ? (int) ($existing['channel_count'] ?? 0) : 0;
            if ($effectiveChannelCount < 1 && $channelCount > 0) {
                $effectiveChannelCount = $channelCount;
            }

            $found = GsmSimInventory::findByImei($imei);
            if (! $found) {
                $errors[] = 'IMEI must match an existing Globe SIM or Smart SIM record';
            } else {
                if ($canonicalNetwork !== null && $canonicalNetwork !== GsmSimInventory::networkForType($found['type'])) {
                    $errors[] = 'Network must match the SIM inventory';
                }
                $simKey = $found['type'].':'.$found['sim']->getKey();
                $ports = is_array($existing) ? ($existing['ports'] ?? []) : [];
                $nextPort = count($ports) + 1;
                $simAlreadyOnGateway = is_array($existing) && isset($existing['sims'][$simKey]);

                if ($effectiveChannelCount > 0 && $nextPort > $effectiveChannelCount) {
                    $errors[] = 'SIM assignments cannot exceed the Channel Count';
                }
                if ($simAlreadyOnGateway || (isset($context->usedSims[$simKey]) && ! $simAlreadyOnGateway)) {
                    $errors[] = 'This SIM is already assigned to a GSM Gateway';
                }

                if ($errors === []) {
                    $assignment = [
                        'sim_type' => $found['type'],
                        'sim_id' => (int) $found['sim']->getKey(),
                        'port' => $nextPort,
                        'remarks' => trim((string) ($values['assignment_remarks'] ?? '')) ?: null,
                    ];
                }
            }
        }

        if ($errors !== []) {
            return ['errors' => $errors, 'record' => null];
        }

        $gatewayNetwork = $canonicalNetwork ?? '';
        if (! $existing && $ip !== '') {
            $existing = [
                'hostname' => $hostname,
                'ip_address' => $ip,
                'site_code' => $serial,
                'channel_count' => $channelCount > 0 ? (string) $channelCount : '',
                'device_function' => $function,
                'site_name' => $site,
                'username' => $username,
                'password' => $password,
                'ports' => [],
                'sims' => [],
            ];
        }

        if ($existing) {
            if ($hostname !== '') {
                $existing['hostname'] = $hostname;
            }
            if ($serial !== '') {
                $existing['site_code'] = $serial;
            }
            if ($channelCount > 0) {
                $existing['channel_count'] = (string) $channelCount;
            }
            if ($function !== '') {
                $existing['device_function'] = $function;
            }
            if ($site !== '') {
                $existing['site_name'] = $site;
            }
            if ($username !== '') {
                $existing['username'] = $username;
            }
            if ($password !== '') {
                $existing['password'] = $password;
            }
        }

        if ($assignment && $existing) {
            $simKey = $assignment['sim_type'].':'.$assignment['sim_id'];
            $existing['ports'][$assignment['port']] = true;
            $existing['sims'][$simKey] = true;
            $context->usedSims[$simKey] = true;
        }

        if ($existing && $ip !== '') {
            $context->gatewaysByIp[$ip] = $existing;
            $resolvedSerialKey = mb_strtolower((string) ($existing['site_code'] ?? $serial));
            if ($resolvedSerialKey !== '') {
                $context->gateways[$resolvedSerialKey] = $existing;
            }
        }

        $resolvedChannelCount = (int) ($existing['channel_count'] ?? $channelCount);

        return [
            'errors' => [],
            'record' => [
                'hostname' => $hostname !== '' ? $hostname : (string) ($existing['hostname'] ?? ''),
                'ip_address' => $ip,
                'site_code' => $serial !== '' ? $serial : (string) ($existing['site_code'] ?? ''),
                'channel_count' => $resolvedChannelCount,
                'device_function' => $function !== '' ? $function : (string) ($existing['device_function'] ?? ''),
                'site_name' => $site !== '' ? $site : (string) ($existing['site_name'] ?? ''),
                'username' => $username !== '' ? $username : (string) ($existing['username'] ?? ''),
                'password' => $password !== '' ? $password : (string) ($existing['password'] ?? ''),
                'network' => $gatewayNetwork,
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
            $ipKey = strtolower(trim((string) ($row['ip_address'] ?? '')));
            $key = $ipKey !== '' ? $ipKey : mb_strtolower((string) ($row['site_code'] ?? ''));
            if ($key === '') {
                continue;
            }
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'gateway' => $row,
                    'assignments' => [],
                ];
            } else {
                $gateway = $groups[$key]['gateway'];
                foreach (['hostname', 'site_code', 'channel_count', 'device_function', 'site_name', 'username', 'password', 'network'] as $field) {
                    $current = $row[$field] ?? null;
                    if ($current === null || $current === '') {
                        continue;
                    }
                    if (($gateway[$field] ?? '') === '' || ($field === 'channel_count' && (int) ($gateway[$field] ?? 0) < 1)) {
                        $gateway[$field] = $current;
                    }
                }
                $groups[$key]['gateway'] = $gateway;
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
                    'network' => GsmSimInventory::canonicalNetwork($row['network'] ?? null) ?? '',
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
}
