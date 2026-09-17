<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelcoCost extends Model
{
    protected $fillable = ['provider', 'site', 'service_type', 'monthly_cost', 'contract_start', 'contract_end', 'status'];

    protected $casts = ['monthly_cost' => 'decimal:2', 'contract_start' => 'date', 'contract_end' => 'date'];
}
