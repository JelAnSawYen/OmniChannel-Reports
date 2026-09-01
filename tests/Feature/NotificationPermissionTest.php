<?php

namespace Tests\Feature;

use App\Models\DefectiveGsm;
use App\Models\GlobeSim;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\PdcServer;
use App\Models\User;
use App\Models\UserType;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $system;
    private User $admin;
    private User $standard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\UserTypeSeeder']);
        $this->system = User::factory()->create([
            'user_type_id' => UserType::where('name', 'System Administrator')->value('id'),
            'status' => 'Active',
        ]);
        $this->admin = User::factory()->create([
            'user_type_id' => UserType::where('name', 'Administrator')->value('id'),
            'status' => 'Active',
        ]);
        $this->standard = User::factory()->create([
            'user_type_id' => UserType::where('name', 'Standard User')->value('id'),
            'status' => 'Active',
        ]);
    }

    private function alert(string $dedupe = 'alert.defective.test'): Notification
    {
        return NotificationService::notify(
            'alert.defective',
            'Defective equipment',
            'Defective GSM ASSET-1 at Alcar needs repair.',
            'media.view',
            $dedupe,
            '/defective-gsm',
            'offline'
        );
    }

    public function test_defective_equipment_raises_a_critical_alert(): void
    {
        DefectiveGsm::create([
            'asset_code' => 'GSM-114',
            'location' => 'Alcar',
            'issue' => 'Port 3 will not register',
            'status' => 'Open',
        ]);

        $this->actingAs($this->admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Defective GSM GSM-114 at Alcar needs repair')
            ->assertSee('prio-critical', false);

        $this->assertSame(1, Notification::where('type', 'alert.defective')->count());
    }

    public function test_low_inventory_inactive_equipment_and_incomplete_records_raise_alerts(): void
    {
        foreach (range(1, 3) as $index) {
            GlobeSim::create([
                'sim_number' => '0917000000'.$index,
                'imsi' => '515020000000'.$index,
                'location' => 'Alcar',
                'status' => 'Active',
            ]);
        }

        GlobeSim::create([
            'sim_number' => '09170000009',
            'imsi' => '515020000009',
            'assigned_to' => 'Alcar hotline',
            'location' => 'Alcar',
            'status' => 'Active',
        ]);

        PdcServer::create([
            'hostname' => 'PDC-OFF-1',
            'ip_address' => '10.24.28.90',
            'location' => 'Skyrise',
            'role' => 'PDC Server',
            'status' => 'Inactive',
        ]);

        PdcServer::create([
            'hostname' => 'PDC-OFF-2',
            'ip_address' => '10.24.28.91',
            'location' => '',
            'role' => 'PDC Server',
            'status' => 'Active',
        ]);

        $this->actingAs($this->admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Globe SIM stock is low — only 3 unassigned SIMs left.')
            ->assertSee('1 of 2 PDC Servers records are inactive or offline.')
            ->assertSee('1 PDC Servers record is missing required details', false);
    }

    public function test_a_location_with_several_issues_raises_one_grouped_alert(): void
    {
        DefectiveGsm::create(['asset_code' => 'GSM-200', 'location' => 'Alcar', 'issue' => 'Dead port', 'status' => 'Open']);
        PdcServer::create([
            'hostname' => 'PDC-ALC-9',
            'ip_address' => '10.24.28.99',
            'location' => 'Alcar',
            'role' => 'PDC Server',
            'status' => 'Offline',
        ]);

        $this->actingAs($this->admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Alcar has 2 open equipment issues (1 defective, 1 inactive).');
    }

    public function test_maintenance_due_records_raise_a_medium_priority_alert(): void
    {
        PdcServer::create([
            'hostname' => 'PDC-MNT-1',
            'ip_address' => '10.24.28.77',
            'location' => 'CTN',
            'role' => 'PDC Server',
            'status' => 'In Repair',
        ]);

        $this->actingAs($this->admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('1 PDC Servers record is flagged for maintenance or repair.');

        $this->assertSame('medium', NotificationService::priorityFor('alert.maintenance'));
    }

    public function test_record_changes_and_login_activity_never_create_notifications(): void
    {
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password']);

        $this->actingAs($this->admin)->postJson('/media-gateways', [
            'site_name' => 'PDC',
            'site_code' => 'PDC-N1',
            'ip_address' => '10.24.28.54',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ])->assertCreated();

        $this->actingAs($this->system)->get('/media-gateways/export')->assertOk();

        $this->assertSame(0, Notification::whereIn('type', [
            'added.media_gateways',
            'exported.media_gateways',
            'login.failed',
            'login.blocked',
        ])->count());

        $this->assertDatabaseHas('audit_logs', ['action' => 'Added', 'module' => 'Media Gateways']);
        $this->assertDatabaseHas('login_logs', ['email' => 'nobody@example.com', 'status' => 'Failed']);
    }

    public function test_legacy_non_operational_notifications_are_never_displayed(): void
    {
        NotificationService::notify(
            'login.failed',
            'Failed login',
            'A failed login attempt was recorded for ghost@example.com.',
            'logs.view',
            'login.failed.legacy',
            '/login-history',
            'offline'
        );

        $this->actingAs($this->system)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('A failed login attempt was recorded');
    }

    public function test_alerts_follow_existing_permissions(): void
    {
        $this->alert();
        NotificationService::systemError('Backup disk is full.');

        $this->actingAs($this->standard)->get('/dashboard')
            ->assertOk()
            ->assertSee('Defective GSM ASSET-1 at Alcar needs repair.')
            ->assertDontSee('Backup disk is full.');

        $this->actingAs($this->system)->get('/dashboard')
            ->assertOk()
            ->assertSee('Backup disk is full.');
    }

    public function test_marking_a_notification_as_read_clears_the_unread_count(): void
    {
        $notification = $this->alert();
        $recipient = NotificationRecipient::where('notification_id', $notification->id)->where('user_id', $this->admin->id)->firstOrFail();

        $this->actingAs($this->admin)->postJson('/notifications/'.$recipient->id.'/read')->assertOk();

        $this->assertNotNull($recipient->fresh()->read_at);
        $this->assertNull(
            NotificationRecipient::where('notification_id', $notification->id)->where('user_id', $this->system->id)->first()?->read_at
        );
    }

    public function test_mark_all_as_read_only_affects_the_current_user(): void
    {
        $this->alert('alert.defective.one');
        $this->alert('alert.defective.two');

        $this->actingAs($this->admin)->postJson('/notifications/read-all')->assertOk();

        $this->assertSame(0, NotificationRecipient::where('user_id', $this->admin->id)->whereNull('read_at')->count());
        $this->assertSame(2, NotificationRecipient::where('user_id', $this->system->id)->whereNull('read_at')->count());
    }

    public function test_dismissing_a_notification_is_per_user(): void
    {
        $notification = $this->alert();
        $systemRecipient = NotificationRecipient::where('notification_id', $notification->id)->where('user_id', $this->system->id)->firstOrFail();
        $adminRecipient = NotificationRecipient::where('notification_id', $notification->id)->where('user_id', $this->admin->id)->firstOrFail();

        $this->actingAs($this->system)->deleteJson('/notifications/'.$systemRecipient->id)->assertOk();

        $this->assertNotNull($systemRecipient->fresh()->dismissed_at);
        $this->assertNull($adminRecipient->fresh()->dismissed_at);

        $this->actingAs($this->system)->get('/user-types')->assertOk()->assertDontSee($notification->body);
        $this->actingAs($this->admin)->get('/dashboard')->assertOk()->assertSee($notification->body);
    }

    public function test_clear_all_only_clears_the_current_user(): void
    {
        $notification = $this->alert();

        $this->actingAs($this->admin)->deleteJson('/notifications')->assertOk();

        $this->assertNotNull(
            NotificationRecipient::where('notification_id', $notification->id)->where('user_id', $this->admin->id)->first()?->dismissed_at
        );
        $this->assertNull(
            NotificationRecipient::where('notification_id', $notification->id)->where('user_id', $this->system->id)->first()?->dismissed_at
        );
    }

    public function test_duplicate_dedupe_key_does_not_create_a_second_notification(): void
    {
        $this->alert('alert.defective.99');
        $this->alert('alert.defective.99');

        $this->assertSame(1, Notification::where('dedupe_key', 'alert.defective.99')->count());
    }

    public function test_repeated_page_loads_do_not_duplicate_the_same_alert(): void
    {
        DefectiveGsm::create(['asset_code' => 'GSM-301', 'location' => 'CTN', 'issue' => 'No signal', 'status' => 'Open']);

        $this->actingAs($this->admin)->get('/dashboard')->assertOk();
        $this->actingAs($this->admin)->get('/dashboard')->assertOk();
        $this->actingAs($this->system)->get('/dashboard')->assertOk();

        $this->assertSame(1, Notification::where('type', 'alert.defective')->count());
    }

    public function test_user_types_page_renders_for_admin_roles(): void
    {
        $this->actingAs($this->system)->get('/user-types')->assertOk();
        $this->actingAs($this->admin)->get('/user-types')->assertOk();
        $this->actingAs($this->standard)->get('/user-types')->assertForbidden();
    }

    public function test_user_cannot_dismiss_another_users_notification(): void
    {
        $notification = $this->alert();
        $adminRecipient = NotificationRecipient::where('notification_id', $notification->id)->where('user_id', $this->admin->id)->firstOrFail();

        $this->actingAs($this->system)->deleteJson('/notifications/'.$adminRecipient->id)->assertOk();

        $this->assertNull($adminRecipient->fresh()->dismissed_at);
    }
}
