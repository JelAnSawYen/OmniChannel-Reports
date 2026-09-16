<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GatewaySimAssignment extends Model
{
    protected $fillable = [
        'media_gateway_id',
        'sim_type',
        'sim_id',
        'port',
    ];

    protected $casts = [
        'port' => 'integer',
        'sim_id' => 'integer',
    ];

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(MediaGateway::class, 'media_gateway_id');
    }

    public function sim(): GlobeSim|SmartSim|null
    {
        if ($this->sim_type === 'smart') {
            return SmartSim::query()->find($this->sim_id);
        }

        return GlobeSim::query()->find($this->sim_id);
    }
}
