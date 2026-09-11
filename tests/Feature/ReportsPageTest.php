<?php

namespace Tests\Feature;

use App\Models\ChannelAllocation;
use App\Models\ChannelAllocationCampaign;
use App\Models\DefectiveGsm;
use App\Models\MediaGateway;
use App\Models\User;
use App\Models\UserType;
use App\Services\XlsxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsPageTest extends TestCase
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
            'name' => 'Mark Daza',
        ]);
        $this->standard = User::factory()->create([
            'user_type_id' => UserType::where('name', 'Standard User')->value('id'),
            'status' => 'Active',
        ]);
    }

    public function test_administrator_profile_settings_contains_admin_links(): void
    {
        $html = $this->actingAs($this->admin)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringNotContainsString('>Administration</div>', $html);
        $this->assertStringContainsString('id="accountSettingsToggle"', $html);
        $this->assertStringContainsString('User Management', $html);
        $this->assertStringContainsString('Login History', $html);
        $this->assertStringContainsString('Audit Logs', $html);
        $this->assertStringContainsString('Reports', $html);

        preg_match('/id="accountMenu"(.*)id="logoutButton"/s', $html, $menu);
        $this->assertNotEmpty($menu);
        $this->assertStringContainsString('My Profile', $menu[1]);
        $this->assertStringContainsString('Settings', $menu[1]);
        $this->assertStringContainsString('User Management', $menu[1]);
        $this->assertStringContainsString('Login History', $menu[1]);
    }

    public function test_standard_user_does_not_see_administration_or_reports(): void
    {
        $this->actingAs($this->standard)
            ->get('/dashboard')
            ->assertOk()
            ->assertDontSee('>Administration</div>', false)
            ->assertDontSee('User Management')
            ->assertDontSee('Audit Logs');

        $this->actingAs($this->standard)->get('/reports')->assertForbidden();
    }

    public function test_campaign_allocation_report_uses_live_data_and_dynamic_insight(): void
    {
        $this->assertSame('SIP', \App\Support\ChannelTypeClassifier::classify('ETPI', 'ETPI_53235320'));
        $this->assertSame('GSM', \App\Support\ChannelTypeClassifier::classify('Globe SIM', 'gsm_globe_bpi'));

        $this->seedAllocations();
        $this->actingAs($this->admin);

        $page = $this->get('/reports')->assertOk();
        $page->assertSee('Campaign Allocation Report')
            ->assertSee('Campaign Allocation')
            ->assertSee('Channel Utilization')
            ->assertSee('GSM Gateway')
            ->assertSee('Defective GSM')
            ->assertSee('Campaign Summary')
            ->assertSee('Filters')
            ->assertSee('Excel (.xlsx)')
            ->assertSee('PDF (.pdf)')
            ->assertSee('CSV (.csv)')
            ->assertSee('BPI Collection')
            ->assertSee('GCash Loans')
            ->assertSee('TOTAL')
            ->assertSee('450')
            ->assertSee('BPI Collection has the highest number of allocated channels with 300 channels, representing 66.7% of total allocations.')
            ->assertSee('min="'.\App\Support\PdcEndorseDate::MIN_DATE.'"', false)
            ->assertDontSee('class="rpt-crumb"', false)
            ->assertDontSee('Home / Reports')
            ->assertDontSee('Operations Reports')
            ->assertDontSee('Inventory Snapshot');

        $filtered = $this->get('/reports?campaign_id='.ChannelAllocationCampaign::where('name', 'GCash Loans')->value('id'))
            ->assertOk();
        $filtered->assertSee('GCash Loans has the highest number of allocated channels with 150 channels, representing 100.0% of total allocations.')
            ->assertDontSee('BPI Collection has the highest');
    }

    public function test_all_report_tabs_render_with_real_records(): void
    {
        $this->seedAllocations();
        $gateway = MediaGateway::create([
            'site_name' => 'Estancia',
            'site_code' => 'EST-1',
            'ip_address' => '10.24.28.91',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ]);
        DefectiveGsm::create([
            'asset_code' => 'GSM-DEF-01',
            'location' => 'Estancia',
            'issue' => 'No signal',
            'reported_on' => now()->toDateString(),
            'status' => 'Open',
        ]);

        $this->actingAs($this->admin);

        $this->get('/reports?tab=channel-utilization')
            ->assertOk()
            ->assertSee('Channel Utilization Report')
            ->assertSee('ETPI_BPI_01')
            ->assertSee('Allocated Channels');

        $this->get('/reports?tab=channel-allocation')
            ->assertOk()
            ->assertSee('Channel Allocation Report')
            ->assertSee('ETPI_BPI_01')
            ->assertSee('gsm_globe_bpi')
            ->assertSee('Allocated By')
            ->assertSee('>Status</th>', false);

        $this->get('/reports?tab=gsm-gateway')
            ->assertOk()
            ->assertSee('GSM Gateway Report')
            ->assertSee('Estancia')
            ->assertSee($gateway->site_code)
            ->assertSee('Identifier')
            ->assertSee('Assigned Channels');

        $this->get('/reports?tab=defective-gsm')
            ->assertOk()
            ->assertSee('Defective GSM Report')
            ->assertSee('GSM-DEF-01')
            ->assertSee('No signal');

        $this->get('/reports?tab=campaign-summary')
            ->assertOk()
            ->assertSee('Campaign Summary Report')
            ->assertSee('BPI Collection')
            ->assertSee('GCash Loans')
            ->assertSee('>Status</th>', false);
    }

    public function test_pagination_and_exports_use_applied_filters(): void
    {
        $this->actingAs($this->admin);
        for ($index = 1; $index <= 12; $index++) {
            $campaign = ChannelAllocationCampaign::create(['name' => 'Campaign '.$index]);
            ChannelAllocation::create([
                'campaign_id' => $campaign->id,
                'channel_allocation' => 'CH-'.$index,
                'network' => 'Eastern SIP',
                'total_channel_allocated' => $index,
            ]);
        }

        $this->get('/reports?per_page=5')
            ->assertOk()
            ->assertSee('Campaign 12')
            ->assertSee('Showing 1 to 5 of 12 entries');

        $this->get('/reports?per_page=5&page=2')
            ->assertOk()
            ->assertSee('Campaign 7')
            ->assertSee('Showing 6 to 10 of 12 entries');

        $bpi = ChannelAllocationCampaign::create(['name' => 'Filter Target']);
        ChannelAllocation::create([
            'campaign_id' => $bpi->id,
            'channel_allocation' => 'ETPI_FILTER',
            'network' => 'Eastern SIP',
            'total_channel_allocated' => 40,
        ]);

        $csv = $this->get('/reports/export/csv?tab=campaign-allocation&campaign_id='.$bpi->id)
            ->assertOk();
        $csv->assertHeader('content-disposition');
        $body = $csv->streamedContent();
        $this->assertStringContainsString('Filter Target', $body);
        $this->assertStringContainsString('Campaign', $body);
        $this->assertStringNotContainsString('Campaign 12', $body);

        $xlsx = $this->get('/reports/export/xlsx?tab=campaign-allocation&campaign_id='.$bpi->id)
            ->assertOk();
        $file = $xlsx->getFile();
        $this->assertNotNull($file);
        $contents = (string) file_get_contents($file->getPathname());
        $this->assertStringContainsString('PK', substr($contents, 0, 2));
        [$headers] = app(XlsxService::class)->read($file->getPathname());
        $this->assertNotEmpty($headers);

        $pdf = $this->get('/reports/export/pdf?tab=campaign-allocation&campaign_id='.$bpi->id)
            ->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    private function seedAllocations(): void
    {
        $bpi = ChannelAllocationCampaign::create(['name' => 'BPI Collection']);
        $gcash = ChannelAllocationCampaign::create(['name' => 'GCash Loans']);

        ChannelAllocation::create([
            'campaign_id' => $bpi->id,
            'media_gateway' => '10.24.28.91',
            'channel_allocation' => 'ETPI_BPI_01',
            'network' => 'Eastern SIP',
            'total_channel_allocated' => 280,
        ]);
        ChannelAllocation::create([
            'campaign_id' => $bpi->id,
            'media_gateway' => '10.24.28.91',
            'channel_allocation' => 'gsm_globe_bpi',
            'network' => 'Globe SIM',
            'total_channel_allocated' => 20,
        ]);
        ChannelAllocation::create([
            'campaign_id' => $gcash->id,
            'media_gateway' => '10.24.28.83',
            'channel_allocation' => 'ETPI_GCASH',
            'network' => 'Eastern SIP',
            'total_channel_allocated' => 150,
        ]);
    }
}
