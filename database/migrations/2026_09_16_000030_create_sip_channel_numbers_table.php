<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sip_channel_numbers')) {
            return;
        }

        Schema::create('sip_channel_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sip_channel_id')->constrained('sip_channels')->cascadeOnDelete();
            $table->string('channel_number');
            $table->timestamps();
            $table->unique('channel_number');
            $table->index(['sip_channel_id', 'channel_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sip_channel_numbers');
    }
};
