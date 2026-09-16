<?php

namespace App\Support;

use App\Models\GlobeSim;
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

        return $model::query()
            ->orderBy('imei')
            ->get()
            ->map(fn (Model $sim) => self::serialize($type, $sim))
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, imei: string, mobile_number: string, plan: string, network: string, sim_type: string}
     */
    public static function serialize(string $type, Model $sim): array
    {
        return [
            'id' => (int) $sim->getKey(),
            'imei' => (string) ($sim->imei ?? ''),
            'mobile_number' => (string) ($sim->mobile_number ?? ''),
            'plan' => (string) ($sim->plan ?? ''),
            'network' => (string) ($sim->network ?: self::networkForType($type)),
            'sim_type' => $type,
        ];
    }
}
