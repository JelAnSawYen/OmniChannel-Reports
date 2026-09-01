<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

class MailSetting extends Model
{
    protected $table = 'mail_settings';

    protected $fillable = [
        'host',
        'port',
        'username',
        'password',
        'from_address',
        'from_name',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'port' => 'integer',
        ];
    }

    public static function current(): self
    {
        $row = static::query()->first();
        if ($row) {
            return $row;
        }

        $model = new static;
        $model->host = (string) env('MAIL_HOST', 'smtp.gmail.com');
        $model->port = (int) env('MAIL_PORT', 587);
        $model->username = (string) env('MAIL_USERNAME', '');
        $model->from_address = (string) env('MAIL_FROM_ADDRESS', '');
        $model->from_name = (string) env('MAIL_FROM_NAME', 'OmniChannel Reports');

        return $model;
    }

    public function applyToConfig(): void
    {
        $port = (int) ($this->port ?: 587);
        $host = $this->host ?: 'smtp.gmail.com';

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.transport', 'smtp');
        Config::set('mail.mailers.smtp.host', $host);
        Config::set('mail.mailers.smtp.port', $port);
        Config::set('mail.mailers.smtp.scheme', $port === 465 ? 'smtps' : 'smtp');
        Config::set('mail.mailers.smtp.username', $this->username);
        if (filled($this->password)) {
            Config::set('mail.mailers.smtp.password', $this->password);
        }
        Config::set('mail.from.address', $this->from_address);
        Config::set('mail.from.name', $this->from_name ?: 'OmniChannel Reports');

        app()->forgetInstance('mail.manager');
        Mail::clearResolvedInstances();
    }

    public static function isConfigured(): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        $mailer = (string) config('mail.default');
        if (in_array($mailer, ['log', 'array'], true)) {
            return $mailer === 'array';
        }

        $username = (string) config('mail.mailers.smtp.username');
        $password = (string) config('mail.mailers.smtp.password');
        $from = (string) config('mail.from.address');

        return filled($username)
            && filled($password)
            && filled($from)
            && ! str_contains($from, 'example.com');
    }
}
