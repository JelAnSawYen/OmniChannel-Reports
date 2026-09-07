<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_allocation_campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('channel_allocation_campaigns', 'location')) {
                $table->string('location')->nullable()->after('fte');
            }
        });

        Schema::table('media_gateways', function (Blueprint $table) {
            if (! Schema::hasColumn('media_gateways', 'plan')) {
                $table->string('plan')->nullable()->after('ip_address');
            }
            if (! Schema::hasColumn('media_gateways', 'port')) {
                $table->string('port')->nullable()->after('plan');
            }
            if (! Schema::hasColumn('media_gateways', 'network')) {
                $table->string('network')->nullable()->after('port');
            }
            if (! Schema::hasColumn('media_gateways', 'device_function')) {
                $table->string('device_function')->nullable()->after('network');
            }
            if (! Schema::hasColumn('media_gateways', 'password')) {
                $table->string('password')->nullable()->after('username');
            }
        });
    }

    public function down(): void
    {
        Schema::table('channel_allocation_campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('channel_allocation_campaigns', 'location')) {
                $table->dropColumn('location');
            }
        });

        Schema::table('media_gateways', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('media_gateways', 'plan') ? 'plan' : null,
                Schema::hasColumn('media_gateways', 'port') ? 'port' : null,
                Schema::hasColumn('media_gateways', 'network') ? 'network' : null,
                Schema::hasColumn('media_gateways', 'device_function') ? 'device_function' : null,
                Schema::hasColumn('media_gateways', 'password') ? 'password' : null,
            ]));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
