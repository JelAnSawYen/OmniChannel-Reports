<?php

namespace Tests\Feature;

use App\Models\GatewaySimAssignment;
use App\Models\GlobeSim;
use App\Models\MediaGateway;
use App\Models\SmartSim;
use App\Models\User;
use App\Models\UserType;
use App\Services\XlsxService;
use App\Support\InventoryImportCatalog;
use App\Support\OperationCatalog;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SimInventoryFieldsTest extends TestCase
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

    public static function simModules(): array
    {
        return [
            'globe-sim' => ['globe-sim', GlobeSim::class, 'globe_sims', 'Globe SIM'],
            'smart-sim' => ['smart-sim', SmartSim::class, 'smart_sims', 'Smart SIM'],
        ];
    }

    #[DataProvider('simModules')]
    public function test_list_add_edit_delete_and_contract_dates(string $module, string $model, string $table, string $network): void
    {
        $this->actingAs($this->admin);

        MediaGateway::create([
            'hostname' => 'sim-gw-'.$module,
            'site_name' => 'Alcar',
            'site_code' => 'SIM-GW-'.$module,
            'ip_address' => '10.73.1.1',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
            'channel_count' => 16,
            'network' => $network,
        ]);

        $page = $this->get('/'.$module)->assertOk();
        $html = $page->getContent();
        $this->assertStringContainsString('<table class="sim-table"', $html);
        $this->assertStringNotContainsString('<th>Id</th>', $html);
        $this->assertStringNotContainsString('<th>Last Updated</th>', $html);
        $page->assertSeeInOrder([
            'IMEI',
            'Mobile Number',
            'Plan',
            'Hostname',
            'Port',
            'Account Number',
            'Contract Start',
            'Contract End',
            'Actions',
        ]);
        $tableHtml = Str::betweenFirst($html, 'class="sim-table"', '</table>');
        $this->assertStringNotContainsString('>Network</th>', $tableHtml);
        $this->assertStringNotContainsString('>Remarks</th>', $tableHtml);
        $page->assertSee('field_imei', false)
            ->assertSee('field_mobile_number', false)
            ->assertSee('field_plan', false)
            ->assertSee('field_hostname', false)
            ->assertSee('<select class="form-control" name="hostname" id="field_hostname" required>', false)
            ->assertSee('value="sim-gw-'.$module.'"', false)
            ->assertSee('>sim-gw-'.$module.'</option>', false)
            ->assertSee('field_port', false)
            ->assertSee('<input class="form-control" type="number" min="1" name="port" id="field_port" required>', false)
            ->assertDontSee('Port <span class="req">*</span>', false)
            ->assertDontSee('field_network', false)
            ->assertDontSee('field_remarks', false)
            ->assertSee('field_account_number', false)
            ->assertSee('field_contract_start', false)
            ->assertSee('field_contract_end', false)
            ->assertSee('placeholder="M/D/YYYY"', false)
            ->assertDontSee('All Statuses')
            ->assertDontSee('SIM Number')
            ->assertDontSee('>IMSI<', false)
            ->assertDontSee('Assigned To');

        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('body[data-page="'.$module.'"] #moduleModal.modal-backdrop.visible', $css);
        $this->assertMatchesRegularExpression(
            '/body\[data-page="'.$module.'"\] #moduleModal\.modal-backdrop\.visible[\s\S]*?overflow-y: auto;/',
            $css
        );
        $this->assertStringContainsString('body[data-page="'.$module.'"] #moduleModal .pdc-cal', $css);
        $this->assertMatchesRegularExpression(
            '/body\[data-page="'.$module.'"\] #moduleModal \.pdc-cal[\s\S]*?overflow: visible;/',
            $css
        );

        $payload = [
            'imei' => '356938035644001',
            'mobile_number' => '09173330001',
            'plan' => 'Unli Surf',
            'hostname' => 'sim-gw-'.$module,
            'port' => 3,
            'account_number' => 'ACC-3001',
            'contract_start' => '3/1/2026',
            'contract_end' => '9/3/2026',
        ];

        $this->post('/'.$module, $payload)->assertRedirect();
        $record = $model::query()->firstOrFail();
        $this->assertSame('356938035644001', $record->imei);
        $this->assertSame('09173330001', $record->mobile_number);
        $this->assertSame($network, $record->network);
        $this->assertSame('Unli Surf', $record->plan);
        $this->assertSame('10.73.1.1', $record->ip_address);
        $this->assertSame(3, (int) $record->port);
        $this->assertNull($record->fresh()->remarks);
        $this->assertNotNull($record->fresh()->gatewayAssignment);
        $this->assertSame('ACC-3001', $record->account_number);
        $this->assertSame('2026-03-01', $record->contract_start?->format('Y-m-d'));
        $this->assertSame('2026-09-03', $record->contract_end?->format('Y-m-d'));

        $show = $this->get('/'.$module)->assertOk();
        $showHtml = $show->getContent();
        $this->assertStringNotContainsString('<th>Id</th>', $showHtml);
        $this->assertStringNotContainsString('2026-03-01', $showHtml);
        $this->assertStringNotContainsString('2026-09-03', $showHtml);
        $show->assertSee('356938035644001')
            ->assertSee('09173330001')
            ->assertSee('Unli Surf')
            ->assertSee('sim-gw-'.$module)
            ->assertSee('ACC-3001')
            ->assertSee('3/1/2026')
            ->assertSee('9/3/2026');
        $this->assertStringContainsString('3\/1\/2026', $showHtml);
        $this->assertStringContainsString('9\/3\/2026', $showHtml);
        $this->assertStringContainsString('"port":3', $showHtml);
        $this->assertStringContainsString('"hostname":"sim-gw-'.$module.'"', $showHtml);
        $this->assertStringContainsString('data-id="'.$record->id.'"', $showHtml);
        $this->assertStringNotContainsString('<th>Last Updated</th>', $showHtml);
        $tableHtml = Str::betweenFirst($showHtml, 'class="sim-table"', '</table>');
        $this->assertStringContainsString('>Port</th>', $tableHtml);
        $this->assertStringNotContainsString('>Network</th>', $tableHtml);
        $this->assertStringNotContainsString('>Remarks</th>', $tableHtml);
        $this->assertTrue(strpos($tableHtml, '>Hostname</th>') < strpos($tableHtml, '>Port</th>'));
        $this->assertStringContainsString('sim-gw-'.$module, $tableHtml);

        $updated = $payload;
        $updated['account_number'] = 'ACC-3001-EDIT';
        $updated['contract_end'] = '10/15/2026';
        $updated['port'] = 5;
        $this->put('/'.$module.'/'.$record->id, $updated)->assertRedirect();
        $record->refresh();
        $this->assertSame('ACC-3001-EDIT', $record->account_number);
        $this->assertSame('2026-03-01', $record->contract_start?->format('Y-m-d'));
        $this->assertSame('2026-10-15', $record->contract_end?->format('Y-m-d'));
        $this->assertSame(5, (int) $record->port);
        $this->assertNotNull($record->gatewayAssignment);

        $this->delete('/'.$module.'/'.$record->id)->assertRedirect();
        $this->assertDatabaseMissing($table, ['id' => $record->id]);
    }

    #[DataProvider('simModules')]
    public function test_validation_rejects_missing_identifiers_bad_ip_and_reversed_contracts(string $module, string $model, string $table, string $network): void
    {
        $this->actingAs($this->admin);

        MediaGateway::create([
            'hostname' => 'sim-val-'.$module,
            'site_name' => 'Alcar',
            'site_code' => 'SIM-VAL-'.$module,
            'ip_address' => '10.73.2.1',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
            'channel_count' => 8,
        ]);

        $validBase = [
            'plan' => 'Plan A',
            'hostname' => 'sim-val-'.$module,
            'port' => 2,
            'account_number' => 'ACC-VAL-1',
            'contract_start' => '3/1/2026',
            'contract_end' => '9/3/2026',
        ];

        $this->from('/'.$module)->post('/'.$module, array_merge($validBase, [
            'imei' => '356938035644010',
            'mobile_number' => '09173330010',
            'hostname' => 'missing-gateway',
        ]))->assertSessionHasErrors(['hostname']);

        $this->from('/'.$module)->post('/'.$module, array_merge($validBase, [
            'imei' => '356938035644014',
            'mobile_number' => '09173330014',
            'port' => 9,
        ]))->assertSessionHasErrors(['port']);

        $this->from('/'.$module)->post('/'.$module, array_merge($validBase, [
            'imei' => '356938035644011',
            'mobile_number' => '09173330011',
            'contract_start' => '12/1/2026',
            'contract_end' => '1/1/2026',
        ]))->assertSessionHas('error', 'Contract end date must be on or after the contract start date.');

        foreach (['9/32/2026', '13/3/2026'] as $invalid) {
            $this->from('/'.$module)->post('/'.$module, array_merge($validBase, [
                'imei' => '356938035644012',
                'mobile_number' => '09173330012',
                'contract_start' => $invalid,
            ]))->assertSessionHasErrors(['contract_start']);
        }

        $this->post('/'.$module, array_merge($validBase, [
            'imei' => '356938035644013',
            'mobile_number' => '09173330013',
            'contract_start' => '2026-03-01',
            'contract_end' => '2026-09-03',
        ]))->assertRedirect();
        $this->assertSame('2026-03-01', $model::query()->where('imei', '356938035644013')->first()?->contract_start?->format('Y-m-d'));

        $this->assertSame(1, $model::count());
        $this->assertSame(1, DB::table($table)->count());
    }

    public function test_globe_and_smart_keep_the_same_field_map(): void
    {
        $this->assertSame(
            OperationCatalog::modules()['globe-sim']['columns'],
            OperationCatalog::modules()['smart-sim']['columns']
        );
        $this->assertSame(
            array_keys(OperationCatalog::simFormFields()),
            OperationCatalog::modules()['globe-sim']['fields']
        );
        $this->assertSame([
            'imei',
            'mobile_number',
            'plan',
            'hostname',
            'port',
            'account_number',
            'contract_start',
            'contract_end',
        ], OperationCatalog::modules()['globe-sim']['fields']);
        $this->assertSame([
            'IMEI',
            'Mobile Number',
            'Plan',
            'Hostname',
            'Port',
            'Account Number',
            'Contract Start',
            'Contract End',
        ], array_values(OperationCatalog::simTableColumns()));
        $this->assertSame([
            'IMEI',
            'Mobile Number',
            'Plan',
            'Hostname',
            'Port',
            'Account Number',
            'Contract Start',
            'Contract End',
        ], array_values(OperationCatalog::simTransferColumns()));
        $this->assertSame([
            'IMEI',
            'Mobile Number',
            'Plan',
            'Hostname',
            'Port',
            'Account Number',
            'Contract Start',
            'Contract End',
        ], array_values(OperationCatalog::simImportFields()));
        $this->assertSame(
            array_values(OperationCatalog::simImportFields()),
            array_values(InventoryImportCatalog::operation('globe-sim')['fields'])
        );
        $this->assertFalse(InventoryImportCatalog::operation('globe-sim')['include_id']);
        $this->assertArrayNotHasKey('skip_save', InventoryImportCatalog::operation('globe-sim'));
        $this->assertArrayNotHasKey('updated_at', InventoryImportCatalog::operation('globe-sim')['fields']);
    }

    #[DataProvider('simModules')]
    public function test_table_shows_gsm_gateway_hostname_beside_port(string $module, string $model, string $table, string $network): void
    {
        $this->actingAs($this->admin);

        $gateway = MediaGateway::create([
            'hostname' => 'sim-port-'.$module,
            'site_name' => 'Alcar',
            'site_code' => 'SIM-PORT-'.$module,
            'ip_address' => '10.73.9.9',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
            'channel_count' => 8,
        ]);
        $sim = $model::query()->create([
            'imei' => $module === 'globe-sim' ? '356938035647001' : '356938035647101',
            'mobile_number' => $module === 'globe-sim' ? '09176660001' : '09286660001',
            'network' => $network,
            'plan' => 'Unli Surf',
            'ip_address' => '10.73.9.9',
            'port' => 4,
            'account_number' => 'ACC-PORT',
            'contract_start' => '2026-03-01',
            'contract_end' => '2026-09-03',
        ]);
        GatewaySimAssignment::create([
            'media_gateway_id' => $gateway->id,
            'sim_type' => $module === 'globe-sim' ? 'globe' : 'smart',
            'sim_id' => $sim->id,
            'port' => 9,
        ]);

        $page = $this->get('/'.$module)->assertOk();
        $tableHtml = Str::betweenFirst($page->getContent(), 'class="sim-table"', '</table>');
        $this->assertStringContainsString('>Hostname</th>', $tableHtml);
        $this->assertStringContainsString('>Port</th>', $tableHtml);
        $this->assertTrue(strpos($tableHtml, '>Hostname</th>') < strpos($tableHtml, '>Port</th>'));
        $this->assertStringNotContainsString('>Network</th>', $tableHtml);
        $this->assertStringContainsString('sim-port-'.$module, $tableHtml);
        $this->assertStringNotContainsString('>IP</th>', $tableHtml);
        $this->assertStringNotContainsString('>10.73.9.9<', $tableHtml);
        $this->assertMatchesRegularExpression('/>\s*4\s*</', $tableHtml);
        $this->assertStringNotContainsString('>9</td>', $tableHtml);
        $this->assertSame('4', $sim->displayPort());
        $this->assertDoesNotMatchRegularExpression('/>\s*'.preg_quote($network, '/').'\s*</', $tableHtml);
    }

    public function test_legacy_sim_columns_are_preserved_and_existing_values_can_still_be_stored(): void
    {
        foreach (['globe_sims', 'smart_sims'] as $table) {
            foreach (['sim_number', 'imsi', 'assigned_to', 'location', 'status', 'imei', 'mobile_number', 'network', 'plan', 'ip_address', 'port', 'account_number', 'contract_start', 'contract_end', 'remarks'] as $column) {
                $this->assertTrue(Schema::hasColumn($table, $column), $table.'.'.$column);
            }
        }

        $id = DB::table('globe_sims')->insertGetId([
            'sim_number' => '09174440001',
            'imsi' => '51502004440001',
            'assigned_to' => 'Legacy Account',
            'location' => 'Alcar',
            'status' => 'Active',
            'imei' => '51502004440001',
            'mobile_number' => '09174440001',
            'network' => 'Globe',
            'account_number' => 'Legacy Account',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('globe_sims', [
            'id' => $id,
            'sim_number' => '09174440001',
            'imsi' => '51502004440001',
            'assigned_to' => 'Legacy Account',
            'imei' => '51502004440001',
            'mobile_number' => '09174440001',
            'account_number' => 'Legacy Account',
        ]);
    }

    #[DataProvider('simModules')]
    public function test_import_template_uses_sim_fields_without_id(string $module, string $model, string $table, string $network): void
    {
        $this->actingAs($this->admin);

        $response = $this->get('/'.$module.'/import/template')->assertOk();
        [$headers] = app(XlsxService::class)->read($response->getFile()->getPathname());
        $this->assertSame([
            'IMEI',
            'Mobile Number',
            'Plan',
            'Hostname',
            'Port',
            'Account Number',
            'Contract Start',
            'Contract End',
        ], $headers);
        $this->assertNotContains('Id', $headers);
        $this->assertNotContains('Last Updated', $headers);
    }

    public function test_imei_uniqueness_is_enforced_in_the_database(): void
    {
        $this->assertTrue(Schema::hasIndex('globe_sims', 'globe_sims_imei_unique'));
        $this->assertTrue(Schema::hasIndex('smart_sims', 'smart_sims_imei_unique'));

        GlobeSim::query()->create([
            'imei' => '356938035649001',
            'mobile_number' => '09175550001',
            'network' => 'Globe',
        ]);

        try {
            GlobeSim::query()->create([
                'imei' => '356938035649001',
                'mobile_number' => '09175550002',
                'network' => 'Globe',
            ]);
            $this->fail('Duplicate Globe IMEI was inserted.');
        } catch (UniqueConstraintViolationException) {
            $this->assertSame(1, GlobeSim::query()->where('imei', '356938035649001')->count());
        }

        SmartSim::query()->create([
            'imei' => '356938035649101',
            'mobile_number' => '09175550101',
            'network' => 'Smart',
        ]);

        try {
            SmartSim::query()->create([
                'imei' => '356938035649101',
                'mobile_number' => '09175550102',
                'network' => 'Smart',
            ]);
            $this->fail('Duplicate Smart IMEI was inserted.');
        } catch (UniqueConstraintViolationException) {
            $this->assertSame(1, SmartSim::query()->where('imei', '356938035649101')->count());
        }
    }

    #[DataProvider('simModules')]
    public function test_sim_port_does_not_change_when_gsm_gateway_is_edited_or_deleted(string $module, string $model, string $table, string $network): void
    {
        $this->actingAs($this->admin);

        $gateway = MediaGateway::create([
            'hostname' => 'sim-port-host',
            'site_name' => 'Alcar',
            'site_code' => 'SIM-IND-'.$module,
            'ip_address' => '10.73.8.8',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
            'channel_count' => 16,
            'password' => 'Secret#Gsm99!',
        ]);
        $keep = MediaGateway::create([
            'hostname' => 'sim-port-keep',
            'site_name' => 'CTN',
            'site_code' => 'SIM-KEEP-'.$module,
            'ip_address' => '10.73.8.9',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
            'channel_count' => 8,
            'password' => 'Secret#Gsm99!',
        ]);

        $this->post('/'.$module, [
            'imei' => $module === 'globe-sim' ? '356938035648001' : '356938035648101',
            'mobile_number' => $module === 'globe-sim' ? '09178880001' : '09288880001',
            'plan' => 'Unli Surf',
            'hostname' => 'sim-port-host',
            'port' => 12,
            'account_number' => 'ACC-IND',
            'contract_start' => '3/1/2026',
            'contract_end' => '9/3/2026',
        ])->assertRedirect();

        $sim = $model::query()->firstOrFail();
        $this->assertSame(12, (int) $sim->port);
        $this->assertNotNull($sim->gatewayAssignment);

        $this->putJson('/gsm-gateways/'.$gateway->id, [
            'hostname' => 'sim-port-host-edit',
            'site_name' => 'Estancia',
            'site_code' => 'SIM-IND-'.$module,
            'ip_address' => '10.73.8.80',
            'channel_count' => 16,
            'device_function' => 'Inbound',
            'network' => $network,
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ])->assertOk();

        $sim->refresh();
        $this->assertSame(12, (int) $sim->port);
        $this->assertSame('10.73.8.80', $sim->ip_address);

        $this->deleteJson('/gsm-gateways/'.$gateway->id)->assertOk();
        $sim->refresh();
        $this->assertSame(12, (int) $sim->port);
        $this->assertDatabaseHas('media_gateways', ['id' => $keep->id]);
    }

    #[DataProvider('simModules')]
    public function test_sim_saves_with_hostname_port_and_one_other_field(string $module, string $model): void
    {
        $this->actingAs($this->admin);
        MediaGateway::create([
            'hostname' => 'sparse-gw-'.$module,
            'site_name' => 'Alcar',
            'site_code' => 'SPARSE-'.$module,
            'ip_address' => '10.73.9.1',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
            'channel_count' => 4,
            'network' => $module === 'smart-sim' ? 'Smart SIM' : 'Globe SIM',
        ]);

        $this->post('/'.$module, [
            'hostname' => 'sparse-gw-'.$module,
            'port' => 2,
            'plan' => '-',
            'imei' => '-',
            'mobile_number' => '',
            'account_number' => '',
            'contract_start' => '3/1/2026',
            'contract_end' => '-',
        ])->assertRedirect();

        $record = $model::query()->firstOrFail();
        $this->assertNull($record->imei);
        $this->assertNull($record->plan);
        $this->assertNull($record->mobile_number);
        $this->assertNull($record->account_number);
        $this->assertNull($record->contract_end);
        $this->assertSame('2026-03-01', $record->contract_start?->format('Y-m-d'));
        $this->assertSame(2, (int) $record->port);
        $this->assertNotNull($record->gatewayAssignment);

        $this->post('/'.$module, [
            'hostname' => 'sparse-gw-'.$module,
            'port' => 3,
            'imei' => '-',
            'mobile_number' => '-',
            'plan' => '-',
            'account_number' => '-',
            'contract_start' => '-',
            'contract_end' => '',
        ])->assertSessionHasErrors('imei');
        $this->assertSame(1, $model::query()->count());
    }
}
