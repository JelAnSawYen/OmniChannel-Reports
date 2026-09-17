<?php

namespace Tests\Feature;

use App\Models\ChannelAllocationCampaign;
use App\Models\MediaGateway;
use App\Models\PdcGroup;
use App\Models\PdcServer;
use App\Models\SipChannel;
use App\Models\SipChannelNumber;
use App\Models\User;
use App\Models\UserType;
use App\Support\OperationCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OperationsCrudSearchTest extends TestCase
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

    public static function inventoryModules(): array
    {
        return [
            'globe-sim' => ['globe-sim', [
                'create' => ['imei' => '356938035643809', 'mobile_number' => '09170000001', 'network' => 'Globe', 'plan' => 'Plan A', 'ip_address' => '10.70.0.1', 'account_number' => 'ACC-ALPHA', 'contract_start' => '1/1/2026', 'contract_end' => '12/31/2026'],
                'other' => ['imei' => '356938035643810', 'mobile_number' => '09170000002', 'network' => 'Globe', 'plan' => 'Plan B', 'ip_address' => '10.70.0.2', 'account_number' => 'ACC-BETA', 'contract_start' => '2/1/2026', 'contract_end' => '11/30/2026'],
                'update' => ['imei' => '356938035643809', 'mobile_number' => '09170000001', 'network' => 'Globe', 'plan' => 'Plan A', 'ip_address' => '10.70.0.1', 'account_number' => 'ACC-ALPHA-UPD', 'contract_start' => '1/1/2026', 'contract_end' => '12/31/2026'],
                'updated' => ['account_number' => 'ACC-ALPHA-UPD', 'plan' => 'Plan A'],
                'kept' => ['mobile_number' => '09170000002'],
                'search' => 'ACC-ALPHA-UPD',
                'hidden' => '09170000002',
            ]],
            'smart-sim' => ['smart-sim', [
                'create' => ['imei' => '356938035643901', 'mobile_number' => '09280000001', 'network' => 'Smart', 'plan' => 'Plan A', 'ip_address' => '10.80.0.1', 'account_number' => 'ACC-ALPHA', 'contract_start' => '1/1/2026', 'contract_end' => '12/31/2026'],
                'other' => ['imei' => '356938035643902', 'mobile_number' => '09280000002', 'network' => 'Smart', 'plan' => 'Plan B', 'ip_address' => '10.80.0.2', 'account_number' => 'ACC-BETA', 'contract_start' => '2/1/2026', 'contract_end' => '11/30/2026'],
                'update' => ['imei' => '356938035643901', 'mobile_number' => '09280000001', 'network' => 'Smart', 'plan' => 'Plan A', 'ip_address' => '10.80.0.1', 'account_number' => 'ACC-ALPHA-UPD', 'contract_start' => '1/1/2026', 'contract_end' => '12/31/2026'],
                'updated' => ['account_number' => 'ACC-ALPHA-UPD', 'plan' => 'Plan A'],
                'kept' => ['mobile_number' => '09280000002'],
                'search' => 'ACC-ALPHA-UPD',
                'hidden' => '09280000002',
            ]],
            'program-inbound-numbers' => ['program-inbound-numbers', [
                'create' => ['campaign' => 'Program A', 'landline_numbers' => ['0325000001'], 'number' => '0325000001'],
                'other' => ['campaign' => 'Program B', 'landline_numbers' => ['0325000002'], 'number' => '0325000002'],
                'update' => ['campaign' => 'Program A Updated', 'landline_numbers' => ['0325000001']],
                'updated' => ['program' => 'Program A Updated'],
                'kept' => ['number' => '0325000002'],
                'search' => 'Program A Updated',
                'hidden' => '0325000002',
            ]],
            'signal-boosters' => ['signal-boosters', [
                'create' => ['model' => 'SB-100', 'serial_number' => 'SB-ALPHA-1', 'location' => 'Estancia', 'status' => 'Active'],
                'other' => ['model' => 'SB-200', 'serial_number' => 'SB-BETA-1', 'location' => 'CTN', 'status' => 'Active'],
                'update' => ['model' => 'SB-100X', 'serial_number' => 'SB-ALPHA-1', 'location' => 'Estancia', 'status' => 'Inactive'],
                'updated' => ['model' => 'SB-100X', 'status' => 'Inactive'],
                'kept' => ['serial_number' => 'SB-BETA-1'],
                'search' => 'SB-100X',
                'hidden' => 'SB-BETA-1',
            ]],
            'defective-gsm' => ['defective-gsm', [
                'create' => ['asset_code' => 'DG-ALPHA', 'location' => 'SC5', 'issue' => 'No signal', 'reported_on' => '2026-08-01', 'status' => 'Open'],
                'other' => ['asset_code' => 'DG-BETA', 'location' => 'Alcar', 'issue' => 'Broken antenna', 'reported_on' => '2026-08-02', 'status' => 'Open'],
                'update' => ['asset_code' => 'DG-ALPHA', 'location' => 'SC5', 'issue' => 'Repaired radio', 'reported_on' => '2026-08-01', 'status' => 'Closed'],
                'updated' => ['issue' => 'Repaired radio', 'status' => 'Closed'],
                'kept' => ['asset_code' => 'DG-BETA'],
                'search' => 'Repaired radio',
                'hidden' => 'DG-BETA',
            ]],
        ];
    }

    #[DataProvider('inventoryModules')]
    public function test_inventory_module_edits_and_deletes_the_correct_record(string $module, array $case): void
    {
        $this->actingAs($this->admin);

        $config = OperationCatalog::modules()[$module];
        $table = (new $config['model'])->getTable();

        if (in_array($module, ['globe-sim', 'smart-sim'], true)) {
            MediaGateway::create([
                'site_name' => 'Alcar',
                'site_code' => 'SIM-IP-A-'.$module,
                'ip_address' => $case['create']['ip_address'],
                'username' => 'root',
                'database' => 'asteriskcdrdb',
            ]);
            MediaGateway::create([
                'site_name' => 'Alcar',
                'site_code' => 'SIM-IP-B-'.$module,
                'ip_address' => $case['other']['ip_address'],
                'username' => 'root',
                'database' => 'asteriskcdrdb',
            ]);
        }

        if ($module === 'program-inbound-numbers') {
            $campaign = ChannelAllocationCampaign::create(['name' => 'PIN Search']);
            $sip = SipChannel::create([
                'campaign_id' => $campaign->id,
                'etpi_sip_name' => 'SIP_PIN_SEARCH',
            ]);
            foreach (['0325000001', '0325000002'] as $number) {
                SipChannelNumber::create([
                    'sip_channel_id' => $sip->id,
                    'channel_number' => $number,
                ]);
            }
        }

        $this->post('/'.$module, $case['create'])->assertRedirect();
        $this->post('/'.$module, $case['other'])->assertRedirect();

        $record = $config['model']::query()->where($this->identity($case['create'], $case['kept']))->firstOrFail();

        $this->get('/'.$module)
            ->assertOk()
            ->assertSee('data-id="'.$record->id.'"', false)
            ->assertSee('action="'.url('/'.$module.'/'.$record->id).'"', false);

        $this->put('/'.$module.'/'.$record->id, $case['update'])->assertRedirect();

        $this->assertDatabaseHas($table, array_merge(['id' => $record->id], $case['updated']));
        $this->assertDatabaseHas($table, $case['kept']);

        $searched = $this->get('/'.$module.'?search='.$case['search'])
            ->assertOk()
            ->assertSee($case['search']);

        $this->assertStringNotContainsString($case['hidden'], Str::between($searched->getContent(), '<table', '</table>'));

        $this->delete('/'.$module.'/'.$record->id)->assertRedirect();
        $this->assertDatabaseMissing($table, ['id' => $record->id]);
        $this->assertDatabaseHas($table, $case['kept']);
    }

    public function test_pdc_server_add_edit_delete_and_search(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create(['name' => 'BPI Collection']);
        $other = ChannelAllocationCampaign::create(['name' => 'Atome']);

        $this->post('/pdc-servers', [
            'campaign_id' => $campaign->id,
            'location' => 'Estancia',
            'date_endorse' => '9/3/2026',
            'dns' => 'pdc-bpicollections.teamssg.com',
        ])->assertRedirect();

        $group = PdcGroup::where('campaign_id', $campaign->id)->firstOrFail();
        $this->post('/pdc-servers/'.$group->id.'/servers', [
            'hostname' => 'pdc-alpha',
            'ip_address' => '10.1.1.10',
            'os' => 'Linux',
            'ram' => '12GB',
            'cpu' => '8cores',
            'storage' => '120GB',
            'admin_username' => 'admin',
            'password' => 'P@ss!word',
            'sql_db_password' => 'Sql#1',
        ])->assertRedirect();

        $this->post('/pdc-servers', [
            'campaign_id' => $other->id,
            'location' => 'Alcar',
            'date_endorse' => '9/4/2026',
            'dns' => 'pdc-atome.teamssg.com',
        ])->assertRedirect();
        $otherGroup = PdcGroup::where('campaign_id', $other->id)->firstOrFail();
        $this->post('/pdc-servers/'.$otherGroup->id.'/servers', [
            'hostname' => 'pdc-beta',
            'ip_address' => '10.1.1.11',
        ])->assertRedirect();

        $record = PdcServer::where('hostname', 'pdc-alpha')->firstOrFail();

        $this->put('/pdc-servers/'.$group->id.'/servers/'.$record->id, [
            'hostname' => 'pdc-alpha-updated',
            'ip_address' => '10.1.1.10',
            'os' => 'Linux',
            'ram' => '16GB',
            'cpu' => '8cores',
            'storage' => '120GB',
            'admin_username' => 'admin',
        ])->assertRedirect();

        $this->assertDatabaseHas('pdc_servers', [
            'id' => $record->id,
            'hostname' => 'pdc-alpha-updated',
            'pdc_group_id' => $group->id,
        ]);

        $this->get('/pdc-servers?search=pdc-alpha-updated')
            ->assertOk()
            ->assertSee('pdc-alpha-updated')
            ->assertDontSee('pdc-beta');

        $this->delete('/pdc-servers/'.$group->id.'/servers/'.$record->id)->assertRedirect();
        $this->assertDatabaseMissing('pdc_servers', ['id' => $record->id]);
        $this->assertDatabaseHas('pdc_servers', ['hostname' => 'pdc-beta']);
    }

    public function test_gsm_gateway_edits_and_deletes_the_correct_record(): void
    {
        $this->actingAs($this->admin);

        $keep = MediaGateway::create([
            'site_name' => 'Alcar',
            'site_code' => 'KEEP01',
            'ip_address' => '10.2.2.2',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ]);
        $target = MediaGateway::create([
            'site_name' => 'Estancia',
            'site_code' => 'TGT001',
            'ip_address' => '10.2.2.3',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ]);

        $page = $this->get('/gsm-gateways')->assertOk();
        $page->assertSee('data-edit-id="'.$target->id.'"', false)
            ->assertSee('data-delete-id="'.$target->id.'"', false)
            ->assertSee('id="site_name"', false)
            ->assertSee('<select class="form-control" id="site_name" name="site_name" required>', false)
            ->assertDontSee('<input class="form-control" id="site_name" name="site_name" required>', false)
            ->assertSee('data-ca-toggle="'.$target->id.'"', false)
            ->assertSee('Add SIM Assignment', false)
            ->assertSee('id="gsmSimNetwork"', false)
            ->assertSee('Search SIM (IMEI or Mobile Number)...', false)
            ->assertSee('data-gsm-sim-add="'.$target->id.'"', false)
            ->assertDontSee('<select class="form-control" id="network" name="network" required>', false)
            ->assertDontSee('SIM Assignments (', false)
            ->assertSee('<option value="" selected hidden>Select Function</option>', false)
            ->assertSee('<option value="" selected hidden>Select Site</option>', false)
            ->assertSee('<option value="" selected hidden>Select Network</option>', false)
            ->assertSee('<option value="Inbound">Inbound</option>', false)
            ->assertSee('<option value="Outbound">Outbound</option>', false)
            ->assertDontSee('placeholder="Enter hostname"', false)
            ->assertDontSee('placeholder="Enter IP address (e.g. 10.5.20.108)"', false)
            ->assertDontSee('placeholder="Enter serial number"', false)
            ->assertDontSee('placeholder="Enter username"', false)
            ->assertDontSee('placeholder="Enter channel count"', false)
            ->assertDontSee('placeholder="Enter password"', false)
            ->assertSee('>Hostname</label>', false)
            ->assertSee('>IP Address</label>', false)
            ->assertDontSee('Hostname <span class="req">*</span>', false)
            ->assertDontSee('IP Address <span class="req">*</span>', false)
            ->assertDontSee('Function <span class="req">*</span>', false)
            ->assertDontSee('Site <span class="req">*</span>', false)
            ->assertDontSee('Serial Number <span class="req">*</span>', false)
            ->assertDontSee('User <span class="req">*</span>', false)
            ->assertDontSee('Channel Count <span class="req">*</span>', false);

        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('body[data-page="gsm-gateways"] .gsm-host-cell > span', $css);
        $this->assertMatchesRegularExpression('/body\[data-page="gsm-gateways"\] \.gsm-host-cell > span \{\s*font-weight: 700;/', $css);
        $this->assertMatchesRegularExpression('/body\[data-page="gsm-gateways"\] \.gsm-host-cell \{\s*display: flex;\s*justify-content: flex-start;/', $css);
        $this->assertMatchesRegularExpression('/body\[data-page="gsm-gateways"\] \.gsm-table > tbody > tr\.gsm-gateway-row > td:first-child \{\s*text-align: left;/', $css);
        $this->assertMatchesRegularExpression('/\.ca-menu-dropdown \{\s*position: absolute;\s*right: 0;\s*left: auto;\s*top: calc\(100% \+ 3\.75px\);/', $css);
        $this->assertDoesNotMatchRegularExpression('/body\[data-page="gsm-gateways"\] \.gsm-table \.ca-menu \{\s*display: inline-flex;\s*justify-content: center;\s*width: 100%;/', $css);
        foreach (array_values(OperationCatalog::locations()) as $name) {
            $page->assertSee('>'.$name.'</option>', false);
        }

        $this->putJson('/gsm-gateways/'.$target->id, [
            'hostname' => 'gsm-tgt-001',
            'site_name' => 'CTN',
            'site_code' => 'TGT001',
            'ip_address' => '10.2.2.3',
            'channel_count' => 8,
            'device_function' => 'Inbound',
            'network' => 'Globe SIM',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ])->assertOk();

        $this->assertDatabaseHas('media_gateways', [
            'id' => $target->id,
            'site_name' => 'CTN',
        ]);
        $this->assertDatabaseHas('media_gateways', [
            'id' => $keep->id,
            'site_name' => 'Alcar',
        ]);

        $this->deleteJson('/gsm-gateways/'.$target->id)->assertOk();
        $this->assertDatabaseMissing('media_gateways', ['id' => $target->id]);
        $this->assertDatabaseHas('media_gateways', ['id' => $keep->id]);

        $this->postJson('/gsm-gateways', [
            'hostname' => 'bad-host',
            'site_name' => 'Not A Location',
            'site_code' => 'BAD001',
            'ip_address' => '10.2.2.9',
            'channel_count' => 8,
            'device_function' => 'Inbound',
            'network' => 'Globe SIM',
            'username' => 'root',
        ])->assertUnprocessable()->assertJsonValidationErrors('site_name');
    }

    public function test_edit_and_delete_buttons_use_the_compact_outline_style(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('.action-btn.edit:hover', $css);
        $this->assertStringContainsString('.action-btn.delete:hover', $css);
        $this->assertStringContainsString('background:#fff; color:#0b70f7', $css);
        $this->assertStringContainsString('background:#fff; color:#ef4444', $css);

        $campaign = ChannelAllocationCampaign::create(['name' => 'Style Campaign']);
        $group = PdcGroup::create([
            'campaign_id' => $campaign->id,
            'location' => 'Estancia',
        ]);
        PdcServer::create([
            'pdc_group_id' => $group->id,
            'hostname' => 'pdc-style',
            'ip_address' => '10.9.9.9',
            'location' => 'Estancia',
            'role' => 'Primary',
            'status' => 'Active',
        ]);
        MediaGateway::create([
            'site_name' => 'Estancia',
            'site_code' => 'STY001',
            'ip_address' => '10.9.9.10',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ]);

        $this->actingAs($this->admin)->get('/pdc-servers')
            ->assertOk()
            ->assertSee('class="action-btn edit"', false)
            ->assertSee('class="action-btn delete"', false)
            ->assertSee('data-confirm-title="Delete Record"', false)
            ->assertSee('M12 20h9', false)
            ->assertSee('M4 7h16', false);

        $this->actingAs($this->admin)->get('/gsm-gateways')
            ->assertOk()
            ->assertSee('class="ca-menu-btn"', false)
            ->assertSee('class="ca-menu-item edit"', false)
            ->assertSee('class="ca-menu-item delete"', false)
            ->assertDontSee('class="action-btn edit"', false)
            ->assertDontSee('class="action-btn delete"', false)
            ->assertSee('gsm-sim-nested', false)
            ->assertSee('ca-actions-head', false);

        $this->actingAs($this->admin)->get('/program-location/estancia')
            ->assertOk()
            ->assertSee('class="action-btn edit"', false)
            ->assertSee('class="action-btn delete"', false)
            ->assertSee('data-confirm-title="Delete GSM Gateway"', false);

        User::factory()->create([
            'user_type_id' => UserType::where('name', 'Standard User')->value('id'),
            'status' => 'Active',
        ]);

        $this->actingAs($this->admin)->get('/users')
            ->assertOk()
            ->assertSee('class="action-btn edit"', false)
            ->assertSee('class="action-btn delete"', false);
    }

    public function test_sidebar_hides_vertical_scrollbar_styles(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('scrollbar-width: none', $css);
        $this->assertStringContainsString('.sidebar-nav::-webkit-scrollbar', $css);
        $this->assertStringContainsString('overflow-y: auto', $css);
    }

    private function identity(array $create, array $kept): array
    {
        foreach (array_keys($kept) as $key) {
            if (array_key_exists($key, $create) && $create[$key] !== ($kept[$key] ?? null)) {
                return [$key => $create[$key]];
            }
        }

        return $create;
    }
}
