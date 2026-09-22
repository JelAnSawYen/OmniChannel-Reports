<?php

namespace App\Support;

use App\Models\GatewaySimAssignment;
use App\Models\GlobeSim;
use App\Models\MediaGateway;
use App\Models\SmartSim;
use Illuminate\Database\Eloquent\Model;

class GsmSimInventory
{
    public const GLOBE = 'Globe SIM';

    public const SMART = 'Smart SIM';

    /**
     * @return list<string>
     */
    public static function networks(): array
    {
        return [self::GLOBE, self::SMART];
    }

    public static function typeForNetwork(string $network): ?string
    {
        return match (self::canonicalNetwork($network)) {
            self::GLOBE => 'globe',
            self::SMART => 'smart',
            default => null,
        };
    }

    public static function networkForType(string $type): string
    {
        return $type === 'smart' ? self::SMART : self::GLOBE;
    }

    public static function canonicalNetwork(?string $network): ?string
    {
        $value = trim((string) $network);
        if ($value === '') {
            return null;
        }

        $lower = mb_strtolower($value);
        if (in_array($lower, ['globe', 'globe sim'], true)) {
            return self::GLOBE;
        }
        if (in_array($lower, ['smart', 'smart sim'], true)) {
            return self::SMART;
        }

        return null;
    }

    /**
     * @return class-string<Model>|null
     */
    public static function modelForType(string $type): ?string
    {
        return match ($type) {
            'globe' => GlobeSim::class,
            'smart' => SmartSim::class,
            default => null,
        };
    }

    public static function findSim(string $type, int $id): GlobeSim|SmartSim|null
    {
        $model = self::modelForType($type);
        if (! $model) {
            return null;
        }

        return $model::query()->find($id);
    }

    public static function findByImei(string $imei): ?array
    {
        $imei = trim($imei);
        if ($imei === '') {
            return null;
        }

        $globe = GlobeSim::query()->where('imei', $imei)->first();
        if ($globe) {
            return ['type' => 'globe', 'sim' => $globe];
        }

        $smart = SmartSim::query()->where('imei', $imei)->first();
        if ($smart) {
            return ['type' => 'smart', 'sim' => $smart];
        }

        return null;
    }

    /**
     * @return list<array{id: int, imei: string, mobile_number: string, plan: string, network: string, sim_type: string}>
     */
    public static function listForNetwork(string $network): array
    {
        $type = self::typeForNetwork($network);
        $model = $type ? self::modelForType($type) : null;
        if (! $model) {
            return [];
        }

        $query = $model::query();
        NaturalSort::apply($query, 'imei');

        return $query
            ->get()
            ->map(fn (Model $sim) => self::serialize($type, $sim))
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, assignment_id: int, imei: string, mobile_number: string, plan: string, remarks: string, network: string, sim_type: string, port: int, ip_address: string}
     */
    public static function emptyPortRow(int $port): array
    {
        return [
            'id' => 0,
            'assignment_id' => 0,
            'imei' => '',
            'mobile_number' => '',
            'plan' => '',
            'remarks' => '',
            'network' => '',
            'sim_type' => '',
            'port' => $port,
            'ip_address' => '',
        ];
    }

    /**
     * @return array{id: int, imei: string, mobile_number: string, plan: string, remarks: string, network: string, sim_type: string, port: int|string}
     */
    public static function serialize(string $type, Model $sim): array
    {
        $port = (int) ($sim->port ?? 0);

        return [
            'id' => (int) $sim->getKey(),
            'imei' => (string) ($sim->imei ?? ''),
            'mobile_number' => (string) ($sim->mobile_number ?? ''),
            'plan' => (string) ($sim->plan ?? ''),
            'remarks' => trim((string) ($sim->remarks ?? '')),
            'network' => (string) ($sim->network ?: self::networkForType($type)),
            'sim_type' => $type,
            'port' => $port > 0 ? $port : '',
        ];
    }

    public static function findGatewayByHostname(?string $hostname): ?MediaGateway
    {
        $hostname = trim((string) $hostname);
        if ($hostname === '') {
            return null;
        }

        return MediaGateway::query()
            ->whereRaw('LOWER(TRIM(hostname)) = ?', [mb_strtolower($hostname)])
            ->first();
    }

    public static function findGatewayByIp(?string $ip): ?MediaGateway
    {
        $ip = strtolower(trim((string) $ip));
        if ($ip === '') {
            return null;
        }

        return MediaGateway::query()
            ->whereRaw('LOWER(TRIM(ip_address)) = ?', [$ip])
            ->first();
    }

    public static function hostnameForSim(GlobeSim|SmartSim $sim): string
    {
        $host = trim((string) ($sim->gatewayAssignment?->gateway?->hostname ?? ''));
        if ($host !== '') {
            return $host;
        }

        return trim((string) (self::findGatewayByIp((string) $sim->ip_address)?->hostname ?? ''));
    }

    public static function pruneOrphanAssignments(MediaGateway $gateway, int $port): void
    {
        if ($port < 1) {
            return;
        }

        GatewaySimAssignment::query()
            ->where('media_gateway_id', $gateway->id)
            ->where('port', $port)
            ->get()
            ->each(function (GatewaySimAssignment $row): void {
                if (! self::findSim((string) $row->sim_type, (int) $row->sim_id)) {
                    $row->delete();
                }
            });
    }

    public static function portIsTaken(MediaGateway $gateway, int $port, ?string $ignoreType = null, ?int $ignoreId = null): bool
    {
        if ($port < 1) {
            return false;
        }

        self::pruneOrphanAssignments($gateway, $port);

        $assignmentTaken = GatewaySimAssignment::query()
            ->where('media_gateway_id', $gateway->id)
            ->where('port', $port)
            ->when($ignoreType && $ignoreId, function ($query) use ($ignoreType, $ignoreId) {
                $query->where(function ($rows) use ($ignoreType, $ignoreId) {
                    $rows->where('sim_type', '!=', $ignoreType)
                        ->orWhere('sim_id', '!=', $ignoreId);
                });
            })
            ->exists();
        if ($assignmentTaken) {
            return true;
        }

        $ip = strtolower(trim((string) $gateway->ip_address));
        foreach (['globe' => GlobeSim::class, 'smart' => SmartSim::class] as $type => $model) {
            $query = $model::query()->where('port', $port);
            if ($ip !== '') {
                $query->whereRaw('LOWER(TRIM(ip_address)) = ?', [$ip]);
            } else {
                $query->whereHas('gatewayAssignment', fn ($assignments) => $assignments->where('media_gateway_id', $gateway->id));
            }
            if ($ignoreType === $type && $ignoreId) {
                $query->whereKeyNot($ignoreId);
            }
            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }

    public const COMPANION_REQUIRED = 'Enter Hostname, Port, and at least one other field.';

    /**
     * @return list<string>
     */
    public static function companionFields(): array
    {
        return ['imei', 'mobile_number', 'plan', 'account_number', 'contract_start', 'contract_end'];
    }

    public static function blankSimValue(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '' || $text === '-') {
            return null;
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function hasCompanionValue(array $data): bool
    {
        foreach (self::companionFields() as $field) {
            if (self::blankSimValue($data[$field] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }
}
