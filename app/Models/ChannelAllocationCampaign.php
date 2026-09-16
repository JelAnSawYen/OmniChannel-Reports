<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

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
    ];

    protected function casts(): array
    {
        return [
            'total_channels_allocated' => 'integer',
            'fte' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Master Campaign dropdown options used across the app.
     *
     * @return Collection<int, $this>
     */
    public static function optionsForDropdown(): Collection
    {
        return static::query()->orderBy('name')->get(['id', 'name', 'fte']);
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

    public static function fromFormValue(?string $name, mixed $id = null): ?self
    {
        $name = trim((string) $name);
        if ($name !== '') {
            return static::findOrCreateByName($name);
        }

        $id = (int) $id;
        if ($id < 1) {
            return null;
        }

        return static::query()->find($id);
    }

    public static function findOrCreateByName(string $name): self
    {
        $name = trim($name);

        return DB::transaction(function () use ($name) {
            $existing = static::query()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            try {
                return static::query()->create(['name' => $name]);
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

    public function refreshTotalChannelsAllocated(): int
    {
        return DB::transaction(function () {
            $campaign = static::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();
            $sum = (int) $campaign->allocations()->lockForUpdate()->sum('total_channel_allocated');
            $campaign->forceFill(['total_channels_allocated' => $sum])->save();
            $this->forceFill(['total_channels_allocated' => $sum]);

            return $sum;
        });
    }
}
