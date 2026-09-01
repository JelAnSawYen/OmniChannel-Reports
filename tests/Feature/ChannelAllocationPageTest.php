<?php

namespace Tests\Feature;

use App\Models\ChannelAllocation;
use App\Models\ChannelAllocationCampaign;
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
            ->assertSee('id="channelAllocationReset"', false)
            ->assertSee('Atome')
            ->assertSee('2 allocations')
            ->assertSee('gsm_globe_est145')
            ->assertSee('data-ca-toggle', false)
            ->assertSee('class="ca-menu-btn"', false)
            ->assertSee('Edit Campaign')
            ->assertSee('Delete Campaign')
            ->assertSee('data-confirm-title="Delete Campaign"', false)
            ->assertSee('data-confirm-title="Delete Allocation"', false)
            ->assertSee('campaigns')
            ->assertDontSee('All Status')
            ->assertDontSee('All Networks')
            ->assertDontSee('All Priorities')
            ->assertDontSee('Add Channel Allocation');

        $this->assertTrue(str_contains($html, '>SIP Channels</span></a>') && str_contains($html, '>Channel Allocation</span></a>'));
        $this->assertTrue(strpos($html, '>SIP Channels</span></a>') < strpos($html, '>Channel Allocation</span></a>'));
        $this->assertTrue(strpos($html, '>Channel Allocation</span></a>') < strpos($html, '>Archive Recordings</span></a>'));
        $this->assertStringContainsString('class="import-upload-error"', $html);
        $this->assertDoesNotMatchRegularExpression('/id="importUploadError"[^>]*\bflash\b/', $html);

        $this->assertEquals(1, ChannelAllocationCampaign::where('name', 'Atome')->count());

        $this->get('/channel-allocation?search=Atome')
            ->assertOk()
            ->assertSee('Atome')
            ->assertDontSee('RCBC Bankard');

        $this->get('/channel-allocation/export')->assertOk()->assertDownload('channel-allocation.xlsx');
        $this->get('/channel-allocation/import/template')->assertOk()->assertDownload('channel-allocation-template.xlsx');
        $this->postJson('/channel-allocation/import/preview', [])->assertStatus(422);
        $this->post('/channel-allocation/import', [])->assertNotFound();
    }

    public function test_campaign_and_allocation_crud_with_delete_confirmation_attributes(): void
    {
        $this->actingAs($this->admin);

        $this->post('/channel-allocation', [
            'name' => 'Alpha Campaign',
            'media_gateway' => '10.0.0.1',
            'total_channels_allocated' => 40,
            'fte' => 4,
            'caller_id' => '123',
            'prefix' => '100',
            'remarks' => 'Campaign note',
            'channel_allocation' => 'CH-A',
            'network' => 'Globe SIM',
            'line_priority' => 1,
            'total_channel_allocated' => 20,
        ])->assertRedirect();

        $this->post('/channel-allocation', [
            'name' => 'Beta Campaign',
            'media_gateway' => '10.0.0.2',
            'channel_allocation' => 'CH-B',
            'network' => 'Smart SIM',
            'line_priority' => 1,
            'total_channel_allocated' => 10,
        ])->assertRedirect();

        $alpha = ChannelAllocationCampaign::where('name', 'Alpha Campaign')->firstOrFail();
        $this->post('/channel-allocation/'.$alpha->id.'/allocations', [
            'media_gateway' => '10.0.0.1',
            'channel_allocation' => 'CH-A2',
            'network' => 'Eastern SIP',
            'line_priority' => 2,
            'total_channel_allocated' => 20,
        ])->assertRedirect();

        $this->assertSame(2, $alpha->allocations()->count());
        $this->assertSame(40, $alpha->fresh()->total_channels_allocated);

        $this->put('/channel-allocation/'.$alpha->id, [
            'name' => 'Alpha Campaign Updated',
            'media_gateway' => '10.0.0.1',
            'total_channels_allocated' => 40,
            'fte' => 5,
            'caller_id' => '123',
            'prefix' => '100',
            'remarks' => 'Updated',
        ])->assertRedirect();

        $this->assertDatabaseHas('channel_allocation_campaigns', [
            'id' => $alpha->id,
            'name' => 'Alpha Campaign Updated',
            'fte' => 5,
        ]);
        $this->assertDatabaseHas('channel_allocation_campaigns', ['name' => 'Beta Campaign']);

        $keep = $alpha->allocations()->where('channel_allocation', 'CH-A2')->firstOrFail();
        $target = $alpha->allocations()->where('channel_allocation', 'CH-A')->firstOrFail();

        $this->put('/channel-allocation/'.$alpha->id.'/allocations/'.$target->id, [
            'media_gateway' => '10.0.0.1',
            'channel_allocation' => 'CH-A-UPDATED',
            'network' => 'Globe SIM',
            'line_priority' => 1,
            'total_channel_allocated' => 25,
            'remarks' => 'line note',
        ])->assertRedirect();

        $this->assertDatabaseHas('channel_allocations', [
            'id' => $target->id,
            'channel_allocation' => 'CH-A-UPDATED',
            'total_channel_allocated' => 25,
        ]);
        $this->assertDatabaseHas('channel_allocations', ['id' => $keep->id, 'channel_allocation' => 'CH-A2']);
        $this->assertSame(45, $alpha->fresh()->total_channels_allocated);

        $page = $this->get('/channel-allocation')->assertOk();
        $table = Str::between($page->getContent(), '<table', '</table>');
        $this->assertStringContainsString('CH-A-UPDATED', $table);
        $this->assertStringContainsString('Data Transfer', $page->getContent());

        $this->get('/channel-allocation?search=CH-A-UPDATED')
            ->assertOk()
            ->assertSee('Alpha Campaign Updated');

        $this->assertStringNotContainsString('Beta Campaign', Str::between(
            $this->get('/channel-allocation?search=CH-A-UPDATED')->getContent(),
            '<tbody>',
            '</tbody>'
        ));

        $this->delete('/channel-allocation/'.$alpha->id.'/allocations/'.$target->id)->assertRedirect();
        $this->assertDatabaseMissing('channel_allocations', ['id' => $target->id]);
        $this->assertDatabaseHas('channel_allocations', ['id' => $keep->id]);
        $this->assertDatabaseHas('channel_allocation_campaigns', ['id' => $alpha->id, 'total_channels_allocated' => 20]);

        $this->delete('/channel-allocation/'.$alpha->id)->assertRedirect();
        $this->assertDatabaseMissing('channel_allocation_campaigns', ['id' => $alpha->id]);
        $this->assertDatabaseMissing('channel_allocations', ['id' => $keep->id]);
        $this->assertDatabaseHas('channel_allocation_campaigns', ['name' => 'Beta Campaign']);
    }

    public function test_roles_can_view_and_standard_cannot_mutate(): void
    {
        $campaign = ChannelAllocationCampaign::create([
            'name' => 'Locked',
            'sort_order' => 1,
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
            ChannelAllocationCampaign::create(['name' => 'Camp '.$i, 'sort_order' => $i]);
        }

        $this->get('/channel-allocation?per_page=5')
            ->assertOk()
            ->assertSee('Showing 1 to 5 of 12 campaigns')
            ->assertSee('Camp 1</button>', false)
            ->assertDontSee('Camp 12</button>', false);

        $this->get('/channel-allocation?per_page=5&page=3')
            ->assertOk()
            ->assertSee('Camp 11');
    }
}
