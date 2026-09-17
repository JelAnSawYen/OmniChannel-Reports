<?php

namespace App\Services;

use App\Models\ChannelPort;
use App\Models\LoginLog;
use App\Models\TelcoCost;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class OperationalAlerts
{
    public static function forCurrentUser(): array
    {
        $user = Auth::user();
        $failedLogins = 0;
        $expiringContracts = 0;
        $items = [];

        if ($user?->hasPermission('logs.view')) {
            $failedLogins = LoginLog::query()
                ->whereIn('status', ['Failed', 'Failed Login', 'Blocked'])
                ->where('created_at', '>=', now()->subDay())
                ->count();
            if ($failedLogins > 0) {
                $items[] = [
                    'count' => $failedLogins,
                    'label' => 'Failed login attempt(s) in the last 24 hours.',
                    'tone' => 'offline',
                    'href' => route('login-history'),
                ];
            }
        }

        if ($user?->hasPermission('media.view')) {
            $expiringContracts = TelcoCost::query()
                ->whereNotNull('contract_end')
                ->whereBetween('contract_end', [now()->toDateString(), now()->addDays(30)->toDateString()])
                ->count();
            if ($expiringContracts > 0) {
                $items[] = [
                    'count' => $expiringContracts,
                    'label' => 'Telco contract(s) expiring within 30 days.',
                    'tone' => 'unknown',
                    'href' => route('telco-cost'),
                ];
            }
        }

        return [
            'failedLogins' => $failedLogins,
            'expiringContracts' => $expiringContracts,
            'badge' => $failedLogins + $expiringContracts,
            'items' => $items,
        ];
    }

    public static function portCapacity(): array
    {
        $total = ChannelPort::count();
        $available = ChannelPort::where('status', 'Available')->count();
        $inUse = ChannelPort::where('status', 'In Use')->count();
        $disabled = ChannelPort::where('status', 'Disabled')->count();

        return [
            'total' => $total,
            'available' => $available,
            'in_use' => $inUse,
            'disabled' => $disabled,
            'utilization' => $total > 0 ? (int) round(($inUse / $total) * 100) : 0,
        ];
    }

    public static function latestBackupName(): ?string
    {
        $backups = collect(Storage::files('backups'))
            ->filter(fn ($path) => str_ends_with($path, '.sqlite') && str_contains(basename($path), 'backup_'))
            ->sortDesc()
            ->values();

        return $backups->isEmpty() ? null : basename((string) $backups->first());
    }
}
