<?php

namespace Tests\Feature;

use App\Models\ChannelAllocation;
use App\Models\ChannelAllocationCampaign;
use App\Models\DefectiveGsm;
use App\Models\GlobeSim;
use App\Models\MediaGateway;
use App\Models\ProgramInboundNumber;
use App\Models\SipChannel;
use App\Models\SmartSim;
use App\Models\User;
use App\Models\UserType;
use App\Services\DashboardOverviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
            ->assertSee('Updated ')
            ->assertDontSee('Last updated:')
            ->assertSee('data-dash-refresh', false)
            ->assertSee('Total Campaigns')
            ->assertSee('Total GSM Gateway')
            ->assertSee('Total SIP Channels')
            ->assertSee('Total SIMs')
            ->assertSee('Defective GSM')
            ->assertSee('Inbound Numbers')
            ->assertSee('>Globe</span>', false)
            ->assertSee('>Smart</span>', false)
            ->assertSee('>Open</span>', false)
            ->assertSee('>In Repair</span>', false)
            ->assertSee('>Mobile</span>', false)
            ->assertSee('>Landline</span>', false)
            ->assertDontSee('Total GSM Gateways')
            ->assertDontSee('Globe SIMs')
            ->assertDontSee('Smart SIMs')
            ->assertDontSee('Mobile Numbers')
            ->assertDontSee('Landline Numbers')
            ->assertDontSee('Total Inbound Numbers')
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
            ->assertSee('class="campaigns-name"', false)
            ->assertSee('<span class="campaigns-name">BPI Collection</span>', false)
            ->assertSee('<span class="campaigns-name">Atome</span>', false)
            ->assertDontSee('<span class="campaigns-name">Total</span>', false)
            ->assertSee('View All')
            ->assertSee('href="'.url('/channel-utilization').'"', false)
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
        $this->assertSame(6, substr_count($html, 'class="dash-kpi '));
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
            ->assertJsonPath('kpis.gateways.value', 240)
            ->assertJsonPath('kpis.channels.value', 500)
            ->assertJsonPath('kpis.sims.value', 3)
            ->assertJsonPath('kpis.globe.value', 2)
            ->assertJsonPath('kpis.smart.value', 1)
            ->assertJsonPath('kpis.defective.value', 1)
            ->assertJsonPath('kpis.defective_open.value', 1)
            ->assertJsonPath('kpis.defective_repair.value', 0)
            ->assertJsonPath('kpis.mobile.value', 3)
            ->assertJsonPath('kpis.landline.value', 2)
            ->assertJsonPath('kpis.inbound.value', 5)
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
            ->assertJsonPath('utilization_rows.1.sip', 0)
            ->assertJsonPath('utilization_rows.1.gsm', 220)
            ->assertJsonPath('campaign_insight', 'BPI Collection has the highest number of allocated channels with 300 channels.')
            ->assertJsonPath('trend.days', 30);

        $this->assertNotEmpty($json->json('fingerprint'));
        $this->assertNotEmpty($json->json('html.bars'));
        $this->assertNotEmpty($json->json('html.utilization'));
        $utilizationHtml = (string) $json->json('html.utilization');
        $this->assertStringContainsString('<span class="campaigns-name">BPI Collection</span>', $utilizationHtml);
        $this->assertStringContainsString('<span class="campaigns-name">Atome</span>', $utilizationHtml);
        $this->assertStringContainsString('<td>Total</td>', $utilizationHtml);
        $this->assertStringNotContainsString('campaigns-name">Total', $utilizationHtml);
        $this->assertStringContainsString('class="campaigns-name">${escapeHtml(row.name)}</span>', file_get_contents(resource_path('js/app.js')));
        $this->assertNotEmpty($json->json('html.trend'));
        $trendHtml = (string) $json->json('html.trend');
        $this->assertStringContainsString('fill="#000000"', $trendHtml);
        $this->assertStringContainsString('font-size="20"', $trendHtml);
        $this->assertStringContainsString('text-anchor="middle"', $trendHtml);
        $this->assertStringContainsString('stroke-width="4.5"', $trendHtml);
        $this->assertStringContainsString('class="dash-trend-date"', $trendHtml);
        $this->assertSame(8, substr_count($trendHtml, 'class="dash-trend-date"'));
        $this->assertStringContainsString('<circle', $trendHtml);
        $this->assertStringContainsString('Allocated Channels', $trendHtml);
        $this->assertArrayNotHasKey('insights', $json->json());
        $this->assertCount(30, $json->json('trend.labels'));
        $this->assertCount(30, $json->json('trend.total'));
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('.dash-bottom{display:grid;grid-template-columns:1.11fr 1fr;gap:13.75px;align-items:stretch;', $css);
        $this->assertStringContainsString('.dash-kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));column-gap:20px;row-gap:20px;align-items:stretch}', $css);

        $payload = app(DashboardOverviewService::class)->payload();
        $this->assertSame($payload['fingerprint'], $json->json('fingerprint'));
        $this->assertSame(220, $json->json('campaigns.1.total'));

        $this->actingAs($this->standard)->get('/dashboard')
            ->assertOk()
            ->assertSee('Standard User')
            ->assertSee('View All')
            ->assertSee('href="'.url('/channel-utilization').'"', false);

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
            'channel_allocation' => 'PAS-TREND',
            'media_gateway' => 'PAS-TREND',
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

    public function test_gsm_gateway_kpi_sums_channel_allocation_gsm_channel_counts(): void
    {
        $campaign = ChannelAllocationCampaign::create(['name' => 'GSM KPI']);
        ChannelAllocation::create([
            'campaign_id' => $campaign->id,
            'channel_allocation' => 'PAS-KPI-1',
            'media_gateway' => 'PAS-KPI-1',
            'network' => 'Globe SIM',
            'total_channel_allocated' => 32,
        ]);
        ChannelAllocation::create([
            'campaign_id' => $campaign->id,
            'channel_allocation' => 'PAS-KPI-2',
            'media_gateway' => 'PAS-KPI-2',
            'network' => 'Smart SIM',
            'total_channel_allocated' => 10,
        ]);
        ChannelAllocation::create([
            'campaign_id' => $campaign->id,
            'channel_allocation' => 'ETPI_SIP',
            'network' => 'Eastern SIP',
            'total_channel_allocated' => 8,
        ]);
        MediaGateway::create([
            'hostname' => 'pas-one',
            'site_name' => 'Estancia',
            'site_code' => 'PAS-KPI-1',
            'ip_address' => '10.28.240.31',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
            'port' => '99',
        ]);

        $payload = app(DashboardOverviewService::class)->payload();

        $this->assertSame(42, $payload['kpis']['gateways']['value']);
        $this->assertSame('42', $payload['kpis']['gateways']['display']);

        ChannelAllocation::query()->where('media_gateway', 'PAS-KPI-2')->delete();
        $campaign->refreshTotalChannelsAllocated();

        $afterDelete = app(DashboardOverviewService::class)->payload();
        $this->assertSame(32, $afterDelete['kpis']['gateways']['value']);
    }

    public function test_inbound_kpis_count_every_phone_number_not_database_rows(): void
    {
        $campaign = ChannelAllocationCampaign::create(['name' => 'Inbound Count']);

        ProgramInboundNumber::create([
            'number' => '09171234567',
            'program' => 'Inbound Count',
            'status' => 'Active',
            'campaign_id' => $campaign->id,
            'mobile_numbers' => ['09171234567', '09181234567'],
            'landline_numbers' => ['0281234567'],
        ]);
        ProgramInboundNumber::create([
            'number' => '09201234567',
            'program' => 'Inbound Count',
            'status' => 'Active',
            'campaign_id' => $campaign->id,
            'mobile_numbers' => ['09201234567'],
            'landline_numbers' => '0287654321; 0288889999',
        ]);

        $payload = app(DashboardOverviewService::class)->payload();
        $this->assertSame(3, $payload['kpis']['mobile']['value']);
        $this->assertSame(3, $payload['kpis']['landline']['value']);
        $this->assertSame(6, $payload['kpis']['inbound']['value']);
    }

    public function test_defective_gsm_kpi_counts_only_open_and_in_repair(): void
    {
        DefectiveGsm::create([
            'asset_code' => 'GSM-D-OPEN',
            'location' => 'Estancia',
            'issue' => 'No signal',
            'status' => 'Open',
        ]);
        DefectiveGsm::create([
            'asset_code' => 'GSM-D-REPAIR',
            'location' => 'Alcar',
            'issue' => 'Port failure',
            'status' => 'In Repair',
        ]);
        DefectiveGsm::create([
            'asset_code' => 'GSM-D-REPLACED',
            'location' => 'CTN',
            'issue' => 'Replaced unit',
            'status' => 'Replaced',
        ]);
        DefectiveGsm::create([
            'asset_code' => 'GSM-D-CLOSED',
            'location' => 'SC5',
            'issue' => 'Resolved',
            'status' => 'Closed',
        ]);

        $payload = app(DashboardOverviewService::class)->payload();
        $this->assertSame(2, $payload['kpis']['defective']['value']);
        $this->assertSame(1, $payload['kpis']['defective_open']['value']);
        $this->assertSame(1, $payload['kpis']['defective_repair']['value']);

        $page = $this->actingAs($this->admin)->get('/dashboard')->assertOk();
        $html = $page->getContent();
        $defectiveCard = Str::between(
            $html,
            'dash-kpi-label">Defective GSM</span>',
            'dash-kpi-label">Inbound Numbers</span>'
        );
        $this->assertStringContainsString('data-dash-kpi="defective">2</div>', $defectiveCard);
        $this->assertStringContainsString('>Open</span>', $defectiveCard);
        $this->assertStringContainsString('data-dash-kpi="defective_open">1</strong>', $defectiveCard);
        $this->assertStringContainsString('class="dash-sim-sep">|</span>', $defectiveCard);
        $this->assertStringContainsString('>In Repair</span>', $defectiveCard);
        $this->assertStringContainsString('data-dash-kpi="defective_repair">1</strong>', $defectiveCard);
        $this->assertStringNotContainsString('Replaced', $defectiveCard);
        $this->assertStringNotContainsString('Closed', $defectiveCard);

        $inboundCard = Str::after($html, 'dash-kpi-label">Inbound Numbers</span>');
        $this->assertStringContainsString('>Mobile</span>', $inboundCard);
        $this->assertStringContainsString('>Landline</span>', $inboundCard);
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
            'channel_allocation' => 'PAS-DASH',
            'media_gateway' => 'PAS-DASH',
            'network' => 'Globe SIM',
            'total_channel_allocated' => 20,
        ]);
        ChannelAllocation::create([
            'campaign_id' => $atome->id,
            'channel_allocation' => 'PAS-ATOME',
            'media_gateway' => 'PAS-ATOME',
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
            'port' => '32',
        ]);

        DefectiveGsm::create([
            'asset_code' => 'GSM-D-01',
            'location' => 'Estancia',
            'issue' => 'No signal',
            'status' => 'Open',
        ]);

        ProgramInboundNumber::create([
            'number' => '09171234567',
            'program' => 'BPI Collection',
            'status' => 'Active',
            'campaign_id' => $bpi->id,
            'mobile_numbers' => ['09171234567', '09181234567', '09191234567'],
            'landline_numbers' => ['0281234567', '0287654321'],
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
