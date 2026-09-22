<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('media_gateways') || Schema::hasColumn('media_gateways', 'port_remarks')) {
            return;
        }

        Schema::table('media_gateways', function (Blueprint $table): void {
            $table->json('port_remarks')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('media_gateways') || ! Schema::hasColumn('media_gateways', 'port_remarks')) {
            return;
        }

        Schema::table('media_gateways', function (Blueprint $table): void {
            $table->dropColumn('port_remarks');
        });
    }
};
