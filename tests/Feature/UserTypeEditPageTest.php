<?php

namespace Tests\Feature;

use App\Http\Controllers\UserTypeController;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTypeEditPageTest extends TestCase
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

    public function test_edit_titles_match_each_user_type(): void
    {
        $actor = $this->user($this->systemType);

        $this->actingAs($actor)->get('/user-types/'.$this->standardType->id.'/edit')
            ->assertOk()
            ->assertSee('Edit User Type: Standard User')
            ->assertDontSee('User Type Name');

        $this->actingAs($actor)->get('/user-types/'.$this->adminType->id.'/edit')
            ->assertOk()
            ->assertSee('Edit User Type: Administrator');

        $this->actingAs($actor)->get('/user-types/'.$this->systemType->id.'/edit')
            ->assertOk()
            ->assertSee('Edit User Type: System Administrator');
    }

    public function test_selecting_a_user_loads_that_users_permissions(): void
    {
        $actor = $this->user($this->systemType);
        $alice = $this->user($this->standardType, ['name' => 'Alice Assigned', 'email' => 'alice.assigned@example.com']);
        $bob = $this->user($this->standardType, ['name' => 'Bob Assigned', 'email' => 'bob.assigned@example.com']);
        $bob->update(['permissions' => ['media.create', 'logs.view']]);

        $aliceHtml = $this->actingAs($actor)->get('/user-types/'.$this->standardType->id.'/edit?user='.$alice->id)
            ->assertOk()
            ->assertSee('Alice Assigned')
            ->assertSee('Add / Create Records')
            ->getContent();
        $this->assertSame(7, substr_count($aliceHtml, 'name="permissions[]"'));
        $this->assertSame(1, substr_count($aliceHtml, 'value="media.export" checked'));
        $this->assertSame(0, substr_count($aliceHtml, 'value="media.create" checked'));

        $bobHtml = $this->actingAs($actor)->get('/user-types/'.$this->standardType->id.'/edit?user='.$bob->id)
            ->assertOk()
            ->getContent();
        $this->assertSame(1, substr_count($bobHtml, 'value="media.create" checked'));
        $this->assertSame(1, substr_count($bobHtml, 'value="logs.view" checked'));
        $this->assertSame(0, substr_count($bobHtml, 'value="media.export" checked'));
    }

    public function test_assigned_users_search_and_pagination(): void
    {
        $actor = $this->user($this->systemType);
        $this->user($this->standardType, ['name' => 'Alice Assigned', 'email' => 'alice.assigned@example.com']);
        $this->user($this->standardType, ['name' => 'Bob Assigned', 'email' => 'bob.assigned@example.com']);
        User::factory()->count(9)->create([
            'user_type_id' => $this->standardType->id,
            'status' => 'Active',
        ]);
        $this->user($this->adminType, ['name' => 'Other Role User', 'email' => 'other.role@example.com']);

        $this->actingAs($actor)->get('/user-types/'.$this->standardType->id.'/edit?search=Alice')
            ->assertOk()
            ->assertSee('Alice Assigned')
            ->assertDontSee('Bob Assigned')
            ->assertDontSee('Other Role User')
            ->assertSee('Showing 1 to 1 of 1 results');

        $this->actingAs($actor)->get('/user-types/'.$this->standardType->id.'/edit')
            ->assertOk()
            ->assertSee('Showing 1 to 10 of 11 results');

        $this->actingAs($actor)->get('/user-types/'.$this->standardType->id.'/edit?page=2')
            ->assertOk()
            ->assertSee('Showing 11 to 11 of 11 results');
    }

    public function test_save_updates_only_the_selected_user_permissions(): void
    {
        $actor = $this->user($this->systemType);
        $alice = $this->user($this->standardType, ['name' => 'Alice Assigned', 'email' => 'alice.save@example.com']);
        $bob = $this->user($this->standardType, ['name' => 'Bob Assigned', 'email' => 'bob.save@example.com']);
        $adminUser = $this->user($this->adminType, ['name' => 'Admin Neighbor', 'email' => 'admin.neighbor@example.com']);
        $typeBefore = $this->standardType->fresh()->permissions;
        $adminTypeBefore = $this->adminType->fresh()->permissions;
        $bobTypeId = $bob->user_type_id;

        $this->actingAs($actor)->put('/user-types/'.$this->standardType->id, [
            'user_id' => $alice->id,
            'permissions' => ['media.create', 'media.export', 'logs.view'],
        ])->assertRedirect()->assertSessionHas('success');

        $alice = $alice->fresh();
        $this->assertContains('media.create', $alice->permissions);
        $this->assertContains('media.export', $alice->permissions);
        $this->assertContains('logs.view', $alice->permissions);
        $this->assertNotContains('users.manage', $alice->permissions);
        $this->assertTrue($alice->hasPermission('media.create'));
        $this->assertTrue($alice->hasPermission('dashboard.view'));
        $this->assertFalse($alice->hasPermission('users.manage'));
        $this->assertSame($this->standardType->id, $alice->user_type_id);

        $this->assertNull($bob->fresh()->permissions);
        $this->assertFalse($bob->fresh()->hasPermission('media.create'));
        $this->assertSame($bobTypeId, $bob->fresh()->user_type_id);
        $this->assertNull($adminUser->fresh()->permissions);
        $this->assertSame($typeBefore, $this->standardType->fresh()->permissions);
        $this->assertSame($adminTypeBefore, $this->adminType->fresh()->permissions);
        $this->assertSame(array_keys(UserTypeController::PERMISSIONS), $this->systemType->fresh()->permissions);
    }

    public function test_save_rejects_users_from_another_user_type(): void
    {
        $actor = $this->user($this->systemType);
        $adminUser = $this->user($this->adminType);
        $typeBefore = $this->standardType->fresh()->permissions;

        $this->actingAs($actor)->put('/user-types/'.$this->standardType->id, [
            'user_id' => $adminUser->id,
            'permissions' => ['media.create'],
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertNull($adminUser->fresh()->permissions);
        $this->assertSame($typeBefore, $this->standardType->fresh()->permissions);
    }

    public function test_cancel_discards_unsaved_changes(): void
    {
        $actor = $this->user($this->systemType);
        $alice = $this->user($this->standardType, ['name' => 'Alice Assigned', 'email' => 'alice.cancel@example.com']);

        $this->actingAs($actor)->get('/user-types/'.$this->standardType->id.'/edit?user='.$alice->id)
            ->assertOk()
            ->assertSee('Cancel')
            ->assertSee('href="'.route('user-types.edit', ['userType' => $this->standardType, 'user' => $alice->id]).'"', false);

        $this->assertNull($alice->fresh()->permissions);
        $this->assertSame($this->standardType->permissions, $this->standardType->fresh()->permissions);
    }

    public function test_administrator_can_change_another_administrator_permissions_without_changing_the_type(): void
    {
        $admin = $this->user($this->adminType);
        $peer = $this->user($this->adminType, ['name' => 'Peer Admin', 'email' => 'peer.admin.perms@example.com']);
        $typeBefore = $this->adminType->fresh()->permissions;

        $this->actingAs($admin)->put('/user-types/'.$this->adminType->id, [
            'user_id' => $peer->id,
            'permissions' => ['media.create', 'logs.view'],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame($typeBefore, $this->adminType->fresh()->permissions);
        $this->assertSame($this->adminType->id, $peer->fresh()->user_type_id);
        $this->assertTrue($peer->fresh()->hasPermission('media.create'));
        $this->assertTrue($peer->fresh()->hasPermission('logs.view'));
        $this->assertFalse($peer->fresh()->hasPermission('users.manage'));

        $html = $this->actingAs($this->user($this->systemType))
            ->get('/user-types/'.$this->adminType->id.'/edit?user='.$peer->id)
            ->assertOk()
            ->getContent();
        $this->assertSame(1, substr_count($html, 'value="media.create" checked'));
        $this->assertSame(1, substr_count($html, 'value="logs.view" checked'));
        $this->assertSame(0, substr_count($html, 'value="users.manage" checked'));
    }

    public function test_administrator_cannot_view_or_change_system_administrator_permissions(): void
    {
        $admin = $this->user($this->adminType);
        $system = $this->user($this->systemType, ['name' => 'Root Admin', 'email' => 'root.admin@example.com']);

        $this->actingAs($admin)->get('/users')->assertOk()->assertDontSee('root.admin@example.com');
        $this->actingAs($admin)->get('/users/'.$system->id.'/edit')->assertRedirect(route('users.index'));
        $this->actingAs($admin)->get('/user-types/'.$this->systemType->id.'/edit')->assertRedirect(route('user-types.index'));
        $this->actingAs($admin)->put('/user-types/'.$this->systemType->id, [
            'user_id' => $system->id,
            'permissions' => [],
        ])->assertRedirect(route('user-types.index'));
        $this->assertNull($system->fresh()->permissions);
        $this->assertTrue($system->fresh()->hasPermission('users.manage'));
    }

    public function test_standard_user_manage_permissions_are_locked_and_ignored(): void
    {
        $admin = $this->user($this->adminType);
        $system = $this->user($this->systemType);
        $standard = $this->user($this->standardType, ['name' => 'Locked Standard', 'email' => 'locked.standard@example.com']);

        $adminHtml = $this->actingAs($admin)->get('/user-types/'.$this->standardType->id.'/edit?user='.$standard->id)
            ->assertOk()
            ->getContent();
        $this->assertMatchesRegularExpression('/value="users\.manage"[^>]*disabled/', $adminHtml);
        $this->assertMatchesRegularExpression('/value="roles\.manage"[^>]*disabled/', $adminHtml);
        $this->assertDoesNotMatchRegularExpression('/value="media\.create"[^>]*disabled/', $adminHtml);
        $this->assertDoesNotMatchRegularExpression('/value="logs\.view"[^>]*disabled/', $adminHtml);

        $this->actingAs($admin)->put('/user-types/'.$this->standardType->id, [
            'user_id' => $standard->id,
            'permissions' => ['media.create', 'media.edit', 'media.export', 'media.delete', 'logs.view', 'users.manage', 'roles.manage'],
        ])->assertRedirect()->assertSessionHas('success');

        $standard = $standard->fresh();
        $this->assertSame($this->standardType->id, $standard->user_type_id);
        $this->assertNotContains('users.manage', $standard->permissions ?? []);
        $this->assertNotContains('roles.manage', $standard->permissions ?? []);
        $this->assertFalse($standard->hasPermission('users.manage'));
        $this->assertFalse($standard->hasPermission('roles.manage'));
        $this->assertTrue($standard->hasPermission('media.create'));
        $this->assertTrue($standard->hasPermission('logs.view'));

        $systemHtml = $this->actingAs($system)->get('/user-types/'.$this->standardType->id.'/edit?user='.$standard->id)
            ->assertOk()
            ->getContent();
        $this->assertSame(1, substr_count($systemHtml, 'value="media.create" checked'));
        $this->assertSame(1, substr_count($systemHtml, 'value="logs.view" checked'));
        $this->assertSame(0, substr_count($systemHtml, 'value="users.manage" checked'));
        $this->assertSame(0, substr_count($systemHtml, 'value="roles.manage" checked'));
        $this->assertMatchesRegularExpression('/value="users\.manage"[^>]*disabled/', $systemHtml);

        $this->actingAs($admin)->put('/user-types/'.$this->standardType->id, [
            'user_id' => $standard->id,
            'permissions' => ['media.export'],
        ])->assertRedirect()->assertSessionHas('success');

        $standard = $standard->fresh();
        $this->assertFalse($standard->hasPermission('media.create'));
        $this->assertTrue($standard->hasPermission('media.export'));

        $refreshHtml = $this->actingAs($system)->get('/user-types/'.$this->standardType->id.'/edit?user='.$standard->id)
            ->assertOk()
            ->getContent();
        $this->assertSame(0, substr_count($refreshHtml, 'value="media.create" checked'));
        $this->assertSame(1, substr_count($refreshHtml, 'value="media.export" checked'));

        $this->actingAs($standard)->get('/users')->assertForbidden();
        $this->actingAs($standard)->get('/user-types')->assertForbidden();
        $this->actingAs($standard)->postJson('/media-gateways', [
            'site_name' => 'Locked Out',
            'site_code' => 'LCK1',
            'ip_address' => '10.8.8.8',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ])->assertForbidden();
    }

    public function test_administrator_permissions_remain_editable_including_manage_users(): void
    {
        $admin = $this->user($this->adminType);
        $peer = $this->user($this->adminType, ['name' => 'Editable Admin', 'email' => 'editable.admin@example.com']);

        $html = $this->actingAs($admin)->get('/user-types/'.$this->adminType->id.'/edit?user='.$peer->id)
            ->assertOk()
            ->getContent();
        $this->assertDoesNotMatchRegularExpression('/value="users\.manage"[^>]*disabled/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="roles\.manage"[^>]*disabled/', $html);

        $this->actingAs($admin)->put('/user-types/'.$this->adminType->id, [
            'user_id' => $peer->id,
            'permissions' => ['media.export', 'users.manage', 'roles.manage'],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertTrue($peer->fresh()->hasPermission('users.manage'));
        $this->assertTrue($peer->fresh()->hasPermission('roles.manage'));
        $this->assertFalse($peer->fresh()->hasPermission('media.create'));
    }

    public function test_standard_user_cannot_manage_user_types_even_with_override(): void
    {
        $standard = $this->user($this->standardType);
        $standard->update(['permissions' => ['users.manage', 'roles.manage', 'media.create']]);

        $this->actingAs($standard)->get('/users')->assertForbidden();
        $this->actingAs($standard)->get('/user-types')->assertForbidden();
        $this->actingAs($standard)->get('/user-types/'.$this->standardType->id.'/edit')->assertForbidden();
        $this->assertFalse($standard->fresh()->hasPermission('users.manage'));
        $this->assertFalse($standard->fresh()->hasPermission('roles.manage'));
        $this->assertTrue($standard->fresh()->hasPermission('media.create'));
    }

    public function test_granting_and_revoking_media_create_is_enforced(): void
    {
        $admin = $this->user($this->adminType);
        $standard = $this->user($this->standardType, ['email' => 'grant.revoke@example.com']);
        $payload = [
            'site_name' => 'Granted Site',
            'site_code' => 'GRT1',
            'ip_address' => '10.9.9.9',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ];

        $this->actingAs($standard)->postJson('/media-gateways', $payload)->assertForbidden();

        $this->actingAs($admin)->put('/user-types/'.$this->standardType->id, [
            'user_id' => $standard->id,
            'permissions' => ['media.create', 'media.export'],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertTrue($standard->fresh()->hasPermission('media.create'));
        $this->actingAs($standard->fresh())->postJson('/media-gateways', $payload)->assertCreated();

        $this->actingAs($admin)->put('/user-types/'.$this->standardType->id, [
            'user_id' => $standard->id,
            'permissions' => ['media.export'],
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertFalse($standard->fresh()->hasPermission('media.create'));
        $this->actingAs($standard->fresh())->postJson('/media-gateways', [
            'site_name' => 'Revoked Site',
            'site_code' => 'REV1',
            'ip_address' => '10.9.9.10',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ])->assertForbidden();
    }
}
