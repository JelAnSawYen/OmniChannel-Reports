<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdc_servers', function (Blueprint $table) {
            $table->id();
            $table->string('hostname');
            $table->string('ip_address');
            $table->string('location')->nullable();
            $table->string('role')->nullable();
            $table->string('status')->default('Active');
            $table->timestamps();
            $table->unique('hostname');
            $table->unique('ip_address');
        });

        Schema::create('sip_channels', function (Blueprint $table) {
            $table->id();
            $table->string('channel');
            $table->string('peer')->nullable();
            $table->string('context')->nullable();
            $table->string('codec')->nullable();
            $table->string('status')->default('Active');
            $table->timestamps();
            $table->unique('channel');
        });

        Schema::create('archive_recordings', function (Blueprint $table) {
            $table->id();
            $table->string('server');
            $table->string('storage_path');
            $table->unsignedInteger('retention_days')->nullable();
            $table->string('status')->default('Active');
            $table->timestamps();
        });

        Schema::create('globe_sims', function (Blueprint $table) {
            $table->id();
            $table->string('sim_number');
            $table->string('imsi')->nullable();
            $table->string('assigned_to')->nullable();
            $table->string('location')->nullable();
            $table->string('status')->default('Active');
            $table->timestamps();
            $table->unique('sim_number');
        });

        Schema::create('smart_sims', function (Blueprint $table) {
            $table->id();
            $table->string('sim_number');
            $table->string('imsi')->nullable();
            $table->string('assigned_to')->nullable();
            $table->string('location')->nullable();
            $table->string('status')->default('Active');
            $table->timestamps();
            $table->unique('sim_number');
        });

        Schema::create('program_inbound_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('number');
            $table->string('program')->nullable();
            $table->string('location')->nullable();
            $table->string('assigned_channel')->nullable();
            $table->string('status')->default('Active');
            $table->timestamps();
            $table->unique('number');
        });

        Schema::create('signal_boosters', function (Blueprint $table) {
            $table->id();
            $table->string('model');
            $table->string('serial_number');
            $table->string('location')->nullable();
            $table->string('status')->default('Active');
            $table->timestamps();
            $table->unique('serial_number');
        });

        Schema::create('defective_gsms', function (Blueprint $table) {
            $table->id();
            $table->string('asset_code');
            $table->string('location')->nullable();
            $table->text('issue')->nullable();
            $table->date('reported_on')->nullable();
            $table->string('status')->default('Open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defective_gsms');
        Schema::dropIfExists('signal_boosters');
        Schema::dropIfExists('program_inbound_numbers');
        Schema::dropIfExists('smart_sims');
        Schema::dropIfExists('globe_sims');
        Schema::dropIfExists('archive_recordings');
        Schema::dropIfExists('sip_channels');
        Schema::dropIfExists('pdc_servers');
    }
};
