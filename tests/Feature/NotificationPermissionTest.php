<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $standard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\UserTypeSeeder']);
        $this->admin = User::factory()->create([
            'user_type_id' => UserType::where('name', 'Administrator')->value('id'),
            'status' => 'Active',
        ]);
        $this->standard = User::factory()->create([
            'user_type_id' => UserType::where('name', 'Standard User')->value('id'),
            'status' => 'Active',
        ]);
    }

    public function test_administrator_does_not_see_notification_ui(): void
    {
        $html = $this->actingAs($this->admin)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="notificationButton"', $html);
        $this->assertStringNotContainsString('id="notificationMenu"', $html);
        $this->assertStringNotContainsString('notification-wrap', $html);
        $this->assertStringNotContainsString('notification-badge', $html);
        $this->assertStringNotContainsString('Mark all as read', $html);
    }

    public function test_standard_user_does_not_see_notification_ui(): void
    {
        $html = $this->actingAs($this->standard)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="notificationButton"', $html);
        $this->assertStringNotContainsString('id="notificationMenu"', $html);
        $this->assertStringNotContainsString('notification-wrap', $html);
    }

    public function test_notification_routes_are_removed(): void
    {
        $this->actingAs($this->admin)->post('/notifications/read-all')->assertNotFound();
        $this->actingAs($this->admin)->post('/notifications/1/read')->assertNotFound();
        $this->actingAs($this->admin)->delete('/notifications/1')->assertNotFound();
        $this->actingAs($this->admin)->delete('/notifications')->assertNotFound();

        $this->actingAs($this->standard)->post('/notifications/read-all')->assertNotFound();
        $this->actingAs($this->standard)->delete('/notifications')->assertNotFound();
    }
}
