<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_allocation_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('media_gateway')->nullable();
            $table->unsignedInteger('total_channels_allocated')->nullable();
            $table->unsignedInteger('fte')->nullable();
            $table->string('caller_id')->nullable();
            $table->string('prefix')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique('name');
        });

        Schema::create('channel_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('channel_allocation_campaigns')->cascadeOnDelete();
            $table->string('media_gateway')->nullable();
            $table->string('channel_allocation');
            $table->string('network')->nullable();
            $table->unsignedInteger('line_priority')->nullable();
            $table->unsignedInteger('total_channel_allocated')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_allocations');
        Schema::dropIfExists('channel_allocation_campaigns');
    }
};
