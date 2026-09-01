<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('media_gateways', function (Blueprint $table) {
            $table->string('status')->default('Unknown')->after('database');
            $table->timestamp('last_checked_at')->nullable()->after('status');
            $table->unsignedInteger('response_ms')->nullable()->after('last_checked_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('status')->default('Active')->after('user_type_id');
            $table->timestamp('last_login_at')->nullable()->after('status');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
        });

        Schema::table('user_types', function (Blueprint $table) {
            $table->text('permissions')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('media_gateways', function (Blueprint $table) {
            $table->dropColumn(['status', 'last_checked_at', 'response_ms']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['status', 'last_login_at', 'last_login_ip']);
        });
        Schema::table('user_types', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }
};
