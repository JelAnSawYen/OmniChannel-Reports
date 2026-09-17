<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DefectiveGsm extends Model
{
    protected $fillable = ['asset_code', 'location', 'issue', 'reported_on', 'status'];

    protected $casts = ['reported_on' => 'date'];
}
