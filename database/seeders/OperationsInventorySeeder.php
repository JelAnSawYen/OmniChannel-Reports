<?php

namespace Database\Seeders;

use App\Models\MediaGateway;
use App\Models\PdcServer;
use Illuminate\Database\Seeder;

class OperationsInventorySeeder extends Seeder
{
    public function run(): void
    {
        MediaGateway::query()
            ->where('site_name', 'PDC')
            ->get()
            ->each(function (MediaGateway $gateway) {
                PdcServer::query()->updateOrCreate(
                    ['hostname' => $gateway->site_code],
                    [
                        'ip_address' => $gateway->ip_address,
                        'location' => $gateway->site_name,
                        'role' => 'PDC Server',
                        'status' => 'Active',
                    ]
                );
            });
    }
}
