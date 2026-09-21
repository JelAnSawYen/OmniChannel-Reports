<?php

namespace App\Support;

use App\Models\ChannelAllocation;
use App\Models\ChannelAllocationCampaign;
use App\Models\ChannelPort;
use App\Models\GlobeSim;
use App\Models\MediaGateway;
use App\Models\NetworkPrefix;
use App\Models\ProgramInboundNumber;
use App\Models\SipChannel;
use App\Models\SmartSim;
use App\Support\GsmSimInventory;
use App\Support\Inbound\ProgramInboundSimLookup;

class InventoryDependentSync
{
    public static function campaignSaved(ChannelAllocationCampaign $campaign): void
    {
        if (! $campaign->wasChanged('name')) {
            return;
        }

        ProgramInboundNumber::query()
            ->where('campaign_id', $campaign->id)
            ->update(['program' => $campaign->name]);
    }

    public static function sipChannelSaved(SipChannel $sip): void
    {
        $oldName = trim((string) $sip->getOriginal('etpi_sip_name'));
        $newName = trim((string) $sip->etpi_sip_name);
        $matchName = $sip->wasChanged('etpi_sip_name') && $oldName !== '' ? $oldName : $newName;
        if ($matchName === '') {
            return;
        }

        $updates = [];
        if ($sip->wasChanged('etpi_sip_name') && $newName !== '') {
            $updates['channel_allocation'] = $newName;
        }
        if ($sip->wasChanged('network')) {
            $updates['network'] = $sip->network;
        }
        if ($sip->wasChanged('channel_count')) {
            $updates['total_channel_allocated'] = (int) $sip->channel_count;
        }
        if ($updates === []) {
            return;
        }

        $query = ChannelAllocation::query()
            ->whereRaw('LOWER(channel_allocation) = ?', [mb_strtolower($matchName)]);
        $campaignIds = (clone $query)->pluck('campaign_id')->unique()->filter()->all();
        $query->update($updates);
        self::refreshCampaignTotals($campaignIds);
    }

    public static function gatewaySaved(MediaGateway $gateway): void
    {
        ProgramInboundSimLookup::flush();

        $oldHost = trim((string) $gateway->getOriginal('hostname'));
        $newHost = trim((string) $gateway->hostname);
        $hostKey = $gateway->wasChanged('hostname') && $oldHost !== '' ? $oldHost : $newHost;

        $allocationUpdates = [];
        if ($gateway->wasChanged('hostname') && $newHost !== '') {
            $allocationUpdates['channel_allocation'] = $newHost;
            $allocationUpdates['media_gateway'] = $newHost;
        }
        if ($gateway->wasChanged('network')) {
            $allocationUpdates['network'] = $gateway->network;
        }
        if ($gateway->wasChanged('channel_count')) {
            $allocationUpdates['total_channel_allocated'] = (int) $gateway->channel_count;
        }

        if ($allocationUpdates !== [] && $hostKey !== '') {
            $query = ChannelAllocation::query()->where(function ($allocations) use ($hostKey) {
                $key = mb_strtolower($hostKey);
                $allocations->whereRaw('LOWER(channel_allocation) = ?', [$key])
                    ->orWhereRaw('LOWER(media_gateway) = ?', [$key]);
            });
            $campaignIds = (clone $query)->pluck('campaign_id')->unique()->filter()->all();
            $query->update($allocationUpdates);
            self::refreshCampaignTotals($campaignIds);
        }

        $oldIp = trim((string) $gateway->getOriginal('ip_address'));
        $newIp = trim((string) $gateway->ip_address);
        if ($gateway->wasChanged('ip_address')) {
            if ($oldIp !== '') {
                GlobeSim::query()
                    ->whereRaw('LOWER(TRIM(ip_address)) = ?', [mb_strtolower($oldIp)])
                    ->update(['ip_address' => $newIp !== '' ? $newIp : null]);
                SmartSim::query()
                    ->whereRaw('LOWER(TRIM(ip_address)) = ?', [mb_strtolower($oldIp)])
                    ->update(['ip_address' => $newIp !== '' ? $newIp : null]);
            }
            $gateway->loadMissing('assignments');
            foreach ($gateway->assignments as $assignment) {
                $sim = GsmSimInventory::findSim((string) $assignment->sim_type, (int) $assignment->sim_id);
                if ($sim) {
                    $sim->forceFill(['ip_address' => $newIp !== '' ? $newIp : null])->saveQuietly();
                }
            }
        }

        if ($gateway->wasChanged('site_code')) {
            self::replaceCopiedGatewayLabel(
                trim((string) $gateway->getOriginal('site_code')),
                trim((string) $gateway->site_code)
            );
        }
        if ($gateway->wasChanged('site_name')) {
            self::replaceCopiedGatewayLabel(
                trim((string) $gateway->getOriginal('site_name')),
                trim((string) $gateway->site_name)
            );
        }

        if ($gateway->wasChanged('hostname')) {
            self::refreshInboundAssignmentHostnames($gateway);
        }
    }

