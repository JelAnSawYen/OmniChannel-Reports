<?php

namespace Tests\Feature;

use App\Models\ChannelAllocationCampaign;
use App\Models\GatewaySimAssignment;
use App\Models\GlobeSim;
use App\Models\MediaGateway;
use App\Models\ProgramInboundNumber;
use App\Models\SipChannel;
use App\Models\SipChannelNumber;
use App\Models\SmartSim;
use App\Models\User;
use App\Models\UserType;
use App\Services\XlsxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
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
        $this->seedChannelNumbers([
            '111',
            '222',
            '53229111',
            '53229112',
            '53229521',
            '09170001111',
            '09170002222',
        ]);
    }

    public function test_table_uses_inbound_columns_alignment_and_stacked_numbers(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create(['name' => 'Mynt']);
        $this->assignSim('globe', '09260484530', 'GSM-01', 7, '10.1.1.1');
        $this->assignSim('globe', '09260484550', 'GSM-02', 8, '10.1.1.2');
        $this->assignSim('globe', '09544086968', 'GSM-01', 4, '10.1.1.1');

        ProgramInboundNumber::create([
            'number' => '09260484530',
            'program' => 'Mynt',
            'status' => 'Active',
            'campaign_id' => $campaign->id,
            'mobile_numbers' => ['09260484530', '09260484550', '09544086968'],
            'landline_numbers' => ['53229111', '53229112'],
            'network' => 'Globe SIM',
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

        $tableHtml = Str::between($html, '<table class="pin-table"', '</table>');

        $page->assertSee('class="pin-table"', false)
            ->assertSee('class="pin-campaign"', false)
            ->assertSee('09260484530')
            ->assertSee('09260484550')
            ->assertSee('09544086968')
            ->assertSee('53229111')
            ->assertSee('53229112')
            ->assertSee('GSM-01')
            ->assertSee('GSM-02')
            ->assertSee('Globe SIM')
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
        $before = ChannelAllocationCampaign::count();
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

        $this->assertSame($before + 1, ChannelAllocationCampaign::count());
        $this->assertDatabaseHas('program_inbound_numbers', [
            'number' => '09170001111',
            'campaign_id' => $existing->id,
            'program' => 'Mynt',
        ]);

        $this->post('/program-inbound-numbers', [
            'campaign' => 'RCBC Bankard',
            'landline_numbers' => ['09170002222'],
        ])->assertRedirect();

        $this->assertSame($before + 2, ChannelAllocationCampaign::count());
        $created = ChannelAllocationCampaign::query()->where('name', 'RCBC Bankard')->first();
        $this->assertNotNull($created);
        $this->assertDatabaseHas('program_inbound_numbers', [
            'number' => '09170002222',
            'campaign_id' => $created->id,
            'program' => 'RCBC Bankard',
        ]);
    }

    public function test_network_filters_mobiles_and_auto_fills_gateway_port(): void
    {
        $this->actingAs($this->admin);
        ChannelAllocationCampaign::create(['name' => 'Mynt']);
        $globe = $this->assignSim('globe', '09260484530', 'GSM-01', 7, '10.1.1.1');
        $this->assignSim('globe', '09260484550', 'GSM-02', 8, '10.1.1.2');
        $this->assignSim('smart', '09170006666', 'GSM-03', 5, '10.1.1.3');

        $page = $this->get('/program-inbound-numbers')->assertOk();
        $html = $page->getContent();
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('id="field_network"', $html);
        $this->assertStringContainsString('<option value="Globe SIM">Globe SIM</option>', $html);
        $this->assertStringContainsString('<option value="Smart SIM">Smart SIM</option>', $html);
        $this->assertDoesNotMatchRegularExpression('/id="field_network"[^>]*\breadonly/', $html);
        $this->assertStringContainsString('id="pinMobileInput"', $html);
        $this->assertStringContainsString('id="pinLandlineInput"', $html);
        $this->assertStringContainsString('Selected Mobile Numbers', $html);
        $this->assertStringContainsString('id="pinSelectedTable"', $html);
        $this->assertStringContainsString('>Mobile Number</th>', $html);
        $this->assertStringContainsString('>GSM Gateway</th>', $html);
        $this->assertStringContainsString('>Port</th>', $html);
        $this->assertStringContainsString('>Action</th>', $html);
        $this->assertStringContainsString('data-pin-remove-mobile', $html);
        $this->assertStringNotContainsString('>Remove</', $html);
        $this->assertStringNotContainsString('Enter mobile number and press Enter', $html);
        $this->assertStringNotContainsString('You can enter multiple mobile numbers.', $html);
        $this->assertStringNotContainsString('id="field_media_gateway_id"', $html);
        $this->assertStringNotContainsString('id="pinGsmWrap"', $html);
        $this->assertStringNotContainsString('toggleGsm', $html);
        $this->assertStringNotContainsString('Please select a GSM Gateway.', $html);
        $this->assertStringContainsString('09260484530', $html);
        $this->assertStringContainsString('09260484550', $html);
        $this->assertStringContainsString('09170006666', $html);
        $this->assertStringContainsString('53229111', $html);
        $this->assertStringContainsString('GSM-01', $html);
        $this->assertStringContainsString('inboundForm', $html);
        $this->assertStringContainsString('Enter at least one Mobile or Landline number.', $html);
        $this->assertStringContainsString('.pin-selected-title', $css);
        $this->assertMatchesRegularExpression('/\.pin-selected-title\s*\{[^}]*text-align:\s*center/', $css);
        $this->assertMatchesRegularExpression('/\.pin-selected-table th,[\s\S]*?text-align:\s*center/', $css);

        $this->post('/program-inbound-numbers', [
            'campaign' => 'Mynt',
            'network' => 'Globe SIM',
            'mobile_numbers' => ['09260484530', '09260484550'],
            'landline_numbers' => ['53229111'],
            'media_gateway_id' => 999,
            'port' => '999',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $record = ProgramInboundNumber::query()->where('number', '09260484530')->firstOrFail();
        $this->assertSame(['09260484530', '09260484550'], $record->mobile_numbers);
        $this->assertSame(['53229111'], $record->landline_numbers);
        $this->assertSame('Globe SIM', $record->network);
        $this->assertSame('GSM-01', $record->mobileDisplayRows()[0]['hostname']);
        $this->assertSame('7', $record->mobileDisplayRows()[0]['port']);
        $this->assertSame('GSM-02', $record->mobileDisplayRows()[1]['hostname']);
        $this->assertSame('8', $record->mobileDisplayRows()[1]['port']);
        $this->assertSame($globe['gateway']->id, $record->media_gateway_id);

        $this->post('/program-inbound-numbers', [
            'campaign' => 'Mynt',
            'mobile_numbers' => ['09170003333'],
        ])->assertRedirect()->assertSessionHasErrors('network');

        $this->post('/program-inbound-numbers', [
            'campaign' => 'Mynt',
            'landline_numbers' => ['53229521'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $landlineOnly = ProgramInboundNumber::query()->where('number', '53229521')->firstOrFail();
        $this->assertNull($landlineOnly->media_gateway_id);
        $this->assertNull($landlineOnly->port);
        $this->assertNull($landlineOnly->network);

        $this->post('/program-inbound-numbers', [
            'campaign' => 'Mynt',
            'network' => 'Globe SIM',
            'mobile_numbers' => ['09990001111'],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $manual = ProgramInboundNumber::query()->where('number', '09990001111')->firstOrFail();
        $this->assertSame(['09990001111'], $manual->mobile_numbers);
        $this->assertSame('', $manual->mobileDisplayRows()[0]['hostname']);
        $this->assertSame('', $manual->mobileDisplayRows()[0]['port']);
        $this->assertNull($manual->media_gateway_id);

        $this->assertSame(2, GlobeSim::count());
        $this->assertSame(1, SmartSim::count());
        $this->delete('/program-inbound-numbers/'.$record->id)->assertRedirect();
        $this->assertSame(2, GlobeSim::count());
        $this->assertSame(1, GatewaySimAssignment::query()->where('sim_type', 'globe')->where('sim_id', $globe['sim']->id)->count());
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
        $this->assertMatchesRegularExpression('/\.pin-modal-backdrop\.visible\s*\{[^}]*align-items:\s*flex-start/', $css);
        $this->assertMatchesRegularExpression('/\.pin-modal-backdrop\.visible\s*\{[^}]*justify-content:\s*center/', $css);
        $this->assertMatchesRegularExpression('/\.pin-modal-backdrop\.visible\s*\{[^}]*overflow-y:\s*auto/', $css);
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
        $this->assertStringNotContainsString('Hidden Campaign', Str::betweenFirst($searched->getContent(), 'class="pin-table"', '</table>'));
    }

    public function test_data_transfer_uses_inbound_columns_and_existing_gsm_gateway(): void
    {
        $this->actingAs($this->admin);
        ChannelAllocationCampaign::create(['name' => 'Mynt']);
        ChannelAllocationCampaign::create(['name' => 'Atome']);
        $globe = $this->assignSim('globe', '09260484530', 'GSM-01', 7, '10.1.1.1');
        $this->assignSim('globe', '09260484550', 'GSM-02', 8, '10.1.1.2');
        $this->assignSim('globe', '09170006666', 'GSM-01', 5, '10.1.1.1');

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
                ['Mynt', "09260484530\n09260484550", '53229111', '', '', 'Globe SIM', 'Primary inbound'],
                ['', '', '53229521', '', '', '', 'No mobile inbound number'],
                ['Unknown Campaign', '09170001111', '', 'GSM-01', '7', 'Globe SIM', ''],
                ['Atome', '09260484530', '', 'GSM-99', '7', 'Globe SIM', ''],
                ['Atome', '09260484550', '', 'GSM-02', '9', 'Globe SIM', ''],
                ['Atome', '09170006666', '', 'GSM-01', '5', 'WrongNet', ''],
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
        $this->assertStringContainsString('Network is required', $preview['rows'][6]['error']);

        $ok = $this->postJson('/program-inbound-numbers/import/preview', [
            'file' => $this->upload($this->spreadsheet([
                ['Campaign', 'Mobile', 'Landline', 'GSM Gateway', 'Port', 'Network', 'Remarks'],
                ['Mynt', "09260484530\n09260484550", '53229111', '', '', 'Globe SIM', 'Primary inbound'],
                ['', '', '53229521', '', '', '', 'No mobile inbound number'],
                ['Atome', '09170006666', '', '', '', 'Globe SIM', ''],
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
        $this->assertSame($globe['gateway']->id, $imported->media_gateway_id);
        $this->assertSame('7', $imported->port);
        $this->assertSame('Globe SIM', $imported->network);
        $this->assertSame('Primary inbound', $imported->remarks);
        $this->assertSame('GSM-01', $imported->mobileDisplayRows()[0]['hostname']);
        $this->assertSame('GSM-02', $imported->mobileDisplayRows()[1]['hostname']);

        $landlineOnly = ProgramInboundNumber::query()->where('number', '53229521')->firstOrFail();
        $this->assertSame('Mynt', $landlineOnly->campaign->name);
        $this->assertNull($landlineOnly->media_gateway_id);
        $this->assertNull($landlineOnly->port);
        $this->assertNull($landlineOnly->network);

        $dup = $this->postJson('/program-inbound-numbers/import/preview', [
            'file' => $this->upload($this->spreadsheet([
                ['Campaign', 'Mobile', 'Landline', 'GSM Gateway', 'Port', 'Network', 'Remarks'],
                ['Mynt', '09260484530', '', '', '', 'Globe SIM', ''],
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
        $this->assertContains("GSM-01\nGSM-02", array_column($exportRows, 3));
    }

    public function test_add_edit_and_import_share_validation_rules(): void
    {
        $this->actingAs($this->admin);
        ChannelAllocationCampaign::create(['name' => 'Mynt']);
        $this->assignSim('globe', '09260484530', 'GSM-01', 7, '10.1.1.1');
        $this->assignSim('globe', '09260484550', 'GSM-02', 8, '10.1.1.2');

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
        ])->assertRedirect()->assertSessionHasErrors('network');

        $this->post('/program-inbound-numbers', [
            'campaign' => 'Mynt',
            'network' => 'Globe SIM',
            'mobile_numbers' => ['09AB'],
        ])->assertRedirect()->assertSessionHasErrors('mobile_numbers');

        $this->post('/program-inbound-numbers', [
            'campaign' => 'Mynt',
            'landline_numbers' => ['5322-9111'],
        ])->assertRedirect()->assertSessionHasErrors('landline_numbers');

        $this->post('/program-inbound-numbers', [
            'campaign' => 'Mynt',
            'landline_numbers' => ['99999999'],
        ])->assertRedirect()->assertSessionHasErrors('landline_numbers');

        $this->post('/program-inbound-numbers', [
            'campaign' => 'Mynt',
            'network' => 'Globe SIM',
            'mobile_numbers' => ['09260484530'],
            'landline_numbers' => ['09260484530'],
        ])->assertRedirect()->assertSessionHasErrors('number');

        $this->post('/program-inbound-numbers', [
            'campaign' => 'Mynt',
            'network' => 'Globe SIM',
            'mobile_numbers' => ['09260484530', '09260484550'],
            'landline_numbers' => ['53229111'],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->post('/program-inbound-numbers', [
            'campaign' => 'Mynt',
            'landline_numbers' => ['09260484550'],
        ])->assertRedirect()->assertSessionHasErrors('number');

        $record = ProgramInboundNumber::query()->where('number', '09260484530')->firstOrFail();
        $this->put('/program-inbound-numbers/'.$record->id, [
            'campaign' => 'Mynt',
            'network' => 'Globe SIM',
            'mobile_numbers' => ['09260484530', '09260484550'],
            'landline_numbers' => ['53229111'],
            'remarks' => 'Updated remarks',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $record->refresh();
        $this->assertSame('Updated remarks', $record->remarks);
        $this->assertSame('GSM-01', $record->mobileDisplayRows()[0]['hostname']);

        $preview = $this->postJson('/program-inbound-numbers/import/preview', [
            'file' => $this->upload($this->spreadsheet([
                ['Campaign', 'Mobile', 'Landline', 'GSM Gateway', 'Port', 'Network', 'Remarks'],
                ['', '09170001111', '', '', '', 'Globe SIM', ''],
                ['Unknown Campaign', '09170002222', '', '', '', 'Globe SIM', ''],
                ['Mynt', '09AB', '', '', '', 'Globe SIM', ''],
                ['Mynt', '', '5322-9111', '', '', '', ''],
                ['Mynt', '09260484550', '', '', '', 'Globe SIM', ''],
                ['Mynt', '09170003333', '09170003333', '', '', 'Globe SIM', ''],
                ['Mynt', '09260484530', '', 'GSM-01', '9', 'Globe SIM', ''],
                ['Mynt', '09260484530', '', 'GSM-01', '7', 'WrongNet', ''],
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

    /**
     * @param  list<string>  $numbers
     */
    private function seedChannelNumbers(array $numbers): void
    {
        $campaign = ChannelAllocationCampaign::query()->first()
            ?? ChannelAllocationCampaign::create(['name' => 'PIN Channels']);
        $sip = SipChannel::query()->first()
            ?? SipChannel::create([
                'campaign_id' => $campaign->id,
                'etpi_sip_name' => 'SIP_PIN_01',
            ]);

        foreach ($numbers as $number) {
            SipChannelNumber::query()->firstOrCreate(
                ['channel_number' => $number],
                ['sip_channel_id' => $sip->id]
            );
        }
    }

    /**
     * @return array{gateway: MediaGateway, sim: GlobeSim|SmartSim}
     */
    private function assignSim(string $type, string $mobile, string $hostname, int $port, string $ip): array
    {
        $gateway = MediaGateway::query()->where('hostname', $hostname)->first()
            ?? MediaGateway::create([
                'hostname' => $hostname,
                'site_name' => 'Alcar',
                'site_code' => 'PIN-'.$hostname.'-'.$port,
                'ip_address' => $ip,
                'username' => 'root',
                'database' => 'asteriskcdrdb',
                'channel_count' => 16,
            ]);

        $model = $type === 'smart' ? SmartSim::class : GlobeSim::class;
        $sim = $model::query()->where('mobile_number', $mobile)->first()
            ?? $model::query()->create([
                'imei' => substr(str_pad(preg_replace('/\D/', '', $mobile).$port, 15, '0', STR_PAD_LEFT), 0, 15),
                'mobile_number' => $mobile,
                'plan' => 'Unli Surf',
                'ip_address' => $ip,
                'account_number' => 'ACC-'.$mobile,
                'contract_start' => '2026-01-01',
                'contract_end' => '2026-12-31',
            ]);

        GatewaySimAssignment::query()->firstOrCreate(
            [
                'sim_type' => $type,
                'sim_id' => $sim->id,
            ],
            [
                'media_gateway_id' => $gateway->id,
                'port' => $port,
            ]
        );

        return ['gateway' => $gateway, 'sim' => $sim];
    }
}
