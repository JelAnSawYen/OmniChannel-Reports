<?php

namespace Tests\Feature;

use App\Models\MediaGateway;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessAndWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private UserType $systemType;
    private UserType $adminType;
    private UserType $standardType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\UserTypeSeeder']);
        $this->systemType = UserType::where('name', 'System Administrator')->firstOrFail();
        $this->adminType = UserType::where('name', 'Administrator')->firstOrFail();
        $this->standardType = UserType::where('name', 'Standard User')->firstOrFail();
    }

    private function user(UserType $type, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'user_type_id' => $type->id,
            'status' => 'Active',
        ], $overrides));
    }

    public function test_media_gateway_page_sets_page_key_for_javascript(): void
    {
        $this->actingAs($this->user($this->systemType));

        $this->get('/media-gateways')
            ->assertOk()
            ->assertSee('data-page="media-gateways"', false)
            ->assertSee('data-can-edit="1"', false);
    }

    public function test_displayed_sequence_is_continuous_after_delete(): void
    {
        $this->actingAs($this->user($this->systemType));

        $first = MediaGateway::create([
            'site_name' => 'A', 'site_code' => 'A1', 'ip_address' => '10.0.0.1',
            'username' => 'root', 'database' => 'asteriskcdrdb',
        ]);
        MediaGateway::create([
            'site_name' => 'B', 'site_code' => 'B1', 'ip_address' => '10.0.0.2',
            'username' => 'root', 'database' => 'asteriskcdrdb',
        ]);
        MediaGateway::create([
            'site_name' => 'C', 'site_code' => 'C1', 'ip_address' => '10.0.0.3',
            'username' => 'root', 'database' => 'asteriskcdrdb',
        ]);

        $this->deleteJson('/media-gateways/'.$first->id)->assertOk();

        $json = $this->getJson('/media-gateways?sort_by=id&sort_dir=asc')->assertOk()->json();

        $this->assertSame(2, $json['pagination']['total']);
        $this->assertCount(2, $json['records']);
        $this->assertSame(['B1', 'C1'], array_column($json['records'], 'site_code'));
        $this->assertArrayNotHasKey('display_id', $json['records'][0]);
        $this->assertNotEmpty($json['records'][0]['id']);
    }

    public function test_system_admin_can_manage_users_roles_and_maintenance(): void
    {
        $admin = $this->user($this->systemType);
        $this->actingAs($admin);

        $this->get('/users')->assertOk();
        $this->get('/users/create')->assertOk();
        $this->post('/users', [
            'name' => 'Ops User',
            'email' => 'ops@example.com',
            'password' => 'Password123!Aa',
            'password_confirmation' => 'Password123!Aa',
            'user_type_id' => $this->standardType->id,
            'status' => 'Active',
        ])->assertRedirect(route('users.index'));

        $created = User::where('email', 'ops@example.com')->firstOrFail();
        $this->get('/users/'.$created->id.'/edit')->assertOk();
        $this->put('/users/'.$created->id, [
            'name' => 'Ops User Updated',
            'email' => 'ops@example.com',
            'user_type_id' => $this->standardType->id,
            'status' => 'Active',
        ])->assertRedirect(route('users.index'));
        $this->delete('/users/'.$created->id)->assertRedirect(route('users.index'));

        $this->get('/user-types')->assertOk();
        $this->get('/maintenance')->assertOk();
        $this->get('/dashboard')->assertOk()->assertSee('System Health');
        $this->get('/reports')->assertOk();
        $this->post('/channel-prefix', [
            'prefix' => '63',
            'channel' => 'SIP',
            'status' => 'Active',
        ])->assertRedirect();
        $this->post('/channel-prefix', [
            'prefix' => '63',
            'channel' => 'SIP',
            'status' => 'Active',
        ])->assertSessionHasErrors('prefix');
    }

    public function test_administrator_can_manage_administrators_and_standard_users(): void
    {
        $system = $this->user($this->systemType, ['name' => 'Hidden Sysadmin', 'email' => 'hidden.sysadmin@example.com']);
        $this->actingAs($this->user($this->adminType));

        $this->get('/media-gateways')->assertOk();
        $this->get('/telco-cost')->assertOk();
        $this->get('/users')->assertOk()->assertDontSee('hidden.sysadmin@example.com');
        $this->get('/users/create')->assertOk()->assertSee('Standard User')->assertSee('Administrator')->assertDontSee('System Administrator');
        $this->post('/users', [
            'name' => 'Ops Standard',
            'email' => 'ops.standard@example.com',
            'password' => 'Password123!Aa',
            'password_confirmation' => 'Password123!Aa',
            'user_type_id' => $this->standardType->id,
            'status' => 'Active',
        ])->assertRedirect(route('users.index'));

        $created = User::where('email', 'ops.standard@example.com')->firstOrFail();
        $this->put('/users/'.$created->id, [
            'name' => 'Ops Standard Updated',
            'email' => 'ops.standard@example.com',
            'user_type_id' => $this->standardType->id,
            'status' => 'Active',
        ])->assertRedirect(route('users.index'));
        $this->delete('/users/'.$created->id)->assertRedirect(route('users.index'));
        $this->assertDatabaseMissing('users', ['email' => 'ops.standard@example.com']);

        $this->post('/users', [
            'name' => 'Peer Admin',
            'email' => 'peer.admin@example.com',
            'password' => 'Password123!Aa',
            'password_confirmation' => 'Password123!Aa',
            'user_type_id' => $this->adminType->id,
            'status' => 'Active',
        ])->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', ['email' => 'peer.admin@example.com']);

        $this->post('/users', [
            'name' => 'Nope System',
            'email' => 'nope.system@example.com',
            'password' => 'Password123!Aa',
            'password_confirmation' => 'Password123!Aa',
            'user_type_id' => $this->systemType->id,
            'status' => 'Active',
        ])->assertRedirect();
        $this->assertDatabaseMissing('users', ['email' => 'nope.system@example.com']);
        $this->assertTrue($system->fresh()->isSystemAdministrator());

        $this->get('/user-types/create')->assertForbidden();
        $this->post('/user-types', [
            'name' => 'Hacker',
            'permissions' => ['users.manage'],
        ])->assertForbidden();
        $this->get('/maintenance')->assertForbidden();
        $this->post('/maintenance/backup')->assertForbidden();
        $this->get('/media-gateways/export')->assertOk();
        $this->get('/reports')->assertOk();
        $this->get('/reports/export/telco')->assertOk();
    }

    public function test_standard_user_is_view_and_export_only(): void
    {
        $this->actingAs($this->user($this->standardType));

        $gateway = MediaGateway::create([
            'site_name' => 'A', 'site_code' => 'STD1', 'ip_address' => '10.1.1.1',
            'username' => 'root', 'database' => 'asteriskcdrdb',
        ]);

        $this->get('/dashboard')->assertOk()->assertSee('System Health');
        $this->get('/reports')->assertOk();
        $this->get('/reports/export/inventory')->assertOk();
        $this->get('/media-gateways/export')->assertOk();
        $this->get('/telco-cost')->assertOk();
        $this->get('/channel-prefix/export')->assertOk();
        $this->post('/channel-prefix', [
            'prefix' => '63',
            'channel' => 'SIP',
            'status' => 'Active',
        ])->assertForbidden();
        $this->postJson('/media-gateways', [
            'site_name' => 'Blocked',
            'site_code' => 'BLK1',
            'ip_address' => '10.1.1.2',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ])->assertForbidden();
        $this->putJson('/media-gateways/'.$gateway->id, [
            'site_name' => 'Blocked',
            'site_code' => 'STD1',
            'ip_address' => '10.1.1.1',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ])->assertForbidden();
        $this->deleteJson('/media-gateways/'.$gateway->id)->assertForbidden();
        $this->post('/media-gateways/import', [])->assertStatus(405);
        $this->post('/telco-cost', [
            'provider' => 'Globe',
            'site' => 'MKT',
            'service_type' => 'SIP',
            'monthly_cost' => 100,
            'status' => 'Active',
        ])->assertForbidden();
        $this->get('/users')->assertForbidden();
        $this->post('/users', [
            'name' => 'Nope',
            'email' => 'nope2@example.com',
            'password' => 'Password123!Aa',
            'password_confirmation' => 'Password123!Aa',
            'user_type_id' => $this->standardType->id,
            'status' => 'Active',
        ])->assertForbidden();
        $this->get('/user-types')->assertForbidden();
        $this->get('/maintenance')->assertForbidden();
        $this->get('/activity-logs')->assertForbidden();
    }

    public function test_user_without_export_permission_cannot_export(): void
    {
        $type = UserType::create([
            'name' => 'Viewer Only',
            'permissions' => ['dashboard.view', 'media.view'],
        ]);
        $this->actingAs($this->user($type));

        $this->get('/media-gateways/export')->assertForbidden();
        $this->get('/reports')->assertOk();
        $this->get('/reports/export/inventory')->assertForbidden();
    }

    public function test_activity_logs_render_complete_descriptions(): void
    {
        $this->actingAs($this->user($this->systemType));

        \App\Models\AuditLog::create([
            'action' => 'Added',
            'module' => 'Media Gateways',
            'description' => 'Added gateway LONGCODE-WITH-EXTRA-DETAIL-THAT-MUST-NOT-BE-CUT',
            'ip_address' => '127.0.0.1',
        ]);

        $this->get('/activity-logs')
            ->assertOk()
            ->assertSee('class="activity-description"', false)
            ->assertSee('Added gateway LONGCODE-WITH-EXTRA-DETAIL-THAT-MUST-NOT-BE-CUT')
            ->assertSee('activity-log-table', false);
    }

    public function test_last_system_administrator_role_cannot_be_removed(): void
    {
        $admin = $this->user($this->systemType);
        $this->actingAs($admin);

        $this->put('/users/'.$admin->id, [
            'name' => $admin->name,
            'email' => $admin->email,
            'user_type_id' => $this->standardType->id,
            'status' => 'Active',
        ])->assertRedirect();

        $this->assertTrue($admin->fresh()->isSystemAdministrator());
    }
}
