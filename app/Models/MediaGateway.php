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
        'plan',
        'port',
        'network',
        'device_function',
        'username',
        'password',
        'database',
    ];

    protected static function booted(): void
    {
        static::creating(function (MediaGateway $gateway): void {
            if ($gateway->database === null) {
                $gateway->database = '';
            }
        });
    }
}
