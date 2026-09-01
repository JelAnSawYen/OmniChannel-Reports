<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SipChannel extends Model
{
    protected $fillable = ['channel', 'peer', 'context', 'codec', 'status'];
}
