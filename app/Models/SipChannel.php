<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

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

        $starts = $bounds->pluck(0)->sortBy(fn ($number) => (int) self::numericKey((string) $number))->values();
        $ends = $bounds->pluck(1)->sortBy(fn ($number) => (int) self::numericKey((string) $number))->values();

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

        $sorted = $values->sortBy(fn ($number) => (int) self::numericKey((string) $number))->values();

        return $sorted->first().' - '.$sorted->last();
    }

    public static function numericKey(string $number): string
    {
        return preg_replace('/\D+/', '', $number) ?? '';
    }

    public static function formatRangeFromBounds(?string $from, ?string $to): ?string
    {
        $from = trim((string) $from);
        $to = trim((string) $to);
        if ($from === '' && $to === '') {
            return null;
        }
        if ($from === '' || $to === '') {
            throw ValidationException::withMessages([
                'from' => 'From and To are both required.',
            ]);
        }

        return $from.' - '.$to;
    }

    /**
     * Expand From/To into stored channel numbers while preserving spaces in each value.
     *
     * @return list<string>
     */
    public static function expandRangeNumbers(string $from, string $to): array
    {
        $from = trim($from);
        $to = trim($to);
        $fromDigits = self::numericKey($from);
        $toDigits = self::numericKey($to);
        if ($fromDigits === '' || $toDigits === '') {
            throw ValidationException::withMessages([
                'from' => 'From and To must contain numbers.',
            ]);
        }

        $length = max(strlen($fromDigits), strlen($toDigits));
        $fromDigits = str_pad($fromDigits, $length, '0', STR_PAD_LEFT);
        $toDigits = str_pad($toDigits, $length, '0', STR_PAD_LEFT);
        if ($toDigits < $fromDigits) {
            throw ValidationException::withMessages([
                'to' => 'To must be greater than or equal to From.',
            ]);
        }

        $count = ((int) $toDigits) - ((int) $fromDigits) + 1;
        if ($count > 10000) {
            throw ValidationException::withMessages([
                'to' => 'The range cannot exceed 10000 channel numbers.',
            ]);
        }

        $numbers = [];
        $current = $fromDigits;
        for ($index = 0; $index < $count; $index++) {
            $numbers[] = self::applyNumberPattern($from, $current);
            $current = str_pad((string) (((int) $current) + 1), $length, '0', STR_PAD_LEFT);
        }

        return $numbers;
    }

    public function syncChannelNumbersFromRange(): void
    {
        $range = trim((string) $this->channel_range);
        if ($range === '') {
            $this->channelNumbers()->delete();

            return;
        }

        [$from, $to] = self::boundsFromRange($range);
        $numbers = self::expandRangeNumbers($from, $to);
        $digitSet = array_fill_keys(array_map(fn (string $number) => self::numericKey($number), $numbers), true);

        $conflicts = SipChannelNumber::query()
            ->where('sip_channel_id', '!=', $this->id)
            ->pluck('channel_number')
            ->filter(function ($existing) use ($numbers, $digitSet) {
                $existing = (string) $existing;

                return in_array($existing, $numbers, true)
                    || isset($digitSet[self::numericKey($existing)]);
            })
            ->unique()
            ->values();

        if ($conflicts->isNotEmpty()) {
            throw ValidationException::withMessages([
                'from' => 'Channel number already exists: '.$conflicts->take(8)->implode(', '),
            ]);
        }

        $this->channelNumbers()->delete();
        foreach ($numbers as $number) {
            SipChannelNumber::query()->create([
                'sip_channel_id' => $this->id,
                'channel_number' => $number,
            ]);
        }
    }

    private static function applyNumberPattern(string $pattern, string $digits): string
    {
        $result = '';
        $index = 0;
        $length = strlen($digits);
        $patternLength = strlen($pattern);
        for ($position = 0; $position < $patternLength; $position++) {
            $character = $pattern[$position];
            if ($character >= '0' && $character <= '9') {
                $result .= $index < $length ? $digits[$index++] : $character;
            } else {
                $result .= $character;
            }
        }
        if ($index < $length) {
            $result .= substr($digits, $index);
        }

        return $result;
    }
}
