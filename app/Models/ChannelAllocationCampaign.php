<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChannelAllocationCampaign extends Model
{
    protected $fillable = [
        'name',
        'media_gateway',
        'total_channels_allocated',
        'fte',
        'caller_id',
        'prefix',
        'remarks',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'total_channels_allocated' => 'integer',
            'fte' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ChannelAllocation::class, 'campaign_id')->orderBy('sort_order')->orderBy('id');
    }

    public function refreshTotalChannelsAllocated(): int
    {
        $sum = (int) $this->allocations()->sum('total_channel_allocated');
        $this->forceFill(['total_channels_allocated' => $sum])->save();

        return $sum;
    }
}
