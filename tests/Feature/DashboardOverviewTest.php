<?php

namespace Tests\Feature;

use App\Models\ChannelAllocation;
use App\Models\ChannelAllocationCampaign;
use App\Models\GlobeSim;
use App\Models\MediaGateway;
use App\Models\SipChannel;
use App\Models\SmartSim;
use App\Models\User;
use App\Models\UserType;
use App\Services\DashboardOverviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $standard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\UserTypeSeeder']);
        $this->admin = User::factory()->create([
            'user_type_id' => UserType::where('name', 'Administrator')->value('id'),
            'status' => 'Active',
        ]);
        $this->standard = User::factory()->create([
            'user_type_id' => UserType::where('name', 'Standard User')->value('id'),
            'status' => 'Active',
        ]);
    }

    public function test_dashboard_shows_live_kpis_charts_and_campaign_utilization_from_the_database(): void
    {
        $this->seedOverview();

        $page = $this->actingAs($this->admin)->get('/dashboard')->assertOk();
        $page->assertSee($this->admin->name)
            ->assertSee('Administrator • '.now()->format('F j, Y'))
            ->assertSee('Last updated:')
            ->assertSee('data-dash-refresh', false)
            ->assertSee('Total Campaigns')
            ->assertSee('Total GSM Gateways')
            ->assertSee('Total Channels')
            ->assertSee('Total SIMs')
            ->assertSee('>Globe</span>', false)
            ->assertSee('>Smart</span>', false)
            ->assertSee('Campaigns with Most Allocations')
            ->assertSee('Top campaigns based on total allocated channels.')
            ->assertSee('fill="#2563eb"', false)
            ->assertDontSee('fill="#0a192f"', false)
            ->assertSee('BPI Collection has the highest number of allocated channels with 300 channels.')
            ->assertDontSee('representing 57.7% of total allocations.')
            ->assertDontSee('Scroll down for more details')
            ->assertSee('Channel Utilization')
            ->assertSee('Breakdown of allocated channels by campaign and channel type.')
            ->assertSee('>Campaign</th>', false)
            ->assertSee('>Total Channels</th>', false)
            ->assertSee('>SIP</th>', false)
            ->assertSee('>GSM</th>', false)
            ->assertSee('>Total</td>', false)
            ->assertSee('280')
            ->assertSee('View All')
            ->assertSee('href="'.url('/reports').'?tab=channel-utilization"', false)
            ->assertSee('Allocation Trends')
            ->assertSee('Last 30 Days')
            ->assertSee('data-snapshot-url="'.url('/dashboard/snapshot').'"', false)
            ->assertDontSee('Quick Insights')
            ->assertDontSee('Recent System Activity')
            ->assertDontSee('Program Location Overview')
            ->assertDontSee('System Health')
            ->assertDontSee('Network Overview')
            ->assertDontSee('Export Data')
            ->assertDontSee('Overview of your campaigns, channels, and GSM resources.')
            ->assertDontSee('data-open="simInventoryModal"', false)
            ->assertDontSee('<a href="'.url('/gsm-gateways').'" class="dash-kpi', false);

        $html = $page->getContent();
        $this->assertSame(1, substr_count($html, 'id="simInventoryModal"'));
        $this->assertStringContainsString('data-dash-kpi="globe"', $html);
        $this->assertStringContainsString('data-dash-kpi="smart"', $html);
        $this->assertStringContainsString('BPI Collection', $html);
        $this->assertStringContainsString('Atome', $html);
    }

    public function test_snapshot_returns_the_same_live_values_and_is_available_to_standard_users(): void
    {
        $this->seedOverview();

        $this->getJson('/dashboard/snapshot')->assertUnauthorized();

        $json = $this->actingAs($this->admin)->getJson('/dashboard/snapshot')->assertOk();
        $json->assertJsonPath('kpis.campaigns.value', 2)
            ->assertJsonPath('kpis.gateways.value', 1)
            ->assertJsonPath('kpis.channels.value', 740)
            ->assertJsonPath('kpis.sims.value', 3)
            ->assertJsonPath('kpis.globe.value', 2)
            ->assertJsonPath('kpis.smart.value', 1)
            ->assertJsonPath('utilization.total', 740)
            ->assertJsonPath('utilization.allocated', 520)
            ->assertJsonPath('utilization.sip', 280)
            ->assertJsonPath('utilization.gsm', 240)
            ->assertJsonPath('campaigns.0.name', 'BPI Collection')
            ->assertJsonPath('campaigns.0.total', 300)
            ->assertJsonPath('utilization_rows.0.sip', 280)
            ->assertJsonPath('utilization_rows.0.gsm', 20)
            ->assertJsonPath('utilization_rows.1.name', 'Atome')
            ->assertJsonPath('utilization_rows.1.total', 220)
            ->assertJsonPath('campaign_insight', 'BPI Collection has the highest number of allocated channels with 300 channels.')
            ->assertJsonPath('trend.days', 30);

        $this->assertNotEmpty($json->json('fingerprint'));
        $this->assertNotEmpty($json->json('html.bars'));
        $this->assertNotEmpty($json->json('html.utilization'));
        $this->assertNotEmpty($json->json('html.trend'));
        $this->assertArrayNotHasKey('insights', $json->json());
        $this->assertCount(30, $json->json('trend.labels'));
        $this->assertCount(30, $json->json('trend.total'));

        $payload = app(DashboardOverviewService::class)->payload();
        $this->assertSame($payload['fingerprint'], $json->json('fingerprint'));
        $this->assertSame(220, $json->json('campaigns.1.total'));

        $this->actingAs($this->standard)->get('/dashboard')
            ->assertOk()
            ->assertSee('Standard User')
            ->assertDontSee('View All');

        $this->actingAs($this->standard)->getJson('/dashboard/snapshot')
            ->assertOk()
            ->assertJsonPath('kpis.campaigns.value', 2);

        ChannelAllocationCampaign::create(['name' => 'New Campaign']);
        $this->actingAs($this->admin)->getJson('/dashboard/snapshot')
            ->assertOk()
            ->assertJsonPath('kpis.campaigns.value', 3);
    }

    public function test_allocation_trend_uses_real_created_at_history(): void
    {
        $campaign = ChannelAllocationCampaign::create(['name' => 'Trend Campaign']);
        $recent = ChannelAllocation::create([
            'campaign_id' => $campaign->id,
            'channel_allocation' => 'ETPI_TREND',
            'network' => 'Eastern SIP',
            'total_channel_allocated' => 40,
        ]);
        $older = ChannelAllocation::create([
            'campaign_id' => $campaign->id,
            'channel_allocation' => 'gsm_globe_trend',
            'network' => 'Globe SIM',
            'total_channel_allocated' => 10,
        ]);
        $older->forceFill(['created_at' => now()->subDays(70)])->save();
        $recent->forceFill(['created_at' => now()->subDays(2)])->save();

        $payload = app(DashboardOverviewService::class)->payload();
        $this->assertSame(10, $payload['trend']['gsm'][0]);
        $this->assertSame(10, $payload['trend']['gsm'][29]);
        $this->assertSame(0, $payload['trend']['sip'][0]);
        $this->assertSame(40, $payload['trend']['sip'][29]);
        $this->assertSame(10, $payload['trend']['total'][0]);
        $this->assertSame(50, $payload['trend']['total'][29]);
    }

    private function seedOverview(): void
    {
        $bpi = ChannelAllocationCampaign::create(['name' => 'BPI Collection']);
        $atome = ChannelAllocationCampaign::create(['name' => 'Atome']);

        ChannelAllocation::create([
            'campaign_id' => $bpi->id,
            'channel_allocation' => 'ETPI_BPI_01',
            'network' => 'Eastern SIP',
            'total_channel_allocated' => 280,
        ]);
        ChannelAllocation::create([
            'campaign_id' => $bpi->id,
            'channel_allocation' => 'gsm_globe_bpi',
            'network' => 'Globe SIM',
            'total_channel_allocated' => 20,
        ]);
        ChannelAllocation::create([
            'campaign_id' => $atome->id,
            'channel_allocation' => 'gsm_globe_atome',
            'network' => 'Globe SIM',
            'total_channel_allocated' => 220,
        ]);

        SipChannel::create([
            'campaign_id' => $bpi->id,
            'etpi_sip_name' => 'ETPI_BPI',
            'channel_count' => 500,
        ]);

        MediaGateway::create([
            'site_name' => 'Estancia',
            'site_code' => 'PAS-DASH',
            'ip_address' => '10.28.240.21',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ]);

        GlobeSim::create([
            'imei' => '356938035643001',
            'mobile_number' => '09170000001',
            'location' => 'Estancia',
            'status' => 'Active',
        ]);
        GlobeSim::create([
            'imei' => '356938035643002',
            'mobile_number' => '09170000002',
            'location' => 'CTN',
            'status' => 'Active',
        ]);
        SmartSim::create([
            'imei' => '356938035643003',
            'mobile_number' => '09280000003',
            'location' => 'Alcar',
            'status' => 'Active',
        ]);
    }
}
