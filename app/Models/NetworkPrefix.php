<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class NetworkPrefix extends Model { protected $fillable=['network','prefix','gateway','status','description']; }
