<?php

namespace Tests\Feature;

use App\Models\ChannelAllocationCampaign;
use App\Models\MediaGateway;
use App\Models\PdcServer;
use App\Models\SipChannel;
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
        \App\Models\ChannelAllocationCampaign::create(['name' => 'BPI Collection']);
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
        $this->assertSame('Estancia', \App\Models\PdcGroup::first()->location);

        $export = $this->get('/pdc-servers/export')->assertOk()->assertDownload('pdc-servers.xlsx');
        [$headers] = app(XlsxService::class)->read($export->getFile()->getPathname());
        $this->assertSame('Campaign', $headers[0]);
        $this->assertContains('Source IP', $headers);
    }

    public function test_gsm_import_allows_duplicate_site_name_username_and_database(): void
    {
        $this->actingAs($this->admin);
        $path = $this->spreadsheet([
            ['Hostname IP', 'Serial Number', 'Plan', 'Port', 'Network', 'Function', 'Site', 'User', 'Password'],
            ['10.24.28.10', 'ALC-100', 'Plan A', '1', 'Globe', 'Inbound', 'Alcar', 'root', 'secret1'],
            ['10.24.28.11', 'ALC-101', '', '', '', '', 'Alcar', 'root', 'secret1'],
            ['10.24.28.12', 'ALC-102', '', '', '', '', '', '', ''],
        ]);

        $preview = $this->postJson('/gsm-gateways/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();

        $this->assertTrue($preview['valid'], $preview['rows'][2]['error'] ?? '');
        $this->assertSame('Alcar', $preview['rows'][2]['site_name']);
        $this->assertSame('root', $preview['rows'][2]['username']);
        $this->assertSame('secret1', $preview['rows'][2]['password']);
        $this->assertSame('Plan A', $preview['rows'][2]['plan']);
        $this->assertSame('Inbound', $preview['rows'][2]['device_function']);

        $this->postJson('/gsm-gateways/import/confirm', ['token' => $preview['token']])
            ->assertOk()
            ->assertJson(['records' => 3]);

        $this->assertSame(3, MediaGateway::where('site_name', 'Alcar')->count());
        $this->assertSame(3, MediaGateway::where('username', 'root')->count());
        $this->assertSame('secret1', MediaGateway::where('site_code', 'ALC-102')->value('password'));

        $template = $this->get('/gsm-gateways/import/template')->assertOk();
        [$headers] = app(XlsxService::class)->read($template->getFile()->getPathname());
        $this->assertSame(['Hostname IP', 'Serial Number', 'Plan', 'Port', 'Network', 'Function', 'Site', 'User', 'Password'], $headers);
        $this->assertNotContains('Id', $headers);
    }

    public function test_ipv4_is_unique_across_pdc_and_gsm_modules(): void
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
            ['Hostname IP', 'Serial Number', 'Plan', 'Port', 'Network', 'Function', 'Site', 'User', 'Password'],
            ['10.50.50.50', 'ALC-CLASH', '', '', '', '', 'Alcar', 'root', 'secret'],
        ]);
        $preview = $this->postJson('/gsm-gateways/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();

        $this->assertFalse($preview['valid']);
        $this->assertStringContainsString('already exists', $preview['rows'][0]['error']);
        $this->assertSame(0, MediaGateway::count());
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
        $zip = new \ZipArchive();
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
        ChannelAllocationCampaign::create(['name' => 'Mynt']);
        $this->get('/sip-channels')
            ->assertOk()
            ->assertSee('Data Transfer')
            ->assertSee('Import Data')
            ->assertSee('Export Data')
            ->assertDontSee('Download Sample Template')
            ->assertSee('Download Excel Template');

        $path = $this->spreadsheet([
            ['Campaign', 'ETPI SIP NAME', 'Pilot Number', 'Channel Count', 'Channel Range', 'Network', 'Date Activation'],
            ['Mynt', 'ETPI-A', '100', '2', '100 - 101', 'ETPI', '7/9/2026'],
            ['', 'ETPI-B', '', '', '', '', ''],
        ]);
        $preview = $this->postJson('/sip-channels/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();
        $this->assertTrue($preview['valid'], $preview['rows'][1]['error'] ?? '');
        $this->assertSame('Mynt', $preview['rows'][1]['campaign']);
        $this->postJson('/sip-channels/import/confirm', ['token' => $preview['token']])->assertOk();
        $this->assertSame(2, SipChannel::count());
        $this->assertSame('Mynt', SipChannel::where('etpi_sip_name', 'ETPI-B')->first()?->campaign?->name);
    }

    public function test_globe_and_smart_sim_import_maps_the_new_fields_including_contract_dates(): void
    {
        $this->actingAs($this->admin);

        foreach (['globe-sim' => \App\Models\GlobeSim::class, 'smart-sim' => \App\Models\SmartSim::class] as $module => $model) {
            $network = $module === 'globe-sim' ? 'Globe' : 'Smart';
            $path = $this->spreadsheet([
                ['IMEI', 'Mobile Number', 'Network', 'Plan', 'IP', 'Account Number', 'Contract Start', 'Contract End'],
                ['356938035643401', '09171110001', $network, 'Unli Surf', '10.71.1.1', 'ACC-1001', '1/15/2026', '12/15/2026'],
                ['356938035643402', '09171110002', $network, 'Plan B', '10.71.1.2', 'ACC-1002', '1/20/2026', '12/20/2026'],
            ]);

            $preview = $this->postJson('/'.$module.'/import/preview', [
                'file' => $this->upload($path),
            ])->assertOk()->json();
            $this->assertTrue($preview['valid'], $preview['rows'][0]['error'] ?? $preview['rows'][1]['error'] ?? '');
            $this->assertSame([
                'IMEI',
                'Mobile Number',
                'Network',
                'Plan',
                'IP',
                'Account Number',
                'Contract Start',
                'Contract End',
            ], $preview['headers']);
            $this->assertNotContains('Id', $preview['headers']);
            $this->assertNotContains('Last Updated', $preview['headers']);
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
            $this->assertSame('ACC-1001', $imported->account_number);
            $this->assertSame('2026-01-15', $imported->contract_start?->format('Y-m-d'));
            $this->assertSame('2026-12-15', $imported->contract_end?->format('Y-m-d'));

            $export = $this->get('/'.$module.'/export')->assertOk()->assertDownload($module.'.xlsx');
            [$exportHeaders, $exportRows] = app(XlsxService::class)->read($export->getFile()->getPathname());
            $this->assertSame([
                'IMEI',
                'Mobile Number',
                'Network',
                'Plan',
                'IP',
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
        }
    }

    public function test_sim_import_rejects_invalid_ip_duplicate_mobile_number_and_reversed_contracts(): void
    {
        $this->actingAs($this->admin);
        \App\Models\GlobeSim::create([
            'imei' => '356938035643501',
            'mobile_number' => '09172220001',
            'network' => 'Globe',
        ]);

        $path = $this->spreadsheet([
            ['IMEI', 'Mobile Number', 'Network', 'Plan', 'IP', 'Account Number', 'Contract Start', 'Contract End'],
            ['', '09172220004', 'Globe', 'Plan A', '10.72.1.4', 'ACC-4', '1/1/2026', '12/1/2026'],
            ['356938035643502', '09172220001', 'Globe', 'Plan A', '10.72.1.1', 'ACC-1', '1/1/2026', '12/1/2026'],
            ['356938035643503', '09172220002', 'Globe', 'Plan A', 'not-an-ip', 'ACC-2', '1/1/2026', '12/1/2026'],
            ['356938035643504', '09172220003', 'Globe', 'Plan A', '10.72.1.3', 'ACC-3', '12/1/2026', '1/1/2026'],
            ['356938035643505', '09172220005', 'Globe', 'Plan A', '10.72.1.5', 'ACC-5', '9/32/2026', '9/3/2026'],
            ['356938035643506', '09172220006', 'Globe', 'Plan A', '10.72.1.6', 'ACC-6', '13/3/2026', '9/3/2026'],
        ]);

        $preview = $this->postJson('/globe-sim/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();

        $this->assertFalse($preview['valid']);
        $this->assertStringContainsString('IMEI is required', $preview['rows'][0]['error']);
        $this->assertStringContainsString('already exists', $preview['rows'][1]['error']);
        $this->assertStringContainsString('valid IPv4', $preview['rows'][2]['error']);
        $this->assertStringContainsString('Contract end date', $preview['rows'][3]['error']);
        $this->assertStringContainsString('valid date', $preview['rows'][4]['error']);
        $this->assertStringContainsString('valid date', $preview['rows'][5]['error']);
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
            $zip = new \ZipArchive();
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
