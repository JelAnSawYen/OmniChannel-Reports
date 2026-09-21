<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Program Inbound Number uniqueness is campaign-scoped.
     * Existing rows and IDs are kept.
     */
    public function up(): void
    {
        if (! Schema::hasTable('program_inbound_numbers')) {
            return;
        }

        Schema::table('program_inbound_numbers', function (Blueprint $table): void {
            if (Schema::hasIndex('program_inbound_numbers', 'program_inbound_numbers_number_unique')) {
                $table->dropUnique('program_inbound_numbers_number_unique');
            }
        });

        if (! Schema::hasIndex('program_inbound_numbers', 'program_inbound_numbers_campaign_number_unique')) {
            Schema::table('program_inbound_numbers', function (Blueprint $table): void {
                $table->unique(['campaign_id', 'number'], 'program_inbound_numbers_campaign_number_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('program_inbound_numbers')) {
            return;
        }

        Schema::table('program_inbound_numbers', function (Blueprint $table): void {
            if (Schema::hasIndex('program_inbound_numbers', 'program_inbound_numbers_campaign_number_unique')) {
                $table->dropUnique('program_inbound_numbers_campaign_number_unique');
            }
        });

        if (! Schema::hasIndex('program_inbound_numbers', 'program_inbound_numbers_number_unique')) {
            Schema::table('program_inbound_numbers', function (Blueprint $table): void {
                $table->unique('number', 'program_inbound_numbers_number_unique');
            });
        }
    }
};
