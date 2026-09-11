<?php

use App\Models\UserType;
use App\Support\RolePermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('media_gateways')) {
            $columns = array_values(array_filter([
                Schema::hasColumn('media_gateways', 'status') ? 'status' : null,
                Schema::hasColumn('media_gateways', 'last_checked_at') ? 'last_checked_at' : null,
                Schema::hasColumn('media_gateways', 'response_ms') ? 'response_ms' : null,
            ]));

            if ($columns) {
                Schema::table('media_gateways', function (Blueprint $table) use ($columns) {
                    $table->dropColumn($columns);
                });
            }
        }

        $admin = UserType::where('name', 'Administrator')->first();
        if ($admin) {
            $permissions = array_values(array_unique(array_merge(
                $admin->permissions ?? [],
                [
                    'dashboard.view',
                    'media.view',
                    'media.create',
                    'media.edit',
                    'media.delete',
                    'media.import',
                    'media.export',
                    'users.view',
                    'roles.view',
                    'roles.manage',
                    'logs.view',
                ]
            )));
            $admin->update(['permissions' => $permissions]);
        }

        $standard = UserType::where('name', 'Standard User')->first();
        if ($standard) {
            $permissions = array_values(array_unique(array_merge(
                $standard->permissions ?? [],
                [
                    'dashboard.view',
                    'media.view',
                    'media.export',
                    'users.view',
                ]
            )));
            $standard->update(['permissions' => $permissions]);
        }
    }

    public function down(): void
    {
        // The status fields are intentionally not restored because Media Gateways
        // no longer use gateway-status functionality.
    }
};
