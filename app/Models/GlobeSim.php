<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GlobeSim extends Model
{
    protected $fillable = ['sim_number', 'imsi', 'assigned_to', 'location', 'status'];
}
