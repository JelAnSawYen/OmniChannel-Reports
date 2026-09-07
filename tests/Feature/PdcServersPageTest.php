<?php

namespace Tests\Feature;

use App\Models\ChannelAllocationCampaign;
use App\Models\MediaGateway;
use App\Models\PdcGroup;
use App\Models\PdcServer;
use App\Models\User;
use App\Models\UserType;
use App\Services\XlsxService;
use App\Support\OperationCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PdcServersPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $system;

    private User $standard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\UserTypeSeeder']);
        $this->admin = User::factory()->create([
            'user_type_id' => UserType::where('name', 'Administrator')->value('id'),
            'status' => 'Active',
        ]);
        $this->system = User::factory()->create([
            'user_type_id' => UserType::where('name', 'System Administrator')->value('id'),
            'status' => 'Active',
        ]);
        $this->standard = User::factory()->create([
            'user_type_id' => UserType::where('name', 'Standard User')->value('id'),
            'status' => 'Active',
        ]);
    }

    public function test_campaign_and_location_dropdowns_use_existing_sources(): void
    {
        $this->actingAs($this->admin);
        ChannelAllocationCampaign::create(['name' => 'BPI Collection']);
        ChannelAllocationCampaign::create(['name' => 'Atome']);

        $page = $this->get('/pdc-servers')->assertOk();
        $html = $page->getContent();

        $page->assertSee('id="pdc_campaign_id"', false)
            ->assertSee('id="pdc_location"', false)
            ->assertSee('BPI Collection')
            ->assertSee('Atome')
            ->assertDontSee('Hardcoded Campaign');

        $this->assertStringContainsString('>BPI Collection</option>', $html);
        $this->assertStringContainsString('>Atome</option>', $html);
        $this->assertStringNotContainsString('data-used', $html);
        $this->assertStringNotContainsString('filterCampaignOptions', $html);

        foreach (OperationCatalog::locations() as $name) {
            $this->assertStringContainsString('>'.$name.'</option>', $html);
        }
        $this->assertStringNotContainsString('PDC – Taytay', $html);
        $this->assertSame(2, ChannelAllocationCampaign::count());
        $this->assertSame(6, count(OperationCatalog::locations()));
    }

    public function test_main_table_is_expandable_campaign_structure(): void
    {
        $this->actingAs($this->admin);
        [$group] = $this->seedGroupAndServer();

        $page = $this->get('/pdc-servers')->assertOk();
        $page->assertSee('Campaign')
            ->assertSee('Location')
            ->assertSee('Date Endorse')
            ->assertSee('DNS')
            ->assertSee('1 server')
            ->assertSee('data-ca-toggle', false)
            ->assertSee('id="pdc-panel-'.$group->id.'"', false)
            ->assertSee('id="pdcGroupAddButton"', false)
            ->assertDontSee('Total Servers')
            ->assertDontSee('Total RAM')
            ->assertDontSee('Total CPU')
            ->assertDontSee('Total Storage')
            ->assertDontSee('>Add</button>', false)
            ->assertDontSee('Add Campaign');

        $this->assertStringNotContainsString('id="pdcGroupAddButton">Add', $page->getContent());
    }

    public function test_add_campaign_form_has_only_campaign_fields(): void
    {
        $this->actingAs($this->admin);
        ChannelAllocationCampaign::create(['name' => 'BPI Collection']);

        $html = $this->get('/pdc-servers')->assertOk()->getContent();
        $groupForm = \Illuminate\Support\Str::between($html, 'id="pdcGroupForm"', 'id="pdcServerForm"');
        $this->assertStringContainsString('id="pdc_campaign_id"', $groupForm);
        $this->assertStringContainsString('id="pdc_location"', $groupForm);
        $this->assertStringContainsString('id="pdc_date_endorse"', $groupForm);
        $this->assertStringContainsString('id="pdc_dns"', $groupForm);
        $this->assertStringContainsString('id="pdcGroupSubmit">Save', $html);
        $this->assertStringNotContainsString('name="hostname"', $groupForm);
        $this->assertStringNotContainsString('pdcGroupSubmit">Save Campaign', $html);
    }

    public function test_campaign_dropdown_lists_every_master_campaign(): void
    {
        $this->actingAs($this->admin);
        $first = ChannelAllocationCampaign::create(['name' => 'BPI Collection']);
        ChannelAllocationCampaign::create(['name' => 'Atome']);
        ChannelAllocationCampaign::create(['name' => 'Mynt']);

        $this->post('/pdc-servers', [
            'campaign_id' => $first->id,
            'location' => 'Estancia',
        ])->assertRedirect();

        $html = $this->get('/pdc-servers')->assertOk()->getContent();
        $select = \Illuminate\Support\Str::between($html, 'id="pdc_campaign_id"', '</select>');
        $this->assertStringContainsString('>BPI Collection</option>', $select);
        $this->assertStringContainsString('>Atome</option>', $select);
        $this->assertStringContainsString('>Mynt</option>', $select);
        $this->assertStringNotContainsString('data-used', $select);
        $this->assertStringNotContainsString('filterCampaignOptions', $html);
    }

    public function test_date_endorse_accepts_valid_and_rejects_invalid_dates(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create(['name' => 'BPI Collection']);

        $this->post('/pdc-servers', [
            'campaign_id' => $campaign->id,
            'location' => 'Estancia',
            'date_endorse' => '9/3/2026',
            'dns' => 'pdc-bpicollections.teamssg.com',
        ])->assertRedirect();

        $group = PdcGroup::firstOrFail();
        $this->assertSame('2026-09-03', $group->date_endorse->format('Y-m-d'));
        $this->get('/pdc-servers')->assertSee('9/3/2026');

        $other = ChannelAllocationCampaign::create(['name' => 'Atome']);
        foreach (['9/32/2026', '13/3/2026', 'abc'] as $invalid) {
            $this->from('/pdc-servers')->post('/pdc-servers', [
                'campaign_id' => $other->id,
                'location' => 'Alcar',
                'date_endorse' => $invalid,
                'dns' => 'ok.example.com',
            ])->assertRedirect('/pdc-servers')->assertSessionHasErrors('date_endorse');
        }
        $this->assertSame(1, PdcGroup::count());
    }

    public function test_date_endorse_rejects_dates_before_2000_and_accepts_minimum(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create(['name' => 'BPI Collection']);
        $html = $this->get('/pdc-servers')->assertOk()->getContent();
        $this->assertStringContainsString('min="2000-01-01"', $html);
        $this->assertStringContainsString('year < 2000', $html);

        foreach (['12/31/1999', '1/1/1999', '1999-12-31'] as $invalid) {
            $this->from('/pdc-servers')->post('/pdc-servers', [
                'campaign_id' => $campaign->id,
                'location' => 'Alcar',
                'date_endorse' => $invalid,
                'dns' => 'ok.example.com',
            ])->assertRedirect('/pdc-servers')->assertSessionHasErrors('date_endorse');
        }
        $this->assertSame(0, PdcGroup::count());

        $this->post('/pdc-servers', [
            'campaign_id' => $campaign->id,
            'location' => 'Estancia',
            'date_endorse' => '1/1/2000',
            'dns' => 'pdc-bpicollections.teamssg.com',
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $group = PdcGroup::firstOrFail();
        $this->assertSame('2000-01-01', $group->date_endorse->format('Y-m-d'));
        $this->get('/pdc-servers')->assertSee('1/1/2000');
    }

    public function test_expanded_server_table_and_add_server_form(): void
    {
        $this->actingAs($this->admin);
        [$group, $server] = $this->seedGroupAndServer();

        $html = $this->get('/pdc-servers')->assertOk()->getContent();
        foreach (['ID', 'Hostname', 'Source IP', 'OS', 'RAM', 'CPU', 'Storage', 'Admin Username', 'Password', 'SQL DB Password'] as $column) {
            $this->assertStringContainsString($column, $html);
        }
        $this->assertStringContainsString('data-pdc-server-add', $html);
        $this->assertStringContainsString('data-group="'.$group->id.'"', $html);
        $this->assertStringContainsString($server->hostname, $html);
        $this->assertStringContainsString('id="pdc_hostname"', $html);
        $this->assertStringContainsString('id="pdc_ip_address"', $html);
        $this->assertStringContainsString('pdcServerSubmit">Save', $html);
        $this->assertStringNotContainsString('Add Server', $html);
        $this->assertStringNotContainsString('Save Server', $html);
    }

    public function test_source_ip_must_be_valid_unique_ipv4_and_can_keep_own_ip_on_edit(): void
    {
        $this->actingAs($this->admin);
        [$group, $server] = $this->seedGroupAndServer('10.24.28.57');

        foreach (['10.24.28.999', '10.24.28', 'abc.24.28.61', '10.24.28.1.5'] as $invalid) {
            $this->from('/pdc-servers')->post('/pdc-servers/'.$group->id.'/servers', [
                'hostname' => 'host-'.md5($invalid),
                'ip_address' => $invalid,
            ])->assertRedirect('/pdc-servers')->assertSessionHasErrors('ip_address');
        }

        $this->from('/pdc-servers')->post('/pdc-servers/'.$group->id.'/servers', [
            'hostname' => 'pdc-dup',
            'ip_address' => '10.24.28.57',
        ])->assertRedirect('/pdc-servers')->assertSessionHasErrors([
            'ip_address' => 'Source IP already exists. Each PDC server must have a unique Source IP.',
        ]);

        $this->put('/pdc-servers/'.$group->id.'/servers/'.$server->id, [
            'hostname' => $server->hostname,
            'ip_address' => '10.24.28.57',
        ])->assertRedirect();
        $this->assertDatabaseHas('pdc_servers', ['id' => $server->id, 'ip_address' => '10.24.28.57']);

        $this->post('/pdc-servers/'.$group->id.'/servers', [
            'hostname' => 'pdc-valid',
            'ip_address' => '10.24.28.61',
        ])->assertRedirect();
        $this->assertDatabaseHas('pdc_servers', ['hostname' => 'pdc-valid', 'ip_address' => '10.24.28.61']);
    }

    public function test_add_server_modal_has_cancel_and_save_without_visible_delete(): void
    {
        $this->actingAs($this->admin);
        $this->seedGroupAndServer();

        $html = $this->get('/pdc-servers')->assertOk()->getContent();
        $serverFooter = null;
        foreach (explode('<div class="modal-footer">', $html) as $chunk) {
            if (str_contains($chunk, 'id="pdcServerSubmit"')) {
                $serverFooter = $chunk;
                break;
            }
        }
        $this->assertNotNull($serverFooter);
        $this->assertStringContainsString('data-close="pdcServerModal">Cancel', $serverFooter);
        $this->assertStringContainsString('id="pdcServerSubmit">Save', $serverFooter);
        $this->assertStringNotContainsString('id="pdcServerDelete"', $serverFooter);
        $this->assertStringNotContainsString('>Delete</button>', $serverFooter);
        $this->assertStringContainsString('id="pdcServerModal" data-mode="add"', $html);
    }

    public function test_existing_server_records_can_be_edited_and_deleted(): void
    {
        $this->actingAs($this->admin);
        [$group, $server] = $this->seedGroupAndServer();

        $html = $this->get('/pdc-servers')->assertOk()->getContent();
        $this->assertStringContainsString('pdcServerSubmit', $html);
        $this->assertStringContainsString('class="action-btn delete"', $html);
        $this->assertStringContainsString('id="pdcServerModal" data-mode="add"', $html);
        $this->assertStringContainsString('data-pdc-server-add', $html);
        $this->assertStringContainsString('data-pdc-server-edit', $html);
        $this->assertStringContainsString("serverModal?.setAttribute('data-mode', 'add')", $html);
        $this->assertStringContainsString("serverModal?.setAttribute('data-mode', 'edit')", $html);
        $this->assertStringNotContainsString('Update Server', $html);
        $this->assertStringNotContainsString('Delete Server', $html);

        $this->put('/pdc-servers/'.$group->id.'/servers/'.$server->id, [
            'hostname' => 'pdc-edited',
            'ip_address' => '10.24.28.57',
            'ram' => '16GB',
        ])->assertRedirect();
        $this->assertDatabaseHas('pdc_servers', [
            'id' => $server->id,
            'hostname' => 'pdc-edited',
            'pdc_group_id' => $group->id,
            'ram' => '16GB',
        ]);

        $this->delete('/pdc-servers/'.$group->id.'/servers/'.$server->id)->assertRedirect();
        $this->assertDatabaseMissing('pdc_servers', ['id' => $server->id]);
        $this->assertDatabaseHas('pdc_groups', ['id' => $group->id]);
    }

    public function test_server_display_ids_restart_at_one_per_campaign(): void
    {
        $this->actingAs($this->admin);
        $location = array_values(OperationCatalog::locations())[0];
        foreach (['Atome', 'Chinabank'] as $name) {
            $campaign = ChannelAllocationCampaign::query()->create(['name' => $name]);
            $group = PdcGroup::query()->create([
                'campaign_id' => $campaign->id,
                'location' => $location,
            ]);
            PdcServer::query()->create([
                'pdc_group_id' => $group->id,
                'hostname' => $name.'-1',
                'ip_address' => $name === 'Atome' ? '10.24.28.71' : '10.24.28.81',
                'location' => $location,
                'status' => 'Active',
            ]);
            PdcServer::query()->create([
                'pdc_group_id' => $group->id,
                'hostname' => $name.'-2',
                'ip_address' => $name === 'Atome' ? '10.24.28.72' : '10.24.28.82',
                'location' => $location,
                'status' => 'Active',
            ]);
        }

        $html = $this->get('/pdc-servers')->assertOk()->getContent();
        preg_match_all('/pdc-server-id">(\d+)</', $html, $matches);
        $this->assertSame(['1', '2', '1', '2'], $matches[1]);
    }

    public function test_server_display_ids_are_sequential_and_renumber_after_delete(): void
    {
        $this->actingAs($this->admin);
        [$group] = $this->seedGroupAndServer('10.24.28.57');
        $second = PdcServer::query()->create([
            'pdc_group_id' => $group->id,
            'hostname' => 'pdc-core-02',
            'ip_address' => '10.24.28.58',
            'location' => 'Estancia',
            'status' => 'Active',
        ]);
        $third = PdcServer::query()->create([
            'pdc_group_id' => $group->id,
            'hostname' => 'pdc-core-03',
            'ip_address' => '10.24.28.59',
            'location' => 'Estancia',
            'status' => 'Active',
        ]);

        $html = $this->get('/pdc-servers')->assertOk()->getContent();
        preg_match_all('/pdc-server-id">(\d+)</', $html, $matches);
        $this->assertSame(['1', '2', '3'], $matches[1]);
        $this->assertStringContainsString('data-id="'.$second->id.'"', $html);
        $this->assertStringContainsString('data-id="'.$third->id.'"', $html);

        $this->delete('/pdc-servers/'.$group->id.'/servers/'.$second->id)->assertRedirect();
        $this->assertDatabaseHas('pdc_servers', ['id' => $third->id, 'hostname' => 'pdc-core-03']);

        $html = $this->get('/pdc-servers')->assertOk()->getContent();
        preg_match_all('/pdc-server-id">(\d+)</', $html, $matches);
        $this->assertSame(['1', '2'], $matches[1]);
        $this->assertStringNotContainsString('pdc-server-id">3<', $html);
        $this->assertStringContainsString('data-id="'.$third->id.'"', $html);
    }

    public function test_server_display_ids_continue_across_pagination(): void
    {
        $this->actingAs($this->admin);
        $location = array_values(OperationCatalog::locations())[0];
        for ($i = 1; $i <= 6; $i++) {
            $campaign = ChannelAllocationCampaign::query()->create(['name' => 'Campaign '.$i]);
            $group = PdcGroup::query()->create([
                'campaign_id' => $campaign->id,
                'location' => $location,
            ]);
            PdcServer::query()->create([
                'pdc_group_id' => $group->id,
                'hostname' => 'pdc-page-'.$i,
                'ip_address' => '10.24.28.'.(50 + $i),
                'location' => $location,
                'status' => 'Active',
            ]);
        }

        $page1 = $this->get('/pdc-servers?per_page=5')->assertOk()->getContent();
        preg_match_all('/pdc-server-id">(\d+)</', $page1, $matches);
        $this->assertSame(['1', '1', '1', '1', '1'], $matches[1]);

        $page2 = $this->get('/pdc-servers?per_page=5&page=2')->assertOk()->getContent();
        preg_match_all('/pdc-server-id">(\d+)</', $page2, $matches);
        $this->assertSame(['1'], $matches[1]);
    }

    public function test_nested_server_table_values_are_centered_to_headers(): void
    {
        $this->actingAs($this->admin);
        $this->seedGroupAndServer();

        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('.pdc-table > thead > tr > th', $css);
        $this->assertStringContainsString('.pdc-servers-nested thead th', $css);
        $this->assertStringContainsString('.pdc-servers-nested tbody td', $css);
        $this->assertStringContainsString('.pdc-cell-group', $css);
        $this->assertStringContainsString('text-align: center', $css);
        $this->assertMatchesRegularExpression('/\.pdc-cal-year\s*\{[^}]*color:\s*#000/', $css);
        $this->assertMatchesRegularExpression('/\.pdc-cal-month\s*\{[^}]*color:\s*#000/', $css);

        $html = $this->get('/pdc-servers')->assertOk()->getContent();
        $this->assertStringContainsString('class="ca-table pdc-table"', $html);
        $this->assertStringContainsString('class="pdc-servers-nested"', $html);
        $this->assertStringContainsString('class="pdc-cell-group pdc-dns"', $html);
        $this->assertStringContainsString('class="pdc-cell-group pdc-secret"', $html);
        $this->assertStringContainsString('class="pdc-cell-group row-actions"', $html);
        foreach (['ID', 'Hostname', 'Source IP', 'OS', 'RAM', 'CPU', 'Storage', 'Admin Username', 'Password', 'SQL DB Password'] as $column) {
            $this->assertStringContainsString($column, $html);
        }
    }

    public function test_passwords_are_masked_preserve_special_characters_and_hidden_from_standard_users(): void
    {
        $this->actingAs($this->admin);
        [$group] = $this->seedGroupAndServer();
        $this->post('/pdc-servers/'.$group->id.'/servers', [
            'hostname' => 'pdc-secret',
            'ip_address' => '10.24.28.80',
            'password' => 'P@ss word!#$',
            'sql_db_password' => 'Sql#DB$%^',
        ])->assertRedirect();

        $server = PdcServer::where('hostname', 'pdc-secret')->firstOrFail();
        $this->assertSame('P@ss word!#$', $server->password);
        $this->assertSame('Sql#DB$%^', $server->sql_db_password);

        $adminHtml = $this->get('/pdc-servers')->assertOk()->getContent();
        $this->assertStringContainsString('pdc-secret-mask', $adminHtml);
        $this->assertStringContainsString('P@ss word!#$', $adminHtml);
        $this->assertStringContainsString('pdc-secret-toggle', $adminHtml);

        $this->actingAs($this->system);
        $systemHtml = $this->get('/pdc-servers')->assertOk()->getContent();
        $this->assertStringContainsString('P@ss word!#$', $systemHtml);

        $this->actingAs($this->standard);
        $standardHtml = $this->get('/pdc-servers')->assertOk()->getContent();
        $this->assertStringContainsString('••••••••', $standardHtml);
        $this->assertStringNotContainsString('P@ss word!#$', $standardHtml);
        $this->assertStringNotContainsString('Sql#DB$%^', $standardHtml);
        $this->assertStringNotContainsString('class="pdc-secret-value"', $standardHtml);

        $export = $this->get('/pdc-servers/export')->assertOk();
        [$headers, $rows] = app(XlsxService::class)->read($export->getFile()->getPathname());
        $passwordIndex = array_search('Password', $headers, true);
        $this->assertNotFalse($passwordIndex);
        foreach ($rows as $row) {
            $this->assertSame('', (string) ($row[$passwordIndex] ?? ''));
        }
    }

    public function test_excel_import_resolves_sources_dash_rules_and_validation(): void
    {
        $this->actingAs($this->admin);
        ChannelAllocationCampaign::create(['name' => 'BPI Collection']);
        ChannelAllocationCampaign::create(['name' => 'Atome']);
        MediaGateway::create([
            'site_name' => 'Alcar',
            'site_code' => 'ALC-IP',
            'ip_address' => '10.50.50.50',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ]);

        $validPath = $this->spreadsheet([
            ['Campaign', 'Location', 'Date Endorse', 'DNS', 'Hostname', 'Source IP', 'OS', 'RAM', 'CPU', 'Storage', 'Admin Username', 'Password', 'SQL DB Password'],
            ['BPI Collection', 'Estancia', '9/3/2026', 'pdc-bpicollections.teamssg.com', 'pdc-one', '10.24.28.57', 'OS1', '12GB', '8cores', '120GB', 'admin', 'P@ss!#$', 'Sql#1'],
            ['', '', '', '', 'pdc-two', '10.24.28.58', '', '', '', '', '', 'Keep!@#', ''],
            ['-', 'Alcar', '9/4/2026', '-', 'pdc-skip', '10.24.28.59', '', '', '', '', '', '', ''],
            ['Atome', 'CTN', '9/5/2026', 'atome.example.com', 'pdc-three', '10.50.50.50', '', '', '', '', '', '', ''],
        ]);

        $preview = $this->postJson('/pdc-servers/import/preview', [
            'file' => $this->upload($validPath),
        ])->assertOk()->json();

        $this->assertFalse($preview['valid']);
        $this->assertTrue($preview['rows'][0]['valid']);
        $this->assertTrue($preview['rows'][1]['valid']);
        $this->assertSame('Estancia', $preview['rows'][1]['location']);
        $this->assertSame('BPI Collection', $preview['rows'][1]['campaign']);
        $this->assertFalse($preview['rows'][2]['valid']);
        $this->assertStringContainsString('Campaign', $preview['rows'][2]['error']);
        $this->assertTrue($preview['rows'][3]['valid']);
        $this->assertSame('Atome', $preview['rows'][3]['campaign']);

        $okPath = $this->spreadsheet([
            ['Campaign', 'Location', 'Date Endorse', 'DNS', 'Hostname', 'Source IP', 'OS', 'RAM', 'CPU', 'Storage', 'Admin Username', 'Password', 'SQL DB Password'],
            ['BPI Collection', 'Estancia', '9/3/2026', 'pdc-bpicollections.teamssg.com', 'pdc-one', '10.24.28.57', 'OS1', '12GB', '8cores', '120GB', 'admin', 'P@ss!#$', 'Sql#1'],
            ['', '', '', '', 'pdc-two', '10.24.28.58', '', '', '', '', '', 'Keep!@#', ''],
            ['Atome', 'CTN', '9/5/2026', 'atome.example.com', 'pdc-three', '10.50.50.50', '', '', '', '', '', '', ''],
        ]);
        $ok = $this->postJson('/pdc-servers/import/preview', ['file' => $this->upload($okPath)])->assertOk()->json();
        $this->assertTrue($ok['valid'], $ok['rows'][0]['error'] ?? '');
        $this->postJson('/pdc-servers/import/confirm', ['token' => $ok['token']])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(2, PdcGroup::count());
        $this->assertSame(2, ChannelAllocationCampaign::count());
        $bpi = PdcGroup::whereHas('campaign', fn ($q) => $q->where('name', 'BPI Collection'))->firstOrFail();
        $this->assertSame('Estancia', $bpi->location);
        $this->assertSame(2, $bpi->servers()->count());
        $this->assertSame('P@ss!#$', PdcServer::where('hostname', 'pdc-one')->first()->password);
        $this->assertSame('Keep!@#', PdcServer::where('hostname', 'pdc-two')->first()->password);
        $this->assertSame('10.50.50.50', PdcServer::where('hostname', 'pdc-three')->value('ip_address'));

        $dup = $this->spreadsheet([
            ['Campaign', 'Location', 'Date Endorse', 'DNS', 'Hostname', 'Source IP', 'OS', 'RAM', 'CPU', 'Storage', 'Admin Username', 'Password', 'SQL DB Password'],
            ['BPI Collection', 'Estancia', '9/3/2026', 'dns', 'pdc-four', '10.24.28.57', '', '', '', '', '', '', ''],
        ]);
        $dupPreview = $this->postJson('/pdc-servers/import/preview', ['file' => $this->upload($dup)])->assertOk()->json();
        $this->assertFalse($dupPreview['valid']);
        $this->assertStringContainsString('Source IP already exists. Each PDC server must have a unique Source IP.', $dupPreview['rows'][0]['error']);

        $missingCampaign = $this->spreadsheet([
            ['Campaign', 'Location', 'Date Endorse', 'DNS', 'Hostname', 'Source IP', 'OS', 'RAM', 'CPU', 'Storage', 'Admin Username', 'Password', 'SQL DB Password'],
            ['Unknown Campaign', 'Estancia', '9/3/2026', 'dns', 'pdc-x', '10.24.28.70', '', '', '', '', '', '', ''],
        ]);
        $missing = $this->postJson('/pdc-servers/import/preview', ['file' => $this->upload($missingCampaign)])->assertOk()->json();
        $this->assertFalse($missing['valid']);
        $this->assertStringContainsString('Campaign does not exist', $missing['rows'][0]['error']);

        $badLocation = $this->spreadsheet([
            ['Campaign', 'Location', 'Date Endorse', 'DNS', 'Hostname', 'Source IP', 'OS', 'RAM', 'CPU', 'Storage', 'Admin Username', 'Password', 'SQL DB Password'],
            ['Atome', 'PDC Taytay', '9/3/2026', 'dns', 'pdc-y', '10.24.28.71', '', '', '', '', '', '', ''],
        ]);
        $loc = $this->postJson('/pdc-servers/import/preview', ['file' => $this->upload($badLocation)])->assertOk()->json();
        $this->assertFalse($loc['valid']);
        $this->assertStringContainsString('Location does not exist', $loc['rows'][0]['error']);

        $export = $this->get('/pdc-servers/export')->assertOk()->assertDownload('pdc-servers.xlsx');
        [$headers] = app(XlsxService::class)->read($export->getFile()->getPathname());
        $this->assertSame('Campaign', $headers[0]);
        $this->assertContains('Source IP', $headers);
        $this->assertContains('Password', $headers);
    }

    public function test_search_reset_and_unrelated_modules_still_work(): void
    {
        $this->actingAs($this->admin);
        $this->seedGroupAndServer();
        ChannelAllocationCampaign::create(['name' => 'Tala']);

        $this->get('/pdc-servers?search=BPI')
            ->assertOk()
            ->assertSee('BPI Collection');
        $this->get('/pdc-servers')->assertOk()->assertSee('id="pdcReset"', false);
        $this->get('/channel-allocation')->assertOk()->assertSee('Channel Allocation');
        $this->get('/program-location')->assertOk()->assertSee('Program Location');
        $this->get('/sip-channels')->assertOk()->assertSee('SIP Channels');
    }

    /**
     * @return array{0: PdcGroup, 1: PdcServer}
     */
    private function seedGroupAndServer(string $ip = '10.24.28.57'): array
    {
        $campaign = ChannelAllocationCampaign::query()->firstOrCreate(['name' => 'BPI Collection']);
        $group = PdcGroup::query()->create([
            'campaign_id' => $campaign->id,
            'location' => 'Estancia',
            'date_endorse' => '2026-09-03',
            'dns' => "pdc-bpicollections.teamssg.com\npdc-bpi-alt.teamssg.com",
        ]);
        $server = PdcServer::query()->create([
            'pdc_group_id' => $group->id,
            'hostname' => 'pdc-core-01',
            'ip_address' => $ip,
            'location' => 'Estancia',
            'os' => 'cpe:/o:opensuse:leap:15.3',
            'ram' => '12GB',
            'cpu' => '8cores',
            'storage' => '120GB',
            'admin_username' => 'admin',
            'status' => 'Active',
        ]);

        return [$group, $server];
    }

    /**
     * @param  list<list<string|int>>  $rows
     */
    private function spreadsheet(array $rows): string
    {
        $headers = array_shift($rows);

        return app(XlsxService::class)->export($headers, $rows, 'pdc-import.xlsx');
    }

    private function upload(string $path): UploadedFile
    {
        return new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
