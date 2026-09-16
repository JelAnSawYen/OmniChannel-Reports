<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_gateways', function (Blueprint $table) {
            if (! Schema::hasColumn('media_gateways', 'hostname')) {
                $table->string('hostname')->nullable()->after('id');
            }
            if (! Schema::hasColumn('media_gateways', 'channel_count')) {
                $table->unsignedInteger('channel_count')->nullable()->after('ip_address');
            }
        });

        if (! Schema::hasTable('gateway_sim_assignments')) {
            Schema::create('gateway_sim_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('media_gateway_id')->constrained('media_gateways')->cascadeOnDelete();
                $table->string('sim_type', 16);
                $table->unsignedBigInteger('sim_id');
                $table->unsignedInteger('port');
                $table->timestamps();

                $table->unique(['media_gateway_id', 'port'], 'gateway_sim_assignments_gateway_port_unique');
                $table->unique(['media_gateway_id', 'sim_type', 'sim_id'], 'gateway_sim_assignments_gateway_sim_unique');
                $table->unique(['sim_type', 'sim_id'], 'gateway_sim_assignments_sim_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_sim_assignments');

        Schema::table('media_gateways', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('media_gateways', 'hostname') ? 'hostname' : null,
                Schema::hasColumn('media_gateways', 'channel_count') ? 'channel_count' : null,
            ]));
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
