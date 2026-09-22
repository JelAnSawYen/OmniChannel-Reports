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
use Illuminate\Support\Str;
use Tests\TestCase;

class SipChannelsPageTest extends TestCase
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

    public function test_page_shows_only_sip_channel_fields_and_centered_table(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create(['name' => 'Mynt']);
        SipChannel::create([
            'campaign_id' => $campaign->id,
            'etpi_sip_name' => 'ETPI_53235320',
            'pilot_number' => '253235320',
            'channel_count' => 14,
            'channel_range' => '253235320 - 253235333',
            'network' => 'ETPI',
            'date_activation' => '2026-07-09',
        ]);

        $page = $this->get('/sip-channels')->assertOk();
        $html = $page->getContent();
        $css = file_get_contents(resource_path('css/app.css'));
        $sipTable = Str::between($html, 'aria-label="SIP Channels"', '</table>');
        $this->assertStringNotContainsString('<th>Campaign</th>', $sipTable);
        $this->assertStringContainsString('<th>SIP Name</th>', $sipTable);

        $page->assertSee('>SIP Name</th>', false)
            ->assertSee('Pilot Number')
            ->assertSee('Channel Count')
            ->assertSee('Channel Range')
            ->assertSee('Network')
            ->assertSee('Date Activation')
            ->assertSee('ETPI_53235320')
            ->assertSee('253235320 - 253235333')
            ->assertSee('7/9/2026')
            ->assertSee('class="sip-cell sip-name"', false)
            ->assertSee('class="sip-table"', false)
            ->assertDontSee('class="sip-cell sip-campaign"', false)
            ->assertSee('id="sipAddButton"', false)
            ->assertSee('id="sipSubmit">Save', false)
            ->assertDontSee('for="sip_campaign_id"', false)
            ->assertDontSee('id="sip_campaign_id"', false)
            ->assertDontSee('sipCampaignMenu', false)
            ->assertDontSee('>Add</button>', false)
            ->assertDontSee('Save SIP')
            ->assertDontSee('Last Updated')
            ->assertDontSee('All Statuses')
            ->assertDontSee('>Peer<')
            ->assertDontSee('>Codec<');

        $this->assertStringNotContainsString('id="sipAddButton">Add', $html);
        $this->assertStringContainsString('sipSubmit">Save', $html);
        $this->assertStringNotContainsString('sipSubmit">Save ', $html);
        $this->assertStringContainsString('.sip-table > thead > tr > th', $css);
        $this->assertMatchesRegularExpression('/\.sip-table \{\s*table-layout: fixed;\s*width: 100%;/', $css);
        $this->assertDoesNotMatchRegularExpression('/th[^>]*(sip-campaign|sip-name|color:\s*#0066FF)/', $html);
        $this->assertStringContainsString('.sip-name', $css);
        $this->assertStringNotContainsString('.sip-campaign', $css);
        $this->assertStringContainsString('color: #0066FF', $css);
        $this->assertMatchesRegularExpression('/\.sip-name\s*\{[^}]*font-weight:\s*700/', $css);
        $this->assertMatchesRegularExpression('/\.sip-table > thead > tr > th:first-child,\s*\.sip-table > tbody > tr > td:first-child \{\s*text-align: left;\s*padding-left: 23px;/', $css);
        $this->assertMatchesRegularExpression('/\.sip-table > tbody > tr > td \.sip-name \{\s*color: #0066FF;\s*font-weight: 700;\s*justify-content: flex-start;\s*text-align: left;/', $css);
        $this->assertStringContainsString('pdc-date-field', $html);
        $this->assertStringContainsString('id="sip_date_activation"', $html);
        $this->assertStringContainsString('min="2000-01-01"', $html);
        $this->assertStringContainsString("cal?.removeAttribute('hidden')", $html);
        $this->assertStringNotContainsString("cal.style.position = 'fixed'", $html);
        $this->assertStringNotContainsString('document.body.appendChild(cal)', $html);
        $this->assertStringContainsString('top:calc(100% + 5px)', $css);
        $this->assertMatchesRegularExpression('/\.pdc-cal-year\s*\{[^}]*color:\s*#000/', $css);
        $this->assertMatchesRegularExpression('/\.pdc-cal-month\s*\{[^}]*color:\s*#000/', $css);
        $this->assertMatchesRegularExpression('/body\[data-page="sip-channels"\] #sipModal\.modal-backdrop\.visible \{\s*align-items: flex-start;\s*justify-content: center;\s*overflow-y: auto;/', $css);
        $this->assertMatchesRegularExpression('/body\[data-page="sip-channels"\] #sipModal \.modal \{\s*margin: 30px auto;\s*overflow: visible;/', $css);
        $this->assertMatchesRegularExpression('/body\[data-page="sip-channels"\] #sipModal \.modal-body \{\s*overflow: visible;/', $css);
        $this->assertMatchesRegularExpression('/body\[data-page="sip-channels"\] #sipCal\.pdc-cal \{\s*overflow: visible;/', $css);
        $this->assertStringContainsString('id="sip_from"', $html);
        $this->assertStringContainsString('id="sip_to"', $html);
        $this->assertStringContainsString('for="sip_from">From</label>', $html);
        $this->assertStringContainsString('for="sip_to">To</label>', $html);
        $this->assertTrue(strpos($html, 'form-group full') < strpos($html, 'for="sip_from">From</label>'));
        $this->assertTrue(strpos($html, '<label>Channel Range</label>') < strpos($html, 'for="sip_from">From</label>'));
        $this->assertTrue(strpos($html, '<label>Channel Range</label>') < strpos($html, 'for="sip_to">To</label>'));
        $this->assertStringContainsString('splitChannelRange', $html);
        $this->assertStringNotContainsString('id="sip_channel_range"', $html);
        $this->assertStringNotContainsString('name="channel_range"', $html);
        $this->assertStringNotContainsString('fillAddChannelRange', $html);
    }

    public function test_add_edit_delete_search_and_pagination(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create(['name' => 'Mynt']);
        $other = ChannelAllocationCampaign::create(['name' => 'Atome']);

        $this->post('/sip-channels', [
            'campaign_id' => $campaign->id,
            'etpi_sip_name' => 'ETPI_ALPHA',
            'pilot_number' => '111',
            'channel_count' => 10,
            'from' => '111',
            'to' => '120',
            'network' => 'ETPI',
            'date_activation' => '7/9/2026',
        ])->assertRedirect();

        $this->post('/sip-channels', [
            'campaign_id' => $other->id,
            'etpi_sip_name' => 'ETPI_BETA',
            'pilot_number' => '222',
            'channel_count' => 4,
            'from' => '222',
            'to' => '225',
            'network' => 'ETPI',
            'date_activation' => '8/1/2026',
        ])->assertRedirect();

        $record = SipChannel::where('etpi_sip_name', 'ETPI_ALPHA')->firstOrFail();
        $this->assertSame('2026-07-09', $record->date_activation->format('Y-m-d'));

        $this->post('/sip-channels', [
            'etpi_sip_name' => 'ETPI_TYPED',
            'channel_count' => 2,
            'network' => 'ETPI',
            'date_activation' => '8/2/2026',
        ])->assertRedirect();
        $this->assertDatabaseMissing('channel_allocation_campaigns', ['name' => 'Typed SIP Campaign']);
        $this->assertDatabaseHas('sip_channels', ['etpi_sip_name' => 'ETPI_TYPED']);

        $this->get('/sip-channels')
            ->assertOk()
            ->assertSee('data-id="'.$record->id.'"', false)
            ->assertSee('action="'.url('/sip-channels/'.$record->id).'"', false);

        $this->from('/sip-channels')->put('/sip-channels/'.$record->id, [
            'campaign_id' => $campaign->id,
            'etpi_sip_name' => 'ETPI_ALPHA_UPDATED',
            'pilot_number' => '111',
            'channel_count' => 12,
            'from' => '111',
            'to' => '122',
            'network' => 'ETPI',
            'date_activation' => '7/9/2026',
        ])->assertRedirect('/sip-channels')->assertSessionMissing('sip_edit');

        $this->assertDatabaseHas('sip_channels', [
            'id' => $record->id,
            'etpi_sip_name' => 'ETPI_ALPHA_UPDATED',
            'channel_count' => 12,
        ]);
        $this->assertDatabaseHas('sip_channels', ['etpi_sip_name' => 'ETPI_BETA']);

        $this->get('/sip-channels?search=ETPI_ALPHA_UPDATED')
            ->assertOk()
            ->assertSee('ETPI_ALPHA_UPDATED')
            ->assertDontSee('ETPI_BETA');

        for ($i = 1; $i <= 9; $i++) {
            SipChannel::create([
                'campaign_id' => $campaign->id,
                'etpi_sip_name' => 'ETPI_PAGE_'.$i,
                'network' => 'ETPI',
            ]);
        }

        $this->get('/sip-channels?per_page=5')
            ->assertOk()
            ->assertSee('page=2', false)
            ->assertSee('Records per page');

        $this->delete('/sip-channels/'.$record->id)->assertRedirect();
        $this->assertDatabaseMissing('sip_channels', ['id' => $record->id]);
        $this->assertDatabaseHas('sip_channels', ['etpi_sip_name' => 'ETPI_BETA']);
    }

    public function test_from_to_preserves_spaces_and_creates_channel_range(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create(['name' => 'Mynt']);

        $this->post('/sip-channels', [
            'campaign_id' => $campaign->id,
            'etpi_sip_name' => 'ETPI_SPACED',
            'from' => '25322 9170',
            'to' => '25322 9199',
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $sip = SipChannel::where('etpi_sip_name', 'ETPI_SPACED')->firstOrFail();
        $this->assertSame('25322 9170 - 25322 9199', $sip->channel_range);
        $this->assertSame(30, SipChannelNumber::where('sip_channel_id', $sip->id)->count());
        $this->assertDatabaseHas('sip_channel_numbers', [
            'sip_channel_id' => $sip->id,
            'channel_number' => '25322 9170',
        ]);
        $this->assertDatabaseHas('sip_channel_numbers', [
            'sip_channel_id' => $sip->id,
            'channel_number' => '25322 9199',
        ]);

        $page = $this->get('/sip-channels')->assertOk();
        $page->assertSee('Channel Range')
            ->assertSee('25322 9170 - 25322 9199')
            ->assertDontSee('>From</th>', false)
            ->assertDontSee('>To</th>', false);

        $range = $this->get('/channel-range-list')->assertOk();
        $range->assertSee('25322 9170 - 25322 9199')
            ->assertSee('ETPI_SPACED')
            ->assertDontSee('id="crlAddButton"', false)
            ->assertDontSee('id="crlAddModal"', false);

        $this->from('/sip-channels')->put('/sip-channels/'.$sip->id, [
            'campaign_id' => $campaign->id,
            'etpi_sip_name' => 'ETPI_SPACED',
            'from' => '25322 9170',
            'to' => '25322 9172',
        ])->assertRedirect('/sip-channels');

        $this->assertSame('25322 9170 - 25322 9172', $sip->fresh()->channel_range);
        $this->assertSame(['25322 9170', '25322 9171', '25322 9172'], SipChannelNumber::query()
            ->where('sip_channel_id', $sip->id)
            ->orderBy('channel_number')
            ->pluck('channel_number')
            ->all());
        $this->assertSame(1, SipChannel::where('etpi_sip_name', 'ETPI_SPACED')->count());

        $other = ChannelAllocationCampaign::create(['name' => 'Atome']);
        $this->from('/sip-channels')->post('/sip-channels', [
            'campaign_id' => $other->id,
            'etpi_sip_name' => 'ETPI_DUP',
            'from' => '25322 9170',
            'to' => '25322 9172',
        ])->assertRedirect('/sip-channels')->assertSessionHasErrors('from');
        $this->assertDatabaseMissing('sip_channels', ['etpi_sip_name' => 'ETPI_DUP']);
    }

    public function test_date_activation_accepts_valid_and_rejects_invalid_dates(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create(['name' => 'Mynt']);

        $this->post('/sip-channels', [
            'campaign_id' => $campaign->id,
            'etpi_sip_name' => 'ETPI_DATE_OK',
            'date_activation' => '7/9/2026',
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertSame('2026-07-09', SipChannel::firstOrFail()->date_activation->format('Y-m-d'));
        $this->get('/sip-channels')->assertSee('7/9/2026');

        foreach (['9/32/2026', '13/3/2026', 'abc', '12/31/1999'] as $invalid) {
            $this->from('/sip-channels')->post('/sip-channels', [
                'campaign_id' => $campaign->id,
                'etpi_sip_name' => 'ETPI_BAD_'.$invalid,
                'date_activation' => $invalid,
            ])->assertRedirect('/sip-channels')->assertSessionHasErrors('date_activation');
        }

        $this->post('/sip-channels', [
            'campaign_id' => $campaign->id,
            'etpi_sip_name' => 'ETPI_MIN_DATE',
            'date_activation' => '1/1/2000',
        ])->assertRedirect()->assertSessionDoesntHaveErrors();
        $this->assertSame('2000-01-01', SipChannel::where('etpi_sip_name', 'ETPI_MIN_DATE')->first()->date_activation->format('Y-m-d'));
    }

    public function test_import_matches_pdc_format_and_uses_sip_fields(): void
    {
        $this->actingAs($this->admin);
        ChannelAllocationCampaign::create(['name' => 'Mynt']);
        ChannelAllocationCampaign::create(['name' => 'Atome']);

        $this->get('/sip-channels')
            ->assertOk()
            ->assertSee('Data Transfer')
            ->assertSee('Import Data')
            ->assertSee('Export Data')
            ->assertDontSee('Download Sample Template')
            ->assertSee('Download Excel Template');

        $template = $this->get('/sip-channels/import/template')->assertOk()->assertDownload('sip-channels-template.xlsx');
        [$headers] = app(XlsxService::class)->read($template->getFile()->getPathname());
        $this->assertSame([
            'SIP Name',
            'Pilot Number',
            'Channel Count',
            'Channel Range',
            'Network',
            'Date Activation',
        ], $headers);
        $this->assertNotContains('Id', $headers);
        $this->assertNotContains('Campaign', $headers);

        $preview = $this->postJson('/sip-channels/import/preview', [
            'file' => $this->upload($this->spreadsheet([
                ['SIP Name', 'Pilot Number', 'Channel Count', 'Channel Range', 'Network', 'Date Activation'],
                ['ETPI_53235320', '253235320', '14', '253235320 - 253235333', 'ETPI', '7/9/2026'],
                ['ETPI_53235334', '253235334', '2', '253235334 - 253235335', 'ETPI', ''],
                ['ETPI_ATOME', '300', '3', '300 - 302', 'ETPI', '8/1/2026'],
            ])),
        ])->assertOk()->json();

        $this->assertTrue($preview['valid'], $preview['rows'][0]['error'] ?? '');
        $this->postJson('/sip-channels/import/confirm', ['token' => $preview['token']])
            ->assertOk()
            ->assertJson(['ok' => true, 'records' => 3]);

        $this->assertSame(3, SipChannel::count());
        $imported = SipChannel::where('etpi_sip_name', 'ETPI_53235320')->firstOrFail();
        $this->assertNull($imported->campaign_id);
        $this->assertSame('253235320', $imported->pilot_number);
        $this->assertSame(14, $imported->channel_count);
        $this->assertSame('253235320 - 253235333', $imported->channel_range);
        $this->assertSame('ETPI', $imported->network);
        $this->assertSame('2026-07-09', $imported->date_activation->format('Y-m-d'));
        $this->assertNull(SipChannel::where('etpi_sip_name', 'ETPI_53235334')->first()->campaign_id);

        $dup = $this->postJson('/sip-channels/import/preview', [
            'file' => $this->upload($this->spreadsheet([
                ['SIP Name', 'Pilot Number', 'Channel Count', 'Channel Range', 'Network', 'Date Activation'],
                ['ETPI_53235320', '1', '1', '1 - 1', 'ETPI', '7/9/2026'],
            ])),
        ])->assertOk()->json();
        $this->assertFalse($dup['valid']);
        $this->assertStringContainsString('already exists', $dup['rows'][0]['error']);

        $export = $this->get('/sip-channels/export')->assertOk()->assertDownload('sip-channels.xlsx');
        [$exportHeaders] = app(XlsxService::class)->read($export->getFile()->getPathname());
        $this->assertSame('SIP Name', $exportHeaders[0]);
        $this->assertContains('SIP Name', $exportHeaders);
        $this->assertContains('Date Activation', $exportHeaders);
        $this->assertNotContains('Campaign', $exportHeaders);
        $this->assertNotContains('Id', $exportHeaders);
    }

    public function test_import_confirm_reports_the_exact_channel_range_conflict(): void
    {
        $this->actingAs($this->admin);
        $this->post('/sip-channels', [
            'etpi_sip_name' => 'ETPI_EXISTING',
            'from' => '253235320',
            'to' => '253235321',
        ])->assertRedirect();

        $preview = $this->postJson('/sip-channels/import/preview', [
            'file' => $this->upload($this->spreadsheet([
                ['SIP Name', 'Pilot Number', 'Channel Count', 'Channel Range', 'Network', 'Date Activation'],
                ['ETPI_OVERLAP', '253235320', '2', '253235320 - 253235321', 'ETPI', ''],
            ])),
        ])->assertOk()->json();

        $this->assertFalse($preview['valid']);
        $this->assertStringContainsString('Channel number already exists', $preview['rows'][0]['error']);
        $this->assertStringContainsString('253235320', $preview['rows'][0]['error']);
        $this->assertStringNotContainsString('contact an administrator', $preview['rows'][0]['error']);

        $confirm = $this->postJson('/sip-channels/import/confirm', ['token' => $preview['token']])
            ->assertStatus(422)
            ->json();

        $this->assertStringNotContainsString('contact an administrator', $confirm['message']);
        $this->assertSame(0, SipChannel::where('etpi_sip_name', 'ETPI_OVERLAP')->count());
    }

    public function test_channel_range_accepts_optional_spaces_around_the_dash(): void
    {
        $this->actingAs($this->admin);
        foreach (['100-200', '100 - 200', '100- 200', '100 -200'] as $range) {
            [$from, $to] = SipChannel::boundsFromRange($range);
            $this->assertSame(['100', '200'], [$from, $to], $range);
        }

        $this->post('/sip-channels', [
            'etpi_sip_name' => 'RANGE_SPACES',
            'from' => '100',
            'to' => '200',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('100 - 200', SipChannel::where('etpi_sip_name', 'RANGE_SPACES')->value('channel_range'));

        $preview = $this->postJson('/sip-channels/import/preview', [
            'file' => $this->upload($this->spreadsheet([
                ['SIP Name', 'Pilot Number', 'Channel Count', 'Channel Range', 'Network', 'Date Activation'],
                ['RANGE_A', '', '', '300-310', '', ''],
                ['RANGE_B', '', '', '320 - 330', '', ''],
                ['RANGE_C', '', '', '340- 350', '', ''],
                ['RANGE_D', '', '', '360 -370', '', ''],
                ['RANGE_BAD', '', '', '400-', '', ''],
            ])),
        ])->assertOk()->json();

        $this->assertTrue($preview['rows'][0]['valid'], $preview['rows'][0]['error'] ?? '');
        $this->assertTrue($preview['rows'][1]['valid'], $preview['rows'][1]['error'] ?? '');
        $this->assertTrue($preview['rows'][2]['valid'], $preview['rows'][2]['error'] ?? '');
        $this->assertTrue($preview['rows'][3]['valid'], $preview['rows'][3]['error'] ?? '');
        $this->assertFalse($preview['rows'][4]['valid']);
        $this->assertNotSame('', $preview['rows'][4]['error']);

        $this->postJson('/sip-channels/import/confirm', ['token' => $preview['token']])->assertStatus(422);
        $this->assertSame(0, SipChannel::where('etpi_sip_name', 'RANGE_A')->count());

        $ok = $this->postJson('/sip-channels/import/preview', [
            'file' => $this->upload($this->spreadsheet([
                ['SIP Name', 'Pilot Number', 'Channel Count', 'Channel Range', 'Network', 'Date Activation'],
                ['RANGE_A', '', '', '300-310', '', ''],
                ['RANGE_B', '', '', '320 - 330', '', ''],
                ['RANGE_C', '', '', '340- 350', '', ''],
                ['RANGE_D', '', '', '360 -370', '', ''],
            ])),
        ])->assertOk()->json();
        $this->assertTrue($ok['valid'], $ok['rows'][0]['error'] ?? '');
        $this->postJson('/sip-channels/import/confirm', ['token' => $ok['token']])->assertOk();
        $this->assertSame('300 - 310', SipChannel::where('etpi_sip_name', 'RANGE_A')->value('channel_range'));
        $this->assertSame('360 - 370', SipChannel::where('etpi_sip_name', 'RANGE_D')->value('channel_range'));
    }

    public function test_standard_user_cannot_access_sip_channels(): void
    {
        $this->actingAs($this->standard)->get('/sip-channels')->assertForbidden();
        $this->actingAs($this->standard)->post('/sip-channels', [])->assertForbidden();
        $this->actingAs($this->standard)->get('/sip-channels/export')->assertForbidden();
        $this->actingAs($this->standard)->postJson('/sip-channels/import/preview', [])->assertForbidden();
        $this->actingAs($this->standard)->get('/sip-channels/import/template')->assertForbidden();
    }

    /**
     * @param  list<list<string|int>>  $rows
     */
    private function spreadsheet(array $rows): string
    {
        $headers = array_shift($rows);

        return app(XlsxService::class)->export($headers, $rows, 'sip-import.xlsx');
    }

    private function upload(string $path): UploadedFile
    {
        return new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
