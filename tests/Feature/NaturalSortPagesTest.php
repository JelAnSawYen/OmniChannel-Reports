<?php

namespace Tests\Feature;

use App\Models\ChannelAllocationCampaign;
use App\Models\MediaGateway;
use App\Models\ProgramInboundNumber;
use App\Models\SipChannel;
use App\Models\SipChannelNumber;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NaturalSortPagesTest extends TestCase
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
        $this->actingAs($this->admin);
    }

    public function test_sip_channels_use_natural_sip_name_order_including_pagination_and_dropdowns(): void
    {
        $campaigns = $this->createNamedCampaigns();
        foreach (['GLOBE10', 'GLOBE02', 'GLOBE03', 'GLOBE01'] as $name) {
            SipChannel::create([
                'campaign_id' => $campaigns['GLOBE01']->id,
                'etpi_sip_name' => $name,
                'network' => 'ETPI',
            ]);
        }

        $html = $this->get('/sip-channels')->assertOk()->getContent();
        $this->assertSame(
            ['GLOBE01', 'GLOBE02', 'GLOBE03', 'GLOBE10'],
            $this->captured($html, '/class="sip-cell sip-name">([^<]+)/')
        );
        $this->assertSame(
            ['GLOBE01', 'GLOBE02', 'GLOBE03', 'GLOBE10'],
            $this->captured($html, '/class="pin-campaign-option"[^>]*data-name="([^"]+)"/')
        );

        SipChannel::create([
            'campaign_id' => $campaigns['GLOBE01']->id,
            'etpi_sip_name' => 'GLOBE11',
            'network' => 'ETPI',
        ]);
        SipChannel::create([
            'campaign_id' => $campaigns['GLOBE01']->id,
            'etpi_sip_name' => 'GLOBE12',
            'network' => 'ETPI',
        ]);

        $pageOne = $this->get('/sip-channels?per_page=5')->assertOk()->getContent();
        $this->assertSame(
            ['GLOBE01', 'GLOBE02', 'GLOBE03', 'GLOBE10', 'GLOBE11'],
            $this->captured($pageOne, '/class="sip-cell sip-name">([^<]+)/')
        );
        $this->assertStringNotContainsString('>GLOBE12</span>', $pageOne);

        $pageTwo = $this->get('/sip-channels?per_page=5&page=2')->assertOk()->getContent();
        $this->assertSame(
            ['GLOBE12'],
            $this->captured($pageTwo, '/class="sip-cell sip-name">([^<]+)/')
        );
    }

    public function test_channel_range_and_allocation_dropdowns_follow_natural_sip_and_hostname_order(): void
    {
        $campaign = ChannelAllocationCampaign::create([
            'name' => 'GLOBE01',
            'listed_in_channel_allocation' => true,
            'sort_order' => 1,
        ]);
        foreach (['GLOBE10', 'GLOBE02', 'GLOBE03', 'GLOBE01'] as $index => $name) {
            $sip = SipChannel::create([
                'campaign_id' => $campaign->id,
                'etpi_sip_name' => $name,
                'network' => 'ETPI',
            ]);
            SipChannelNumber::create([
                'sip_channel_id' => $sip->id,
                'channel_number' => (string) (100 + $index),
            ]);
            MediaGateway::create([
                'hostname' => $name,
                'site_name' => 'Alcar',
                'site_code' => $name,
                'ip_address' => '10.0.0.'.($index + 1),
                'username' => 'root',
                'database' => 'asteriskcdrdb',
            ]);
        }

        $range = $this->get('/channel-range-list')->assertOk()->getContent();
        $this->assertSame(
            ['GLOBE01', 'GLOBE02', 'GLOBE03', 'GLOBE10'],
            $this->captured($range, '/class="ca-campaign-link crl-sip-name">([^<]+)/')
        );

        $allocation = $this->get('/channel-allocation')->assertOk()->getContent();
        $this->assertSame(
            ['GLOBE01', 'GLOBE02', 'GLOBE03', 'GLOBE10'],
            $this->jsonList($allocation, 'sipChannelOptions')
        );
        $this->assertSame(
            ['GLOBE01', 'GLOBE02', 'GLOBE03', 'GLOBE10'],
            $this->jsonList($allocation, 'gsmChannelOptions')
        );
    }

    public function test_gsm_gateways_default_to_natural_hostname_order(): void
    {
        foreach (['GLOBE10', 'GLOBE02', 'GLOBE03', 'GLOBE01'] as $index => $name) {
            MediaGateway::create([
                'hostname' => $name,
                'site_name' => 'Alcar',
                'site_code' => 'CODE'.$index,
                'ip_address' => '10.1.1.'.($index + 1),
                'username' => 'root',
                'database' => 'asteriskcdrdb',
            ]);
        }

        $this->getJson('/gsm-gateways?per_page=10')
            ->assertOk()
            ->assertJsonPath('records.0.hostname', 'GLOBE01')
            ->assertJsonPath('records.1.hostname', 'GLOBE02')
            ->assertJsonPath('records.2.hostname', 'GLOBE03')
            ->assertJsonPath('records.3.hostname', 'GLOBE10');
    }

    public function test_program_inbound_numbers_and_campaigns_use_natural_campaign_order(): void
    {
        $campaigns = $this->createNamedCampaigns();
        foreach (['GLOBE10', 'GLOBE02', 'GLOBE03', 'GLOBE01'] as $name) {
            ProgramInboundNumber::create([
                'number' => $name,
                'program' => $name,
                'status' => 'Active',
                'campaign_id' => $campaigns[$name]->id,
                'landline_numbers' => ['111'],
            ]);
        }

        $pin = $this->get('/program-inbound-numbers')->assertOk()->getContent();
        $this->assertSame(
            ['GLOBE01', 'GLOBE02', 'GLOBE03', 'GLOBE10'],
            $this->captured($pin, '/class="pin-campaign">([^<]+)/')
        );
        $this->assertSame(
            ['GLOBE01', 'GLOBE02', 'GLOBE03', 'GLOBE10'],
            $this->captured($pin, '/class="pin-campaign-option"[^>]*data-name="([^"]+)"/')
        );

        $campaignsPage = $this->get('/campaigns')->assertOk()->getContent();
        $this->assertSame(
            ['GLOBE01', 'GLOBE02', 'GLOBE03', 'GLOBE10'],
            $this->captured($campaignsPage, '/class="campaigns-name">([^<]+)/')
        );
    }

    /**
     * @return array<string, ChannelAllocationCampaign>
     */
    private function createNamedCampaigns(): array
    {
        $campaigns = [];
        foreach (['GLOBE10', 'GLOBE02', 'GLOBE03', 'GLOBE01'] as $index => $name) {
            $campaigns[$name] = ChannelAllocationCampaign::create([
                'name' => $name,
                'listed_in_channel_allocation' => true,
                'sort_order' => $index + 1,
            ]);
        }

        return $campaigns;
    }

    /**
     * @return list<string>
     */
    private function captured(string $html, string $pattern): array
    {
        preg_match_all($pattern, $html, $matches);

        return array_values($matches[1] ?? []);
    }

    /**
     * @return list<string>
     */
    private function jsonList(string $html, string $variable): array
    {
        $this->assertTrue(preg_match('/const '.$variable.' = (\[.*?\]);/s', $html, $match) === 1);
        $rows = json_decode($match[1], true);
        $this->assertIsArray($rows);

        return array_values(array_map(fn (array $row) => (string) $row['value'], $rows));
    }
}
