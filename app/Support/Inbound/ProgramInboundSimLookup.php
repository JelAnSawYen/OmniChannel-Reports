<?php

namespace App\Support\Inbound;

use App\Models\MediaGateway;
use App\Models\SipChannelNumber;
use App\Support\GsmSimInventory;
use Illuminate\Database\Eloquent\Model;

class ProgramInboundSimLookup
{
    /**
     * @var array<string, array<string, array{mobile: string, hostname: string, port: string, media_gateway_id: int|null}>>|null
     */
    private static ?array $indexes = null;

    public static function flush(): void
    {
        self::$indexes = null;
    }

    /**
     * @return array<string, list<array{mobile: string, hostname: string, port: string, media_gateway_id: int|null}>>
     */
    public static function payload(): array
    {
        $payload = [];
        foreach (GsmSimInventory::networks() as $network) {
            $payload[$network] = array_values(self::indexForNetwork($network));
        }

        return $payload;
    }

    /**
     * @return array{mobile: string, hostname: string, port: string, media_gateway_id: int|null}
     */
    public static function resolve(?string $network, string $mobile): array
    {
        $mobile = trim($mobile);
        $empty = [
            'mobile' => $mobile,
            'hostname' => '',
            'port' => '',
            'media_gateway_id' => null,
        ];
        $canonical = GsmSimInventory::canonicalNetwork($network);
        if ($canonical === null || $mobile === '') {
            return $empty;
        }

        return self::indexForNetwork($canonical)[$mobile] ?? $empty;
    }

    /**
     * @param  list<string>  $mobiles
     * @return list<array{mobile: string, hostname: string, port: string, media_gateway_id: int|null}>
     */
    public static function resolveMany(?string $network, array $mobiles): array
    {
        return array_map(
            static fn (string $mobile) => self::resolve($network, $mobile),
            $mobiles
        );
    }

    /**
     * @return list<string>
     */
    public static function channelNumbers(): array
    {
        return SipChannelNumber::query()
            ->orderBy('channel_number')
            ->pluck('channel_number')
            ->map(static fn ($number) => trim((string) $number))
            ->filter()
            ->values()
            ->all();
    }

    public static function isChannelNumber(string $number): bool
    {
        $number = trim($number);
        if ($number === '') {
            return false;
        }

        return SipChannelNumber::query()->where('channel_number', $number)->exists();
    }

    public static function findGateway(?string $value): ?MediaGateway
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $query = MediaGateway::query();

        return $query
            ->whereRaw('LOWER(TRIM(hostname)) = ?', [mb_strtolower($value)])
            ->orWhereRaw('LOWER(TRIM(ip_address)) = ?', [mb_strtolower($value)])
            ->first();
    }

    /**
     * @return array<string, array{mobile: string, hostname: string, port: string, media_gateway_id: int|null}>
     */
    private static function indexForNetwork(string $network): array
    {
        if (self::$indexes === null) {
            self::$indexes = [];
        }
        if (isset(self::$indexes[$network])) {
            return self::$indexes[$network];
        }

        $type = GsmSimInventory::typeForNetwork($network);
        $model = $type ? GsmSimInventory::modelForType($type) : null;
        if (! $model) {
            self::$indexes[$network] = [];

            return [];
        }

        $indexed = [];
        $sims = $model::query()
            ->with(['gatewayAssignment.gateway:id,hostname,ip_address'])
            ->orderBy('mobile_number')
            ->get();

        $missingIps = [];
        foreach ($sims as $sim) {
            if ($sim->gatewayAssignment?->gateway) {
                continue;
            }
            $ip = strtolower(trim((string) ($sim->ip_address ?? '')));
            if ($ip !== '') {
                $missingIps[$ip] = true;
            }
        }

        $gatewaysByIp = collect();
        if ($missingIps !== []) {
            $gatewaysByIp = MediaGateway::query()
                ->whereNotNull('hostname')
                ->where('hostname', '!=', '')
                ->whereNotNull('ip_address')
                ->get(['id', 'hostname', 'ip_address'])
                ->filter(fn (MediaGateway $gateway) => isset($missingIps[strtolower(trim((string) $gateway->ip_address))]))
                ->keyBy(fn (MediaGateway $gateway) => strtolower(trim((string) $gateway->ip_address)));
        }

        foreach ($sims as $sim) {
            $mobile = trim((string) ($sim->mobile_number ?? ''));
            if ($mobile === '' || isset($indexed[$mobile])) {
                continue;
            }

            $assignment = $sim->gatewayAssignment;
            $gateway = $assignment?->gateway;
            if (! $gateway) {
                $ip = strtolower(trim((string) ($sim->ip_address ?? '')));
                $gateway = $ip !== '' ? $gatewaysByIp->get($ip) : null;
            }
            $port = $assignment?->port;

            $indexed[$mobile] = [
                'mobile' => $mobile,
                'hostname' => trim((string) ($gateway?->hostname ?? '')),
                'port' => $port ? (string) $port : '',
                'media_gateway_id' => $gateway?->id,
            ];
        }

        self::$indexes[$network] = $indexed;

        return $indexed;
    }
}
