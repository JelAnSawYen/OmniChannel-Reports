<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signal_boosters', function (Blueprint $table) {
            $table->text('specs')->nullable()->after('model');
        });
    }

    public function down(): void
    {
        Schema::table('signal_boosters', function (Blueprint $table) {
            $table->dropColumn('specs');
        });
    }
};
