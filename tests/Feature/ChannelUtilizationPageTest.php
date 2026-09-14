<?php

namespace Tests\Feature;

use App\Models\ChannelAllocation;
use App\Models\ChannelAllocationCampaign;
use App\Models\User;
use App\Models\UserType;
use App\Services\DashboardOverviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelUtilizationPageTest extends TestCase
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

    public function test_administrator_and_standard_user_can_open_channel_utilization(): void
    {
        $this->seedUtilization();

        $this->actingAs($this->admin)->get('/channel-utilization')
            ->assertOk()
            ->assertSee('<h1 class="page-title">Channel Utilization</h1>', false)
            ->assertSee('>Campaign</th>', false)
            ->assertSee('>Total Channels</th>', false)
            ->assertSee('>SIP</th>', false)
            ->assertSee('>GSM</th>', false)
            ->assertSee('BPI Collection')
            ->assertSee('Atome')
            ->assertSee('280')
            ->assertSee('<title>Channel Utilization</title>', false)
            ->assertDontSee('>Reports</', false);

        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('.dash-kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));column-gap:16px;row-gap:16px;align-items:stretch}', $css);
        $this->assertStringContainsString('.dash-util-table th.num,.dash-util-table td.num{text-align:center}', $css);
        $this->assertStringContainsString('table[aria-label="Channel Utilization"] th.num-col', $css);
        $this->assertStringContainsString('table[aria-label="Channel Utilization"] td.num-col{text-align:center}', $css);

        $this->actingAs($this->standard)->get('/channel-utilization')
            ->assertOk()
            ->assertSee('<h1 class="page-title">Channel Utilization</h1>', false)
            ->assertSee('BPI Collection');
    }

    public function test_channel_utilization_values_match_dashboard_payload(): void
    {
        $this->seedUtilization();

        $rows = app(DashboardOverviewService::class)->payload()['utilization_rows'];
        $this->assertSame('BPI Collection', $rows[0]['name']);
        $this->assertSame(300, $rows[0]['total']);
        $this->assertSame(280, $rows[0]['sip']);
        $this->assertSame(20, $rows[0]['gsm']);
        $this->assertSame('Atome', $rows[1]['name']);
        $this->assertSame(220, $rows[1]['total']);

        $page = $this->actingAs($this->admin)->get('/channel-utilization')->assertOk();
        $html = $page->getContent();
        $this->assertStringContainsString('BPI Collection', $html);
        $this->assertStringContainsString($rows[0]['total_display'], $html);
        $this->assertStringContainsString($rows[0]['sip_display'], $html);
        $this->assertStringContainsString($rows[0]['gsm_display'], $html);
        $this->assertStringContainsString($rows[1]['total_display'], $html);
        $this->assertStringContainsString('>Total</td>', $html);
        $this->assertStringContainsString(number_format(520), $html);
        $this->assertStringContainsString(number_format(280), $html);
        $this->assertStringContainsString(number_format(240), $html);
    }

    public function test_channel_utilization_search_filters_campaigns(): void
    {
        $this->seedUtilization();

        $this->actingAs($this->admin)->get('/channel-utilization?search=BPI')
            ->assertOk()
            ->assertSee('BPI Collection')
            ->assertDontSee('Atome')
            ->assertSee('placeholder="Search campaign..."', false);
    }

    public function test_reports_routes_are_removed(): void
    {
        $this->actingAs($this->admin)->get('/reports')->assertNotFound();
        $this->actingAs($this->admin)->get('/reports/export/xlsx')->assertNotFound();
        $this->actingAs($this->standard)->get('/reports')->assertNotFound();
        $this->actingAs($this->standard)->get('/reports/export/xlsx')->assertNotFound();
    }

    public function test_dashboard_view_all_opens_channel_utilization_for_admin_and_standard_user(): void
    {
        $this->seedUtilization();

        $this->actingAs($this->admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('href="'.url('/channel-utilization').'"', false)
            ->assertDontSee('href="'.url('/reports').'?tab=channel-utilization"', false)
            ->assertDontSee('href="'.url('/reports').'"', false);

        $this->actingAs($this->standard)->get('/dashboard')
            ->assertOk()
            ->assertSee('View All')
            ->assertSee('href="'.url('/channel-utilization').'"', false);
    }

    private function seedUtilization(): void
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
    }
}
