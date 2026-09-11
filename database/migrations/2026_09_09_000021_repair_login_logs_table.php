<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->loginLogsAreUsable()) {
            return;
        }

        try {
            DB::statement('DROP TABLE IF EXISTS `login_logs`');
        } catch (Throwable) {
            Schema::dropIfExists('login_logs');
        }

        Schema::create('login_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('status');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Repair-only. Do not drop a healthy login_logs table on rollback.
    }

    private function loginLogsAreUsable(): bool
    {
        if (! Schema::hasTable('login_logs')) {
            return false;
        }

        try {
            DB::table('login_logs')->limit(1)->count();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
};
