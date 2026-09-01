<?php

namespace Tests\Feature;

use App\Models\MediaGateway;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarDashboardAndGsmAccessTest extends TestCase
{
    use RefreshDatabase;

    private UserType $systemType;
    private UserType $adminType;
    private UserType $standardType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\UserTypeSeeder']);
        $this->systemType = UserType::where('name', 'System Administrator')->firstOrFail();
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
        $this->actingAs($this->user($this->systemType));

        MediaGateway::create([
            'site_name' => 'Estancia',
            'site_code' => 'PAS202',
            'ip_address' => '10.28.240.202',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ]);

        $pages = [
            '/dashboard' => 'Network Overview',
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
            '/program-location/estancia' => 'Estancia',
            '/program-location/skyrise' => 'Skyrise',
        ];

        foreach ($pages as $url => $text) {
            $this->get($url)->assertOk()->assertSee($text);
        }

        $this->get('/program-location/estancia')
            ->assertOk()
            ->assertSee('Estancia')
            ->assertSee('id="programLocationGroup"', false)
            ->assertSee('nav-group open', false)
            ->assertSee('nav-item nav-subitem active', false)
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
            ->assertDontSee('>Media Gateways</span>', false)
            ->assertDontSee('>Users</span>', false)
            ->assertDontSee('>User Types</span>', false)
            ->assertDontSee('>Operations</div>', false);
    }

    public function test_operational_pages_use_gsm_gateway_layout(): void
    {
        $this->actingAs($this->user($this->systemType));

        \App\Models\PdcServer::create([
            'hostname' => 'pdc-core-01',
            'ip_address' => '10.10.10.10',
            'location' => 'Estancia',
            'role' => 'Primary',
            'status' => 'Active',
        ]);

        $pages = [
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
            $this->get($url)
                ->assertOk()
                ->assertSee('class="page-head"', false)
                ->assertSee('class="search-box', false)
                ->assertSee('class="table-card table-wrap"', false)
                ->assertSee('class="table-footer"', false);
        }

        $this->get('/pdc-servers')
            ->assertOk()
            ->assertSee('Export Data', false)
            ->assertSee('class="plus-btn"', false)
            ->assertSee('id="transferButton"', false)
            ->assertSee('class="actions-column"', false)
            ->assertSee('class="row-actions"', false)
            ->assertSee('Hostname')
            ->assertDontSee('filter-row', false);
    }

    public function test_system_admin_and_admin_see_administration_and_can_manage_gsm(): void
    {
        foreach ([$this->systemType, $this->adminType] as $type) {
            $this->actingAs($this->user($type));

            $this->get('/dashboard')
                ->assertOk()
                ->assertDontSee('Administration')
                ->assertSee('id="accountUserManagement"', false)
                ->assertSee('User Management')
                ->assertSee('>Users</a>', false)
                ->assertSee('>User Types</a>', false)
                ->assertSee('Recent System Activity')
                ->assertSee('Program Location Overview')
                ->assertSee('System Health')
                ->assertSee('Network Overview')
                ->assertSee('background:#dc2626', false)
                ->assertSee('background:#92400e', false)
                ->assertDontSee('stroke="#eab308"', false)
                ->assertDontSee('Quick Actions')
                ->assertSee('My Profile')
                ->assertSee('Login History')
                ->assertSee('id="notificationButton"', false)
                ->assertSee('id="accountButton"', false)
                ->assertDontSee('Activity Logs')
                ->assertDontSee('Recent Login Activity');

            $this->get('/users')->assertOk();
            $this->get('/user-types')->assertOk();

            $this->postJson('/gsm-gateways', [
                'site_name' => 'Alcar',
                'site_code' => 'ALC-'.$type->id,
                'ip_address' => '10.9.9.'.$type->id,
                'username' => 'root',
                'database' => 'asteriskcdrdb',
            ])->assertCreated();
        }
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
        $this->get('/user-types')->assertForbidden();
        $this->get('/user-types/1/edit')->assertForbidden();

        $this->assertDatabaseHas('media_gateways', [
            'id' => $gateway->id,
            'site_name' => 'Estancia',
        ]);
    }

    public function test_sim_inventory_card_opens_a_selection_modal_for_globe_and_smart(): void
    {
        \App\Models\GlobeSim::create([
            'sim_number' => '09170001111',
            'imsi' => '51502000001111',
            'location' => 'Estancia',
            'status' => 'Active',
        ]);
        \App\Models\SmartSim::create([
            'sim_number' => '09280002222',
            'imsi' => '51503000002222',
            'location' => 'CTN',
            'status' => 'Active',
        ]);

        $page = $this->actingAs($this->user($this->adminType))->get('/dashboard')->assertOk();

        $page->assertSee('SIM Inventory')
            ->assertSee('1 Globe / 1 Smart')
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
            ->assertSee('class="page-head"', false);

        $this->actingAs($this->user($this->adminType))->get('/smart-sim')
            ->assertOk()
            ->assertSee('Smart SIM')
            ->assertSee('class="page-head"', false);
    }
}
