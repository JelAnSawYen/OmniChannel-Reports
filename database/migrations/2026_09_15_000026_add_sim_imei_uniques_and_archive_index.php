<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->uniqueRequired('globe_sims', 'imei', 'globe_sims_imei_unique');
        $this->uniqueRequired('smart_sims', 'imei', 'smart_sims_imei_unique');
        $this->uniqueIfClean('media_gateways', ['site_code'], 'media_gateways_site_code_unique');
        $this->uniqueIfClean('media_gateways', ['ip_address'], 'media_gateways_ip_address_unique');
        $this->uniqueIfClean('channel_prefixes', ['prefix'], 'channel_prefixes_prefix_unique');
        $this->uniqueIfClean('channel_ports', ['port_number', 'gateway'], 'channel_ports_port_gateway_unique');
        $this->uniqueIfClean('network_prefixes', ['network', 'prefix'], 'network_prefixes_network_prefix_unique');
        $this->uniqueIfClean('globe_sims', ['mobile_number'], 'globe_sims_mobile_number_unique');
        $this->uniqueIfClean('smart_sims', ['mobile_number'], 'smart_sims_mobile_number_unique');

        if (Schema::hasTable('archive_recordings') && ! Schema::hasIndex('archive_recordings', 'archive_recordings_campaign_called_at_index')) {
            Schema::table('archive_recordings', function (Blueprint $table) {
                $table->index(['campaign_id', 'called_at'], 'archive_recordings_campaign_called_at_index');
            });
        }
    }

    public function down(): void
    {
        $this->dropIndexIfExists('globe_sims', 'globe_sims_imei_unique');
        $this->dropIndexIfExists('smart_sims', 'smart_sims_imei_unique');
        if (Schema::hasTable('archive_recordings') && Schema::hasIndex('archive_recordings', 'archive_recordings_campaign_called_at_index')) {
            Schema::table('archive_recordings', function (Blueprint $table) {
                $table->dropIndex('archive_recordings_campaign_called_at_index');
            });
        }
    }

    private function uniqueRequired(string $table, string $column, string $index): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        if (Schema::hasIndex($table, $index)) {
            return;
        }

        $duplicates = DB::table($table)
            ->select($column)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($duplicates > 0) {
            throw new \RuntimeException(
                "Cannot add unique index {$index}: {$duplicates} duplicate {$table}.{$column} value(s) exist."
            );
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $index) {
            $blueprint->unique($column, $index);
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function uniqueIfClean(string $table, array $columns, string $index): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        if (Schema::hasIndex($table, $index)) {
            return;
        }

        $query = DB::table($table)->select($columns);
        foreach ($columns as $column) {
            $query->whereNotNull($column)->where($column, '!=', '');
        }
        $duplicates = $query->groupBy($columns)->havingRaw('COUNT(*) > 1')->count();
        if ($duplicates > 0) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $index) {
            $blueprint->unique($columns, $index);
        });
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasIndex($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($index) {
            $blueprint->dropUnique($index);
        });
    }
};
