<?php

namespace App\Support;

use App\Models\MediaGateway;
use App\Models\SipChannel;

class ChannelAllocationResolver
{
    /**
     * @return array{ok: true, type: string, channel: string, media_gateway: ?string, network: ?string, total_channel_allocated: ?int}|array{ok: false, error: string}
     */
    public static function resolve(string $channel, ?string $type = null): array
    {
        $channel = trim($channel);
        if ($channel === '') {
            return ['ok' => false, 'error' => 'Channel is required'];
        }
        if ($channel === '-') {
            return ['ok' => false, 'error' => 'Channel cannot be -'];
        }
        if (mb_strlen($channel) > 255) {
            return ['ok' => false, 'error' => 'Channel must be 255 characters or fewer'];
        }

        $sip = self::sipByName($channel);
        $gsm = self::gatewayByHostname($channel);
        $type = $type !== null && $type !== '' ? mb_strtolower(trim($type)) : null;

        if ($type === 'sip') {
            if ($sip === null) {
                return ['ok' => false, 'error' => 'Channel must match an existing SIP Name'];
            }

            return self::fromSip($sip);
        }

        if ($type === 'gsm') {
            if ($gsm === null) {
                return ['ok' => false, 'error' => 'Channel must match an existing GSM Hostname'];
            }

            return self::fromGsm($gsm);
        }

        if ($sip !== null && $gsm !== null) {
            return ['ok' => false, 'error' => 'Channel matches both a SIP Channel and a GSM Gateway'];
        }
        if ($sip !== null) {
            return self::fromSip($sip);
        }
        if ($gsm !== null) {
            return self::fromGsm($gsm);
        }

        return ['ok' => false, 'error' => 'Channel must match an existing SIP Name or GSM Hostname'];
    }

    public static function sipByName(string $name): ?SipChannel
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        return SipChannel::query()
            ->whereRaw('LOWER(etpi_sip_name) = ?', [mb_strtolower($name)])
            ->first();
    }

    public static function gatewayByHostname(string $name): ?MediaGateway
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        return MediaGateway::query()
            ->whereNotNull('hostname')
            ->where('hostname', '!=', '')
            ->whereRaw('LOWER(hostname) = ?', [mb_strtolower($name)])
            ->first();
    }

    /**
     * @return array{ok: true, type: string, channel: string, media_gateway: ?string, network: ?string, total_channel_allocated: ?int}
     */
    private static function fromSip(SipChannel $sip): array
    {
        return [
            'ok' => true,
            'type' => 'sip',
            'channel' => (string) $sip->etpi_sip_name,
            'media_gateway' => null,
            'network' => $sip->network !== null && trim((string) $sip->network) !== '' ? (string) $sip->network : null,
            'total_channel_allocated' => $sip->channel_count,
        ];
    }

    /**
     * @return array{ok: true, type: string, channel: string, media_gateway: ?string, network: ?string, total_channel_allocated: ?int}
     */
    private static function fromGsm(MediaGateway $gateway): array
    {
        $hostname = (string) $gateway->hostname;

        return [
            'ok' => true,
            'type' => 'gsm',
            'channel' => $hostname,
            'media_gateway' => $hostname,
            'network' => $gateway->network !== null && trim((string) $gateway->network) !== '' ? (string) $gateway->network : null,
            'total_channel_allocated' => $gateway->channel_count,
        ];
    }
}
