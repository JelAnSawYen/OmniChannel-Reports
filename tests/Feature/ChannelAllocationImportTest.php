<?php

namespace Tests\Feature;

use App\Models\ChannelAllocation;
use App\Models\ChannelAllocationCampaign;
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

        $this->assertContains('Campaign', $headers);
        $this->assertContains('Channel Allocation', $headers);
        $this->assertContains('Total Channel Allocated', $headers);
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
        $path = $this->makeSpreadsheet([
            ['Broken', '', '', '123', '100', '', 'CH-1', 'Globe SIM', '1', '10'],
            ['Broken', '10.0.0.2', '', '123', '100', '', 'CH-2', 'Globe SIM', '1', ''],
            ['Broken', '10.0.0.2', '2', '123', '100', '', 'CH-3', 'InvalidNet', '1', '8'],
        ]);

        $preview = $this->postJson('/channel-allocation/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();

        $this->assertFalse($preview['valid']);
        $this->assertSame(2, $preview['summary']['errors']);
        $this->assertStringContainsString('Missing Media Gateway', $preview['rows'][0]['error']);
        $this->assertStringContainsString('Total Channel Allocated is required', $preview['rows'][1]['error']);
        $this->assertTrue($preview['rows'][2]['valid']);
        $this->assertSame('InvalidNet', $preview['rows'][2]['network']);
        $this->assertSame(0, ChannelAllocationCampaign::count());
        $this->assertSame(0, ChannelAllocation::count());

        $this->postJson('/channel-allocation/import/confirm', ['token' => $preview['token']])
            ->assertStatus(422);
        $this->assertSame(0, ChannelAllocation::count());
    }

    public function test_valid_import_groups_campaigns_calculates_totals_and_keeps_export(): void
    {
        $this->actingAs($this->admin);
        $path = $this->makeSpreadsheet([
            ['Alpha Import', '10.1.1.1', '4', '555', '200', 'note', 'CH-A1', 'Globe SIM', '1', '20'],
            ['Alpha Import', '10.1.1.1', '4', '555', '200', '', 'CH-A2', 'Smart SIM', '2', '12'],
            ['Beta Import', '10.1.1.2', '3', 'N/A', '201', '', 'CH-B1', 'Eastern SIP', '1', '8'],
        ]);

        $preview = $this->postJson('/channel-allocation/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();

        $this->assertTrue($preview['valid']);
        $this->assertSame(0, $preview['summary']['errors']);
        $this->assertSame(2, $preview['summary']['campaigns']);
        $this->assertSame(3, $preview['summary']['allocations']);
        $this->assertSame(0, ChannelAllocationCampaign::count());

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
            ->assertSee('data-label="Allocations">2', false)
            ->assertDontSee('2 allocations')
            ->assertSee('Beta Import');

        $this->get('/channel-allocation/export')->assertOk()->assertDownload('channel-allocation.xlsx');
    }

    public function test_excel_shared_string_file_with_edited_values_is_parsed_and_previewed(): void
    {
        $this->actingAs($this->admin);
        $path = $this->makeExcelSharedStringSpreadsheet([
            ['Campaign', 'Media Gateway', 'FTE', 'Caller ID', 'Prefix', 'Remarks', 'Channel Allocation', 'Network', 'Line Priority', 'Total Channel Allocated'],
            ['Edited Campaign', '10.2.2.2', '5', '888', '400', 'edited', 'CH-EDIT-1', 'Globe SIM', '1', '15'],
            ['Edited Campaign', '10.2.2.2', '5', '888', '400', '', 'CH-EDIT-2', 'Smart SIM', '2', '9'],
        ]);

        [$headers, $rows] = app(XlsxService::class)->read($path);
        $this->assertSame('Campaign', $headers[0]);
        $this->assertSame('Channel Allocation', $headers[6]);
        $this->assertSame('Edited Campaign', trim((string) $rows[0][0]));
        $this->assertSame('CH-EDIT-2', trim((string) $rows[1][6]));

        $preview = $this->postJson('/channel-allocation/import/preview', [
            'file' => $this->upload($path, 'edited-template.xlsx'),
        ])->assertOk()->json();

        $this->assertTrue($preview['ok']);
        $this->assertTrue($preview['valid']);
        $this->assertSame(0, $preview['summary']['errors']);
        $this->assertSame(1, $preview['summary']['campaigns']);
        $this->assertSame(2, $preview['summary']['allocations']);
        $this->assertSame(0, ChannelAllocationCampaign::count());
    }

    public function test_excel_shared_string_file_with_errors_returns_preview_and_blocks_confirm(): void
    {
        $this->actingAs($this->admin);
        $path = $this->makeExcelSharedStringSpreadsheet([
            ['Campaign', 'Media Gateway', 'FTE', 'Caller ID', 'Prefix', 'Remarks', 'Channel Allocation', 'Network', 'Line Priority', 'Total Channel Allocated'],
            ['Broken Edit', '', '2', '123', '100', '', 'CH-1', 'Globe SIM', '1', '10'],
            ['Broken Edit', '10.0.0.2', '', '123', '100', '', 'CH-2', 'InvalidNet', '1', '8'],
        ]);

        $preview = $this->postJson('/channel-allocation/import/preview', [
            'file' => $this->upload($path, 'edited-errors.xlsx'),
        ])->assertOk()->json();

        $this->assertFalse($preview['valid']);
        $this->assertGreaterThan(0, $preview['summary']['errors']);
        $this->assertStringContainsString('Missing Media Gateway', $preview['rows'][0]['error']);
        $this->assertTrue($preview['rows'][1]['valid']);
        $this->assertSame('InvalidNet', $preview['rows'][1]['network']);
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

        $path = $this->makeSpreadsheet([
            ['Alpha Import', '10.1.1.1', '4', '555', '200', '', 'CH-NEW', 'Globe SIM', '2', '7'],
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

    public function test_preview_accepts_arbitrary_network_values_and_inherits_blank_network(): void
    {
        $this->actingAs($this->admin);
        $path = $this->makeSpreadsheet([
            ['Net Import', '10.3.3.3', '2', '111', '300', '', 'CH-N1', 'DITO SIM', '1', '5'],
            ['Net Import', '10.3.3.3', '2', '111', '300', '', 'CH-N2', 'TNT', '2', '6'],
            ['Net Import', '10.3.3.3', '2', '111', '300', '', 'CH-N3', 'Custom Carrier X', '3', '7'],
            ['Net Import', '10.3.3.3', '2', '111', '300', '', 'CH-N4', '', '4', '8'],
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

        $this->postJson('/channel-allocation/import/confirm', ['token' => $preview['token']])
            ->assertOk()
            ->assertJson(['ok' => true, 'allocations' => 4]);

        $campaign = ChannelAllocationCampaign::where('name', 'Net Import')->firstOrFail();
        $this->assertSame('DITO SIM', $campaign->allocations()->where('channel_allocation', 'CH-N1')->value('network'));
        $this->assertSame('TNT', $campaign->allocations()->where('channel_allocation', 'CH-N2')->value('network'));
        $this->assertSame('Custom Carrier X', $campaign->allocations()->where('channel_allocation', 'CH-N3')->value('network'));
        $this->assertSame('Custom Carrier X', $campaign->allocations()->where('channel_allocation', 'CH-N4')->value('network'));
    }

    public function test_media_gateway_accepts_one_or_two_ipv4_addresses_and_rejects_invalid(): void
    {
        $this->actingAs($this->admin);
        $path = $this->makeSpreadsheet([
            ['Gw Import', '10.24.28.38', '2', '111', '300', '', 'CH-G1', 'DITO SIM', '1', '5'],
            ['Gw Import', '10.24.28.38, 10.24.28.39', '2', '111', '300', '', 'CH-G2', 'TNT', '2', '6'],
            ['Gw Import', '', '2', '111', '300', '', 'CH-G3', 'Globe SIM', '3', '7'],
            ['Gw Import', 'not-an-ip', '2', '111', '300', '', 'CH-G4', 'Globe SIM', '4', '8'],
            ['Gw Import', '10.24.28.38, 10.24.28.39, 10.24.28.40', '2', '111', '300', '', 'CH-G5', 'Globe SIM', '5', '9'],
        ]);

        $preview = $this->postJson('/channel-allocation/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();

        $this->assertFalse($preview['valid']);
        $this->assertTrue($preview['rows'][0]['valid']);
        $this->assertTrue($preview['rows'][1]['valid']);
        $this->assertTrue($preview['rows'][2]['valid']);
        $this->assertSame('10.24.28.38', $preview['rows'][0]['media_gateway']);
        $this->assertSame('10.24.28.38, 10.24.28.39', $preview['rows'][1]['media_gateway']);
        $this->assertSame('10.24.28.38, 10.24.28.39', $preview['rows'][2]['media_gateway']);
        $this->assertStringContainsString('Media Gateway must be a valid IPv4 address', $preview['rows'][3]['error']);
        $this->assertStringContainsString('Media Gateway must contain 1 or 2 IPv4 addresses', $preview['rows'][4]['error']);
    }

    public function test_import_uses_master_campaign_fte_and_ignores_file_fte(): void
    {
        $this->actingAs($this->admin);
        ChannelAllocationCampaign::create([
            'name' => 'Fte Import',
            'fte' => 9,
            'sort_order' => 1,
        ]);

        $validPath = $this->makeSpreadsheet([
            ['Fte Import', '10.24.28.38', '4', '111', '300', '', 'CH-F1', 'DITO SIM', '1', '5'],
            ['Fte Import', '10.24.28.38', '', '111', '300', '', 'CH-F2', 'DITO SIM', '2', '6'],
            ['Fte Import', '10.24.28.38', '5', '111', '300', '', 'CH-F3', 'DITO SIM', '3', '7'],
            ['Fte Import', '10.24.28.38', '4.5', '111', '300', '', 'CH-F4', 'DITO SIM', '4', '8'],
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
        $path = $this->makeSpreadsheet([
            ['Prefix Import', '10.24.28.38', '4', '111', '100', '', 'CH-P1', 'DITO SIM', '1', '5'],
            ['Prefix Import', '10.24.28.38', '4', '111', '', '', 'CH-P2', 'DITO SIM', '2', '6'],
            ['Prefix Import', '10.24.28.38', '4', '111', '200', '', 'CH-P3', 'DITO SIM', '3', '7'],
            ['Prefix Import', '10.24.28.38', '4', '111', '', '', 'CH-P4', 'DITO SIM', '4', '8'],
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
        $path = $this->makeSpreadsheet([
            ['Caller Import', '10.24.28.38', '4', '123456789, 987654321', '100', '', 'CH-C1', 'DITO SIM', '1', '5'],
            ['Caller Import', '10.24.28.38', '4', '', '100', '', 'CH-C2', 'DITO SIM', '2', '6'],
            ['Caller Import', '10.24.28.38', '4', '555111000', '100', '', 'CH-C3', 'DITO SIM', '3', '7'],
            ['Caller Import', '10.24.28.38', '4', '', '100', '', 'CH-C4', 'DITO SIM', '4', '8'],
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

    public function test_dash_clears_carry_forward_fields_but_not_remarks_or_required_totals(): void
    {
        $this->actingAs($this->admin);
        $path = $this->makeSpreadsheet([
            ['Dash Import', '10.24.28.38', '4', '111', '100', '-', 'CH-D1', 'DITO SIM', '1', '5'],
            ['Dash Import', '-', '-', '-', '-', '', 'CH-D2', '-', '2', '6'],
            ['Dash Import', '', '', '', '', '', 'CH-D3', '', '3', '7'],
            ['Dash Import', '10.24.28.39', '5', '222', '200', 'note', 'CH-D4', 'TNT', '4', '8'],
        ]);

        $preview = $this->postJson('/channel-allocation/import/preview', [
            'file' => $this->upload($path),
        ])->assertOk()->json();

        $this->assertTrue($preview['valid'], $preview['rows'][1]['error'] ?? '');
        $this->assertSame('10.24.28.38', $preview['rows'][0]['media_gateway']);
        $this->assertSame('DITO SIM', $preview['rows'][0]['network']);
        $this->assertSame('', $preview['rows'][1]['media_gateway']);
        $this->assertSame('', $preview['rows'][1]['fte']);
        $this->assertSame('', $preview['rows'][1]['caller_id']);
        $this->assertSame('', $preview['rows'][1]['prefix']);
        $this->assertSame('', $preview['rows'][1]['network']);
        $this->assertSame('', $preview['rows'][2]['media_gateway']);
        $this->assertSame('', $preview['rows'][2]['fte']);
        $this->assertSame('', $preview['rows'][2]['network']);
        $this->assertSame('10.24.28.39', $preview['rows'][3]['media_gateway']);
        $this->assertSame('', $preview['rows'][3]['fte']);
        $this->assertSame('TNT', $preview['rows'][3]['network']);

        $this->postJson('/channel-allocation/import/confirm', ['token' => $preview['token']])
            ->assertOk()
            ->assertJson(['ok' => true, 'allocations' => 4]);

        $campaign = ChannelAllocationCampaign::where('name', 'Dash Import')->firstOrFail();
        $this->assertNull($campaign->fte);
        $this->assertSame('222', $campaign->caller_id);
        $this->assertSame('200', $campaign->prefix);
        $this->assertSame('-', $campaign->remarks);
        $this->assertSame('10.24.28.38', $campaign->allocations()->where('channel_allocation', 'CH-D1')->value('media_gateway'));
        $this->assertNull($campaign->allocations()->where('channel_allocation', 'CH-D2')->value('media_gateway'));
        $this->assertNull($campaign->allocations()->where('channel_allocation', 'CH-D2')->value('network'));
        $this->assertNull($campaign->allocations()->where('channel_allocation', 'CH-D3')->value('network'));
        $this->assertSame('10.24.28.39', $campaign->allocations()->where('channel_allocation', 'CH-D4')->value('media_gateway'));
        $this->assertSame('TNT', $campaign->allocations()->where('channel_allocation', 'CH-D4')->value('network'));

        $blockedAllocation = $this->postJson('/channel-allocation/import/preview', [
            'file' => $this->upload($this->makeSpreadsheet([
                ['Dash Bad CH', '10.24.28.38', '4', '111', '100', '', '-', 'DITO SIM', '1', '5'],
            ])),
        ])->assertOk()->json();
        $this->assertFalse($blockedAllocation['valid']);
        $this->assertStringContainsString('Channel Allocation cannot be -', $blockedAllocation['rows'][0]['error']);

        $blockedTotal = $this->postJson('/channel-allocation/import/preview', [
            'file' => $this->upload($this->makeSpreadsheet([
                ['Dash Bad Total', '10.24.28.38', '4', '111', '100', '', 'CH-DT', 'DITO SIM', '1', '-'],
            ])),
        ])->assertOk()->json();
        $this->assertFalse($blockedTotal['valid']);
        $this->assertStringContainsString('Total Channel Allocated must be a whole number', $blockedTotal['rows'][0]['error']);
    }

    public function test_blank_campaign_rows_stay_on_the_same_campaign_with_unique_allocations(): void
    {
        $this->actingAs($this->admin);
        $path = $this->makeSpreadsheet([
            ['Campaign A', '10.24.28.38', '5', '123456789', '100', '', 'CH-001', 'Globe SIM', '1', '10'],
            ['', '10.24.28.39', '', '', '', '', 'CH-002', 'Smart SIM', '2', '11'],
            ['', '10.24.28.40', '', '', '', '', 'CH-003', 'DITO SIM', '3', '12'],
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
        $this->assertSame('10.24.28.38', $preview['rows'][0]['media_gateway']);
        $this->assertSame('10.24.28.39', $preview['rows'][1]['media_gateway']);
        $this->assertSame('10.24.28.40', $preview['rows'][2]['media_gateway']);
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
            ['Campaign A', '10.24.28.38', '5', '123456789', '100', '', 'CH-001', 'Globe SIM', '1', '10'],
        ]);
        $duplicate = $this->postJson('/channel-allocation/import/preview', [
            'file' => $this->upload($duplicatePath),
        ])->assertOk()->json();
        $this->assertFalse($duplicate['valid']);
        $this->assertStringContainsString('Channel Allocation already exists', $duplicate['rows'][0]['error']);
    }

    /**
     * @param  list<list<string|int>>  $rows
     */
    private function makeSpreadsheet(array $rows): string
    {
        $headers = [
            'Campaign',
            'Media Gateway',
            'FTE',
            'Caller ID',
            'Prefix',
            'Remarks',
            'Channel Allocation',
            'Network',
            'Line Priority',
            'Total Channel Allocated',
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
