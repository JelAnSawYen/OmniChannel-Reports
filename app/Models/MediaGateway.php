<?php

namespace App\Models;

use App\Support\GsmSimInventory;
use App\Support\InventoryDependentSync;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaGateway extends Model
{
    use HasFactory;

    protected $fillable = [
        'hostname',
        'site_name',
        'site_code',
        'ip_address',
        'channel_count',
        'plan',
        'port',
        'network',
        'device_function',
        'username',
        'password',
        'database',
    ];

    protected $casts = [
        'channel_count' => 'integer',
        'port_remarks' => 'array',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(GatewaySimAssignment::class)->orderBy('port');
    }

    public function assignmentPayload(): array
    {
        $count = max(0, (int) $this->channel_count);
        if ($count < 1) {
            return [];
        }

        $filled = [];
        $this->loadMissing('assignments');
        foreach ($this->assignments as $assignment) {
            $port = (int) $assignment->port;
            if ($port < 1 || $port > $count || isset($filled[$port])) {
                continue;
            }
            $sim = GsmSimInventory::findSim((string) $assignment->sim_type, (int) $assignment->sim_id);
            $row = $sim
                ? GsmSimInventory::serialize((string) $assignment->sim_type, $sim)
                : GsmSimInventory::emptyPortRow($port);
            $row['assignment_id'] = (int) $assignment->id;
            $row['port'] = $port;
            $row['ip_address'] = (string) ($this->ip_address ?? '');
            if ($sim) {
                $row['network'] = GsmSimInventory::networkForType((string) $assignment->sim_type);
            }
            $filled[$port] = $row;
        }

        $ip = strtolower(trim((string) $this->ip_address));
        $host = mb_strtolower(trim((string) $this->hostname));
        if ($ip !== '' || $host !== '') {
            foreach (['globe' => GlobeSim::class, 'smart' => SmartSim::class] as $type => $model) {
                $query = $model::query()->with('gatewayAssignment.gateway:id,hostname,ip_address');
                $query->where(function ($sims) use ($ip, $host): void {
                    if ($ip !== '') {
                        $sims->whereRaw('LOWER(TRIM(ip_address)) = ?', [$ip]);
                    }
                    if ($host !== '') {
                        $method = $ip !== '' ? 'orWhereHas' : 'whereHas';
                        $sims->{$method}('gatewayAssignment.gateway', function ($gateways) use ($host): void {
                            $gateways->whereRaw('LOWER(TRIM(hostname)) = ?', [$host]);
                        });
                    }
                });
                foreach ($query->get() as $sim) {
                    $port = (int) ($sim->port ?? 0);
                    if ($port < 1 || $port > $count || isset($filled[$port])) {
                        continue;
                    }
                    $row = GsmSimInventory::serialize($type, $sim);
                    $row['assignment_id'] = (int) ($sim->gatewayAssignment?->id ?? 0);
                    $row['port'] = $port;
                    $row['ip_address'] = (string) ($this->ip_address ?? '');
                    $row['network'] = GsmSimInventory::networkForType($type);
                    $filled[$port] = $row;
                }
            }
        }

        $rows = [];
        $portRemarks = is_array($this->port_remarks) ? $this->port_remarks : [];
        for ($port = 1; $port <= $count; $port++) {
            $row = $filled[$port] ?? GsmSimInventory::emptyPortRow($port);
            $hasSim = (int) ($row['id'] ?? 0) > 0 || (int) ($row['assignment_id'] ?? 0) > 0;
            if (! $hasSim) {
                $row['remarks'] = trim((string) ($portRemarks[(string) $port] ?? ''));
            }
            $rows[] = $row;
        }

        return $rows;
    }

    protected static function booted(): void
    {
        static::creating(function (MediaGateway $gateway): void {
            if ($gateway->database === null) {
                $gateway->database = '';
            }
        });
        static::updated(function (MediaGateway $gateway): void {
            InventoryDependentSync::gatewaySaved($gateway);
        });
        static::deleting(function (MediaGateway $gateway): void {
            InventoryDependentSync::gatewayDeleted($gateway);
        });
    }
}
