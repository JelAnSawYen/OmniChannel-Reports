<?php

namespace App\Console\Commands;

use App\Services\Admin\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'system:backup';

    protected $description = 'Create a timestamped backup of the active application database';

    public function handle(DatabaseBackupService $backups): int
    {
        try {
            $name = $backups->create();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Backup created: '.$name);

        return self::SUCCESS;
    }
}
