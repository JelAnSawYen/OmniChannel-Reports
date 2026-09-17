<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChannelPrefix extends Model
{
    protected $fillable = ['prefix', 'channel', 'description', 'status'];
}
