<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SipChannel extends Model
{
    protected $fillable = [
        'campaign_id',
        'etpi_sip_name',
        'pilot_number',
        'channel_count',
        'channel_range',
        'network',
        'date_activation',
    ];

    protected function casts(): array
    {
        return [
            'channel_count' => 'integer',
            'date_activation' => 'date',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(ChannelAllocationCampaign::class, 'campaign_id');
    }

    public function channelNumbers(): HasMany
    {
        return $this->hasMany(SipChannelNumber::class)->orderBy('channel_number');
    }

    public function channelRangeFromNumbers(): string
    {
        return self::formatChannelRange($this->channelNumbers->pluck('channel_number'));
    }

    public function resolvedChannelRange(): string
    {
        $fromNumbers = $this->channelRangeFromNumbers();
        if ($fromNumbers !== '') {
            return $fromNumbers;
        }

        return trim((string) $this->channel_range);
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function boundsFromRange(?string $range): array
    {
        $range = trim((string) $range);
        if ($range === '') {
            return ['', ''];
        }
        if (preg_match('/^(.+?)\s+-\s+(.+)$/', $range, $matches) === 1) {
            return [trim($matches[1]), trim($matches[2])];
        }

        return [$range, $range];
    }

    public static function combinedRangeForSips(iterable $sips): string
    {
        $sips = collect($sips);
        $fromNumbers = self::formatChannelRange(
            $sips->flatMap(fn (self $sip) => $sip->channelNumbers->pluck('channel_number'))
        );
        if ($fromNumbers !== '') {
            return $fromNumbers;
        }

        $bounds = $sips
            ->map(fn (self $sip) => self::boundsFromRange($sip->channel_range))
            ->filter(fn (array $pair) => $pair[0] !== '' && $pair[1] !== '');
        if ($bounds->isEmpty()) {
            return '';
        }

        $starts = $bounds->pluck(0)->sortBy(fn ($number) => (int) $number)->values();
        $ends = $bounds->pluck(1)->sortBy(fn ($number) => (int) $number)->values();

        return $starts->first().' - '.$ends->last();
    }

    public static function formatChannelRange(iterable $numbers): string
    {
        $values = collect($numbers)
            ->map(fn ($number) => is_object($number) ? ($number->channel_number ?? '') : $number)
            ->filter(fn ($number) => $number !== null && $number !== '')
            ->values();

        if ($values->isEmpty()) {
            return '';
        }

        $sorted = $values->sortBy(fn ($number) => (int) $number)->values();

        return $sorted->first().' - '.$sorted->last();
    }
}
