<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('notification_recipients');
        Schema::dropIfExists('notifications');
    }

    public function down(): void
    {
        // In-app notification tables were removed from the project.
    }
};
