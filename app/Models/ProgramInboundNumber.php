<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProgramInboundNumber extends Model
{
    protected $fillable = ['number', 'program', 'location', 'assigned_channel', 'status'];
}
