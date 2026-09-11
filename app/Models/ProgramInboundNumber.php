<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramInboundNumber extends Model
{
    protected $fillable = [
        'number',
        'program',
        'location',
        'assigned_channel',
        'status',
        'campaign_id',
        'mobile_numbers',
        'landline_numbers',
        'media_gateway_id',
        'port',
        'network',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'mobile_numbers' => 'array',
            'landline_numbers' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(ChannelAllocationCampaign::class, 'campaign_id');
    }

    public function mediaGateway(): BelongsTo
    {
        return $this->belongsTo(MediaGateway::class, 'media_gateway_id');
    }

    public function campaignLabel(): string
    {
        $name = trim((string) ($this->campaign?->name ?: $this->program ?: ''));

        return $name !== '' ? $name : '—';
    }

    /**
     * @return list<string>
     */
    public function mobileList(): array
    {
        $mobiles = $this->numberList($this->mobile_numbers);
        if ($mobiles !== []) {
            return $mobiles;
        }
        if ($this->numberList($this->landline_numbers) !== []) {
            return [];
        }

        return $this->numberList(null, $this->number);
    }

    /**
     * @return list<string>
     */
    public function landlineList(): array
    {
        return $this->numberList($this->landline_numbers);
    }

    public function gatewayLabel(): string
    {
        return $this->displayValue($this->mediaGateway?->ip_address);
    }

    public function portLabel(): string
    {
        return $this->displayValue($this->port ?: $this->mediaGateway?->port);
    }

    public function networkLabel(): string
    {
        return $this->displayValue($this->network ?: $this->mediaGateway?->network);
    }

    public function remarksLabel(): string
    {
        return $this->displayValue($this->remarks);
    }

    /**
     * @param  mixed  $stored
     * @return list<string>
     */
    private function numberList(mixed $stored, ?string $legacy = null): array
    {
        $items = [];
        if (is_array($stored)) {
            $items = $stored;
        } elseif (is_string($stored) && trim($stored) !== '') {
            $items = preg_split('/\r\n|\n|,/', $stored) ?: [];
        }

        $items = array_values(array_filter(array_map(
            static fn ($number) => trim((string) $number),
            $items
        ), static fn ($number) => $number !== ''));

        if ($items === [] && $legacy !== null) {
            $legacy = trim($legacy);
            if ($legacy !== '') {
                return [$legacy];
            }
        }

        return $items;
    }

    private function displayValue(mixed $value): string
    {
        $text = trim((string) $value);

        return $text !== '' ? $text : '—';
    }
}
