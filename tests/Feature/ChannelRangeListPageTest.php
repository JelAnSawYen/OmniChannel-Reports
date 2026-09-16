<?php

namespace Tests\Feature;

use App\Models\ChannelAllocationCampaign;
use App\Models\SipChannel;
use App\Models\SipChannelNumber;
use App\Models\User;
use App\Models\UserType;
use App\Services\XlsxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ChannelRangeListPageTest extends TestCase
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

    public function test_page_uses_campaign_and_sip_name_with_pdc_style_expander(): void
    {
        $this->actingAs($this->admin);
        $atome = ChannelAllocationCampaign::create(['name' => 'Atome']);
        $sip = SipChannel::create([
            'campaign_id' => $atome->id,
            'etpi_sip_name' => 'SIP_ATOME_01',
        ]);
        SipChannelNumber::create([
            'sip_channel_id' => $sip->id,
            'channel_number' => '253235320',
        ]);

        $page = $this->get('/channel-range-list')->assertOk();
        $html = $page->getContent();

        $page->assertSee('Channel Range List')
            ->assertSee('Manage channel numbers for each SIP channel.')
            ->assertSee('>Campaign</th>', false)
            ->assertSee('>SIP Name</th>', false)
            ->assertSee('class="crl-toggle-col"', false)
            ->assertSee('data-ca-toggle="'.$sip->id.'"', false)
            ->assertSee('SIP_ATOME_01')
            ->assertSee('Atome')
            ->assertSee('>Channel Number</th>', false)
            ->assertSee('253235320')
            ->assertSee('class="action-btn edit"', false)
            ->assertSee('class="action-btn delete"', false)
            ->assertSee('id="crl_sip_channel_id"', false)
            ->assertSee('id="crl_sip_name"', false)
            ->assertSee('readonly', false)
            ->assertSee('disabled', false)
            ->assertSee('id="crl_from"', false)
            ->assertSee('id="crl_to"', false)
            ->assertSee('data-sip-name="SIP_ATOME_01"', false)
            ->assertSee('data-confirm-title="Delete Record"', false)
            ->assertDontSee('required-asterisk')
            ->assertDontSee('class="ca-menu-btn"', false);

        $this->assertStringContainsString('class="crl-toggle-col"', $html);
        $mainHead = \Illuminate\Support\Str::betweenFirst($html, 'class="ca-table crl-table"', '</thead>');
        $this->assertTrue(strpos($mainHead, 'class="crl-toggle-col"') < strpos($mainHead, '>Campaign</th>'));
        $this->assertTrue(strpos($mainHead, '>Campaign</th>') < strpos($mainHead, '>SIP Name</th>'));
        $this->assertStringContainsString('crl-campaign-col', $mainHead);
        $this->assertStringContainsString('crl-channel-col', $mainHead);
        $this->assertStringContainsString('crl-sip-col', $mainHead);
        $this->assertStringContainsString('crl-actions-col', $mainHead);
        $this->assertTrue(strpos($mainHead, 'crl-campaign-col') < strpos($mainHead, 'crl-channel-col'));
        $this->assertTrue(strpos($mainHead, 'crl-channel-col') < strpos($mainHead, 'crl-sip-col'));
        $this->assertTrue(strpos($mainHead, 'crl-sip-col') < strpos($mainHead, 'crl-actions-col'));
        $this->assertStringNotContainsString('Channel Number', $mainHead);
        $this->assertStringNotContainsString('Actions', $mainHead);
        $this->assertStringContainsString('id="sipChannelsGroup"', $html);
        $this->assertStringContainsString('id="sipChannelsCaret"', $html);
        $this->assertStringContainsString('>Channel Range List</span></a>', $html);
        $this->assertStringContainsString('href="'.url('/sip-channels').'"', $html);
        $this->assertDoesNotMatchRegularExpression('/id="sipChannelsSub"[^>]*>[\s\S]*?<span>SIP Channels<\/span>/', $html);
        $this->assertStringContainsString('id="crl_sip_name" readonly disabled', $html);

        $nested = \Illuminate\Support\Str::betweenFirst($html, 'class="crl-nested"', '</table>');
        $this->assertStringContainsString('Channel Number', $nested);
        $this->assertStringContainsString('Actions', $nested);
        $this->assertStringContainsString('crl-channel-col', $nested);
        $this->assertTrue(strpos($nested, 'Channel Number') < strpos($nested, 'Actions'));
        $this->assertStringNotContainsString('SIP Name', $nested);
        $this->assertStringNotContainsString('SIP_ATOME_01', $nested);
        $this->assertStringNotContainsString('ca-menu-btn', $nested);
        $this->assertStringNotContainsString('>ID</th>', $nested);
        $this->assertStringNotContainsString('>Status</th>', $nested);
    }

    public function test_from_to_creates_actual_channel_records_and_blocks_duplicates(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create(['name' => 'Atome']);
        $sip = SipChannel::create([
            'campaign_id' => $campaign->id,
            'etpi_sip_name' => 'SIP_ATOME_01',
        ]);

        $this->post('/channel-range-list', [
            'sip_channel_id' => $sip->id,
            'from' => '253235320',
            'to' => '253235333',
        ])->assertRedirect();

        $this->assertSame(14, SipChannelNumber::count());
        $this->assertSame('253235320', SipChannelNumber::orderBy('channel_number')->value('channel_number'));
        $this->assertTrue(SipChannelNumber::where('channel_number', '253235333')->where('sip_channel_id', $sip->id)->exists());
        $this->assertSame(14, SipChannelNumber::where('sip_channel_id', $sip->id)->count());

        $this->from('/channel-range-list')->post('/channel-range-list', [
            'sip_channel_id' => $sip->id,
            'from' => '253235333',
            'to' => '253235334',
        ])->assertRedirect('/channel-range-list')->assertSessionHasErrors('from');

        $this->assertSame(14, SipChannelNumber::count());
    }

    public function test_edit_and_delete_actual_channel_number(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create(['name' => 'BPI']);
        $sip = SipChannel::create([
            'campaign_id' => $campaign->id,
            'etpi_sip_name' => 'SIP_BPI_01',
        ]);
        $first = SipChannelNumber::create([
            'sip_channel_id' => $sip->id,
            'channel_number' => '100',
        ]);
        SipChannelNumber::create([
            'sip_channel_id' => $sip->id,
            'channel_number' => '101',
        ]);

        $this->put('/channel-range-list/'.$first->id, [
            'channel_number' => '199',
        ])->assertRedirect();
        $this->assertDatabaseHas('sip_channel_numbers', [
            'id' => $first->id,
            'sip_channel_id' => $sip->id,
            'channel_number' => '199',
        ]);

        $this->from('/channel-range-list')->put('/channel-range-list/'.$first->id, [
            'channel_number' => '101',
        ])->assertRedirect('/channel-range-list')->assertSessionHasErrors('channel_number');

        $this->delete('/channel-range-list/'.$first->id)->assertRedirect();
        $this->assertDatabaseMissing('sip_channel_numbers', ['id' => $first->id]);
        $this->assertDatabaseHas('sip_channel_numbers', ['channel_number' => '101']);
    }

    public function test_import_export_and_template_use_actual_channel_records(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create(['name' => 'Atome']);
        $sip = SipChannel::create([
            'campaign_id' => $campaign->id,
            'etpi_sip_name' => 'SIP_ATOME_01',
        ]);

        $page = $this->get('/channel-range-list')
            ->assertOk()
            ->assertSee('Data Transfer')
            ->assertSee('Import Data')
            ->assertSee('Export Data')
            ->assertDontSee('Download Sample Template')
            ->assertSee('Download Excel Template')
            ->assertSee(route('channel-range-list.export'), false);
        $html = $page->getContent();
        $this->assertStringContainsString('const previewUrl', $html);
        $this->assertStringContainsString('channel-range-list\\/import\\/preview', $html);
        $this->assertStringContainsString('channel-range-list\\/import\\/confirm', $html);
        $this->assertStringContainsString('["campaign","channel_number"]', $html);

        $template = $this->get('/channel-range-list/import/template')->assertOk()->assertDownload('channel-range-list-template.xlsx');
        [$headers, $rows] = app(XlsxService::class)->read($template->getFile()->getPathname());
        $this->assertSame(['Campaign', 'Channel Number'], $headers);
        $this->assertNotContains('SIP Name', $headers);
        $this->assertNotContains('Id', $headers);
        $this->assertNotContains('From', $headers);
        $this->assertNotContains('To', $headers);
        foreach ($rows as $row) {
            $this->assertSame(['', ''], array_pad($row, 2, ''));
        }

        $preview = $this->postJson('/channel-range-list/import/preview', [
            'file' => $this->upload($this->spreadsheet([
                ['Campaign', 'Channel Number'],
                ['Atome', '253235320'],
                ['Unknown', '253235321'],
            ])),
        ])->assertOk()->json();
        $this->assertFalse($preview['valid']);
        $this->assertTrue($preview['rows'][0]['valid']);
        $this->assertFalse($preview['rows'][1]['valid']);
        $this->assertStringContainsString('SIP Channel', $preview['rows'][1]['error']);

        $ok = $this->postJson('/channel-range-list/import/preview', [
            'file' => $this->upload($this->spreadsheet([
                ['Campaign', 'Channel Number'],
                ['Atome', '253235320'],
                ['Atome', '253235321'],
            ])),
        ])->assertOk()->json();
        $this->assertTrue($ok['valid'], $ok['rows'][0]['error'] ?? '');
        $this->postJson('/channel-range-list/import/confirm', ['token' => $ok['token']])
            ->assertOk()
            ->assertJson(['ok' => true, 'records' => 2]);

        $this->assertSame(2, SipChannelNumber::count());
        $this->assertTrue(SipChannelNumber::where('channel_number', '253235320')->where('sip_channel_id', $sip->id)->exists());
        $this->assertTrue(SipChannelNumber::where('channel_number', '253235321')->where('sip_channel_id', $sip->id)->exists());
        $this->assertSame('SIP_ATOME_01', SipChannelNumber::where('channel_number', '253235320')->first()?->sipChannel?->etpi_sip_name);
        $this->assertSame('Atome', SipChannelNumber::where('channel_number', '253235320')->first()?->sipChannel?->campaign?->name);

        $dup = $this->postJson('/channel-range-list/import/preview', [
            'file' => $this->upload($this->spreadsheet([
                ['Campaign', 'Channel Number'],
                ['Atome', '253235320'],
            ])),
        ])->assertOk()->json();
        $this->assertFalse($dup['valid']);
        $this->assertStringContainsString('already exists', $dup['rows'][0]['error']);

        $export = $this->get('/channel-range-list/export')->assertOk()->assertDownload('channel-range-list.xlsx');
        [$exportHeaders, $exportRows] = app(XlsxService::class)->read($export->getFile()->getPathname());
        $this->assertSame(['Campaign', 'SIP Name', 'Channel Number'], $exportHeaders);
        $this->assertContains(['Atome', 'SIP_ATOME_01', '253235320'], $exportRows);
    }

    public function test_standard_user_cannot_access_channel_range_list(): void
    {
        $this->actingAs($this->standard)->get('/channel-range-list')->assertForbidden();
        $this->actingAs($this->standard)->post('/channel-range-list', [])->assertForbidden();
        $this->actingAs($this->standard)->get('/channel-range-list/export')->assertForbidden();
        $this->actingAs($this->standard)->get('/channel-range-list/import/template')->assertForbidden();
    }

    /**
     * @param  list<list<string|int>>  $rows
     */
    private function spreadsheet(array $rows): string
    {
        $headers = array_shift($rows);

        return app(XlsxService::class)->export($headers, $rows, 'crl-import.xlsx');
    }

    private function upload(string $path): UploadedFile
    {
        return new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
