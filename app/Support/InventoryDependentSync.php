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
        if ($gateway->wasChanged('ip_address') && $oldIp !== '') {
            GlobeSim::query()
                ->whereRaw('LOWER(TRIM(ip_address)) = ?', [mb_strtolower($oldIp)])
                ->update(['ip_address' => $newIp]);
            SmartSim::query()
                ->whereRaw('LOWER(TRIM(ip_address)) = ?', [mb_strtolower($oldIp)])
                ->update(['ip_address' => $newIp]);
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
}
