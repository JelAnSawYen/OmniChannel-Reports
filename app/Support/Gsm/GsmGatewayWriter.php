<?php

namespace App\Support\Gsm;

use App\Models\GatewaySimAssignment;
use App\Models\MediaGateway;
use App\Support\GsmSimInventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GsmGatewayWriter
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $assignments
     */
    public static function create(array $data, array $assignments = []): MediaGateway
    {
        return DB::transaction(function () use ($data, $assignments) {
            $gateway = MediaGateway::create(self::gatewayAttributes($data, $assignments));
            if ($assignments !== []) {
                self::replaceAssignments($gateway, $assignments);
            }

            return $gateway->fresh(['assignments']) ?? $gateway;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $assignments
     */
    public static function update(MediaGateway $gateway, array $data, array $assignments = []): MediaGateway
    {
        return DB::transaction(function () use ($gateway, $data, $assignments) {
            $channelCount = (int) ($data['channel_count'] ?? $gateway->channel_count);
            $existingPorts = $gateway->assignments()->pluck('port')->map(fn ($port) => (int) $port)->all();
            if (count($existingPorts) > $channelCount || collect($existingPorts)->contains(fn ($port) => $port > $channelCount)) {
                throw ValidationException::withMessages([
                    'channel_count' => 'Channel Count cannot be lower than existing SIM assignment ports.',
                ]);
            }

            $gateway->update(self::gatewayAttributes($data, [], $gateway));
            if ($assignments !== []) {
                self::replaceAssignments($gateway, $assignments);
            }

            return $gateway->fresh(['assignments']) ?? $gateway;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $assignments
     * @return array<string, mixed>
     */
    public static function gatewayAttributes(array $data, array $assignments, ?MediaGateway $existing = null): array
    {
        $first = $assignments[0] ?? null;
        $attributes = [
            'hostname' => trim((string) ($data['hostname'] ?? '')),
            'site_name' => $data['site_name'],
            'site_code' => $data['site_code'],
            'ip_address' => $data['ip_address'],
            'channel_count' => (int) $data['channel_count'],
            'device_function' => trim((string) ($data['device_function'] ?? '')),
            'network' => trim((string) ($data['network'] ?? '')),
            'username' => $data['username'],
            'password' => $data['password'] ?? $existing?->password,
        ];

        if ($first) {
            $attributes['port'] = (string) $first['port'];
            $sim = GsmSimInventory::findSim((string) $first['sim_type'], (int) $first['sim_id']);
            if ($sim) {
                $attributes['plan'] = (string) ($sim->plan ?? '');
            }
        }

        return array_filter($attributes, static fn ($value) => $value !== null);
    }

    public static function saveAssignment(MediaGateway $gateway, string $simType, int $simId, ?GatewaySimAssignment $existing = null): GatewaySimAssignment
    {
        $port = $existing ? (int) $existing->port : self::nextPort($gateway);
        $items = $gateway->assignments
            ->reject(fn (GatewaySimAssignment $row) => $existing && (int) $row->id === (int) $existing->id)
            ->map(fn (GatewaySimAssignment $row) => [
                'sim_type' => (string) $row->sim_type,
                'sim_id' => (int) $row->sim_id,
                'port' => (int) $row->port,
            ])
            ->values()
            ->all();
        $items[] = [
            'sim_type' => $simType,
            'sim_id' => $simId,
            'port' => $port,
        ];

        self::assertValid($items, (int) $gateway->channel_count, $gateway->id);

        return DB::transaction(function () use ($gateway, $simType, $simId, $existing, $port) {
            if ($existing) {
                $existing->update([
                    'sim_type' => $simType,
                    'sim_id' => $simId,
                ]);
                $assignment = $existing->fresh() ?? $existing;
            } else {
                $assignment = $gateway->assignments()->create([
                    'sim_type' => $simType,
                    'sim_id' => $simId,
                    'port' => $port,
                ]);
            }

            $gateway->update([
                'port' => (string) $port,
            ]);

            self::syncLinkedSim($simType, $simId, $gateway, $port);

            return $assignment;
        });
    }

    public static function unlinkSim(MediaGateway $gateway, string $simType, int $simId): bool
    {
        $simType = strtolower(trim($simType));
        if (! in_array($simType, ['globe', 'smart'], true) || $simId < 1) {
            return false;
        }

        $deleted = GatewaySimAssignment::query()
            ->where('media_gateway_id', $gateway->id)
            ->where('sim_type', $simType)
            ->where('sim_id', $simId)
            ->delete() > 0;

        $sim = GsmSimInventory::findSim($simType, $simId);
        if ($sim) {
            $gatewayIp = strtolower(trim((string) $gateway->ip_address));
            $simIp = strtolower(trim((string) $sim->ip_address));
            if ($gatewayIp !== '' && $simIp === $gatewayIp) {
                $sim->forceFill(['ip_address' => null])->save();
                $deleted = true;
            }
        }

        return $deleted;
    }

    public static function nextPort(MediaGateway $gateway): int
    {
        $used = $gateway->assignments()->pluck('port')->map(fn ($port) => (int) $port)->all();
        $max = max(1, (int) $gateway->channel_count);
        for ($port = 1; $port <= $max; $port++) {
            if (! in_array($port, $used, true)) {
                return $port;
            }
        }

        throw ValidationException::withMessages([
            'assignments' => 'SIM assignments cannot exceed the Channel Count.',
        ]);
    }

    public static function syncSimPort(MediaGateway $gateway, string $simType, int $simId, int $port): GatewaySimAssignment
    {
        if ($port < 1) {
            throw ValidationException::withMessages([
                'port' => 'Port must be a number greater than 0.',
            ]);
        }

        $max = (int) $gateway->channel_count;
        if ($max > 0 && $port > $max) {
            throw ValidationException::withMessages([
                'port' => 'Port must not exceed Channel Count.',
            ]);
        }

        $existing = GatewaySimAssignment::query()
            ->where('sim_type', $simType)
            ->where('sim_id', $simId)
            ->first();

        $taken = GatewaySimAssignment::query()
            ->where('media_gateway_id', $gateway->id)
            ->where('port', $port)
            ->when($existing, fn ($query) => $query->where('id', '!=', $existing->id))
            ->exists();
        if ($taken) {
            throw ValidationException::withMessages([
                'port' => 'Port is already assigned on this gateway.',
            ]);
        }

        return DB::transaction(function () use ($gateway, $simType, $simId, $port, $existing) {
            if ($existing) {
                $existing->update([
                    'media_gateway_id' => $gateway->id,
                    'port' => $port,
                ]);
                $assignment = $existing->fresh() ?? $existing;
            } else {
                $assignment = $gateway->assignments()->create([
                    'sim_type' => $simType,
                    'sim_id' => $simId,
                    'port' => $port,
                ]);
            }

            self::syncLinkedSim($simType, $simId, $gateway, $port);

            return $assignment;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $assignments
     */
    public static function replaceAssignments(MediaGateway $gateway, array $assignments): void
    {
        $gateway->assignments()->delete();

        foreach ($assignments as $assignment) {
            $gateway->assignments()->create([
                'sim_type' => $assignment['sim_type'],
                'sim_id' => (int) $assignment['sim_id'],
                'port' => (int) $assignment['port'],
            ]);

            self::syncLinkedSim(
                (string) $assignment['sim_type'],
                (int) $assignment['sim_id'],
                $gateway,
                (int) $assignment['port']
            );
        }
    }

    private static function syncLinkedSim(string $simType, int $simId, MediaGateway $gateway, int $port): void
    {
        $sim = GsmSimInventory::findSim($simType, $simId);
        if (! $sim) {
            return;
        }

        $updates = [];
        if ($port > 0) {
            $updates['port'] = $port;
        }
        $ip = trim((string) $gateway->ip_address);
        if ($ip !== '') {
            $updates['ip_address'] = $ip;
        }
        if ($updates === []) {
            return;
        }

        $sim->forceFill($updates)->save();
    }

    /**
     * @return list<array{sim_type: string, sim_id: int, port: int}>
     */
    public static function normalizeAssignments(mixed $raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $type = strtolower(trim((string) ($row['sim_type'] ?? '')));
            $id = (int) ($row['sim_id'] ?? 0);
            $port = (int) ($row['port'] ?? 0);
            if (! in_array($type, ['globe', 'smart'], true) || $id < 1 || $port < 1) {
                continue;
            }
            $items[] = [
                'sim_type' => $type,
                'sim_id' => $id,
                'port' => $port,
            ];
        }

        usort($items, static fn ($a, $b) => $a['port'] <=> $b['port']);

        return $items;
    }

    /**
     * @param  list<array{sim_type: string, sim_id: int, port: int}>  $assignments
     * @return array<string, string>
     */
    public static function assignmentErrors(array $assignments, int $channelCount, ?int $ignoreGatewayId = null): array
    {
        $errors = [];
        if ($channelCount < 1) {
            $errors['channel_count'] = 'Channel Count must be a number greater than 0.';
        }
        if (count($assignments) > $channelCount) {
            $errors['assignments'] = 'SIM assignments cannot exceed the Channel Count.';
        }

        $ports = [];
        $sims = [];
        foreach ($assignments as $index => $assignment) {
            $key = 'assignments.'.$index.'.port';
            if ($assignment['port'] > $channelCount) {
                $errors[$key] = 'Port must not exceed Channel Count.';
            }
            if (isset($ports[$assignment['port']])) {
                $errors[$key] = 'Port is already assigned on this gateway.';
            }
            $ports[$assignment['port']] = true;

            $simKey = $assignment['sim_type'].':'.$assignment['sim_id'];
            if (isset($sims[$simKey])) {
                $errors['assignments.'.$index.'.sim_id'] = 'This SIM is already assigned to this gateway.';
            }
            $sims[$simKey] = true;

            $sim = GsmSimInventory::findSim($assignment['sim_type'], $assignment['sim_id']);
            if (! $sim) {
                $errors['assignments.'.$index.'.sim_id'] = 'The selected SIM does not exist in Globe SIM or Smart SIM inventory.';

                continue;
            }

            $taken = GatewaySimAssignment::query()
                ->where('sim_type', $assignment['sim_type'])
                ->where('sim_id', $assignment['sim_id'])
                ->when($ignoreGatewayId, fn ($query) => $query->where('media_gateway_id', '!=', $ignoreGatewayId))
                ->exists();
            if ($taken) {
                $errors['assignments.'.$index.'.sim_id'] = 'This SIM is already assigned to another GSM Gateway.';
            }
        }

        return $errors;
    }

    /**
     * @param  list<array{sim_type: string, sim_id: int, port: int}>  $assignments
     */
    public static function assertValid(array $assignments, int $channelCount, ?int $ignoreGatewayId = null): void
    {
        $errors = self::assignmentErrors($assignments, $channelCount, $ignoreGatewayId);
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
