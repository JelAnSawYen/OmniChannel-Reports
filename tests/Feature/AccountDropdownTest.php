<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountDropdownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\UserTypeSeeder']);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'user_type_id' => UserType::where('name', 'Administrator')->value('id'),
            'status' => 'Active',
            'name' => 'Live Admin User',
        ]);
    }

    public function test_account_menu_shows_authenticated_user_and_links(): void
    {
        $user = $this->admin();
        $this->actingAs($user);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('id="accountButton"', false)
            ->assertSee('id="accountMenu"', false)
            ->assertSee('Live Admin User')
            ->assertSee('Administrator')
            ->assertSee('My Profile')
            ->assertSee('Logout')
            ->assertSee('Settings')
            ->assertSee('User Management')
            ->assertSee('Login History')
            ->assertSee('Audit Logs')
            ->assertSee('Reports')
            ->assertDontSee('class="account-caret"', false)
            ->assertSee('class="account-group-caret"', false);

        $html = $this->get('/dashboard')->getContent();
        preg_match('/id="accountMenu"(.*)id="logoutButton"/s', $html, $menu);
        $this->assertNotEmpty($menu);
        $this->assertStringContainsString('My Profile', $menu[1]);
        $this->assertStringContainsString('Settings', $menu[1]);
        $this->assertStringContainsString('User Management', $menu[1]);
        $this->assertStringContainsString('Login History', $menu[1]);
        $this->assertStringContainsString('Audit Logs', $menu[1]);
        $this->assertStringContainsString('Reports', $menu[1]);
        $this->assertTrue(strpos($menu[1], 'My Profile') < strpos($menu[1], 'Settings'));
        $this->assertTrue(strpos($menu[1], 'Settings') < strpos($menu[1], 'User Management'));
    }

    public function test_profile_login_history_and_logout_work(): void
    {
        $user = $this->admin();
        $this->actingAs($user);

        $this->get('/profile')
            ->assertOk()
            ->assertSee('Live Admin User')
            ->assertSee($user->email);

        $this->get('/login-history')->assertOk()->assertSee('Login History');

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get('/profile')->assertRedirect(route('login'));
    }

    public function test_profile_cancel_returns_to_the_previous_page(): void
    {
        $this->actingAs($this->admin());

        $fromGlobe = $this->from('/globe-sim')->get('/profile');
        $fromGlobe->assertOk()->assertSee('href="'.url('/globe-sim').'"', false);

        $this->from('/profile')->get('/profile')
            ->assertOk()
            ->assertSee('href="'.url('/globe-sim').'"', false);

        $this->withHeaders(['Referer' => ''])->get('/profile')
            ->assertOk()
            ->assertSee('href="'.url('/globe-sim').'"', false);
    }
}
