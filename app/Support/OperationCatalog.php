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
        return [
            'alcar' => 'Alcar',
            'ctn' => 'CTN',
            'scs' => 'SCS',
            'estancia' => 'Estancia',
            'skyrise' => 'Skyrise',
        ];
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
                'description' => 'Manage PDC server inventory, addressing, and operational status.',
                'model' => PdcServer::class,
                'columns' => ['hostname' => 'Hostname', 'ip_address' => 'IP Address', 'location' => 'Location', 'role' => 'Role', 'status' => 'Status'],
                'fields' => ['hostname', 'ip_address', 'location', 'role', 'status'],
            ],
            'sip-channels' => [
                'title' => 'SIP Channels',
                'description' => 'Manage SIP channel names, peers, codecs, and status.',
                'model' => SipChannel::class,
                'columns' => ['channel' => 'Channel', 'peer' => 'Peer', 'context' => 'Context', 'codec' => 'Codec', 'status' => 'Status'],
                'fields' => ['channel', 'peer', 'context', 'codec', 'status'],
            ],
            'archive-recordings' => [
                'title' => 'Archive Recordings',
                'description' => 'Manage recording archive servers, storage paths, and retention.',
                'model' => ArchiveRecording::class,
                'columns' => ['server' => 'Server', 'storage_path' => 'Storage Path', 'retention_days' => 'Retention Days', 'status' => 'Status'],
                'fields' => ['server', 'storage_path', 'retention_days', 'status'],
            ],
            'globe-sim' => [
                'title' => 'Globe SIM',
                'description' => 'Manage Globe SIM inventory and assignments.',
                'model' => GlobeSim::class,
                'columns' => ['sim_number' => 'SIM Number', 'imsi' => 'IMSI', 'assigned_to' => 'Assigned To', 'location' => 'Location', 'status' => 'Status'],
                'fields' => ['sim_number', 'imsi', 'assigned_to', 'location', 'status'],
            ],
            'smart-sim' => [
                'title' => 'Smart SIM',
                'description' => 'Manage Smart SIM inventory and assignments.',
                'model' => SmartSim::class,
                'columns' => ['sim_number' => 'SIM Number', 'imsi' => 'IMSI', 'assigned_to' => 'Assigned To', 'location' => 'Location', 'status' => 'Status'],
                'fields' => ['sim_number', 'imsi', 'assigned_to', 'location', 'status'],
            ],
            'program-inbound-numbers' => [
                'title' => 'Program Inbound Numbers',
                'description' => 'Manage inbound numbers assigned to programs and locations.',
                'model' => ProgramInboundNumber::class,
                'columns' => ['number' => 'Number', 'program' => 'Program', 'location' => 'Location', 'assigned_channel' => 'Assigned Channel', 'status' => 'Status'],
                'fields' => ['number', 'program', 'location', 'assigned_channel', 'status'],
            ],
            'signal-boosters' => [
                'title' => 'Signal Boosters',
                'description' => 'Manage signal booster hardware by location.',
                'model' => SignalBooster::class,
                'columns' => ['model' => 'Model', 'serial_number' => 'Serial Number', 'location' => 'Location', 'status' => 'Status'],
                'fields' => ['model', 'serial_number', 'location', 'status'],
            ],
            'defective-gsm' => [
                'title' => 'Defective GSM',
                'description' => 'Track defective GSM assets, issues, and repair status.',
                'model' => DefectiveGsm::class,
                'columns' => ['asset_code' => 'Asset Code', 'location' => 'Location', 'issue' => 'Issue', 'reported_on' => 'Reported On', 'status' => 'Status'],
                'fields' => ['asset_code', 'location', 'issue', 'reported_on', 'status'],
            ],
        ];
    }

    public static function sidebarModules(): array
    {
        return [
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
}
