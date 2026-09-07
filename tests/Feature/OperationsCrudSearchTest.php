<?php

namespace Tests\Feature;

use App\Models\ChannelAllocationCampaign;
use App\Models\MediaGateway;
use App\Models\PdcGroup;
use App\Models\PdcServer;
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
                'create' => ['number' => '0325000001', 'program' => 'Program A', 'location' => 'Skyrise', 'assigned_channel' => 'CH-A', 'status' => 'Active'],
                'other' => ['number' => '0325000002', 'program' => 'Program B', 'location' => 'Alcar', 'assigned_channel' => 'CH-B', 'status' => 'Active'],
                'update' => ['number' => '0325000001', 'program' => 'Program A Updated', 'location' => 'Skyrise', 'assigned_channel' => 'CH-A', 'status' => 'Inactive'],
                'updated' => ['program' => 'Program A Updated', 'status' => 'Inactive'],
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
                'create' => ['asset_code' => 'DG-ALPHA', 'location' => 'SCS', 'issue' => 'No signal', 'reported_on' => '2026-08-01', 'status' => 'Open'],
                'other' => ['asset_code' => 'DG-BETA', 'location' => 'Alcar', 'issue' => 'Broken antenna', 'reported_on' => '2026-08-02', 'status' => 'Open'],
                'update' => ['asset_code' => 'DG-ALPHA', 'location' => 'SCS', 'issue' => 'Repaired radio', 'reported_on' => '2026-08-01', 'status' => 'Closed'],
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
            ->assertDontSee('<input class="form-control" id="site_name" name="site_name" required>', false);
        foreach (array_values(OperationCatalog::locations()) as $name) {
            $page->assertSee('>'.$name.'</option>', false);
        }

        $this->putJson('/gsm-gateways/'.$target->id, [
            'site_name' => 'CTN',
            'site_code' => 'TGT001',
            'ip_address' => '10.2.2.3',
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
            'site_name' => 'Not A Location',
            'site_code' => 'BAD001',
            'ip_address' => '10.2.2.9',
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
            ->assertSee('class="action-btn edit"', false)
            ->assertSee('class="action-btn delete"', false);

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

        $this->actingAs($this->admin)->get('/user-types')
            ->assertOk()
            ->assertSee('class="action-btn edit"', false);
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
