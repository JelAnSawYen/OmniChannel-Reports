<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archive_recordings', function (Blueprint $table) {
            $table->foreignId('campaign_id')
                ->nullable()
                ->after('id')
                ->constrained('channel_allocation_campaigns')
                ->nullOnDelete();
            $table->string('file_name')->nullable()->after('campaign_id');
            $table->dateTime('called_at')->nullable()->after('file_name');
            $table->string('caller_number')->nullable()->after('called_at');
            $table->string('agent_number')->nullable()->after('caller_number');
            $table->string('duration')->nullable()->after('agent_number');
        });
    }

    public function down(): void
    {
        Schema::table('archive_recordings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_id');
            $table->dropColumn([
                'file_name',
                'called_at',
                'caller_number',
                'agent_number',
                'duration',
            ]);
        });
    }
};
