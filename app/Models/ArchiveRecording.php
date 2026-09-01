<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ArchiveRecording extends Model
{
    protected $fillable = ['server', 'storage_path', 'retention_days', 'status'];
}
