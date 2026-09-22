<?php

namespace Tests\Feature;

use App\Models\ChannelAllocationCampaign;
use App\Models\GatewaySimAssignment;
use App\Models\GlobeSim;
use App\Models\MediaGateway;
use App\Models\PdcGroup;
use App\Models\PdcServer;
use App\Models\SmartSim;
use App\Models\User;
use App\Models\UserType;
use App\Services\XlsxService;
use App\Support\Gsm\GsmGatewayWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SidebarDashboardAndGsmAccessTest extends TestCase
{
    use RefreshDatabase;

    private UserType $adminType;

    private UserType $standardType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\UserTypeSeeder']);
        $this->adminType = UserType::where('name', 'Administrator')->firstOrFail();
        $this->standardType = UserType::where('name', 'Standard User')->firstOrFail();
    }

    private function user(UserType $type): User
    {
        return User::factory()->create([
            'user_type_id' => $type->id,
            'status' => 'Active',
        ]);
    }

    public function test_sidebar_brand_shows_ssg_logo_and_inventory_name(): void
    {
        $this->actingAs($this->user($this->adminType));

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('class="brand-logo"', false)
            ->assertSee('images/ssg-logo-white.png', false)
            ->assertSee('images/ssg-favicon.png', false)
            ->assertSee('<title>Dashboard</title>', false)
            ->assertSee('<div class="brand-title">OmniChannel Inventory</div>', false)
            ->assertDontSee('<div class="brand-subtitle">Inventory</div>', false)
            ->assertDontSee('<div class="brand-subtitle">Reports</div>', false)
            ->assertSee('>Dashboard</span></a>', false)
            ->assertSee('class="nav-item nav-parent-row" id="networkToggle"', false)
            ->assertSee('id="networkCaret"', false)
            ->assertSee('class="nav-caret" id="networkCaret"', false)
            ->assertSee('id="sipChannelsGroup"', false)
            ->assertSee('id="sipChannelsToggle"', false)
            ->assertSee('href="'.url('/sip-channels').'"', false)
            ->assertSee('id="sipChannelsCaret"', false)
            ->assertSee('id="sipChannelsSub"', false)
            ->assertSee('>Channel Range</span></a>', false)
            ->assertDontSee('<button type="button" class="nav-caret"', false)
            ->assertDontSee('>⌃</span>', false)
            ->assertDontSee('>⌄</span>', false);

        $html = $this->get('/dashboard')->getContent();
        $sipSub = Str::betweenFirst($html, 'id="sipChannelsSub"', '</div>');
        $this->assertStringContainsString('Channel Range', $sipSub);
        $this->assertStringNotContainsString('SIP Channels', $sipSub);
        $this->assertStringContainsString('id="networkToggle"', $html);
        $this->assertStringContainsString('<button type="button" class="nav-item nav-parent-row" id="networkToggle"', $html);
        $this->assertStringContainsString('href="'.url('/sip-channels').'"', $html);
        $this->get('/channel-range-list')
            ->assertOk()
            ->assertSee('class="nav-group open" id="sipChannelsGroup"', false)
            ->assertSee('>Channel Range</span></a>', false);
    }

    public function test_sidebar_operations_and_locations_are_reachable(): void
    {
        $this->actingAs($this->user($this->adminType));

        MediaGateway::create([
            'site_name' => 'Estancia',
            'site_code' => 'PAS202',
            'ip_address' => '10.28.240.202',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ]);

        $pages = [
            '/dashboard' => 'Total Campaigns',
            '/campaigns' => 'Campaigns',
            '/pdc-servers' => 'PDC Servers',
            '/sip-channels' => 'SIP Channels',
            '/channel-range-list' => 'Channel Range',
            '/channel-allocation' => 'Channel Allocation',
            '/archive-recordings' => 'Archive Recordings',
            '/gsm-gateways' => 'GSM Gateway Server List',
            '/globe-sim' => 'Globe SIM',
            '/smart-sim' => 'Smart SIM',
            '/program-inbound-numbers' => 'Program Inbound Numbers',
            '/signal-boosters' => 'Signal Boosters',
            '/defective-gsm' => 'Defective GSM',
            '/program-location' => 'Program Location',
            '/program-location/alcar' => 'Alcar',
            '/program-location/ctn' => 'CTN',
            '/program-location/scs' => 'SC5',
            '/program-location/pdc' => 'PDC',
            '/program-location/estancia' => 'Estancia',
            '/program-location/skyrise' => 'Skyrise',
        ];

        foreach ($pages as $url => $text) {
            $this->get($url)->assertOk()->assertSee($text);
        }

        $this->get('/program-location/estancia')
            ->assertOk()
            ->assertSee('Estancia')
            ->assertDontSee('id="programLocationGroup"', false)
            ->assertDontSee('id="programLocationSub"', false)
            ->assertDontSee('id="programLocationToggle"', false)
            ->assertDontSee('Toggle Program Location')
            ->assertSee('id="networkGroup"', false)
            ->assertSee('id="networkToggle"', false)
            ->assertSee('id="sidebarNav"', false)
            ->assertSee('>Manage</div>', false)
            ->assertDontSee('>Operations</div>', false)
            ->assertDontSee('>Locations</div>', false);

        $this->get('/gsm-gateways')
            ->assertOk()
            ->assertSee('data-page="gsm-gateways"', false)
            ->assertSee('GSM Gateway Server List')
            ->assertSee('Search GSM Gateways')
            ->assertSee('Export Data')
            ->assertSee('id="transferButton"', false)
            ->assertSee('class="plus-btn"', false)
            ->assertSee('class="table-card table-wrap"', false)
            ->assertSee('class="table-footer"', false)
            ->assertSee('Hostname')
            ->assertSee('Serial Number')
            ->assertSee('Channel Count')
            ->assertSee('data-sort="network"', false)
            ->assertSee('>Function<', false)
            ->assertSee('>Site<', false)
            ->assertSee('>User<', false)
            ->assertSee('>Password<', false)
            ->assertDontSee('Hostname IP')
            ->assertSee('pdc-secret-toggle', false)
            ->assertDontSee('>Id</span>', false)
            ->assertDontSee('>Media Gateways</span>', false)
            ->assertDontSee('>Users</span>', false)
            ->assertDontSee('>User Types</span>', false)
            ->assertDontSee('>Operations</div>', false);
    }

    public function test_browser_tab_titles_match_the_current_page(): void
    {
        $this->actingAs($this->user($this->adminType));

        $titles = [
            '/dashboard' => 'Dashboard',
            '/campaigns' => 'Campaigns',
            '/pdc-servers' => 'PDC Servers',
            '/sip-channels' => 'SIP Channels',
            '/channel-range-list' => 'Channel Range',
            '/channel-allocation' => 'Channel Allocation',
            '/archive-recordings' => 'Archive Recordings',
            '/gsm-gateways' => 'GSM Gateway',
            '/globe-sim' => 'Globe SIM',
            '/smart-sim' => 'Smart SIM',
            '/program-inbound-numbers' => 'Program Inbound Numbers',
            '/signal-boosters' => 'Signal Boosters',
            '/users' => 'Users',
            '/activity-logs' => 'Audit Logs',
            '/channel-utilization' => 'Channel Utilization',
        ];

        foreach ($titles as $url => $title) {
            $this->get($url)
                ->assertOk()
                ->assertSee('<title>'.$title.'</title>', false)
                ->assertDontSee('<title>'.$title.' - OmniChannel Inventory</title>', false);
        }
    }

    public function test_operational_pages_use_gsm_gateway_layout(): void
    {
        $this->actingAs($this->user($this->adminType));

        $campaign = ChannelAllocationCampaign::create(['name' => 'BPI Collection']);
        $group = PdcGroup::create([
            'campaign_id' => $campaign->id,
            'location' => 'Estancia',
        ]);
        PdcServer::create([
            'pdc_group_id' => $group->id,
            'hostname' => 'pdc-core-01',
            'ip_address' => '10.10.10.10',
            'location' => 'Estancia',
            'role' => 'Primary',
            'status' => 'Active',
        ]);

        $pages = [
            '/campaigns',
            '/pdc-servers',
            '/sip-channels',
            '/channel-allocation',
            '/archive-recordings',
            '/globe-sim',
            '/smart-sim',
            '/program-inbound-numbers',
            '/signal-boosters',
            '/defective-gsm',
            '/program-location/estancia',
        ];

        foreach ($pages as $url) {
            $page = $this->get($url)->assertOk()->assertSee('class="page-head"', false);
            if ($url === '/archive-recordings') {
                $page->assertSee('ar-tree-card', false)
                    ->assertSee('id="arSearchInput"', false)
                    ->assertSee('Search recordings...')
                    ->assertDontSee('id="archiveSearchInput"', false);

                continue;
            }
            $page->assertSee('class="search-box', false)
                ->assertSee('class="table-card table-wrap"', false)
                ->assertSee('class="table-footer"', false);
        }

        $this->get('/pdc-servers')
            ->assertOk()
            ->assertSee('Export Data', false)
            ->assertSee('class="plus-btn"', false)
            ->assertSee('id="transferButton"', false)
            ->assertSee('class="actions-column"', false)
            ->assertSee('row-actions', false)
            ->assertSee('Hostname')
            ->assertDontSee('filter-row', false);
    }

    public function test_admin_sees_user_management_and_can_manage_gsm(): void
    {
        $this->actingAs($this->user($this->adminType));

        $this->get('/dashboard')
            ->assertOk()
            ->assertDontSee('>Administration</div>', false)
            ->assertSee('User Management')
            ->assertSee('href="'.url('/users').'"', false)
            ->assertSee('id="accountSettingsToggle"', false)
            ->assertSee('account-group-caret', false)
            ->assertDontSee('>User Types</a>', false)
            ->assertSee('Total Campaigns')
            ->assertSee('Total GSM Gateway')
            ->assertSee('Total SIP Channels')
            ->assertSee('Total SIMs')
            ->assertSee('Campaigns with Most Allocations')
            ->assertSee('Channel Utilization')
            ->assertSee('Allocation Trends')
            ->assertDontSee('Quick Insights')
            ->assertDontSee('Recent System Activity')
            ->assertDontSee('Program Location Overview')
            ->assertDontSee('System Health')
            ->assertDontSee('Network Overview')
            ->assertDontSee('Quick Actions')
            ->assertSee('My Profile')
            ->assertSee('Login History')
            ->assertSee('Audit Logs')
            ->assertDontSee('id="notificationButton"', false)
            ->assertSee('id="accountButton"', false)
            ->assertDontSee('Activity Logs')
            ->assertDontSee('Recent Login Activity');

        $this->get('/users')->assertOk();
        $this->get('/user-types')->assertNotFound();

        $this->postJson('/gsm-gateways', [
            'hostname' => 'gsm-admin-'.$this->adminType->id,
            'site_name' => 'Alcar',
            'site_code' => 'ALC-'.$this->adminType->id,
            'ip_address' => '10.9.9.'.$this->adminType->id,
            'channel_count' => 8,
            'device_function' => 'Outbound',
            'network' => 'Globe SIM',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ])->assertCreated();
    }

    public function test_standard_user_cannot_see_or_access_admin_or_mutate_gsm(): void
    {
        $this->actingAs($this->user($this->standardType));

        $gateway = MediaGateway::create([
            'site_name' => 'Estancia',
            'site_code' => 'STD-GSM',
            'ip_address' => '10.8.8.8',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ]);

        $this->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Administration')
            ->assertDontSee('User Management')
            ->assertDontSee('id="accountUserManagement"', false)
            ->assertDontSee('Activity Logs')
            ->assertDontSee('Recent Login Activity');

        $this->get('/gsm-gateways')
            ->assertOk()
            ->assertSee('GSM Gateway Server List')
            ->assertSee('data-can-edit="0"', false)
            ->assertSee('data-can-delete="0"', false)
            ->assertDontSee('action-btn edit', false)
            ->assertDontSee('action-btn delete', false)
            ->assertDontSee('ca-menu-item edit', false)
            ->assertDontSee('ca-menu-item delete', false);

        $this->putJson('/gsm-gateways/'.$gateway->id, [
            'hostname' => 'hacked-host',
            'site_name' => 'Hacked',
            'site_code' => 'STD-GSM',
            'ip_address' => '10.8.8.8',
            'channel_count' => 8,
            'device_function' => 'Inbound',
            'network' => 'Globe SIM',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ])->assertForbidden();

        $this->deleteJson('/gsm-gateways/'.$gateway->id)->assertForbidden();
        $this->get('/gsm-gateways/'.$gateway->id.'/edit')->assertNotFound();
        $this->get('/users')->assertForbidden();
        $this->get('/users/1/edit')->assertForbidden();
        $this->get('/user-types')->assertNotFound();
        $this->get('/user-types/1/edit')->assertNotFound();

        $this->assertDatabaseHas('media_gateways', [
            'id' => $gateway->id,
            'site_name' => 'Estancia',
        ]);
    }

    public function test_standard_users_cannot_reveal_gsm_gateway_passwords(): void
    {
        $secret = 'GsmSecret#Reveal99!';
        MediaGateway::create([
            'site_name' => 'Estancia',
            'site_code' => 'STD-PW',
            'ip_address' => '10.8.8.88',
            'username' => 'root',
            'password' => $secret,
            'database' => 'asteriskcdrdb',
        ]);

        $this->actingAs($this->user($this->adminType));
        $adminHtml = $this->get('/gsm-gateways')->assertOk()->getContent();
        $this->assertStringContainsString('pdc-secret-toggle', $adminHtml);
        $this->assertStringContainsString($secret, $adminHtml);
        $this->assertStringContainsString('data-can-reveal-secrets="1"', $adminHtml);
        $this->getJson('/gsm-gateways')->assertOk()->assertJsonFragment(['password' => $secret]);

        $this->actingAs($this->user($this->standardType));
        $html = $this->get('/gsm-gateways')->assertOk()->getContent();
        $this->assertStringContainsString('••••••', $html);
        $this->assertStringContainsString('data-can-reveal-secrets="0"', $html);
        $this->assertStringNotContainsString($secret, $html);
        $this->assertStringNotContainsString('pdc-secret-toggle', $html);
        $this->assertStringNotContainsString('pdc-secret-value', $html);
        $this->assertStringNotContainsString('data-edit-password', $html);

        $json = $this->getJson('/gsm-gateways')->assertOk();
        $this->assertSame('', $json->json('records.0.password'));
        $this->assertStringNotContainsString($secret, $json->getContent());

        $export = $this->get('/gsm-gateways/export')->assertOk();
        [$headers, $rows] = app(XlsxService::class)->read($export->getFile()->getPathname());
        $this->assertFalse(array_search('Password', $headers, true));
        foreach ($rows as $row) {
            $this->assertNotContains($secret, $row);
        }
    }

    public function test_gsm_sim_assignments_use_existing_inventory_and_gateway_ip(): void
    {
        $this->actingAs($this->user($this->adminType));

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

        $create = $this->postJson('/gsm-gateways', [
            'hostname' => 'gsm_smart_mn201',
            'site_name' => 'Alcar',
            'site_code' => 'SN654321',
            'ip_address' => '10.5.20.201',
            'channel_count' => 2,
            'device_function' => 'Inbound',
            'username' => 'root',
        ])->assertCreated();

        $gatewayId = $create->json('record.id');
        $this->assertNotEmpty($gatewayId);
        $this->assertCount(2, $create->json('record.assignments'));
        $this->assertSame(1, $create->json('record.assignments.0.port'));
        $this->assertSame('', $create->json('record.assignments.0.imei'));
        $this->assertSame(2, $create->json('record.assignments.1.port'));

        $this->getJson('/gsm-gateways/sims?network=Globe SIM')
            ->assertOk()
            ->assertJsonFragment(['imei' => '123456789012345', 'sim_type' => 'globe']);

        $first = $this->postJson('/gsm-gateways/'.$gatewayId.'/assignments', [
            'network' => 'Globe SIM',
            'sim_id' => $globe->id,
        ])->assertCreated();

        $this->assertSame(1, $first->json('record.assignments.0.port'));
        $this->assertSame('10.5.20.201', $first->json('record.assignments.0.ip_address'));
        $this->assertSame('123456789012345', $first->json('record.assignments.0.imei'));
        $this->assertSame('Corporate', $first->json('record.assignments.0.plan'));
        $this->assertSame(1, (int) $globe->fresh()->port);

        $second = $this->postJson('/gsm-gateways/'.$gatewayId.'/assignments', [
            'network' => 'Smart SIM',
            'sim_id' => $smart->id,
        ])->assertCreated();
        $this->assertSame(2, $second->json('record.assignments.1.port'));
        $this->assertSame('10.5.20.201', $second->json('record.assignments.1.ip_address'));
        $this->assertSame(2, (int) $smart->fresh()->port);

        $this->postJson('/gsm-gateways/'.$gatewayId.'/assignments', [
            'network' => 'Globe SIM',
            'sim_id' => $globe->id,
        ])->assertUnprocessable();

        $this->assertSame(1, GlobeSim::count());
        $this->assertSame(1, SmartSim::count());
        $this->assertSame(2, GatewaySimAssignment::count());

        $page = $this->get('/gsm-gateways')->assertOk();
        $page->assertSee('123456789012345')
            ->assertSee('09181234567')
            ->assertSee('data-open-modal="add-media-gateway"', false)
            ->assertSee('class="ca-menu-item edit"', false)
            ->assertSee('class="ca-menu-item delete"', false)
            ->assertSee('data-port-remarks', false)
            ->assertSee('data-port-delete', false)
            ->assertDontSee('data-gsm-sim-add', false)
            ->assertDontSee('data-gsm-sim-edit', false)
            ->assertDontSee('data-gsm-sim-delete', false)
            ->assertDontSee('id="gsmSimModal"', false)
            ->assertDontSee('Add SIM Assignment', false)
            ->assertDontSee('SIM Assignments (', false)
            ->assertDontSee('type="radio"', false);

        $nested = Str::betweenFirst($page->getContent(), 'class="gsm-sim-nested"', '</table>');
        $this->assertStringContainsString('>Port</th>', $nested);
        $this->assertStringContainsString('>IMEI</th>', $nested);
        $this->assertStringContainsString('>Mobile Number</th>', $nested);
        $this->assertStringContainsString('>Plan</th>', $nested);
        $this->assertStringContainsString('>Remarks</th>', $nested);
        $this->assertStringContainsString('>Actions</th>', $nested);
        $this->assertTrue(strpos($nested, '>Port</th>') < strpos($nested, '>IMEI</th>'));
        $this->assertTrue(strpos($nested, '>Remarks</th>') < strpos($nested, '>Actions</th>'));
        $this->assertStringNotContainsString('>IP</th>', $nested);
        $this->assertStringNotContainsString('plus-btn', $nested);

        $this->actingAs($this->user($this->standardType));
        $this->postJson('/gsm-gateways/'.$gatewayId.'/assignments', [
            'network' => 'Globe SIM',
            'sim_id' => $globe->id,
        ])->assertForbidden();
    }

    public function test_gsm_gateway_add_can_assign_multiple_unassigned_sims_up_to_channel_count(): void
    {
        $this->actingAs($this->user($this->adminType));

        $first = GlobeSim::create([
            'imei' => '356938035643001',
            'mobile_number' => '09170000001',
            'plan' => 'Plan A',
            'status' => 'Active',
        ]);
        $second = GlobeSim::create([
            'imei' => '356938035643002',
            'mobile_number' => '09170000002',
            'plan' => 'Plan B',
            'status' => 'Active',
        ]);
        $third = GlobeSim::create([
            'imei' => '356938035643003',
            'mobile_number' => '09170000003',
            'plan' => 'Plan C',
            'status' => 'Active',
        ]);

        $create = $this->postJson('/gsm-gateways', [
            'hostname' => 'gsm-multi-add',
            'site_name' => 'Alcar',
            'site_code' => 'SNMULTI01',
            'ip_address' => '10.5.20.210',
            'channel_count' => 2,
            'device_function' => 'Inbound',
            'username' => 'root',
        ])->assertCreated();

        $gatewayId = $create->json('record.id');

        $this->postJson('/gsm-gateways/'.$gatewayId.'/assignments', [
            'network' => 'Globe SIM',
            'sim_id' => $first->id,
        ])->assertCreated();
        $this->postJson('/gsm-gateways/'.$gatewayId.'/assignments', [
            'network' => 'Globe SIM',
            'sim_id' => $second->id,
        ])->assertCreated();
        $this->postJson('/gsm-gateways/'.$gatewayId.'/assignments', [
            'network' => 'Globe SIM',
            'sim_id' => $third->id,
        ])->assertUnprocessable();

        $this->assertSame(2, GatewaySimAssignment::query()->where('media_gateway_id', $gatewayId)->count());
    }

    public function test_gsm_gateway_displays_ports_from_channel_count_and_places_sims_by_hostname_and_port(): void
    {
        $this->actingAs($this->user($this->adminType));

        $gateway = MediaGateway::create([
            'hostname' => 'Jelan',
            'site_name' => 'Alcar',
            'site_code' => 'SNIP01',
            'ip_address' => '192.168.1.1',
            'channel_count' => 5,
            'device_function' => 'Inbound',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ]);

        $this->post('/globe-sim', [
            'imei' => 'IMEI-G1',
            'mobile_number' => '0963125515',
            'plan' => 'Postpaid',
            'hostname' => 'Jelan',
            'port' => 1,
            'account_number' => 'ACC-0001',
            'contract_start' => '3/1/2026',
            'contract_end' => '9/3/2026',
        ])->assertRedirect();
        $this->post('/smart-sim', [
            'imei' => 'IMEI-S3',
            'mobile_number' => '0963125510',
            'plan' => 'Prepaid',
            'hostname' => 'Jelan',
            'port' => 3,
            'account_number' => 'ACC-0003',
            'contract_start' => '3/1/2026',
            'contract_end' => '9/3/2026',
        ])->assertRedirect();
        SmartSim::query()->where('imei', 'IMEI-S3')->update(['remarks' => 'Defective']);
        $this->post('/globe-sim', [
            'imei' => 'IMEI-G5',
            'mobile_number' => '09628307759',
            'plan' => 'Postpaid',
            'hostname' => 'Jelan',
            'port' => 5,
            'account_number' => 'ACC-0005',
            'contract_start' => '3/1/2026',
            'contract_end' => '9/3/2026',
        ])->assertRedirect();

        $records = collect($this->getJson('/gsm-gateways')->assertOk()->json('records'));
        $payload = $records->firstWhere('id', $gateway->id)['assignments'];
        $this->assertCount(5, $payload);
        $this->assertSame([1, 2, 3, 4, 5], array_column($payload, 'port'));
        $this->assertSame('0963125515', $payload[0]['mobile_number']);
        $this->assertSame('', $payload[1]['imei']);
        $this->assertSame('0963125510', $payload[2]['mobile_number']);
        $this->assertSame('Defective', $payload[2]['remarks']);
        $this->assertSame('', $payload[3]['imei']);
        $this->assertSame('09628307759', $payload[4]['mobile_number']);
        $this->assertSame('', $payload[4]['remarks']);

        $page = $this->get('/gsm-gateways')->assertOk();
        $nested = Str::betweenFirst($page->getContent(), 'class="gsm-sim-nested"', '</table>');
        $this->assertStringContainsString('>Port</th>', $nested);
        $this->assertStringContainsString('0963125515', $nested);
        $this->assertStringContainsString('Defective', $nested);
        $this->assertGreaterThanOrEqual(5, substr_count($nested, '—'));

        $smart = SmartSim::query()->where('imei', 'IMEI-S3')->firstOrFail();
        $this->put('/smart-sim/'.$smart->id, [
            'imei' => 'IMEI-S3',
            'mobile_number' => '0963125510',
            'plan' => 'Prepaid',
            'hostname' => 'Jelan',
            'port' => 2,
            'account_number' => 'ACC-0003',
            'contract_start' => '3/1/2026',
            'contract_end' => '9/3/2026',
        ])->assertRedirect();
        $smart->refresh();
        $smart->forceFill(['remarks' => 'For replacement'])->save();

        $moved = collect($this->getJson('/gsm-gateways')->assertOk()->json('records'))
            ->firstWhere('id', $gateway->id)['assignments'];
        $this->assertSame('', $moved[2]['imei']);
        $this->assertSame('0963125510', $moved[1]['mobile_number']);
        $this->assertSame('For replacement', $moved[1]['remarks']);
    }

    public function test_gsm_port_actions_update_remarks_only_and_delete_one_assignment(): void
    {
        $this->actingAs($this->user($this->adminType));

        $gateway = MediaGateway::create([
            'hostname' => 'port-actions',
            'site_name' => 'Alcar',
            'site_code' => 'PORTACT1',
            'ip_address' => '10.5.20.77',
            'channel_count' => 5,
            'network' => 'Globe SIM',
            'device_function' => 'Inbound',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ]);
        $first = GlobeSim::create([
            'imei' => 'PORT-IMEI-1',
            'mobile_number' => '09170000011',
            'plan' => 'Plan One',
            'network' => 'Globe SIM',
            'port' => 1,
            'ip_address' => '10.5.20.77',
            'remarks' => 'Keep me',
            'status' => 'Active',
        ]);
        $second = SmartSim::create([
            'imei' => 'PORT-IMEI-3',
            'mobile_number' => '09170000013',
            'plan' => 'Plan Three',
            'network' => 'Smart SIM',
            'port' => 3,
            'ip_address' => '10.5.20.77',
            'remarks' => 'Stay on three',
            'status' => 'Active',
        ]);
        $firstAssignment = GatewaySimAssignment::create([
            'media_gateway_id' => $gateway->id,
            'sim_type' => 'globe',
            'sim_id' => $first->id,
            'port' => 1,
        ]);
        GatewaySimAssignment::create([
            'media_gateway_id' => $gateway->id,
            'sim_type' => 'smart',
            'sim_id' => $second->id,
            'port' => 3,
        ]);

        $this->patchJson('/gsm-gateways/'.$gateway->id.'/port-remarks', [
            'sim_type' => 'globe',
            'sim_id' => $first->id,
            'port' => 1,
            'remarks' => '',
        ])->assertOk();

        $first->refresh();
        $second->refresh();
        $this->assertNull($first->remarks);
        $this->assertSame(1, (int) $first->port);
        $this->assertSame('PORT-IMEI-1', $first->imei);
        $this->assertSame('09170000011', $first->mobile_number);
        $this->assertSame('Plan One', $first->plan);
        $this->assertSame('Stay on three', $second->remarks);
        $this->assertSame(3, (int) $second->port);

        $this->patchJson('/gsm-gateways/'.$gateway->id.'/port-remarks', [
            'sim_type' => 'globe',
            'sim_id' => $first->id,
            'port' => 4,
            'remarks' => 'Moved',
        ])->assertUnprocessable();
        $this->assertSame(1, (int) $first->fresh()->port);
        $this->assertNull($first->fresh()->remarks);

        $this->deleteJson('/gsm-gateways/'.$gateway->id.'/assignments/'.$firstAssignment->id)
            ->assertOk();

        $this->assertDatabaseHas('media_gateways', ['id' => $gateway->id]);
        $this->assertDatabaseHas('globe_sims', ['id' => $first->id]);
        $this->assertDatabaseHas('smart_sims', ['id' => $second->id]);
        $this->assertDatabaseMissing('gateway_sim_assignments', ['id' => $firstAssignment->id]);
        $this->assertDatabaseHas('gateway_sim_assignments', [
            'media_gateway_id' => $gateway->id,
            'sim_id' => $second->id,
            'port' => 3,
        ]);
        $this->assertSame(3, (int) $second->fresh()->port);

        $payload = collect($this->getJson('/gsm-gateways')->assertOk()->json('records'))
            ->firstWhere('id', $gateway->id)['assignments'];
        $this->assertSame([1, 2, 3, 4, 5], array_column($payload, 'port'));
        $this->assertSame('', $payload[0]['imei']);
        $this->assertSame('PORT-IMEI-3', $payload[2]['imei']);
        $this->assertSame('Stay on three', $payload[2]['remarks']);

        $page = $this->get('/gsm-gateways')->assertOk()->getContent();
        $panelStart = strpos($page, 'id="gsm-panel-'.$gateway->id.'"');
        $panelEnd = strpos($page, '</table>', $panelStart);
        $this->assertNotFalse($panelStart);
        $this->assertNotFalse($panelEnd);
        $panel = substr($page, $panelStart, $panelEnd - $panelStart);
        $this->assertSame(5, substr_count($panel, 'data-port-remarks'));
        $this->assertSame(5, substr_count($panel, 'data-port-number'));
        $this->assertSame(5, substr_count($panel, 'action-btn edit'));
        $this->assertSame(5, substr_count($panel, 'action-btn delete'));

        $this->patchJson('/gsm-gateways/'.$gateway->id.'/port-remarks', [
            'sim_id' => 0,
            'port' => 2,
            'remarks' => 'Empty port note',
        ])->assertOk();
        $noted = collect($this->getJson('/gsm-gateways')->assertOk()->json('records'))
            ->firstWhere('id', $gateway->id)['assignments'];
        $this->assertSame('Empty port note', $noted[1]['remarks']);
        $this->assertSame('', $noted[1]['imei']);
        $this->assertSame('PORT-IMEI-3', $noted[2]['imei']);
        $this->assertSame(3, (int) $second->fresh()->port);
        $this->assertSame(1, GatewaySimAssignment::query()->where('media_gateway_id', $gateway->id)->count());

        $this->deleteJson('/gsm-gateways/'.$gateway->id.'/ports/2')->assertOk();
        $this->deleteJson('/gsm-gateways/'.$gateway->id.'/ports/4')->assertOk();
        $cleared = collect($this->getJson('/gsm-gateways')->assertOk()->json('records'))
            ->firstWhere('id', $gateway->id)['assignments'];
        $this->assertSame([1, 2, 3, 4, 5], array_column($cleared, 'port'));
        $this->assertSame('', $cleared[1]['remarks']);
        $this->assertSame('', $cleared[3]['imei']);
        $this->assertSame('PORT-IMEI-3', $cleared[2]['imei']);
        $this->assertSame('Stay on three', $cleared[2]['remarks']);
        $this->assertDatabaseHas('media_gateways', ['id' => $gateway->id]);
        $this->assertDatabaseHas('smart_sims', ['id' => $second->id]);

        $js = file_get_contents(resource_path('js/app.js'));
        $portKey = strpos($js, "{key:'port', label:'Port'");
        $imeiKey = strpos($js, "{key:'imei', label:'IMEI'");
        $remarksKey = strpos($js, "{key:'remarks', label:'Remarks'");
        $actionsHead = strpos($js, '<th class="actions-column">Actions</th>');
        $this->assertNotFalse($portKey);
        $this->assertTrue($portKey < $imeiKey);
        $this->assertTrue($imeiKey < $remarksKey);
        $this->assertTrue($remarksKey < $actionsHead);
    }

    public function test_sim_inventory_card_opens_a_selection_modal_for_globe_and_smart(): void
    {
        GlobeSim::create([
            'imei' => '356938035643111',
            'mobile_number' => '09170001111',
            'location' => 'Estancia',
            'status' => 'Active',
        ]);
        SmartSim::create([
            'imei' => '356938035643222',
            'mobile_number' => '09280002222',
            'location' => 'CTN',
            'status' => 'Active',
        ]);

        $page = $this->actingAs($this->user($this->adminType))->get('/dashboard')->assertOk();

        $page->assertSee('Total SIMs')
            ->assertSee('>Globe</span>', false)
            ->assertSee('>Smart</span>', false)
            ->assertSee('SIM Inventory')
            ->assertDontSee('data-open="simInventoryModal"', false)
            ->assertSee('id="simInventoryModal"', false)
            ->assertSee('Select which SIM inventory you want to view.')
            ->assertSee('class="sim-pick sim-pick-globe"', false)
            ->assertSee('class="sim-pick sim-pick-smart"', false)
            ->assertSee('class="sim-pick sim-pick-globe" href="'.url('/globe-sim').'"', false)
            ->assertSee('class="sim-pick sim-pick-smart" href="'.url('/smart-sim').'"', false)
            ->assertSee('Total Records')
            ->assertDontSee('class="dash-kpi dash-kpi-green" href="'.url('/globe-sim').'"', false)
            ->assertDontSee('<a href="'.url('/gsm-gateways').'" class="dash-kpi', false);

        $this->actingAs($this->user($this->adminType))->get('/globe-sim')
            ->assertOk()
            ->assertSee('Globe SIM')
            ->assertSee('class="page-head"', false)
            ->assertSee('class="nav-group open" id="networkGroup"', false)
            ->assertSee('class="nav-item nav-parent-row" id="networkToggle"', false)
            ->assertSee('id="networkCaret"', false)
            ->assertSee('class="nav-caret" id="networkCaret"', false)
            ->assertDontSee('>⌄</span>', false)
            ->assertDontSee('<button type="button" class="nav-caret"', false)
            ->assertSee('href="'.url('/globe-sim').'"', false)
            ->assertSee('href="'.url('/smart-sim').'"', false)
            ->assertDontSee('href="'.url('/network').'"', false);

        $this->actingAs($this->user($this->adminType))->get('/smart-sim')
            ->assertOk()
            ->assertSee('Smart SIM')
            ->assertSee('class="page-head"', false)
            ->assertSee('class="nav-group open" id="networkGroup"', false)
            ->assertSee('href="'.url('/globe-sim').'"', false)
            ->assertSee('href="'.url('/smart-sim').'"', false)
            ->assertDontSee('href="'.url('/network').'"', false);
    }

    public function test_linked_assignment_port_syncs_to_sim_tabs_and_ignores_gateway_ip_edits(): void
    {
        $this->actingAs($this->user($this->adminType));

        $globe = GlobeSim::create([
            'imei' => '356938035651001',
            'mobile_number' => '09170005551',
            'plan' => 'Unli Surf',
            'network' => 'Globe',
            'ip_address' => '10.9.1.1',
            'port' => 12,
            'account_number' => 'ACC-PORT-SYNC',
            'contract_start' => '2026-03-01',
            'contract_end' => '2026-09-03',
            'status' => 'Active',
        ]);
        $create = $this->postJson('/gsm-gateways', [
            'hostname' => 'gsm-port-sync',
            'site_name' => 'Alcar',
            'site_code' => 'PORTSYNC1',
            'ip_address' => '10.9.1.1',
            'channel_count' => 16,
            'device_function' => 'Inbound',
            'username' => 'root',
        ])->assertCreated();
        $gatewayId = (int) $create->json('record.id');

        $this->postJson('/gsm-gateways/'.$gatewayId.'/assignments', [
            'network' => 'Globe SIM',
            'sim_id' => $globe->id,
        ])->assertCreated();

        $globe->refresh();
        $this->assertSame(1, (int) $globe->port);
        $this->assertSame('10.9.1.1', $globe->ip_address);
        $globePage = $this->actingAs($this->user($this->adminType))->get('/globe-sim')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/>\s*1\s*</', Str::betweenFirst($globePage, 'class="sim-table"', '</table>'));

        $gateway = MediaGateway::query()->findOrFail($gatewayId);
        GsmGatewayWriter::syncSimPort($gateway, 'globe', (int) $globe->id, 7);
        $this->assertSame(7, (int) $globe->fresh()->port);
        $globePage = $this->actingAs($this->user($this->adminType))->get('/globe-sim')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/>\s*7\s*</', Str::betweenFirst($globePage, 'class="sim-table"', '</table>'));

        $this->putJson('/gsm-gateways/'.$gatewayId, [
            'hostname' => 'gsm-port-sync-edit',
            'site_name' => 'Alcar',
            'site_code' => 'PORTSYNC1',
            'ip_address' => '10.9.1.9',
            'channel_count' => 16,
            'device_function' => 'Inbound',
            'network' => 'Globe SIM',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ])->assertOk();

        $globe->refresh();
        $this->assertSame(7, (int) $globe->port);
        $this->assertSame('10.9.1.9', $globe->ip_address);
    }
}
