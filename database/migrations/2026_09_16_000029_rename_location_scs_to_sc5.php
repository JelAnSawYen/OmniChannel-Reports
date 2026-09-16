<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private const COLUMNS = [
        'media_gateways' => 'site_name',
        'pdc_groups' => 'location',
        'pdc_servers' => 'location',
        'channel_allocation_campaigns' => 'location',
        'archive_recordings' => 'location',
        'globe_sims' => 'location',
        'smart_sims' => 'location',
        'program_inbound_numbers' => 'location',
        'signal_boosters' => 'location',
        'defective_gsms' => 'location',
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::table($table)->whereRaw('UPPER('.$column.') = ?', ['SCS'])->update([$column => 'SC5']);
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::table($table)->where($column, 'SC5')->update([$column => 'SCS']);
        }
    }
};
