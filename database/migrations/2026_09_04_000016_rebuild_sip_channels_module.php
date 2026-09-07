<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sip_channels', function (Blueprint $table) {
            $table->dropUnique(['channel']);
        });

        Schema::table('sip_channels', function (Blueprint $table) {
            $table->foreignId('campaign_id')
                ->nullable()
                ->constrained('channel_allocation_campaigns')
                ->nullOnDelete();
            $table->string('etpi_sip_name')->nullable();
            $table->string('pilot_number')->nullable();
            $table->unsignedInteger('channel_count')->nullable();
            $table->string('channel_range')->nullable();
            $table->string('network')->nullable();
            $table->date('date_activation')->nullable();
        });

        if (Schema::hasColumn('sip_channels', 'channel')) {
            foreach (DB::table('sip_channels')->orderBy('id')->get() as $row) {
                $name = trim((string) ($row->etpi_sip_name ?? ''));
                $channel = trim((string) ($row->channel ?? ''));
                if ($name === '' && $channel !== '') {
                    DB::table('sip_channels')->where('id', $row->id)->update([
                        'etpi_sip_name' => $channel,
                    ]);
                }
            }
        }

        Schema::table('sip_channels', function (Blueprint $table) {
            $table->dropColumn(['channel', 'peer', 'context', 'codec', 'status']);
        });

        Schema::table('sip_channels', function (Blueprint $table) {
            $table->unique('etpi_sip_name');
        });
    }

    public function down(): void
    {
        Schema::table('sip_channels', function (Blueprint $table) {
            $table->dropUnique(['etpi_sip_name']);
        });

        Schema::table('sip_channels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_id');
            $table->dropColumn([
                'etpi_sip_name',
                'pilot_number',
                'channel_count',
                'channel_range',
                'network',
                'date_activation',
            ]);
        });

        Schema::table('sip_channels', function (Blueprint $table) {
            $table->string('channel');
            $table->string('peer')->nullable();
            $table->string('context')->nullable();
            $table->string('codec')->nullable();
            $table->string('status')->default('Active');
            $table->unique('channel');
        });
    }
};
