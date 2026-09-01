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
}
