<?php

namespace Tests\Feature;

use App\Models\ChannelAllocation;
use App\Models\ChannelAllocationCampaign;
use App\Models\MediaGateway;
use App\Models\SipChannel;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChannelAllocationPageTest extends TestCase
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

    public function test_excel_seed_groups_campaigns_and_keeps_allocation_counts(): void
    {
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChannelAllocationSeeder']);

        $this->assertSame(24, ChannelAllocationCampaign::count());
        $this->assertSame(160, ChannelAllocation::count());

        $atome = ChannelAllocationCampaign::where('name', 'Atome')->firstOrFail();
        $this->assertSame(2, $atome->allocations()->count());
        $this->assertSame(52, $atome->total_channels_allocated);
        $this->assertSame(5, $atome->fte);
        $this->assertSame('N/A', $atome->caller_id);
        $this->assertSame('633', $atome->prefix);
        $this->assertTrue($atome->allocations()->where('channel_allocation', 'gsm_globe_est145')->exists());
        $this->assertTrue($atome->allocations()->where('channel_allocation', 'gsm_globe_phq101')->exists());

        $mynt = ChannelAllocationCampaign::where('name', 'Mynt')->firstOrFail();
        $this->assertSame(49, $mynt->allocations()->count());
        $this->assertSame(1195, $mynt->total_channels_allocated);
        $this->assertSame(1565, (int) $mynt->allocations()->sum('total_channel_allocated'));

        $sagad = ChannelAllocationCampaign::where('name', 'Mynt Sagad')->firstOrFail();
        $this->assertSame(0, $sagad->allocations()->count());
        $this->assertSame(370, $sagad->total_channels_allocated);

        $mcc = ChannelAllocationCampaign::where('name', 'MCCAcqui')->firstOrFail();
        $this->assertSame(3, $mcc->allocations()->count());
        $this->assertSame([1, 2, 4], $mcc->allocations()->orderBy('sort_order')->pluck('line_priority')->all());
    }

    public function test_page_layout_search_export_and_sidebar(): void
    {
        $this->actingAs($this->admin);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChannelAllocationSeeder']);

        $page = $this->get('/channel-allocation')->assertOk();
        $html = $page->getContent();

        $page->assertSee('Channel Allocation')
            ->assertSee('Manage channel allocations by campaign.')
            ->assertSee('Search Channels')
            ->assertSee('Data Transfer')
            ->assertSee('Import Data')
            ->assertSee('Export Data')
            ->assertSee('id="importDataButton"', false)
            ->assertSee('id="exportDataButton"', false)
            ->assertDontSee('Download Sample Template')
            ->assertSee('Download Excel Template')
            ->assertSee('id="importUploadError"', false)
            ->assertSee('Preview &amp; Validate', false)
            ->assertSee('class="plus-btn"', false)
            ->assertDontSee('id="channelAllocationReset"', false)
            ->assertSee('Atome')
            ->assertSee('class="ca-count">2 allocations', false)
            ->assertDontSee('data-label="Allocations"', false)
            ->assertSee('gsm_globe_est145')
            ->assertSee('data-ca-toggle', false)
            ->assertSee('class="ca-menu-btn"', false)
            ->assertSee('class="ca-menu-item edit"', false)
            ->assertSee('class="ca-menu-item delete"', false)
            ->assertSee('>Channel</th>', false)
            ->assertDontSee('>SIP Channel</th>', false)
            ->assertDontSee('>GSM Gateway</th>', false)
            ->assertSee('>Channel Count</th>', false)
            ->assertDontSee('>Total Channel Allocated</th>', false)
            ->assertSee('id="alloc_channel"', false)
            ->assertSee('id="alloc_channel_type"', false)
            ->assertSee('for="alloc_channel_type">Channel Type', false)
            ->assertSee('Select Channel Type')
            ->assertSee('name="channel"', false)
            ->assertDontSee('id="alloc_channel_value"', false)
            ->assertSee('id="alloc_channel_count"', false)
            ->assertDontSee('id="alloc_channel_allocation"', false)
            ->assertDontSee('id="alloc_media_gateway"', false)
            ->assertDontSee('id="campaign_channel_allocation"', false)
            ->assertDontSee('id="campaign_alloc_media_gateway"', false)
            ->assertDontSee('id="campaign_media_gateway"', false)
            ->assertDontSee('for="campaign_channel_allocation">SIP Channel', false)
            ->assertDontSee('for="campaign_alloc_media_gateway">GSM Gateway', false)
            ->assertSee('id="alloc_network"', false)
            ->assertSee('readonly', false)
            ->assertSee('Total Channels')
            ->assertDontSee('id="campaign_total_channels_allocated"', false)
            ->assertDontSee('Total Channels Allocated')
            ->assertSee('data-confirm-title="Delete Allocations"', false)
            ->assertSee('data-confirm-title="Delete Allocation"', false)
            ->assertSee('campaigns')
            ->assertDontSee('All Status')
            ->assertDontSee('All Networks')
            ->assertDontSee('All Priorities')
            ->assertDontSee('Add Channel Allocation');

        $this->assertTrue(str_contains($html, '>SIP Channels</span>') && str_contains($html, '>Channel Allocation</span></a>'));
        $this->assertTrue(strpos($html, '>SIP Channels</span>') < strpos($html, '>Channel Allocation</span></a>'));
        $this->assertTrue(strpos($html, '>Channel Allocation</span></a>') < strpos($html, '>Archive Recordings</span></a>'));
        $this->assertStringContainsString('class="import-upload-error"', $html);
        $this->assertMatchesRegularExpression('/id="campaign_fte"[^>]*\breadonly\b/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="campaign_fte"[^>]*\bname="fte"/', $html);
        $this->assertStringContainsString('id="campaign_id"', $html);
        $this->assertStringContainsString('Select or type a campaign...', $html);
        $this->assertStringContainsString('data-fte="5"', $html);
        $this->assertStringContainsString('>Atome</button>', $html);
        $mainHead = Str::between($html, 'aria-label="Channel Allocation"', '</thead>');
        $this->assertStringNotContainsString('>Allocations</th>', $mainHead);
        $this->assertStringContainsString('>Total Channels</th>', $mainHead);
        $this->assertStringContainsString('>FTE</th>', $mainHead);
        $this->assertStringContainsString('>Caller ID</th>', $mainHead);
        $this->assertStringContainsString('>Prefix</th>', $mainHead);
        $this->assertStringContainsString('>Remarks</th>', $mainHead);
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertMatchesRegularExpression('/\.modal-backdrop\s*\{[^}]*visibility:\s*hidden/', $css);
        $this->assertMatchesRegularExpression('/\.ca-menu-dropdown \{\s*position: absolute;\s*right: 0;\s*left: auto;\s*top: calc\(100% \+ 3\.75px\);/', $css);
        $this->assertStringContainsString('body[data-page="channel-allocation"] .table-card', $css);
        $this->assertStringContainsString('#allocationModal select.form-control', $css);
        $this->assertStringNotContainsString('#allocationModal[data-mode="edit"] [data-alloc-channel-type]', $css);
        $this->assertMatchesRegularExpression('/body\[data-page="channel-allocation"\] \.ca-table \{\s*table-layout: fixed;\s*width: 100%;/', $css);
        $this->assertMatchesRegularExpression('/body\[data-page="channel-allocation"\] \.ca-table > thead > tr > th:first-child,\s*body\[data-page="channel-allocation"\] \.ca-table \.ca-campaign-row > td:first-child \{\s*width: 22%;/', $css);
        $this->assertStringContainsString('body[data-page="channel-allocation"] .ca-table .ca-nested thead th', $css);
        $this->assertMatchesRegularExpression('/body\[data-page="channel-allocation"\] \.ca-table \.ca-nested thead th,\s*body\[data-page="channel-allocation"\] \.ca-table \.ca-nested tbody td \{\s*text-align: center;/', $css);
        $js = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString('omnichannel.expandedView', $js);
        $this->assertStringContainsString('initPreserveExpandedView', $js);
        $this->assertStringNotContainsString('if(e.target===m)', $js);
        $this->assertDoesNotMatchRegularExpression('/id="importUploadError"[^>]*\bflash\b/', $html);

        $this->assertEquals(1, ChannelAllocationCampaign::where('name', 'Atome')->count());

        $this->get('/channel-allocation?search=Atome')
            ->assertOk()
            ->assertSee('Atome');
        $atomeTable = Str::between(
            $this->get('/channel-allocation?search=Atome')->getContent(),
            'class="ca-table"',
            'class="table-footer"'
        );
        $this->assertStringContainsString('Atome</button>', $atomeTable);
        $this->assertStringNotContainsString('RCBC Bankard</button>', $atomeTable);

        $this->get('/channel-allocation/export')->assertOk()->assertDownload('channel-allocation.xlsx');
        $this->get('/channel-allocation/import/template')->assertOk()->assertDownload('channel-allocation-template.xlsx');
        $this->postJson('/channel-allocation/import/preview', [])->assertStatus(422);
        $this->post('/channel-allocation/import', [])->assertNotFound();
    }

    public function test_campaign_and_allocation_crud_with_delete_confirmation_attributes(): void
    {
        $this->actingAs($this->admin);
        $this->createSipChannel('CH-A', 'Globe SIM', 20);
        $this->createSipChannel('CH-A2', 'Eastern SIP', 20);
        $this->createSipChannel('CH-A-UPDATED', 'Globe SIM', 25);
        $this->createGsmGateway('PDC-MG1');

        $alpha = ChannelAllocationCampaign::create([
            'name' => 'Alpha Campaign',
            'fte' => 4,
            'sort_order' => 1,
        ]);
        $beta = ChannelAllocationCampaign::create([
            'name' => 'Beta Campaign',
            'sort_order' => 2,
        ]);

        $this->post('/channel-allocation', [
            'campaign_id' => $alpha->id,
            'media_gateway' => '10.0.0.1',
            'total_channels_allocated' => 40,
            'fte' => 4,
            'caller_id' => '123',
            'prefix' => '100',
            'remarks' => 'Campaign note',
            'channel_type' => 'sip',
            'channel' => 'CH-A',
            'network' => 'Globe SIM',
            'line_priority' => 1,
            'total_channel_allocated' => 20,
        ])->assertRedirect();

        $this->post('/channel-allocation', [
            'campaign_id' => $beta->id,
            'media_gateway' => '10.0.0.2',
        ])->assertRedirect();
        $this->from('/channel-allocation')->post('/channel-allocation', [
            'campaign' => 'Typed CA Campaign',
            'caller_id' => '999',
        ])->assertRedirect('/channel-allocation')->assertSessionHasErrors([
            'campaign' => 'Campaign does not exist in Master Campaign. Create the campaign in Master Campaign before adding a Channel Allocation.',
        ]);
        $this->assertDatabaseMissing('channel_allocation_campaigns', [
            'name' => 'Typed CA Campaign',
        ]);
        $this->post('/channel-allocation/'.$alpha->id.'/allocations', [
            'channel_type' => 'sip',
            'channel' => 'CH-A2',
            'network' => 'SHOULD-IGNORE',
            'line_priority' => 2,
            'total_channel_allocated' => 999,
        ])->assertRedirect();

        $this->assertSame(2, $alpha->allocations()->count());
        $this->assertDatabaseHas('channel_allocations', [
            'campaign_id' => $alpha->id,
            'channel_allocation' => 'CH-A2',
            'media_gateway' => null,
            'network' => 'Eastern SIP',
            'total_channel_allocated' => 20,
        ]);
        $this->assertSame(40, $alpha->fresh()->total_channels_allocated);

        $this->put('/channel-allocation/'.$alpha->id, [
            'campaign_id' => $alpha->id,
            'media_gateway' => '10.0.0.1',
            'total_channels_allocated' => 40,
            'fte' => 5,
            'caller_id' => '123',
            'prefix' => '100',
            'remarks' => 'Updated',
        ])->assertRedirect();

        $this->from('/channel-allocation')->put('/channel-allocation/'.$alpha->id, [
            'campaign' => 'TEST-CAMPAIGN',
            'caller_id' => '123',
            'prefix' => '100',
            'remarks' => 'Updated',
        ])->assertRedirect('/channel-allocation')->assertSessionHasErrors([
            'campaign' => 'Campaign does not exist in Master Campaign. Create the campaign in Master Campaign before adding a Channel Allocation.',
        ]);
        $this->assertDatabaseHas('channel_allocation_campaigns', [
            'id' => $alpha->id,
            'name' => 'Alpha Campaign',
        ]);
        $this->assertDatabaseMissing('channel_allocation_campaigns', ['name' => 'TEST-CAMPAIGN']);

        $this->assertDatabaseHas('channel_allocation_campaigns', [
            'id' => $alpha->id,
            'name' => 'Alpha Campaign',
        ]);
        $this->assertSame(4, $alpha->fresh()->fte);
        $this->assertDatabaseHas('channel_allocation_campaigns', ['name' => 'Beta Campaign']);

        $keep = $alpha->allocations()->where('channel_allocation', 'CH-A2')->firstOrFail();
        $target = $alpha->allocations()->where('channel_allocation', 'CH-A')->firstOrFail();

        $this->put('/channel-allocation/'.$alpha->id.'/allocations/'.$target->id, [
            'channel_type' => 'sip',
            'channel' => 'CH-A-UPDATED',
            'network' => 'HACKED',
            'line_priority' => 1,
            'total_channel_allocated' => 1,
            'remarks' => 'line note',
        ])->assertRedirect()->assertSessionHas('ca_expanded', $alpha->id)->assertSessionMissing('ca_edit_allocation');

        $this->assertDatabaseHas('channel_allocations', [
            'id' => $target->id,
            'channel_allocation' => 'CH-A-UPDATED',
            'media_gateway' => null,
            'network' => 'Globe SIM',
            'total_channel_allocated' => 25,
        ]);
        $this->assertDatabaseHas('channel_allocations', ['id' => $keep->id, 'channel_allocation' => 'CH-A2']);
        $this->assertSame(45, $alpha->fresh()->total_channels_allocated);

        $page = $this->get('/channel-allocation')->assertOk();
        $html = $page->getContent();
        $table = Str::between($html, '<table', '</table>');
        $this->assertStringContainsString('CH-A-UPDATED', $table);
        $this->assertStringContainsString('Data Transfer', $html);
        $this->assertStringNotContainsString('restoreEditAllocation', $html);
        $this->assertDoesNotMatchRegularExpression('/id="allocationModal"[^>]*\bvisible\b/', $html);
        $this->assertStringContainsString('class="plus-btn"', $html);

        $this->put('/channel-allocation/'.$alpha->id.'/allocations/'.$target->id, [
            'channel_type' => 'sip',
            'line_priority' => 1,
        ])->assertSessionHasErrors('channel')->assertSessionMissing('ca_edit_allocation');

        $this->get('/channel-allocation?search=CH-A-UPDATED')
            ->assertOk()
            ->assertSee('Alpha Campaign');

        $this->assertStringNotContainsString('Beta Campaign</button>', Str::between(
            $this->get('/channel-allocation?search=CH-A-UPDATED')->getContent(),
            'class="ca-table"',
            'class="table-footer"'
        ));

        $this->delete('/channel-allocation/'.$alpha->id.'/allocations/'.$target->id)->assertRedirect();
        $this->assertDatabaseMissing('channel_allocations', ['id' => $target->id]);
        $this->assertDatabaseHas('channel_allocations', ['id' => $keep->id]);
        $this->assertDatabaseHas('channel_allocation_campaigns', ['id' => $alpha->id, 'total_channels_allocated' => 20]);

        $this->delete('/channel-allocation/'.$alpha->id)->assertRedirect();
        $this->assertDatabaseHas('channel_allocation_campaigns', [
            'id' => $alpha->id,
            'name' => 'Alpha Campaign',
        ]);
        $this->assertFalse((bool) $alpha->fresh()->listed_in_channel_allocation);
        $this->assertSame(0, (int) $alpha->fresh()->total_channels_allocated);
        $this->assertDatabaseMissing('channel_allocations', ['id' => $keep->id]);
        $this->assertDatabaseHas('channel_allocation_campaigns', ['name' => 'Beta Campaign']);
        $this->get('/channel-allocation')->assertOk()->assertDontSee('title="Expand Alpha Campaign"', false);
        $this->get('/campaigns')->assertOk()->assertSee('Alpha Campaign');
    }

    public function test_roles_can_view_and_standard_cannot_mutate(): void
    {
        $campaign = ChannelAllocationCampaign::create([
            'name' => 'Locked',
            'sort_order' => 1,
            'listed_in_channel_allocation' => true,
        ]);
        $allocation = ChannelAllocation::create([
            'campaign_id' => $campaign->id,
            'channel_allocation' => 'LOCKED-1',
            'sort_order' => 1,
        ]);

        $this->actingAs($this->standard)->get('/channel-allocation')
            ->assertOk()
            ->assertSee('Channel Allocation')
            ->assertSee('Export Data')
            ->assertDontSee('class="plus-btn"', false)
            ->assertDontSee('action-btn edit', false)
            ->assertDontSee('action-btn delete', false);

        $this->actingAs($this->standard)->post('/channel-allocation', ['name' => 'Hacked'])->assertForbidden();
        $this->actingAs($this->standard)->postJson('/channel-allocation/import/preview', [])->assertForbidden();
        $this->actingAs($this->standard)->get('/channel-allocation/import/template')->assertForbidden();
        $this->actingAs($this->standard)->put('/channel-allocation/'.$campaign->id, ['name' => 'Hacked'])->assertForbidden();
        $this->actingAs($this->standard)->delete('/channel-allocation/'.$campaign->id)->assertForbidden();
        $this->actingAs($this->standard)->delete('/channel-allocation/'.$campaign->id.'/allocations/'.$allocation->id)->assertForbidden();

        $this->actingAs($this->admin)->get('/channel-allocation')
            ->assertOk()
            ->assertSee('class="plus-btn"', false)
            ->assertSee('class="action-btn edit"', false)
            ->assertSee('class="action-btn delete"', false);

        $this->assertDatabaseHas('channel_allocation_campaigns', ['id' => $campaign->id, 'name' => 'Locked']);
    }

    public function test_pagination_and_reset_query(): void
    {
        $this->actingAs($this->admin);
        for ($i = 1; $i <= 12; $i++) {
            ChannelAllocationCampaign::create([
                'name' => 'Camp '.$i,
                'sort_order' => $i,
                'listed_in_channel_allocation' => true,
            ]);
        }

        $this->get('/channel-allocation?per_page=5')
            ->assertOk()
            ->assertSee('Showing 1 to 5 of 12 campaigns')
            ->assertSee('Camp 1</button>', false)
            ->assertSee('title="Expand Camp 1"', false)
            ->assertDontSee('title="Expand Camp 12"', false);

        $this->get('/channel-allocation?per_page=5&page=3')
            ->assertOk()
            ->assertSee('Camp 11');
    }

    public function test_campaigns_tab_records_are_not_listed_until_added_or_imported(): void
    {
        $this->actingAs($this->admin);

        $this->post('/campaigns', [
            'name' => 'Catalog Only',
            'fte' => 3,
            'location' => 'WFH',
        ])->assertRedirect();

        $campaign = ChannelAllocationCampaign::where('name', 'Catalog Only')->firstOrFail();
        $this->assertFalse((bool) $campaign->listed_in_channel_allocation);

        $this->get('/channel-allocation')
            ->assertOk()
            ->assertDontSee('title="Expand Catalog Only"', false)
            ->assertSee('>Catalog Only</button>', false);

        $this->post('/channel-allocation', [
            'campaign_id' => $campaign->id,
            'caller_id' => '100',
        ])->assertRedirect();

        $this->assertTrue((bool) $campaign->fresh()->listed_in_channel_allocation);
        $this->get('/channel-allocation')
            ->assertOk()
            ->assertSee('title="Expand Catalog Only"', false)
            ->assertSee('data-label="Caller ID">100', false);
    }

    public function test_allocation_dropdowns_auto_fill_from_sip_channel(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create([
            'name' => 'Mynt',
            'sort_order' => 1,
            'listed_in_channel_allocation' => true,
        ]);
        $this->createSipChannel('ETPI_53235320', 'ETPI', 14);
        $this->createGsmGateway('PDC-MG1', 'Globe SIM', 16);

        $page = $this->get('/channel-allocation')->assertOk();
        $page->assertSee('>Channel</th>', false)
            ->assertSee('>Channel Count</th>', false)
            ->assertDontSee('>SIP Channel</th>', false)
            ->assertDontSee('>GSM Gateway</th>', false)
            ->assertDontSee('id="campaign_channel_allocation"', false)
            ->assertDontSee('id="campaign_alloc_media_gateway"', false)
            ->assertSee('id="alloc_channel"', false)
            ->assertSee('id="alloc_channel_type"', false)
            ->assertSee('for="alloc_channel_type">Channel Type', false)
            ->assertSee('Select Channel Type')
            ->assertSee('ETPI_53235320')
            ->assertSee('PDC-MG1')
            ->assertSee('readonly', false)
            ->assertSee('id="alloc_line_priority"', false)
            ->assertSee('id="alloc_channel_count"', false)
            ->assertSee('class="ca-menu-item edit"', false)
            ->assertSee('class="ca-menu-item delete"', false);

        $this->post('/channel-allocation/'.$campaign->id.'/allocations', [
            'channel_type' => 'sip',
            'channel' => 'ETPI_53235320',
            'network' => 'MANUAL',
            'line_priority' => 1,
            'total_channel_allocated' => 99,
        ])->assertRedirect();

        $this->assertDatabaseHas('channel_allocations', [
            'campaign_id' => $campaign->id,
            'channel_allocation' => 'ETPI_53235320',
            'media_gateway' => null,
            'network' => 'ETPI',
            'line_priority' => 1,
            'total_channel_allocated' => 14,
        ]);
        $this->assertSame(14, $campaign->fresh()->total_channels_allocated);

        $this->post('/channel-allocation/'.$campaign->id.'/allocations', [
            'channel_type' => 'gsm',
            'channel' => 'PDC-MG1',
            'network' => 'MANUAL',
            'line_priority' => 2,
            'total_channel_allocated' => 99,
        ])->assertRedirect();

        $this->assertDatabaseHas('channel_allocations', [
            'campaign_id' => $campaign->id,
            'channel_allocation' => 'PDC-MG1',
            'media_gateway' => 'PDC-MG1',
            'network' => 'Globe SIM',
            'line_priority' => 2,
            'total_channel_allocated' => 16,
        ]);
        $this->assertSame(30, $campaign->fresh()->total_channels_allocated);

        $this->post('/channel-allocation/'.$campaign->id.'/allocations', [
            'channel_type' => 'sip',
            'channel' => 'DOES-NOT-EXIST',
            'line_priority' => 2,
        ])->assertSessionHasErrors('channel');
    }

    public function test_channel_allocation_delete_keeps_master_campaign_and_sip_channels(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create([
            'name' => 'Master Keep',
            'fte' => 6,
            'location' => 'WFH',
            'listed_in_channel_allocation' => true,
        ]);
        $sip = SipChannel::create([
            'campaign_id' => $campaign->id,
            'etpi_sip_name' => 'SIP_MASTER_KEEP',
            'channel_count' => 4,
        ]);
        ChannelAllocation::create([
            'campaign_id' => $campaign->id,
            'channel_allocation' => 'SIP_MASTER_KEEP',
            'total_channel_allocated' => 4,
        ]);

        $this->delete('/channel-allocation/'.$campaign->id)->assertRedirect();

        $this->assertDatabaseHas('channel_allocation_campaigns', [
            'id' => $campaign->id,
            'name' => 'Master Keep',
            'fte' => 6,
            'location' => 'WFH',
        ]);
        $this->assertFalse((bool) $campaign->fresh()->listed_in_channel_allocation);
        $this->assertDatabaseHas('sip_channels', ['id' => $sip->id, 'etpi_sip_name' => 'SIP_MASTER_KEEP']);
        $this->assertDatabaseMissing('channel_allocations', ['campaign_id' => $campaign->id]);
        $this->get('/campaigns')->assertOk()->assertSee('Master Keep');
    }

    public function test_pdc_created_campaign_is_not_usable_in_channel_allocation(): void
    {
        $this->actingAs($this->admin);
        $this->post('/pdc-servers', [
            'campaign' => 'PDC Only Campaign',
            'location' => 'Estancia',
        ])->assertRedirect();
        $this->assertDatabaseMissing('channel_allocation_campaigns', ['name' => 'PDC Only Campaign']);

        $html = $this->get('/channel-allocation')->assertOk()->getContent();
        $this->assertStringNotContainsString('>PDC Only Campaign</button>', $html);

        $this->from('/channel-allocation')->post('/channel-allocation', [
            'campaign' => 'PDC Only Campaign',
        ])->assertRedirect('/channel-allocation')->assertSessionHasErrors([
            'campaign' => 'Campaign does not exist in Master Campaign. Create the campaign in Master Campaign before adding a Channel Allocation.',
        ]);
        $this->assertDatabaseMissing('channel_allocation_campaigns', ['name' => 'PDC Only Campaign']);
        $this->get('/campaigns')->assertOk()->assertDontSee('PDC Only Campaign');
    }

    private function createSipChannel(string $name, string $network, int $count): SipChannel
    {
        return SipChannel::create([
            'etpi_sip_name' => $name,
            'network' => $network,
            'channel_count' => $count,
        ]);
    }

    private function createGsmGateway(string $hostname, ?string $network = null, ?int $count = null): MediaGateway
    {
        return MediaGateway::create([
            'hostname' => $hostname,
            'site_name' => 'WFH',
            'site_code' => $hostname,
            'ip_address' => '10.24.28.'.random_int(20, 250),
            'network' => $network,
            'channel_count' => $count,
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ]);
    }
}
