<?php

use App\Http\Controllers\UserTypeController;
use App\Models\UserType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $system = UserType::where('name', 'System Administrator')->first();
        if ($system) {
            $system->update(['permissions' => array_keys(UserTypeController::PERMISSIONS)]);
        }

        $admin = UserType::where('name', 'Administrator')->first();
        if ($admin) {
            $admin->update([
                'description' => 'Can manage and edit operational system data',
                'permissions' => [
                    'dashboard.view',
                    'media.view',
                    'media.create',
                    'media.edit',
                    'media.delete',
                    'media.import',
                    'media.export',
                    'users.view',
                    'users.manage',
                    'roles.view',
                    'roles.manage',
                    'logs.view',
                ],
            ]);
        }

        $standard = UserType::where('name', 'Standard User')->first();
        if ($standard) {
            $standard->update([
                'description' => 'View-only system access',
                'permissions' => [
                    'dashboard.view',
                    'media.view',
                    'media.export',
                ],
            ]);
        }
    }

    public function down(): void
    {
        // Role restriction alignment is not reversed.
    }
};
