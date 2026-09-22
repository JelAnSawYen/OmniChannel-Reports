<?php

namespace Tests\Feature;

use App\Models\ChannelAllocation;
use App\Models\ChannelAllocationCampaign;
use App\Models\MediaGateway;
use App\Models\SipChannel;
use App\Models\User;
use App\Models\UserType;
use App\Services\XlsxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ChannelAllocationImportTest extends TestCase
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

    public function test_template_download_has_headers_without_sample_records(): void
    {
        $this->actingAs($this->admin);
        $response = $this->get('/channel-allocation/import/template')->assertOk()->assertDownload('channel-allocation-template.xlsx');
        $path = $response->getFile()->getPathname();
        [$headers, $rows] = app(XlsxService::class)->read($path);

        $this->assertSame([
            'Campaign',
            'Channel',
            'Network',
            'Line Priority',
            'Channel Count',
            'FTE',
            'Caller ID',
            'Prefix',
            'Remarks',
        ], $headers);
        $this->assertSame(count($headers), count(array_unique($headers)));
        $this->assertNotContains('Channel Type', $headers);
        $this->assertNotContains('SIP Channel', $headers);
        $this->assertNotContains('GSM Gateway', $headers);
        $this->assertNotContains('Channel Allocation', $headers);
        $this->assertNotContains('Media Gateway', $headers);
        $this->assertNotContains('Total Channel Allocated', $headers);
        $this->assertNotContains('Total Channels Allocated', $headers);
        $this->assertSame([], $rows);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        $this->assertStringNotContainsString('Sample Campaign', $sheet);
        $this->assertStringNotContainsString('CH-SAMPLE-1', $sheet);
        $this->assertSame(11, preg_match_all('/<row r="/', $sheet));
    }

    public function test_preview_shows_row_errors_and_does_not_save(): void
    {
        $this->actingAs($this->admin);
        $this->sip('CH-3', 'Globe SIM', 8);
        ChannelAllocationCampaign::create(['name' => 'Broken']);
        $path = $this->makeSpreadsheet([
            ['Broken', '', '123', '100', '', '', '1'],
            ['Broken', '', '123', '100', '', 'UNKNOWN-CH', '1'],
            ['Broken', '2', '123', '100', '', 'CH-3', '1'],
        ]);

        $preview = $this->postJson('/channel-allocation/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();

        $this->assertFalse($preview['valid']);
        $this->assertSame(2, $preview['summary']['errors']);
        $this->assertStringContainsString('Channel is required', $preview['rows'][0]['error']);
        $this->assertStringContainsString('Channel must match an existing SIP Name or GSM Hostname', $preview['rows'][1]['error']);
        $this->assertTrue($preview['rows'][2]['valid']);
        $this->assertSame('Globe SIM', $preview['rows'][2]['network']);
        $this->assertSame('8', $preview['rows'][2]['total_channel_allocated']);
        $this->assertSame(1, ChannelAllocationCampaign::count());
        $this->assertSame(0, ChannelAllocation::count());

        $this->postJson('/channel-allocation/import/confirm', ['token' => $preview['token']])
            ->assertStatus(422);
        $this->assertSame(0, ChannelAllocation::count());
    }

    public function test_valid_import_groups_campaigns_calculates_totals_and_keeps_export(): void
    {
        $this->actingAs($this->admin);
        $this->sip('CH-A1', 'Globe SIM', 20);
        $this->sip('CH-A2', 'Smart SIM', 12);
        $this->sip('CH-B1', 'Eastern SIP', 8);
        ChannelAllocationCampaign::create(['name' => 'Alpha Import']);
        ChannelAllocationCampaign::create(['name' => 'Beta Import']);
        $path = $this->makeSpreadsheet([
            ['Alpha Import', '4', '555', '200', 'note', 'CH-A1', '1'],
            ['Alpha Import', '4', '555', '200', '', 'CH-A2', '2'],
            ['Beta Import', '3', 'N/A', '201', '', 'CH-B1', '1'],
        ]);

        $preview = $this->postJson('/channel-allocation/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();

        $this->assertTrue($preview['valid']);
        $this->assertSame(0, $preview['summary']['errors']);
        $this->assertSame(2, $preview['summary']['campaigns']);
        $this->assertSame(3, $preview['summary']['allocations']);
        $this->assertSame(2, ChannelAllocationCampaign::count());
        $this->assertSame(0, ChannelAllocation::count());

        $this->postJson('/channel-allocation/import/confirm', ['token' => $preview['token']])
            ->assertOk()
            ->assertJson(['ok' => true, 'allocations' => 3]);

        $alpha = ChannelAllocationCampaign::where('name', 'Alpha Import')->firstOrFail();
        $this->assertSame(1, ChannelAllocationCampaign::where('name', 'Alpha Import')->count());
        $this->assertSame(2, $alpha->allocations()->count());
        $this->assertSame(32, $alpha->fresh()->total_channels_allocated);
        $this->assertNull($alpha->fte);
        $this->assertTrue($alpha->allocations()->where('channel_allocation', 'CH-A1')->exists());
        $this->assertTrue($alpha->allocations()->where('channel_allocation', 'CH-A2')->exists());

        $beta = ChannelAllocationCampaign::where('name', 'Beta Import')->firstOrFail();
        $this->assertSame(8, $beta->fresh()->total_channels_allocated);

        $this->get('/channel-allocation')
            ->assertOk()
            ->assertSee('Alpha Import')
            ->assertSee('class="ca-count">2 allocations', false)
            ->assertDontSee('data-label="Allocations"', false)
            ->assertSee('Beta Import');

        $export = $this->get('/channel-allocation/export')->assertOk()->assertDownload('channel-allocation.xlsx');
        [$exportHeaders] = app(XlsxService::class)->read($export->getFile()->getPathname());
        $this->assertSame([
            'Campaign',
            'Channel',
            'Network',
            'Line Priority',
            'Channel Count',
            'Total Channels',
            'FTE',
            'Caller ID',
            'Prefix',
            'Remarks',
        ], $exportHeaders);
        $this->assertNotContains('SIP Channel', $exportHeaders);
        $this->assertNotContains('GSM Gateway', $exportHeaders);
        $this->assertNotContains('Total Channel Allocated', $exportHeaders);
    }

    public function test_preview_reports_every_field_error_on_every_invalid_row(): void
    {
        $this->actingAs($this->admin);
        $this->sip('CH-OK', 'Globe SIM', 8);
        $this->sip('CH-DUP', 'Smart SIM', 4);
        ChannelAllocationCampaign::create(['name' => 'Mixed Campaign']);
        $path = $this->makeSpreadsheet([
            ['', '', str_repeat('c', 256), str_repeat('p', 101), '', '', 'ABC'],
            ['Mixed Campaign', '2', '123', '100', '', 'CH-OK', '1'],
            ['Mixed Campaign', '2', '123', '100', '', 'UNKNOWN-CH', '-2'],
            ['Mixed Campaign', '2', '123', '100', '', 'CH-DUP', '3'],
            ['Mixed Campaign', '2', '123', '100', '', 'CH-DUP', '4'],
        ]);

        $preview = $this->postJson('/channel-allocation/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();

        $this->assertFalse($preview['valid']);
        $this->assertSame(5, $preview['summary']['total']);
        $this->assertSame(3, $preview['summary']['errors']);

        $first = $preview['rows'][0]['error'];
        $this->assertStringContainsString('Campaign is required', $first);
        $this->assertStringContainsString('Caller ID: ', $first);
        $this->assertStringContainsString('Prefix: ', $first);
        $this->assertStringContainsString('Line Priority: ', $first);
        $this->assertStringContainsString('Channel is required', $first);

        $this->assertTrue($preview['rows'][1]['valid']);
        $this->assertStringContainsString('Channel must match an existing SIP Name or GSM Hostname', $preview['rows'][2]['error']);
        $this->assertStringContainsString('Line Priority: ', $preview['rows'][2]['error']);
        $this->assertTrue($preview['rows'][3]['valid']);
        $this->assertStringContainsString('Channel: Duplicate Channel in file', $preview['rows'][4]['error']);

        $this->postJson('/channel-allocation/import/confirm', ['token' => $preview['token']])->assertStatus(422);
        $this->assertSame(0, ChannelAllocation::count());
    }

    public function test_line_priority_uses_the_same_message_as_the_add_form(): void
    {
        $this->actingAs($this->admin);
        $this->sip('CH-LP', 'Globe SIM', 8);
        $campaign = ChannelAllocationCampaign::create(['name' => 'Priority Campaign', 'listed_in_channel_allocation' => true]);

        $formMessage = $this->from('/channel-allocation')
            ->post('/channel-allocation/'.$campaign->id.'/allocations', [
                'channel' => 'CH-LP',
                'line_priority' => 'ABC',
            ])
            ->assertSessionHasErrors('line_priority')
            ->getSession()
            ->get('errors')
            ->first('line_priority');

        $preview = $this->postJson('/channel-allocation/import/preview', [
            'file' => $this->upload($this->makeSpreadsheet([
                ['Priority Campaign', '2', '', '', '', 'CH-LP', 'ABC'],
            ])),
        ])->assertOk()->json();

        $this->assertFalse($preview['valid']);
        $this->assertSame('Line Priority: '.$formMessage, $preview['rows'][0]['error']);
    }

    public function test_confirm_revalidates_and_returns_exact_row_errors_without_a_generic_message(): void
    {
        $this->actingAs($this->admin);
        $sip = $this->sip('CH-GONE', 'Globe SIM', 8);
        ChannelAllocationCampaign::create(['name' => 'Revalidated Campaign']);
        $preview = $this->postJson('/channel-allocation/import/preview', [
            'file' => $this->upload($this->makeSpreadsheet([
                ['Revalidated Campaign', '2', '123', '100', '', 'CH-GONE', '1'],
            ])),
        ])->assertOk()->json();
        $this->assertTrue($preview['valid']);

        $sip->delete();

        $confirm = $this->postJson('/channel-allocation/import/confirm', ['token' => $preview['token']])
            ->assertStatus(422)
            ->json();

        $this->assertFalse($confirm['ok']);
        $this->assertFalse($confirm['valid']);
        $this->assertStringNotContainsString('contact an administrator', $confirm['message']);
        $this->assertSame(1, $confirm['summary']['errors']);
        $this->assertSame(2, $confirm['rows'][0]['row']);
        $this->assertSame('Error', $confirm['rows'][0]['status']);
        $this->assertStringContainsString(
            'Channel must match an existing SIP Name or GSM Hostname',
            $confirm['rows'][0]['error']
        );
        $this->assertSame(0, ChannelAllocation::count());
        $this->assertSame(1, ChannelAllocationCampaign::where('name', 'Revalidated Campaign')->count());
        $this->assertFalse((bool) ChannelAllocationCampaign::where('name', 'Revalidated Campaign')->value('listed_in_channel_allocation'));
    }

    public function test_excel_shared_string_file_with_edited_values_is_parsed_and_previewed(): void
    {
        $this->actingAs($this->admin);
        $this->sip('CH-EDIT-1', 'Globe SIM', 15);
        $this->sip('CH-EDIT-2', 'Smart SIM', 9);
        ChannelAllocationCampaign::create(['name' => 'Edited Campaign']);
        $path = $this->makeExcelSharedStringSpreadsheet([
            ['Campaign', 'FTE', 'Caller ID', 'Prefix', 'Remarks', 'Channel', 'Line Priority'],
            ['Edited Campaign', '5', '888', '400', 'edited', 'CH-EDIT-1', '1'],
            ['Edited Campaign', '5', '888', '400', '', 'CH-EDIT-2', '2'],
        ]);

        [$headers, $rows] = app(XlsxService::class)->read($path);
        $this->assertSame('Campaign', $headers[0]);
        $this->assertSame('Channel', $headers[5]);
        $this->assertSame('Edited Campaign', trim((string) $rows[0][0]));
        $this->assertSame('CH-EDIT-2', trim((string) $rows[1][5]));

        $preview = $this->postJson('/channel-allocation/import/preview', [
            'file' => $this->upload($path, 'edited-template.xlsx'),
        ])->assertOk()->json();

        $this->assertTrue($preview['ok']);
        $this->assertTrue($preview['valid']);
        $this->assertSame(0, $preview['summary']['errors']);
        $this->assertSame(1, $preview['summary']['campaigns']);
        $this->assertSame(2, $preview['summary']['allocations']);
        $this->assertSame(1, ChannelAllocationCampaign::count());
        $this->assertSame(0, ChannelAllocation::count());
    }

    public function test_excel_shared_string_file_with_errors_returns_preview_and_blocks_confirm(): void
    {
        $this->actingAs($this->admin);
        $this->sip('CH-2', 'Globe SIM', 8);
        ChannelAllocationCampaign::create(['name' => 'Broken Edit']);
        $path = $this->makeExcelSharedStringSpreadsheet([
            ['Campaign', 'FTE', 'Caller ID', 'Prefix', 'Remarks', 'Channel', 'Line Priority'],
            ['Broken Edit', '2', '123', '100', '', '', '1'],
            ['Broken Edit', '', '123', '100', '', 'CH-2', '1'],
        ]);

        $preview = $this->postJson('/channel-allocation/import/preview', [
            'file' => $this->upload($path, 'edited-errors.xlsx'),
        ])->assertOk()->json();

        $this->assertFalse($preview['valid']);
        $this->assertGreaterThan(0, $preview['summary']['errors']);
        $this->assertStringContainsString('Channel is required', $preview['rows'][0]['error']);
        $this->assertTrue($preview['rows'][1]['valid']);
        $this->assertSame('Globe SIM', $preview['rows'][1]['network']);
        $this->postJson('/channel-allocation/import/confirm', ['token' => $preview['token']])->assertStatus(422);
        $this->assertSame(0, ChannelAllocation::count());
    }

    public function test_repeated_campaign_name_uses_existing_campaign(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create([
            'name' => 'Alpha Import',
            'media_gateway' => '10.1.1.1',
            'fte' => 4,
            'sort_order' => 1,
        ]);
        ChannelAllocation::create([
            'campaign_id' => $campaign->id,
            'channel_allocation' => 'CH-EXISTING',
            'total_channel_allocated' => 5,
            'sort_order' => 1,
        ]);
        $campaign->refreshTotalChannelsAllocated();

        $this->sip('CH-NEW', 'Globe SIM', 7);
        $path = $this->makeSpreadsheet([
            ['Alpha Import', '4', '555', '200', '', 'CH-NEW', '2'],
        ]);
        $preview = $this->postJson('/channel-allocation/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();
        $this->assertTrue($preview['valid']);
        $this->postJson('/channel-allocation/import/confirm', ['token' => $preview['token']])->assertOk();

        $this->assertSame(1, ChannelAllocationCampaign::where('name', 'Alpha Import')->count());
        $this->assertSame(2, $campaign->fresh()->allocations()->count());
        $this->assertSame(12, $campaign->fresh()->total_channels_allocated);
    }

    public function test_preview_accepts_network_from_sip_or_gsm_source_records(): void
    {
        $this->actingAs($this->admin);
        $this->sip('CH-N1', 'DITO SIM', 5);
        $this->sip('CH-N2', 'TNT', 6);
        $this->sip('CH-N3', 'Custom Carrier X', 7);
        $this->sip('CH-N4', 'Custom Carrier X', 8);
        ChannelAllocationCampaign::create(['name' => 'Net Import']);
        $path = $this->makeSpreadsheet([
            ['Net Import', '2', '111', '300', '', 'CH-N1', '1'],
            ['Net Import', '2', '111', '300', '', 'CH-N2', '2'],
            ['Net Import', '2', '111', '300', '', 'CH-N3', '3'],
            ['Net Import', '2', '111', '300', '', 'CH-N4', '4'],
        ]);

        $preview = $this->postJson('/channel-allocation/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();

        $this->assertTrue($preview['valid']);
        $this->assertSame(0, $preview['summary']['errors']);
        $this->assertSame('DITO SIM', $preview['rows'][0]['network']);
        $this->assertSame('TNT', $preview['rows'][1]['network']);
        $this->assertSame('Custom Carrier X', $preview['rows'][2]['network']);
        $this->assertSame('Custom Carrier X', $preview['rows'][3]['network']);
        $this->assertSame('5', $preview['rows'][0]['total_channel_allocated']);
        $this->assertSame('8', $preview['rows'][3]['total_channel_allocated']);

        $this->postJson('/channel-allocation/import/confirm', ['token' => $preview['token']])
            ->assertOk()
            ->assertJson(['ok' => true, 'allocations' => 4]);

        $campaign = ChannelAllocationCampaign::where('name', 'Net Import')->firstOrFail();
        $this->assertSame('DITO SIM', $campaign->allocations()->where('channel_allocation', 'CH-N1')->value('network'));
        $this->assertSame('TNT', $campaign->allocations()->where('channel_allocation', 'CH-N2')->value('network'));
        $this->assertSame('Custom Carrier X', $campaign->allocations()->where('channel_allocation', 'CH-N3')->value('network'));
        $this->assertSame('Custom Carrier X', $campaign->allocations()->where('channel_allocation', 'CH-N4')->value('network'));
    }

    public function test_channel_must_match_sip_name_or_gsm_hostname_and_rejects_clash(): void
    {
        $this->actingAs($this->admin);
        $this->sip('CH-G1', 'DITO SIM', 5);
        $this->sip('CH-G2', 'TNT', 6);
        $this->gsm('GSM-HOST-1', 'Globe SIM', 12);
        $this->sip('SHARED-NAME', 'ETPI', 14);
        $this->gsm('SHARED-NAME', 'Globe SIM', 9);
        ChannelAllocationCampaign::create(['name' => 'Gw Import']);

        $path = $this->makeSpreadsheet([
            ['Gw Import', '2', '111', '300', '', 'CH-G1', '1'],
            ['Gw Import', '2', '111', '300', '', 'GSM-HOST-1', '2'],
            ['Gw Import', '2', '111', '300', '', 'UNKNOWN', '3'],
            ['Gw Import', '2', '111', '300', '', 'SHARED-NAME', '4'],
        ]);

        $preview = $this->postJson('/channel-allocation/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();

        $this->assertFalse($preview['valid']);
        $this->assertTrue($preview['rows'][0]['valid']);
        $this->assertTrue($preview['rows'][1]['valid']);
        $this->assertSame('DITO SIM', $preview['rows'][0]['network']);
        $this->assertSame('Globe SIM', $preview['rows'][1]['network']);
        $this->assertSame('12', $preview['rows'][1]['total_channel_allocated']);
        $this->assertStringContainsString('Channel must match an existing SIP Name or GSM Hostname', $preview['rows'][2]['error']);
        $this->assertStringContainsString('Channel matches both a SIP Channel and a GSM Gateway', $preview['rows'][3]['error']);
    }

    public function test_import_uses_master_campaign_fte_and_ignores_file_fte(): void
    {
        $this->actingAs($this->admin);
        ChannelAllocationCampaign::create([
            'name' => 'Fte Import',
            'fte' => 9,
            'sort_order' => 1,
        ]);

        $this->sip('CH-F1', 'DITO SIM', 5);
        $this->sip('CH-F2', 'DITO SIM', 6);
        $this->sip('CH-F3', 'DITO SIM', 7);
        $this->sip('CH-F4', 'DITO SIM', 8);

        $validPath = $this->makeSpreadsheet([
            ['Fte Import', '4', '111', '300', '', 'CH-F1', '1'],
            ['Fte Import', '', '111', '300', '', 'CH-F2', '2'],
            ['Fte Import', '5', '111', '300', '', 'CH-F3', '3'],
            ['Fte Import', '4.5', '111', '300', '', 'CH-F4', '4'],
        ]);

        $preview = $this->postJson('/channel-allocation/import/preview', [
            'file' => $this->upload($validPath),
        ])->assertOk()->json();

        $this->assertTrue($preview['valid'], $preview['rows'][3]['error'] ?? '');
        $this->assertSame('9', $preview['rows'][0]['fte']);
        $this->assertSame('9', $preview['rows'][1]['fte']);
        $this->assertSame('9', $preview['rows'][2]['fte']);
        $this->assertSame('9', $preview['rows'][3]['fte']);

        $this->postJson('/channel-allocation/import/confirm', ['token' => $preview['token']])
            ->assertOk()
            ->assertJson(['ok' => true, 'allocations' => 4]);

        $this->assertSame(9, ChannelAllocationCampaign::where('name', 'Fte Import')->value('fte'));
    }

    public function test_prefix_inherits_blank_and_uses_new_value(): void
    {
        $this->actingAs($this->admin);
        $this->sip('CH-P1', 'DITO SIM', 5);
        $this->sip('CH-P2', 'DITO SIM', 6);
        $this->sip('CH-P3', 'DITO SIM', 7);
        $this->sip('CH-P4', 'DITO SIM', 8);
        ChannelAllocationCampaign::create(['name' => 'Prefix Import']);
        $path = $this->makeSpreadsheet([
            ['Prefix Import', '4', '111', '100', '', 'CH-P1', '1'],
            ['Prefix Import', '4', '111', '', '', 'CH-P2', '2'],
            ['Prefix Import', '4', '111', '200', '', 'CH-P3', '3'],
            ['Prefix Import', '4', '111', '', '', 'CH-P4', '4'],
        ]);

        $preview = $this->postJson('/channel-allocation/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();

        $this->assertTrue($preview['valid']);
        $this->assertSame('100', $preview['rows'][0]['prefix']);
        $this->assertSame('100', $preview['rows'][1]['prefix']);
        $this->assertSame('200', $preview['rows'][2]['prefix']);
        $this->assertSame('200', $preview['rows'][3]['prefix']);

        $this->postJson('/channel-allocation/import/confirm', ['token' => $preview['token']])
            ->assertOk()
            ->assertJson(['ok' => true, 'allocations' => 4]);

        $this->assertSame('200', ChannelAllocationCampaign::where('name', 'Prefix Import')->value('prefix'));
    }

    public function test_caller_id_allows_multiple_values_inherits_blank_and_uses_new_value(): void
    {
        $this->actingAs($this->admin);
        $this->sip('CH-C1', 'DITO SIM', 5);
        $this->sip('CH-C2', 'DITO SIM', 6);
        $this->sip('CH-C3', 'DITO SIM', 7);
        $this->sip('CH-C4', 'DITO SIM', 8);
        ChannelAllocationCampaign::create(['name' => 'Caller Import']);
        $path = $this->makeSpreadsheet([
            ['Caller Import', '4', '123456789, 987654321', '100', '', 'CH-C1', '1'],
            ['Caller Import', '4', '', '100', '', 'CH-C2', '2'],
            ['Caller Import', '4', '555111000', '100', '', 'CH-C3', '3'],
            ['Caller Import', '4', '', '100', '', 'CH-C4', '4'],
        ]);

        $preview = $this->postJson('/channel-allocation/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();

        $this->assertTrue($preview['valid']);
        $this->assertSame('123456789, 987654321', $preview['rows'][0]['caller_id']);
        $this->assertSame('123456789, 987654321', $preview['rows'][1]['caller_id']);
        $this->assertSame('555111000', $preview['rows'][2]['caller_id']);
        $this->assertSame('555111000', $preview['rows'][3]['caller_id']);

        $this->postJson('/channel-allocation/import/confirm', ['token' => $preview['token']])
            ->assertOk()
            ->assertJson(['ok' => true, 'allocations' => 4]);

        $this->assertSame('555111000', ChannelAllocationCampaign::where('name', 'Caller Import')->value('caller_id'));
    }

    public function test_dash_clears_carry_forward_fields_but_not_remarks_and_auto_network(): void
    {
        $this->actingAs($this->admin);
        $this->sip('CH-D1', 'DITO SIM', 5);
        $this->sip('CH-D2', 'Globe SIM', 6);
        $this->sip('CH-D3', 'Smart SIM', 7);
        $this->sip('CH-D4', 'TNT', 8);
        ChannelAllocationCampaign::create(['name' => 'Dash Import']);
        ChannelAllocationCampaign::create(['name' => 'Dash Bad CH']);
        $path = $this->makeSpreadsheet([
            ['Dash Import', '4', '111', '100', '-', 'CH-D1', '1'],
            ['Dash Import', '-', '-', '-', '', 'CH-D2', '2'],
            ['Dash Import', '', '', '', '', 'CH-D3', '3'],
            ['Dash Import', '5', '222', '200', 'note', 'CH-D4', '4'],
        ]);

        $preview = $this->postJson('/channel-allocation/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();

        $this->assertTrue($preview['valid'], $preview['rows'][1]['error'] ?? '');
        $this->assertSame('DITO SIM', $preview['rows'][0]['network']);
        $this->assertSame('5', $preview['rows'][0]['total_channel_allocated']);
        $this->assertSame('', $preview['rows'][1]['fte']);
        $this->assertSame('', $preview['rows'][1]['caller_id']);
        $this->assertSame('', $preview['rows'][1]['prefix']);
        $this->assertSame('Globe SIM', $preview['rows'][1]['network']);
        $this->assertSame('', $preview['rows'][2]['fte']);
        $this->assertSame('Smart SIM', $preview['rows'][2]['network']);
        $this->assertSame('', $preview['rows'][3]['fte']);
        $this->assertSame('TNT', $preview['rows'][3]['network']);
        $this->assertSame('8', $preview['rows'][3]['total_channel_allocated']);

        $this->postJson('/channel-allocation/import/confirm', ['token' => $preview['token']])
            ->assertOk()
            ->assertJson(['ok' => true, 'allocations' => 4]);

        $campaign = ChannelAllocationCampaign::where('name', 'Dash Import')->firstOrFail();
        $this->assertNull($campaign->fte);
        $this->assertSame('222', $campaign->caller_id);
        $this->assertSame('200', $campaign->prefix);
        $this->assertSame('-', $campaign->remarks);
        $this->assertNull($campaign->allocations()->where('channel_allocation', 'CH-D1')->value('media_gateway'));
        $this->assertSame('DITO SIM', $campaign->allocations()->where('channel_allocation', 'CH-D1')->value('network'));
        $this->assertSame('Globe SIM', $campaign->allocations()->where('channel_allocation', 'CH-D2')->value('network'));
        $this->assertSame('Smart SIM', $campaign->allocations()->where('channel_allocation', 'CH-D3')->value('network'));
        $this->assertSame('TNT', $campaign->allocations()->where('channel_allocation', 'CH-D4')->value('network'));
        $this->assertSame(8, $campaign->allocations()->where('channel_allocation', 'CH-D4')->value('total_channel_allocated'));

        $blockedAllocation = $this->postJson('/channel-allocation/import/preview', [
            'file' => $this->upload($this->makeSpreadsheet([
                ['Dash Bad CH', '4', '111', '100', '', '-', '1'],
            ])),
        ])->assertOk()->json();
        $this->assertFalse($blockedAllocation['valid']);
        $this->assertStringContainsString('Channel cannot be -', $blockedAllocation['rows'][0]['error']);
    }

    public function test_blank_campaign_rows_stay_on_the_same_campaign_with_unique_allocations(): void
    {
        $this->actingAs($this->admin);
        $this->sip('CH-001', 'Globe SIM', 10);
        $this->sip('CH-002', 'Smart SIM', 11);
        $this->sip('CH-003', 'DITO SIM', 12);
        ChannelAllocationCampaign::create(['name' => 'Campaign A']);
        $path = $this->makeSpreadsheet([
            ['Campaign A', '5', '123456789', '100', '', 'CH-001', '1'],
            ['', '', '', '', '', 'CH-002', '2'],
            ['', '', '', '', '', 'CH-003', '3'],
        ]);

        $preview = $this->postJson('/channel-allocation/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();

        $this->assertTrue($preview['valid']);
        $this->assertSame(1, $preview['summary']['campaigns']);
        $this->assertSame(3, $preview['summary']['allocations']);
        $this->assertSame('Campaign A', $preview['rows'][0]['campaign']);
        $this->assertSame('Campaign A', $preview['rows'][1]['campaign']);
        $this->assertSame('Campaign A', $preview['rows'][2]['campaign']);
        $this->assertSame('CH-001', $preview['rows'][0]['channel']);
        $this->assertSame('CH-002', $preview['rows'][1]['channel']);
        $this->assertSame('CH-003', $preview['rows'][2]['channel']);
        $this->assertSame('Globe SIM', $preview['rows'][0]['network']);
        $this->assertSame('Smart SIM', $preview['rows'][1]['network']);
        $this->assertSame('DITO SIM', $preview['rows'][2]['network']);
        $this->assertSame('', $preview['rows'][1]['fte']);
        $this->assertSame('123456789', $preview['rows'][1]['caller_id']);
        $this->assertSame('100', $preview['rows'][1]['prefix']);
        $this->assertSame('1', $preview['rows'][0]['line_priority']);
        $this->assertSame('2', $preview['rows'][1]['line_priority']);
        $this->assertSame('3', $preview['rows'][2]['line_priority']);

        $this->postJson('/channel-allocation/import/confirm', ['token' => $preview['token']])
            ->assertOk()
            ->assertJson(['ok' => true, 'allocations' => 3, 'campaigns' => 1]);

        $this->assertSame(1, ChannelAllocationCampaign::where('name', 'Campaign A')->count());
        $campaign = ChannelAllocationCampaign::where('name', 'Campaign A')->firstOrFail();
        $this->assertSame(3, $campaign->allocations()->count());
        $this->assertSame(33, $campaign->fresh()->total_channels_allocated);
        $this->assertNull($campaign->fte);
        $this->assertTrue($campaign->allocations()->where('channel_allocation', 'CH-001')->exists());
        $this->assertTrue($campaign->allocations()->where('channel_allocation', 'CH-002')->exists());
        $this->assertTrue($campaign->allocations()->where('channel_allocation', 'CH-003')->exists());
        $this->assertSame(1, $campaign->allocations()->where('channel_allocation', 'CH-001')->value('line_priority'));
        $this->assertSame(10, $campaign->allocations()->where('channel_allocation', 'CH-001')->value('total_channel_allocated'));
        $this->assertSame(12, $campaign->allocations()->where('channel_allocation', 'CH-003')->value('total_channel_allocated'));

        $duplicatePath = $this->makeSpreadsheet([
            ['Campaign A', '5', '123456789', '100', '', 'CH-001', '1'],
        ]);
        $duplicate = $this->postJson('/channel-allocation/import/preview', [
            'file' => $this->upload($duplicatePath),
        ])->assertOk()->json();
        $this->assertFalse($duplicate['valid']);
        $this->assertStringContainsString('Channel already exists', $duplicate['rows'][0]['error']);
    }

    public function test_preview_rejects_campaign_missing_from_master_and_keeps_other_row_errors(): void
    {
        $this->actingAs($this->admin);
        $this->sip('CH-OK', 'Globe SIM', 8);
        ChannelAllocationCampaign::create(['name' => 'Campaign A']);
        ChannelAllocationCampaign::create([
            'name' => 'PDC Only',
            'listed_in_campaigns' => false,
        ]);

        $preview = $this->postJson('/channel-allocation/import/preview', [
            'file' => $this->upload($this->makeSpreadsheet([
                ['TEST-CAMPAIGN', '2', str_repeat('c', 256), '100', '', 'UNKNOWN-CH', 'ABC'],
                ['PDC Only', '2', '123', '100', '', 'CH-OK', '1'],
                ['Campaign A', '2', '123', '100', '', 'CH-OK', '1'],
            ])),
        ])->assertOk()->json();

        $this->assertFalse($preview['valid']);
        $this->assertTrue($preview['rows'][2]['valid']);
        $this->assertStringContainsString(
            'Campaign does not exist in Master Campaign. Create the campaign in Master Campaign before adding a Channel Allocation.',
            $preview['rows'][0]['error']
        );
        $this->assertStringContainsString('Caller ID: ', $preview['rows'][0]['error']);
        $this->assertStringContainsString('Line Priority: ', $preview['rows'][0]['error']);
        $this->assertStringContainsString('Channel must match an existing SIP Name or GSM Hostname', $preview['rows'][0]['error']);
        $this->assertStringContainsString(
            'Campaign does not exist in Master Campaign. Create the campaign in Master Campaign before adding a Channel Allocation.',
            $preview['rows'][1]['error']
        );

        $this->postJson('/channel-allocation/import/confirm', ['token' => $preview['token']])->assertStatus(422);
        $this->assertSame(0, ChannelAllocation::count());
        $this->assertDatabaseMissing('channel_allocation_campaigns', ['name' => 'TEST-CAMPAIGN']);
        $this->assertFalse((bool) ChannelAllocationCampaign::where('name', 'PDC Only')->value('listed_in_campaigns'));
        $this->get('/campaigns')->assertOk()->assertDontSee('TEST-CAMPAIGN')->assertDontSee('PDC Only');
    }

    private function sip(string $name, string $network = 'Globe SIM', int $count = 10): SipChannel
    {
        return SipChannel::query()->firstOrCreate(
            ['etpi_sip_name' => $name],
            ['network' => $network, 'channel_count' => $count]
        );
    }

    private function gsm(string $hostname, ?string $network = 'Globe SIM', int $count = 10): MediaGateway
    {
        return MediaGateway::query()->firstOrCreate(
            ['hostname' => $hostname],
            [
                'site_name' => 'WFH',
                'site_code' => $hostname,
                'ip_address' => '10.24.28.'.random_int(20, 250),
                'network' => $network,
                'channel_count' => $count,
                'username' => 'root',
                'database' => 'asteriskcdrdb',
            ]
        );
    }

    /**
     * @param  list<list<string|int>>  $rows
     */
    private function makeSpreadsheet(array $rows): string
    {
        $headers = [
            'Campaign',
            'FTE',
            'Caller ID',
            'Prefix',
            'Remarks',
            'Channel',
            'Line Priority',
        ];

        return app(XlsxService::class)->export($headers, $rows, 'import-test.xlsx');
    }

    /**
     * @param  list<list<string|int>>  $rows
     */
    private function makeExcelSharedStringSpreadsheet(array $rows): string
    {
        $shared = [];
        $sheetRows = '';
        $rowNumber = 1;
        foreach ($rows as $row) {
            $sheetRows .= '<row r="'.$rowNumber.'">';
            foreach (array_values($row) as $index => $value) {
                $cellRef = $this->excelColumnName($index + 1).$rowNumber;
                $text = (string) $value;
                if (preg_match('/^-?\d+$/', $text)) {
                    $sheetRows .= '<c r="'.$cellRef.'"><v>'.$text.'</v></c>';
                } else {
                    $shared[] = $text;
                    $sheetRows .= '<c r="'.$cellRef.'" t="s"><v>'.(count($shared) - 1).'</v></c>';
                }
            }
            $sheetRows .= '</row>';
            $rowNumber++;
        }

        $sharedXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        foreach ($shared as $text) {
            $sharedXml .= '<si><t>'.htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></si>';
        }
        $sharedXml .= '</sst>';

        $base = storage_path('app/temp-xlsx');
        if (! is_dir($base)) {
            mkdir($base, 0775, true);
        }
        $path = $base.'/'.uniqid('excel-style-', true).'.xlsx';
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/></Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', $sharedXml);
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>');
        $zip->close();

        return $path;
    }

    private function excelColumnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    private function upload(string $path, string $name = 'import-test.xlsx'): UploadedFile
    {
        return new UploadedFile($path, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
