<?php

namespace App\Models;

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
        return $this->assignments()
            ->orderBy('port')
            ->get()
            ->map(function (GatewaySimAssignment $assignment) {
                $sim = \App\Support\GsmSimInventory::findSim((string) $assignment->sim_type, (int) $assignment->sim_id);
                $serialized = $sim
                    ? \App\Support\GsmSimInventory::serialize((string) $assignment->sim_type, $sim)
                    : [
                        'id' => (int) $assignment->sim_id,
                        'imei' => '',
                        'mobile_number' => '',
                        'plan' => '',
                        'network' => \App\Support\GsmSimInventory::networkForType((string) $assignment->sim_type),
                        'sim_type' => $assignment->sim_type,
                    ];
                $serialized['assignment_id'] = (int) $assignment->id;
                $serialized['port'] = (int) $assignment->port;
                $serialized['ip_address'] = (string) ($this->ip_address ?? '');
                $serialized['network'] = \App\Support\GsmSimInventory::networkForType((string) $assignment->sim_type);

                return $serialized;
            })
            ->values()
            ->all();
    }

    protected static function booted(): void
    {
        static::creating(function (MediaGateway $gateway): void {
            if ($gateway->database === null) {
                $gateway->database = '';
            }
        });
    }
}
