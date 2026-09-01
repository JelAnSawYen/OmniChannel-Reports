<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->uniqueIfClean('media_gateways', ['site_code'], 'media_gateways_site_code_unique');
        $this->uniqueIfClean('media_gateways', ['ip_address'], 'media_gateways_ip_address_unique');
        $this->uniqueIfClean('channel_prefixes', ['prefix'], 'channel_prefixes_prefix_unique');
        $this->uniqueIfClean('channel_ports', ['port_number', 'gateway'], 'channel_ports_port_gateway_unique');
        $this->uniqueIfClean('network_prefixes', ['network', 'prefix'], 'network_prefixes_network_prefix_unique');
    }

    public function down(): void
    {
        foreach ([
            'media_gateways' => ['media_gateways_site_code_unique', 'media_gateways_ip_address_unique'],
            'channel_prefixes' => ['channel_prefixes_prefix_unique'],
            'channel_ports' => ['channel_ports_port_gateway_unique'],
            'network_prefixes' => ['network_prefixes_network_prefix_unique'],
        ] as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) use ($indexes) {
                foreach ($indexes as $index) {
                    $blueprint->dropUnique($index);
                }
            });
        }
    }

    private function uniqueIfClean(string $table, array $columns, string $index): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $duplicates = DB::table($table)
            ->select($columns)
            ->groupBy($columns)
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicates > 0) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $index) {
            $blueprint->unique($columns, $index);
        });
    }
};
