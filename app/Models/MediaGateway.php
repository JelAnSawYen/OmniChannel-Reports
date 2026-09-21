<?php

namespace App\Models;

use App\Support\GsmSimInventory;
use App\Support\InventoryDependentSync;
use App\Support\NaturalSort;
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
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(GatewaySimAssignment::class)->orderBy('port');
    }

    public function assignmentPayload(): array
    {
        $ip = strtolower(trim((string) $this->ip_address));
        if ($ip === '') {
            return [];
        }

        $this->loadMissing('assignments');
        $ports = [];
        $assignmentIds = [];
        foreach ($this->assignments as $assignment) {
            $key = $assignment->sim_type.':'.$assignment->sim_id;
            $ports[$key] = (int) $assignment->port;
            $assignmentIds[$key] = (int) $assignment->id;
        }

        $rows = [];
        foreach (['globe' => GlobeSim::class, 'smart' => SmartSim::class] as $type => $model) {
            $sims = $model::query()
                ->whereRaw('LOWER(TRIM(ip_address)) = ?', [$ip]);
            NaturalSort::apply($sims, 'imei');
            $sims = $sims->get();
            foreach ($sims as $sim) {
                $serialized = GsmSimInventory::serialize($type, $sim);
                $key = $type.':'.$sim->id;
                $serialized['assignment_id'] = $assignmentIds[$key] ?? 0;
                $serialized['port'] = $ports[$key] ?? '';
                $serialized['ip_address'] = (string) ($this->ip_address ?? '');
                $serialized['network'] = GsmSimInventory::networkForType($type);
                $rows[] = $serialized;
            }
        }

        usort($rows, function (array $left, array $right): int {
            $portLeft = $left['port'] === '' || $left['port'] === null ? PHP_INT_MAX : (int) $left['port'];
            $portRight = $right['port'] === '' || $right['port'] === null ? PHP_INT_MAX : (int) $right['port'];
            if ($portLeft !== $portRight) {
                return $portLeft <=> $portRight;
            }

            return NaturalSort::compare($left['imei'] ?? '', $right['imei'] ?? '');
        });

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
    }
}
