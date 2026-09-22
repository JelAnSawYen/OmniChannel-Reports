<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['globe_sims', 'smart_sims'] as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'remarks')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->text('remarks')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['globe_sims', 'smart_sims'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'remarks')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('remarks');
            });
        }
    }
};
