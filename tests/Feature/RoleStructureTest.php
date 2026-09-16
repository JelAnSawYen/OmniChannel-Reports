<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleStructureTest extends TestCase
{
    use RefreshDatabase;

    private UserType $adminType;
    private UserType $standardType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\UserTypeSeeder']);
        $this->adminType = UserType::where('name', 'Administrator')->firstOrFail();
        $this->standardType = UserType::where('name', 'Standard User')->firstOrFail();
    }

    private function user(UserType $type, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'user_type_id' => $type->id,
            'status' => 'Active',
            'password' => 'Password123!Aa',
        ], $overrides));
    }

    public function test_only_administrator_and_standard_user_roles_exist(): void
    {
        $this->assertSame(
            ['Administrator', 'Standard User'],
            UserType::orderBy('name')->pluck('name')->all()
        );
        $this->assertDatabaseMissing('user_types', ['name' => 'System Administrator']);
    }

    public function test_administrator_login_and_user_management_open_existing_users_page(): void
    {
        $admin = $this->user($this->adminType);
        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'Password123!Aa',
        ])->assertRedirect(route('dashboard'));

        $html = $this->actingAs($admin)->get('/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString('User Management', $html);
        $this->assertStringContainsString('href="'.route('users.index').'"', $html);
        $this->assertStringContainsString('accountSettingsToggle', $html);
        $this->assertStringContainsString('account-group-caret', $html);
        $this->assertStringNotContainsString('accountUserManagementToggle', $html);
        $this->assertStringNotContainsString('>Administration</div>', $html);
        $this->assertStringNotContainsString('User Types', $html);
        $this->assertStringNotContainsString('>Users</a>', $html);

        $this->actingAs($admin)->get('/users')
            ->assertOk()
            ->assertSee('Users')
            ->assertSee('Manage system accounts, status, and access levels.');
    }

    public function test_administrator_can_crud_users_and_assign_only_two_roles(): void
    {
        $admin = $this->user($this->adminType);
        $this->actingAs($admin);

        $this->get('/users/create')
            ->assertOk()
            ->assertSee('Standard User')
            ->assertSee('Administrator')
            ->assertDontSee('System Administrator');

        $this->post('/users', [
            'name' => 'Ops User',
            'email' => 'ops.user@example.com',
            'password' => 'Password123!Aa',
            'password_confirmation' => 'Password123!Aa',
            'user_type_id' => $this->standardType->id,
            'status' => 'Active',
        ])->assertRedirect(route('users.index'));

        $created = User::where('email', 'ops.user@example.com')->firstOrFail();
        $this->assertTrue($created->isStandardUser());

        $this->get('/users/'.$created->id.'/edit')->assertOk();
        $this->put('/users/'.$created->id, [
            'name' => 'Ops Admin',
            'email' => 'ops.user@example.com',
            'user_type_id' => $this->adminType->id,
            'status' => 'Active',
        ])->assertRedirect(route('users.index'));
        $this->assertTrue($created->fresh()->isAdministrator());

        $this->delete('/users/'.$created->id)->assertRedirect(route('users.index'));
        $this->assertDatabaseMissing('users', ['email' => 'ops.user@example.com']);
    }

    public function test_system_administrator_cannot_be_assigned(): void
    {
        $admin = $this->user($this->adminType);
        $stale = UserType::create([
            'name' => 'System Administrator',
            'description' => 'Removed role',
            'permissions' => ['dashboard.view'],
        ]);

        $this->actingAs($admin)->post('/users', [
            'name' => 'Nope System',
            'email' => 'nope.system@example.com',
            'password' => 'Password123!Aa',
            'password_confirmation' => 'Password123!Aa',
            'user_type_id' => $stale->id,
            'status' => 'Active',
        ])->assertRedirect();
        $this->assertDatabaseMissing('users', ['email' => 'nope.system@example.com']);

        $this->get('/users/create')->assertDontSee('System Administrator');
    }

    public function test_user_types_module_no_longer_exists(): void
    {
        $admin = $this->user($this->adminType);
        $this->actingAs($admin)->get('/user-types')->assertNotFound();
        $this->actingAs($admin)->get('/user-types/create')->assertNotFound();
        $this->actingAs($admin)->post('/user-types', ['name' => 'Hacker'])->assertNotFound();
    }

    public function test_standard_user_can_access_only_allowed_modules(): void
    {
        $user = $this->user($this->standardType);
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!Aa',
        ])->assertRedirect(route('dashboard'));

        $this->actingAs($user);
        $dashboard = $this->get('/dashboard')->assertOk();
        $dashboard->assertSee('Dashboard')
            ->assertSee('href="'.url('/campaigns').'"', false)
            ->assertSee('href="'.url('/gsm-gateways').'"', false)
            ->assertSee('href="'.url('/channel-allocation').'"', false)
            ->assertDontSee('User Management')
            ->assertDontSee('href="'.url('/pdc-servers').'"', false)
            ->assertDontSee('href="'.url('/sip-channels').'"', false)
            ->assertDontSee('href="'.url('/archive-recordings').'"', false)
            ->assertDontSee('href="'.url('/program-inbound-numbers').'"', false)
            ->assertDontSee('href="'.url('/signal-boosters').'"', false)
            ->assertDontSee('href="'.url('/defective-gsm').'"', false)
            ->assertDontSee('href="'.url('/program-location').'"', false)
            ->assertSee('id="networkGroup"', false)
            ->assertSee('href="'.url('/globe-sim').'"', false)
            ->assertSee('href="'.url('/smart-sim').'"', false);

        $this->get('/campaigns')->assertOk();
        $this->get('/gsm-gateways')->assertOk();
        $this->get('/channel-allocation')->assertOk();
        $this->get('/globe-sim')->assertOk()->assertSee('Globe SIM');
        $this->get('/smart-sim')->assertOk()->assertSee('Smart SIM');
        $this->get('/channel-utilization')->assertOk()->assertSee('Channel Utilization');
        $this->get('/reports')->assertNotFound();
    }

    public function test_standard_user_is_denied_restricted_pages_and_actions(): void
    {
        $user = $this->user($this->standardType);
        $this->actingAs($user);

        foreach ([
            '/users',
            '/users/create',
            '/pdc-servers',
            '/sip-channels',
            '/channel-range-list',
            '/archive-recordings',
            '/program-inbound-numbers',
            '/signal-boosters',
            '/defective-gsm',
            '/program-location',
            '/system-health',
            '/activity-logs',
            '/login-history',
            '/maintenance',
            '/telco-cost',
        ] as $url) {
            $this->get($url)->assertForbidden();
        }

        $this->get('/reports')->assertNotFound();
        $this->get('/reports/export/xlsx')->assertNotFound();

        $this->post('/users', [
            'name' => 'Nope',
            'email' => 'nope@example.com',
            'password' => 'Password123!Aa',
            'password_confirmation' => 'Password123!Aa',
            'user_type_id' => $this->standardType->id,
            'status' => 'Active',
        ])->assertForbidden();

        $this->post('/pdc-servers', [
            'campaign_id' => 1,
            'location' => 'Estancia',
        ])->assertForbidden();

        $this->get('/user-types')->assertNotFound();
    }
}
