<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archive_recordings', function (Blueprint $table) {
            if (! Schema::hasColumn('archive_recordings', 'location')) {
                $table->string('location')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('archive_recordings', function (Blueprint $table) {
            if (Schema::hasColumn('archive_recordings', 'location')) {
                $table->dropColumn('location');
            }
        });
    }
};
