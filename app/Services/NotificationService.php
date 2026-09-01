<?php

namespace App\Services;

use App\Models\ArchiveRecording;
use App\Models\DefectiveGsm;
use App\Models\GlobeSim;
use App\Models\MediaGateway;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\PdcServer;
use App\Models\ProgramInboundNumber;
use App\Models\SignalBooster;
use App\Models\SipChannel;
use App\Models\SmartSim;
use App\Models\TelcoCost;
use App\Models\User;
use App\Support\OperationCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Throwable;

class NotificationService
{
    /**
     * Operational alert types and their priority. Only these types are delivered to the
     * bell menu: routine CRUD, login, and logout activity belongs to the audit trail.
     */
    private const ALERT_PRIORITIES = [
        'alert.defective' => 'critical',
        'system.error' => 'critical',
        'alert.low_inventory' => 'high',
        'alert.inactive' => 'high',
        'alert.location_issues' => 'high',
        'export.failed' => 'high',
        'alert.incomplete' => 'medium',
        'alert.maintenance' => 'medium',
        'contract.expiring' => 'medium',
    ];

    private const PRIORITY_RANK = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

    private const INACTIVE_STATUSES = ['inactive', 'offline', 'suspended', 'disabled', 'down', 'blocked'];

    private const MAINTENANCE_STATUSES = ['maintenance', 'in repair', 'for repair', 'servicing', 'under maintenance'];

    private const CLEARED_STATUSES = ['resolved', 'closed', 'replaced', 'disposed', 'repaired'];

    private const LOW_STOCK_THRESHOLD = 5;

    private const MULTI_ISSUE_THRESHOLD = 2;

    public static function notify(
        string $type,
        string $title,
        string $body,
        string $audiencePermission,
        string $dedupeKey,
        ?string $link = null,
        string $tone = 'unknown'
    ): ?Notification {
        if (! self::ready()) {
            return null;
        }

        $notification = Notification::firstOrCreate(
            ['dedupe_key' => $dedupeKey],
            [
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'link' => $link,
                'tone' => $tone,
                'audience_permission' => $audiencePermission,
            ]
        );

        self::fanOut($notification);

        return $notification;
    }

    public static function forCurrentUser(): array
    {
        $user = Auth::user();
        if (! $user || ! self::ready()) {
            return ['badge' => 0, 'items' => [], 'total' => 0];
        }

        try {
            self::syncOperational();

            $rows = NotificationRecipient::query()
                ->with('notification')
                ->where('user_id', $user->id)
                ->whereNull('dismissed_at')
                ->latest('id')
                ->limit(60)
                ->get()
                ->filter(fn (NotificationRecipient $row) => $row->notification
                    && self::isOperational($row->notification->type)
                    && $user->hasPermission($row->notification->audience_permission))
                ->sort(fn (NotificationRecipient $a, NotificationRecipient $b) => [self::rank($a), -$a->id] <=> [self::rank($b), -$b->id])
                ->take(20);

            return [
                'badge' => $rows->whereNull('read_at')->count(),
                'items' => $rows->values()->all(),
                'total' => $rows->count(),
            ];
        } catch (Throwable) {
            return ['badge' => 0, 'items' => [], 'total' => 0];
        }
    }

    public static function priorityFor(?string $type): string
    {
        return self::ALERT_PRIORITIES[$type] ?? 'medium';
    }

    public static function isOperational(?string $type): bool
    {
        return $type !== null && array_key_exists($type, self::ALERT_PRIORITIES);
    }

    public static function markRead(User $user, int $recipientId): void
    {
        if (! self::ready()) {
            return;
        }

        NotificationRecipient::query()
            ->where('id', $recipientId)
            ->where('user_id', $user->id)
            ->whereNull('dismissed_at')
            ->update(['read_at' => now()]);
    }

