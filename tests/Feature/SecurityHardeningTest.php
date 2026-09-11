<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserType;
use App\Notifications\ResetUserPassword;
use App\Notifications\VerifyUserEmail;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
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
        ], $overrides));
    }

    public function test_unverified_user_cannot_access_the_application(): void
    {
        $user = $this->user($this->standardType, ['email_verified_at' => null]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->get('/media-gateways')->assertRedirect(route('verification.notice'));
    }

    public function test_weak_password_is_rejected_when_creating_a_user(): void
    {
        $this->actingAs($this->user($this->adminType));

        $this->post('/users', [
            'name' => 'Weak',
            'email' => 'weak@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_type_id' => $this->standardType->id,
            'status' => 'Active',
        ])->assertSessionHasErrors('password');
    }

    public function test_password_reset_does_not_reveal_whether_the_email_exists(): void
    {
        $this->get('/forgot-password')->assertOk();
        $this->post('/forgot-password', ['email' => 'missing@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status');
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'brute@example.com', 'password' => 'wrong'])->assertStatus(302);
        }

        $this->post('/login', ['email' => 'brute@example.com', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_standard_user_cannot_manage_users(): void
    {
        $admin = $this->user($this->adminType, ['name' => 'Keep Admin', 'email' => 'keep.admin@example.com']);
        $standard = $this->user($this->standardType);

        $this->actingAs($standard)->put('/users/'.$admin->id, [
            'name' => 'Hacked',
            'email' => $admin->email,
            'user_type_id' => $this->standardType->id,
            'status' => 'Inactive',
        ])->assertForbidden();

        $this->assertSame($this->adminType->id, $admin->fresh()->user_type_id);
        $this->assertSame('Active', $admin->fresh()->status);
    }

    public function test_administrator_logs_in_without_mfa_from_legacy_system_admin_flag(): void
    {
        Config::set('security.mfa_for_system_admin', true);
        $admin = $this->user($this->adminType);

        $this->assertFalse($admin->requiresMfa());
        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->actingAs($admin)->get('/mfa/setup')->assertRedirect(route('dashboard'));
    }

    public function test_standard_user_is_not_forced_through_mfa(): void
    {
        Config::set('security.mfa_for_system_admin', true);
        $user = $this->user($this->standardType);

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_restore_rejects_non_sqlite_uploads(): void
    {
        $this->adminType->update([
            'permissions' => array_values(array_unique(array_merge($this->adminType->permissions ?? [], ['maintenance.manage']))),
        ]);
        $admin = $this->user($this->adminType);
        $path = sys_get_temp_dir().'/not-a-db.txt';
        file_put_contents($path, 'hello');

        $this->actingAs($admin)->post('/maintenance/restore', [
            'backup_file' => new \Illuminate\Http\UploadedFile($path, 'backup.sqlite', 'application/octet-stream', null, true),
        ])->assertRedirect()->assertSessionHas('error');

        @unlink($path);
    }

    public function test_clear_logs_keeps_security_records(): void
    {
        $this->adminType->update([
            'permissions' => array_values(array_unique(array_merge($this->adminType->permissions ?? [], ['maintenance.manage']))),
        ]);
        $admin = $this->user($this->adminType);
        AuditLog::create(['action' => 'Login', 'module' => 'Authentication', 'description' => 'Keep me', 'ip_address' => '127.0.0.1']);
        AuditLog::create(['action' => 'Added', 'module' => 'Media Gateways', 'description' => 'Clear me', 'ip_address' => '127.0.0.1']);

        $this->actingAs($admin)->delete('/maintenance/logs')->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['description' => 'Keep me']);
        $this->assertDatabaseMissing('audit_logs', ['description' => 'Clear me']);
    }

    public function test_security_headers_are_present(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_password_reset_completes_with_a_strong_password(): void
    {
        Notification::fake();
        $user = $this->user($this->standardType);

        $token = Password::broker()->createToken($user);
        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'Password123!Aa',
            'password_confirmation' => 'Password123!Aa',
        ])->assertRedirect(route('login'));
    }

    public function test_creating_users_sends_verification_to_the_entered_email(): void
    {
        Notification::fake();
        $this->actingAs($this->user($this->adminType));

        foreach ([
            [$this->adminType, 'admin.inbox@example.com'],
            [$this->standardType, 'standard.inbox@example.com'],
        ] as [$type, $email]) {
            $this->post('/users', [
                'name' => 'Mailbox '.$type->name,
                'email' => $email,
                'password' => 'Password123!Aa',
                'password_confirmation' => 'Password123!Aa',
                'user_type_id' => $type->id,
                'status' => 'Active',
            ])->assertRedirect(route('users.index'));

            $created = User::where('email', $email)->firstOrFail();
            $this->assertNull($created->email_verified_at);
            Notification::assertSentTo($created, VerifyUserEmail::class);
        }
    }

    public function test_unverified_user_cannot_log_in(): void
    {
        $user = $this->user($this->adminType, [
            'email' => 'unverified.admin@example.com',
            'email_verified_at' => null,
            'password' => 'Password123!Aa',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!Aa',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_verification_link_works_without_being_logged_in(): void
    {
        foreach ([$this->adminType, $this->standardType] as $type) {
            $user = $this->user($type, [
                'email' => strtolower(str_replace(' ', '.', $type->name)).'.verify.me@example.com',
                'email_verified_at' => null,
                'password' => 'Password123!Aa',
            ]);

            $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ]);

            $this->get($url)->assertRedirect(route('login'));
            $this->assertNotNull($user->fresh()->email_verified_at);

            $this->post('/login', [
                'email' => $user->email,
                'password' => 'Password123!Aa',
            ])->assertRedirect(route('dashboard'));
            $this->assertAuthenticatedAs($user->fresh());
            $this->get('/dashboard')->assertOk();
            $this->post('/logout');
        }
    }

    public function test_administrator_logs_in_without_mfa_after_email_verification(): void
    {
        Config::set('security.mfa_for_system_admin', true);
        $admin = $this->user($this->adminType);

        $this->assertFalse($admin->requiresMfa());
        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->actingAs($admin)->get('/mfa/setup')->assertRedirect(route('dashboard'));
    }

    public function test_custom_privileged_roles_still_must_complete_mfa_when_enabled(): void
    {
        Config::set('security.mfa_for_system_admin', true);
        $peopleType = UserType::create([
            'name' => 'People Admin',
            'permissions' => ['dashboard.view', 'users.view', 'users.manage'],
        ]);
        $peopleAdmin = $this->user($peopleType);

        $this->actingAs($peopleAdmin)->get('/dashboard')->assertRedirect(route('mfa.setup'));
        $this->actingAs($peopleAdmin)->get('/mfa/setup')->assertOk();
        $secret = session('mfa_setup_secret');
        $this->assertNotEmpty($secret);
        $this->actingAs($peopleAdmin)->post('/mfa/setup', [
            'code' => TotpService::currentCode($secret),
        ])->assertRedirect(route('mfa.recovery'));
        $this->actingAs($peopleAdmin)->post('/mfa/recovery')->assertRedirect(route('dashboard'));
        $this->actingAs($peopleAdmin)->get('/dashboard')->assertOk();
    }

    public function test_unverified_administrator_cannot_log_in_until_verified(): void
    {
        Config::set('security.mfa_for_system_admin', true);
        $admin = $this->user($this->adminType, [
            'email' => 'admin.unverified@example.com',
            'email_verified_at' => null,
            'password' => 'Password123!Aa',
        ]);

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'Password123!Aa',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame('The email or password is incorrect.', session('errors')->first('email'));
    }

    public function test_unverified_administrator_and_standard_user_still_need_email_verification(): void
    {
        foreach ([$this->adminType, $this->standardType] as $type) {
            $user = $this->user($type, [
                'email' => strtolower(str_replace(' ', '.', $type->name)).'.unverified@example.com',
                'email_verified_at' => null,
                'password' => 'Password123!Aa',
            ]);

            $this->post('/login', [
                'email' => $user->email,
                'password' => 'Password123!Aa',
            ])->assertSessionHasErrors('email');
            $this->assertGuest();
        }
    }

    public function test_administrator_can_edit_peer_administrators_and_standard_users(): void
    {
        $admin = $this->user($this->adminType);
        $otherAdmin = $this->user($this->adminType);
        $standard = $this->user($this->standardType);

        $this->actingAs($admin)->get('/users/'.$otherAdmin->id.'/edit')->assertOk();
        $this->actingAs($admin)->put('/users/'.$otherAdmin->id, [
            'name' => 'Peer Admin',
            'email' => $otherAdmin->email,
            'user_type_id' => $this->adminType->id,
            'status' => 'Active',
        ])->assertRedirect(route('users.index'));
        $this->assertSame('Peer Admin', $otherAdmin->fresh()->name);

        $this->actingAs($admin)->get('/users/'.$standard->id.'/edit')->assertOk();
        $this->actingAs($admin)->get('/user-types')->assertNotFound();
    }

    public function test_standard_user_cannot_reach_user_management_even_with_override(): void
    {
        $standard = $this->user($this->standardType);
        $standard->update(['permissions' => ['users.manage', 'roles.manage', 'media.create']]);

        $this->assertFalse($standard->fresh()->hasPermission('users.manage'));
        $this->actingAs($standard)->get('/users')->assertForbidden();
        $this->actingAs($standard)->get('/user-types')->assertNotFound();
    }

    public function test_authenticated_pages_are_not_stored_in_the_browser_cache(): void
    {
        $user = $this->user($this->standardType);
        $this->actingAs($user)->get('/dashboard')
            ->assertOk();
        $this->assertStringContainsString('no-store', (string) $this->actingAs($user)->get('/dashboard')->headers->get('Cache-Control'));

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/media-gateways')->assertRedirect(route('login'));
    }

    public function test_activity_log_timestamps_use_asia_manila(): void
    {
        $this->assertSame('Asia/Manila', config('app.timezone'));
        $this->actingAs($this->user($this->adminType));

        $log = AuditLog::create([
            'action' => 'Added',
            'module' => 'Media Gateways',
            'description' => 'Timezone check',
            'ip_address' => '127.0.0.1',
        ]);

        $this->assertSame('Asia/Manila', $log->created_at->timezoneName);
        $this->get('/activity-logs')
            ->assertOk()
            ->assertSee($log->created_at->format('M d, Y'))
            ->assertSee($log->created_at->format('h:i A'));
    }

    public function test_inactive_and_unverified_logins_use_the_same_generic_error(): void
    {
        $inactive = $this->user($this->standardType, [
            'email' => 'inactive.user@example.com',
            'status' => 'Inactive',
            'password' => 'Password123!Aa',
        ]);
        $this->post('/login', [
            'email' => $inactive->email,
            'password' => 'Password123!Aa',
        ])->assertSessionHasErrors('email');
        $this->assertSame('The email or password is incorrect.', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_email_change_requires_current_password(): void
    {
        $user = $this->user($this->standardType, ['password' => 'Password123!Aa']);
        $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'email' => 'new.address@example.com',
        ])->assertSessionHasErrors('current_password');
        $this->assertSame($user->email, $user->fresh()->email);

        $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'email' => 'new.address@example.com',
            'current_password' => 'Password123!Aa',
        ])->assertRedirect();
        $this->assertSame('new.address@example.com', $user->fresh()->email);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_sensitive_routes_are_rate_limited(): void
    {
        $user = $this->user($this->standardType, ['password' => 'Password123!Aa']);
        $this->actingAs($user);

        for ($i = 0; $i < 6; $i++) {
            $this->put('/profile/password', [
                'current_password' => 'wrong-password',
                'password' => 'Password123!Aa',
                'password_confirmation' => 'Password123!Aa',
            ]);
        }

        $this->put('/profile/password', [
            'current_password' => 'wrong-password',
            'password' => 'Password123!Aa',
            'password_confirmation' => 'Password123!Aa',
        ])->assertStatus(429);
    }

    public function test_password_reset_email_is_sent_to_each_role(): void
    {
        Notification::fake();

        foreach ([$this->adminType, $this->standardType] as $type) {
            $user = $this->user($type);
            $this->post('/forgot-password', ['email' => $user->email])->assertRedirect();
            Notification::assertSentTo($user, ResetUserPassword::class);
        }
    }
}
