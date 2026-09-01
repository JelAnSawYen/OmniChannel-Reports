<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
class BackupDatabase extends Command
{
    protected $signature='system:backup';
    protected $description='Create a timestamped SQLite database backup';
    public function handle(): int
    {
        $source=database_path('database.sqlite');
        if(!is_file($source)){ $this->error('SQLite database file not found.'); return self::FAILURE; }
        Storage::makeDirectory('backups');
        $name='backups/backup_'.now()->format('Y-m-d_H-i-s').'.sqlite';
        Storage::put($name,file_get_contents($source));
        $this->info('Backup created: '.$name);
        return self::SUCCESS;
    }
}
