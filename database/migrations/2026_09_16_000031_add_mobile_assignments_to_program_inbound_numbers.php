<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_inbound_numbers', function (Blueprint $table) {
            if (! Schema::hasColumn('program_inbound_numbers', 'mobile_assignments')) {
                $table->json('mobile_assignments')->nullable()->after('mobile_numbers');
            }
        });
    }

    public function down(): void
    {
        Schema::table('program_inbound_numbers', function (Blueprint $table) {
            if (Schema::hasColumn('program_inbound_numbers', 'mobile_assignments')) {
                $table->dropColumn('mobile_assignments');
            }
        });
    }
};
