<?php

namespace App\Support\Gsm;

use App\Models\GlobeSim;
use App\Support\GsmSimInventory;
use App\Support\PdcEndorseDate;
use Illuminate\Support\Facades\DB;

class SimInventoryImportMapper
{
    /**
     * @param  array<string, string>  $values
     * @return array{errors: list<string>, record: ?array<string, mixed>}
     */
    public static function map(array $values, object $context): array
    {
        $module = (string) ($context->module ?? 'globe-sim');
        $expected = $module === 'smart-sim' ? GsmSimInventory::SMART : GsmSimInventory::GLOBE;
        $errors = [];

        $hostname = trim((string) ($values['hostname'] ?? ''));
        $gateway = GsmSimInventory::findGatewayByHostname($hostname);
        if ($hostname === '' || ! $gateway) {
            $errors[] = 'Hostname must match an existing GSM Gateway.';
        }

        $portRaw = trim((string) ($values['port'] ?? ''));
        if ($portRaw === '' || ! preg_match('/^-?\d+$/', $portRaw)) {
            $errors[] = 'Port must be a number.';
            $port = 0;
        } else {
            $port = (int) $portRaw;
            if ($port < 1) {
                $errors[] = 'Port must be a number.';
            }
        }

        if ($gateway && $port > 0) {
            $max = (int) $gateway->channel_count;
            if ($max > 0 && $port > $max) {
                $errors[] = 'Port must not exceed Channel Count.';
            }
            $hostKey = mb_strtolower(trim((string) $gateway->hostname));
            if (isset($context->ports[$hostKey][$port])) {
                $errors[] = 'Port is already assigned on this GSM Gateway.';
            } elseif (GsmSimInventory::portIsTaken($gateway, $port)) {
                $errors[] = 'Port is already assigned on this GSM Gateway.';
            }
        }

        if ($gateway && $port > 0 && ! GsmSimInventory::hasCompanionValue($values)) {
            $errors[] = GsmSimInventory::COMPANION_REQUIRED;
        }

        if ($errors !== []) {
            return ['errors' => $errors, 'record' => null];
        }

        $hostKey = mb_strtolower(trim((string) $gateway->hostname));
        $context->ports[$hostKey][$port] = true;

        $start = PdcEndorseDate::parse((string) ($values['contract_start'] ?? ''));
        $end = PdcEndorseDate::parse((string) ($values['contract_end'] ?? ''));

        return [
            'errors' => [],
            'record' => [
                'imei' => GsmSimInventory::blankSimValue($values['imei'] ?? null),
                'mobile_number' => GsmSimInventory::blankSimValue($values['mobile_number'] ?? null),
                'plan' => GsmSimInventory::blankSimValue($values['plan'] ?? null),
                'ip_address' => $gateway->ip_address,
                'port' => $port,
                'network' => GsmSimInventory::canonicalNetwork($gateway->network ?? null) ?: $expected,
                'account_number' => GsmSimInventory::blankSimValue($values['account_number'] ?? null),
                'contract_start' => $start['valid'] && ! $start['empty'] ? $start['iso'] : null,
                'contract_end' => $end['valid'] && ! $end['empty'] ? $end['iso'] : null,
                '_module' => $module,
                '_hostname' => $gateway->hostname,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $payload
     */
    public static function commit(array $payload, array $config): int
    {
        $model = $config['model'] ?? GlobeSim::class;
        $count = 0;

        DB::transaction(function () use ($payload, $model, &$count): void {
            foreach ($payload as $row) {
                $module = (string) ($row['_module'] ?? 'globe-sim');
                $hostname = (string) ($row['_hostname'] ?? '');
                unset($row['_module'], $row['hostname'], $row['_hostname']);
                $sim = $model::query()->create($row);
                $gateway = GsmSimInventory::findGatewayByHostname($hostname)
                    ?: GsmSimInventory::findGatewayByIp((string) $sim->ip_address);
                $port = (int) ($sim->port ?? 0);
                if ($gateway && $port > 0) {
                    GsmGatewayWriter::syncSimPort(
                        $gateway,
                        $module === 'smart-sim' ? 'smart' : 'globe',
                        (int) $sim->id,
                        $port
                    );
                }
                $count++;
            }
        });

        return $count;
    }
}
