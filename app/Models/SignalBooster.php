<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SignalBooster extends Model
{
    protected $fillable = ['model', 'specs', 'serial_number', 'location', 'status'];
}
