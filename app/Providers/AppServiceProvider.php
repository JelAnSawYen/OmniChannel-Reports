<?php

namespace App\Providers;

use App\Models\MailSetting;
use App\View\Composers\AppLayoutComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->runningInConsole() && empty($_ENV['SERVER_PORT'] ?? getenv('SERVER_PORT'))) {
            $_ENV['SERVER_PORT'] = '8011';
            putenv('SERVER_PORT=8011');
        }

        // `php artisan serve` strips most env vars from the PHP built-in server
        // worker. TMP/TEMP must be passed through or Windows falls back to
        // C:\WINDOWS and every multipart upload fails at request startup.
        $tempDir = $this->app->storagePath('framework'.DIRECTORY_SEPARATOR.'php-tmp');
        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }
        if (is_dir($tempDir) && is_writable($tempDir)) {
            foreach (['TMP', 'TEMP', 'TMPDIR'] as $key) {
                $_ENV[$key] = $tempDir;
                $_SERVER[$key] = $tempDir;
                putenv($key.'='.$tempDir);
            }
        }

        ServeCommand::$passthroughVariables = array_values(array_unique(array_merge(
            ServeCommand::$passthroughVariables,
            ['TMP', 'TEMP', 'TMPDIR']
        )));
    }

    public function boot(): void
    {
        View::composer('layouts.app', AppLayoutComposer::class);

        if (! $this->app->environment('testing') && Schema::hasTable('mail_settings')) {
            $settings = MailSetting::query()->first();
            if ($settings && filled($settings->username)) {
                $settings->applyToConfig();
            }
        }

        RateLimiter::for('login', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));

            return Limit::perMinute(5)->by($request->ip().'|'.$email);
        });

        RateLimiter::for('mfa', function (Request $request) {
            return Limit::perMinute(5)->by(($request->user()?->id ?? $request->ip()).'|mfa');
        });

        RateLimiter::for('sensitive', function (Request $request) {
            return Limit::perMinute(6)->by(($request->user()?->id ?? $request->ip()).'|sensitive|'.$request->path());
        });
    }
}