    public static function sipChannelDeleted(SipChannel $sip): void
    {
        $name = trim((string) $sip->etpi_sip_name);
        if ($name === '') {
            return;
        }

        $query = ChannelAllocation::query()
            ->whereRaw('LOWER(channel_allocation) = ?', [mb_strtolower($name)]);
        $campaignIds = (clone $query)->pluck('campaign_id')->unique()->filter()->all();
        $query->delete();
        self::refreshCampaignTotals($campaignIds);
    }

    public static function gatewayDeleted(MediaGateway $gateway): void
    {
        ProgramInboundSimLookup::flush();

        $host = trim((string) $gateway->hostname);
        $ip = trim((string) $gateway->ip_address);

        $query = ChannelAllocation::query()->where(function ($allocations) use ($host, $ip): void {
            if ($host !== '') {
                $key = mb_strtolower($host);
                $allocations->whereRaw('LOWER(channel_allocation) = ?', [$key])
                    ->orWhereRaw('LOWER(media_gateway) = ?', [$key]);
            }
            if ($ip !== '') {
                $allocations->orWhereRaw('LOWER(TRIM(media_gateway)) = ?', [mb_strtolower($ip)]);
            }
        });
        if ($host !== '' || $ip !== '') {
            $campaignIds = (clone $query)->pluck('campaign_id')->unique()->filter()->all();
            $query->delete();
            self::refreshCampaignTotals($campaignIds);
        }

        $gateway->loadMissing('assignments');
        foreach ($gateway->assignments as $assignment) {
            $sim = GsmSimInventory::findSim((string) $assignment->sim_type, (int) $assignment->sim_id);
            if ($sim) {
                $sim->forceFill(['ip_address' => null])->saveQuietly();
            }
        }
        if ($ip !== '') {
            GlobeSim::query()
                ->whereRaw('LOWER(TRIM(ip_address)) = ?', [mb_strtolower($ip)])
                ->update(['ip_address' => null]);
            SmartSim::query()
                ->whereRaw('LOWER(TRIM(ip_address)) = ?', [mb_strtolower($ip)])
                ->update(['ip_address' => null]);
        }

        self::clearInboundGateway($gateway);
        self::clearCopiedGatewayLabel(trim((string) $gateway->site_code));
        self::clearCopiedGatewayLabel(trim((string) $gateway->site_name));
        self::clearCopiedGatewayLabel($host);
        ProgramInboundSimLookup::flush();
    }

    public static function simSaved(GlobeSim|SmartSim $sim): void
    {
        ProgramInboundSimLookup::flush();

        $newMobile = trim((string) ($sim->mobile_number ?? ''));
        $oldMobile = $sim->wasRecentlyCreated
            ? $newMobile
            : trim((string) $sim->getOriginal('mobile_number'));

        self::refreshInboundForSimMobiles($sim, $oldMobile, $newMobile);
    }

    public static function simDeleted(GlobeSim|SmartSim $sim): void
    {
        $mobile = trim((string) ($sim->mobile_number ?? ''));
        ProgramInboundSimLookup::flush();
        self::refreshInboundForSimMobiles($sim, $mobile, $mobile, true);
    }

