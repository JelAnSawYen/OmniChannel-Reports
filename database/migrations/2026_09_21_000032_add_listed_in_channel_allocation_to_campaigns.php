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
            if (! Schema::hasColumn('channel_allocation_campaigns', 'listed_in_channel_allocation')) {
                $table->boolean('listed_in_channel_allocation')->default(false);
            }
        });

        DB::table('channel_allocation_campaigns')->update(['listed_in_channel_allocation' => true]);

        $listedIds = DB::table('channel_allocations')->distinct()->pluck('campaign_id');
        DB::table('channel_allocation_campaigns')
            ->whereNotIn('id', $listedIds)
            ->where(function ($query) {
                $query->whereNull('caller_id')->orWhere('caller_id', '');
            })
            ->where(function ($query) {
                $query->whereNull('prefix')->orWhere('prefix', '');
            })
            ->where(function ($query) {
                $query->whereNull('remarks')->orWhere('remarks', '');
            })
            ->where(function ($query) {
                $query->whereNull('media_gateway')->orWhere('media_gateway', '');
            })
            ->where(function ($query) {
                $query->whereNull('total_channels_allocated')->orWhere('total_channels_allocated', 0);
            })
            ->update(['listed_in_channel_allocation' => false]);
    }

    public function down(): void
    {
        Schema::table('channel_allocation_campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('channel_allocation_campaigns', 'listed_in_channel_allocation')) {
                $table->dropColumn('listed_in_channel_allocation');
            }
        });
    }
};
