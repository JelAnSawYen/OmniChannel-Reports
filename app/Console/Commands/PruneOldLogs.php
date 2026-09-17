<?php

namespace App\Console\Commands;

use App\Services\Logs\LogRetentionService;
use Illuminate\Console\Command;

class PruneOldLogs extends Command
{
    protected $signature = 'logs:prune';

    protected $description = 'Delete activity logs and login history older than 14 days';

    public function handle(): int
    {
        $result = LogRetentionService::prune();
        $this->info('Removed '.$result['activity'].' activity logs and '.$result['login'].' login history records older than '.LogRetentionService::DAYS.' days.');

        return self::SUCCESS;
    }
}
