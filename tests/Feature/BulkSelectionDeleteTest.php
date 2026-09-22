<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ChannelAllocation;
use App\Models\ChannelAllocationCampaign;
use App\Models\GatewaySimAssignment;
use App\Models\GlobeSim;
use App\Models\MediaGateway;
use App\Models\PdcGroup;
use App\Models\PdcServer;
use App\Models\SipChannel;
use App\Models\SipChannelNumber;
use App\Models\SmartSim;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkSelectionDeleteTest extends TestCase
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

    public function test_campaigns_page_enables_row_selection_without_permanent_bulk_controls(): void
    {
        $campaign = ChannelAllocationCampaign::create([
            'name' => 'Bulk Visible',
            'fte' => 4,
            'location' => 'CTN',
        ]);

        $html = $this->actingAs($this->admin)->get('/campaigns')->assertOk()->getContent();

        $this->assertStringContainsString('data-bulk-row="main"', $html);
        $this->assertStringContainsString('data-bulk-id="'.$campaign->id.'"', $html);
        $this->assertStringContainsString('data-bulk-url="'.url('/campaigns/bulk').'"', $html);
        $this->assertStringNotContainsString('Delete Selected', $html);
        $this->assertStringNotContainsString('Bulk Delete', $html);
        $this->assertStringNotContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('id="transferButton"', $html);
        $this->assertStringContainsString('Delete this campaign?', $html);
    }

    public function test_campaigns_bulk_delete_removes_only_selected_records_and_logs_deleted(): void
    {
        $keep = ChannelAllocationCampaign::create(['name' => 'Keep Campaign', 'fte' => 1, 'location' => 'CTN']);
        $dropA = ChannelAllocationCampaign::create(['name' => 'Drop Campaign A', 'fte' => 2, 'location' => 'CTN']);
        $dropB = ChannelAllocationCampaign::create(['name' => 'Drop Campaign B', 'fte' => 3, 'location' => 'Alcar']);

        $this->actingAs($this->admin)->from('/campaigns')->delete('/campaigns/bulk', [
            'ids' => [$dropA->id, $dropB->id],
        ])->assertRedirect('/campaigns');

        $this->assertDatabaseHas('channel_allocation_campaigns', ['id' => $keep->id]);
        $this->assertDatabaseMissing('channel_allocation_campaigns', ['id' => $dropA->id]);
        $this->assertDatabaseMissing('channel_allocation_campaigns', ['id' => $dropB->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Deleted',
            'module' => 'Campaigns',
            'record_id' => (string) $dropA->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Deleted',
            'module' => 'Campaigns',
            'record_id' => (string) $dropB->id,
        ]);
        $this->assertSame(2, AuditLog::query()->where('action', 'Deleted')->where('module', 'Campaigns')->count());
    }

    public function test_standard_user_cannot_bulk_delete_campaigns(): void
    {
        $campaign = ChannelAllocationCampaign::create(['name' => 'Locked Bulk', 'fte' => 1, 'location' => 'CTN']);

        $this->actingAs($this->standard)->delete('/campaigns/bulk', [
            'ids' => [$campaign->id],
        ])->assertForbidden();

        $this->assertDatabaseHas('channel_allocation_campaigns', ['id' => $campaign->id]);
        $this->actingAs($this->standard)->get('/campaigns')
            ->assertOk()
            ->assertDontSee('data-bulk-row', false);
    }

    public function test_channel_allocation_child_bulk_delete_does_not_remove_parent_or_other_campaigns(): void
    {
        $alpha = ChannelAllocationCampaign::create(['name' => 'Alpha Bulk', 'sort_order' => 1]);
        $beta = ChannelAllocationCampaign::create(['name' => 'Beta Bulk', 'sort_order' => 2]);
        $keep = ChannelAllocation::create([
            'campaign_id' => $alpha->id,
            'channel_allocation' => 'KEEP-1',
            'sort_order' => 1,
        ]);
        $drop = ChannelAllocation::create([
            'campaign_id' => $alpha->id,
            'channel_allocation' => 'DROP-1',
            'sort_order' => 2,
        ]);
        $other = ChannelAllocation::create([
            'campaign_id' => $beta->id,
            'channel_allocation' => 'OTHER-1',
            'sort_order' => 1,
        ]);

        $this->actingAs($this->admin)->from('/channel-allocation')->delete('/channel-allocation/'.$alpha->id.'/allocations/bulk', [
            'ids' => [$drop->id, $other->id],
        ])->assertRedirect('/channel-allocation');

        $this->assertDatabaseHas('channel_allocation_campaigns', ['id' => $alpha->id]);
        $this->assertDatabaseHas('channel_allocation_campaigns', ['id' => $beta->id]);
        $this->assertDatabaseHas('channel_allocations', ['id' => $keep->id]);
        $this->assertDatabaseMissing('channel_allocations', ['id' => $drop->id]);
        $this->assertDatabaseHas('channel_allocations', ['id' => $other->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Deleted',
            'module' => 'Channel Allocation',
            'record_id' => (string) $drop->id,
        ]);
    }

    public function test_channel_allocation_parent_bulk_delete_removes_selected_campaigns_only(): void
    {
        $keep = ChannelAllocationCampaign::create(['name' => 'Keep Parent', 'sort_order' => 1]);
        $drop = ChannelAllocationCampaign::create(['name' => 'Drop Parent', 'sort_order' => 2]);
        ChannelAllocation::create([
            'campaign_id' => $drop->id,
            'channel_allocation' => 'CHILD-1',
            'sort_order' => 1,
        ]);

        $this->actingAs($this->admin)->delete('/channel-allocation/bulk', [
            'ids' => [$drop->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('channel_allocation_campaigns', ['id' => $keep->id]);
        $this->assertDatabaseHas('channel_allocation_campaigns', ['id' => $drop->id, 'name' => 'Drop Parent']);
        $this->assertFalse((bool) $drop->fresh()->listed_in_channel_allocation);
        $this->assertDatabaseMissing('channel_allocations', ['channel_allocation' => 'CHILD-1']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Deleted',
            'module' => 'Channel Allocation',
            'record_id' => (string) $drop->id,
        ]);
        $this->get('/campaigns')->assertOk()->assertSee('Drop Parent')->assertSee('Keep Parent');
    }

    public function test_pdc_nested_bulk_delete_removes_only_servers_in_that_group(): void
    {
        $campaign = ChannelAllocationCampaign::create(['name' => 'PDC Bulk']);
        $group = PdcGroup::create(['campaign_id' => $campaign->id, 'location' => 'Estancia']);
        $otherGroup = PdcGroup::create([
            'campaign_id' => ChannelAllocationCampaign::create(['name' => 'PDC Other'])->id,
            'location' => 'Alcar',
        ]);
        $keep = PdcServer::create([
            'pdc_group_id' => $group->id,
            'hostname' => 'pdc-keep',
            'ip_address' => '10.1.1.1',
            'location' => 'Estancia',
            'status' => 'Active',
        ]);
        $drop = PdcServer::create([
            'pdc_group_id' => $group->id,
            'hostname' => 'pdc-drop',
            'ip_address' => '10.1.1.2',
            'location' => 'Estancia',
            'status' => 'Active',
        ]);
        $other = PdcServer::create([
            'pdc_group_id' => $otherGroup->id,
            'hostname' => 'pdc-other',
            'ip_address' => '10.1.1.3',
            'location' => 'Alcar',
            'status' => 'Active',
        ]);

        $this->actingAs($this->admin)->delete('/pdc-servers/'.$group->id.'/servers/bulk', [
            'ids' => [$drop->id, $other->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('pdc_groups', ['id' => $group->id]);
        $this->assertDatabaseHas('pdc_servers', ['id' => $keep->id]);
        $this->assertDatabaseMissing('pdc_servers', ['id' => $drop->id]);
        $this->assertDatabaseHas('pdc_servers', ['id' => $other->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Deleted',
            'module' => 'PDC Servers',
            'record_id' => (string) $drop->id,
        ]);
    }

    public function test_channel_range_list_bulk_delete_removes_only_selected_numbers(): void
    {
        $campaign = ChannelAllocationCampaign::create(['name' => 'CRL Bulk']);
        $sip = SipChannel::create(['campaign_id' => $campaign->id, 'etpi_sip_name' => 'SIP_BULK']);
        $keep = SipChannelNumber::create(['sip_channel_id' => $sip->id, 'channel_number' => '0325111001']);
        $drop = SipChannelNumber::create(['sip_channel_id' => $sip->id, 'channel_number' => '0325111002']);

        $html = $this->actingAs($this->admin)->get('/channel-range-list')->assertOk()->getContent();
        $this->assertStringContainsString('data-bulk-row="main"', $html);
        $this->assertStringContainsString('data-bulk-ids="'.$keep->id.','.$drop->id.'"', $html);
        $this->assertStringContainsString('data-bulk-row="nested" data-bulk-id="'.$keep->id.'" data-bulk-url="'.url('/channel-range-list/bulk').'"', $html);
        $this->assertStringContainsString('data-bulk-row="nested" data-bulk-id="'.$drop->id.'" data-bulk-url="'.url('/channel-range-list/bulk').'"', $html);

        $this->delete('/channel-range-list/bulk', ['ids' => [$drop->id]])->assertRedirect();

        $this->assertDatabaseHas('sip_channels', ['id' => $sip->id]);
        $this->assertDatabaseHas('sip_channel_numbers', ['id' => $keep->id]);
        $this->assertDatabaseMissing('sip_channel_numbers', ['id' => $drop->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Deleted',
            'module' => 'Channel Range List',
            'record_id' => (string) $drop->id,
        ]);
    }

    public function test_operations_and_sip_bulk_delete_keep_unselected_records(): void
    {
        $keepSim = GlobeSim::create([
            'imei' => '356938035643801',
            'mobile_number' => '09170000011',
            'network' => 'Globe',
            'plan' => 'Plan Keep',
            'status' => 'Active',
        ]);
        $dropSim = GlobeSim::create([
            'imei' => '356938035643802',
            'mobile_number' => '09170000012',
            'network' => 'Globe',
            'plan' => 'Plan Drop',
            'status' => 'Active',
        ]);

        $this->actingAs($this->admin)->delete('/globe-sim/bulk', ['ids' => [$dropSim->id]])->assertRedirect();
        $this->assertDatabaseHas('globe_sims', ['id' => $keepSim->id]);
        $this->assertDatabaseMissing('globe_sims', ['id' => $dropSim->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Deleted',
            'module' => 'Globe SIM',
            'record_id' => (string) $dropSim->id,
        ]);

        $campaign = ChannelAllocationCampaign::create(['name' => 'SIP Bulk']);
        $keepSip = SipChannel::create(['campaign_id' => $campaign->id, 'etpi_sip_name' => 'SIP_KEEP']);
        $dropSip = SipChannel::create(['campaign_id' => $campaign->id, 'etpi_sip_name' => 'SIP_DROP']);

        $this->delete('/sip-channels/bulk', ['ids' => [$dropSip->id]])->assertRedirect();
        $this->assertDatabaseHas('sip_channels', ['id' => $keepSip->id]);
        $this->assertDatabaseMissing('sip_channels', ['id' => $dropSip->id]);
    }

    public function test_users_bulk_delete_skips_self_and_logs_deleted_for_removed_users(): void
    {
        $other = User::factory()->create([
            'user_type_id' => UserType::where('name', 'Standard User')->value('id'),
            'status' => 'Active',
        ]);

        $this->actingAs($this->admin)->from('/users')->delete('/users/bulk', [
            'ids' => [$this->admin->id, $other->id],
        ])->assertRedirect('/users');

        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
        $this->assertDatabaseMissing('users', ['id' => $other->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Deleted',
            'module' => 'Users',
            'record_id' => (string) $other->id,
        ]);
    }

    public function test_gsm_bulk_delete_removes_selected_gateways_and_assignments_separately(): void
    {
        $keep = MediaGateway::create([
            'hostname' => 'gsm-keep',
            'site_name' => 'Alcar',
            'site_code' => 'KEEP99',
            'ip_address' => '10.9.9.1',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ]);
        $drop = MediaGateway::create([
            'hostname' => 'gsm-drop',
            'site_name' => 'Alcar',
            'site_code' => 'DROP99',
            'ip_address' => '10.9.9.2',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ]);
        $simA = GlobeSim::create([
            'imei' => '356938035643821',
            'mobile_number' => '09170000021',
            'network' => 'Globe',
            'status' => 'Active',
        ]);
        $simB = GlobeSim::create([
            'imei' => '356938035643822',
            'mobile_number' => '09170000022',
            'network' => 'Globe',
            'status' => 'Active',
        ]);
        $keepAssignment = GatewaySimAssignment::create([
            'media_gateway_id' => $keep->id,
            'sim_type' => 'globe',
            'sim_id' => $simA->id,
            'port' => 1,
        ]);
        $dropAssignment = GatewaySimAssignment::create([
            'media_gateway_id' => $keep->id,
            'sim_type' => 'globe',
            'sim_id' => $simB->id,
            'port' => 2,
        ]);

        $this->actingAs($this->admin)->deleteJson('/gsm-gateways/'.$keep->id.'/assignments/bulk', [
            'ids' => [$dropAssignment->id],
        ])->assertOk();

        $this->assertDatabaseHas('media_gateways', ['id' => $keep->id]);
        $this->assertDatabaseHas('gateway_sim_assignments', ['id' => $keepAssignment->id]);
        $this->assertDatabaseMissing('gateway_sim_assignments', ['id' => $dropAssignment->id]);
        $this->assertDatabaseHas('globe_sims', ['id' => $simA->id]);
        $this->assertDatabaseHas('globe_sims', ['id' => $simB->id]);

        $this->deleteJson('/gsm-gateways/bulk', ['ids' => [$drop->id]])->assertOk();
        $this->assertDatabaseHas('media_gateways', ['id' => $keep->id]);
        $this->assertDatabaseMissing('media_gateways', ['id' => $drop->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Deleted',
            'module' => 'Media Gateways',
            'record_id' => (string) $drop->id,
        ]);
    }

    public function test_gsm_nested_rows_bulk_unlink_assignments_without_deleting_sims(): void
    {
        $keep = MediaGateway::create([
            'hostname' => 'gsm-nested-keep',
            'site_name' => 'Alcar',
            'site_code' => 'NEST01',
            'ip_address' => '10.9.8.1',
            'channel_count' => 3,
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ]);
        $empty = MediaGateway::create([
            'hostname' => 'gsm-nested-empty',
            'site_name' => 'Alcar',
            'site_code' => 'NEST02',
            'ip_address' => '10.9.8.2',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ]);
        $globe = GlobeSim::create([
            'imei' => '356938035643831',
            'mobile_number' => '09170000031',
            'network' => 'Globe',
            'plan' => 'Globe Nested',
            'ip_address' => '10.9.8.1',
            'status' => 'Active',
        ]);
        $smart = SmartSim::create([
            'imei' => '356938035643832',
            'mobile_number' => '09170000032',
            'network' => 'Smart',
            'plan' => 'Smart Nested',
            'ip_address' => '10.9.8.1',
            'status' => 'Active',
        ]);
        $keepAssignment = GatewaySimAssignment::create([
            'media_gateway_id' => $keep->id,
            'sim_type' => 'globe',
            'sim_id' => $globe->id,
            'port' => 1,
        ]);
        $dropAssignment = GatewaySimAssignment::create([
            'media_gateway_id' => $keep->id,
            'sim_type' => 'smart',
            'sim_id' => $smart->id,
            'port' => 2,
        ]);
        $ipOnly = GlobeSim::create([
            'imei' => '356938035643833',
            'mobile_number' => '09170000033',
            'network' => 'Globe',
            'plan' => 'IP Nested',
            'ip_address' => '10.9.8.1',
            'port' => 3,
            'status' => 'Active',
        ]);

        $html = $this->actingAs($this->admin)->get('/gsm-gateways')->assertOk()->getContent();
        $this->assertStringContainsString(
            'data-bulk-row="main" data-bulk-id="'.$keep->id.'" data-bulk-url="'.url('/gsm-gateways/bulk').'" data-bulk-ajax="1"',
            $html
        );
        $this->assertStringContainsString(
            'data-bulk-row="nested" data-bulk-id="'.$keepAssignment->id.'" data-bulk-url="'.url('/gsm-gateways/'.$keep->id.'/assignments/bulk').'" data-bulk-ajax="1"',
            $html
        );
        $this->assertStringContainsString(
            'data-bulk-row="nested" data-bulk-id="'.$dropAssignment->id.'" data-bulk-url="'.url('/gsm-gateways/'.$keep->id.'/assignments/bulk').'" data-bulk-ajax="1"',
            $html
        );
        $this->assertStringContainsString(
            'data-bulk-row="nested" data-bulk-id="globe-'.$ipOnly->id.'" data-bulk-url="'.url('/gsm-gateways/'.$keep->id.'/assignments/bulk').'" data-bulk-ajax="1"',
            $html
        );
        $emptyStart = strpos($html, 'id="gsm-panel-'.$empty->id.'"');
        $this->assertNotFalse($emptyStart);
        $emptyEnd = strpos($html, '</table>', $emptyStart);
        $this->assertNotFalse($emptyEnd);
        $emptyPanel = substr($html, $emptyStart, $emptyEnd - $emptyStart);
        $this->assertStringContainsString('No SIM assignments.', $emptyPanel);
        $this->assertStringNotContainsString('data-bulk-row="nested"', $emptyPanel);

        $record = collect($this->getJson('/gsm-gateways')->assertOk()->json('records'))->firstWhere('id', $keep->id);
        $this->assertSame($keepAssignment->id, (int) $record['assignments'][0]['assignment_id']);
        $this->assertSame($dropAssignment->id, (int) $record['assignments'][1]['assignment_id']);

        $this->deleteJson('/gsm-gateways/'.$keep->id.'/assignments/bulk', [
            'ids' => [$dropAssignment->id, 'globe-'.$ipOnly->id],
        ])->assertOk();

        $this->assertDatabaseHas('gateway_sim_assignments', ['id' => $keepAssignment->id]);
        $this->assertDatabaseMissing('gateway_sim_assignments', ['id' => $dropAssignment->id]);
        $this->assertDatabaseHas('globe_sims', ['id' => $globe->id, 'imei' => '356938035643831']);
        $this->assertDatabaseHas('smart_sims', ['id' => $smart->id, 'imei' => '356938035643832']);
        $this->assertDatabaseHas('globe_sims', ['id' => $ipOnly->id, 'imei' => '356938035643833']);
        $this->assertNull($ipOnly->fresh()->ip_address);
        $this->assertNull($smart->fresh()->ip_address);
        $this->assertDatabaseHas('media_gateways', ['id' => $keep->id]);
        $this->assertDatabaseHas('media_gateways', ['id' => $empty->id]);
    }

    public function test_activity_logs_and_data_transfer_do_not_gain_bulk_delete_controls(): void
    {
        $this->actingAs($this->admin);

        $this->get('/activity-logs')->assertOk()->assertDontSee('Delete Selected')->assertDontSee('data-bulk-row', false);
        $this->get('/login-history')->assertOk()->assertDontSee('Delete Selected')->assertDontSee('data-bulk-row', false);

        $transfer = file_get_contents(resource_path('views/partials/data-transfer.blade.php'));
        $this->assertIsString($transfer);
        $this->assertStringNotContainsString('Bulk Delete', $transfer);
        $this->assertStringNotContainsString('Delete Selected', $transfer);
    }
}
