<?php

namespace App\Models;

use App\Support\InventoryDependentSync;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SmartSim extends Model
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
        return $this->hasOne(GatewaySimAssignment::class, 'sim_id')->where('sim_type', 'smart');
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
        static::creating(function (SmartSim $sim): void {
            if ($sim->network === null || $sim->network === '') {
                $sim->network = 'Smart';
            }
        });
        static::saved(function (SmartSim $sim): void {
            InventoryDependentSync::simSaved($sim);
        });
        static::deleted(function (SmartSim $sim): void {
            InventoryDependentSync::simDeleted($sim);
        });
    }
}
