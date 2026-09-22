<?php

namespace Tests\Feature;

use App\Models\ChannelAllocationCampaign;
use App\Models\GatewaySimAssignment;
use App\Models\GlobeSim;
use App\Models\MediaGateway;
use App\Models\PdcGroup;
use App\Models\PdcServer;
use App\Models\SipChannel;
use App\Models\SmartSim;
use App\Models\User;
use App\Models\UserType;
use App\Services\XlsxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class InventoryImportTest extends TestCase
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

    public function test_pdc_import_inherits_blanks_allows_duplicate_non_ip_and_rejects_duplicate_ipv4(): void
    {
        $this->actingAs($this->admin);
        ChannelAllocationCampaign::create(['name' => 'BPI Collection']);
        PdcServer::create([
            'hostname' => 'existing-pdc',
            'ip_address' => '10.9.9.9',
            'location' => 'Alcar',
            'role' => 'Backup',
            'status' => 'Active',
        ]);

        $path = $this->spreadsheet([
            ['Campaign', 'Location', 'Date Endorse', 'DNS', 'Hostname', 'Source IP', 'OS', 'RAM', 'CPU', 'Storage', 'Admin Username', 'Password', 'SQL DB Password'],
            ['BPI Collection', 'Estancia', '9/3/2026', 'dns.example.com', 'pdc-one', '10.1.1.1', '', '', '', '', '', '', ''],
            ['', '', '', '', 'pdc-two', '10.1.1.2', '', '', '', '', '', '', ''],
            ['', '', '', '', 'pdc-three', '10.9.9.9', '', '', '', '', '', '', ''],
            ['', '', '', '', 'pdc-four', 'not-an-ip', '', '', '', '', '', '', ''],
            ['', '', '', '', 'pdc-five', '10.1.1.1', '', '', '', '', '', '', ''],
        ]);

        $preview = $this->postJson('/pdc-servers/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();

        $this->assertFalse($preview['valid']);
        $this->assertTrue($preview['rows'][0]['valid']);
        $this->assertTrue($preview['rows'][1]['valid']);
        $this->assertSame('Estancia', $preview['rows'][1]['location']);
        $this->assertSame('BPI Collection', $preview['rows'][1]['campaign']);
        $this->assertStringContainsString('already exists', $preview['rows'][2]['error']);
        $this->assertStringContainsString('valid IPv4', $preview['rows'][3]['error']);
        $this->assertStringContainsString('already exists', $preview['rows'][4]['error']);

        $validPath = $this->spreadsheet([
            ['Campaign', 'Location', 'Date Endorse', 'DNS', 'Hostname', 'Source IP', 'OS', 'RAM', 'CPU', 'Storage', 'Admin Username', 'Password', 'SQL DB Password'],
            ['BPI Collection', 'Estancia', '9/3/2026', 'dns.example.com', 'pdc-one', '10.1.1.1', '', '', '', '', '', '', ''],
            ['', '', '', '', 'pdc-two', '10.1.1.2', '', '', '', '', '', '', ''],
        ]);
        $valid = $this->postJson('/pdc-servers/import/preview', [
            'file' => $this->upload($validPath),
        ])->assertOk()->json();
        $this->assertTrue($valid['valid'], $valid['rows'][1]['error'] ?? '');
        $this->postJson('/pdc-servers/import/confirm', ['token' => $valid['token']])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(3, PdcServer::count());
        $this->assertSame('Estancia', PdcGroup::first()->location);

        $export = $this->get('/pdc-servers/export')->assertOk()->assertDownload('pdc-servers.xlsx');
        [$headers] = app(XlsxService::class)->read($export->getFile()->getPathname());
        $this->assertSame('Campaign', $headers[0]);
        $this->assertContains('Source IP', $headers);
    }

    public function test_gsm_import_allows_duplicate_site_name_username_and_database(): void
    {
        $this->actingAs($this->admin);
        $headers = ['Hostname', 'IP', 'Serial Number', 'Channel Count', 'Function', 'Site', 'User', 'Password', 'Port', 'IMEI', 'Mobile Number', 'Network', 'Plan', 'Remarks'];
        $path = $this->spreadsheet([
            $headers,
            ['gsm-one', '10.24.28.10', 'ALC-100', '20', 'Inbound', 'Alcar', 'root', 'secret1', '', '', '', '', ''],
            ['gsm-two', '10.24.28.11', 'ALC-101', '', '', '', '', '', '', '', '', '', ''],
            ['gsm-three', '10.24.28.12', 'ALC-102', '', '', '', '', '', '', '', '', '', ''],
        ]);

        $preview = $this->postJson('/gsm-gateways/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();

        $this->assertTrue($preview['valid'], $preview['rows'][2]['error'] ?? '');
        $this->assertSame('Alcar', $preview['rows'][2]['site_name']);
        $this->assertSame('root', $preview['rows'][2]['username']);
        $this->assertSame('secret1', $preview['rows'][2]['password']);
        $this->assertSame('Inbound', $preview['rows'][2]['device_function']);
        $this->assertSame('20', $preview['rows'][2]['channel_count']);

        $this->postJson('/gsm-gateways/import/confirm', ['token' => $preview['token']])
            ->assertOk()
            ->assertJson(['records' => 3]);

        $this->assertSame(3, MediaGateway::where('site_name', 'Alcar')->count());
        $this->assertSame(3, MediaGateway::where('username', 'root')->count());
        $this->assertSame('secret1', MediaGateway::where('site_code', 'ALC-102')->value('password'));
        $this->assertSame('gsm-three', MediaGateway::where('site_code', 'ALC-102')->value('hostname'));
        $this->assertSame(20, (int) MediaGateway::where('site_code', 'ALC-102')->value('channel_count'));

        $template = $this->get('/gsm-gateways/import/template')->assertOk();
        [$headers] = app(XlsxService::class)->read($template->getFile()->getPathname());
        $this->assertSame(['Hostname', 'IP', 'Serial Number', 'Channel Count', 'Function', 'Site', 'User', 'Password', 'Port', 'IMEI', 'Mobile Number', 'Network', 'Plan', 'Remarks'], $headers);
        $this->assertNotContains('Id', $headers);
        $this->assertNotContains('Hostname IP', $headers);
    }

    public function test_gsm_import_accepts_typed_function_text(): void
    {
        $this->actingAs($this->admin);
        $headers = ['Hostname', 'IP', 'Serial Number', 'Channel Count', 'Function', 'Site', 'User', 'Password', 'Port', 'IMEI', 'Mobile Number', 'Network', 'Plan', 'Remarks'];
        $path = $this->spreadsheet([
            $headers,
            ['gsm-sms', '10.24.80.10', 'SMS-100', '4', 'SMS', 'Alcar', 'root', 'secret', '', '', '', '', ''],
            ['gsm-voice', '10.24.80.11', 'VOICE-100', '4', 'Voice', 'Alcar', 'root', 'secret', '', '', '', '', ''],
        ]);

        $preview = $this->postJson('/gsm-gateways/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();

        $this->assertTrue($preview['valid'], $preview['rows'][0]['error'] ?? '');
        $this->assertSame('SMS', $preview['rows'][0]['device_function']);
        $this->assertSame('Voice', $preview['rows'][1]['device_function']);

        $this->postJson('/gsm-gateways/import/confirm', ['token' => $preview['token']])
            ->assertOk()
            ->assertJson(['records' => 2]);

        $this->assertDatabaseHas('media_gateways', ['site_code' => 'SMS-100', 'device_function' => 'SMS']);
        $this->assertDatabaseHas('media_gateways', ['site_code' => 'VOICE-100', 'device_function' => 'Voice']);
    }

    public function test_import_appends_after_existing_records_and_ignores_excel_ids(): void
    {
        $this->actingAs($this->admin);
        $first = MediaGateway::create([
            'site_name' => 'Estancia',
            'site_code' => 'EST-OLD-1',
            'ip_address' => '10.9.1.1',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
            'port' => '8',
        ]);
        $second = MediaGateway::create([
            'site_name' => 'Alcar',
            'site_code' => 'ALC-OLD-2',
            'ip_address' => '10.9.1.2',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
            'port' => '8',
        ]);

        $path = $this->spreadsheet([
            ['Id', 'Hostname', 'IP', 'Serial Number', 'Channel Count', 'Function', 'Site', 'User', 'Password', 'Port', 'IMEI', 'Mobile Number', 'Network', 'Plan', 'Remarks'],
            [1, 'gsm-new-a', '10.24.40.10', 'NEW-A', '4', 'Inbound', 'CTN', 'root', 'x', '', '', '', '', ''],
            [2, 'gsm-new-b', '10.24.40.11', 'NEW-B', '4', 'Inbound', 'SC5', 'root', 'x', '', '', '', '', ''],
            [3, 'gsm-new-c', '10.24.40.12', 'NEW-C', '4', 'Inbound', 'WFH', 'root', 'x', '', '', '', '', ''],
        ]);

        $preview = $this->postJson('/gsm-gateways/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();
        $this->assertTrue($preview['valid'], $preview['rows'][0]['error'] ?? '');

        $this->postJson('/gsm-gateways/import/confirm', ['token' => $preview['token']])
            ->assertOk()
            ->assertJson(['records' => 3]);

        $this->assertSame([$first->id, $second->id], MediaGateway::query()->whereIn('id', [$first->id, $second->id])->orderBy('id')->pluck('id')->all());
        $this->assertSame(
            ['EST-OLD-1', 'ALC-OLD-2', 'NEW-A', 'NEW-B', 'NEW-C'],
            MediaGateway::query()->orderBy('id')->pluck('site_code')->all()
        );
        $this->assertSame(
            [$first->id, $second->id, $second->id + 1, $second->id + 2, $second->id + 3],
            MediaGateway::query()->orderBy('id')->pluck('id')->all()
        );
    }

    public function test_gsm_ip_is_unique_only_among_gsm_gateways(): void
    {
        $this->actingAs($this->admin);
        PdcServer::create([
            'hostname' => 'pdc-existing',
            'ip_address' => '10.50.50.50',
            'location' => 'Estancia',
            'role' => 'Primary',
            'status' => 'Active',
        ]);

        $path = $this->spreadsheet([
            ['Hostname', 'IP', 'Serial Number', 'Channel Count', 'Function', 'Site', 'User', 'Password', 'Port', 'IMEI', 'Mobile Number', 'Network', 'Plan', 'Remarks'],
            ['clash-host', '10.50.50.50', 'ALC-CLASH', '8', 'Inbound', 'Alcar', 'root', 'secret', '', '', '', '', ''],
        ]);
        $preview = $this->postJson('/gsm-gateways/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();

        $this->assertTrue($preview['valid'], $preview['rows'][0]['error'] ?? '');
        $this->postJson('/gsm-gateways/import/confirm', ['token' => $preview['token']])->assertOk();
        $this->assertDatabaseHas('media_gateways', [
            'hostname' => 'clash-host',
            'ip_address' => '10.50.50.50',
        ]);

        $dup = $this->postJson('/gsm-gateways/import/preview', [
            'file' => $this->upload($this->spreadsheet([
                ['Hostname', 'IP', 'Serial Number', 'Channel Count', 'Function', 'Site', 'User', 'Password', 'Port', 'IMEI', 'Mobile Number', 'Network', 'Plan', 'Remarks'],
                ['other-host', '10.50.50.50', 'ALC-DUP', '8', 'Inbound', 'Alcar', 'root', 'secret', '', '', '', '', ''],
            ])),
        ])->assertOk()->json();
        $this->assertFalse($dup['valid']);
        $this->assertStringContainsString('already exists', $dup['rows'][0]['error']);
    }

    public function test_gsm_import_assigns_existing_sims_without_duplicating_inventory(): void
    {
        $this->actingAs($this->admin);
        $globe = GlobeSim::create([
            'imei' => '123456789012345',
            'mobile_number' => '09171234567',
            'plan' => 'Corporate',
            'network' => 'Globe',
            'status' => 'Active',
        ]);
        $smart = SmartSim::create([
            'imei' => '987654321098765',
            'mobile_number' => '09181234567',
            'plan' => 'Unli Data',
            'network' => 'Smart',
            'status' => 'Active',
        ]);

        $path = $this->spreadsheet([
            ['Hostname', 'IP', 'Serial Number', 'Channel Count', 'Function', 'Site', 'User', 'Password', 'Port', 'IMEI', 'Mobile Number', 'Network', 'Plan', 'Remarks'],
            ['gsm_globe_phq108', '10.5.20.108', 'SN-108', '20', 'Outbound', 'SC5', 'root', 'secret', '1', '123456789012345', '', '', ''],
            ['', '', '', '', '', '', '', '', '2', '987654321098765', '', '', ''],
        ]);

        $preview = $this->postJson('/gsm-gateways/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();
        $this->assertTrue($preview['valid'], $preview['rows'][1]['error'] ?? '');

        $this->postJson('/gsm-gateways/import/confirm', ['token' => $preview['token']])
            ->assertOk()
            ->assertJson(['records' => 1]);

        $gateway = MediaGateway::where('site_code', 'SN-108')->firstOrFail();
        $this->assertSame('gsm_globe_phq108', $gateway->hostname);
        $this->assertSame(20, (int) $gateway->channel_count);
        $this->assertSame(1, MediaGateway::count());
        $this->assertSame(1, GlobeSim::count());
        $this->assertSame(1, SmartSim::count());
        $this->assertDatabaseHas('gateway_sim_assignments', [
            'media_gateway_id' => $gateway->id,
            'sim_type' => 'globe',
            'sim_id' => $globe->id,
            'port' => 1,
        ]);
        $this->assertDatabaseHas('gateway_sim_assignments', [
            'media_gateway_id' => $gateway->id,
            'sim_type' => 'smart',
            'sim_id' => $smart->id,
            'port' => 2,
        ]);
        $this->assertSame(1, (int) $globe->fresh()->port);
        $this->assertSame(2, (int) $smart->fresh()->port);
        $this->assertSame('', (string) $gateway->network);
    }

    public function test_gsm_import_saves_globe_or_smart_network_on_gateway(): void
    {
        $this->actingAs($this->admin);
        $globe = GlobeSim::create([
            'imei' => '123456789012111',
            'mobile_number' => '09170001111',
            'plan' => 'Corporate',
            'network' => 'Globe',
            'status' => 'Active',
        ]);

        $path = $this->spreadsheet([
            ['Hostname', 'IP', 'Serial Number', 'Channel Count', 'Function', 'Site', 'User', 'Password', 'Port', 'IMEI', 'Mobile Number', 'Network', 'Plan', 'Remarks'],
            ['gsm-east-1', '10.24.50.10', 'EAST-100', '8', 'Inbound', 'Alcar', 'root', 'secret', '', '', '', 'Globe SIM', ''],
            ['', '', '', '', '', '', '', '', '1', '123456789012111', '09170001111', 'Globe SIM', 'Corporate'],
        ]);

        $preview = $this->postJson('/gsm-gateways/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();
        $this->assertTrue($preview['valid'], $preview['rows'][1]['error'] ?? '');

        $this->postJson('/gsm-gateways/import/confirm', ['token' => $preview['token']])
            ->assertOk()
            ->assertJson(['records' => 1]);

        $gateway = MediaGateway::where('site_code', 'EAST-100')->firstOrFail();
        $this->assertSame('Globe SIM', $gateway->network);
        $this->assertDatabaseHas('gateway_sim_assignments', [
            'media_gateway_id' => $gateway->id,
            'sim_type' => 'globe',
            'sim_id' => $globe->id,
            'port' => 1,
        ]);
        $this->assertSame('Globe SIM', $globe->fresh()->network);

        $export = $this->get('/gsm-gateways/export')->assertOk();
        [$headers, $rows] = app(XlsxService::class)->read($export->getFile()->getPathname());
        $networkIndex = array_search('Network', $headers, true);
        $this->assertNotFalse($networkIndex);
        $this->assertSame('Globe SIM', $rows[0][$networkIndex]);
    }

    public function test_gsm_import_groups_same_ip_allows_empty_imei_and_assigns_sequential_ports(): void
    {
        $this->actingAs($this->admin);
        $globe = GlobeSim::create([
            'imei' => '111111111111111',
            'mobile_number' => '09171111111',
            'plan' => 'Corporate',
            'network' => 'Globe',
            'status' => 'Active',
        ]);
        $smart = SmartSim::create([
            'imei' => '222222222222222',
            'mobile_number' => '09182222222',
            'plan' => 'Unli Data',
            'network' => 'Smart',
            'status' => 'Active',
        ]);

        $path = $this->spreadsheet([
            ['Hostname', 'IP', 'Serial Number', 'Channel Count', 'Function', 'Site', 'User', 'Password', 'Port', 'IMEI', 'Mobile Number', 'Network', 'Plan', 'Remarks'],
            ['gsm-shared', '10.24.60.10', 'SHARED-100', '4', 'Inbound', 'Alcar', 'root', 'secret', '1', '', '', '', '', ''],
            ['', '10.24.60.10', '', '', '', '', '', '', '2', '', '', '', '', ''],
            ['', '10.24.60.10', '', '', '', '', '', '', '3', '111111111111111', '', '', '', ''],
            ['', '10.24.60.10', '', '', '', '', '', '', '', '222222222222222', '', '', '', ''],
        ]);

        $preview = $this->postJson('/gsm-gateways/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();

        $this->assertTrue($preview['valid'], json_encode($preview['rows']));
        $this->assertSame(4, $preview['summary']['valid']);
        $this->assertSame(0, $preview['summary']['errors']);

        $this->postJson('/gsm-gateways/import/confirm', ['token' => $preview['token']])
            ->assertOk()
            ->assertJson(['records' => 1]);

        $gateway = MediaGateway::where('site_code', 'SHARED-100')->firstOrFail();
        $this->assertSame(1, MediaGateway::count());
        $this->assertSame('10.24.60.10', $gateway->ip_address);
        $this->assertSame(4, (int) $gateway->channel_count);
        $this->assertDatabaseHas('gateway_sim_assignments', [
            'media_gateway_id' => $gateway->id,
            'sim_type' => 'globe',
            'sim_id' => $globe->id,
            'port' => 1,
        ]);
        $this->assertDatabaseHas('gateway_sim_assignments', [
            'media_gateway_id' => $gateway->id,
            'sim_type' => 'smart',
            'sim_id' => $smart->id,
            'port' => 2,
        ]);
        $this->assertSame(2, GatewaySimAssignment::count());
    }

    public function test_gsm_import_rejects_ip_already_in_database_but_allows_two_distinct_ips(): void
    {
        $this->actingAs($this->admin);
        MediaGateway::create([
            'hostname' => 'existing-gsm',
            'site_name' => 'Alcar',
            'site_code' => 'EXIST-IP',
            'ip_address' => '10.24.70.1',
            'channel_count' => 8,
            'username' => 'root',
        ]);

        $dupPreview = $this->postJson('/gsm-gateways/import/preview', [
            'file' => $this->upload($this->spreadsheet([
                ['Hostname', 'IP', 'Serial Number', 'Channel Count', 'Function', 'Site', 'User', 'Password', 'Port', 'IMEI', 'Mobile Number', 'Network', 'Plan', 'Remarks'],
                ['new-gsm', '10.24.70.1', 'NEW-DUP', '8', 'Inbound', 'Alcar', 'root', 'secret', '', '', '', '', '', ''],
            ])),
        ])->assertOk()->json();
        $this->assertFalse($dupPreview['valid']);
        $this->assertStringContainsString('IP already exists', $dupPreview['rows'][0]['error']);

        $twoIps = $this->postJson('/gsm-gateways/import/preview', [
            'file' => $this->upload($this->spreadsheet([
                ['Hostname', 'IP', 'Serial Number', 'Channel Count', 'Function', 'Site', 'User', 'Password', 'Port', 'IMEI', 'Mobile Number', 'Network', 'Plan', 'Remarks'],
                ['gsm-a', '10.24.71.10', 'TWO-A', '4', 'Inbound', 'Alcar', 'root', 'secret', '', '', '', '', '', ''],
                ['', '10.24.71.10', '', '', '', '', '', '', '1', '', '', '', '', ''],
                ['gsm-b', '10.24.71.11', 'TWO-B', '4', 'Inbound', 'Alcar', 'root', 'secret', '', '', '', '', '', ''],
            ])),
        ])->assertOk()->json();
        $this->assertTrue($twoIps['valid'], json_encode($twoIps['rows']));
        $this->postJson('/gsm-gateways/import/confirm', ['token' => $twoIps['token']])
            ->assertOk()
            ->assertJson(['records' => 2]);
        $this->assertSame(3, MediaGateway::count());
        $this->assertDatabaseHas('media_gateways', ['site_code' => 'TWO-A', 'ip_address' => '10.24.71.10']);
        $this->assertDatabaseHas('media_gateways', ['site_code' => 'TWO-B', 'ip_address' => '10.24.71.11']);
    }

    public function test_gsm_import_rejects_sim_rows_beyond_channel_count(): void
    {
        $this->actingAs($this->admin);
        GlobeSim::create([
            'imei' => '333333333333333',
            'mobile_number' => '09173333333',
            'plan' => 'Corporate',
            'network' => 'Globe',
            'status' => 'Active',
        ]);
        GlobeSim::create([
            'imei' => '444444444444444',
            'mobile_number' => '09174444444',
            'plan' => 'Corporate',
            'network' => 'Globe',
            'status' => 'Active',
        ]);
        GlobeSim::create([
            'imei' => '555555555555555',
            'mobile_number' => '09175555555',
            'plan' => 'Corporate',
            'network' => 'Globe',
            'status' => 'Active',
        ]);

        $preview = $this->postJson('/gsm-gateways/import/preview', [
            'file' => $this->upload($this->spreadsheet([
                ['Hostname', 'IP', 'Serial Number', 'Channel Count', 'Function', 'Site', 'User', 'Password', 'Port', 'IMEI', 'Mobile Number', 'Network', 'Plan', 'Remarks'],
                ['gsm-limit', '10.24.72.10', 'LIMIT-2', '2', 'Inbound', 'Alcar', 'root', 'secret', '', '333333333333333', '', '', '', ''],
                ['', '10.24.72.10', '', '', '', '', '', '', '', '444444444444444', '', '', '', ''],
                ['', '10.24.72.10', '', '', '', '', '', '', '', '555555555555555', '', '', '', ''],
            ])),
        ])->assertOk()->json();

        $this->assertFalse($preview['valid']);
        $this->assertTrue($preview['rows'][0]['valid']);
        $this->assertTrue($preview['rows'][1]['valid']);
        $this->assertFalse($preview['rows'][2]['valid']);
        $this->assertStringContainsString('SIM assignments cannot exceed the Channel Count', $preview['rows'][2]['error']);
    }

    public function test_program_location_import_and_sample_template(): void
    {
        $this->actingAs($this->admin);
        $template = $this->get('/program-location/skyrise/import/template')
            ->assertOk()
            ->assertDownload('skyrise-gsm-gateways-template.xlsx');
        [$headers, $rows] = app(XlsxService::class)->read($template->getFile()->getPathname());
        $this->assertSame('Id', $headers[0]);
        $this->assertContains('Site Name', $headers);
        $this->assertContains('IP Address', $headers);
        $sheet = '';
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($template->getFile()->getPathname()) === true);
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        $this->assertSame(11, preg_match_all('/<row r="/', $sheet));

        $path = $this->spreadsheet([
            ['Id', 'Site Name', 'Site Code', 'IP Address', 'Username', 'Database'],
            ['1', 'Skyrise', 'SKY-1', '10.60.60.1', 'root', 'asteriskcdrdb'],
            ['2', '', 'SKY-2', '10.60.60.2', 'root', 'asteriskcdrdb'],
        ]);
        $preview = $this->postJson('/program-location/skyrise/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();
        $this->assertTrue($preview['valid']);
        $this->postJson('/program-location/skyrise/import/confirm', ['token' => $preview['token']])->assertOk();
        $this->assertSame(2, MediaGateway::where('site_name', 'Skyrise')->count());
    }

    public function test_sip_channel_import_one_row_one_record_and_template_menu(): void
    {
        $this->actingAs($this->admin);
        $this->get('/sip-channels')
            ->assertOk()
            ->assertSee('Data Transfer')
            ->assertSee('Import Data')
            ->assertSee('Export Data')
            ->assertDontSee('Download Sample Template')
            ->assertSee('Download Excel Template');

        $path = $this->spreadsheet([
            ['SIP Name', 'Pilot Number', 'Channel Count', 'Channel Range', 'Network', 'Date Activation'],
            ['ETPI-A', '100', '2', '100 - 101', 'ETPI', '7/9/2026'],
            ['ETPI-B', '', '', '', '', ''],
        ]);
        $preview = $this->postJson('/sip-channels/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();
        $this->assertTrue($preview['valid'], $preview['rows'][1]['error'] ?? '');
        $this->postJson('/sip-channels/import/confirm', ['token' => $preview['token']])->assertOk();
        $this->assertSame(2, SipChannel::count());
        $this->assertNull(SipChannel::where('etpi_sip_name', 'ETPI-B')->first()?->campaign_id);
    }

    public function test_globe_and_smart_sim_import_maps_the_new_fields_including_contract_dates(): void
    {
        $this->actingAs($this->admin);

        foreach (['10.71.1.1' => ['SIM-IMP-1', 'host-imp-1'], '10.71.1.2' => ['SIM-IMP-2', 'host-imp-2']] as $ip => $meta) {
            MediaGateway::create([
                'hostname' => $meta[1],
                'site_name' => 'Alcar',
                'site_code' => $meta[0],
                'ip_address' => $ip,
                'username' => 'root',
                'database' => 'asteriskcdrdb',
                'channel_count' => 16,
            ]);
        }

        foreach (['globe-sim' => GlobeSim::class, 'smart-sim' => SmartSim::class] as $module => $model) {
            $network = $module === 'globe-sim' ? 'Globe SIM' : 'Smart SIM';
            MediaGateway::query()->whereIn('hostname', ['host-imp-1', 'host-imp-2'])->update(['network' => $network]);
            $path = $this->spreadsheet([
                ['IMEI', 'Mobile Number', 'Plan', 'Hostname', 'Port', 'Account Number', 'Contract Start', 'Contract End'],
                ['356938035643401', '09171110001', 'Unli Surf', 'host-imp-1', '1', 'ACC-1001', '1/15/2026', '12/15/2026'],
                ['356938035643402', '09171110002', 'Plan B', 'host-imp-2', '2', 'ACC-1002', '1/20/2026', '12/20/2026'],
            ]);

            $preview = $this->postJson('/'.$module.'/import/preview', [
                'file' => $this->upload($path),
            ])->assertOk()->json();
            $this->assertTrue($preview['valid'], $preview['rows'][0]['error'] ?? $preview['rows'][1]['error'] ?? '');
            $this->assertSame([
                'IMEI',
                'Mobile Number',
                'Plan',
                'Hostname',
                'Port',
                'Account Number',
                'Contract Start',
                'Contract End',
            ], $preview['headers']);
            $this->assertNotContains('Id', $preview['headers']);
            $this->assertNotContains('Last Updated', $preview['headers']);
            $this->assertNotContains('Network', $preview['headers']);
            $this->assertNotContains('Remarks', $preview['headers']);
            $this->assertSame('1/15/2026', $preview['rows'][0]['contract_start']);
            $this->assertSame('12/15/2026', $preview['rows'][0]['contract_end']);

            $this->postJson('/'.$module.'/import/confirm', ['token' => $preview['token']])->assertOk();
            $this->assertSame(2, $model::count());
            $imported = $model::query()->where('imei', '356938035643401')->first();
            $this->assertNotNull($imported);
            $this->assertSame('09171110001', $imported->mobile_number);
            $this->assertSame($network, $imported->network);
            $this->assertSame('Unli Surf', $imported->plan);
            $this->assertSame('10.71.1.1', $imported->ip_address);
            $this->assertSame(1, (int) $imported->port);
            $this->assertNull($imported->remarks);
            $this->assertSame('ACC-1001', $imported->account_number);
            $this->assertSame('2026-01-15', $imported->contract_start?->format('Y-m-d'));
            $this->assertSame('2026-12-15', $imported->contract_end?->format('Y-m-d'));
            $this->assertNull($model::query()->where('imei', '356938035643402')->value('remarks'));

            $export = $this->get('/'.$module.'/export')->assertOk()->assertDownload($module.'.xlsx');
            [$exportHeaders, $exportRows] = app(XlsxService::class)->read($export->getFile()->getPathname());
            $this->assertSame([
                'IMEI',
                'Mobile Number',
                'Plan',
                'Hostname',
                'Port',
                'Account Number',
                'Contract Start',
                'Contract End',
            ], $exportHeaders);
            $this->assertNotContains('Id', $exportHeaders);
            $this->assertNotContains('Last Updated', $exportHeaders);
            $exported = collect($exportRows)->first(fn ($row) => ($row[0] ?? '') === '356938035643401');
            $this->assertSame('1/15/2026', $exported[6] ?? null);
            $this->assertSame('12/15/2026', $exported[7] ?? null);
            $this->assertCount(8, $exported);
            $model::query()->get()->each->delete();
            $this->assertSame(0, $model::count());
            $this->assertSame(0, GatewaySimAssignment::query()->count());
        }
    }

    public function test_sim_import_rejects_unknown_hostname_invalid_port_and_dates(): void
    {
        $this->actingAs($this->admin);
        MediaGateway::create([
            'hostname' => 'host-imp-val',
            'site_name' => 'Alcar',
            'site_code' => 'SIM-VAL-1',
            'ip_address' => '10.72.1.1',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
            'channel_count' => 4,
        ]);
        GlobeSim::create([
            'imei' => '356938035643501',
            'mobile_number' => '09172220001',
            'network' => 'Globe SIM',
        ]);

        $headers = ['IMEI', 'Mobile Number', 'Plan', 'Hostname', 'Port', 'Account Number', 'Contract Start', 'Contract End'];
        $path = $this->spreadsheet([
            $headers,
            ['', '', '', 'host-imp-val', '1', '', '', ''],
            ['356938035643502', '09172220001', 'Plan A', 'host-imp-val', '1', 'ACC-1', '1/1/2026', '12/1/2026'],
            ['356938035643503', '09172220002', 'Plan A', 'missing-host', '1', 'ACC-2', '1/1/2026', '12/1/2026'],
            ['356938035643504', '09172220003', 'Plan A', 'host-imp-val', '9', 'ACC-3', '1/1/2026', '12/1/2026'],
            ['356938035643506', '09172220006', 'Plan A', 'host-imp-val', '2', 'ACC-6', '12/1/2026', '1/1/2026'],
            ['356938035643507', '09172220007', 'Plan A', 'host-imp-val', '2', 'ACC-7', '9/32/2026', '9/3/2026'],
        ]);

        $preview = $this->postJson('/globe-sim/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();

        $this->assertFalse($preview['valid']);
        $this->assertStringContainsString('Enter Hostname, Port, and at least one other field.', $preview['rows'][0]['error']);
        $this->assertStringContainsString('already exists', $preview['rows'][1]['error']);
        $this->assertStringContainsString('Hostname must match an existing GSM Gateway', $preview['rows'][2]['error']);
        $this->assertStringContainsString('Port must not exceed Channel Count', $preview['rows'][3]['error']);
        $this->assertStringContainsString('Contract end date', $preview['rows'][4]['error']);
        $this->assertStringContainsString('valid date', $preview['rows'][5]['error']);
    }

    public function test_globe_sim_import_treats_blank_and_dash_as_empty_and_does_not_carry(): void
    {
        $this->actingAs($this->admin);
        MediaGateway::create([
            'hostname' => 'host-sparse',
            'site_name' => 'Alcar',
            'site_code' => 'SIM-SPARSE',
            'ip_address' => '10.72.9.1',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
            'channel_count' => 4,
            'network' => 'Globe SIM',
        ]);

        $headers = ['IMEI', 'Mobile Number', 'Plan', 'Hostname', 'Port', 'Account Number', 'Contract Start', 'Contract End'];
        $path = $this->spreadsheet([
            $headers,
            ['356938035649001', '09170000901', 'Plan A', 'host-sparse', '1', 'ACC-1', '1/1/2026', '12/1/2026'],
            ['-', '', '', 'host-sparse', '2', '-', '4/2/2026', '-'],
        ]);

        $preview = $this->postJson('/globe-sim/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();

        $this->assertTrue($preview['valid'], $preview['rows'][1]['error'] ?? '');
        $this->assertSame('', $preview['rows'][1]['imei']);
        $this->assertSame('', $preview['rows'][1]['plan']);
        $this->assertSame('4/2/2026', $preview['rows'][1]['contract_start']);

        $this->postJson('/globe-sim/import/confirm', ['token' => $preview['token']])->assertOk();
        $second = GlobeSim::query()->where('port', 2)->firstOrFail();
        $this->assertNull($second->imei);
        $this->assertNull($second->plan);
        $this->assertNull($second->account_number);
        $this->assertNull($second->contract_end);
        $this->assertSame('2026-04-02', $second->contract_start?->format('Y-m-d'));
        $this->assertSame('356938035649001', GlobeSim::query()->where('port', 1)->value('imei'));
    }

    public function test_sample_templates_exist_for_manage_modules(): void
    {
        $this->actingAs($this->admin);
        $urls = [
            '/campaigns/import/template',
            '/pdc-servers/import/template',
            '/sip-channels/import/template',
            '/archive-recordings/import/template',
            '/gsm-gateways/import/template',
            '/globe-sim/import/template',
            '/smart-sim/import/template',
            '/program-inbound-numbers/import/template',
            '/signal-boosters/import/template',
            '/defective-gsm/import/template',
            '/program-location/alcar/import/template',
            '/program-location/ctn/import/template',
            '/program-location/scs/import/template',
            '/program-location/pdc/import/template',
            '/program-location/estancia/import/template',
            '/program-location/skyrise/import/template',
            '/channel-allocation/import/template',
        ];
        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_sample_templates_include_column_widths_borders_and_centered_headers(): void
    {
        $this->actingAs($this->admin);
        $urls = [
            '/campaigns/import/template',
            '/pdc-servers/import/template',
            '/sip-channels/import/template',
            '/archive-recordings/import/template',
            '/gsm-gateways/import/template',
            '/globe-sim/import/template',
            '/smart-sim/import/template',
            '/program-inbound-numbers/import/template',
            '/signal-boosters/import/template',
            '/defective-gsm/import/template',
            '/program-location/alcar/import/template',
            '/program-location/ctn/import/template',
            '/program-location/scs/import/template',
            '/program-location/pdc/import/template',
            '/program-location/estancia/import/template',
            '/program-location/skyrise/import/template',
            '/channel-allocation/import/template',
        ];

        foreach ($urls as $url) {
            $response = $this->get($url)->assertOk();
            $path = $response->getFile()->getPathname();
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path) === true, $url);
            $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
            $styles = (string) $zip->getFromName('xl/styles.xml');
            $zip->close();

            $this->assertStringContainsString('<cols>', $sheet, $url);
            $this->assertStringContainsString('customWidth="1"', $sheet, $url);
            $this->assertStringContainsString('s="1"', $sheet, $url);
            $this->assertStringContainsString('horizontal="center"', $styles, $url);
            $this->assertStringContainsString('vertical="center"', $styles, $url);
            $this->assertStringContainsString('<b/>', $styles, $url);
            $this->assertStringContainsString('style="thin"', $styles, $url);
            $this->assertGreaterThanOrEqual(2, preg_match_all('/<row r="/', $sheet));
        }
    }

    /**
     * @param  list<list<string|int>>  $rows
     */
    private function spreadsheet(array $rows): string
    {
        $headers = array_shift($rows);

        return app(XlsxService::class)->export($headers, $rows, 'inventory-import.xlsx');
    }

    private function upload(string $path): UploadedFile
    {
        return new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
