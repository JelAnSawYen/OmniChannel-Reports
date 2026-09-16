<?php

namespace App\Support;

use App\Models\ArchiveRecording;
use App\Models\ChannelPort;
use App\Models\ChannelPrefix;
use App\Models\DefectiveGsm;
use App\Models\GlobeSim;
use App\Models\NetworkPrefix;
use App\Models\PdcServer;
use App\Models\ProgramInboundNumber;
use App\Models\SignalBooster;
use App\Models\SipChannel;
use App\Models\SmartSim;
use App\Models\TelcoCost;

class OperationCatalog
{
    public static function locations(): array
    {
        $display = [
            'alcar' => 'Alcar',
            'ctn' => 'CTN',
            'estancia' => 'Estancia',
            'scs' => 'SC5',
            'skyrise' => 'Skyrise',
        ];

        $locations = [];
        foreach (self::locationMapSites() as $slug => $site) {
            if ($slug === 'pdc' || strcasecmp((string) ($site['name'] ?? ''), 'PDC') === 0) {
                $locations['wfh'] = 'WFH';
                continue;
            }

            $locations[$slug] = $display[$slug] ?? $site['name'];
        }

        uasort($locations, fn (string $left, string $right) => strnatcasecmp($left, $right));

        return $locations;
    }

    /**
     * Map markers for Program Location. Coordinates come from published building
     * listings / OpenStreetMap for each SSG site; the database has no lat/lng columns.
     *
     * @return array<string, array{name: string, address: string, lat: float, lng: float, assigned: bool}>
     */
    public static function locationMapSites(): array
    {
        return [
            'alcar' => [
                'name' => 'ALCAR',
                'address' => 'G/F Alcar Building, 888 EDSA, Mandaluyong City',
                'lat' => 14.57801,
                'lng' => 121.05279,
                'assigned' => true,
            ],
            'cg3' => [
                'name' => 'CG3',
                'address' => 'Robinsons Cybergate Center Tower 3, Pioneer St., Mandaluyong City',
                'lat' => 14.5709738,
                'lng' => 121.0499863,
                'assigned' => true,
            ],
            'ctn' => [
                'name' => 'CTN',
                'address' => 'Citynet Building, 612–628 Sultan St., Mandaluyong City',
                'lat' => 14.5812809,
                'lng' => 121.0525056,
                'assigned' => true,
            ],
            'estancia' => [
                'name' => 'ESTANCIA',
                'address' => '5th Floor, Estancia North Wing, Capitol Commons, Meralco Ave., Pasig City',
                'lat' => 14.5762409,
                'lng' => 121.0629435,
                'assigned' => true,
            ],
            'scs' => [
                'name' => 'SC5',
                'address' => '3F Silver City 5 (formerly Transcom Building), Eulogio Rodriguez Jr. Ave., Pasig City',
                'lat' => 14.5872126,
                'lng' => 121.0788446,
                'assigned' => true,
            ],
            'skyrise' => [
                'name' => 'SKYRISE',
                'address' => 'Skyrise Beta, Samar Loop, Cebu City, Cebu 6000',
                'lat' => 10.3177541,
                'lng' => 123.9089807,
                'assigned' => true,
            ],
            'pdc' => [
                'name' => 'PDC',
                'address' => '6819 Ayala Avenue, Makati City, RCBC Plaza',
                'lat' => 14.5607611,
                'lng' => 121.0165333,
                'assigned' => false,
            ],
        ];
    }

    /**
     * Display labels for Program Location pages. Keys match locationMapSites slugs.
     *
     * @return array<string, string>
     */
    public static function programLocationLabels(): array
    {
        return [
            'alcar' => 'Alcar',
            'cg3' => 'CG3',
            'ctn' => 'CTN',
            'estancia' => 'Estancia',
            'scs' => 'SC5',
            'skyrise' => 'Skyrise',
            'pdc' => 'PDC',
        ];
    }

    /**
     * @return list<string>
     */
    public static function locationNames(): array
    {
        return array_values(self::locations());
    }

    public static function canonicalLocationName(?string $name): ?string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        foreach (self::locationNames() as $label) {
            if (strcasecmp($label, $name) === 0) {
                return $label;
            }
        }

