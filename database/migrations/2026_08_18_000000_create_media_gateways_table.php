<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('site_name');
            $table->string('site_code');
            $table->string('ip_address');
            $table->string('username');
            $table->string('database');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_gateways');
    }
};
