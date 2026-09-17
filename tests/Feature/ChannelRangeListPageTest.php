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
            'channel_number' => '24556',
        ]);
        SipChannelNumber::create([
            'sip_channel_id' => $sip->id,
            'channel_number' => '457864',
        ]);
        $empty = ChannelAllocationCampaign::create(['name' => 'PNB Collection/ Telesales']);
        $emptySip = SipChannel::create([
            'campaign_id' => $empty->id,
            'etpi_sip_name' => '1345665',
            'channel_range' => '30001 - 30500',
        ]);

        $page = $this->get('/channel-range-list')->assertOk();
        $html = $page->getContent();

        $page->assertSee('<h1 class="page-title">Channel Range</h1>', false)
            ->assertSee('Manage channel numbers for each SIP channel.')
            ->assertSee('>Campaign</th>', false)
            ->assertSee('>Channel Range</th>', false)
            ->assertSee('>SIP Name</th>', false)
            ->assertSee('>Actions</th>', false)
            ->assertSee('class="crl-toggle-col"', false)
            ->assertSee('data-ca-toggle="'.$sip->id.'"', false)
            ->assertSee('SIP_ATOME_01')
            ->assertSee('Atome')
            ->assertSee('24556 - 457864')
            ->assertSee('>Channel Number</th>', false)
            ->assertSee('24556')
            ->assertSee('457864')
            ->assertDontSee('class="action-btn edit"', false)
            ->assertSee('class="action-btn delete"', false)
            ->assertSee('id="crl_sip_channel_id"', false)
            ->assertSee('id="crl_sip_name"', false)
            ->assertSee('readonly', false)
            ->assertSee('disabled', false)
            ->assertSee('id="crl_from"', false)
            ->assertSee('id="crl_to"', false)
            ->assertSee('data-sip-name="SIP_ATOME_01"', false)
            ->assertSee('data-confirm-title="Delete Record"', false)
            ->assertSee('data-bulk-row="main"', false)
            ->assertSee('data-from="24556"', false)
            ->assertSee('data-to="457864"', false)
            ->assertSee('data-from="30001"', false)
            ->assertSee('data-to="30500"', false)
            ->assertSee('id="crl_from"', false)
            ->assertSee('id="crl_to"', false)
            ->assertDontSee('data-ca-toggle="'.$emptySip->id.'"', false)
            ->assertDontSee('data-bulk-row="nested"', false)
            ->assertDontSee('required-asterisk')
            ->assertDontSee('class="ca-menu-btn"', false);

        $this->assertStringContainsString('class="crl-toggle-col"', $html);
        $this->assertStringContainsString('campaigns-name', $html);
        $mainHead = Str::betweenFirst($html, 'class="ca-table crl-table"', '</thead>');
        $this->assertTrue(strpos($mainHead, 'class="crl-toggle-col"') < strpos($mainHead, '>Campaign</th>'));
        $this->assertTrue(strpos($mainHead, '>Campaign</th>') < strpos($mainHead, '>Channel Range</th>'));
        $this->assertTrue(strpos($mainHead, '>Channel Range</th>') < strpos($mainHead, '>SIP Name</th>'));
        $this->assertTrue(strpos($mainHead, '>SIP Name</th>') < strpos($mainHead, '>Actions</th>'));
        $this->assertStringContainsString('crl-campaign-col', $mainHead);
        $this->assertStringContainsString('crl-channel-col', $mainHead);
        $this->assertStringContainsString('crl-sip-col', $mainHead);
        $this->assertStringContainsString('crl-actions-col', $mainHead);
        $this->assertTrue(strpos($mainHead, 'crl-campaign-col') < strpos($mainHead, 'crl-channel-col'));
        $this->assertTrue(strpos($mainHead, 'crl-channel-col') < strpos($mainHead, 'crl-sip-col'));
        $this->assertTrue(strpos($mainHead, 'crl-sip-col') < strpos($mainHead, 'crl-actions-col'));
        $this->assertStringNotContainsString('Channel Number', $mainHead);
        $this->assertStringContainsString('id="sipChannelsGroup"', $html);
        $this->assertStringContainsString('id="sipChannelsCaret"', $html);
        $this->assertStringContainsString('>Channel Range</span></a>', $html);
        $this->assertStringContainsString('href="'.url('/sip-channels').'"', $html);
        $this->assertDoesNotMatchRegularExpression('/id="sipChannelsSub"[^>]*>[\s\S]*?<span>SIP Channels<\/span>/', $html);
        $this->assertStringContainsString('id="crl_sip_name" readonly disabled', $html);

        $campaignRow = Str::betweenFirst($html, 'class="ca-campaign-row"', 'class="ca-nested-row"');
        $this->assertStringContainsString('24556 - 457864', $campaignRow);
        $this->assertStringContainsString('class="action-btn delete"', $campaignRow);
        $this->assertStringNotContainsString('class="action-btn edit"', $campaignRow);
        $this->assertSame(1, substr_count($html, 'class="action-btn delete"'));
        $this->assertStringNotContainsString('data-ca-toggle="'.$emptySip->id.'"', $campaignRow);
        $this->assertStringNotContainsString('PNB Collection/ Telesales', $campaignRow);
        $this->assertStringContainsString("fromInput.value = option?.getAttribute('data-from') || ''", $html);
        $this->assertStringContainsString("toInput.value = option?.getAttribute('data-to') || ''", $html);
        $this->assertStringContainsString('id="crl_from"', $html);
        $this->assertStringContainsString('id="crl_to"', $html);
        $this->assertMatchesRegularExpression('/id="crl_from"[^>]*readonly/', $html);
        $this->assertMatchesRegularExpression('/id="crl_to"[^>]*readonly/', $html);
        $this->assertStringContainsString('channel-range-list\'?null:nestedScope()', file_get_contents(resource_path('js/app.js')));

        $nested = Str::betweenFirst($html, 'class="crl-nested"', '</table>');
        $this->assertStringContainsString('Channel Number', $nested);
        $this->assertStringNotContainsString('>Actions</th>', $nested);
        $this->assertStringContainsString('crl-channel-col', $nested);
        $this->assertStringNotContainsString('class="action-btn', $nested);
        $this->assertStringNotContainsString('SIP Name', $nested);
        $this->assertStringNotContainsString('SIP_ATOME_01', $nested);
        $this->assertStringNotContainsString('ca-menu-btn', $nested);
        $this->assertStringNotContainsString('>ID</th>', $nested);
        $this->assertStringNotContainsString('>Status</th>', $nested);
    }

    public function test_channel_range_table_uses_compact_spacing_mixed_alignment_and_no_grid(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertMatchesRegularExpression(
            '/body\[data-page="channel-range-list"\] \.crl-table > thead > tr > th\.crl-campaign-col,\s*body\[data-page="channel-range-list"\] \.crl-table \.ca-campaign-row > td\.crl-campaign-col \{\s*text-align: left;/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/body\[data-page="channel-range-list"\] \.crl-table \.ca-campaign-link,\s*body\[data-page="channel-range-list"\] \.crl-table \.ca-campaign-link\.campaigns-name \{\s*color: #0066FF;\s*font-weight: 700;/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/body\[data-page="channel-range-list"\] \.crl-table > thead > tr > th\.crl-channel-col,\s*body\[data-page="channel-range-list"\] \.crl-table \.ca-campaign-row > td\.crl-channel-col \{\s*text-align: center;/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/body\[data-page="channel-range-list"\] \.crl-table > thead > tr > th\.crl-sip-col,\s*body\[data-page="channel-range-list"\] \.crl-table \.ca-campaign-row > td\.crl-sip-col \{\s*text-align: center;/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/body\[data-page="channel-range-list"\] \.crl-table > thead > tr > th\.crl-actions-col,\s*body\[data-page="channel-range-list"\] \.crl-table \.ca-campaign-row > td\.crl-actions-col \{\s*text-align: center;/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/body\[data-page="channel-range-list"\] \.crl-table \.ca-nested \.crl-nested thead th\.crl-channel-col,\s*body\[data-page="channel-range-list"\] \.crl-table \.ca-nested \.crl-nested tbody td\.crl-channel-col \{\s*width: 24%;\s*text-align: center;/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/body\[data-page="channel-range-list"\] \.crl-table \.ca-nested \.crl-nested \.actions-column \{\s*width: 24%;\s*text-align: center;/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/body\[data-page="channel-range-list"\] \.crl-table > thead > tr > th,\s*body\[data-page="channel-range-list"\] \.crl-table > tbody > tr > td,\s*body\[data-page="channel-range-list"\] \.crl-nested > thead > tr > th,\s*body\[data-page="channel-range-list"\] \.crl-nested > tbody > tr > td \{\s*border: 0;\s*border-bottom: 0;/',
            $css
        );
        $this->assertDoesNotMatchRegularExpression(
            '/body\[data-page="channel-range-list"\] \.crl-table > thead > tr > th\.crl-campaign-col[\s\S]{0,180}padding-left: 16px;/',
            $css
        );
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
        $this->assertDatabaseHas('sip_channels', ['id' => $sip->id]);
    }

    public function test_delete_works_for_channel_range_with_zero_channel_numbers(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create(['name' => 'Empty Range']);
        $keep = SipChannel::create([
            'campaign_id' => $campaign->id,
            'etpi_sip_name' => 'SIP_KEEP',
            'channel_range' => '100 - 110',
        ]);
        $drop = SipChannel::create([
            'campaign_id' => $campaign->id,
            'etpi_sip_name' => 'SIP_EMPTY',
            'channel_range' => '200 - 210',
        ]);
        SipChannelNumber::create([
            'sip_channel_id' => $keep->id,
            'channel_number' => '100',
        ]);

        $this->from('/channel-range-list')->delete('/channel-range-list/bulk', [
            'sip_channel_id' => $drop->id,
        ])->assertRedirect('/channel-range-list');

        $this->assertDatabaseHas('sip_channels', ['id' => $drop->id]);
        $this->assertDatabaseHas('sip_channels', ['id' => $keep->id]);
        $this->assertDatabaseHas('sip_channel_numbers', ['sip_channel_id' => $keep->id, 'channel_number' => '100']);

        $list = $this->get('/channel-range-list')->assertOk()->getContent();
        $this->assertStringContainsString('data-ca-toggle="'.$keep->id.'"', $list);
        $this->assertStringNotContainsString('data-ca-toggle="'.$drop->id.'"', $list);
    }

    public function test_delete_removes_channel_range_row_but_keeps_the_sip_channel(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create(['name' => 'Atome']);
        $sip = SipChannel::create([
            'campaign_id' => $campaign->id,
            'etpi_sip_name' => 'SIP_ATOME_01',
            'channel_range' => '100 - 102',
        ]);
        foreach (['100', '101', '102'] as $number) {
            SipChannelNumber::create([
                'sip_channel_id' => $sip->id,
                'channel_number' => $number,
            ]);
        }

        $this->from('/channel-range-list')->delete('/channel-range-list/bulk', [
            'sip_channel_id' => $sip->id,
            'ids' => SipChannelNumber::query()->where('sip_channel_id', $sip->id)->pluck('id')->all(),
        ])->assertRedirect('/channel-range-list');

        $this->assertDatabaseHas('sip_channels', ['id' => $sip->id, 'etpi_sip_name' => 'SIP_ATOME_01']);
        $this->assertSame(0, SipChannelNumber::where('sip_channel_id', $sip->id)->count());

        $html = $this->get('/channel-range-list')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-ca-toggle="'.$sip->id.'"', $html);
        $this->assertStringContainsString('data-sip-name="SIP_ATOME_01"', $html);
    }

    public function test_sip_channel_edits_reflect_on_channel_range_list(): void
    {
        $this->actingAs($this->admin);
        $atome = ChannelAllocationCampaign::create(['name' => 'Atome']);
        $bpi = ChannelAllocationCampaign::create(['name' => 'BPI']);
        $sip = SipChannel::create([
            'campaign_id' => $atome->id,
            'etpi_sip_name' => 'SIP_ATOME_01',
            'channel_range' => '100 - 102',
        ]);
        foreach (['100', '101', '102'] as $number) {
            SipChannelNumber::create([
                'sip_channel_id' => $sip->id,
                'channel_number' => $number,
            ]);
        }

        $this->from('/sip-channels')->put('/sip-channels/'.$sip->id, [
            'campaign_id' => $bpi->id,
            'etpi_sip_name' => 'SIP_BPI_01',
            'channel_range' => '200 - 202',
        ])->assertRedirect('/sip-channels')->assertSessionMissing('sip_edit');

        $this->assertDatabaseHas('sip_channels', [
            'id' => $sip->id,
            'campaign_id' => $bpi->id,
            'etpi_sip_name' => 'SIP_BPI_01',
            'channel_range' => '200 - 202',
        ]);
        $this->assertSame(['200', '201', '202'], SipChannelNumber::query()
            ->where('sip_channel_id', $sip->id)
            ->orderBy('channel_number')
            ->pluck('channel_number')
            ->all());

        $html = $this->get('/channel-range-list')->assertOk()->getContent();
        $this->assertStringContainsString('BPI', $html);
        $this->assertStringContainsString('SIP_BPI_01', $html);
        $this->assertStringContainsString('200 - 202', $html);
        $this->assertStringContainsString('>200</span>', $html);
        $this->assertStringContainsString('>202</span>', $html);
        $this->assertStringNotContainsString('SIP_ATOME_01', $html);
        $this->assertStringNotContainsString('100 - 102', $html);
    }

    public function test_saving_a_sip_channel_does_not_create_a_channel_range_row(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create(['name' => 'Atome']);
        $this->post('/sip-channels', [
            'campaign_id' => $campaign->id,
            'etpi_sip_name' => 'SIP_ATOME_01',
            'channel_range' => '30001 - 30500',
        ])->assertRedirect();

        $sip = SipChannel::where('etpi_sip_name', 'SIP_ATOME_01')->firstOrFail();
        $this->assertSame(0, SipChannelNumber::where('sip_channel_id', $sip->id)->count());

        $html = $this->get('/channel-range-list')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-ca-toggle="'.$sip->id.'"', $html);
        $this->assertStringContainsString('data-sip-name="SIP_ATOME_01"', $html);
        $this->assertStringContainsString('data-from="30001"', $html);
        $this->assertStringContainsString('data-to="30500"', $html);
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
