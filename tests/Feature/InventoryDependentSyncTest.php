<?php

namespace Tests\Feature;

use App\Models\ChannelAllocation;
use App\Models\ChannelAllocationCampaign;
use App\Models\ChannelPort;
use App\Models\GlobeSim;
use App\Models\MediaGateway;
use App\Models\NetworkPrefix;
use App\Models\ProgramInboundNumber;
use App\Models\SipChannel;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryDependentSyncTest extends TestCase
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

    public function test_renaming_a_campaign_updates_dependent_modules_and_keeps_ids(): void
    {
        $campaign = ChannelAllocationCampaign::create(['name' => 'Campaign A', 'fte' => 1, 'location' => 'Estancia']);
        $sip = SipChannel::create([
            'campaign_id' => $campaign->id,
            'etpi_sip_name' => 'ETPI_A',
            'channel_count' => 10,
            'network' => 'Eastern SIP',
        ]);
        $pin = ProgramInboundNumber::create([
            'number' => '09171234567',
            'program' => 'Campaign A',
            'status' => 'Active',
            'campaign_id' => $campaign->id,
            'mobile_numbers' => ['09171234567'],
        ]);
        ChannelAllocation::create([
            'campaign_id' => $campaign->id,
            'channel_allocation' => 'ETPI_A',
            'network' => 'Eastern SIP',
            'total_channel_allocated' => 10,
        ]);
        $campaign->refreshTotalChannelsAllocated();

        $this->actingAs($this->admin)->put(route('campaigns.update', $campaign), [
            'name' => 'Campaign B',
            'fte' => 1,
            'location' => 'Estancia',
        ])->assertRedirect();

        $campaign->refresh();
        $sip->refresh();
        $pin->refresh();

        $this->assertSame('Campaign B', $campaign->name);
        $this->assertSame($campaign->id, $sip->campaign_id);
        $this->assertSame($campaign->id, $pin->campaign_id);
        $this->assertSame('Campaign B', $pin->program);
        $this->assertSame('Campaign B', $pin->campaignLabel());

        $this->actingAs($this->admin)->get(route('sip-channels'))
            ->assertOk()
            ->assertSee('Campaign B')
            ->assertDontSee('Campaign A');
        $this->actingAs($this->admin)->get(route('program-inbound-numbers'))
            ->assertOk()
            ->assertSee('Campaign B')
            ->assertDontSee('Campaign A');
        $this->actingAs($this->admin)->get(route('program-inbound-numbers', ['search' => 'Campaign B']))
            ->assertOk()
            ->assertSee('09171234567');
        $this->actingAs($this->admin)->get(route('channel-allocation'))
            ->assertOk()
            ->assertSee('Campaign B');
        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Campaign B');
    }

    public function test_renaming_a_sip_channel_updates_channel_allocation_copies(): void
    {
        $campaign = ChannelAllocationCampaign::create(['name' => 'Atome']);
        $sip = SipChannel::create([
            'campaign_id' => $campaign->id,
            'etpi_sip_name' => 'ETPI_OLD',
            'channel_count' => 8,
            'network' => 'Eastern SIP',
        ]);
        $allocation = ChannelAllocation::create([
            'campaign_id' => $campaign->id,
            'channel_allocation' => 'ETPI_OLD',
            'network' => 'Eastern SIP',
            'total_channel_allocated' => 8,
        ]);

        $sip->update(['etpi_sip_name' => 'ETPI_NEW', 'channel_count' => 12, 'network' => 'Eastern SIP 2']);

        $allocation->refresh();
        $campaign->refresh();
        $this->assertSame('ETPI_NEW', $allocation->channel_allocation);
        $this->assertSame('ETPI_NEW', $allocation->channelLabel());
        $this->assertSame('Eastern SIP 2', $allocation->network);
        $this->assertSame(12, (int) $allocation->total_channel_allocated);
        $this->assertSame(12, (int) $campaign->total_channels_allocated);
        $this->assertSame($campaign->id, $allocation->campaign_id);
    }

    public function test_renaming_a_gsm_hostname_and_ip_updates_dependents(): void
    {
        $campaign = ChannelAllocationCampaign::create(['name' => 'Globe Camp']);
        $gateway = MediaGateway::create([
            'hostname' => 'old-host',
            'site_name' => 'Estancia',
            'site_code' => 'EST-1',
            'ip_address' => '10.1.1.1',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
            'channel_count' => 4,
            'network' => 'Globe SIM',
        ]);
        $allocation = ChannelAllocation::create([
            'campaign_id' => $campaign->id,
            'channel_allocation' => 'old-host',
            'media_gateway' => 'old-host',
            'network' => 'Globe SIM',
            'total_channel_allocated' => 4,
        ]);
        $sim = GlobeSim::create([
            'imei' => '356938035643111',
            'mobile_number' => '09170001111',
            'ip_address' => '10.1.1.1',
            'location' => 'Estancia',
            'status' => 'Active',
        ]);
        $port = ChannelPort::create([
            'port_number' => 5060,
            'gateway' => 'EST-1',
            'status' => 'Active',
        ]);
        $prefix = NetworkPrefix::create([
            'network' => 'Globe',
            'prefix' => '917',
            'gateway' => 'Estancia',
            'status' => 'Active',
        ]);
        $pin = ProgramInboundNumber::create([
            'number' => '09170001111',
            'program' => 'Globe Camp',
            'status' => 'Active',
            'campaign_id' => $campaign->id,
            'network' => 'Globe SIM',
            'mobile_numbers' => ['09170001111'],
            'media_gateway_id' => $gateway->id,
            'mobile_assignments' => [[
                'mobile' => '09170001111',
                'hostname' => 'old-host',
                'port' => '1',
                'media_gateway_id' => $gateway->id,
            ]],
        ]);

        $gateway->update([
            'hostname' => 'new-host',
            'ip_address' => '10.9.9.9',
            'site_code' => 'EST-2',
            'site_name' => 'Alcar',
            'channel_count' => 6,
            'username' => 'root',
            'database' => 'asteriskcdrdb',
        ]);

        $allocation->refresh();
        $sim->refresh();
        $port->refresh();
        $prefix->refresh();
        $campaign->refresh();
        $pin->refresh();

        $this->assertSame('new-host', $allocation->channel_allocation);
        $this->assertSame('new-host', $allocation->media_gateway);
        $this->assertSame(6, (int) $allocation->total_channel_allocated);
        $this->assertSame(6, (int) $campaign->total_channels_allocated);
        $this->assertSame('10.9.9.9', $sim->ip_address);
        $this->assertSame('EST-2', $port->gateway);
        $this->assertSame('Alcar', $prefix->gateway);
        $this->assertSame($gateway->id, $pin->media_gateway_id);
        $this->assertSame('new-host', $pin->mobile_assignments[0]['hostname']);
        $this->assertSame($gateway->id, $pin->mobile_assignments[0]['media_gateway_id']);
        $this->assertSame('new-host', $pin->mobileDisplayRows()[0]['hostname']);
    }
}
