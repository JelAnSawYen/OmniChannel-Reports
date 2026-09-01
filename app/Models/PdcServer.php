<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PdcServer extends Model
{
    protected $fillable = ['hostname', 'ip_address', 'location', 'role', 'status'];
}
