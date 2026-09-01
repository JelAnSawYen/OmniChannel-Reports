<?php
use App\Console\Commands\BackupDatabase;
use App\Console\Commands\PruneOldLogs;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () { $this->comment(Inspiring::quote()); })->purpose('Display an inspiring quote');

app(Schedule::class)->command(BackupDatabase::class)->dailyAt('23:00');
app(Schedule::class)->command(PruneOldLogs::class)->dailyAt('00:15');
