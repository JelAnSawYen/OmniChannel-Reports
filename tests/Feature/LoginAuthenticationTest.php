<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LoginAuthenticationTest extends TestCase
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

    private function makeUser(UserType $type): User
    {
        return User::factory()->create([
            'user_type_id' => $type->id,
            'status' => 'Active',
            'password' => 'Password123!Aa',
        ]);
    }

    public function test_login_page_is_available(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Login')
            ->assertSee('<title>OmniChannel Inventory</title>', false)
            ->assertSee('d="M21 3L3 21"', false)
            ->assertDontSee('d="M3 3l18 18"', false);
    }

    public function test_untrusted_forwarded_host_does_not_rewrite_login_urls(): void
    {
        $response = $this->call('GET', '/login', [], [], [], [
            'HTTP_HOST' => '127.0.0.1:8011',
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_HOST' => 'tunnel.example.test',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ]);

        $response->assertOk();
        $response->assertDontSee('https://tunnel.example.test/login', false);
        $this->assertStringContainsString('127.0.0.1:8011', $response->getContent());
    }

    public function test_trusted_proxy_forwarded_host_is_used_for_login_urls(): void
    {
        config(['app.trusted_proxies' => ['127.0.0.1']]);

        $response = $this->call('GET', '/login', [], [], [], [
            'HTTP_HOST' => '127.0.0.1:8011',
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_HOST' => 'tunnel.example.test',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ]);

        $response->assertOk();
        $response->assertSee('https://tunnel.example.test/login', false);
        $this->assertStringNotContainsString('127.0.0.1', $response->getContent());
    }

    public function test_untrusted_forwarded_host_does_not_change_login_redirect(): void
    {
        $user = $this->makeUser($this->standardType);

        $response = $this->call('POST', '/login', [
            'email' => $user->email,
            'password' => 'Password123!Aa',
        ], [], [], [
            'HTTP_HOST' => '127.0.0.1:8011',
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_HOST' => 'tunnel.example.test',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ]);

        $location = (string) $response->headers->get('Location');
        $response->assertRedirect();
        $this->assertStringNotContainsString('tunnel.example.test', $location);
        $this->assertStringContainsString('127.0.0.1:8011', $location);
    }

    public function test_trusted_proxy_preserves_the_forwarded_request_host_on_login_redirect(): void
    {
        config(['app.trusted_proxies' => ['127.0.0.1']]);
        $user = $this->makeUser($this->standardType);

        $response = $this->call('POST', '/login', [
            'email' => $user->email,
            'password' => 'Password123!Aa',
        ], [], [], [
            'HTTP_HOST' => '127.0.0.1:8011',
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_HOST' => 'tunnel.example.test',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ]);

        $location = (string) $response->headers->get('Location');
        $response->assertRedirect();
        $this->assertStringContainsString('https://tunnel.example.test', $location);
        $this->assertStringNotContainsString('127.0.0.1', $location);
    }

    public function test_invalid_login_is_rejected(): void
    {
        $this->from('/login')->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_each_role_can_log_in_and_reach_dashboard_then_log_out(): void
    {
        foreach ([$this->adminType, $this->standardType] as $type) {
            $user = $this->makeUser($type);

            $this->post('/login', [
                'email' => $user->email,
                'password' => 'Password123!Aa',
            ])->assertRedirect(route('dashboard'));

            $this->assertAuthenticatedAs($user);
            $this->get('/dashboard')->assertOk();

            $this->post('/logout')->assertRedirect(route('login'));
            $this->assertGuest();
            $this->get('/dashboard')->assertRedirect(route('login'));
        }
    }

    public function test_successful_login_still_reaches_the_dashboard_if_login_history_cannot_be_written(): void
    {
        $user = $this->makeUser($this->adminType);
        Schema::drop('login_logs');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!Aa',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertOk();
    }

    public function test_login_logout_and_failed_login_are_recorded_in_login_history_not_audit_logs(): void
    {
        $user = $this->makeUser($this->adminType);

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertRedirect('/login');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!Aa',
        ])->assertRedirect(route('dashboard'));

        $this->post('/logout')->assertRedirect(route('login'));

        $this->assertDatabaseHas('login_logs', [
            'email' => $user->email,
            'status' => 'Failed Login',
        ]);
        $this->assertDatabaseHas('login_logs', [
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => 'Login',
        ]);
        $this->assertDatabaseHas('login_logs', [
            'user_id' => $user->id,
            'email' => $user->email,
            'status' => 'Logout',
        ]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'Login']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'Logout']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'Failed Login']);

        $this->actingAs($user)->get('/login-history')
            ->assertOk()
            ->assertSee('>Activity</th>', false)
            ->assertSee('>Login</span>', false)
            ->assertSee('>Logout</span>', false)
            ->assertSee('>Failed Login</span>', false);
    }
}
