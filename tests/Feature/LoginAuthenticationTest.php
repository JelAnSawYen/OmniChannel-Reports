<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginAuthenticationTest extends TestCase
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
        $this->get('/login')->assertOk()->assertSee('Login');
    }

    public function test_generated_login_urls_follow_the_request_host_instead_of_app_url(): void
    {
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

    public function test_login_redirect_preserves_the_forwarded_request_host(): void
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
        foreach ([$this->systemType, $this->adminType, $this->standardType] as $type) {
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
}
