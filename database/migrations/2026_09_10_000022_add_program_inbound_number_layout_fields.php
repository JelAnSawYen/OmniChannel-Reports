<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_inbound_numbers', function (Blueprint $table) {
            if (! Schema::hasColumn('program_inbound_numbers', 'campaign_id')) {
                $table->foreignId('campaign_id')->nullable()->after('id')->constrained('channel_allocation_campaigns')->nullOnDelete();
            }
            if (! Schema::hasColumn('program_inbound_numbers', 'mobile_numbers')) {
                $table->json('mobile_numbers')->nullable()->after('number');
            }
            if (! Schema::hasColumn('program_inbound_numbers', 'landline_numbers')) {
                $table->json('landline_numbers')->nullable()->after('mobile_numbers');
            }
            if (! Schema::hasColumn('program_inbound_numbers', 'media_gateway_id')) {
                $table->foreignId('media_gateway_id')->nullable()->after('landline_numbers')->constrained('media_gateways')->nullOnDelete();
            }
            if (! Schema::hasColumn('program_inbound_numbers', 'port')) {
                $table->string('port')->nullable()->after('media_gateway_id');
            }
            if (! Schema::hasColumn('program_inbound_numbers', 'network')) {
                $table->string('network')->nullable()->after('port');
            }
            if (! Schema::hasColumn('program_inbound_numbers', 'remarks')) {
                $table->text('remarks')->nullable()->after('network');
            }
        });
    }

    public function down(): void
    {
        Schema::table('program_inbound_numbers', function (Blueprint $table) {
            if (Schema::hasColumn('program_inbound_numbers', 'media_gateway_id')) {
                $table->dropConstrainedForeignId('media_gateway_id');
            }
            if (Schema::hasColumn('program_inbound_numbers', 'campaign_id')) {
                $table->dropConstrainedForeignId('campaign_id');
            }
            foreach (['mobile_numbers', 'landline_numbers', 'port', 'network', 'remarks'] as $column) {
                if (Schema::hasColumn('program_inbound_numbers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
