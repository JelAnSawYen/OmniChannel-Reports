<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdcServer extends Model
{
    protected $fillable = [
        'pdc_group_id',
        'hostname',
        'ip_address',
        'location',
        'role',
        'status',
        'os',
        'ram',
        'cpu',
        'storage',
        'admin_username',
        'password',
        'sql_db_password',
    ];

    protected $hidden = [
        'password',
        'sql_db_password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'sql_db_password' => 'encrypted',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(PdcGroup::class, 'pdc_group_id');
    }
}
