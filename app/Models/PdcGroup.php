<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PdcGroup extends Model
{
    protected $fillable = [
        'campaign_id',
        'location',
        'date_endorse',
        'dns',
    ];

    protected function casts(): array
    {
        return [
            'date_endorse' => 'date',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(ChannelAllocationCampaign::class, 'campaign_id');
    }

    public function servers(): HasMany
    {
        return $this->hasMany(PdcServer::class, 'pdc_group_id')->orderBy('id');
    }
}
