<?php

namespace App\Models;

use App\Support\NaturalSort;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PdcGroup extends Model
{
    protected $fillable = [
        'campaign_id',
        'campaign_name',
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

    /**
     * Name shown on PDC Servers. Master Campaign is used when this group is
     * linked; otherwise the name lives only on the PDC group.
     */
    public function campaignName(): string
    {
        $linked = trim((string) ($this->campaign?->name ?? ''));
        if ($linked !== '') {
            return $linked;
        }

        return trim((string) ($this->campaign_name ?? ''));
    }

    public function servers(): HasMany
    {
        $relation = $this->hasMany(PdcServer::class, 'pdc_group_id');
        NaturalSort::apply($relation->getQuery(), 'hostname');
        $relation->orderBy('id');

        return $relation;
    }
}
