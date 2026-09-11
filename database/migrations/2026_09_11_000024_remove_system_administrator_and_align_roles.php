<?php

use App\Models\User;
use App\Models\UserType;
use App\Support\RolePermissions;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $admin = UserType::where('name', UserType::ADMINISTRATOR)->first();
        $system = UserType::where('name', 'System Administrator')->first();

        if ($admin && $system) {
            User::where('user_type_id', $system->id)->update(['user_type_id' => $admin->id]);
            $system->delete();
        } elseif ($system && ! $admin) {
            $system->update([
                'name' => UserType::ADMINISTRATOR,
                'description' => 'Can manage and edit operational system data',
                'permissions' => RolePermissions::ADMINISTRATOR_PERMISSIONS,
            ]);
            $admin = $system->fresh();
        }

        if ($admin) {
            $admin->update([
                'description' => 'Can manage and edit operational system data',
                'permissions' => RolePermissions::ADMINISTRATOR_PERMISSIONS,
            ]);
        }

        $standard = UserType::where('name', UserType::STANDARD_USER)->first();
        if ($standard) {
            $standard->update([
                'description' => 'View-only system access',
                'permissions' => RolePermissions::STANDARD_USER_PERMISSIONS,
            ]);
        }
    }

    public function down(): void
    {
        // System Administrator was removed permanently and is not restored.
    }
};
