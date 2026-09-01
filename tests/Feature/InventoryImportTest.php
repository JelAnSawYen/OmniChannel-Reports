<?php

namespace Tests\Feature;

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
        PdcServer::create([
            'hostname' => 'existing-pdc',
            'ip_address' => '10.9.9.9',
            'location' => 'Alcar',
            'role' => 'Backup',
            'status' => 'Active',
        ]);

        $path = $this->spreadsheet([
            ['Id', 'Hostname', 'IP Address', 'Location', 'Role', 'Status'],
            ['1', 'pdc-one', '10.1.1.1', 'Estancia', 'Primary', 'Active'],
            ['', 'pdc-two', '10.1.1.2', '', '', ''],
            ['', '', '', '', '', ''],
            ['', 'pdc-three', '10.9.9.9', 'Estancia', 'Primary', 'Active'],
            ['', 'pdc-four', 'not-an-ip', 'Estancia', 'Primary', 'Active'],
            ['', 'pdc-five', '10.1.1.1', 'Estancia', 'Primary', 'Active'],
        ]);

        $preview = $this->postJson('/pdc-servers/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();

        $this->assertFalse($preview['valid']);
        $this->assertTrue($preview['rows'][0]['valid']);
        $this->assertTrue($preview['rows'][1]['valid']);
        $this->assertSame('Estancia', $preview['rows'][1]['location']);
        $this->assertSame('Primary', $preview['rows'][1]['role']);
        $this->assertSame('Active', $preview['rows'][1]['status']);
        $this->assertSame(5, $preview['summary']['total']);
        $this->assertStringContainsString('already exists', $preview['rows'][2]['error']);
        $this->assertStringContainsString('valid IPv4', $preview['rows'][3]['error']);
        $this->assertStringContainsString('already exists', $preview['rows'][4]['error']);

        $validPath = $this->spreadsheet([
            ['Id', 'Hostname', 'IP Address', 'Location', 'Role', 'Status'],
            ['1', 'pdc-one', '10.1.1.1', 'Estancia', 'Primary', 'Active'],
            ['2', 'pdc-two', '10.1.1.2', '', '', ''],
        ]);
        $valid = $this->postJson('/pdc-servers/import/preview', [
            'file' => $this->upload($validPath),
        ])->assertOk()->json();
        $this->assertTrue($valid['valid']);
        $this->postJson('/pdc-servers/import/confirm', ['token' => $valid['token']])
            ->assertOk()
            ->assertJson(['ok' => true, 'records' => 2]);

        $this->assertSame(3, PdcServer::count());
        $this->assertSame('Estancia', PdcServer::where('hostname', 'pdc-two')->value('location'));
        $this->assertSame('Primary', PdcServer::where('hostname', 'pdc-two')->value('role'));

        $export = $this->get('/pdc-servers/export')->assertOk()->assertDownload('pdc-servers.xlsx');
        [$headers, $rows] = app(XlsxService::class)->read($export->getFile()->getPathname());
        $this->assertSame('Id', $headers[0]);
        $this->assertSame(['1', '2', '3'], array_map(fn ($row) => (string) $row[0], $rows));
    }

    public function test_gsm_import_allows_duplicate_site_name_username_and_database(): void
    {
        $this->actingAs($this->admin);
        $path = $this->spreadsheet([
            ['Id', 'Site Name', 'Site Code', 'IP Address', 'Username', 'Database'],
            ['1', 'Alcar', 'ALC-100', '10.24.28.10', 'root', 'asteriskcdrdb'],
            ['2', 'Alcar', 'ALC-101', '10.24.28.11', 'root', 'asteriskcdrdb'],
            ['3', '', 'ALC-102', '10.24.28.12', '', ''],
        ]);

        $preview = $this->postJson('/gsm-gateways/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();

        $this->assertTrue($preview['valid'], $preview['rows'][2]['error'] ?? '');
        $this->assertSame('Alcar', $preview['rows'][2]['site_name']);
        $this->assertSame('root', $preview['rows'][2]['username']);
        $this->assertSame('asteriskcdrdb', $preview['rows'][2]['database']);

        $this->postJson('/gsm-gateways/import/confirm', ['token' => $preview['token']])
            ->assertOk()
            ->assertJson(['records' => 3]);

        $this->assertSame(3, MediaGateway::where('site_name', 'Alcar')->count());
        $this->assertSame(3, MediaGateway::where('username', 'root')->count());
    }

    public function test_ipv4_is_unique_across_pdc_and_gsm_modules(): void
    {
        $this->actingAs($this->admin);
        MediaGateway::create([
            'site_name' => 'Alcar',
            'site_code' => 'ALC-IP',
            'ip_address' => '10.50.50.50',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ]);

        $path = $this->spreadsheet([
            ['Id', 'Hostname', 'IP Address', 'Location', 'Role', 'Status'],
            ['1', 'pdc-clash', '10.50.50.50', 'Estancia', 'Primary', 'Active'],
        ]);
        $preview = $this->postJson('/pdc-servers/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();

        $this->assertFalse($preview['valid']);
        $this->assertStringContainsString('already exists', $preview['rows'][0]['error']);
        $this->assertSame(0, PdcServer::count());
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
        $this->get('/sip-channels')
            ->assertOk()
            ->assertSee('Data Transfer')
            ->assertSee('Import Data')
            ->assertSee('Export Data')
            ->assertDontSee('Download Sample Template')
            ->assertSee('Download Excel Template');

        $path = $this->spreadsheet([
            ['Id', 'Channel', 'Peer', 'Context', 'Codec', 'Status'],
            ['1', 'SIP-A', 'peer-a', 'from-internal', 'ulaw', 'Active'],
            ['2', 'SIP-B', '', '', '', ''],
        ]);
        $preview = $this->postJson('/sip-channels/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();
        $this->assertTrue($preview['valid']);
        $this->assertSame('peer-a', $preview['rows'][1]['peer']);
        $this->postJson('/sip-channels/import/confirm', ['token' => $preview['token']])->assertOk();
        $this->assertSame(2, SipChannel::count());
        $this->assertSame('from-internal', SipChannel::where('channel', 'SIP-B')->value('context'));
    }

    public function test_sample_templates_exist_for_manage_modules(): void
    {
        $this->actingAs($this->admin);
        $urls = [
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
