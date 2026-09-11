<?php

namespace Tests\Feature;

use App\Models\MediaGateway;
use App\Models\User;
use App\Models\UserType;
use App\Services\XlsxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            '/program-location/scs' => 'SCS',
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
            ->assertSee('Hostname IP')
            ->assertSee('Serial Number')
            ->assertSee('>Plan<', false)
            ->assertSee('>Port<', false)
            ->assertSee('>Network<', false)
            ->assertSee('>Function<', false)
            ->assertSee('>Site<', false)
            ->assertSee('>User<', false)
            ->assertSee('>Password<', false)
            ->assertSee('pdc-secret-toggle', false)
            ->assertDontSee('>Id</span>', false)
            ->assertDontSee('>Media Gateways</span>', false)
            ->assertDontSee('>Users</span>', false)
            ->assertDontSee('>User Types</span>', false)
            ->assertDontSee('>Operations</div>', false);
    }

    public function test_operational_pages_use_gsm_gateway_layout(): void
    {
        $this->actingAs($this->user($this->adminType));

        $campaign = \App\Models\ChannelAllocationCampaign::create(['name' => 'BPI Collection']);
        $group = \App\Models\PdcGroup::create([
            'campaign_id' => $campaign->id,
            'location' => 'Estancia',
        ]);
        \App\Models\PdcServer::create([
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
            ->assertSee('Total GSM Gateways')
            ->assertSee('Total Channels')
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
            'site_name' => 'Alcar',
            'site_code' => 'ALC-'.$this->adminType->id,
            'ip_address' => '10.9.9.'.$this->adminType->id,
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
            ->assertDontSee('action-btn delete', false);

        $this->putJson('/gsm-gateways/'.$gateway->id, [
            'site_name' => 'Hacked',
            'site_code' => 'STD-GSM',
            'ip_address' => '10.8.8.8',
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

    public function test_sim_inventory_card_opens_a_selection_modal_for_globe_and_smart(): void
    {
        \App\Models\GlobeSim::create([
            'imei' => '356938035643111',
            'mobile_number' => '09170001111',
            'location' => 'Estancia',
            'status' => 'Active',
        ]);
        \App\Models\SmartSim::create([
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
            ->assertSee('id="networkToggle"', false)
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
}
