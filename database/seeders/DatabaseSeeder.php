<?php
namespace Database\Seeders;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Database\Seeder;
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([UserTypeSeeder::class,MediaGatewaySeeder::class,OperationsInventorySeeder::class,ChannelAllocationSeeder::class]);
        if (! app()->environment(['local', 'testing'])) {
            return;
        }
        $accounts = [
            ['email' => 'test@example.com', 'name' => 'System Administrator', 'type' => 'System Administrator'],
            ['email' => 'admin@example.com', 'name' => 'Administrator', 'type' => 'Administrator'],
            ['email' => 'user@example.com', 'name' => 'Standard User', 'type' => 'Standard User'],
        ];
        foreach ($accounts as $account) {
            $type = UserType::where('name', $account['type'])->first();
            User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => 'Password123!Aa',
                    'email_verified_at' => now(),
                    'user_type_id' => $type?->id,
                    'status' => 'Active',
                ]
            );
        }
    }
}
