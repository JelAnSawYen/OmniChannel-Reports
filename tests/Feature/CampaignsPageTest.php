<?php

namespace Tests\Feature;

use App\Models\ChannelAllocationCampaign;
use App\Models\SipChannel;
use App\Models\SipChannelNumber;
use App\Models\User;
use App\Models\UserType;
use App\Services\XlsxService;
use App\Support\OperationCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CampaignsPageTest extends TestCase
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

    public function test_campaigns_is_the_first_manage_sidebar_item(): void
    {
        $this->actingAs($this->admin);
        $html = $this->get('/campaigns')->assertOk()->getContent();

        $manage = strpos($html, '>Manage</div>');
        $campaigns = strpos($html, '>Campaigns</span>');
        $pdc = strpos($html, '>PDC Servers</span>');

        $this->assertNotFalse($manage);
        $this->assertNotFalse($campaigns);
        $this->assertNotFalse($pdc);
        $this->assertGreaterThan($manage, $campaigns);
        $this->assertGreaterThan($campaigns, $pdc);
    }

    public function test_campaigns_page_supports_crud_search_and_location_dropdown(): void
    {
        $this->actingAs($this->admin);

        $page = $this->get('/campaigns')->assertOk();
        $html = $page->getContent();
        $page->assertSee('Campaigns')
            ->assertSee('class="campaigns-table"', false)
            ->assertSee('class="plus-btn"', false)
            ->assertSee('id="transferButton"', false)
            ->assertSee('id="campaign_location"', false)
            ->assertSee('>Site</th>', false)
            ->assertSee('for="campaign_location">Site</label>', false)
            ->assertSee('Select Site')
            ->assertDontSee('Hardcoded Location');

        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('.campaigns-name', $css);
        $this->assertMatchesRegularExpression('/\.campaigns-name\s*\{[^}]*color:\s*#0066FF/', $css);
        $this->assertMatchesRegularExpression('/\.campaigns-name\s*\{[^}]*font-weight:\s*700/', $css);
        $this->assertMatchesRegularExpression('/\.campaigns-table\s*>\s*tbody\s*>\s*tr\s*>\s*td\s*\{[^}]*padding:\s*10px 12.5px/', $css);
        $this->assertDoesNotMatchRegularExpression('/<th[^>]*>Last Updated<\/th>/', $html);

        $names = array_values(OperationCatalog::locations());
        $this->assertSame(['Alcar', 'CG3', 'CTN', 'Estancia', 'SC5', 'Skyrise', 'WFH'], $names);
        foreach ($names as $name) {
            $this->assertStringContainsString('>'.$name.'</option>', $html);
        }
        $this->assertStringNotContainsString('>PDC</option>', $html);
        $this->assertStringNotContainsString('>SCS</option>', $html);

        $this->post('/campaigns', [
            'name' => 'Atome',
            'fte' => 12,
            'location' => 'WFH',
        ])->assertRedirect();

        $this->assertDatabaseHas('channel_allocation_campaigns', [
            'name' => 'Atome',
            'fte' => 12,
            'location' => 'WFH',
        ]);

        $campaign = ChannelAllocationCampaign::where('name', 'Atome')->firstOrFail();

        $this->get('/campaigns')
            ->assertOk()
            ->assertSee('class="campaigns-name"', false)
            ->assertSee('Atome')
            ->assertSee('WFH')
            ->assertSee('>12<', false);

        $this->put('/campaigns/'.$campaign->id, [
            'name' => 'Atome Updated',
            'fte' => 8,
            'location' => 'Alcar',
        ])->assertRedirect();

        $this->assertDatabaseHas('channel_allocation_campaigns', [
            'id' => $campaign->id,
            'name' => 'Atome Updated',
            'fte' => 8,
            'location' => 'Alcar',
        ]);

        $this->get('/campaigns?search=Atome')
            ->assertOk()
            ->assertSee('Atome Updated')
            ->assertDontSee('No Campaigns Found');

        $this->delete('/campaigns/'.$campaign->id)->assertRedirect();
        $this->assertDatabaseMissing('channel_allocation_campaigns', ['id' => $campaign->id]);
    }

    public function test_deleting_a_campaign_still_uses_the_existing_campaign_delete_action(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create([
            'name' => 'Range Owner',
            'fte' => 2,
            'location' => 'WFH',
        ]);
        $this->post('/sip-channels', [
            'campaign_id' => $campaign->id,
            'etpi_sip_name' => 'SIP_RANGE_OWNER',
            'from' => '400',
            'to' => '402',
        ])->assertRedirect();
        $sip = SipChannel::where('etpi_sip_name', 'SIP_RANGE_OWNER')->firstOrFail();
        $this->assertSame(3, SipChannelNumber::where('sip_channel_id', $sip->id)->count());

        $html = $this->get('/campaigns')->assertOk()->getContent();
        $this->assertStringContainsString('action="'.url('/campaigns/'.$campaign->id).'"', $html);
        $this->assertStringContainsString('data-confirm-title="Delete Campaign"', $html);

        $this->delete('/campaigns/'.$campaign->id)->assertRedirect();
        $this->assertDatabaseMissing('channel_allocation_campaigns', ['id' => $campaign->id]);
        $this->assertDatabaseMissing('sip_channels', ['id' => $sip->id, 'etpi_sip_name' => 'SIP_RANGE_OWNER']);
        $this->assertSame(0, SipChannelNumber::where('sip_channel_id', $sip->id)->count());
    }

    public function test_campaigns_are_the_master_source_for_dropdowns_and_fte(): void
    {
        $this->actingAs($this->admin);

        $this->post('/campaigns', [
            'name' => 'Master Camp',
            'fte' => 9,
            'location' => 'WFH',
        ])->assertRedirect();

        $campaign = ChannelAllocationCampaign::where('name', 'Master Camp')->firstOrFail();
        $option = '>Master Camp</button>';

        $this->get('/sip-channels')->assertOk()->assertDontSee($option, false);
        $this->get('/pdc-servers')->assertOk()->assertSee($option, false);
        $this->get('/archive-recordings')->assertOk()->assertSee('Master Camp');

        $ca = $this->get('/channel-allocation')->assertOk();
        $caHtml = $ca->getContent();
        $this->assertStringContainsString('id="campaign_id"', $caHtml);
        $this->assertStringContainsString($option, $caHtml);
        $this->assertStringContainsString('data-fte="9"', $caHtml);
        $this->assertMatchesRegularExpression('/id="campaign_fte"[^>]*\breadonly\b/', $caHtml);
        $this->assertDoesNotMatchRegularExpression('/id="campaign_fte"[^>]*\bname="fte"/', $caHtml);
        $this->assertStringNotContainsString('data-label="FTE">9', $caHtml);
        $this->assertDoesNotMatchRegularExpression('/class="ca-campaign-link"[^>]*>Master Camp</', $caHtml);

        $this->put('/channel-allocation/'.$campaign->id, [
            'name' => 'Master Camp',
            'fte' => 99,
        ])->assertRedirect();

        $this->assertSame(9, $campaign->fresh()->fte);
        $this->assertSame(9, ChannelAllocationCampaign::optionsForDropdown()->firstWhere('id', $campaign->id)?->fte);
    }

    public function test_campaigns_rejects_invalid_and_unknown_locations(): void
    {
        $this->actingAs($this->admin);

        $this->post('/campaigns', [
            'name' => 'Bad',
            'fte' => 'abc',
            'location' => 'Moon Base',
        ])->assertSessionHasErrors(['fte', 'location']);

        $this->assertSame(0, ChannelAllocationCampaign::count());
    }

    public function test_standard_user_cannot_mutate_campaigns(): void
    {
        $campaign = ChannelAllocationCampaign::create([
            'name' => 'Locked',
            'fte' => 3,
            'location' => 'CTN',
        ]);

        $this->actingAs($this->standard);
        $this->get('/campaigns')
            ->assertOk()
            ->assertDontSee('class="plus-btn"', false)
            ->assertDontSee('action-btn edit', false);

        $this->post('/campaigns', [
            'name' => 'Hacked',
            'fte' => 1,
            'location' => 'WFH',
        ])->assertForbidden();

        $this->put('/campaigns/'.$campaign->id, [
            'name' => 'Hacked',
            'fte' => 1,
            'location' => 'WFH',
        ])->assertForbidden();

        $this->delete('/campaigns/'.$campaign->id)->assertForbidden();
        $this->assertDatabaseHas('channel_allocation_campaigns', ['id' => $campaign->id, 'name' => 'Locked']);
    }

    public function test_master_campaign_add_lists_a_pdc_created_name_without_duplicating(): void
    {
        $this->actingAs($this->admin);
        $this->post('/pdc-servers', [
            'campaign' => 'Campaign C',
            'location' => 'Estancia',
        ])->assertRedirect();
        $this->assertDatabaseMissing('channel_allocation_campaigns', ['name' => 'Campaign C']);
        $this->assertDatabaseHas('pdc_groups', ['campaign_name' => 'Campaign C']);
        $this->get('/campaigns')->assertOk()->assertDontSee('Campaign C');

        $this->from('/channel-allocation')->post('/channel-allocation', [
            'campaign' => 'Campaign C',
        ])->assertRedirect('/channel-allocation')->assertSessionHasErrors('campaign');

        $this->post('/campaigns', [
            'name' => 'Campaign C',
            'fte' => 3,
            'location' => 'WFH',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, ChannelAllocationCampaign::where('name', 'Campaign C')->count());
        $listed = ChannelAllocationCampaign::where('name', 'Campaign C')->firstOrFail();
        $this->assertTrue((bool) $listed->listed_in_campaigns);
        $this->assertSame(3, (int) $listed->fte);
        $this->assertSame('WFH', $listed->location);
        $this->get('/campaigns')->assertOk()->assertSee('Campaign C');

        $this->post('/channel-allocation', [
            'campaign' => 'Campaign C',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue((bool) $listed->fresh()->listed_in_channel_allocation);
    }

    public function test_campaign_data_transfer_uses_campaigns_fte_location(): void
    {
        $this->actingAs($this->admin);

        $template = $this->get('/campaigns/import/template')
            ->assertOk()
            ->assertDownload('campaigns-template.xlsx');
        [$headers] = app(XlsxService::class)->read($template->getFile()->getPathname());
        $this->assertSame(['Campaigns', 'FTE', 'Site'], $headers);

        $path = $this->spreadsheet([
            ['Campaigns', 'FTE', 'Site'],
            ['Mynt', '10', 'Moon Base'],
        ]);
        $preview = $this->postJson('/campaigns/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();

        $this->assertFalse($preview['valid']);
        $this->assertStringContainsString('Program Location', $preview['rows'][0]['error']);

        $validPath = $this->spreadsheet([
            ['Campaigns', 'FTE', 'Site'],
            ['Mynt', '10', 'WFH'],
            ['Chinabank', '4', 'alcar'],
        ]);
        $valid = $this->postJson('/campaigns/import/preview', [
            'file' => $this->upload($validPath),
        ])->assertOk()->json();
        $this->assertTrue($valid['valid'], $valid['rows'][1]['error'] ?? '');
        $this->assertSame('Alcar', $valid['rows'][1]['location']);
        $this->postJson('/campaigns/import/confirm', ['token' => $valid['token']])->assertOk();

        $this->assertSame(2, ChannelAllocationCampaign::count());
        $this->assertDatabaseHas('channel_allocation_campaigns', ['name' => 'Mynt', 'fte' => 10, 'location' => 'WFH']);
        $this->assertDatabaseHas('channel_allocation_campaigns', ['name' => 'Chinabank', 'fte' => 4, 'location' => 'Alcar']);

        $export = $this->get('/campaigns/export')->assertOk()->assertDownload('campaigns.xlsx');
        [$exportHeaders] = app(XlsxService::class)->read($export->getFile()->getPathname());
        $this->assertSame(['Campaigns', 'FTE', 'Site'], $exportHeaders);
        $this->assertNotContains('Id', $exportHeaders);
    }

    /**
     * @param  list<list<string|int>>  $rows
     */
    private function spreadsheet(array $rows): string
    {
        $headers = array_shift($rows);

        return app(XlsxService::class)->export($headers, $rows, 'campaigns-import.xlsx');
    }

    private function upload(string $path): UploadedFile
    {
        return new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
