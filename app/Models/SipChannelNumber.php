<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SipChannelNumber extends Model
{
    protected $fillable = [
        'sip_channel_id',
        'channel_number',
    ];

    public function sipChannel(): BelongsTo
    {
        return $this->belongsTo(SipChannel::class);
    }
}
