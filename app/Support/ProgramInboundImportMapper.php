<?php

namespace App\Support;

use App\Models\ChannelAllocationCampaign;
use App\Models\MediaGateway;

class ProgramInboundImportMapper
{
    /**
     * @param  array<string, mixed>  $values
     * @return array{errors: list<string>, record: ?array<string, mixed>}
     */
    public static function map(array $values, object $context): array
    {
        $errors = [];
        $campaigns = $context->campaigns ??= ChannelAllocationCampaign::keyedByName();
        $gateways = $context->gateways ??= MediaGateway::query()
            ->get(['id', 'ip_address', 'port', 'network'])
            ->keyBy(fn (MediaGateway $gateway) => mb_strtolower(trim((string) $gateway->ip_address)));
        $seen = $context->numbers ?? [];

        $campaignName = trim((string) ($values['campaign'] ?? ''));
        $campaign = $campaigns->get(mb_strtolower($campaignName));
        if ($campaignName !== '' && ! $campaign) {
            $errors[] = ProgramInboundNumberValidator::CAMPAIGN_MISSING;
        }

        $mobiles = ProgramInboundNumberValidator::normalize($values['mobile'] ?? '');
        $landlines = ProgramInboundNumberValidator::normalize($values['landline'] ?? '');
        if ($mobiles === [] && $landlines === []) {
            $errors[] = ProgramInboundNumberValidator::NEED_NUMBER;
        }

        $errors = array_merge($errors, ProgramInboundNumberValidator::formatErrors($mobiles, $landlines));
        $errors = array_merge(
            $errors,
            ProgramInboundNumberValidator::uniquenessErrors($mobiles, $landlines, $seen, null, true)
        );
        $context->numbers = $seen;

        $gatewayIp = trim((string) ($values['gsm_gateway'] ?? ''));
        $port = trim((string) ($values['port'] ?? ''));
        $network = trim((string) ($values['network'] ?? ''));
        $gateway = $gatewayIp !== '' ? $gateways->get(mb_strtolower($gatewayIp)) : null;

        $mediaGatewayId = null;
        $savedPort = null;
        $savedNetwork = null;

        if ($mobiles !== []) {
            if ($gatewayIp === '') {
                $errors[] = ProgramInboundNumberValidator::GSM_REQUIRED_WITH_MOBILE;
            } elseif (! $gateway) {
                $errors[] = ProgramInboundNumberValidator::GSM_MUST_EXIST;
            } else {
                $mediaGatewayId = $gateway->id;
                $savedPort = trim((string) $gateway->port);
                $savedNetwork = trim((string) $gateway->network);
                if ($port !== '' && strcasecmp($port, $savedPort) !== 0) {
                    $errors[] = ProgramInboundNumberValidator::PORT_MUST_MATCH;
                }
                if ($network !== '' && strcasecmp($network, $savedNetwork) !== 0) {
                    $errors[] = ProgramInboundNumberValidator::NETWORK_MUST_MATCH;
                }
            }
        } elseif ($gatewayIp !== '' || $port !== '' || $network !== '') {
            $errors[] = ProgramInboundNumberValidator::GSM_LANDLINE_ONLY;
        }

        if ($errors !== [] || ! $campaign) {
            return ['errors' => array_values(array_unique($errors)), 'record' => null];
        }

        return [
            'errors' => [],
            'record' => [
                'campaign_id' => $campaign->id,
                'program' => $campaign->name,
                'number' => $mobiles[0] ?? $landlines[0] ?? '',
                'mobile_numbers' => $mobiles === [] ? null : $mobiles,
                'landline_numbers' => $landlines === [] ? null : $landlines,
                'media_gateway_id' => $mediaGatewayId,
                'port' => $savedPort !== '' ? $savedPort : null,
                'network' => $savedNetwork !== '' ? $savedNetwork : null,
                'remarks' => trim((string) ($values['remarks'] ?? '')) ?: null,
                'status' => 'Active',
            ],
        ];
    }
}
