<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlobeSim extends Model
{
    protected $fillable = [
        'imei',
        'mobile_number',
        'network',
        'plan',
        'ip_address',
        'account_number',
        'contract_start',
        'contract_end',
        'location',
        'status',
    ];

    protected $casts = [
        'contract_start' => 'date',
        'contract_end' => 'date',
    ];
}
