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
use App\Services\XlsxService;
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
            ->assertSee('ETPI_A');
        $this->actingAs($this->admin)->get(route('campaigns'))
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

    public function test_deleting_a_sip_channel_removes_channel_allocation_copies_and_keeps_the_campaign(): void
    {
        $campaign = ChannelAllocationCampaign::create([
            'name' => 'Keep Camp',
            'listed_in_channel_allocation' => true,
        ]);
        $sip = SipChannel::create([
            'campaign_id' => $campaign->id,
            'etpi_sip_name' => 'ETPI_GONE',
            'channel_count' => 8,
            'network' => 'Eastern SIP',
        ]);
        $keepAllocation = ChannelAllocation::create([
            'campaign_id' => $campaign->id,
            'channel_allocation' => 'ETPI_KEEP',
            'network' => 'Eastern SIP',
            'total_channel_allocated' => 4,
        ]);
        $dropAllocation = ChannelAllocation::create([
            'campaign_id' => $campaign->id,
            'channel_allocation' => 'ETPI_GONE',
            'network' => 'Eastern SIP',
            'total_channel_allocated' => 8,
        ]);

        $this->actingAs($this->admin)->delete('/sip-channels/'.$sip->id)->assertRedirect();

        $this->assertDatabaseMissing('sip_channels', ['id' => $sip->id]);
        $this->assertDatabaseMissing('channel_allocations', ['id' => $dropAllocation->id]);
        $this->assertDatabaseHas('channel_allocations', ['id' => $keepAllocation->id, 'channel_allocation' => 'ETPI_KEEP']);
        $this->assertDatabaseHas('channel_allocation_campaigns', ['id' => $campaign->id, 'name' => 'Keep Camp']);
        $this->assertSame(4, (int) $campaign->fresh()->total_channels_allocated);

        $this->actingAs($this->admin)->get(route('channel-allocation'))
            ->assertOk()
            ->assertDontSee('ETPI_GONE')
            ->assertSee('ETPI_KEEP');
    }

    public function test_deleting_a_gsm_gateway_clears_dependents_without_removing_valid_records(): void
    {
        $campaign = ChannelAllocationCampaign::create([
            'name' => 'Globe Camp',
            'listed_in_channel_allocation' => true,
        ]);
        $keepGateway = MediaGateway::create([
            'hostname' => 'keep-host',
            'site_name' => 'CTN',
            'site_code' => 'CTN-1',
            'ip_address' => '10.2.2.2',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
            'channel_count' => 4,
            'network' => 'Globe SIM',
        ]);
        $gateway = MediaGateway::create([
            'hostname' => 'drop-host',
            'site_name' => 'Estancia',
            'site_code' => 'EST-DROP',
            'ip_address' => '10.1.1.1',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
            'channel_count' => 4,
            'network' => 'Globe SIM',
        ]);
        $allocation = ChannelAllocation::create([
            'campaign_id' => $campaign->id,
            'channel_allocation' => 'drop-host',
            'media_gateway' => 'drop-host',
            'network' => 'Globe SIM',
            'total_channel_allocated' => 4,
        ]);
        $keepAllocation = ChannelAllocation::create([
            'campaign_id' => $campaign->id,
            'channel_allocation' => 'keep-host',
            'media_gateway' => 'keep-host',
            'network' => 'Globe SIM',
            'total_channel_allocated' => 4,
        ]);
        $sim = GlobeSim::create([
            'imei' => '356938035643222',
            'mobile_number' => '09170002222',
            'ip_address' => '10.1.1.1',
            'location' => 'Estancia',
            'status' => 'Active',
        ]);
        $gateway->assignments()->create([
            'sim_type' => 'globe',
            'sim_id' => $sim->id,
            'port' => 1,
        ]);
        $port = ChannelPort::create([
            'port_number' => 5070,
            'gateway' => 'EST-DROP',
            'status' => 'Active',
        ]);
        $prefix = NetworkPrefix::create([
            'network' => 'Globe',
            'prefix' => '918',
            'gateway' => 'Estancia',
            'status' => 'Active',
        ]);
        $pin = ProgramInboundNumber::create([
            'number' => '09170002222',
            'program' => 'Globe Camp',
            'status' => 'Active',
            'campaign_id' => $campaign->id,
            'network' => 'Globe SIM',
            'mobile_numbers' => ['09170002222'],
            'media_gateway_id' => $gateway->id,
            'mobile_assignments' => [[
                'mobile' => '09170002222',
                'hostname' => 'drop-host',
                'port' => '1',
                'media_gateway_id' => $gateway->id,
            ]],
        ]);

        $this->actingAs($this->admin)->deleteJson('/gsm-gateways/'.$gateway->id)->assertOk();

        $this->assertDatabaseMissing('media_gateways', ['id' => $gateway->id]);
        $this->assertDatabaseHas('media_gateways', ['id' => $keepGateway->id, 'hostname' => 'keep-host']);
        $this->assertDatabaseMissing('channel_allocations', ['id' => $allocation->id]);
        $this->assertDatabaseHas('channel_allocations', ['id' => $keepAllocation->id, 'channel_allocation' => 'keep-host']);
        $this->assertDatabaseHas('channel_allocation_campaigns', ['id' => $campaign->id, 'name' => 'Globe Camp']);
        $this->assertDatabaseHas('globe_sims', ['id' => $sim->id, 'imei' => '356938035643222']);
        $this->assertNull($sim->fresh()->ip_address);
        $this->assertDatabaseHas('program_inbound_numbers', ['id' => $pin->id]);
        $pin->refresh();
        $this->assertNull($pin->media_gateway_id);
        $this->assertSame('', $pin->mobile_assignments[0]['hostname']);
        $this->assertNull($pin->mobile_assignments[0]['media_gateway_id']);
        $this->assertSame('—', $pin->gatewayLabel());
        $this->assertNull($port->fresh()->gateway);
        $this->assertNull($prefix->fresh()->gateway);

        $globePage = $this->actingAs($this->admin)->get('/globe-sim')->assertOk()->getContent();
        $this->assertStringNotContainsString('10.1.1.1', $globePage);
        $this->assertStringContainsString('10.2.2.2', $globePage);

        $pinPage = $this->actingAs($this->admin)->get('/program-inbound-numbers')->assertOk()->getContent();
        $this->assertStringNotContainsString('drop-host', $pinPage);

        $caPage = $this->actingAs($this->admin)->get('/channel-allocation')->assertOk()->getContent();
        $this->assertStringNotContainsString('drop-host', $caPage);
        $this->assertStringContainsString('keep-host', $caPage);

        $xlsx = app(XlsxService::class);
        $pinExport = $this->get('/program-inbound-numbers/export')->assertOk();
        [, $pinRows] = $xlsx->read($pinExport->getFile()->getPathname());
        $pinFlat = strtolower(implode(' ', array_map(static fn ($row) => implode(' ', $row), $pinRows)));
        $this->assertStringNotContainsString('drop-host', $pinFlat);

        $globeExport = $this->get('/globe-sim/export')->assertOk();
        [, $globeRows] = $xlsx->read($globeExport->getFile()->getPathname());
        $globeFlat = implode(' ', array_map(static fn ($row) => implode(' ', $row), $globeRows));
        $this->assertStringNotContainsString('10.1.1.1', $globeFlat);

        $caExport = $this->get('/channel-allocation/export')->assertOk();
        [, $caRows] = $xlsx->read($caExport->getFile()->getPathname());
        $caFlat = strtolower(implode(' ', array_map(static fn ($row) => implode(' ', $row), $caRows)));
        $this->assertStringNotContainsString('drop-host', $caFlat);
        $this->assertStringContainsString('keep-host', $caFlat);
    }

    public function test_editing_or_deleting_a_sim_updates_program_inbound_numbers_immediately(): void
    {
        $this->actingAs($this->admin);

        $campaign = ChannelAllocationCampaign::create(['name' => 'PIN Sync Camp']);
        MediaGateway::create([
            'hostname' => 'GSM-SYNC-1',
            'site_name' => 'Alcar',
            'site_code' => 'SYNC-1',
            'ip_address' => '10.80.1.1',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
            'channel_count' => 16,
        ]);
        MediaGateway::create([
            'hostname' => 'GSM-SYNC-2',
            'site_name' => 'CTN',
            'site_code' => 'SYNC-2',
            'ip_address' => '10.80.1.2',
            'username' => 'root',
            'database' => 'asteriskcdrdb',
            'channel_count' => 16,
        ]);

        $this->post('/globe-sim', [
            'imei' => '356938035650001',
            'mobile_number' => '09170003333',
            'plan' => 'Plan A',
            'ip_address' => '10.80.1.1',
            'port' => 6,
            'account_number' => 'ACC-SYNC',
            'contract_start' => '1/1/2026',
            'contract_end' => '12/1/2026',
        ])->assertRedirect();

        $sim = GlobeSim::query()->firstOrFail();
        $pin = ProgramInboundNumber::create([
            'number' => '09170003333',
            'program' => 'PIN Sync Camp',
            'status' => 'Active',
            'campaign_id' => $campaign->id,
            'network' => 'Globe SIM',
            'mobile_numbers' => ['09170003333'],
            'mobile_assignments' => [[
                'mobile' => '09170003333',
                'hostname' => 'GSM-SYNC-1',
                'port' => '6',
                'media_gateway_id' => MediaGateway::query()->where('hostname', 'GSM-SYNC-1')->value('id'),
            ]],
            'port' => '6',
        ]);
        $pinId = $pin->id;

        $this->put('/globe-sim/'.$sim->id, [
            'imei' => '356938035650001',
            'mobile_number' => '09170004444',
            'plan' => 'Plan B',
            'ip_address' => '10.80.1.2',
            'port' => 11,
            'account_number' => 'ACC-SYNC-EDIT',
            'contract_start' => '2/1/2026',
            'contract_end' => '11/1/2026',
        ])->assertRedirect();

        $sim->refresh();
        $pin->refresh();
        $this->assertSame($pinId, $pin->id);
        $this->assertSame('09170004444', $sim->mobile_number);
        $this->assertSame(11, (int) $sim->port);
        $this->assertSame('Plan B', $sim->plan);
        $this->assertSame('ACC-SYNC-EDIT', $sim->account_number);
        $this->assertSame(['09170004444'], $pin->mobile_numbers);
        $this->assertSame('09170004444', $pin->number);
        $this->assertSame('11', (string) $pin->port);
        $this->assertSame('GSM-SYNC-2', $pin->mobileDisplayRows()[0]['hostname']);
        $this->assertSame('11', $pin->mobileDisplayRows()[0]['port']);
        $this->assertSame('GSM-SYNC-2', $pin->gatewayLabel());
        $this->assertSame('11', $pin->portLabel());

        $pinPage = $this->get('/program-inbound-numbers')->assertOk()->getContent();
        $this->assertStringContainsString('09170004444', $pinPage);
        $this->assertStringNotContainsString('09170003333', $pinPage);
        $this->assertStringContainsString('GSM-SYNC-2', $pinPage);
        $this->assertStringContainsString('>11<', $pinPage);

        $xlsx = app(XlsxService::class);
        $export = $this->get('/program-inbound-numbers/export')->assertOk();
        [, $rows] = $xlsx->read($export->getFile()->getPathname());
        $this->assertContains(['PIN Sync Camp', '09170004444', '', 'GSM-SYNC-2', '11', 'Globe SIM', ''], $rows);

        $this->delete('/globe-sim/'.$sim->id)->assertRedirect();
        $this->assertDatabaseMissing('globe_sims', ['id' => $sim->id]);
        $pin->refresh();
        $this->assertSame($pinId, $pin->id);
        $this->assertSame(['09170004444'], $pin->mobile_numbers);
        $this->assertSame('', $pin->mobileDisplayRows()[0]['hostname']);
        $this->assertSame('', $pin->mobileDisplayRows()[0]['port']);
        $this->assertSame('—', $pin->gatewayLabel());
        $this->assertSame('—', $pin->portLabel());
    }
}
