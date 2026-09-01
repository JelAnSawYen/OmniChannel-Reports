<?php

namespace Tests\Feature;

use App\Models\MediaGateway;
use App\Models\SipChannel;
use App\Models\User;
use App\Models\UserType;
use App\Support\OperationCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramLocationAndDataTransferTest extends TestCase
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

    private function gateway(string $siteName = 'Alcar', string $code = 'ALC-001', string $ip = '10.20.30.40'): MediaGateway
    {
        return MediaGateway::create([
            'site_name' => $siteName,
            'site_code' => $code,
            'ip_address' => $ip,
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ]);
    }

    public function test_program_location_pages_match_the_sip_channels_layout(): void
    {
        $this->actingAs($this->admin);
        $this->gateway();
        SipChannel::create([
            'channel' => 'SIP-LAYOUT',
            'peer' => 'peer-a',
            'context' => 'from-internal',
            'codec' => 'ulaw',
            'status' => 'Active',
        ]);

        $markers = ['class="page-head"', 'class="search-box', 'class="table-card table-wrap"', 'class="table-footer"', 'id="transferButton"', 'class="plus-btn"', 'class="actions-column"', 'class="row-actions"'];

        $sip = $this->get('/sip-channels')->assertOk();
        $location = $this->get('/program-location/alcar')->assertOk();

        foreach ($markers as $marker) {
            $sip->assertSee($marker, false);
            $location->assertSee($marker, false);
        }

        $location
            ->assertSee('Alcar')
            ->assertSee('Export Data')
            ->assertSee('Import Data')
            ->assertSee('Data Transfer')
            ->assertDontSee('Download Sample Template')
            ->assertSee('Download Excel Template')
            ->assertSee('id="importModal"', false)
            ->assertSee('data-location-edit', false)
            ->assertSee('Site Code');

        $this->get('/program-location')
            ->assertOk()
            ->assertSee('Program Location')
            ->assertSee('class="table-card table-wrap"', false)
            ->assertSee('class="search-clear"', false)
            ->assertSee('data-clear-search', false)
            ->assertSee('Manage')
            ->assertSee('Skyrise')
            ->assertDontSee('Add Program Location')
            ->assertDontSee('id="programLocationAddButton"', false);
    }

    public function test_program_location_index_supports_search_status_and_location_filters(): void
    {
        $this->actingAs($this->admin);
        $this->gateway('Alcar', 'ALC-IDX', '10.31.31.1');

        $this->get('/program-location')
            ->assertOk()
            ->assertDontSee('Add Program Location')
            ->assertDontSee('id="programLocationAddButton"', false)
            ->assertDontSee('loc-manage active', false)
            ->assertSee('All Gateway Status')
            ->assertSee('All Locations')
            ->assertSee('1 Active')
            ->assertSee('0 None')
            ->assertSee(route('program-location.show', 'skyrise'), false)
            ->assertSee('Showing 1 to 5 of 5 entries');

        $this->get('/program-location?search=sky')
            ->assertOk()
            ->assertSee('title="Manage Skyrise"', false)
            ->assertDontSee('title="Manage Alcar"', false)
            ->assertSee('Showing 1 to 1 of 1 entries');

        $this->get('/program-location?status=active')
            ->assertOk()
            ->assertSee('title="Manage Alcar"', false)
            ->assertDontSee('title="Manage CTN"', false);

        $this->get('/program-location?status=none')
            ->assertOk()
            ->assertSee('title="Manage CTN"', false)
            ->assertSee('title="Manage SCS"', false)
            ->assertDontSee('title="Manage Alcar"', false);

        $this->get('/program-location?location=alcar')
            ->assertOk()
            ->assertSee('title="Manage Alcar"', false)
            ->assertSee('loc-manage active', false)
            ->assertDontSee('title="Manage Skyrise"', false)
            ->assertSee('Showing 1 to 1 of 1 entries');

        $this->get('/program-location?search=no-such-location')
            ->assertOk()
            ->assertSee('No program locations found.')
            ->assertSee('Showing 0 to 0 of 0 entries');
    }

    public function test_standard_user_cannot_see_the_add_program_location_action(): void
    {
        $this->actingAs($this->standard);

        $this->get('/program-location')
            ->assertOk()
            ->assertSee('Manage')
            ->assertDontSee('Add Program Location')
            ->assertDontSee('id="programLocationModal"', false);
    }

    public function test_admin_can_add_edit_and_delete_program_location_records(): void
    {
        $this->actingAs($this->admin);

        $this->post('/program-location/skyrise', [
            'site_code' => 'SKY-100',
            'ip_address' => '10.44.44.10',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ])->assertRedirect();

        $this->assertDatabaseHas('media_gateways', [
            'site_name' => 'Skyrise',
            'site_code' => 'SKY-100',
        ]);

        $record = MediaGateway::where('site_code', 'SKY-100')->firstOrFail();
        $other = $this->gateway('Skyrise', 'SKY-200', '10.44.44.20');

        $this->put('/program-location/skyrise/'.$record->id, [
            'site_name' => 'Skyrise',
            'site_code' => 'SKY-101',
            'ip_address' => '10.44.44.11',
            'username' => 'admin',
            'database' => 'asteriskcdrdb',
        ])->assertRedirect();

        $this->assertDatabaseHas('media_gateways', [
            'id' => $record->id,
            'site_code' => 'SKY-101',
            'ip_address' => '10.44.44.11',
            'username' => 'admin',
        ]);

        $this->delete('/program-location/skyrise/'.$record->id)->assertRedirect();
        $this->assertDatabaseMissing('media_gateways', ['id' => $record->id]);
        $this->assertDatabaseHas('media_gateways', ['id' => $other->id, 'site_code' => 'SKY-200']);
    }

    public function test_program_location_rejects_duplicate_and_invalid_records(): void
    {
        $this->actingAs($this->admin);
        $existing = $this->gateway('Alcar', 'ALC-DUP', '10.55.55.5');

        $this->post('/program-location/alcar', [
            'site_code' => 'ALC-DUP',
            'ip_address' => '10.55.55.9',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ])->assertRedirect()->assertSessionHasErrors('site_code');

        $this->post('/program-location/alcar', [
            'site_code' => 'ALC-NEW',
            'ip_address' => 'not-an-ip',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ])->assertRedirect()->assertSessionHasErrors('ip_address');

        $this->assertSame(1, MediaGateway::count());
        $this->assertDatabaseHas('media_gateways', ['id' => $existing->id, 'site_code' => 'ALC-DUP']);
    }

    public function test_standard_user_cannot_mutate_program_location_records(): void
    {
        $this->actingAs($this->standard);
        $gateway = $this->gateway('Estancia', 'EST-900', '10.66.66.6');

        $this->get('/program-location/estancia')
            ->assertOk()
            ->assertDontSee('class="plus-btn"', false)
            ->assertDontSee('action-btn edit', false)
            ->assertDontSee('action-btn delete', false)
            ->assertDontSee('Import Data')
            ->assertSee('Export Data')
            ->assertSee('Data Transfer');

        $this->post('/program-location/estancia', [
            'site_code' => 'EST-901',
            'ip_address' => '10.66.66.7',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ])->assertForbidden();

        $this->put('/program-location/estancia/'.$gateway->id, [
            'site_name' => 'Estancia',
            'site_code' => 'HACKED',
            'ip_address' => '10.66.66.6',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ])->assertForbidden();

        $this->delete('/program-location/estancia/'.$gateway->id)->assertForbidden();
        $this->postJson('/program-location/estancia/import/preview', [])->assertForbidden();
        $this->get('/program-location/estancia/import/template')->assertForbidden();

        $this->get('/program-location/estancia/export')->assertOk();
        $this->assertDatabaseHas('media_gateways', ['id' => $gateway->id, 'site_code' => 'EST-900']);
        $this->assertSame(1, MediaGateway::count());
    }

    public function test_program_location_export_downloads_current_data(): void
    {
        $this->actingAs($this->admin);
        $this->gateway('CTN', 'CTN-001', '10.88.88.8');

        $this->get('/program-location/ctn/export')
            ->assertOk()
            ->assertDownload('ctn-gsm-gateways.xlsx');

        $this->get('/program-location/ctn/import/template')->assertOk()->assertDownload('ctn-gsm-gateways-template.xlsx');
    }

    public function test_every_inventory_module_offers_data_transfer_export(): void
    {
        $this->actingAs($this->admin);

        foreach (array_keys(OperationCatalog::modules()) as $module) {
            $this->get('/'.$module)
                ->assertOk()
                ->assertSee('Export Data')
                ->assertSee('Import Data')
                ->assertSee('Data Transfer')
                ->assertDontSee('Download Sample Template')
                ->assertSee('Download Excel Template')
                ->assertSee('id="importModal"', false);

            $this->get('/'.$module.'/export')->assertOk();
            $this->get('/'.$module.'/import/template')->assertOk();
            $this->post('/'.$module.'/import', [])->assertNotFound();
        }
    }

    public function test_standard_user_can_export_but_cannot_import(): void
    {
        $this->actingAs($this->standard)->get('/sip-channels/export')->assertOk();
        $this->actingAs($this->standard)->postJson('/sip-channels/import/preview', [])->assertForbidden();
        $this->actingAs($this->standard)->get('/sip-channels/import/template')->assertForbidden();
        $this->actingAs($this->standard)
            ->get('/sip-channels')
            ->assertOk()
            ->assertDontSee('Import Data')
            ->assertSee('Export Data')
            ->assertSee('Data Transfer');
    }

    public function test_program_location_cannot_mutate_a_gateway_from_another_location(): void
    {
        $this->actingAs($this->admin);
        $alcar = $this->gateway('Alcar', 'ALC-IDOR', '10.90.90.1');

        $this->put('/program-location/skyrise/'.$alcar->id, [
            'site_name' => 'Skyrise',
            'site_code' => 'HACKED',
            'ip_address' => '10.90.90.1',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ])->assertNotFound();

        $this->delete('/program-location/skyrise/'.$alcar->id)->assertNotFound();
        $this->assertDatabaseHas('media_gateways', [
            'id' => $alcar->id,
            'site_name' => 'Alcar',
            'site_code' => 'ALC-IDOR',
        ]);
    }

    public function test_standard_user_export_omits_gateway_credentials(): void
    {
        $this->actingAs($this->standard);
        $this->gateway('Estancia', 'EST-SEC', '10.92.92.2');

        MediaGateway::query()->where('site_code', 'EST-SEC')->update([
            'username' => 'secret-user-xyz',
            'database' => 'secret-db-xyz',
        ]);

        $response = $this->get('/program-location/estancia/export')->assertOk();
        $path = $response->headers->get('X-Accel-Redirect')
            ?: (method_exists($response->baseResponse, 'getFile') ? $response->baseResponse->getFile()->getPathname() : null);

        $this->assertNotEmpty($path);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml') ?: '';
        $zip->close();

        $this->assertStringNotContainsString('secret-user-xyz', $sheet);
        $this->assertStringNotContainsString('secret-db-xyz', $sheet);
        $this->assertStringContainsString('EST-SEC', $sheet);
    }
}
