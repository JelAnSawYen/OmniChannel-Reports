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
            'globe-sim' => ['globe-sim', GlobeSim::class, 'globe_sims', 'Globe'],
            'smart-sim' => ['smart-sim', SmartSim::class, 'smart_sims', 'Smart'],
        ];
    }

    #[DataProvider('simModules')]
    public function test_list_add_edit_delete_and_contract_dates(string $module, string $model, string $table, string $network): void
    {
        $this->actingAs($this->admin);

        MediaGateway::create([
            'site_name' => 'Alcar',
            'site_code' => 'SIM-GW-'.$module,
            'ip_address' => '10.73.1.1',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
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
            'IP',
            'Port',
            'Account Number',
            'Contract Start',
            'Contract End',
            'Actions',
        ]);
        $this->assertStringNotContainsString('<th>Network</th>', $html);
        $page->assertSee('field_imei', false)
            ->assertSee('field_mobile_number', false)
            ->assertSee('field_plan', false)
            ->assertSee('field_ip_address', false)
            ->assertSee('<select class="form-control" name="ip_address" id="field_ip_address" required>', false)
            ->assertSee('<option value="10.73.1.1">10.73.1.1</option>', false)
            ->assertSee('field_account_number', false)
            ->assertSee('field_contract_start', false)
            ->assertSee('field_contract_end', false)
            ->assertSee('placeholder="M/D/YYYY"', false)
            ->assertDontSee('All Statuses')
            ->assertDontSee('SIM Number')
            ->assertDontSee('>IMSI<', false)
            ->assertDontSee('Assigned To');

        $payload = [
            'imei' => '356938035644001',
            'mobile_number' => '09173330001',
            'network' => $network,
            'plan' => 'Unli Surf',
            'ip_address' => '10.73.1.1',
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
            ->assertSee($network)
            ->assertSee('Unli Surf')
            ->assertSee('10.73.1.1')
            ->assertSee('ACC-3001')
            ->assertSee('3/1/2026')
            ->assertSee('9/3/2026');
        $this->assertStringContainsString('3\/1\/2026', $showHtml);
        $this->assertStringContainsString('9\/3\/2026', $showHtml);
        $this->assertStringContainsString('data-id="'.$record->id.'"', $showHtml);
        $this->assertStringNotContainsString('<th>Last Updated</th>', $showHtml);
        $this->assertStringNotContainsString('<th>Network</th>', $showHtml);
        $tableHtml = Str::betweenFirst($showHtml, 'class="sim-table"', '</table>');
        $this->assertStringContainsString('>Port</th>', $tableHtml);
        $this->assertStringNotContainsString('>Network</th>', $tableHtml);
        $this->assertTrue(strpos($tableHtml, '>IP</th>') < strpos($tableHtml, '>Port</th>'));

        $updated = $payload;
        $updated['account_number'] = 'ACC-3001-EDIT';
        $updated['contract_end'] = '10/15/2026';
        $this->put('/'.$module.'/'.$record->id, $updated)->assertRedirect();
        $record->refresh();
        $this->assertSame('ACC-3001-EDIT', $record->account_number);
        $this->assertSame('2026-03-01', $record->contract_start?->format('Y-m-d'));
        $this->assertSame('2026-10-15', $record->contract_end?->format('Y-m-d'));

        $this->delete('/'.$module.'/'.$record->id)->assertRedirect();
        $this->assertDatabaseMissing($table, ['id' => $record->id]);
    }

    #[DataProvider('simModules')]
    public function test_validation_rejects_missing_identifiers_bad_ip_and_reversed_contracts(string $module, string $model, string $table, string $network): void
    {
        $this->actingAs($this->admin);

        MediaGateway::create([
            'site_name' => 'Alcar',
            'site_code' => 'SIM-VAL-'.$module,
            'ip_address' => '10.73.2.1',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ]);

        $this->from('/'.$module)->post('/'.$module, [
            'imei' => '',
            'mobile_number' => '09173330009',
            'plan' => 'Plan A',
            'ip_address' => '10.73.2.1',
            'account_number' => 'ACC-VAL-1',
            'contract_start' => '3/1/2026',
            'contract_end' => '9/3/2026',
        ])->assertSessionHasErrors(['imei']);

        $this->from('/'.$module)->post('/'.$module, [
            'imei' => '356938035644009',
            'mobile_number' => '',
            'plan' => 'Plan A',
            'ip_address' => '10.73.2.1',
            'account_number' => 'ACC-VAL-1',
            'contract_start' => '3/1/2026',
            'contract_end' => '9/3/2026',
        ])->assertSessionHasErrors(['mobile_number']);

        $this->from('/'.$module)->post('/'.$module, [
            'imei' => '356938035644010',
            'mobile_number' => '09173330010',
            'plan' => 'Plan A',
            'ip_address' => 'not-an-ip',
            'account_number' => 'ACC-VAL-1',
            'contract_start' => '3/1/2026',
            'contract_end' => '9/3/2026',
        ])->assertSessionHasErrors(['ip_address']);

        $this->from('/'.$module)->post('/'.$module, [
            'imei' => '356938035644011',
            'mobile_number' => '09173330011',
            'plan' => 'Plan A',
            'ip_address' => '10.73.2.1',
            'account_number' => 'ACC-VAL-1',
            'contract_start' => '12/1/2026',
            'contract_end' => '1/1/2026',
        ])->assertSessionHas('error', 'Contract end date must be on or after the contract start date.');

        foreach (['9/32/2026', '13/3/2026'] as $invalid) {
            $this->from('/'.$module)->post('/'.$module, [
                'imei' => '356938035644012',
                'mobile_number' => '09173330012',
                'plan' => 'Plan A',
                'ip_address' => '10.73.2.1',
                'account_number' => 'ACC-VAL-1',
                'contract_start' => $invalid,
                'contract_end' => '9/3/2026',
            ])->assertSessionHasErrors(['contract_start']);
        }

        $this->post('/'.$module, [
            'imei' => '356938035644013',
            'mobile_number' => '09173330013',
            'plan' => 'Plan A',
            'ip_address' => '10.73.2.1',
            'account_number' => 'ACC-VAL-1',
            'contract_start' => '2026-03-01',
            'contract_end' => '2026-09-03',
        ])->assertRedirect();
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
            'ip_address',
            'account_number',
            'contract_start',
            'contract_end',
        ], OperationCatalog::modules()['globe-sim']['fields']);
        $this->assertSame([
            'IMEI',
            'Mobile Number',
            'Plan',
            'IP',
            'Port',
            'Account Number',
            'Contract Start',
            'Contract End',
        ], array_values(OperationCatalog::simTableColumns()));
        $this->assertSame([
            'IMEI',
            'Mobile Number',
            'Plan',
            'IP',
            'Account Number',
            'Contract Start',
            'Contract End',
        ], array_values(OperationCatalog::simTransferColumns()));
        $this->assertSame(
            array_values(OperationCatalog::simTransferColumns()),
            array_values(InventoryImportCatalog::operation('globe-sim')['fields'])
        );
        $this->assertFalse(InventoryImportCatalog::operation('globe-sim')['include_id']);
        $this->assertArrayNotHasKey('skip_save', InventoryImportCatalog::operation('globe-sim'));
        $this->assertArrayNotHasKey('updated_at', InventoryImportCatalog::operation('globe-sim')['fields']);
    }

    #[DataProvider('simModules')]
    public function test_table_shows_gsm_gateway_port_beside_ip(string $module, string $model, string $table, string $network): void
    {
        $this->actingAs($this->admin);

        $gateway = MediaGateway::create([
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
            'account_number' => 'ACC-PORT',
            'contract_start' => '2026-03-01',
            'contract_end' => '2026-09-03',
        ]);
        GatewaySimAssignment::create([
            'media_gateway_id' => $gateway->id,
            'sim_type' => $module === 'globe-sim' ? 'globe' : 'smart',
            'sim_id' => $sim->id,
            'port' => 4,
        ]);

        $page = $this->get('/'.$module)->assertOk();
        $tableHtml = Str::betweenFirst($page->getContent(), 'class="sim-table"', '</table>');
        $this->assertStringContainsString('>IP</th>', $tableHtml);
        $this->assertStringContainsString('>Port</th>', $tableHtml);
        $this->assertTrue(strpos($tableHtml, '>IP</th>') < strpos($tableHtml, '>Port</th>'));
        $this->assertStringNotContainsString('>Network</th>', $tableHtml);
        $this->assertStringContainsString('10.73.9.9', $tableHtml);
        $this->assertMatchesRegularExpression('/>\s*4\s*</', $tableHtml);
        $this->assertDoesNotMatchRegularExpression('/>\s*'.preg_quote($network, '/').'\s*</', $tableHtml);
    }

    public function test_legacy_sim_columns_are_preserved_and_existing_values_can_still_be_stored(): void
    {
        foreach (['globe_sims', 'smart_sims'] as $table) {
            foreach (['sim_number', 'imsi', 'assigned_to', 'location', 'status', 'imei', 'mobile_number', 'network', 'plan', 'ip_address', 'account_number', 'contract_start', 'contract_end'] as $column) {
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
            'IP',
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
}
