<?php

namespace App\Support;

class ChannelTypeClassifier
{
    public const SIP = 'SIP';

    public const GSM = 'GSM';

    public const OTHER = 'Other';

    public static function classify(?string $network, ?string $channel = null): string
    {
        $hay = mb_strtolower(trim(($network ?? '').' '.($channel ?? '')));
        if ($hay === '') {
            return self::OTHER;
        }

        if (str_contains($hay, 'sip') || str_contains($hay, 'etpi')) {
            return self::SIP;
        }

        if (str_contains($hay, 'gsm')
            || str_contains($hay, 'sim')
            || preg_match('/\b(globe|smart|dito|tnt)\b/', $hay)) {
            return self::GSM;
        }

        return self::OTHER;
    }

    public static function isSip(?string $network, ?string $channel = null): bool
    {
        return self::classify($network, $channel) === self::SIP;
    }

    public static function isGsm(?string $network, ?string $channel = null): bool
    {
        return self::classify($network, $channel) === self::GSM;
    }
}
