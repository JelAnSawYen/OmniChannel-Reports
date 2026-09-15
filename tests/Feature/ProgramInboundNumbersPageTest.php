<?php

namespace Tests\Feature;

use App\Models\ChannelAllocationCampaign;
use App\Models\MediaGateway;
use App\Models\ProgramInboundNumber;
use App\Models\User;
use App\Models\UserType;
use App\Services\XlsxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProgramInboundNumbersPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\UserTypeSeeder']);
        $this->admin = User::factory()->create([
            'user_type_id' => UserType::where('name', 'Administrator')->value('id'),
            'status' => 'Active',
        ]);
    }

    public function test_table_uses_inbound_columns_alignment_and_stacked_numbers(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create(['name' => 'Mynt']);
        $gateway = MediaGateway::create([
            'site_name' => 'Alcar',
            'site_code' => 'PIN001',
            'ip_address' => '192.168.1.10',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
            'port' => '1',
            'network' => 'Globe',
        ]);

        ProgramInboundNumber::create([
            'number' => '09260484530',
            'program' => 'Mynt',
            'status' => 'Active',
            'campaign_id' => $campaign->id,
            'mobile_numbers' => ['09260484530', '09260484550', '09544086968'],
            'landline_numbers' => ['53229111', '53229112'],
            'media_gateway_id' => $gateway->id,
            'port' => '1',
            'network' => 'Globe',
            'remarks' => '',
        ]);

        ProgramInboundNumber::create([
            'number' => '53229521',
            'program' => 'PNB Collections/Telesales',
            'status' => 'Active',
            'landline_numbers' => ['53229521'],
            'remarks' => 'No mobile inbound number',
        ]);

        $page = $this->get('/program-inbound-numbers')->assertOk();
        $html = $page->getContent();
        $css = file_get_contents(resource_path('css/app.css'));

        $tableHtml = \Illuminate\Support\Str::between($html, '<table class="pin-table"', '</table>');

        $page->assertSee('class="pin-table"', false)
            ->assertSee('class="pin-campaign"', false)
            ->assertSee('09260484530')
            ->assertSee('09260484550')
            ->assertSee('09544086968')
            ->assertSee('53229111')
            ->assertSee('53229112')
            ->assertSee('192.168.1.10')
            ->assertSee('PNB Collections/Telesales')
            ->assertSee('No mobile inbound number');

        $this->assertStringContainsString('>Campaign</th>', $tableHtml);
        $this->assertStringContainsString('>Mobile</th>', $tableHtml);
        $this->assertStringContainsString('>Landline</th>', $tableHtml);
        $this->assertStringContainsString('>GSM Gateway</th>', $tableHtml);
        $this->assertStringContainsString('>Port</th>', $tableHtml);
        $this->assertStringContainsString('>Network</th>', $tableHtml);
        $this->assertStringContainsString('>Remarks</th>', $tableHtml);
        $this->assertStringContainsString('>Actions</th>', $tableHtml);
        $this->assertStringContainsString('class="pin-stack"', $tableHtml);
        $this->assertStringNotContainsString('>Number</th>', $tableHtml);
        $this->assertStringNotContainsString('>Program</th>', $tableHtml);
        $this->assertStringNotContainsString('>Location</th>', $tableHtml);
        $this->assertStringNotContainsString('>Assigned Channel</th>', $tableHtml);
        $this->assertStringNotContainsString('>Id</th>', $tableHtml);
        $this->assertStringNotContainsString('Last Updated', $tableHtml);
        $this->assertMatchesRegularExpression('/PNB Collections\/Telesales<\/span><\/td>\s*<td>\s*—/', $tableHtml);
        $this->assertDoesNotMatchRegularExpression('/<th[^>]*\bpin-campaign"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<th[^>]*color:\s*#0b70f7/', $html);
        $this->assertStringContainsString('.pin-table > thead > tr > th', $css);
        $this->assertStringContainsString('.pin-campaign', $css);
        $this->assertMatchesRegularExpression('/\.pin-campaign\s*\{[^}]*color:\s*#0b70f7/', $css);
        $this->assertMatchesRegularExpression('/\.pin-campaign\s*\{[^}]*font-weight:\s*700/', $css);
        $this->assertMatchesRegularExpression('/th\.pin-campaign-col[\s\S]*?text-align:\s*left/', $css);
        $this->assertMatchesRegularExpression('/th\.pin-campaign-col[\s\S]*?padding-left:\s*20px/', $css);
        $this->assertMatchesRegularExpression('/\.pin-table > thead > tr > th,[\s\S]*?text-align:\s*center/', $css);
        $this->assertMatchesRegularExpression('/\.pin-stack\s*\{[^}]*flex-direction:\s*column/', $css);
    }

    public function test_campaign_uses_existing_records_and_does_not_duplicate(): void
    {
        $this->actingAs($this->admin);
        $existing = ChannelAllocationCampaign::create(['name' => 'Mynt']);

        $page = $this->get('/program-inbound-numbers')->assertOk();
        $html = $page->getContent();
        $this->assertStringContainsString('id="field_campaign"', $html);
        $this->assertStringContainsString('Select or type a campaign...', $html);
        $this->assertStringContainsString('data-name="Mynt"', $html);
        $this->assertStringContainsString('pin-campaign-combo', $html);
        $this->assertStringContainsString('.pin-campaign-option[hidden]', file_get_contents(resource_path('css/app.css')));
        $this->assertStringNotContainsString('id="field_program"', $html);

        $this->post('/program-inbound-numbers', [
            'campaign' => 'mynt',
            'landline_numbers' => ['09170001111'],
        ])->assertRedirect();

        $this->assertSame(1, ChannelAllocationCampaign::count());
        $this->assertDatabaseHas('program_inbound_numbers', [
            'number' => '09170001111',
            'campaign_id' => $existing->id,
            'program' => 'Mynt',
        ]);

        $this->post('/program-inbound-numbers', [
            'campaign' => 'RCBC Bankard',
            'landline_numbers' => ['09170002222'],
        ])->assertRedirect();

        $this->assertSame(2, ChannelAllocationCampaign::count());
        $created = ChannelAllocationCampaign::query()->where('name', 'RCBC Bankard')->first();
        $this->assertNotNull($created);
        $this->assertDatabaseHas('program_inbound_numbers', [
            'number' => '09170002222',
            'campaign_id' => $created->id,
            'program' => 'RCBC Bankard',
        ]);
    }

    public function test_mobile_numbers_show_gsm_gateway_and_fill_port_network(): void
    {
        $this->actingAs($this->admin);
        ChannelAllocationCampaign::create(['name' => 'Mynt']);
        $gateway = MediaGateway::create([
            'site_name' => 'Alcar',
            'site_code' => 'PIN-GSM-1',
            'ip_address' => '192.168.1.10',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
            'port' => '1',
            'network' => 'Globe',
        ]);

        $page = $this->get('/program-inbound-numbers')->assertOk();
        $html = $page->getContent();
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('id="pinMobileInput"', $html);
        $this->assertStringContainsString('id="pinLandlineInput"', $html);
        $this->assertStringNotContainsString('Enter mobile number and press Enter', $html);
        $this->assertStringNotContainsString('Enter landline number and press Enter', $html);
        $this->assertStringContainsString('You can enter multiple mobile numbers.', $html);
        $this->assertStringContainsString('You can enter multiple landline numbers.', $html);
        $this->assertTrue(strpos($html, 'id="pinMobileInput"') < strpos($html, 'You can enter multiple mobile numbers.'));
        $this->assertTrue(strpos($html, 'You can enter multiple mobile numbers.') < strpos($html, 'id="pinMobileChips"'));
        $this->assertTrue(strpos($html, 'id="pinLandlineInput"') < strpos($html, 'You can enter multiple landline numbers.'));
        $this->assertTrue(strpos($html, 'You can enter multiple landline numbers.') < strpos($html, 'id="pinLandlineChips"'));
        $this->assertStringContainsString('.pin-number-hint', $css);
        $this->assertStringContainsString('id="field_media_gateway_id"', $html);
        $this->assertMatchesRegularExpression('/id="field_media_gateway_id"[^>]*\bdisabled/', $html);
        $this->assertStringContainsString('id="pinGsmWrap"', $html);
        $this->assertDoesNotMatchRegularExpression('/id="pinGsmWrap"[^>]*\bhidden/', $html);
        $this->assertStringContainsString('id="field_port" readonly', $html);
        $this->assertStringContainsString('id="field_network" readonly', $html);
        $this->assertMatchesRegularExpression('/id="field_port"[^>]*\bdisabled/', $html);
        $this->assertMatchesRegularExpression('/id="field_network"[^>]*\bdisabled/', $html);
        $this->assertStringContainsString('192.168.1.10', $html);
        $this->assertStringContainsString('data-port="1"', $html);
        $this->assertStringContainsString('data-network="Globe"', $html);
        $this->assertStringContainsString('id="field_port" readonly', $html);
        $this->assertStringContainsString('id="field_network" readonly', $html);
        $this->assertStringContainsString('toggleGsm', $html);
        $this->assertStringContainsString('gateway.disabled', $html);
        $this->assertStringContainsString('inboundForm', $html);
        $this->assertStringContainsString('Enter at least one Mobile or Landline number.', $html);

        $this->post('/program-inbound-numbers', [
            'campaign' => 'Mynt',
            'mobile_numbers' => ['09260484530', '09260484550'],
            'landline_numbers' => ['53229111'],
            'media_gateway_id' => $gateway->id,
            'port' => '999',
            'network' => 'Forged',
        ])->assertRedirect();

        $this->assertDatabaseHas('program_inbound_numbers', [
            'number' => '09260484530',
            'media_gateway_id' => $gateway->id,
            'port' => '1',
            'network' => 'Globe',
        ]);
        $record = ProgramInboundNumber::query()->where('number', '09260484530')->firstOrFail();
        $this->assertSame(['09260484530', '09260484550'], $record->mobile_numbers);
        $this->assertSame(['53229111'], $record->landline_numbers);

        $this->post('/program-inbound-numbers', [
            'campaign' => 'Mynt',
            'mobile_numbers' => ['09170003333'],
        ])->assertRedirect()->assertSessionHasErrors('media_gateway_id');

        $this->post('/program-inbound-numbers', [
            'campaign' => 'Mynt',
            'landline_numbers' => ['53229521'],
        ])->assertRedirect();

        $landlineOnly = ProgramInboundNumber::query()->where('number', '53229521')->firstOrFail();
        $this->assertNull($landlineOnly->media_gateway_id);
        $this->assertNull($landlineOnly->port);
        $this->assertNull($landlineOnly->network);

        $this->post('/program-inbound-numbers', [
            'campaign' => 'Mynt',
            'mobile_numbers' => ['09170004444'],
            'media_gateway_id' => $gateway->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $mobileOnly = ProgramInboundNumber::query()->where('number', '09170004444')->firstOrFail();
        $this->assertSame(['09170004444'], $mobileOnly->mobile_numbers);
        $this->assertNull($mobileOnly->landline_numbers);
        $this->assertSame($gateway->id, $mobileOnly->media_gateway_id);
    }

    public function test_add_and_edit_use_centered_modal_and_plus_icon_only(): void
    {
        $this->actingAs($this->admin);
        $html = $this->get('/program-inbound-numbers')->assertOk()->getContent();
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('id="operationAddButton"', $html);
        $this->assertStringContainsString('aria-label="Add"', $html);
        $this->assertStringNotContainsString('id="operationAddButton">Add', $html);
        $this->assertStringContainsString('>+</button>', $html);
        $this->assertStringContainsString('pin-modal-backdrop', $html);
        $this->assertStringContainsString('pin-modal', $html);
        $this->assertStringContainsString('Add Program Inbound Number', $html);
        $this->assertStringContainsString('id="field_remarks"', $html);
        $this->assertStringNotContainsString('Add Program Inbound Number</h1>', $html);
        $this->assertMatchesRegularExpression('/\.pin-modal-backdrop\.visible\s*\{[^}]*align-items:\s*center/', $css);
        $this->assertMatchesRegularExpression('/\.pin-modal-backdrop\.visible\s*\{[^}]*justify-content:\s*center/', $css);
    }

    public function test_toolbar_matches_other_tabs_without_status_filter(): void
    {
        $this->actingAs($this->admin);
        $page = $this->get('/program-inbound-numbers')->assertOk();
        $html = $page->getContent();

        $page->assertSee('id="operationSearchInput"', false)
            ->assertSee('Search Program Inbound Numbers...', false)
            ->assertSee('>Search</button>', false)
            ->assertSee('id="transferButton"', false)
            ->assertSee('id="operationAddButton"', false)
            ->assertDontSee('All Statuses')
            ->assertDontSee('Filter by status')
            ->assertDontSee('data-clear-search', false)
            ->assertDontSee('id="pinReset"', false);

        $this->assertDoesNotMatchRegularExpression('/name="status"/', $html);
        $this->assertDoesNotMatchRegularExpression('/>Reset</', $html);
        $searchPos = strpos($html, 'id="operationSearchForm"');
        $searchBtnPos = strpos($html, '>Search</button>');
        $transferPos = strpos($html, 'id="transferButton"');
        $plusPos = strpos($html, 'id="operationAddButton"');
        $this->assertNotFalse($searchPos);
        $this->assertTrue($searchBtnPos > $searchPos);
        $this->assertTrue($transferPos > $searchBtnPos);
        $this->assertTrue($plusPos > $transferPos);

        ChannelAllocationCampaign::create(['name' => 'Visible Campaign']);
        $this->post('/program-inbound-numbers', [
            'campaign' => 'Visible Campaign',
            'landline_numbers' => ['111'],
        ])->assertRedirect();
        $this->post('/program-inbound-numbers', [
            'campaign' => 'Hidden Campaign',
            'landline_numbers' => ['222'],
        ])->assertRedirect();

        $searched = $this->get('/program-inbound-numbers?search=Visible')->assertOk();
        $searched->assertSee('Visible Campaign')
            ->assertDontSee('data-clear-search', false)
            ->assertDontSee('id="pinReset"', false);
        $this->assertDoesNotMatchRegularExpression('/>Reset</', $searched->getContent());
        $this->assertStringNotContainsString('Hidden Campaign', \Illuminate\Support\Str::between($searched->getContent(), '<table', '</table>'));
    }

    public function test_data_transfer_uses_inbound_columns_and_existing_gsm_gateway(): void
    {
        $this->actingAs($this->admin);
        ChannelAllocationCampaign::create(['name' => 'Mynt']);
        ChannelAllocationCampaign::create(['name' => 'Atome']);
        $gateway = MediaGateway::create([
            'site_name' => 'Alcar',
            'site_code' => 'PIN-IMP-1',
            'ip_address' => '192.168.1.10',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
            'port' => '1',
            'network' => 'Globe',
        ]);

        $this->get('/program-inbound-numbers')
            ->assertOk()
            ->assertSee('Data Transfer')
            ->assertSee('Import Data')
            ->assertSee('Export Data')
            ->assertDontSee('Download Sample Template')
            ->assertSee('Download Excel Template')
            ->assertSee('>Campaign</th>', false)
            ->assertDontSee('>Number</th>', false)
            ->assertDontSee('>Program</th>', false);

        $template = $this->get('/program-inbound-numbers/import/template')
            ->assertOk()
            ->assertDownload('program-inbound-numbers-template.xlsx');
        [$headers] = app(XlsxService::class)->read($template->getFile()->getPathname());
        $this->assertSame([
            'Campaign',
            'Mobile',
            'Landline',
            'GSM Gateway',
            'Port',
            'Network',
            'Remarks',
        ], $headers);
        $this->assertNotContains('Id', $headers);
        $this->assertNotContains('Number', $headers);
        $this->assertNotContains('Program', $headers);

        $preview = $this->postJson('/program-inbound-numbers/import/preview', [
            'file' => $this->upload($this->spreadsheet([
                ['Campaign', 'Mobile', 'Landline', 'GSM Gateway', 'Port', 'Network', 'Remarks'],
                ['Mynt', "09260484530\n09260484550", '53229111', '192.168.1.10', '', '', 'Primary inbound'],
                ['', '', '53229521', '', '', '', 'No mobile inbound number'],
                ['Unknown Campaign', '09170001111', '', '192.168.1.10', '1', 'Globe', ''],
                ['Atome', '09170002222', '', '10.0.0.99', '1', 'Globe', ''],
                ['Atome', '09170003333', '', '192.168.1.10', '9', 'Globe', ''],
                ['Atome', '09170004444', '', '192.168.1.10', '1', 'Smart', ''],
                ['Atome', '09170005555', '', '', '', '', ''],
            ])),
        ])->assertOk()->json();

        $this->assertFalse($preview['valid']);
        $this->assertTrue($preview['rows'][0]['valid']);
        $this->assertTrue($preview['rows'][1]['valid']);
        $this->assertSame('Mynt', $preview['rows'][1]['campaign']);
        $this->assertSame('53229521', $preview['rows'][1]['landline']);
        $this->assertSame('', $preview['rows'][1]['gsm_gateway']);
        $this->assertFalse($preview['rows'][2]['valid']);
        $this->assertStringContainsString('Campaign does not exist', $preview['rows'][2]['error']);
        $this->assertFalse($preview['rows'][3]['valid']);
        $this->assertStringContainsString('GSM Gateway', $preview['rows'][3]['error']);
        $this->assertFalse($preview['rows'][4]['valid']);
        $this->assertStringContainsString('Port must match', $preview['rows'][4]['error']);
        $this->assertFalse($preview['rows'][5]['valid']);
        $this->assertStringContainsString('Network must match', $preview['rows'][5]['error']);
        $this->assertFalse($preview['rows'][6]['valid']);
        $this->assertStringContainsString('GSM Gateway is required', $preview['rows'][6]['error']);

        $ok = $this->postJson('/program-inbound-numbers/import/preview', [
            'file' => $this->upload($this->spreadsheet([
                ['Campaign', 'Mobile', 'Landline', 'GSM Gateway', 'Port', 'Network', 'Remarks'],
                ['Mynt', "09260484530\n09260484550", '53229111', '192.168.1.10', '', '', 'Primary inbound'],
                ['', '', '53229521', '', '', '', 'No mobile inbound number'],
                ['Atome', '09170006666', '', '192.168.1.10', '1', 'Globe', ''],
            ])),
        ])->assertOk()->json();
        $this->assertTrue($ok['valid'], $ok['rows'][0]['error'] ?? '');
        $this->postJson('/program-inbound-numbers/import/confirm', ['token' => $ok['token']])
            ->assertOk()
            ->assertJson(['ok' => true, 'records' => 3]);

        $this->assertSame(3, ProgramInboundNumber::count());
        $imported = ProgramInboundNumber::query()->where('number', '09260484530')->firstOrFail();
        $this->assertSame('Mynt', $imported->campaign->name);
        $this->assertSame(['09260484530', '09260484550'], $imported->mobile_numbers);
        $this->assertSame(['53229111'], $imported->landline_numbers);
        $this->assertSame($gateway->id, $imported->media_gateway_id);
        $this->assertSame('1', $imported->port);
        $this->assertSame('Globe', $imported->network);
        $this->assertSame('Primary inbound', $imported->remarks);

        $landlineOnly = ProgramInboundNumber::query()->where('number', '53229521')->firstOrFail();
        $this->assertSame('Mynt', $landlineOnly->campaign->name);
        $this->assertNull($landlineOnly->media_gateway_id);
        $this->assertNull($landlineOnly->port);
        $this->assertNull($landlineOnly->network);

        $dup = $this->postJson('/program-inbound-numbers/import/preview', [
            'file' => $this->upload($this->spreadsheet([
                ['Campaign', 'Mobile', 'Landline', 'GSM Gateway', 'Port', 'Network', 'Remarks'],
                ['Mynt', '09260484530', '', '192.168.1.10', '1', 'Globe', ''],
            ])),
        ])->assertOk()->json();
        $this->assertFalse($dup['valid']);
        $this->assertStringContainsString('already exists', $dup['rows'][0]['error']);

        $export = $this->get('/program-inbound-numbers/export')->assertOk()->assertDownload('program-inbound-numbers.xlsx');
        [$exportHeaders, $exportRows] = app(XlsxService::class)->read($export->getFile()->getPathname());
        $this->assertSame([
            'Campaign',
            'Mobile',
            'Landline',
            'GSM Gateway',
            'Port',
            'Network',
            'Remarks',
        ], $exportHeaders);
        $this->assertNotContains('Id', $exportHeaders);
        $this->assertContains("09260484530\n09260484550", array_column($exportRows, 1));
        $this->assertContains('192.168.1.10', array_column($exportRows, 3));
    }

    public function test_add_edit_and_import_share_validation_rules(): void
    {
        $this->actingAs($this->admin);
        ChannelAllocationCampaign::create(['name' => 'Mynt']);
        $gateway = MediaGateway::create([
            'site_name' => 'Alcar',
            'site_code' => 'PIN-VAL-1',
            'ip_address' => '192.168.1.10',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
            'port' => '1',
            'network' => 'Globe',
        ]);

        $html = $this->get('/program-inbound-numbers')->assertOk()->getContent();
        $this->assertStringContainsString('setCustomValidity', $html);
        $this->assertStringContainsString('must contain only digits', $html);

        $this->post('/program-inbound-numbers', [
            'landline_numbers' => ['53229111'],
        ])->assertRedirect()->assertSessionHasErrors('campaign');

        $this->post('/program-inbound-numbers', [
            'campaign' => 'Mynt',
        ])->assertRedirect()->assertSessionHasErrors('mobile_numbers');

        $this->post('/program-inbound-numbers', [
            'campaign' => 'Mynt',
            'mobile_numbers' => ['09260484530'],
        ])->assertRedirect()->assertSessionHasErrors('media_gateway_id');

        $this->post('/program-inbound-numbers', [
            'campaign' => 'Mynt',
            'mobile_numbers' => ['09AB'],
            'media_gateway_id' => $gateway->id,
        ])->assertRedirect()->assertSessionHasErrors('mobile_numbers');

        $this->post('/program-inbound-numbers', [
            'campaign' => 'Mynt',
            'landline_numbers' => ['5322-9111'],
        ])->assertRedirect()->assertSessionHasErrors('landline_numbers');

        $this->post('/program-inbound-numbers', [
            'campaign' => 'Mynt',
            'mobile_numbers' => ['09260484530'],
            'landline_numbers' => ['09260484530'],
            'media_gateway_id' => $gateway->id,
        ])->assertRedirect()->assertSessionHasErrors('number');

        $this->post('/program-inbound-numbers', [
            'campaign' => 'Mynt',
            'mobile_numbers' => ['09260484530', '09260484550'],
            'landline_numbers' => ['53229111'],
            'media_gateway_id' => $gateway->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->post('/program-inbound-numbers', [
            'campaign' => 'Mynt',
            'landline_numbers' => ['09260484550'],
        ])->assertRedirect()->assertSessionHasErrors('number');

        $record = ProgramInboundNumber::query()->where('number', '09260484530')->firstOrFail();
        $this->put('/program-inbound-numbers/'.$record->id, [
            'campaign' => 'Mynt',
            'mobile_numbers' => ['09260484530', '09260484550'],
            'landline_numbers' => ['53229111'],
            'media_gateway_id' => $gateway->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $preview = $this->postJson('/program-inbound-numbers/import/preview', [
            'file' => $this->upload($this->spreadsheet([
                ['Campaign', 'Mobile', 'Landline', 'GSM Gateway', 'Port', 'Network', 'Remarks'],
                ['', '09170001111', '', '192.168.1.10', '1', 'Globe', ''],
                ['Unknown Campaign', '09170002222', '', '192.168.1.10', '1', 'Globe', ''],
                ['Mynt', '09AB', '', '192.168.1.10', '1', 'Globe', ''],
                ['Mynt', '', '5322-9111', '', '', '', ''],
                ['Mynt', '09260484550', '', '192.168.1.10', '1', 'Globe', ''],
                ['Mynt', '09170003333', '09170003333', '192.168.1.10', '1', 'Globe', ''],
                ['Mynt', '09170004444', '', '192.168.1.10', '9', 'Globe', ''],
                ['Mynt', '09170005555', '', '192.168.1.10', '1', 'Smart', ''],
            ])),
        ])->assertOk()->json();

        $this->assertFalse($preview['valid']);
        $this->assertStringContainsString('Campaign is required', $preview['rows'][0]['error']);
        $this->assertStringContainsString('Campaign does not exist', $preview['rows'][1]['error']);
        $this->assertStringContainsString('Mobile must contain only digits', $preview['rows'][2]['error']);
        $this->assertStringContainsString('Landline must contain only digits', $preview['rows'][3]['error']);
        $this->assertStringContainsString('already exists', $preview['rows'][4]['error']);
        $this->assertStringContainsString('duplicated', $preview['rows'][5]['error']);
        $this->assertStringContainsString('Port must match', $preview['rows'][6]['error']);
        $this->assertStringContainsString('Network must match', $preview['rows'][7]['error']);
    }

    /**
     * @param  list<list<string|int>>  $rows
     */
    private function spreadsheet(array $rows): string
    {
        $headers = array_shift($rows);

        return app(XlsxService::class)->export($headers, $rows, 'pin-import.xlsx');
    }

    private function upload(string $path): UploadedFile
    {
        return new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