    public static function markAllRead(User $user): void
    {
        if (! self::ready()) {
            return;
        }

        NotificationRecipient::query()
            ->where('user_id', $user->id)
            ->whereNull('dismissed_at')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public static function dismiss(User $user, int $recipientId): void
    {
        if (! self::ready()) {
            return;
        }

        NotificationRecipient::query()
            ->where('id', $recipientId)
            ->where('user_id', $user->id)
            ->update(['dismissed_at' => now(), 'read_at' => now()]);
    }

    public static function dismissAll(User $user): void
    {
        if (! self::ready()) {
            return;
        }

        NotificationRecipient::query()
            ->where('user_id', $user->id)
            ->whereNull('dismissed_at')
            ->update(['dismissed_at' => now(), 'read_at' => now()]);
    }

    public static function exportFailed(string $module, string $message, ?string $routeName = null): void
    {
        self::notify(
            'export.failed',
            'Export failed',
            $module.': '.$message,
            'media.export',
            'export.failed.'.md5($module.$message.microtime(true)),
            $routeName ? route($routeName) : route('media-gateways.index'),
            'offline'
        );
    }

    public static function systemError(string $message): void
    {
        self::notify(
            'system.error',
            'System error',
            $message,
            'logs.view',
            'system.error.'.md5($message.microtime(true)),
            route('activity-logs'),
            'offline'
        );
    }

    /**
     * Rebuild the operational alert feed from live inventory data. Every alert is keyed to the
     * current day so an unresolved issue is raised once per day instead of on every page load.
     */
    public static function syncOperational(): void
    {
        if (! self::ready()) {
            return;
        }

        $day = now()->toDateString();

        self::publish(array_merge(
            self::defectiveAlerts($day),
            self::statusAlerts($day),
            self::locationAlerts($day),
            self::incompleteAlerts($day),
            self::contractAlerts($day)
        ));
    }

    /**
     * Inventory tables that carry a status, an optional location, and the columns that must be
     * filled in for a record to be considered complete.
     */
    private static function modules(): array
    {
        return [
            'PDC Servers' => [
                'model' => PdcServer::class,
                'route' => 'pdc-servers',
                'unit' => 'server',
                'location' => true,
                'stock' => null,
                'required' => ['ip_address', 'location', 'role'],
            ],
            'SIP Channels' => [
                'model' => SipChannel::class,
                'route' => 'sip-channels',
                'unit' => 'channel',
                'location' => false,
                'stock' => null,
                'required' => ['peer', 'context'],
            ],
            'Archive Recordings' => [
                'model' => ArchiveRecording::class,
                'route' => 'archive-recordings',
                'unit' => 'archive',
                'location' => false,
                'stock' => null,
                'required' => ['storage_path'],
            ],
            'Globe SIM' => [
                'model' => GlobeSim::class,
                'route' => 'globe-sim',
                'unit' => 'SIM',
                'location' => true,
                'stock' => 'assigned_to',
                'required' => ['imsi', 'location'],
            ],
            'Smart SIM' => [
                'model' => SmartSim::class,
                'route' => 'smart-sim',
                'unit' => 'SIM',
                'location' => true,
                'stock' => 'assigned_to',
                'required' => ['imsi', 'location'],
            ],
            'Program Inbound Numbers' => [
                'model' => ProgramInboundNumber::class,
                'route' => 'program-inbound-numbers',
                'unit' => 'number',
                'location' => true,
                'stock' => null,
                'required' => ['program', 'assigned_channel'],
            ],
            'Signal Boosters' => [
                'model' => SignalBooster::class,
                'route' => 'signal-boosters',
                'unit' => 'booster',
                'location' => true,
                'stock' => null,
                'required' => ['location'],
            ],
        ];
    }

    private static function defectiveAlerts(string $day): array
    {
        if (! Schema::hasTable('defective_gsms')) {
            return [];
        }

        $open = DefectiveGsm::query()
            ->get(['id', 'asset_code', 'location', 'issue', 'status'])
            ->reject(fn (DefectiveGsm $unit) => in_array(strtolower(trim((string) $unit->status)), self::CLEARED_STATUSES, true))
            ->sortByDesc('id')
            ->values();

        if ($open->isEmpty()) {
            return [];
        }

        $link = route('defective-gsm');
        $alerts = [];

        foreach ($open->take(6) as $unit) {
            $where = $unit->location ? ' at '.$unit->location : '';
            $issue = trim((string) $unit->issue);

            $alerts[] = self::alert(
                'alert.defective',
                'Defective equipment',
                'Defective GSM '.$unit->asset_code.$where.' needs repair'.($issue !== '' ? ' — '.$issue : '').'.',
                'media.view',
                'alert.defective.'.$unit->id.'.'.strtolower(trim((string) $unit->status)).'.'.$day,
                $link,
                'offline'
            );
        }

        if ($open->count() > 6) {
            $alerts[] = self::alert(
                'alert.defective',
                'Defective equipment',
                ($open->count() - 6).' more defective GSM units are still waiting for repair.',
                'media.view',
                'alert.defective.backlog.'.$open->count().'.'.$day,
                $link,
                'offline'
            );
        }

        return $alerts;
    }

    private static function statusAlerts(string $day): array
    {
        $alerts = [];

        foreach (self::modules() as $label => $module) {
            $model = $module['model'];
            if (! Schema::hasTable((new $model)->getTable())) {
                continue;
            }

            $counts = self::statusCounts($model);
            if ($counts['total'] < 1) {
                continue;
            }

            if ($counts['inactive'] > 0) {
                $alerts[] = self::alert(
                    'alert.inactive',
                    'Inactive equipment',
                    $counts['inactive'].' of '.$counts['total'].' '.$label.' records are inactive or offline.',
                    'media.view',
                    'alert.inactive.'.$module['route'].'.'.$counts['inactive'].'.'.$day,
                    route($module['route']),
                    'offline'
                );
            }

            if ($counts['maintenance'] > 0) {
                $alerts[] = self::alert(
                    'alert.maintenance',
                    'Maintenance due',
                    $counts['maintenance'].' '.$label.' '.($counts['maintenance'] === 1 ? 'record is' : 'records are').' flagged for maintenance or repair.',
                    'media.view',
                    'alert.maintenance.'.$module['route'].'.'.$counts['maintenance'].'.'.$day,
                    route($module['route']),
                    'unknown'
                );
            }

            if ($module['stock'] === null) {
                continue;
            }

            $spare = self::spareCount($model, $module['stock']);
            if ($spare > self::LOW_STOCK_THRESHOLD) {
                continue;
            }

            $alerts[] = self::alert(
                'alert.low_inventory',
                'Low inventory',
                $spare < 1
                    ? $label.' stock is empty — every '.$module['unit'].' on record is already assigned.'
                    : $label.' stock is low — only '.$spare.' unassigned '.$module['unit'].($spare === 1 ? '' : 's').' left.',
                'media.view',
                'alert.low_inventory.'.$module['route'].'.'.$spare.'.'.$day,
                route($module['route']),
                'offline'
            );
        }

        return $alerts;
    }

    private static function locationAlerts(string $day): array
    {
        $issues = [];
        foreach (OperationCatalog::locations() as $slug => $name) {
            $issues[$slug] = ['name' => $name, 'inactive' => 0, 'maintenance' => 0, 'defective' => 0];
        }

        foreach (self::modules() as $module) {
            if (! $module['location']) {
                continue;
            }

            $model = $module['model'];
            if (! Schema::hasTable((new $model)->getTable())) {
                continue;
            }

            foreach ($model::query()->selectRaw('location, status, count(*) as total')->groupBy('location', 'status')->get() as $row) {
                $slug = self::locationSlug($row->location);
                if (! $slug) {
                    continue;
                }

                $status = strtolower(trim((string) $row->status));
                if (in_array($status, self::INACTIVE_STATUSES, true)) {
                    $issues[$slug]['inactive'] += (int) $row->total;
                } elseif (in_array($status, self::MAINTENANCE_STATUSES, true)) {
                    $issues[$slug]['maintenance'] += (int) $row->total;
                }
            }
        }

        if (Schema::hasTable('defective_gsms')) {
            foreach (DefectiveGsm::query()->selectRaw('location, status, count(*) as total')->groupBy('location', 'status')->get() as $row) {
                $slug = self::locationSlug($row->location);
                if (! $slug || in_array(strtolower(trim((string) $row->status)), self::CLEARED_STATUSES, true)) {
                    continue;
                }

                $issues[$slug]['defective'] += (int) $row->total;
            }
        }

        $alerts = [];
        foreach ($issues as $slug => $issue) {
            $total = $issue['inactive'] + $issue['maintenance'] + $issue['defective'];
            if ($total < self::MULTI_ISSUE_THRESHOLD) {
                continue;
            }

            $parts = [];
            if ($issue['defective'] > 0) {
                $parts[] = $issue['defective'].' defective';
            }
            if ($issue['inactive'] > 0) {
                $parts[] = $issue['inactive'].' inactive';
            }
            if ($issue['maintenance'] > 0) {
                $parts[] = $issue['maintenance'].' for maintenance';
            }

            $alerts[] = self::alert(
                'alert.location_issues',
                'Location needs attention',
                $issue['name'].' has '.$total.' open equipment issues ('.implode(', ', $parts).').',
                'media.view',
                'alert.location_issues.'.$slug.'.'.$total.'.'.$day,
                route('program-location.show', $slug),
                'offline'
            );
        }

        return $alerts;
    }

    private static function incompleteAlerts(string $day): array
    {
        $targets = self::modules();
        $targets['GSM Gateways'] = [
            'model' => MediaGateway::class,
            'route' => 'gsm-gateways.index',
            'required' => ['ip_address', 'username', 'database'],
        ];

        $alerts = [];
        foreach ($targets as $label => $module) {
            $model = $module['model'];
            $columns = $module['required'] ?? [];
            if ($columns === [] || ! Schema::hasTable((new $model)->getTable())) {
                continue;
            }

            $incomplete = $model::query()
                ->where(function (Builder $query) use ($columns) {
                    foreach ($columns as $column) {
                        $query->orWhereNull($column)->orWhere($column, '');
                    }
                })
                ->count();

            if ($incomplete < 1) {
                continue;
            }

            $alerts[] = self::alert(
                'alert.incomplete',
                'Incomplete records',
                $incomplete.' '.$label.' '.($incomplete === 1 ? 'record is' : 'records are').' missing required details ('.implode(', ', array_map(fn ($column) => str_replace('_', ' ', $column), $columns)).').',
                'media.view',
                'alert.incomplete.'.$module['route'].'.'.$incomplete.'.'.$day,
                route($module['route']),
                'unknown'
            );
        }

        return $alerts;
    }

    private static function contractAlerts(string $day): array
    {
        if (! Schema::hasTable('telco_costs')) {
            return [];
        }

        $alerts = [];
        $contracts = TelcoCost::query()
            ->whereNotNull('contract_end')
            ->whereBetween('contract_end', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->orderBy('id')
            ->get();

        foreach ($contracts as $contract) {
            $end = optional($contract->contract_end)->format('Y-m-d');
            $alerts[] = self::alert(
                'contract.expiring',
                'Contract expiring',
                $contract->provider.' — '.$contract->site.' ends '.$end.'.',
                'media.view',
                'contract.expiring.'.$contract->id.'.'.$end,
                route('reports'),
                'unknown'
            );
        }

        return $alerts;
    }

    /**
     * Usable stock is a record that is still in service but not assigned to anything yet.
     */
    private static function spareCount(string $model, string $column): int
    {
        $placeholders = implode(',', array_fill(0, count(self::INACTIVE_STATUSES), '?'));

        return $model::query()
            ->whereRaw("lower(coalesce(status,'')) not in ($placeholders)", self::INACTIVE_STATUSES)
            ->where(fn (Builder $query) => $query->whereNull($column)->orWhere($column, ''))
            ->count();
    }

    private static function statusCounts(string $model): array
    {
        $counts = ['total' => 0, 'inactive' => 0, 'maintenance' => 0];

        foreach ($model::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status') as $status => $total) {
            $total = (int) $total;
            $counts['total'] += $total;
            $status = strtolower(trim((string) $status));

            if (in_array($status, self::INACTIVE_STATUSES, true)) {
                $counts['inactive'] += $total;
            } elseif (in_array($status, self::MAINTENANCE_STATUSES, true)) {
                $counts['maintenance'] += $total;
            }
        }

        return $counts;
    }

    private static function locationSlug(?string $location): ?string
    {
        $needle = strtolower(trim((string) $location));
        if ($needle === '') {
            return null;
        }

        foreach (OperationCatalog::locations() as $slug => $name) {
            if ($needle === $slug || $needle === strtolower($name)) {
                return $slug;
            }
        }

        return null;
    }

    private static function alert(string $type, string $title, string $body, string $permission, string $dedupe, ?string $link, string $tone): array
    {
        return compact('type', 'title', 'body', 'permission', 'dedupe', 'link', 'tone');
    }

    /**
     * Create only the alerts that are not on file yet, so a repeat page load costs one lookup
     * instead of re-writing the whole feed.
     */
    private static function publish(array $alerts): void
    {
        if ($alerts === []) {
            return;
        }

        $existing = Notification::query()
            ->whereIn('dedupe_key', array_column($alerts, 'dedupe'))
            ->pluck('dedupe_key')
            ->all();

        foreach ($alerts as $alert) {
            if (in_array($alert['dedupe'], $existing, true)) {
                continue;
            }

            self::notify(
                $alert['type'],
                $alert['title'],
                $alert['body'],
                $alert['permission'],
                $alert['dedupe'],
                $alert['link'],
                $alert['tone']
            );
        }
    }

    private static function rank(NotificationRecipient $row): int
    {
        return self::PRIORITY_RANK[self::priorityFor($row->notification->type)] ?? 3;
    }

    private static function fanOut(Notification $notification): void
    {
        $userIds = User::query()
            ->with('userType')
            ->where('status', 'Active')
            ->get()
            ->filter(fn (User $user) => $user->hasPermission($notification->audience_permission))
            ->pluck('id');

        foreach ($userIds as $userId) {
            NotificationRecipient::firstOrCreate([
                'notification_id' => $notification->id,
                'user_id' => $userId,
            ]);
        }
    }

    private static function ready(): bool
    {
        return Schema::hasTable('notifications') && Schema::hasTable('notification_recipients');
    }
}
