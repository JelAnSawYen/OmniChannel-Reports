<?php

namespace Database\Seeders;

use App\Models\MediaGateway;
use Illuminate\Database\Seeder;

class MediaGatewaySeeder extends Seeder
{
    public function run(): void
    {
        $records = [
            ['id' => 1, 'site_name' => 'Estancia', 'site_code' => 'PAS202', 'ip_address' => '10.28.240.202', 'username' => 'root', 'database' => 'asteriskcdrdb'],
            ['id' => 2, 'site_name' => 'Alpha', 'site_code' => 'MKT132', 'ip_address' => '10.18.20.132', 'username' => 'root', 'database' => 'asteriskcdrdb'],
            ['id' => 3, 'site_name' => 'Alpha', 'site_code' => 'MKT136', 'ip_address' => '10.18.20.136', 'username' => 'root', 'database' => 'asteriskcdrdb'],
            ['id' => 5, 'site_name' => 'Alpha', 'site_code' => 'MKT135', 'ip_address' => '10.18.20.135', 'username' => 'root', 'database' => 'asteriskcdrdb'],
            ['id' => 6, 'site_name' => 'Astra', 'site_code' => 'MA2-201', 'ip_address' => '10.20.240.201', 'username' => 'root', 'database' => 'asteriskcdrdb'],
            ['id' => 7, 'site_name' => 'Estancia', 'site_code' => 'PAS201', 'ip_address' => '10.28.240.201', 'username' => 'root', 'database' => 'asteriskcdrdb'],
            ['id' => 8, 'site_name' => 'WFH', 'site_code' => 'PDC-MG1', 'ip_address' => '10.24.28.54', 'username' => 'root', 'database' => 'asteriskcdrdb'],
            ['id' => 9, 'site_name' => 'WFH', 'site_code' => 'PDC-MG2', 'ip_address' => '10.24.28.38', 'username' => 'root', 'database' => 'asteriskcdrdb'],
            ['id' => 10, 'site_name' => 'WFH', 'site_code' => 'PDC-MG3', 'ip_address' => '10.24.28.55', 'username' => 'root', 'database' => 'asteriskcdrdb'],
            ['id' => 11, 'site_name' => 'WFH', 'site_code' => 'PDC-MG4', 'ip_address' => '10.24.28.83', 'username' => 'root', 'database' => 'asteriskcdrdb'],
        ];

        foreach ($records as $record) {
            MediaGateway::query()->updateOrCreate(['site_code' => $record['site_code']], $record);
        }
    }
}
