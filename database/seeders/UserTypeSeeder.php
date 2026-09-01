<?php
namespace Database\Seeders;
use App\Http\Controllers\UserTypeController;
use App\Models\UserType;
use Illuminate\Database\Seeder;
class UserTypeSeeder extends Seeder
{
    public function run(): void
    {
        $defaults=[
            ['name'=>'System Administrator','description'=>'Full system access','permissions'=>array_keys(UserTypeController::PERMISSIONS)],
            ['name'=>'Administrator','description'=>'Can manage and edit operational system data','permissions'=>['dashboard.view','media.view','media.create','media.edit','media.delete','media.export','users.view','users.manage','roles.view','roles.manage','logs.view']],
            ['name'=>'Standard User','description'=>'View-only system access','permissions'=>['dashboard.view','media.view','media.export']],
        ];
        foreach($defaults as $data)UserType::updateOrCreate(['name'=>$data['name']],$data);
    }
}
