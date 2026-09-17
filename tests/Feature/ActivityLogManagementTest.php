<?php

namespace Tests\Feature;

use App\Console\Commands\PruneOldLogs;
use App\Models\AuditLog;
use App\Models\ChannelAllocationCampaign;
use App\Models\LoginLog;
use App\Models\PdcGroup;
use App\Models\User;
use App\Models\UserType;
use App\Services\Logs\LogRetentionService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ActivityLogManagementTest extends TestCase
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
            'user_type_id' => UserType::where('name', 'Administrator')->value('id'),
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

    private function log(User $user, array $overrides = []): AuditLog
    {
        return AuditLog::create(array_merge([
            'user_id' => $user->id,
            'action' => 'Added',
            'module' => 'Media Gateways',
            'description' => $user->name.' added a gateway',
            'ip_address' => '127.0.0.1',
        ], $overrides));
    }

    private function loginRow(User $user, array $overrides = []): LoginLog
    {
        return LoginLog::create(array_merge([
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => 'Success',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ], $overrides));
    }

    private function age($record, int $days): void
    {
        $record->forceFill([
            'created_at' => now()->subDays($days),
            'updated_at' => now()->subDays($days),
        ])->save();
    }

    public function test_admins_see_only_the_14_day_clear_button_and_no_row_deletes(): void
    {
        $this->log($this->admin);
        $this->log($this->system);

        $page = $this->actingAs($this->admin)->get('/activity-logs')->assertOk();

        $page->assertSee('Clear Logs Older Than 14 Days')
            ->assertSee('id="confirmModal"', false)
            ->assertSee('data-confirm-title="Clear Logs Older Than 14 Days"', false)
            ->assertDontSee('Select All')
            ->assertDontSee('Delete Selected')
            ->assertDontSee('Delete All My Logs')
            ->assertDontSee('Older than 60 days')
            ->assertDontSee('Older than 90 days')
            ->assertDontSee('data-log-row', false)
            ->assertDontSee('>Actions</th>', false)
            ->assertDontSee('return confirm(', false);

        $this->assertStringNotContainsString('action-btn delete', $page->getContent());
        $this->assertStringNotContainsString('activity-logs.selected', $page->getContent());

        $html = $page->getContent();
        $this->assertSame(1, substr_count($html, '>All Actions</option>'));
        $this->assertStringContainsString('hidden>All Actions</option>', $html);
        $this->assertStringContainsString('<th>User</th>', $html);
        $this->assertStringContainsString('<th>Email</th>', $html);
        $this->assertTrue(strpos($html, '<th>User</th>') < strpos($html, '<th>Email</th>'));
        $this->assertTrue(strpos($html, '<th>Email</th>') < strpos($html, '<th>Action</th>'));
        $this->assertStringContainsString($this->admin->email, $html);
        $this->assertStringNotContainsString('Restore Started', $html);
        $this->assertStringNotContainsString('>Login</option>', $html);
        $this->assertStringNotContainsString('>Logout</option>', $html);
        $this->assertStringNotContainsString('Created Backup', $html);
        $this->assertMatchesRegularExpression(
            '/>Created<\/option>\s*<option value="Added"[^>]*>Added<\/option>\s*<option value="Updated"[^>]*>Updated<\/option>\s*<option value="Deleted"[^>]*>Deleted<\/option>\s*<option value="Imported"[^>]*>Imported<\/option>\s*<option value="Exported"[^>]*>Exported<\/option>\s*<option value="Changed Password"[^>]*>Changed Password<\/option>\s*<option value="Cleared Logs"[^>]*>Cleared Logs<\/option>/',
            $html
        );
    }

    public function test_standard_user_cannot_view_or_clear_activity_logs(): void
    {
        $this->log($this->standard);

        $this->actingAs($this->standard)->get('/activity-logs')->assertForbidden();
        $this->actingAs($this->standard)->delete('/activity-logs/older')->assertForbidden();
    }

    public function test_scheduled_prune_command_removes_records_older_than_14_days(): void
    {
        Carbon::setTestNow('2026-08-26 12:00:00');

        $oldActivity = $this->log($this->admin, ['description' => 'old activity']);
        $this->age($oldActivity, 15);
        $keptActivity = $this->log($this->system, ['description' => 'recent activity']);
        $this->age($keptActivity, 14);

        $oldLogin = $this->loginRow($this->admin);
        $this->age($oldLogin, 15);
        $keptLogin = $this->loginRow($this->system);
        $this->age($keptLogin, 2);

        try {
            $this->artisan('logs:prune')
                ->expectsOutputToContain('Removed 1 activity logs and 1 login history records older than 14 days.')
                ->assertSuccessful();

            $this->assertDatabaseMissing('audit_logs', ['id' => $oldActivity->id]);
            $this->assertDatabaseHas('audit_logs', ['id' => $keptActivity->id]);
            $this->assertDatabaseMissing('login_logs', ['id' => $oldLogin->id]);
            $this->assertDatabaseHas('login_logs', ['id' => $keptLogin->id]);

            $this->actingAs($this->admin)->get('/activity-logs')
                ->assertOk()
                ->assertDontSee('old activity')
                ->assertSee('recent activity');
            $this->assertDatabaseMissing('audit_logs', ['id' => $oldActivity->id]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_log_prune_command_is_scheduled_daily(): void
    {
        $scheduled = collect(app(Schedule::class)->events())
            ->contains(fn ($event) => str_contains((string) $event->command, PruneOldLogs::class)
                || str_contains((string) $event->command, 'logs:prune'));

        $this->assertTrue($scheduled);
    }

    public function test_opening_activity_logs_prunes_only_the_current_users_old_records(): void
    {
        $ownOld = $this->log($this->admin, ['description' => 'own stale gateway add']);
        $this->age($ownOld, 15);
        $otherOld = $this->log($this->system, ['description' => 'other stale gateway add']);
        $this->age($otherOld, 15);
        $recent = $this->log($this->admin, ['description' => 'fresh gateway add']);

        $this->actingAs($this->admin)->get('/activity-logs')
            ->assertOk()
            ->assertDontSee('own stale gateway add')
            ->assertSee('other stale gateway add')
            ->assertSee('fresh gateway add');

        $this->assertDatabaseMissing('audit_logs', ['id' => $ownOld->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $otherOld->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $recent->id]);

        $this->actingAs($this->admin)->get('/activity-logs')
            ->assertOk()
            ->assertSee('other stale gateway add');
        $this->assertDatabaseHas('audit_logs', ['id' => $otherOld->id]);
    }

    public function test_manual_clear_deletes_all_activity_logs_older_than_14_days(): void
    {
        $ownOld = $this->log($this->admin, ['description' => 'own old log']);
        $this->age($ownOld, 15);
        $otherOld = $this->log($this->system, ['description' => 'other old log']);
        $this->age($otherOld, 16);
        $recent = $this->log($this->admin, ['description' => 'own recent log']);

        $this->actingAs($this->admin)->from('/activity-logs')->delete('/activity-logs/older')->assertRedirect('/activity-logs');

        $this->assertDatabaseMissing('audit_logs', ['id' => $ownOld->id]);
        $this->assertDatabaseMissing('audit_logs', ['id' => $otherOld->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $recent->id]);

        $this->actingAs($this->admin)->get('/activity-logs')
            ->assertOk()
            ->assertDontSee('own old log')
            ->assertDontSee('other old log')
            ->assertSee('own recent log');
    }

    public function test_manual_clear_deletes_all_login_history_older_than_14_days(): void
    {
        $ownOld = $this->loginRow($this->admin);
        $this->age($ownOld, 15);
        $otherOld = $this->loginRow($this->system);
        $this->age($otherOld, 15);
        $recent = $this->loginRow($this->admin);

        $this->actingAs($this->admin)->from('/login-history')->delete('/login-history/older')->assertRedirect('/login-history');

        $this->assertDatabaseMissing('login_logs', ['id' => $ownOld->id]);
        $this->assertDatabaseMissing('login_logs', ['id' => $otherOld->id]);
        $this->assertDatabaseHas('login_logs', ['id' => $recent->id]);
    }

    public function test_login_history_page_hides_bulk_delete_controls(): void
    {
        $this->loginRow($this->admin);

        $this->actingAs($this->admin)->get('/login-history')
            ->assertOk()
            ->assertSee('Clear Logs Older Than 14 Days')
            ->assertSee('placeholder="Search user or email"', false)
            ->assertDontSee('placeholder="Search email"', false)
            ->assertSee('id="confirmModal"', false)
            ->assertSee('>Activity</th>', false)
            ->assertDontSee('>Status</th>', false)
            ->assertSee('>Login</span>', false)
            ->assertDontSee('Select All')
            ->assertDontSee('Delete Selected')
            ->assertDontSee('Delete All My History')
            ->assertDontSee('data-log-row', false)
            ->assertDontSee('>Actions</th>', false);
    }

    public function test_standard_user_cannot_clear_login_history(): void
    {
        $this->loginRow($this->standard);

        $this->actingAs($this->standard)->get('/login-history')->assertForbidden();
        $this->actingAs($this->standard)->delete('/login-history/older')->assertForbidden();
    }

    public function test_opening_login_history_prunes_only_the_current_users_old_records(): void
    {
        $ownOld = $this->loginRow($this->admin, ['email' => 'own-old-login@example.com']);
        $this->age($ownOld, 15);
        $otherOld = $this->loginRow($this->system, ['email' => 'other-old-login@example.com']);
        $this->age($otherOld, 15);
        $recent = $this->loginRow($this->admin, ['email' => 'fresh-login@example.com']);

        $this->actingAs($this->admin)->get('/login-history')
            ->assertOk()
            ->assertDontSee('own-old-login@example.com')
            ->assertSee('other-old-login@example.com')
            ->assertSee('fresh-login@example.com');

        $this->assertDatabaseMissing('login_logs', ['id' => $ownOld->id]);
        $this->assertDatabaseHas('login_logs', ['id' => $otherOld->id]);
        $this->assertDatabaseHas('login_logs', ['id' => $recent->id]);
    }

    public function test_login_history_search_matches_user_name_or_email(): void
    {
        $named = User::factory()->create([
            'name' => 'Jellie Ann',
            'email' => 'jellie@example.com',
            'user_type_id' => UserType::where('name', 'Administrator')->value('id'),
            'status' => 'Active',
        ]);
        $other = User::factory()->create([
            'name' => 'Other Person',
            'email' => 'other-person@example.com',
            'user_type_id' => UserType::where('name', 'Administrator')->value('id'),
            'status' => 'Active',
        ]);
        $this->loginRow($named);
        $this->loginRow($other);

        $this->actingAs($this->admin)->get('/login-history?search=Jellie')
            ->assertOk()
            ->assertSee('jellie@example.com')
            ->assertDontSee('other-person@example.com');

        $this->actingAs($this->admin)->get('/login-history?search=other-person@example.com')
            ->assertOk()
            ->assertSee('other-person@example.com')
            ->assertDontSee('jellie@example.com');
    }

    public function test_log_tables_are_center_aligned_and_clear_buttons_use_delete_hover(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertMatchesRegularExpression(
            '/\.activity-log-table > thead > tr > th,\s*\.activity-log-table > tbody > tr > td \{\s*text-align: center;/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.btn\.danger-outline:hover,\s*\.btn\.danger-outline:focus-visible,\s*\.btn\.danger-outline:active \{ background:#ef4444; color:#fff; border-color:#ef4444; \}/',
            $css
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.btn\.danger-outline:hover \{ background:#fef2f2;/',
            $css
        );
    }

    public function test_removed_bulk_delete_routes_are_gone(): void
    {
        $this->actingAs($this->admin)->delete('/activity-logs/selected')->assertNotFound();
        $this->actingAs($this->admin)->delete('/activity-logs/mine')->assertNotFound();
        $this->actingAs($this->admin)->delete('/login-history/selected')->assertNotFound();
        $this->actingAs($this->admin)->delete('/login-history/mine')->assertNotFound();
    }

    public function test_retention_cutoff_is_fourteen_days(): void
    {
        $this->assertSame(14, LogRetentionService::DAYS);
    }

    public function test_dashboard_activity_logs_prune_only_the_current_users_old_records(): void
    {
        $ownOld = $this->log($this->admin, ['description' => 'admin stale dashboard activity']);
        $this->age($ownOld, 15);
        $otherOld = $this->log($this->system, ['description' => 'system stale dashboard activity']);
        $this->age($otherOld, 15);
        $recent = $this->log($this->system, ['description' => 'fresh dashboard activity']);

        $this->actingAs($this->admin)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Recent System Activity');

        $this->assertDatabaseMissing('audit_logs', ['id' => $ownOld->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $otherOld->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $recent->id]);
    }

    public function test_main_add_logs_created_nested_add_logs_added_and_edits_log_updated(): void
    {
        $this->actingAs($this->admin);

        $this->post('/campaigns', [
            'name' => 'Audit Track Campaign',
            'fte' => 1,
            'location' => 'WFH',
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'Created',
            'module' => 'Campaigns',
        ]);

        $campaign = ChannelAllocationCampaign::where('name', 'Audit Track Campaign')->firstOrFail();

        $this->put('/campaigns/'.$campaign->id, [
            'name' => 'Audit Track Campaign',
            'fte' => 2,
            'location' => 'WFH',
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'Updated',
            'module' => 'Campaigns',
        ]);

        $this->post('/pdc-servers', [
            'campaign_id' => $campaign->id,
            'location' => 'WFH',
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Created',
            'module' => 'PDC Servers',
            'description' => 'PDC campaign group added',
        ]);

        $group = PdcGroup::query()->firstOrFail();
        $this->post('/pdc-servers/'.$group->id.'/servers', [
            'hostname' => 'pdc-audit-host',
            'ip_address' => '10.24.28.80',
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Added',
            'module' => 'PDC Servers',
            'description' => 'PDC server added',
        ]);
    }

    public function test_audit_logs_hide_login_logout_and_created_backup(): void
    {
        $this->log($this->admin, [
            'action' => 'Login',
            'module' => 'Authentication',
            'description' => 'hidden login activity',
        ]);
        $this->log($this->admin, [
            'action' => 'Logout',
            'module' => 'Authentication',
            'description' => 'hidden logout activity',
        ]);
        $this->log($this->admin, [
            'action' => 'Created Backup',
            'module' => 'Maintenance',
            'description' => 'hidden created backup activity',
        ]);
        $this->log($this->admin, [
            'action' => 'Created',
            'module' => 'Campaigns',
            'description' => 'visible created campaign',
        ]);

        $this->actingAs($this->admin)->get('/activity-logs')
            ->assertOk()
            ->assertDontSee('hidden login activity')
            ->assertDontSee('hidden logout activity')
            ->assertDontSee('hidden created backup activity')
            ->assertSee('visible created campaign');
    }
}
