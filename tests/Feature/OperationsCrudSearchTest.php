<?php

namespace Tests\Feature;

use App\Models\MediaGateway;
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
            'pdc-servers' => ['pdc-servers', [
                'create' => ['hostname' => 'pdc-alpha', 'ip_address' => '10.1.1.10', 'location' => 'Estancia', 'role' => 'Primary', 'status' => 'Active'],
                'other' => ['hostname' => 'pdc-beta', 'ip_address' => '10.1.1.11', 'location' => 'Alcar', 'role' => 'Backup', 'status' => 'Active'],
                'update' => ['hostname' => 'pdc-alpha-updated', 'ip_address' => '10.1.1.10', 'location' => 'Estancia', 'role' => 'Primary', 'status' => 'Inactive'],
                'updated' => ['hostname' => 'pdc-alpha-updated', 'status' => 'Inactive'],
                'kept' => ['hostname' => 'pdc-beta'],
                'search' => 'pdc-alpha-updated',
                'hidden' => 'pdc-beta',
            ]],
            'sip-channels' => ['sip-channels', [
                'create' => ['channel' => 'SIP-ALPHA', 'peer' => 'peer-a', 'context' => 'from-internal', 'codec' => 'ulaw', 'status' => 'Active'],
                'other' => ['channel' => 'SIP-BETA', 'peer' => 'peer-b', 'context' => 'from-external', 'codec' => 'alaw', 'status' => 'Active'],
                'update' => ['channel' => 'SIP-ALPHA-UPDATED', 'peer' => 'peer-a', 'context' => 'from-internal', 'codec' => 'ulaw', 'status' => 'Inactive'],
                'updated' => ['channel' => 'SIP-ALPHA-UPDATED', 'status' => 'Inactive'],
                'kept' => ['channel' => 'SIP-BETA'],
                'search' => 'SIP-ALPHA-UPDATED',
                'hidden' => 'SIP-BETA',
            ]],
            'archive-recordings' => ['archive-recordings', [
                'create' => ['server' => 'arc-alpha', 'storage_path' => '/recordings/a', 'retention_days' => 30, 'status' => 'Active'],
                'other' => ['server' => 'arc-beta', 'storage_path' => '/recordings/b', 'retention_days' => 60, 'status' => 'Active'],
                'update' => ['server' => 'arc-alpha-updated', 'storage_path' => '/recordings/a', 'retention_days' => 90, 'status' => 'Inactive'],
                'updated' => ['server' => 'arc-alpha-updated', 'retention_days' => 90],
                'kept' => ['server' => 'arc-beta'],
                'search' => 'arc-alpha-updated',
                'hidden' => 'arc-beta',
            ]],
            'globe-sim' => ['globe-sim', [
                'create' => ['sim_number' => '09170000001', 'imsi' => '515020000000001', 'assigned_to' => 'Alpha', 'location' => 'Estancia', 'status' => 'Active'],
                'other' => ['sim_number' => '09170000002', 'imsi' => '515020000000002', 'assigned_to' => 'Beta', 'location' => 'Alcar', 'status' => 'Active'],
                'update' => ['sim_number' => '09170000001', 'imsi' => '515020000000001', 'assigned_to' => 'Alpha Updated', 'location' => 'Estancia', 'status' => 'Inactive'],
                'updated' => ['assigned_to' => 'Alpha Updated', 'status' => 'Inactive'],
                'kept' => ['sim_number' => '09170000002'],
                'search' => 'Alpha Updated',
                'hidden' => '09170000002',
            ]],
            'smart-sim' => ['smart-sim', [
                'create' => ['sim_number' => '09280000001', 'imsi' => '515030000000001', 'assigned_to' => 'Alpha', 'location' => 'CTN', 'status' => 'Active'],
                'other' => ['sim_number' => '09280000002', 'imsi' => '515030000000002', 'assigned_to' => 'Beta', 'location' => 'SCS', 'status' => 'Active'],
                'update' => ['sim_number' => '09280000001', 'imsi' => '515030000000001', 'assigned_to' => 'Alpha Updated', 'location' => 'CTN', 'status' => 'Inactive'],
                'updated' => ['assigned_to' => 'Alpha Updated', 'status' => 'Inactive'],
                'kept' => ['sim_number' => '09280000002'],
                'search' => 'Alpha Updated',
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

        $this->post('/pdc-servers', [
            'hostname' => 'pdc-alpha',
            'ip_address' => '10.1.1.10',
            'location' => 'Estancia',
            'role' => 'Primary',
            'status' => 'Active',
        ])->assertRedirect();

        $this->post('/pdc-servers', [
            'hostname' => 'pdc-beta',
            'ip_address' => '10.1.1.11',
            'location' => 'Alcar',
            'role' => 'Backup',
            'status' => 'Active',
        ])->assertRedirect();

        $record = PdcServer::where('hostname', 'pdc-alpha')->firstOrFail();

        $this->put('/pdc-servers/'.$record->id, [
            'hostname' => 'pdc-alpha-updated',
            'ip_address' => '10.1.1.10',
            'location' => 'Estancia',
            'role' => 'Primary',
            'status' => 'Inactive',
        ])->assertRedirect();

        $this->assertDatabaseHas('pdc_servers', [
            'id' => $record->id,
            'hostname' => 'pdc-alpha-updated',
            'status' => 'Inactive',
        ]);

        $this->delete('/pdc-servers/'.$record->id)->assertRedirect();
        $this->assertDatabaseMissing('pdc_servers', ['id' => $record->id]);
        $this->assertDatabaseHas('pdc_servers', ['hostname' => 'pdc-beta']);
    }

    public function test_gsm_gateway_edits_and_deletes_the_correct_record(): void
    {
        $this->actingAs($this->admin);

        $keep = MediaGateway::create([
            'site_name' => 'Keep Site',
            'site_code' => 'KEEP01',
            'ip_address' => '10.2.2.2',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ]);
        $target = MediaGateway::create([
            'site_name' => 'Target Site',
            'site_code' => 'TGT001',
            'ip_address' => '10.2.2.3',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ]);

        $this->get('/gsm-gateways')
            ->assertOk()
            ->assertSee('data-edit-id="'.$target->id.'"', false)
            ->assertSee('data-delete-id="'.$target->id.'"', false);

        $this->putJson('/gsm-gateways/'.$target->id, [
            'site_name' => 'Target Updated',
            'site_code' => 'TGT001',
            'ip_address' => '10.2.2.3',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ])->assertOk();

        $this->assertDatabaseHas('media_gateways', [
            'id' => $target->id,
            'site_name' => 'Target Updated',
        ]);
        $this->assertDatabaseHas('media_gateways', [
            'id' => $keep->id,
            'site_name' => 'Keep Site',
        ]);

        $this->deleteJson('/gsm-gateways/'.$target->id)->assertOk();
        $this->assertDatabaseMissing('media_gateways', ['id' => $target->id]);
        $this->assertDatabaseHas('media_gateways', ['id' => $keep->id]);
    }

    public function test_edit_and_delete_buttons_use_the_compact_outline_style(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('.action-btn.edit:hover', $css);
        $this->assertStringContainsString('.action-btn.delete:hover', $css);
        $this->assertStringContainsString('background:#fff; color:#0b70f7', $css);
        $this->assertStringContainsString('background:#fff; color:#ef4444', $css);

        PdcServer::create([
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
