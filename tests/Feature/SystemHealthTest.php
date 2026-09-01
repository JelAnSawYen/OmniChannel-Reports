<?php

namespace Tests\Feature;

use App\Models\ChannelPort;
use App\Models\DefectiveGsm;
use App\Models\GlobeSim;
use App\Models\PdcServer;
use App\Models\User;
use App\Models\UserType;
use App\Services\SystemHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemHealthTest extends TestCase
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

    public function test_dashboard_health_uses_live_equipment_status_not_a_fixed_score(): void
    {
        $this->seedHealthRecords();

        $page = $this->actingAs($this->admin)->get('/dashboard')->assertOk();

        $page->assertSee('System Health')
            ->assertSee('44%')
            ->assertSee('data-tier="Critical"', false)
            ->assertSee('Excellent')
            ->assertSee('1 (20%)')
            ->assertSee('Good')
            ->assertSee('2 (40%)')
            ->assertDontSee('90%')
            ->assertSee('/system-health', false)
            ->assertSee('status=critical', false)
            ->assertSee('View detailed Health');

        PdcServer::where('hostname', 'pdc-offline-01')->update(['status' => 'Active']);

        $refreshed = $this->actingAs($this->admin)->get('/dashboard')->assertOk();
        $refreshed->assertSee('64%')
            ->assertDontSee('44%')
            ->assertSee('2 (40%)')
            ->assertSee('1 (20%)');
    }

    public function test_detailed_health_page_lists_modules_with_live_scores(): void
    {
        $this->seedHealthRecords();

        $this->actingAs($this->admin)->get('/system-health')
            ->assertOk()
            ->assertSee('System Health')
            ->assertSee('Overview of the current system status across all equipment and modules.')
            ->assertSee('Live Score:')
            ->assertSee('44% Critical')
            ->assertSee('Based on 5 equipment and module records')
            ->assertSee('Health by Module')
            ->assertSee('Search module...')
            ->assertSee('PDC Servers')
            ->assertSee('Defective GSM')
            ->assertSee('Globe SIM')
            ->assertSee('Channel Port')
            ->assertSee('View Details')
            ->assertSee('Systems operating normally')
            ->assertSee('Minor issues detected')
            ->assertSee('Requires attention')
            ->assertSee('Immediate action required')
            ->assertSee('Health score is calculated based on the status of all equipment and modules in the system.')
            ->assertSee('Last updated:')
            ->assertSee('10 per page')
            ->assertSee('class="health-view-btn" href="'.url('/pdc-servers').'"', false)
            ->assertSee('class="health-view-btn" href="'.url('/defective-gsm').'"', false)
            ->assertSee('class="health-view-btn" href="'.url('/globe-sim').'"', false)
            ->assertSee('class="health-view-btn" href="'.url('/channel-port').'"', false)
            ->assertDontSee('Back to Dashboard')
            ->assertDontSee('pdc-core-01')
            ->assertDontSee('search=GSM-HEALTH-1', false)
            ->assertDontSee('Port 8');

        $critical = $this->actingAs($this->admin)->get('/system-health?status=critical')->assertOk();
        $critical->assertSee('Defective GSM')
            ->assertSee('Globe SIM')
            ->assertSee('class="health-view-btn" href="'.url('/defective-gsm').'"', false)
            ->assertSee('class="health-view-btn" href="'.url('/globe-sim').'"', false)
            ->assertDontSee('class="health-view-btn" href="'.url('/pdc-servers').'"', false)
            ->assertDontSee('class="health-view-btn" href="'.url('/channel-port').'"', false);

        $excellent = $this->actingAs($this->admin)->get('/system-health?status=excellent')->assertOk();
        $excellent->assertSee('No modules in this health status.')
            ->assertDontSee('class="health-view-btn" href="'.url('/pdc-servers').'"', false)
            ->assertDontSee('class="health-view-btn" href="'.url('/defective-gsm').'"', false);

        $search = $this->actingAs($this->admin)->get('/system-health?search=PDC')->assertOk();
        $search->assertSee('PDC Servers')
            ->assertSee('class="health-view-btn" href="'.url('/pdc-servers').'"', false)
            ->assertDontSee('class="health-view-btn" href="'.url('/globe-sim').'"', false)
            ->assertDontSee('class="health-view-btn" href="'.url('/channel-port').'"', false);
    }

    public function test_standard_user_can_open_system_health_and_guests_cannot(): void
    {
        $standard = User::factory()->create([
            'user_type_id' => UserType::where('name', 'Standard User')->value('id'),
            'status' => 'Active',
        ]);

        $this->actingAs($standard)->get('/system-health')->assertOk();
    }

    public function test_guests_cannot_open_system_health(): void
    {
        $this->get('/system-health')->assertRedirect('/login');
    }

    public function test_health_score_is_weighted_from_real_status_counts(): void
    {
        $service = app(SystemHealthService::class);

        $this->assertSame('critical', $service->tier('Offline'));
        $this->assertSame('warning', $service->tier('Inactive'));
        $this->assertSame('good', $service->tier('Available'));
        $this->assertSame('excellent', $service->tier('Active'));

        $this->assertSame(53, $service->score([
            'excellent' => 2,
            'good' => 1,
            'warning' => 1,
            'critical' => 2,
        ], 6));
    }

    private function seedHealthRecords(): void
    {
        PdcServer::create([
            'hostname' => 'pdc-core-01',
            'ip_address' => '10.10.10.10',
            'location' => 'Estancia',
            'role' => 'Primary',
            'status' => 'Active',
        ]);
        PdcServer::create([
            'hostname' => 'pdc-offline-01',
            'ip_address' => '10.10.10.11',
            'location' => 'Alcar',
            'role' => 'Backup',
            'status' => 'Offline',
        ]);
        DefectiveGsm::create([
            'asset_code' => 'GSM-HEALTH-1',
            'location' => 'Alcar',
            'issue' => 'No signal',
            'status' => 'Open',
        ]);
        GlobeSim::create([
            'sim_number' => '09170009991',
            'imsi' => '51502000009991',
            'location' => 'CTN',
            'status' => 'Inactive',
        ]);
        ChannelPort::create([
            'port_number' => 8,
            'gateway' => 'GW-1',
            'channel' => 'SIP',
            'status' => 'Available',
        ]);
    }
}
