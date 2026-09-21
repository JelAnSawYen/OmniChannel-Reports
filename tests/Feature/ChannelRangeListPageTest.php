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

    public function test_page_uses_sip_name_with_pdc_style_expander(): void
    {
        $this->actingAs($this->admin);
        $atome = ChannelAllocationCampaign::create(['name' => 'Atome']);
        $sip = SipChannel::create([
            'campaign_id' => $atome->id,
            'etpi_sip_name' => 'SIP_ATOME_01',
        ]);
        $firstNumber = SipChannelNumber::create([
            'sip_channel_id' => $sip->id,
            'channel_number' => '24556',
        ]);
        $secondNumber = SipChannelNumber::create([
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
            ->assertSee('ca-campaign-identity">SIP Name</span>', false)
            ->assertSee('>Channel Range</th>', false)
            ->assertSee('>Actions</th>', false)
            ->assertSee('class="ca-campaign-cell"', false)
            ->assertSee('data-ca-toggle="'.$sip->id.'"', false)
            ->assertSee('SIP_ATOME_01')
            ->assertSee('24556 - 457864')
            ->assertSee('>Channel Number</th>', false)
            ->assertSee('24556')
            ->assertSee('457864')
            ->assertDontSee('class="action-btn edit"', false)
            ->assertSee('class="action-btn delete"', false)
            ->assertSee('data-confirm-title="Delete Record"', false)
            ->assertSee('data-bulk-row="main"', false)
            ->assertDontSee('id="crlAddButton"', false)
            ->assertDontSee('id="crlAddModal"', false)
            ->assertDontSee('id="crl_sip_channel_id"', false)
            ->assertDontSee('id="crl_from"', false)
            ->assertDontSee('id="crl_to"', false)
            ->assertDontSee('data-ca-toggle="'.$emptySip->id.'"', false)
            ->assertSee('data-bulk-row="nested"', false)
            ->assertSee('data-bulk-id="'.$firstNumber->id.'"', false)
            ->assertSee('data-bulk-id="'.$secondNumber->id.'"', false)
            ->assertSee('data-bulk-url="'.url('/channel-range-list/bulk').'"', false)
            ->assertDontSee('required-asterisk')
            ->assertDontSee('class="ca-menu-btn"', false);

        $this->assertStringContainsString('class="ca-campaign-cell"', $html);
        $this->assertStringContainsString('crl-sip-name', $html);
        $this->assertStringNotContainsString('ca-campaign-identity">Campaign</span>', $html);
        $mainHead = Str::betweenFirst($html, 'class="ca-table crl-table"', '</thead>');
        $this->assertStringContainsString('class="ca-campaign-cell"', $mainHead);
        $this->assertStringContainsString('class="ca-toggle" aria-hidden="true"', $mainHead);
        $this->assertStringContainsString('ca-campaign-identity">SIP Name</span>', $mainHead);
        $this->assertStringNotContainsString('class="crl-toggle-col"', $mainHead);
        $this->assertStringNotContainsString('Campaign', $mainHead);
        $this->assertTrue(strpos($mainHead, 'ca-campaign-identity">SIP Name</span>') < strpos($mainHead, '>Channel Range</th>'));
        $this->assertTrue(strpos($mainHead, '>Channel Range</th>') < strpos($mainHead, '>Actions</th>'));
        $this->assertStringContainsString('crl-sip-col', $mainHead);
        $this->assertStringContainsString('crl-channel-col', $mainHead);
        $this->assertStringContainsString('crl-actions-col', $mainHead);
        $this->assertStringNotContainsString('crl-campaign-col', $mainHead);
        $this->assertTrue(strpos($mainHead, 'crl-sip-col') < strpos($mainHead, 'crl-channel-col'));
        $this->assertTrue(strpos($mainHead, 'crl-channel-col') < strpos($mainHead, 'crl-actions-col'));
        $this->assertStringNotContainsString('Channel Number', $mainHead);
        $this->assertStringContainsString('id="sipChannelsGroup"', $html);
        $this->assertStringContainsString('id="sipChannelsCaret"', $html);
        $this->assertStringContainsString('>Channel Range</span></a>', $html);
        $this->assertStringContainsString('href="'.url('/sip-channels').'"', $html);
        $this->assertDoesNotMatchRegularExpression('/id="sipChannelsSub"[^>]*>[\s\S]*?<span>SIP Channels<\/span>/', $html);
        $this->assertStringNotContainsString('id="crlAddButton"', $html);
        $this->assertStringNotContainsString('id="crl_from"', $html);

        $campaignRow = Str::betweenFirst($html, 'class="ca-campaign-row"', 'class="ca-nested-row"');
        $this->assertStringContainsString('class="ca-campaign-cell"', $campaignRow);
        $this->assertTrue(strpos($campaignRow, 'class="ca-toggle"') < strpos($campaignRow, 'crl-sip-name'));
        $this->assertTrue(strpos($campaignRow, 'crl-sip-name') < strpos($campaignRow, 'crl-channel-range'));
        $this->assertStringContainsString('SIP_ATOME_01', $campaignRow);
        $this->assertStringNotContainsString('Atome', $campaignRow);
        $this->assertStringContainsString('24556 - 457864', $campaignRow);
        $this->assertStringContainsString('class="action-btn delete"', $campaignRow);
        $this->assertStringNotContainsString('class="action-btn edit"', $campaignRow);
        $this->assertSame(1, substr_count($html, 'class="action-btn delete"'));
        $this->assertStringNotContainsString('data-ca-toggle="'.$emptySip->id.'"', $campaignRow);
        $this->assertStringNotContainsString('PNB Collection/ Telesales', $campaignRow);
        $this->assertStringNotContainsString('id="crlAddButton"', $html);
        $this->assertStringNotContainsString('id="crlAddModal"', $html);
        $js = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString('const nested=nestedScope();', $js);
        $this->assertStringNotContainsString("channel-range-list'?null:nestedScope()", $js);

        $nested = Str::betweenFirst($html, 'class="crl-nested"', '</table>');
        $this->assertStringContainsString('Channel Number', $nested);
        $this->assertStringNotContainsString('>Actions</th>', $nested);
        $this->assertStringContainsString('crl-channel-col', $nested);
        $this->assertStringContainsString('data-bulk-row="nested" data-bulk-id="'.$firstNumber->id.'" data-bulk-url="'.url('/channel-range-list/bulk').'"', $nested);
        $this->assertStringContainsString('data-bulk-row="nested" data-bulk-id="'.$secondNumber->id.'" data-bulk-url="'.url('/channel-range-list/bulk').'"', $nested);
        $this->assertStringNotContainsString('class="action-btn', $nested);
        $this->assertStringNotContainsString('SIP Name', $nested);
        $this->assertStringNotContainsString('class="crl-sip-name"', $nested);
        $this->assertStringNotContainsString('>SIP_ATOME_01<', $nested);
        $this->assertStringNotContainsString('ca-menu-btn', $nested);
        $this->assertStringNotContainsString('>ID</th>', $nested);
        $this->assertStringNotContainsString('>Status</th>', $nested);

        $filtered = $this->get('/channel-range-list?search=SIP_ATOME_01')->assertOk()->getContent();
        $this->assertStringContainsString('SIP_ATOME_01', $filtered);
        $this->assertStringContainsString('data-ca-toggle="'.$sip->id.'"', $filtered);
    }

    public function test_channel_range_table_uses_compact_spacing_mixed_alignment_and_no_grid(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertMatchesRegularExpression(
            '/body\[data-page="channel-range-list"\] \.crl-table > thead > tr > th\.crl-sip-col,\s*body\[data-page="channel-range-list"\] \.crl-table \.ca-campaign-row > td\.crl-sip-col \{\s*text-align: left;/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/body\[data-page="channel-range-list"\] \.crl-table \.ca-campaign-link,\s*body\[data-page="channel-range-list"\] \.crl-table \.ca-campaign-link\.crl-sip-name,\s*body\[data-page="channel-range-list"\] \.crl-table \.crl-sip-name \{\s*color: #0066FF;\s*font-weight: 700;/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/body\[data-page="channel-range-list"\] \.crl-table > thead > tr > th\.crl-sip-col \.ca-campaign-identity \{\s*color: #0f172a;\s*font-weight: 700;/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/body\[data-page="channel-range-list"\] \.crl-table > thead > tr > th\.crl-channel-col,\s*body\[data-page="channel-range-list"\] \.crl-table \.ca-campaign-row > td\.crl-channel-col \{\s*text-align: center;/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/body\[data-page="channel-range-list"\] \.crl-table > thead > tr > th\.crl-actions-col,\s*body\[data-page="channel-range-list"\] \.crl-table \.ca-campaign-row > td\.crl-actions-col \{\s*text-align: center;/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/body\[data-page="channel-range-list"\] \.crl-table > colgroup > \.crl-col-sip,[\s\S]*?\.crl-actions-col \{\s*width: 33\.333%;/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/body\[data-page="channel-range-list"\] \.crl-table \.ca-nested \.crl-nested thead th\.crl-channel-col,\s*body\[data-page="channel-range-list"\] \.crl-table \.ca-nested \.crl-nested tbody td\.crl-channel-col \{\s*width: 33\.333%;\s*text-align: center;/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/body\[data-page="channel-range-list"\] \.crl-table \.ca-nested \.crl-nested \.actions-column \{\s*width: 33\.333%;\s*text-align: center;/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/body\[data-page="channel-range-list"\] \.crl-table > thead > tr > th,\s*body\[data-page="channel-range-list"\] \.crl-table > tbody > tr > td \{\s*border: 0;\s*border-bottom: 0;/',
            $css
        );
        $this->assertDoesNotMatchRegularExpression(
            '/body\[data-page="channel-range-list"\] \.crl-table \.ca-nested \{\s*border: 0;/',
            $css
        );
        $this->assertStringContainsString('.ca-nested { border: 1.25px solid var(--border); border-radius: 10px;', $css);
        $this->assertMatchesRegularExpression(
            '/body\[data-page="channel-range-list"\] \.crl-table > thead > tr > th\.crl-sip-col[\s\S]{0,180}padding-left: 16px;/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/body\[data-page="channel-range-list"\] \.crl-table > thead > tr > th\.crl-actions-col[\s\S]{0,180}padding-right: 16px;/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/body\[data-page="channel-range-list"\] \.crl-table > thead > tr > th:first-child \.ca-toggle \{\s*visibility: hidden;[\s\S]*?width: 26\.25px;/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/body\[data-page="channel-range-list"\] \.crl-table \.ca-campaign-cell \{\s*gap: 4px;/',
            $css
        );
        $this->assertStringNotContainsString('crl-toggle-col', $css);
        $this->assertStringNotContainsString('crl-campaign-col', $css);
    }

    public function test_from_to_creates_actual_channel_records_and_blocks_duplicates(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create(['name' => 'Atome']);

        $this->post('/sip-channels', [
            'campaign_id' => $campaign->id,
            'etpi_sip_name' => 'SIP_ATOME_01',
            'from' => '253235320',
            'to' => '253235333',
        ])->assertRedirect();

        $sip = SipChannel::where('etpi_sip_name', 'SIP_ATOME_01')->firstOrFail();
        $this->assertSame(14, SipChannelNumber::count());
        $this->assertSame('253235320', SipChannelNumber::orderBy('channel_number')->value('channel_number'));
        $this->assertTrue(SipChannelNumber::where('channel_number', '253235333')->where('sip_channel_id', $sip->id)->exists());
        $this->assertSame(14, SipChannelNumber::where('sip_channel_id', $sip->id)->count());
        $this->assertSame('253235320 - 253235333', $sip->channel_range);

        $this->from('/sip-channels')->post('/sip-channels', [
            'campaign_id' => $campaign->id,
            'etpi_sip_name' => 'SIP_ATOME_02',
            'from' => '253235333',
            'to' => '253235334',
        ])->assertRedirect('/sip-channels')->assertSessionHasErrors('from');

        $this->assertSame(14, SipChannelNumber::count());
        $this->assertDatabaseMissing('sip_channels', ['etpi_sip_name' => 'SIP_ATOME_02']);
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
        $this->assertStringNotContainsString('id="crlAddModal"', $html);
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
            'from' => '200',
            'to' => '202',
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
        $this->assertStringContainsString('SIP_BPI_01', $html);
        $this->assertStringNotContainsString('ca-campaign-identity">Campaign</span>', $html);
        $this->assertStringContainsString('200 - 202', $html);
        $this->assertStringContainsString('>200</span>', $html);
        $this->assertStringContainsString('>202</span>', $html);
        $this->assertStringNotContainsString('SIP_ATOME_01', $html);
        $this->assertStringNotContainsString('100 - 102', $html);
    }

    public function test_saving_a_sip_channel_creates_its_channel_range_row(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create(['name' => 'Atome']);
        $this->post('/sip-channels', [
            'campaign_id' => $campaign->id,
            'etpi_sip_name' => 'SIP_ATOME_01',
            'from' => '30001',
            'to' => '30005',
        ])->assertRedirect();

        $sip = SipChannel::where('etpi_sip_name', 'SIP_ATOME_01')->firstOrFail();
        $this->assertSame(5, SipChannelNumber::where('sip_channel_id', $sip->id)->count());
        $this->assertSame('30001 - 30005', $sip->channel_range);

        $html = $this->get('/channel-range-list')->assertOk()->getContent();
        $this->assertStringContainsString('data-ca-toggle="'.$sip->id.'"', $html);
        $this->assertStringContainsString('30001 - 30005', $html);
        $this->assertStringNotContainsString('id="crlAddButton"', $html);
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
        $this->assertStringContainsString('["sip_name","channel_number"]', $html);

        $template = $this->get('/channel-range-list/import/template')->assertOk()->assertDownload('channel-range-list-template.xlsx');
        [$headers, $rows] = app(XlsxService::class)->read($template->getFile()->getPathname());
        $this->assertSame(['SIP Name', 'Channel Number'], $headers);
        $this->assertNotContains('Campaign', $headers);
        $this->assertNotContains('Id', $headers);
        $this->assertNotContains('From', $headers);
        $this->assertNotContains('To', $headers);
        foreach ($rows as $row) {
            $this->assertSame(['', ''], array_pad($row, 2, ''));
        }

        $preview = $this->postJson('/channel-range-list/import/preview', [
            'file' => $this->upload($this->spreadsheet([
                ['SIP Name', 'Channel Number'],
                ['SIP_ATOME_01', '253235320'],
                ['Unknown', '253235321'],
            ])),
        ])->assertOk()->json();
        $this->assertFalse($preview['valid']);
        $this->assertTrue($preview['rows'][0]['valid']);
        $this->assertFalse($preview['rows'][1]['valid']);
        $this->assertStringContainsString('SIP Channel', $preview['rows'][1]['error']);

        $ok = $this->postJson('/channel-range-list/import/preview', [
            'file' => $this->upload($this->spreadsheet([
                ['SIP Name', 'Channel Number'],
                ['SIP_ATOME_01', '253235320'],
                ['', '253235321'],
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
                ['SIP Name', 'Channel Number'],
                ['SIP_ATOME_01', '253235320'],
            ])),
        ])->assertOk()->json();
        $this->assertFalse($dup['valid']);
        $this->assertStringContainsString('already exists', $dup['rows'][0]['error']);

        $export = $this->get('/channel-range-list/export')->assertOk()->assertDownload('channel-range-list.xlsx');
        [$exportHeaders, $exportRows] = app(XlsxService::class)->read($export->getFile()->getPathname());
        $this->assertSame(['SIP Name', 'Channel Range', 'Channel Numbers/Range Entries'], $exportHeaders);
        $this->assertNotContains('Campaign', $exportHeaders);
        $this->assertContains(['SIP_ATOME_01', '253235320 - 253235321', '253235320'], $exportRows);
        $this->assertContains(['', '', '253235321'], $exportRows);
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
