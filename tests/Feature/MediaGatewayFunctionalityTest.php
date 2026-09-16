<?php

namespace Tests\Feature;

use App\Models\MediaGateway;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaGatewayFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdministrator(): User
    {
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\UserTypeSeeder']);
        $type = UserType::where('name', 'Administrator')->firstOrFail();

        $user = User::factory()->create([
            'user_type_id' => $type->id,
            'status' => 'Active',
        ]);

        $this->actingAs($user);

        return $user;
    }

    public function test_json_list_searches_and_sorts_real_records(): void
    {
        $this->actingAsAdministrator();

        MediaGateway::create([
            'site_name' => 'Alpha',
            'site_code' => 'MKT132',
            'ip_address' => '10.18.20.132',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ]);

        MediaGateway::create([
            'site_name' => 'WFH',
            'site_code' => 'PDC-MG1',
            'ip_address' => '10.24.28.54',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ]);

        $this->getJson('/media-gateways?search=alpha&sort_by=site_code&sort_dir=desc&per_page=5')
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('records.0.site_code', 'MKT132')
            ->assertJsonMissingPath('records.0.status');
    }

    public function test_json_requests_create_update_and_delete_a_media_gateway(): void
    {
        $this->actingAsAdministrator();

        $payload = [
            'site_name' => 'Test Site',
            'site_code' => 'TST001',
            'ip_address' => '10.0.0.1',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ];

        $this->postJson('/media-gateways', $payload)
            ->assertCreated()
            ->assertJsonPath('record.site_code', 'TST001');

        $gateway = MediaGateway::where('site_code', 'TST001')->firstOrFail();

        $this->putJson('/media-gateways/'.$gateway->id, [
            ...$payload,
            'site_name' => 'Updated Site',
        ])
            ->assertOk()
            ->assertJsonPath('record.site_name', 'Updated Site');

        $this->deleteJson('/media-gateways/'.$gateway->id)
            ->assertOk()
            ->assertJsonPath('total', 0);

        $this->assertDatabaseMissing('media_gateways', ['id' => $gateway->id]);
    }

    public function test_invalid_ip_address_is_rejected(): void
    {
        $this->actingAsAdministrator();

        $this->postJson('/media-gateways', [
            'site_name' => 'Test Site',
            'site_code' => 'TST001',
            'ip_address' => 'not-an-ip',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ip_address');
    }

    public function test_export_downloads_an_excel_file_without_gateway_status(): void
    {
        $this->actingAsAdministrator();

        MediaGateway::create([
            'site_name' => 'WFH',
            'site_code' => 'PDC-MG1',
            'ip_address' => '10.24.28.54',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ]);

        $response = $this->get('/media-gateways/export')
            ->assertOk()
            ->assertDownload('media-gateways.xlsx');

        $this->assertSame('PK', substr($response->streamedContent(), 0, 2));
    }
}