        return null;
    }

    public static function modules(): array
    {
        return [
            'telco-cost' => [
                'title' => 'Telco Cost',
                'description' => 'Manage telecommunications providers, contracts, and monthly costs.',
                'model' => TelcoCost::class,
                'columns' => ['provider' => 'Provider', 'site' => 'Site', 'service_type' => 'Service Type', 'monthly_cost' => 'Monthly Cost', 'contract_end' => 'Contract End', 'status' => 'Status'],
                'fields' => ['provider', 'site', 'service_type', 'monthly_cost', 'contract_start', 'contract_end', 'status'],
            ],
            'channel-prefix' => [
                'title' => 'Channel Prefix',
                'description' => 'Manage channel prefixes and routing assignments.',
                'model' => ChannelPrefix::class,
                'columns' => ['prefix' => 'Prefix', 'channel' => 'Channel', 'description' => 'Description', 'status' => 'Status'],
                'fields' => ['prefix', 'channel', 'description', 'status'],
            ],
            'channel-port' => [
                'title' => 'Channel Port',
                'description' => 'Manage channel ports and gateway assignments.',
                'model' => ChannelPort::class,
                'columns' => ['port_number' => 'Port Number', 'gateway' => 'Gateway', 'channel' => 'Channel', 'status' => 'Status', 'description' => 'Description'],
                'fields' => ['port_number', 'gateway', 'channel', 'status', 'description'],
            ],
            'network-prefix' => [
                'title' => 'Network Prefix',
                'description' => 'Manage network prefixes and gateway routing.',
                'model' => NetworkPrefix::class,
                'columns' => ['network' => 'Network', 'prefix' => 'Prefix', 'gateway' => 'Gateway', 'status' => 'Status', 'description' => 'Description'],
                'fields' => ['network', 'prefix', 'gateway', 'status', 'description'],
            ],
            'pdc-servers' => [
                'title' => 'PDC Servers',
                'description' => 'Manage PDC server inventory grouped by campaign.',
                'model' => PdcServer::class,
                'columns' => ['hostname' => 'Hostname', 'ip_address' => 'IP Address', 'location' => 'Location', 'role' => 'Role', 'status' => 'Status'],
                'fields' => ['hostname', 'ip_address', 'location', 'role', 'status'],
            ],
            'sip-channels' => [
                'title' => 'SIP Channels',
                'description' => 'Manage SIP channel campaigns, ranges, and activation dates.',
                'model' => SipChannel::class,
                'columns' => [
                    'etpi_sip_name' => 'ETPI SIP NAME',
                    'pilot_number' => 'Pilot Number',
                    'channel_count' => 'Channel Count',
                    'channel_range' => 'Channel Range',
                    'network' => 'Network',
                    'date_activation' => 'Date Activation',
                ],
                'fields' => ['campaign_id', 'etpi_sip_name', 'pilot_number', 'channel_count', 'channel_range', 'network', 'date_activation'],
            ],
            'archive-recordings' => [
                'title' => 'Archive Recordings',
                'description' => 'Browse call recordings by campaign, year, and month.',
                'model' => ArchiveRecording::class,
                'columns' => [
                    'file_name' => 'File Name',
                    'called_at' => 'Call Date & Time',
                    'caller_number' => 'Caller Number',
                    'agent_number' => 'Agent Number',
                    'duration' => 'Duration',
                    'location' => 'Location',
                ],
                'fields' => ['campaign_id', 'file_name', 'called_at', 'caller_number', 'agent_number', 'duration', 'location', 'storage_path'],
            ],
            'globe-sim' => [
                'title' => 'Globe SIM',
                'description' => 'Manage Globe SIM inventory and assignments.',
                'model' => GlobeSim::class,
                'columns' => self::simTableColumns(),
                'fields' => array_keys(self::simFormFields()),
            ],
            'smart-sim' => [
                'title' => 'Smart SIM',
                'description' => 'Manage Smart SIM inventory and assignments.',
                'model' => SmartSim::class,
                'columns' => self::simTableColumns(),
                'fields' => array_keys(self::simFormFields()),
            ],
            'program-inbound-numbers' => [
                'title' => 'Program Inbound Numbers',
                'description' => 'Manage inbound numbers assigned to programs and locations.',
                'model' => ProgramInboundNumber::class,
                'columns' => ['number' => 'Number', 'program' => 'Program', 'location' => 'Location', 'assigned_channel' => 'Assigned Channel', 'status' => 'Status'],
                'fields' => ['campaign', 'network', 'mobile_numbers', 'landline_numbers', 'remarks'],
                'table_columns' => [
                    'campaign' => 'Campaign',
                    'mobile' => 'Mobile',
                    'landline' => 'Landline',
                    'gsm_gateway' => 'GSM Gateway',
                    'port' => 'Port',
                    'network' => 'Network',
                    'remarks' => 'Remarks',
                ],
            ],
            'signal-boosters' => [
                'title' => 'Signal Boosters',
                'description' => 'Manage signal booster hardware by location.',
                'model' => SignalBooster::class,
                'columns' => [
                    'model' => 'Model',
                    'specs' => 'Specifications',
                    'serial_number' => 'Serial Number',
                    'location' => 'Location',
                    'status' => 'Status',
                ],
                'fields' => ['model', 'specs', 'serial_number', 'location', 'status'],
            ],
            'defective-gsm' => [
                'title' => 'Defective GSM',
                'description' => 'Track defective GSM assets, issues, and repair status.',
                'model' => DefectiveGsm::class,
                'columns' => ['asset_code' => 'Serial Tag', 'location' => 'Location', 'issue' => 'Issue', 'reported_on' => 'Reported On', 'status' => 'Status'],
                'fields' => ['asset_code', 'location', 'issue', 'reported_on', 'status'],
            ],
        ];
    }

    public static function sidebarModules(): array
    {
        return [
            'campaigns' => 'Campaigns',
            'pdc-servers' => 'PDC Servers',
            'sip-channels' => 'SIP Channels',
            'channel-allocation' => 'Channel Allocation',
            'archive-recordings' => 'Archive Recordings',
            'globe-sim' => 'Globe SIM',
            'smart-sim' => 'Smart SIM',
            'program-inbound-numbers' => 'Program Inbound Numbers',
            'signal-boosters' => 'Signal Boosters',
            'defective-gsm' => 'Defective GSM',
        ];
    }

    /**
     * Globe / Smart SIM table columns.
     *
     * @return array<string, string>
     */
    public static function simTableColumns(): array
    {
        return [
            'imei' => 'IMEI',
            'mobile_number' => 'Mobile Number',
            'plan' => 'Plan',
            'ip_address' => 'IP',
            'port' => 'Port',
            'account_number' => 'Account Number',
            'contract_start' => 'Contract Start',
            'contract_end' => 'Contract End',
        ];
    }

    /**
     * Shared Globe / Smart SIM field map. Keys are database columns; values are UI labels.
     *
     * @return array<string, string>
     */
    public static function simColumns(): array
    {
        return self::simTableColumns();
    }

    /**
     * Globe / Smart SIM add/edit and Data Transfer fields. Port comes from GSM Gateway assignments.
     *
     * @return array<string, string>
     */
    public static function simFormFields(): array
    {
        return [
            'imei' => 'IMEI',
            'mobile_number' => 'Mobile Number',
            'plan' => 'Plan',
            'ip_address' => 'IP',
            'account_number' => 'Account Number',
            'contract_start' => 'Contract Start',
            'contract_end' => 'Contract End',
        ];
    }

    public static function isSim(string $module): bool
    {
        return in_array($module, ['globe-sim', 'smart-sim'], true);
    }

    public static function isInbound(string $module): bool
    {
        return $module === 'program-inbound-numbers';
    }

    public static function isBooster(string $module): bool
    {
        return $module === 'signal-boosters';
    }

    public static function isDefective(string $module): bool
    {
        return $module === 'defective-gsm';
    }

    /**
     * Data Transfer columns match the table fields. No Id and no Last Updated.
     *
     * @return array<string, string>
     */
    public static function simTransferColumns(): array
    {
        return self::simFormFields();
    }
}
