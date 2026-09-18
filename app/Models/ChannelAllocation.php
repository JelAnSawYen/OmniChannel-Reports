<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelAllocation extends Model
{
    protected $fillable = [
        'campaign_id',
        'media_gateway',
        'channel_allocation',
        'network',
        'line_priority',
        'total_channel_allocated',
        'remarks',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'line_priority' => 'integer',
            'total_channel_allocated' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(ChannelAllocationCampaign::class, 'campaign_id');
    }

    public function channelLabel(): string
    {
        $channel = trim((string) $this->channel_allocation);
        if ($channel !== '') {
            return $channel;
        }

        $gateway = trim((string) $this->media_gateway);

        return $gateway !== '' ? $gateway : '—';
    }

    public function channelType(): string
    {
        $channel = trim((string) $this->channel_allocation);
        $gateway = trim((string) $this->media_gateway);
        if ($gateway !== '' && ($channel === '' || strcasecmp($channel, $gateway) === 0)) {
            return 'gsm';
        }

        return 'sip';
    }
}
