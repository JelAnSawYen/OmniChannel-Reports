<?php

namespace Tests\Feature;

use App\Models\ChannelAllocationCampaign;
use App\Models\MediaGateway;
use App\Models\SipChannel;
use App\Models\User;
use App\Models\UserType;
use App\Support\Gsm\ProgramLocationStatus;
use App\Support\OperationCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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

    protected function tearDown(): void
    {
        $path = ProgramLocationStatus::path();
        if (is_file($path)) {
            @unlink($path);
        }
        parent::tearDown();
    }

    private function mapSitesFromHtml(string $html): array
    {
        $this->assertTrue(
            (bool) preg_match('/id="programLocationSites">(?P<json>.*?)<\/script>/s', $html, $match),
            'Program Location map payload is missing.'
        );

        $sites = json_decode(html_entity_decode($match['json'], ENT_QUOTES), true);
        $this->assertIsArray($sites, json_last_error_msg().' '.substr($match['json'], 0, 300));

        return $sites;
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
        $campaign = ChannelAllocationCampaign::create(['name' => 'Layout Campaign']);
        SipChannel::create([
            'campaign_id' => $campaign->id,
            'etpi_sip_name' => 'SIP-LAYOUT',
            'pilot_number' => '100',
            'channel_count' => 2,
            'channel_range' => '100 - 101',
            'network' => 'ETPI',
            'date_activation' => '2026-07-09',
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

        $index = $this->get('/program-location')->assertOk();
        $index
            ->assertSee('Program Location')
            ->assertSee('placeholder="Search Sites"', false)
            ->assertSee('id="programLocationMap"', false)
            ->assertSee('id="programLocationSearch"', false)
            ->assertSee('id="programLocationSites"', false)
            ->assertDontSee('class="search-clear"', false)
            ->assertDontSee('data-clear-search', false)
            ->assertSee('vendor/leaflet/leaflet.js', false)
            ->assertSee('Gateway Assigned')
            ->assertSee('No Gateway Assigned')
            ->assertSee('PDC')
            ->assertSee('SKYRISE')
            ->assertSee('CG3')
            ->assertSee('SC5')
            ->assertDontSee('With GSM Gateway')
            ->assertDontSee('Without GSM Gateway')
            ->assertDontSee('unpkg.com')
            ->assertDontSee('initLocalMap')
            ->assertDontSee('fitBounds')
            ->assertDontSee('Add Program Location')
            ->assertDontSee('id="programLocationAddButton"', false)
            ->assertDontSee('All Gateway Status')
            ->assertDontSee('class="table-card table-wrap"', false)
            ->assertDontSee('title="Manage Alcar"', false);

        $sites = $this->mapSitesFromHtml($index->getContent());
        $this->assertSame(
            ['ALCAR', 'CG3', 'CTN', 'ESTANCIA', 'SC5', 'SKYRISE', 'PDC'],
            array_column($sites, 'name')
        );
        $byName = collect($sites)->keyBy('name');
        $this->assertTrue($byName['ALCAR']['available']);
        $this->assertTrue($byName['CG3']['available']);
        $this->assertTrue($byName['CTN']['available']);
        $this->assertTrue($byName['ESTANCIA']['available']);
        $this->assertTrue($byName['SC5']['available']);
        $this->assertTrue($byName['SKYRISE']['available']);
        $this->assertFalse($byName['PDC']['available']);
        $this->assertSame('6819 Ayala Avenue, Makati City, RCBC Plaza', $byName['PDC']['address']);
        $this->assertEqualsWithDelta(14.5607611, $byName['PDC']['lat'], 0.001);
        $this->assertEqualsWithDelta(121.0165333, $byName['PDC']['lng'], 0.001);
        $this->assertEqualsWithDelta(10.3177541, $byName['SKYRISE']['lat'], 0.001);
        foreach ($sites as $site) {
            $this->assertNotEmpty($site['lat']);
            $this->assertNotEmpty($site['lng']);
            $this->assertArrayNotHasKey('gateways', $site);
        }

        $sidebar = Str::between(
            $this->get('/dashboard')->assertOk()->getContent(),
            '<aside',
            '</aside>'
        );
        $this->assertStringContainsString('Program Location', $sidebar);
        $this->assertStringNotContainsString('id="programLocationGroup"', $sidebar);
        $this->assertStringNotContainsString('id="programLocationSub"', $sidebar);
        foreach (['alcar', 'ctn', 'estancia', 'pdc', 'scs', 'skyrise'] as $slug) {
            $this->assertStringNotContainsString(route('program-location.show', $slug), $sidebar);
        }
    }

    public function test_program_location_index_is_a_map_of_existing_sites(): void
    {
        $this->actingAs($this->admin);
        $this->gateway('Alcar', 'ALC-IDX', '10.31.31.1');
        $this->gateway('Estancia', 'EST-IDX', '10.31.31.2');

        $html = $this->get('/program-location')->assertOk()->getContent();
        $this->assertStringContainsString('id="locConfigPanel"', $html);
        $this->assertStringContainsString('loc-popup-edit', $html);
        $this->assertStringContainsString('GSM Gateway Configuration', $html);
        $this->assertStringContainsString('id="locConfigName"', $html);
        $this->assertStringContainsString('id="locConfigAddress"', $html);
        $this->assertStringContainsString('id="locConfigStatus"', $html);
        $this->assertStringContainsString('No Gateway Assigned', $html);
        $this->assertStringContainsString('Gateway Assigned', $html);
        $this->assertStringContainsString('Assign GSM Gateway', $html);
        $this->assertStringNotContainsString('>Edit<', $html);
        $configForm = Str::between($html, 'id="locConfigForm"', '</form>');
        $this->assertStringNotContainsString('<select', $configForm);
        $this->assertStringNotContainsString('hostname', $configForm);
        $this->assertStringNotContainsString('ip_address', $configForm);
        $this->assertStringNotContainsString('All Gateway Status', $html);
        $this->assertStringNotContainsString('class="table-card table-wrap"', $html);

        $sites = collect($this->mapSitesFromHtml($html));
        $this->assertTrue($sites->firstWhere('name', 'ALCAR')['available']);
        $this->assertTrue($sites->firstWhere('name', 'ESTANCIA')['available']);
        $this->assertTrue($sites->firstWhere('name', 'CG3')['available']);
        $this->assertFalse($sites->firstWhere('name', 'PDC')['available']);

        $this->get('/program-location?search=sky')
            ->assertOk()
            ->assertSee('id="programLocationMap"', false)
            ->assertSee('value="sky"', false)
            ->assertSee('id="programLocationSites"', false);
    }

    public function test_program_location_status_can_be_saved_without_creating_a_location(): void
    {
        $this->actingAs($this->admin);

        $this->putJson('/program-location/pdc/status', ['assigned' => true])
            ->assertOk()
            ->assertJson(['ok' => true, 'assigned' => true]);

        $sites = collect($this->mapSitesFromHtml($this->get('/program-location')->assertOk()->getContent()));
        $this->assertTrue($sites->firstWhere('name', 'PDC')['available']);
        $this->assertCount(7, $sites);

        $this->putJson('/program-location/pdc/status', ['assigned' => false])
            ->assertOk()
            ->assertJson(['ok' => true, 'assigned' => false]);

        $sites = collect($this->mapSitesFromHtml($this->get('/program-location')->assertOk()->getContent()));
        $this->assertFalse($sites->firstWhere('name', 'PDC')['available']);
    }

    public function test_standard_user_cannot_access_program_location(): void
    {
        $this->actingAs($this->standard);

        $this->get('/program-location')->assertForbidden();
        $this->get('/program-location/estancia')->assertForbidden();
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

        $this->get('/program-location/estancia')->assertForbidden();

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

        $this->get('/program-location/estancia/export')->assertForbidden();
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

    public function test_import_preview_modal_stays_within_the_viewport(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/#importPreviewModal\.modal-backdrop\.visible\s*\{[^}]*overflow:\s*hidden/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/#importPreviewModal \.modal\.import-wide\s*\{[^}]*max-height:\s*calc\(100vh - 24px\)/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/#importPreviewModal \.modal\.import-wide\s*\{[^}]*display:\s*flex/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/#importPreviewModal \.modal\.import-wide\s*\{[^}]*overflow:\s*hidden/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/#importPreviewModal \.modal-body\s*\{[^}]*min-height:\s*0/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/#importPreviewModal \.import-preview-wrap\s*\{[^}]*overflow:\s*auto/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/#importPreviewModal \.import-preview-wrap\s*\{[^}]*min-height:\s*0/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/#importPreviewModal \.import-preview-table td\s*\{[^}]*overflow-wrap:\s*anywhere/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/#importPreviewModal \.import-preview-table thead th\s*\{[^}]*position:\s*sticky/',
            $css
        );
    }

    public function test_every_inventory_module_offers_data_transfer_export(): void
    {
        $this->actingAs($this->admin);

        foreach (array_keys(OperationCatalog::modules()) as $module) {
            $page = $this->get('/'.$module)->assertOk();
            if ($module === 'archive-recordings') {
                $page->assertDontSee('Data Transfer')
                    ->assertDontSee('id="transferButton"', false);
            } else {
                $page->assertSee('Export Data')
                    ->assertSee('Import Data')
                    ->assertSee('Data Transfer')
                    ->assertDontSee('Download Sample Template')
                    ->assertSee('Download Excel Template')
                    ->assertSee('id="importModal"', false);
            }

            $this->get('/'.$module.'/export')->assertOk();
            $this->get('/'.$module.'/import/template')->assertOk();
            $this->post('/'.$module.'/import', [])->assertNotFound();
        }
    }

    public function test_standard_user_cannot_export_or_import_restricted_modules(): void
    {
        $this->actingAs($this->standard)->get('/sip-channels/export')->assertForbidden();
        $this->actingAs($this->standard)->postJson('/sip-channels/import/preview', [])->assertForbidden();
        $this->actingAs($this->standard)->get('/sip-channels/import/template')->assertForbidden();
        $this->actingAs($this->standard)->get('/sip-channels')->assertForbidden();
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

    public function test_standard_user_cannot_export_program_location(): void
    {
        $this->actingAs($this->standard);
        $this->gateway('Estancia', 'EST-SEC', '10.92.92.2');

        $this->get('/program-location/estancia/export')->assertForbidden();
    }
}
