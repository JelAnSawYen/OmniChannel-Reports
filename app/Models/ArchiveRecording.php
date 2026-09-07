<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchiveRecording extends Model
{
    protected $fillable = [
        'campaign_id',
        'file_name',
        'called_at',
        'caller_number',
        'agent_number',
        'duration',
        'server',
        'storage_path',
        'retention_days',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'called_at' => 'datetime',
            'retention_days' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(ChannelAllocationCampaign::class, 'campaign_id');
    }

    public function calledAtDisplay(): string
    {
        return $this->called_at ? $this->called_at->format('m/d/Y H:i:s') : '—';
    }

    public function durationDisplay(): string
    {
        $value = trim((string) $this->duration);
        if ($value === '') {
            return '—';
        }
        if (preg_match('/^\d+$/', $value) === 1) {
            $seconds = (int) $value;

            return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
        }

        return $value;
    }
}
