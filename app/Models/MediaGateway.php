<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MediaGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_name',
        'site_code',
        'ip_address',
        'username',
        'database',
    ];
}
