<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('telco_costs', function (Blueprint $table) {
            $table->id(); $table->string('provider'); $table->string('site'); $table->string('service_type');
            $table->decimal('monthly_cost', 12, 2)->default(0); $table->date('contract_start')->nullable(); $table->date('contract_end')->nullable();
            $table->string('status')->default('Active'); $table->timestamps();
        });
        Schema::create('channel_prefixes', function (Blueprint $table) {
            $table->id(); $table->string('prefix'); $table->string('channel')->nullable(); $table->text('description')->nullable(); $table->string('status')->default('Active'); $table->timestamps();
        });
        Schema::create('channel_ports', function (Blueprint $table) {
            $table->id(); $table->unsignedInteger('port_number'); $table->string('gateway')->nullable(); $table->string('channel')->nullable(); $table->string('status')->default('Available'); $table->text('description')->nullable(); $table->timestamps();
        });
        Schema::create('network_prefixes', function (Blueprint $table) {
            $table->id(); $table->string('network'); $table->string('prefix'); $table->string('gateway')->nullable(); $table->string('status')->default('Active'); $table->text('description')->nullable(); $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_prefixes'); Schema::dropIfExists('channel_ports'); Schema::dropIfExists('channel_prefixes'); Schema::dropIfExists('telco_costs');
    }
};