    private static function refreshInboundForSimMobiles(
        GlobeSim|SmartSim $sim,
        string $oldMobile,
        string $newMobile,
        bool $deleted = false
    ): void {
        $mobiles = array_values(array_unique(array_filter([$oldMobile, $newMobile], static fn (string $value) => $value !== '')));
        if ($mobiles === []) {
            return;
        }

        $network = GsmSimInventory::canonicalNetwork($sim->network)
            ?: GsmSimInventory::networkForType($sim instanceof SmartSim ? 'smart' : 'globe');

        ProgramInboundNumber::query()
            ->where(function ($rows) use ($mobiles): void {
                foreach ($mobiles as $index => $mobile) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $rows->{$method}(function ($inner) use ($mobile): void {
                        $inner->whereRaw('LOWER(number) = ?', [mb_strtolower($mobile)])
                            ->orWhereJsonContains('mobile_numbers', $mobile);
                    });
                }
            })
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($oldMobile, $newMobile, $network, $deleted): void {
                foreach ($rows as $row) {
                    $rowNetwork = GsmSimInventory::canonicalNetwork($row->network);
                    if ($rowNetwork && $rowNetwork !== $network) {
                        continue;
                    }

                    self::refreshInboundRowFromSim($row, $oldMobile, $newMobile, $network, $deleted);
                }
            });
    }

    private static function refreshInboundRowFromSim(
        ProgramInboundNumber $row,
        string $oldMobile,
        string $newMobile,
        string $network,
        bool $deleted
    ): void {
        $mobiles = $row->mobileList();
        if ($oldMobile !== '' && $newMobile !== '' && $oldMobile !== $newMobile) {
            $mobiles = array_values(array_unique(array_map(
                static fn (string $mobile) => $mobile === $oldMobile ? $newMobile : $mobile,
                $mobiles
            )));
        }

        $lookupNetwork = GsmSimInventory::canonicalNetwork($row->network) ?: $network;
        $assignments = $mobiles === [] ? [] : ProgramInboundSimLookup::resolveMany($lookupNetwork, $mobiles);
        $linked = collect($assignments)->first(static fn (array $assignment) => ! empty($assignment['media_gateway_id']));

        $updates = [
            'mobile_numbers' => $mobiles === [] ? null : $mobiles,
            'mobile_assignments' => $assignments === [] ? null : $assignments,
            'media_gateway_id' => $linked['media_gateway_id'] ?? null,
            'port' => $linked['port'] ?? null,
        ];
        if ($mobiles !== [] && ! $deleted) {
            $updates['network'] = $lookupNetwork;
        }
        if ($oldMobile !== '' && $newMobile !== '' && $oldMobile !== $newMobile && strcasecmp((string) $row->number, $oldMobile) === 0) {
            $updates['number'] = $newMobile;
        } elseif ($mobiles !== [] && in_array(trim((string) $row->number), ['', $oldMobile], true)) {
            $updates['number'] = $mobiles[0];
        }

        $row->forceFill($updates)->saveQuietly();
    }

    /**
     * @param  list<int|string>  $campaignIds
     */
    private static function refreshCampaignTotals(array $campaignIds): void
    {
        foreach ($campaignIds as $campaignId) {
            $campaign = ChannelAllocationCampaign::query()->find($campaignId);
            $campaign?->refreshTotalChannelsAllocated();
        }
    }

    private static function refreshInboundAssignmentHostnames(MediaGateway $gateway): void
    {
        $gatewayId = (int) $gateway->id;
        $hostname = trim((string) $gateway->hostname);
        if ($gatewayId < 1) {
            return;
        }

        ProgramInboundNumber::query()
            ->where('media_gateway_id', $gatewayId)
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($gatewayId, $hostname): void {
                foreach ($rows as $row) {
                    $assignments = $row->mobile_assignments;
                    if (! is_array($assignments) || $assignments === []) {
                        continue;
                    }

                    $changed = false;
                    foreach ($assignments as $index => $assignment) {
                        if (! is_array($assignment) || (int) ($assignment['media_gateway_id'] ?? 0) !== $gatewayId) {
                            continue;
                        }
                        if ((string) ($assignment['hostname'] ?? '') === $hostname) {
                            continue;
                        }
                        $assignments[$index]['hostname'] = $hostname;
                        $changed = true;
                    }

                    if ($changed) {
                        $row->forceFill(['mobile_assignments' => $assignments])->saveQuietly();
                    }
                }
            });
    }

    private static function replaceCopiedGatewayLabel(string $previous, string $next): void
    {
        if ($previous === '' || $next === '' || strcasecmp($previous, $next) === 0) {
            return;
        }

        ChannelPort::query()
            ->whereRaw('LOWER(gateway) = ?', [mb_strtolower($previous)])
            ->update(['gateway' => $next]);
        NetworkPrefix::query()
            ->whereRaw('LOWER(gateway) = ?', [mb_strtolower($previous)])
            ->update(['gateway' => $next]);
    }

    private static function clearCopiedGatewayLabel(string $previous): void
    {
        if ($previous === '') {
            return;
        }

        ChannelPort::query()
            ->whereRaw('LOWER(gateway) = ?', [mb_strtolower($previous)])
            ->update(['gateway' => null]);
        NetworkPrefix::query()
            ->whereRaw('LOWER(gateway) = ?', [mb_strtolower($previous)])
            ->update(['gateway' => null]);
    }

    private static function clearInboundGateway(MediaGateway $gateway): void
    {
        $gatewayId = (int) $gateway->id;
        $host = mb_strtolower(trim((string) $gateway->hostname));
        if ($gatewayId < 1) {
            return;
        }

        ProgramInboundNumber::query()
            ->where(function ($rows) use ($gatewayId): void {
                $rows->where('media_gateway_id', $gatewayId)
                    ->orWhereNotNull('mobile_assignments');
            })
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($gatewayId, $host): void {
                foreach ($rows as $row) {
                    $changed = false;
                    if ((int) $row->media_gateway_id === $gatewayId) {
                        $row->media_gateway_id = null;
                        $changed = true;
                    }

                    $assignments = $row->mobile_assignments;
                    if (is_array($assignments) && $assignments !== []) {
                        foreach ($assignments as $index => $assignment) {
                            if (! is_array($assignment)) {
                                continue;
                            }
                            $matchesId = (int) ($assignment['media_gateway_id'] ?? 0) === $gatewayId;
                            $matchesHost = $host !== ''
                                && mb_strtolower(trim((string) ($assignment['hostname'] ?? ''))) === $host;
                            if (! $matchesId && ! $matchesHost) {
                                continue;
                            }
                            $assignments[$index]['hostname'] = '';
                            $assignments[$index]['media_gateway_id'] = null;
                            $changed = true;
                        }
                        if ($changed) {
                            $row->mobile_assignments = $assignments;
                        }
                    }

                    if ($changed) {
                        $row->saveQuietly();
                    }
                }
            });
    }
}
