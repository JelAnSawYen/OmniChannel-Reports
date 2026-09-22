<?php

namespace App\Models;

use App\Support\GsmSimInventory;
use App\Support\InventoryDependentSync;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GlobeSim extends Model
{
    protected $fillable = [
        'imei',
        'mobile_number',
        'network',
        'plan',
        'ip_address',
        'port',
        'account_number',
        'contract_start',
        'contract_end',
        'remarks',
        'location',
        'status',
    ];

    protected $casts = [
        'port' => 'integer',
        'contract_start' => 'date',
        'contract_end' => 'date',
    ];

    public function gatewayAssignment(): HasOne
    {
        return $this->hasOne(GatewaySimAssignment::class, 'sim_id')->where('sim_type', 'globe');
    }

    public function displayHostname(): string
    {
        $hostname = GsmSimInventory::hostnameForSim($this);

        return $hostname !== '' ? $hostname : '—';
    }

    public function displayIp(): string
    {
        $ip = $this->gatewayAssignment?->gateway?->ip_address ?: $this->ip_address;

        return $ip !== null && $ip !== '' ? (string) $ip : '—';
    }

    public function displayPort(): string
    {
        $port = $this->port;

        return $port ? (string) $port : '—';
    }

    protected static function booted(): void
    {
        static::creating(function (GlobeSim $sim): void {
            if ($sim->network === null || $sim->network === '') {
                $sim->network = 'Globe SIM';
            }
        });
        static::saved(function (GlobeSim $sim): void {
            InventoryDependentSync::simSaved($sim);
        });
        static::deleted(function (GlobeSim $sim): void {
            InventoryDependentSync::simDeleted($sim);
        });
    }
}
