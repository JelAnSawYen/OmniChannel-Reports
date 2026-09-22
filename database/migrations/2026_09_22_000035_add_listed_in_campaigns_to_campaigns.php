<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_allocation_campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('channel_allocation_campaigns', 'listed_in_campaigns')) {
                $table->boolean('listed_in_campaigns')->default(true);
            }
        });

        DB::table('channel_allocation_campaigns')->update(['listed_in_campaigns' => true]);
    }

    public function down(): void
    {
        Schema::table('channel_allocation_campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('channel_allocation_campaigns', 'listed_in_campaigns')) {
                $table->dropColumn('listed_in_campaigns');
            }
        });
    }
};
