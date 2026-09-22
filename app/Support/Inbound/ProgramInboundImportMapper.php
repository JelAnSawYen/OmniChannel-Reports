<?php

namespace App\Support\Inbound;

use App\Models\ChannelAllocationCampaign;
use App\Support\GsmSimInventory;

class ProgramInboundImportMapper
{
    /**
     * @param  array<string, mixed>  $values
     * @return array{errors: list<string>, record: ?array<string, mixed>}
     */
    public static function map(array $values, object $context): array
    {
        if (empty($context->pinLookupReady)) {
            ProgramInboundSimLookup::flush();
            $context->pinLookupReady = true;
        }
        $errors = [];
        // Only Master Campaign values are accepted; never create a missing campaign.
        $campaigns = $context->campaigns ??= ChannelAllocationCampaign::masterOptionsForDropdown()
            ->keyBy(static fn (ChannelAllocationCampaign $campaign) => mb_strtolower((string) $campaign->name));
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
        $errors = array_merge($errors, ProgramInboundNumberValidator::landlineSourceErrors($landlines));
        $errors = array_merge(
            $errors,
            ProgramInboundNumberValidator::uniquenessErrors(
                $mobiles,
                $landlines,
                $seen,
                null,
                true,
                $campaign ? (int) $campaign->id : null,
                mb_strtolower($campaignName)
            )
        );
        $context->numbers = $seen;

        $postedGateway = trim((string) ($values['gsm_gateway'] ?? ''));
        $postedPorts = ProgramInboundNumberValidator::normalize($values['port'] ?? '');
        $postedNetwork = trim((string) ($values['network'] ?? ''));
        $network = GsmSimInventory::canonicalNetwork($postedNetwork);

        if ($mobiles === []) {
            if ($postedGateway !== '' || $postedPorts !== [] || $postedNetwork !== '') {
                $errors[] = ProgramInboundNumberValidator::GSM_LANDLINE_ONLY;
            }
        } else {
            if ($postedNetwork !== '' && $network === null) {
                $errors[] = ProgramInboundNumberValidator::NETWORK_MUST_MATCH;
            }

            if ($network === null) {
                foreach ($mobiles as $mobile) {
                    foreach (GsmSimInventory::networks() as $candidate) {
                        $resolved = ProgramInboundSimLookup::resolve($candidate, $mobile);
                        if ($resolved['media_gateway_id'] || $resolved['hostname'] !== '' || self::mobileExistsOnNetwork($candidate, $mobile)) {
                            if ($network !== null && $network !== $candidate) {
                                $errors[] = ProgramInboundNumberValidator::NETWORK_MUST_MATCH;
                                $network = null;
                                break 2;
                            }
                            $network = $candidate;
                        }
                    }
                }
            }

            if ($network === null) {
                $errors[] = ProgramInboundNumberValidator::NETWORK_REQUIRED;
            }

            $assignments = $network ? ProgramInboundSimLookup::resolveMany($network, $mobiles) : [];
            $providedGateway = $postedGateway !== '' ? ProgramInboundSimLookup::findGateway($postedGateway) : null;
            if ($postedGateway !== '' && ! $providedGateway) {
                $errors[] = ProgramInboundNumberValidator::GSM_MUST_EXIST;
            }

            foreach ($assignments as $index => $row) {
                if ($providedGateway && $row['media_gateway_id'] && (int) $row['media_gateway_id'] !== (int) $providedGateway->id) {
                    $errors[] = ProgramInboundNumberValidator::GSM_MUST_EXIST;
                }
                if ($providedGateway && ! $row['media_gateway_id']) {
                    $errors[] = ProgramInboundNumberValidator::GSM_MUST_EXIST;
                }
                $postedPort = $postedPorts[$index] ?? ($postedPorts[0] ?? '');
                if ($postedPort !== '' && $row['port'] !== '' && strcasecmp($postedPort, $row['port']) !== 0) {
                    $errors[] = ProgramInboundNumberValidator::PORT_MUST_MATCH;
                }
                if ($postedPort !== '' && $row['port'] === '') {
                    $errors[] = ProgramInboundNumberValidator::PORT_MUST_MATCH;
                }
            }
        }

        $errors = array_values(array_unique($errors));
        if ($errors !== [] || ! $campaign) {
            return ['errors' => $errors, 'record' => null];
        }

        $assignments = $mobiles === [] ? [] : ProgramInboundSimLookup::resolveMany($network, $mobiles);
        $linked = collect($assignments)->first(static fn (array $row) => ! empty($row['media_gateway_id']));

        return [
            'errors' => [],
            'record' => [
                'campaign_id' => $campaign->id,
                'program' => $campaign->name,
                'number' => $mobiles[0] ?? $landlines[0] ?? '',
                'mobile_numbers' => $mobiles === [] ? null : $mobiles,
                'mobile_assignments' => $assignments === [] ? null : $assignments,
                'landline_numbers' => $landlines === [] ? null : $landlines,
                'media_gateway_id' => $linked['media_gateway_id'] ?? null,
                'port' => $linked['port'] ?? null,
                'network' => $mobiles === [] ? null : $network,
                'remarks' => trim((string) ($values['remarks'] ?? '')) ?: null,
                'status' => 'Active',
            ],
        ];
    }

    private static function mobileExistsOnNetwork(string $network, string $mobile): bool
    {
        foreach (ProgramInboundSimLookup::payload()[$network] ?? [] as $row) {
            if (($row['mobile'] ?? '') === $mobile) {
                return true;
            }
        }

        return false;
    }
}
