<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\View\TaskResult;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'clear')]
class ClearCaches extends Command
{
    protected $signature = 'clear';

    protected $description = 'Clear cached configuration, routes, views, and application cache';

    public function handle(): int
    {
        $this->components->info('Clearing cached bootstrap files.');

        foreach ([
            'config' => 'config:clear',
            'compiled' => 'clear-compiled',
            'events' => 'event:clear',
            'routes' => 'route:clear',
            'views' => 'view:clear',
        ] as $label => $command) {
            $this->components->task($label, function () use ($command) {
                return $this->callSilently($command) === 0
                    ? TaskResult::Success->value
                    : TaskResult::Failure->value;
            });
        }

        $cacheCleared = false;
        try {
            $cacheCleared = $this->callSilently('cache:clear') === 0;
        } catch (\Throwable) {
            $cacheCleared = false;
        }
        $this->components->task('cache', fn () => $cacheCleared ? TaskResult::Success->value : TaskResult::Failure->value);

        if (! $cacheCleared) {
            $this->newLine();
            $this->components->warn('The application cache was not cleared because MySQL is not accepting connections on 127.0.0.1:3306. Start MySQL in XAMPP, then run php artisan clear again.');
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
