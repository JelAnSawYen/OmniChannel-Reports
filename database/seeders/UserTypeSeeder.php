<?php
namespace Database\Seeders;
use App\Models\UserType;
use App\Support\RolePermissions;
use Illuminate\Database\Seeder;
class UserTypeSeeder extends Seeder
{
    public function run(): void
    {
        $defaults=[
            ['name'=>UserType::ADMINISTRATOR,'description'=>'Can manage and edit operational system data','permissions'=>RolePermissions::ADMINISTRATOR_PERMISSIONS],
            ['name'=>UserType::STANDARD_USER,'description'=>'View-only system access','permissions'=>RolePermissions::STANDARD_USER_PERMISSIONS],
        ];
        foreach($defaults as $data)UserType::updateOrCreate(['name'=>$data['name']],$data);
    }
}
