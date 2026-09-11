<?php

namespace App\Support;

use App\Models\ChannelAllocation;
use App\Models\ChannelAllocationCampaign;
use App\Models\MediaGateway;
use App\Models\PdcServer;
use App\Support\ProgramInboundImportMapper;

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
        $extras = self::operationExtras($module);
        $fields = $config['table_columns'] ?? $config['columns'];
        if (! empty($extras['extra_fields']) && is_array($extras['extra_fields'])) {
            $fields = array_merge($fields, $extras['extra_fields']);
            unset($extras['extra_fields']);
        }

        return array_merge($extras, [
            'key' => $module,
            'title' => $config['title'],
            'model' => $config['model'],
            'fields' => $fields,
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
                'ip_address' => 'Hostname IP',
                'site_code' => 'Serial Number',
                'plan' => 'Plan',
                'port' => 'Port',
                'network' => 'Network',
                'device_function' => 'Function',
                'site_name' => 'Site',
                'username' => 'User',
                'password' => 'Password',
            ],
            'required' => ['ip_address', 'site_code', 'site_name', 'username'],
            'ip_fields' => ['ip_address'],
            'unique' => ['site_code'],
            'include_id' => false,
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
     * @return array<string, mixed>
     */
    public static function campaigns(): array
    {
        return [
            'key' => 'campaigns',
            'title' => 'Campaigns',
            'model' => ChannelAllocationCampaign::class,
            'fields' => [
                'name' => 'Campaigns',
                'fte' => 'FTE',
                'location' => 'Location',
            ],
            'required' => ['name', 'fte', 'location'],
            'integer_fields' => ['fte'],
            'unique' => ['name'],
            'options' => [
                'location' => OperationCatalog::locationNames(),
            ],
            'include_id' => false,
            'filename' => 'campaigns',
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
                'required' => ['hostname', 'ip_address'],
                'ip_fields' => ['ip_address'],
                'unique' => ['hostname', 'ip_address'],
            ],
            'sip-channels' => [
                'required' => ['etpi_sip_name'],
                'unique' => ['etpi_sip_name'],
                'integer_fields' => ['channel_count'],
                'date_fields' => ['date_activation'],
            ],
            'archive-recordings' => [
                'required' => ['file_name', 'called_at'],
                'date_fields' => ['called_at'],
            ],
            'globe-sim' => self::simImportExtras(),
            'smart-sim' => self::simImportExtras(),
            'program-inbound-numbers' => [
                'required' => ['campaign'],
                'include_id' => false,
                'no_carry' => ['mobile', 'landline', 'gsm_gateway', 'port', 'network', 'remarks'],
                'to_record' => [ProgramInboundImportMapper::class, 'map'],
            ],
            'signal-boosters' => [
                'required' => ['model', 'serial_number', 'status'],
                'unique' => ['serial_number'],
                'status_options' => ['Active', 'Inactive'],
                'no_carry' => ['specs'],
            ],
            'defective-gsm' => [
                'required' => ['asset_code', 'status'],
                'mdy_date_fields' => ['reported_on'],
                'status_options' => ['Open', 'In Repair', 'Replaced', 'Closed'],
            ],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function simImportExtras(): array
    {
        return [
            'required' => ['imei', 'mobile_number'],
            'unique' => ['imei', 'mobile_number'],
            'ip_fields' => ['ip_address'],
            'mdy_date_fields' => ['contract_start', 'contract_end'],
            'include_id' => false,
        ];
    }
}
