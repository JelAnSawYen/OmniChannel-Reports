<?php

namespace Tests\Feature;

use App\Console\Commands\PruneOldLogs;
use App\Models\AuditLog;
use App\Models\LoginLog;
use App\Models\User;
use App\Models\UserType;
use App\Services\LogRetentionService;
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

    public function test_admins_see_only_the_3_day_clear_button_and_no_row_deletes(): void
    {
        $this->log($this->admin);
        $this->log($this->system);

        $page = $this->actingAs($this->admin)->get('/activity-logs')->assertOk();

        $page->assertSee('Clear Logs Older Than 3 Days')
            ->assertSee('id="confirmModal"', false)
            ->assertSee('data-confirm-title="Clear Logs Older Than 3 Days"', false)
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
    }

    public function test_standard_user_cannot_view_or_clear_activity_logs(): void
    {
        $this->log($this->standard);

        $this->actingAs($this->standard)->get('/activity-logs')->assertForbidden();
        $this->actingAs($this->standard)->delete('/activity-logs/older')->assertForbidden();
    }

    public function test_scheduled_prune_command_removes_records_older_than_3_days(): void
    {
        Carbon::setTestNow('2026-08-26 12:00:00');

        $oldActivity = $this->log($this->admin, ['description' => 'old activity']);
        $this->age($oldActivity, 4);
        $keptActivity = $this->log($this->system, ['description' => 'recent activity']);
        $this->age($keptActivity, 3);

        $oldLogin = $this->loginRow($this->admin);
        $this->age($oldLogin, 4);
        $keptLogin = $this->loginRow($this->system);
        $this->age($keptLogin, 2);

        try {
            $this->artisan('logs:prune')
                ->expectsOutputToContain('Removed 1 activity logs and 1 login history records older than 3 days.')
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
        $this->age($ownOld, 4);
        $otherOld = $this->log($this->system, ['description' => 'other stale gateway add']);
        $this->age($otherOld, 4);
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

    public function test_manual_clear_deletes_all_activity_logs_older_than_3_days(): void
    {
        $ownOld = $this->log($this->admin, ['description' => 'own old log']);
        $this->age($ownOld, 4);
        $otherOld = $this->log($this->system, ['description' => 'other old log']);
        $this->age($otherOld, 5);
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

    public function test_manual_clear_deletes_all_login_history_older_than_3_days(): void
    {
        $ownOld = $this->loginRow($this->admin);
        $this->age($ownOld, 4);
        $otherOld = $this->loginRow($this->system);
        $this->age($otherOld, 4);
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
            ->assertSee('Clear Logs Older Than 3 Days')
            ->assertSee('id="confirmModal"', false)
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
        $this->age($ownOld, 4);
        $otherOld = $this->loginRow($this->system, ['email' => 'other-old-login@example.com']);
        $this->age($otherOld, 4);
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

    public function test_removed_bulk_delete_routes_are_gone(): void
    {
        $this->actingAs($this->admin)->delete('/activity-logs/selected')->assertNotFound();
        $this->actingAs($this->admin)->delete('/activity-logs/mine')->assertNotFound();
        $this->actingAs($this->admin)->delete('/login-history/selected')->assertNotFound();
        $this->actingAs($this->admin)->delete('/login-history/mine')->assertNotFound();
    }

    public function test_retention_cutoff_is_three_days(): void
    {
        $this->assertSame(3, LogRetentionService::DAYS);
    }

    public function test_dashboard_activity_logs_prune_only_the_current_users_old_records(): void
    {
        $ownOld = $this->log($this->admin, ['description' => 'admin stale dashboard activity']);
        $this->age($ownOld, 4);
        $otherOld = $this->log($this->system, ['description' => 'system stale dashboard activity']);
        $this->age($otherOld, 4);
        $recent = $this->log($this->system, ['description' => 'fresh dashboard activity']);

        $this->actingAs($this->admin)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Recent System Activity');

        $this->assertDatabaseMissing('audit_logs', ['id' => $ownOld->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $otherOld->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $recent->id]);
    }
}
