<?php

namespace App\Support;

use App\Models\ChannelAllocation;
use App\Models\MediaGateway;
use App\Models\PdcServer;

class InventoryImportCatalog
{
    /**
     * @return array<string, mixed>
     */
    public static function operation(string $module): array
    {
        $modules = OperationCatalog::modules();
        abort_unless(isset($modules[$module]), 404);
        $config = $modules[$module];

        return array_merge(self::operationExtras($module), [
            'key' => $module,
            'title' => $config['title'],
            'model' => $config['model'],
            'fields' => $config['columns'],
            'filename' => $module,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function gateway(string $key = 'gsm-gateways'): array
    {
        $title = $key === 'media-gateways' ? 'Media Gateways' : 'GSM Gateways';

        return [
            'key' => $key,
            'title' => $title,
            'model' => MediaGateway::class,
            'fields' => [
                'site_name' => 'Site Name',
                'site_code' => 'Site Code',
                'ip_address' => 'IP Address',
                'username' => 'Username',
                'database' => 'Database',
            ],
            'required' => ['site_name', 'site_code', 'ip_address', 'username', 'database'],
            'ip_fields' => ['ip_address'],
            'unique' => ['site_code'],
            'filename' => $key,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function location(string $slug, string $name): array
    {
        return [
            'key' => 'program-location-'.$slug,
            'title' => $name.' GSM Gateways',
            'model' => MediaGateway::class,
            'fields' => [
                'site_name' => 'Site Name',
                'site_code' => 'Site Code',
                'ip_address' => 'IP Address',
                'username' => 'Username',
                'database' => 'Database',
            ],
            'required' => ['site_name', 'site_code', 'ip_address', 'username', 'database'],
            'ip_fields' => ['ip_address'],
            'unique' => ['site_code'],
            'fixed' => ['site_name' => $name],
            'filename' => $slug.'-gsm-gateways',
        ];
    }

    /**
     * @return list<string>
     */
    public static function existingIpv4Addresses(): array
    {
        $ips = [];
        foreach (PdcServer::query()->whereNotNull('ip_address')->pluck('ip_address') as $value) {
            $ips[] = strtolower(trim((string) $value));
        }
        foreach (MediaGateway::query()->whereNotNull('ip_address')->pluck('ip_address') as $value) {
            $ips[] = strtolower(trim((string) $value));
        }
        foreach (ChannelAllocation::query()->whereNotNull('media_gateway')->pluck('media_gateway') as $value) {
            foreach (preg_split('/\s*,\s*/', (string) $value) ?: [] as $part) {
                $part = strtolower(trim($part));
                if ($part !== '') {
                    $ips[] = $part;
                }
            }
        }

        return array_values(array_unique(array_filter($ips)));
    }

    /**
     * @return array<string, mixed>
     */
    private static function operationExtras(string $module): array
    {
        return match ($module) {
            'telco-cost' => [
                'required' => ['provider', 'site', 'service_type', 'monthly_cost', 'status'],
                'numeric_fields' => ['monthly_cost'],
                'date_fields' => ['contract_start', 'contract_end'],
                'status_options' => ['Active', 'Inactive', 'Expiring'],
            ],
            'channel-prefix' => [
                'required' => ['prefix', 'status'],
                'unique' => ['prefix'],
                'status_options' => ['Active', 'Inactive'],
            ],
            'channel-port' => [
                'required' => ['port_number', 'status'],
                'integer_fields' => ['port_number'],
                'composite_unique' => [['port_number', 'gateway']],
                'status_options' => ['Available', 'In Use', 'Disabled'],
            ],
            'network-prefix' => [
                'required' => ['network', 'prefix', 'status'],
                'composite_unique' => [['network', 'prefix']],
                'status_options' => ['Active', 'Inactive'],
            ],
            'pdc-servers' => [
                'required' => ['hostname', 'ip_address', 'status'],
                'ip_fields' => ['ip_address'],
                'unique' => ['hostname'],
                'status_options' => ['Active', 'Inactive'],
            ],
            'sip-channels' => [
                'required' => ['channel', 'status'],
                'unique' => ['channel'],
                'status_options' => ['Active', 'Inactive'],
            ],
            'archive-recordings' => [
                'required' => ['server', 'storage_path', 'status'],
                'integer_fields' => ['retention_days'],
                'status_options' => ['Active', 'Inactive'],
            ],
            'globe-sim' => [
                'required' => ['sim_number', 'status'],
                'unique' => ['sim_number'],
                'status_options' => ['Active', 'Inactive'],
            ],
            'smart-sim' => [
                'required' => ['sim_number', 'status'],
                'unique' => ['sim_number'],
                'status_options' => ['Active', 'Inactive'],
            ],
            'program-inbound-numbers' => [
                'required' => ['number', 'status'],
                'unique' => ['number'],
                'status_options' => ['Active', 'Inactive'],
            ],
            'signal-boosters' => [
                'required' => ['model', 'serial_number', 'status'],
                'unique' => ['serial_number'],
                'status_options' => ['Active', 'Inactive'],
            ],
            'defective-gsm' => [
                'required' => ['asset_code', 'status'],
                'date_fields' => ['reported_on'],
                'status_options' => ['Open', 'In Repair', 'Replaced', 'Closed'],
            ],
            default => [],
        };
    }
}
