<?php

namespace Tests\Feature;

use App\Models\ArchiveRecording;
use App\Models\ChannelAllocationCampaign;
use App\Models\User;
use App\Models\UserType;
use App\Services\XlsxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ArchiveRecordingsPageTest extends TestCase
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

    public function test_campaigns_come_from_channel_allocation_and_are_not_hardcoded(): void
    {
        $this->actingAs($this->admin);
        ChannelAllocationCampaign::create(['name' => 'Atome', 'sort_order' => 1]);
        ChannelAllocationCampaign::create(['name' => 'Chinabank', 'sort_order' => 2]);

        $page = $this->get('/archive-recordings')->assertOk();
        $html = $page->getContent();

        $page->assertSee('Archive Recordings')
            ->assertSee('View, manage, and track archived call recordings.')
            ->assertSee('Atome')
            ->assertSee('Chinabank')
            ->assertSee('No archived recordings yet.')
            ->assertDontSee('No years available for this campaign.')
            ->assertSee('class="plus-btn"', false)
            ->assertSee('id="arAddButton"', false)
            ->assertSee('id="arSearchInput"', false)
            ->assertSee('placeholder="Search recordings..."', false)
            ->assertSee('class="toolbar"', false)
            ->assertSee('id="arAddModal"', false)
            ->assertSee('Add Archive Records')
            ->assertSee('>Save</button>', false)
            ->assertDontSee('>Add Records</button>', false)
            ->assertSee('Select or type a campaign...', false)
            ->assertSee('Select Year')
            ->assertSee('Select Month')
            ->assertSee('id="arAddLocation"', false)
            ->assertSee('for="arAddLocation">Location</label>', false)
            ->assertSee('data-value="ALCAR"', false)
            ->assertSee('>Alcar</button>', false)
            ->assertSee('data-value="ESTANCIA"', false)
            ->assertSee('>Estancia</button>', false)
            ->assertSee('data-value="SKYRISE"', false)
            ->assertSee('>Skyrise</button>', false)
            ->assertSee('data-value="CG3"', false)
            ->assertSee('>CG3</button>', false)
            ->assertSee('data-value="CTN"', false)
            ->assertSee('>CTN</button>', false)
            ->assertSee('data-value="SC5"', false)
            ->assertSee('>SC5</button>', false)
            ->assertSee('data-value="WFH"', false)
            ->assertSee('>WFH</button>', false)
            ->assertDontSee('>ALCAR</button>', false)
            ->assertDontSee('>ESTANCIA</button>', false)
            ->assertDontSee('>SKYRISE</button>', false)
            ->assertSee('id="arAddCampaign"', false)
            ->assertSee('pin-campaign-combo', false)
            ->assertSee('selected disabled hidden>Select Year</option>', false)
            ->assertSee('selected disabled hidden>Select Month</option>', false)
            ->assertDontSee('data-value="">Select Month</button>', false)
            ->assertSee('id="arAddMonthWrap"', false)
            ->assertSee('id="arAddMonthMenu"', false)
            ->assertSee('ar-add-dd-menu', false)
            ->assertSee('id="arDeleteModal"', false)
            ->assertSee('Delete Recording')
            ->assertSee('A Certificate of Deletion (PDF) is required before this recording can be marked as deleted.')
            ->assertSee('Choose a PDF file')
            ->assertSee('Browse')
            ->assertSee('Only PDF files are allowed.')
            ->assertSee('This certificate will be permanently stored with the record for audit purposes.')
            ->assertSee('Confirm Deletion')
            ->assertSee('Recording Files')
            ->assertDontSee('Bulk Upload')
            ->assertDontSee('Clear Filters')
            ->assertDontSee('class="search-clear"', false)
            ->assertDontSee('id="archiveSearchInput"', false)
            ->assertDontSee('Import Audio Logs')
            ->assertDontSee('Caller Number')
            ->assertDontSee('Agent Number')
            ->assertDontSee('Duration')
            ->assertDontSee('Data Transfer')
            ->assertDontSee('id="transferButton"', false)
            ->assertDontSee('Hardcoded Campaign')
            ->assertDontSee('>Add</', false);

        $this->assertSame(2, ChannelAllocationCampaign::count());
        $this->assertTrue(strpos($html, 'Atome') < strpos($html, 'Chinabank'));
        $this->assertStringContainsString('>+</button>', $html);
        $this->assertStringContainsString('ar-tree-card', $html);
        $this->assertStringNotContainsString('ar-node-month', $html);
        $this->assertStringNotContainsString('ar-file-name', $html);
        $this->assertStringNotContainsString('ar-file-icon', $html);
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('.ar-node-campaign > .ar-folder', $css);
        $this->assertMatchesRegularExpression('/\.ar-node-campaign\s*>\s*\.ar-folder\s*\{[^}]*min-height:\s*45px/', $css);
        $this->assertMatchesRegularExpression('/\.ar-node-campaign\s*>\s*\.ar-folder\s*>\s*span\s*\{[^}]*font-weight:\s*700/', $css);
        $this->assertStringNotContainsString('ar-folder-icon', $html);
        $this->assertMatchesRegularExpression('/\.ar-files\s+\.ar-col-name[\s\S]*?width:\s*25%/', $css);
        $this->assertMatchesRegularExpression('/\.ar-files\s+\.ar-col-status[\s\S]*?width:\s*25%/', $css);
        $this->assertMatchesRegularExpression('/\.ar-files\s+\.ar-col-location[\s\S]*?width:\s*25%/', $css);
        $this->assertMatchesRegularExpression('/\.ar-files\s+\.ar-col-actions[\s\S]*?width:\s*25%/', $css);
        $this->assertMatchesRegularExpression('/th\.ar-status-column,[\s\S]*?text-align:\s*center/', $css);
        $this->assertMatchesRegularExpression('/th\.actions-column,[\s\S]*?text-align:\s*center/', $css);
        $this->assertDoesNotMatchRegularExpression('/\.ar-files\s*>\s*thead\s*>\s*tr\s*>\s*th\s*\+\s*th,[\s\S]*?border-left:\s*1px/', $css);
        $this->assertMatchesRegularExpression('/\.ar-files\s*>\s*tbody\s*>\s*tr\s*>\s*td\s*\{[^}]*border:\s*0/', $css);
    }

    public function test_campaign_year_month_hierarchy_scopes_recordings(): void
    {
        $this->actingAs($this->admin);
        $atome = ChannelAllocationCampaign::create(['name' => 'Atome']);
        $china = ChannelAllocationCampaign::create(['name' => 'Chinabank']);

        ArchiveRecording::create([
            'campaign_id' => $atome->id,
            'file_name' => 'ATOME_20260901_102345.wav',
            'called_at' => '2026-09-01 10:23:45',
            'server' => '',
            'storage_path' => '',
            'status' => 'Available',
            'location' => 'Estancia',
        ]);
        ArchiveRecording::create([
            'campaign_id' => $atome->id,
            'file_name' => 'ATOME_20260815_090000.wav',
            'called_at' => '2026-08-15 09:00:00',
            'server' => '',
            'storage_path' => '',
            'status' => 'Available',
            'location' => 'CTN',
        ]);
        ArchiveRecording::create([
            'campaign_id' => $china->id,
            'file_name' => 'CHINA_20260901_111111.wav',
            'called_at' => '2026-09-01 11:11:11',
            'server' => '',
            'storage_path' => '',
            'status' => 'Available',
            'location' => 'Skyrise',
        ]);

        $page = $this->get('/archive-recordings')->assertOk();
        $page->assertSee('Atome')
            ->assertSee('Chinabank')
            ->assertSee('2026')
            ->assertSee('September')
            ->assertSee('August')
            ->assertSee('ATOME_20260901_102345.wav')
            ->assertSee('ATOME_20260815_090000.wav')
            ->assertSee('CHINA_20260901_111111.wav')
            ->assertSee('Available')
            ->assertSee('Estancia')
            ->assertSee('CTN')
            ->assertSee('Skyrise')
            ->assertSee('>Month</th>', false)
            ->assertSee('>Status</th>', false)
            ->assertSee('>Location</th>', false)
            ->assertSee('>Action</th>', false)
            ->assertDontSee('Available/Deleted')
            ->assertDontSee('>File Name</th>', false)
            ->assertDontSee('>Actions</th>', false)
            ->assertDontSee('class="ar-folder-icon"', false)
            ->assertDontSee('class="ar-node-month"', false)
            ->assertDontSee('class="ar-file-name"', false)
            ->assertDontSee('class="ar-file-icon"', false)
            ->assertSee('class="ar-files"', false)
            ->assertSee('class="ar-month-row"', false)
            ->assertSee('class="ar-col-name"', false)
            ->assertSee('class="ar-col-status"', false)
            ->assertSee('class="ar-col-location"', false)
            ->assertSee('class="ar-col-actions"', false)
            ->assertSee('data-ar-text="Atome"', false)
            ->assertSee('id="arSearchInput"', false)
            ->assertSee('filterTree', false)
            ->assertDontSee('09171234567')
            ->assertDontSee('00:05:12');

        $this->get('/archive-recordings?campaign='.$atome->id.'&year=2026&month=9')
            ->assertOk()
            ->assertSee('open', false)
            ->assertSee('ATOME_20260901_102345.wav');
    }

    public function test_play_and_download_use_existing_file(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create(['name' => 'Atome']);
        $dir = storage_path('app/archive-recordings');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $path = $dir.'/play-test.wav';
        file_put_contents($path, 'RIFF');
        $record = ArchiveRecording::create([
            'campaign_id' => $campaign->id,
            'file_name' => 'play-test.wav',
            'called_at' => '2026-09-01 10:00:00',
            'server' => '',
            'storage_path' => 'archive-recordings/play-test.wav',
            'status' => 'Available',
        ]);

        $this->get('/archive-recordings/'.$record->id.'/play')->assertOk();
        $this->get('/archive-recordings/'.$record->id.'/download')->assertOk()->assertDownload('play-test.wav');
        @unlink($path);
    }

    public function test_added_channel_allocation_campaign_appears_automatically(): void
    {
        $this->actingAs($this->admin);
        ChannelAllocationCampaign::create(['name' => 'Atome']);
        $this->get('/archive-recordings')->assertOk()->assertDontSee('Marketlink');

        ChannelAllocationCampaign::create(['name' => 'Marketlink']);
        $this->get('/archive-recordings')->assertOk()->assertSee('Marketlink')->assertSee('Atome');
        $this->assertSame(2, ChannelAllocationCampaign::count());
    }

    public function test_import_template_uses_recording_columns(): void
    {
        $this->actingAs($this->admin);
        $template = $this->get('/archive-recordings/import/template')->assertOk()->assertDownload('archive-recordings-template.xlsx');
        [$headers] = app(XlsxService::class)->read($template->getFile()->getPathname());
        $this->assertSame([
            'Campaign',
            'File Name',
            'Call Date & Time',
            'Caller Number',
            'Agent Number',
            'Duration',
            'Location',
            'Storage Path',
        ], $headers);
    }

    public function test_audio_import_assigns_files_to_channel_allocation_campaign_year_and_month(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create(['name' => 'RCBC Bankard']);
        $one = UploadedFile::fake()->create('RCBC_20260901_102345.wav', 20, 'audio/wav');
        $two = UploadedFile::fake()->create('RCBC_20260901_110512.mp3', 12, 'audio/mpeg');

        $response = $this->postJson('/archive-recordings/import/audio', [
            'campaign_id' => $campaign->id,
            'year' => 2026,
            'month' => 9,
            'location' => 'ALCAR',
            'files' => [$one, $two],
        ])->assertOk()->assertJson(['ok' => true, 'records' => 2]);

        $this->assertSame(2, ArchiveRecording::count());
        $this->assertTrue(ArchiveRecording::query()->where('campaign_id', $campaign->id)->where('file_name', 'RCBC_20260901_102345.wav')->exists());
        $record = ArchiveRecording::query()->where('file_name', 'RCBC_20260901_102345.wav')->first();
        $this->assertSame('2026-09-01 10:23:45', $record->called_at->format('Y-m-d H:i:s'));
        $this->assertSame('Available', $record->status);
        $this->assertSame('ALCAR', $record->location);
        $this->assertNotEmpty($record->storage_path);
        $this->assertFileExists(storage_path('app/private/'.$record->storage_path));
        $this->get('/archive-recordings')
            ->assertOk()
            ->assertSee('RCBC_20260901_102345.wav')
            ->assertSee('RCBC_20260901_110512.mp3')
            ->assertSee('Available')
            ->assertSee('ALCAR');
        $this->get('/archive-recordings/'.$record->id.'/play')->assertOk();
        $this->get('/archive-recordings/'.$record->id.'/download')->assertOk()->assertDownload('RCBC_20260901_102345.wav');
        $this->assertStringContainsString('campaign='.$campaign->id, (string) $response->json('redirect'));
        $this->assertStringContainsString('year=2026', (string) $response->json('redirect'));
        $this->assertStringContainsString('month=9', (string) $response->json('redirect'));

        foreach (ArchiveRecording::query()->get() as $imported) {
            @unlink(storage_path('app/private/'.$imported->storage_path));
        }
    }

    public function test_audio_import_accepts_typed_campaign_name(): void
    {
        $this->actingAs($this->admin);
        $file = UploadedFile::fake()->create('typed_20260901_102345.wav', 20, 'audio/wav');

        $this->postJson('/archive-recordings/import/audio', [
            'campaign' => 'Typed AR Campaign',
            'year' => 2026,
            'month' => 9,
            'files' => [$file],
        ])->assertOk()->assertJson(['ok' => true, 'records' => 1]);

        $this->assertDatabaseHas('channel_allocation_campaigns', ['name' => 'Typed AR Campaign']);
        $campaign = ChannelAllocationCampaign::query()->where('name', 'Typed AR Campaign')->first();
        $this->assertTrue(
            ArchiveRecording::query()->where('campaign_id', $campaign->id)->where('file_name', 'typed_20260901_102345.wav')->exists()
        );

        foreach (ArchiveRecording::query()->get() as $imported) {
            @unlink(storage_path('app/private/'.$imported->storage_path));
        }
    }

    public function test_audio_import_rejects_files_over_100mb(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create(['name' => 'Atome']);
        $tooBig = UploadedFile::fake()->create('huge.wav', 102401, 'audio/wav');

        $this->postJson('/archive-recordings/import/audio', [
            'campaign_id' => $campaign->id,
            'year' => 2026,
            'month' => 9,
            'files' => [$tooBig],
        ])->assertStatus(422)->assertJsonFragment(['ok' => false]);

        $this->assertSame(0, ArchiveRecording::count());
    }

    public function test_audio_import_requires_create_permission(): void
    {
        $viewer = User::factory()->create([
            'user_type_id' => UserType::where('name', 'Standard User')->value('id'),
            'status' => 'Active',
        ]);
        $this->actingAs($viewer);
        $campaign = ChannelAllocationCampaign::create(['name' => 'Atome']);
        $file = UploadedFile::fake()->create('viewer.wav', 10, 'audio/wav');

        $this->postJson('/archive-recordings/import/audio', [
            'campaign_id' => $campaign->id,
            'year' => 2026,
            'month' => 9,
            'files' => [$file],
        ])->assertForbidden();
        $this->assertSame(0, ArchiveRecording::count());
    }

    public function test_delete_requires_pdf_certificate_and_keeps_recording(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create(['name' => 'Atome']);
        $record = ArchiveRecording::create([
            'campaign_id' => $campaign->id,
            'file_name' => 'ATOME_DEL_1.wav',
            'called_at' => '2026-09-01 10:00:00',
            'server' => '',
            'storage_path' => '',
            'status' => 'Available',
        ]);

        $this->from('/archive-recordings')->delete('/archive-recordings/'.$record->id)->assertRedirect('/archive-recordings');
        $record->refresh();
        $this->assertSame('Available', $record->status);
        $this->assertTrue(ArchiveRecording::query()->whereKey($record->id)->exists());

        $this->from('/archive-recordings')->delete('/archive-recordings/'.$record->id, [
            'certificate' => UploadedFile::fake()->create('note.txt', 10, 'text/plain'),
        ])->assertRedirect('/archive-recordings');
        $record->refresh();
        $this->assertSame('Available', $record->status);
        $this->assertNull($record->certificate_path);

        $this->from('/archive-recordings')->delete('/archive-recordings/'.$record->id, [
            'certificate' => UploadedFile::fake()->create('certificate.pdf', 20, 'application/pdf'),
        ])->assertRedirect('/archive-recordings');
        $record->refresh();
        $this->assertSame('Deleted', $record->status);
        $this->assertNotEmpty($record->certificate_path);
        $this->assertFileExists(storage_path('app/private/'.$record->certificate_path));
        $this->assertTrue(ArchiveRecording::query()->whereKey($record->id)->exists());

        $page = $this->get('/archive-recordings')->assertOk();
        $page->assertSee('Deleted')
            ->assertSee('ATOME_DEL_1.wav')
            ->assertSee('/archive-recordings/'.$record->id.'/certificate', false)
            ->assertSee('target="_blank"', false)
            ->assertDontSee('data-ar-delete data-id="'.$record->id.'"', false);

        $certificate = $this->get('/archive-recordings/'.$record->id.'/certificate')->assertOk();
        $this->assertStringContainsString('inline', (string) $certificate->headers->get('Content-Disposition'));
        @unlink(storage_path('app/private/'.$record->certificate_path));
    }

    public function test_duration_is_read_from_the_audio_file(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create(['name' => 'Atome']);
        $dir = storage_path('app/archive-recordings');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $wav = $this->silentWav(3);
        $stored = UploadedFile::fake()->createWithContent('ATOME_20260901_120000.wav', $wav);

        $this->postJson('/archive-recordings/import/audio', [
            'campaign_id' => $campaign->id,
            'year' => 2026,
            'month' => 9,
            'files' => [$stored],
        ])->assertOk()->assertJson(['ok' => true, 'records' => 1]);

        $record = ArchiveRecording::query()->where('file_name', 'ATOME_20260901_120000.wav')->first();
        $this->assertNotNull($record);
        $this->assertSame('00:00:03', $record->duration);
        $this->assertSame('Available', $record->status);

        foreach (ArchiveRecording::query()->get() as $imported) {
            @unlink(storage_path('app/private/'.$imported->storage_path));
            @unlink(storage_path('app/'.$imported->storage_path));
        }
    }

    public function test_play_and_download_reject_paths_outside_archive_storage(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create(['name' => 'Atome']);
        $secret = storage_path('app/secret-outside.txt');
        file_put_contents($secret, 'RIFFSECRET');
        $record = ArchiveRecording::create([
            'campaign_id' => $campaign->id,
            'file_name' => 'outside.wav',
            'called_at' => '2026-09-01 10:00:00',
            'server' => '',
            'storage_path' => '../secret-outside.txt',
            'status' => 'Available',
        ]);

        $this->from('/archive-recordings')->get('/archive-recordings/'.$record->id.'/play')
            ->assertRedirect('/archive-recordings')
            ->assertSessionHas('error', 'The recording file is not available.');
        $this->from('/archive-recordings')->get('/archive-recordings/'.$record->id.'/download')
            ->assertRedirect('/archive-recordings')
            ->assertSessionHas('error', 'The recording file is not available.');

        $record->update(['storage_path' => $secret]);
        $this->from('/archive-recordings')->get('/archive-recordings/'.$record->id.'/play')
            ->assertRedirect('/archive-recordings')
            ->assertSessionHas('error', 'The recording file is not available.');

        $record->update(['storage_path' => '%2e%2e/secret-outside.txt']);
        $this->from('/archive-recordings')->get('/archive-recordings/'.$record->id.'/play')
            ->assertRedirect('/archive-recordings')
            ->assertSessionHas('error', 'The recording file is not available.');

        @unlink($secret);
    }

    private function silentWav(int $seconds): string
    {
        $sampleRate = 8000;
        $samples = $seconds * $sampleRate;
        $data = str_repeat("\x00\x00", $samples);
        $fmt = pack('v', 1).pack('v', 1).pack('V', $sampleRate).pack('V', $sampleRate * 2).pack('v', 2).pack('v', 16);

        return 'RIFF'.pack('V', 36 + strlen($data)).'WAVEfmt '.pack('V', 16).$fmt.'data'.pack('V', strlen($data)).$data;
    }
}
