<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdc_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')
                ->nullable()
                ->constrained('channel_allocation_campaigns')
                ->nullOnDelete();
            $table->string('location')->nullable();
            $table->date('date_endorse')->nullable();
            $table->text('dns')->nullable();
            $table->timestamps();
            $table->unique('campaign_id');
        });

        Schema::table('pdc_servers', function (Blueprint $table) {
            $table->foreignId('pdc_group_id')->nullable()->constrained('pdc_groups')->cascadeOnDelete();
            $table->string('os')->nullable();
            $table->string('ram')->nullable();
            $table->string('cpu')->nullable();
            $table->string('storage')->nullable();
            $table->string('admin_username')->nullable();
            $table->text('password')->nullable();
            $table->text('sql_db_password')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pdc_servers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pdc_group_id');
            $table->dropColumn([
                'os',
                'ram',
                'cpu',
                'storage',
                'admin_username',
                'password',
                'sql_db_password',
            ]);
        });

        Schema::dropIfExists('pdc_groups');
    }
};
