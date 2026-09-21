<?php

namespace App\Support\Inbound;

use App\Models\ProgramInboundNumber;

class ProgramInboundNumberValidator
{
    public const CAMPAIGN_REQUIRED = 'Campaign is required';

    public const CAMPAIGN_MISSING = 'Campaign does not exist';

    public const NEED_NUMBER = 'Enter at least one Mobile or Landline number.';

    public const GSM_REQUIRED = 'Please select a GSM Gateway.';

    public const GSM_REQUIRED_WITH_MOBILE = 'GSM Gateway is required when Mobile numbers are entered.';

    public const GSM_MUST_EXIST = 'GSM Gateway must match the Mobile Number assignment.';

    public const PORT_MUST_MATCH = 'Port must match the Mobile Number assignment.';

    public const NETWORK_REQUIRED = 'Network is required when Mobile numbers are entered.';

    public const NETWORK_MUST_MATCH = 'Network must match Globe SIM or Smart SIM.';

    public const GSM_LANDLINE_ONLY = 'GSM Gateway, Port, and Network are only used when Mobile numbers are entered.';

    public const MOBILE_DIGITS = 'Mobile must contain only digits.';

    public const LANDLINE_DIGITS = 'Landline must contain only digits.';

    public const LANDLINE_MUST_EXIST = 'Landline must match an existing Channel Number.';

    public const NUMBER_DUPLICATED = 'Number is duplicated.';

    public const NUMBER_DUPLICATED_IN_FILE = 'Number is duplicated in the file';

    public const NUMBER_EXISTS = 'Number already exists';

    /**
     * @return list<string>
     */
    public static function normalize(mixed $values): array
    {
        if (is_string($values)) {
            $values = preg_split('/[\r\n,;]+/', $values) ?: [];
        }
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn ($value) => trim((string) $value),
            $values
        ), static fn ($value) => $value !== '')));
    }

    public static function isValidFormat(string $number): bool
    {
        return (bool) preg_match('/^[0-9]{1,50}$/', $number);
    }

    /**
     * @param  list<string>  $mobiles
     * @param  list<string>  $landlines
     * @return list<string>
     */
    public static function formatErrors(array $mobiles, array $landlines): array
    {
        $errors = [];
        foreach ($mobiles as $number) {
            if (! self::isValidFormat($number)) {
                $errors[] = self::MOBILE_DIGITS;
                break;
            }
        }
        foreach ($landlines as $number) {
            if (! self::isValidFormat($number)) {
                $errors[] = self::LANDLINE_DIGITS;
                break;
            }
        }

        return $errors;
    }

    /**
     * @param  list<string>  $landlines
     * @return list<string>
     */
    public static function landlineSourceErrors(array $landlines): array
    {
        foreach ($landlines as $number) {
            if (self::isValidFormat($number) && ! ProgramInboundSimLookup::isChannelNumber($number)) {
                return [self::LANDLINE_MUST_EXIST];
            }
        }

        return [];
    }

    /**
     * @param  list<string>  $mobiles
     * @param  list<string>  $landlines
     */
    public static function hasInternalDuplicate(array $mobiles, array $landlines): bool
    {
        $all = array_map(static fn ($number) => mb_strtolower($number), array_merge($mobiles, $landlines));

        return count($all) !== count(array_unique($all));
    }

    public static function exists(string $number, ?int $ignoreId = null, ?int $campaignId = null, string $field = 'any'): bool
    {
        $number = trim($number);
        if ($number === '' || ! $campaignId) {
            return false;
        }

        $query = ProgramInboundNumber::query();
        if ($campaignId) {
            $query->where('campaign_id', $campaignId);
        }
        if ($ignoreId) {
            $query->where('id', '<>', $ignoreId);
        }

        return $query->where(function ($inner) use ($number, $field) {
            if ($field === 'mobile') {
                $inner->whereJsonContains('mobile_numbers', $number);

                return;
            }
            if ($field === 'landline') {
                $inner->whereJsonContains('landline_numbers', $number);

                return;
            }

            $inner->whereRaw('LOWER(number) = ?', [mb_strtolower($number)])
                ->orWhereJsonContains('mobile_numbers', $number)
                ->orWhereJsonContains('landline_numbers', $number);
        })->exists();
    }

    /**
     * @param  list<string>  $mobiles
     * @param  list<string>  $landlines
     * @param  array<string, true>  $seen
     * @return list<string>
     */
    public static function uniquenessErrors(
        array $mobiles,
        array $landlines,
        array &$seen,
        ?int $ignoreId = null,
        bool $inFile = false,
        ?int $campaignId = null,
        string $campaignKey = ''
    ): array {
        $errors = [];
        if (self::hasInternalDuplicate($mobiles, $landlines)) {
            $errors[] = $inFile ? self::NUMBER_DUPLICATED_IN_FILE : self::NUMBER_DUPLICATED;
        }

        $scope = mb_strtolower(trim($campaignKey));
        foreach ($mobiles as $number) {
            $key = $scope.'|m|'.mb_strtolower($number);
            if (isset($seen[$key])) {
                $errors[] = $inFile ? self::NUMBER_DUPLICATED_IN_FILE : self::NUMBER_DUPLICATED;

                continue;
            }
            $seen[$key] = true;
            if (self::exists($number, $ignoreId, $campaignId, 'mobile')) {
                $errors[] = self::NUMBER_EXISTS;
            }
        }
        foreach ($landlines as $number) {
            $key = $scope.'|l|'.mb_strtolower($number);
            if (isset($seen[$key])) {
                $errors[] = $inFile ? self::NUMBER_DUPLICATED_IN_FILE : self::NUMBER_DUPLICATED;

                continue;
            }
            $seen[$key] = true;
            if (self::exists($number, $ignoreId, $campaignId, 'landline')) {
                $errors[] = self::NUMBER_EXISTS;
            }
        }

        return array_values(array_unique($errors));
    }
}
