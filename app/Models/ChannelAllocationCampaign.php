<?php

namespace App\Models;

use App\Support\InventoryDependentSync;
use App\Support\NaturalSort;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChannelAllocationCampaign extends Model
{
    protected $fillable = [
        'name',
        'media_gateway',
        'total_channels_allocated',
        'fte',
        'location',
        'caller_id',
        'prefix',
        'remarks',
        'sort_order',
        'listed_in_channel_allocation',
        'listed_in_campaigns',
    ];

    protected function casts(): array
    {
        return [
            'total_channels_allocated' => 'integer',
            'fte' => 'integer',
            'sort_order' => 'integer',
            'listed_in_channel_allocation' => 'boolean',
            'listed_in_campaigns' => 'boolean',
        ];
    }

    /**
     * Master Campaign membership. Campaigns created by PDC Servers are the only
     * ones flagged false, so they never reach the Campaigns page.
     */
    public function scopeListedInCampaigns($query)
    {
        if (! Schema::hasColumn($this->getTable(), 'listed_in_campaigns')) {
            return $query;
        }

        return $query->where('listed_in_campaigns', true);
    }

    public function isListedInCampaigns(): bool
    {
        if (! Schema::hasColumn($this->getTable(), 'listed_in_campaigns')) {
            return true;
        }

        return (bool) $this->listed_in_campaigns;
    }

    public function scopeListedInChannelAllocation($query)
    {
        if (! Schema::hasColumn($this->getTable(), 'listed_in_channel_allocation')) {
            return $query;
        }

        return $query->where('listed_in_channel_allocation', true);
    }

    public function markListedInChannelAllocation(): void
    {
        if (! Schema::hasColumn($this->getTable(), 'listed_in_channel_allocation')) {
            return;
        }

        if ($this->listed_in_channel_allocation) {
            return;
        }

        $this->forceFill(['listed_in_channel_allocation' => true])->saveQuietly();
    }

    /**
     * Remove this campaign from Channel Allocation without deleting the master Campaign.
     */
    public function removeFromChannelAllocation(): void
    {
        DB::transaction(function () {
            $campaign = static::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();
            $campaign->allocations()->delete();
            $payload = ['total_channels_allocated' => 0];
            if (Schema::hasColumn($campaign->getTable(), 'listed_in_channel_allocation')) {
                $payload['listed_in_channel_allocation'] = false;
            }
            $campaign->forceFill($payload)->saveQuietly();
            $this->forceFill($payload);
        });
    }

    /**
     * Master Campaign dropdown options used across the app.
     *
     * @return Collection<int, $this>
     */
    public static function optionsForDropdown(): Collection
    {
        $query = static::query();
        NaturalSort::apply($query, 'name');

        return $query->get(['id', 'name', 'fte']);
    }

    /**
     * Campaigns listed on the Campaigns page, for pickers that may only offer a
     * master Campaign.
     *
     * @return Collection<int, $this>
     */
    public static function masterOptionsForDropdown(): Collection
    {
        $query = static::query()->listedInCampaigns();
        NaturalSort::apply($query, 'name');

        return $query->get(['id', 'name', 'fte']);
    }

    /**
     * Master Campaign lookup keyed by lowercase name for imports and references.
     *
     * @return Collection<string, $this>
     */
    public static function keyedByName(): Collection
    {
        return static::query()
            ->get(['id', 'name', 'fte'])
            ->keyBy(fn (self $campaign) => mb_strtolower((string) $campaign->name));
    }

    /**
     * Case-insensitive lookup restricted to the Campaigns page, for the pages
     * that may only reference a campaign the master list already has.
     */
    public static function masterByName(string $name): ?self
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        return static::query()
            ->listedInCampaigns()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();
    }

    public static function fromFormValue(?string $name, mixed $id = null, bool $listedInCampaigns = true): ?self
    {
        $name = trim((string) $name);
        if ($name !== '') {
            return static::findOrCreateByName($name, $listedInCampaigns);
        }

        $id = (int) $id;
        if ($id < 1) {
            return null;
        }

        return static::query()->find($id);
    }

    /**
     * An existing campaign is reused exactly as it is; $listedInCampaigns only
     * decides whether a newly created campaign joins the Campaigns page.
     */
    public static function findOrCreateByName(string $name, bool $listedInCampaigns = true): self
    {
        $name = trim($name);

        return DB::transaction(function () use ($name, $listedInCampaigns) {
            $existing = static::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            $attributes = ['name' => $name];
            if (! $listedInCampaigns && Schema::hasColumn((new static)->getTable(), 'listed_in_campaigns')) {
                $attributes['listed_in_campaigns'] = false;
            }

            try {
                return static::query()->create($attributes);
            } catch (UniqueConstraintViolationException) {
                return static::query()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->firstOrFail();
            }
        });
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ChannelAllocation::class, 'campaign_id')->orderBy('sort_order')->orderBy('id');
    }

    public function pdcGroups(): HasMany
    {
        return $this->hasMany(PdcGroup::class, 'campaign_id');
    }

    public function archiveRecordings(): HasMany
    {
        return $this->hasMany(ArchiveRecording::class, 'campaign_id');
    }

    public function sipChannels(): HasMany
    {
        return $this->hasMany(SipChannel::class, 'campaign_id');
    }

    protected static function booted(): void
    {
        static::updated(function (self $campaign): void {
            InventoryDependentSync::campaignSaved($campaign);
        });
        static::deleting(function (self $campaign): void {
            $campaign->sipChannels()->get()->each(function (SipChannel $sip): void {
                $sip->delete();
            });
        });
    }

    public function refreshTotalChannelsAllocated(): int
    {
        return DB::transaction(function () {
            $campaign = static::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();
            $sum = (int) $campaign->allocations()->sum('total_channel_allocated');
            $campaign->forceFill(['total_channels_allocated' => $sum])->save();
            $this->forceFill(['total_channels_allocated' => $sum]);

            return $sum;
        });
    }
}
