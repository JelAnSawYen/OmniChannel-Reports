<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
