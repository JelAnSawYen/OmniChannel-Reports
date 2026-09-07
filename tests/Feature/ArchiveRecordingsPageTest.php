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
        $css = file_get_contents(resource_path('css/app.css'));

        $page->assertSee('Archived Recordings')
            ->assertSee('Search, view, and manage historical call recordings.')
            ->assertSee('Atome')
            ->assertSee('Chinabank')
            ->assertSee('name="campaign"', false)
            ->assertSee('name="year"', false)
            ->assertSee('name="month"', false)
            ->assertSee('Now Playing')
            ->assertSee('Clear Filters')
            ->assertSee('Import Audio Logs')
            ->assertSee('id="arImportAudioBtn"', false)
            ->assertDontSee('Data Transfer')
            ->assertDontSee('id="transferButton"', false)
            ->assertDontSee('id="importModal"', false)
            ->assertSee('id="arImportModal"', false)
            ->assertDontSee('id="arPlayerMeta"', false)
            ->assertSee('id="arImportCampaign"', false)
            ->assertSee('Drag & Drop Audio Files Here')
            ->assertSee('Browse Files')
            ->assertSee('class="page-head"', false)
            ->assertSee('class="ar-filter-card"', false)
            ->assertSee('ar-filter-row-primary', false)
            ->assertSee('ar-filter-row-secondary', false)
            ->assertSee('Enter caller number')
            ->assertSee('Enter agent number')
            ->assertSee('Search recordings (file name, caller, agent, etc...)')
            ->assertSee('class="ar-workspace"', false)
            ->assertSee('data-ar-dd', false)
            ->assertSee('id="arPlayer"', false)
            ->assertSee('id="arWaveformCanvas"', false)
            ->assertSee('Download Recording')
            ->assertDontSee('Hardcoded Campaign')
            ->assertDontSee('>Server<')
            ->assertDontSee('Archive Server');

        $this->assertSame(2, ChannelAllocationCampaign::count());
        $this->assertTrue(strpos($html, 'Atome') < strpos($html, 'Chinabank'));
        $this->assertStringContainsString('.ar-filter-card', $css);
        $this->assertStringContainsString('.ar-workspace', $css);
        $this->assertStringContainsString('.ar-dd-menu', $css);
        $this->assertStringContainsString('--ar-dd-menu-height', $css);
        $this->assertStringContainsString('top: calc(100% + 4px)', $css);
        $this->assertStringNotContainsString('.ar-dd.is-up', $css);
        $this->assertStringNotContainsString('.ar-dd.is-down', $css);
        $this->assertMatchesRegularExpression('/\.ar-dd-toggle svg\s*\{[^}]*max-width:\s*10px/s', $css);
        $this->assertMatchesRegularExpression('/\.ar-date-icon svg\s*\{[^}]*max-width:\s*12px/s', $css);
        $this->assertStringContainsString('width="10"', $html);
        $this->assertStringContainsString('width="12"', $html);
        $this->assertTrue(strpos($html, 'id="arImportAudioBtn"') < strpos($html, 'id="archiveSearchInput"'));
        $this->assertTrue(strpos($html, 'id="archiveSearchInput"') < strpos($html, 'ar-search-btn'));
        $this->assertStringContainsString('>Archive Recordings</span></a>', $html);
        $this->assertStringContainsString('.ar-play-circle', $css);
        $this->assertStringContainsString('.ar-play-circle:not(.is-playing) .ar-icon-play', $css);
        $this->assertStringContainsString('.ar-play-circle.is-playing .ar-icon-pause', $css);
        $this->assertStringContainsString('.ar-play-toggle:not(.is-playing) .ar-icon-play', $css);
        $this->assertStringContainsString('.ar-play-toggle.is-playing .ar-icon-pause', $css);
        $this->assertStringContainsString('.ar-dd-toggle.has-value span', $css);
        $this->assertMatchesRegularExpression('/\.ar-import-btn(?:,[^{]*)*\{[^}]*background:\s*var\(--blue\)/s', $css);
        $this->assertMatchesRegularExpression('/\.ar-import-btn \.btn-icon\s*\{[^}]*color:\s*#fff/s', $css);
        $this->assertMatchesRegularExpression('/\.ar-skip\s*\{[^}]*border-radius:\s*50%/s', $css);
        $this->assertStringContainsString('overflow-x: hidden', $css);
        $this->assertStringContainsString('id="arSkipBack"', $html);
        $this->assertStringContainsString('id="arSkipForward"', $html);
        $this->assertStringContainsString('class="ar-skip"', $html);
        $this->assertStringContainsString('const count = 72', $html);
        $this->assertStringContainsString('ar-filter-row-secondary', $html);
        $this->assertStringNotContainsString('spaceAbove', $html);
        $this->assertStringNotContainsString('positionDropdown', $html);
        $this->assertStringNotContainsString('is-up', $html);
        $this->assertStringNotContainsString('data-value="">Select campaign', $html);
        $this->assertStringNotContainsString('data-value="">Year', $html);
        $this->assertStringNotContainsString('data-value="">Month', $html);
        $this->assertStringContainsString('Delete Selected', $html);
        $this->assertStringNotContainsString('ca-menu-btn', $html);
        $this->assertStringNotContainsString('class="ca-menu"', $html);

        $atome = ChannelAllocationCampaign::query()->where('name', 'Atome')->first();
        $emptyYearHtml = $this->get('/archive-recordings?campaign='.$atome->id)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/>2026<.*?>2025<.*?>2024<.*?>2023<.*?>2022<.*?>2021<.*?>2020<.*?>2019<.*?>2018</s', $emptyYearHtml);
    }

    public function test_campaign_year_month_workflow_scopes_recordings_and_search(): void
    {
        $this->actingAs($this->admin);
        $atome = ChannelAllocationCampaign::create(['name' => 'Atome']);
        $china = ChannelAllocationCampaign::create(['name' => 'Chinabank']);

        ArchiveRecording::create([
            'campaign_id' => $atome->id,
            'file_name' => 'ATOME_20260901_102345.wav',
            'called_at' => '2026-09-01 10:23:45',
            'caller_number' => '09171234567',
            'agent_number' => '1001',
            'duration' => '00:05:12',
            'server' => '',
            'storage_path' => 'ATOME_20260901_102345.wav',
            'status' => 'Active',
        ]);
        ArchiveRecording::create([
            'campaign_id' => $atome->id,
            'file_name' => 'ATOME_20260815_090000.wav',
            'called_at' => '2026-08-15 09:00:00',
            'caller_number' => '09170000000',
            'agent_number' => '1002',
            'duration' => '00:01:00',
            'server' => '',
            'storage_path' => 'ATOME_20260815_090000.wav',
            'status' => 'Active',
        ]);
        ArchiveRecording::create([
            'campaign_id' => $china->id,
            'file_name' => 'CHINA_20260901_111111.wav',
            'called_at' => '2026-09-01 11:11:11',
            'caller_number' => '09179999999',
            'agent_number' => '2001',
            'duration' => '00:02:00',
            'server' => '',
            'storage_path' => 'CHINA_20260901_111111.wav',
            'status' => 'Active',
        ]);

        $yearPage = $this->get('/archive-recordings?campaign='.$atome->id)->assertOk();
        $yearHtml = $yearPage->getContent();
        $yearPage->assertDontSee('ATOME_20260901_102345.wav')
            ->assertDontSee('CHINA_20260901_111111.wav')
            ->assertDontSee('No years available for this campaign.')
            ->assertDontSee('>2017<', false);
        $this->assertMatchesRegularExpression('/>2026<.*?>2025<.*?>2024<.*?>2023<.*?>2022<.*?>2021<.*?>2020<.*?>2019<.*?>2018</s', $yearHtml);

        $this->get('/archive-recordings?campaign='.$atome->id.'&year=2018')
            ->assertOk()
            ->assertSee('value="2018"', false)
            ->assertDontSee('ATOME_20260901_102345.wav');

        $emptyMonthHtml = $this->get('/archive-recordings?campaign='.$atome->id.'&year=2018')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/>January<.*?>February<.*?>March<.*?>April<.*?>May<.*?>June<.*?>July<.*?>August<.*?>September<.*?>October<.*?>November<.*?>December</s', $emptyMonthHtml);
        $this->assertStringNotContainsString('No months available for this year.', $emptyMonthHtml);

        $monthListHtml = $this->get('/archive-recordings?campaign='.$atome->id.'&year=2026')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/>January<.*?>February<.*?>March<.*?>April<.*?>May<.*?>June<.*?>July<.*?>August<.*?>September<.*?>October<.*?>November<.*?>December</s', $monthListHtml);
        $this->get('/archive-recordings?campaign='.$atome->id.'&year=2026')
            ->assertOk()
            ->assertSee('September')
            ->assertSee('August')
            ->assertDontSee('ATOME_20260901_102345.wav');

        $this->get('/archive-recordings?campaign='.$atome->id.'&year=2018&month=1')
            ->assertOk()
            ->assertSee('January')
            ->assertSee('No call recordings found for this campaign, year, and month.')
            ->assertDontSee('ATOME_20260901_102345.wav');

        $monthPage = $this->get('/archive-recordings?campaign='.$atome->id.'&year=2026&month=9')->assertOk();
        $monthHtml = $monthPage->getContent();
        $monthPage->assertSee('ATOME_20260901_102345.wav')
            ->assertSee('09171234567')
            ->assertSee('1001')
            ->assertSee('00:05:12')
            ->assertDontSee('ATOME_20260815_090000.wav')
            ->assertDontSee('CHINA_20260901_111111.wav')
            ->assertSee('data-ar-play', false)
            ->assertSee('class="ar-play-circle"', false)
            ->assertSee('class="ar-icon-play ar-play-icon"', false)
            ->assertSee('class="ar-icon-pause"', false)
            ->assertDontSee('id="arPlayerMeta"', false)
            ->assertSee('/archive-recordings/');
        $this->assertSame(substr_count($monthHtml, 'class="ar-play-circle"'), substr_count($monthHtml, 'ar-play-icon'));
        $this->assertSame(1, substr_count($monthHtml, 'class="ar-play-circle"'));
        $this->assertGreaterThanOrEqual(2, substr_count($monthHtml, '<polygon points="9,6 9,18 19,12"/>'));
        $this->assertGreaterThanOrEqual(2, substr_count($monthHtml, 'ar-icon-pause'));
        $this->assertStringContainsString('ar-dd-toggle has-value', $monthHtml);

        $this->get('/archive-recordings?campaign='.$atome->id.'&year=2026&month=9&search=CHINA')
            ->assertOk()
            ->assertDontSee('CHINA_20260901_111111.wav')
            ->assertDontSee('ATOME_20260901_102345.wav');

        $this->get('/archive-recordings?campaign='.$atome->id.'&year=2026&month=9&search=09171234567')
            ->assertOk()
            ->assertSee('ATOME_20260901_102345.wav');

        $this->get('/archive-recordings?campaign='.$atome->id.'&year=2026&month=9&caller=09171234567')
            ->assertOk()
            ->assertSee('ATOME_20260901_102345.wav');

        $cleared = $this->get('/archive-recordings?campaign='.$atome->id.'&year=2026&month=9')->assertOk();
        $this->assertStringContainsString('id="archiveReset"', $cleared->getContent());
        $this->assertStringContainsString('href="'.url('/archive-recordings').'"', $cleared->getContent());
    }

    public function test_pagination_and_records_per_page(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create(['name' => 'Atome']);
        for ($i = 1; $i <= 12; $i++) {
            ArchiveRecording::create([
                'campaign_id' => $campaign->id,
                'file_name' => 'ATOME_PAGE_'.$i.'.wav',
                'called_at' => sprintf('2026-09-%02d 10:00:00', $i),
                'caller_number' => '0917000000'.$i,
                'agent_number' => '100'.$i,
                'duration' => '00:01:00',
                'server' => '',
                'storage_path' => 'ATOME_PAGE_'.$i.'.wav',
                'status' => 'Active',
            ]);
        }

        $this->get('/archive-recordings?campaign='.$campaign->id.'&year=2026&month=9&per_page=5')
            ->assertOk()
            ->assertSee('ATOME_PAGE_1.wav')
            ->assertDontSee('ATOME_PAGE_6.wav')
            ->assertSee('page=2', false)
            ->assertSee('per page');

        $this->get('/archive-recordings?campaign='.$campaign->id.'&year=2026&month=9&per_page=5&sort=newest')
            ->assertOk()
            ->assertSee('ATOME_PAGE_12.wav')
            ->assertDontSee('ATOME_PAGE_1.wav');
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
            'caller_number' => '09171111111',
            'agent_number' => '1001',
            'duration' => '00:00:01',
            'server' => '',
            'storage_path' => 'archive-recordings/play-test.wav',
            'status' => 'Active',
        ]);

        $this->get('/archive-recordings/'.$record->id.'/play')->assertOk();
        $this->get('/archive-recordings/'.$record->id.'/download')->assertOk()->assertDownload('play-test.wav');
        @unlink($path);
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

        $this->get('/archive-recordings?campaign='.$campaign->id.'&year=2026&month=9')
            ->assertOk()
            ->assertSee('00:00:03');

        $existingPath = $dir.'/existing-duration.wav';
        file_put_contents($existingPath, $this->silentWav(5));
        ArchiveRecording::create([
            'campaign_id' => $campaign->id,
            'file_name' => 'existing-duration.wav',
            'called_at' => '2026-09-02 10:00:00',
            'server' => '',
            'storage_path' => 'archive-recordings/existing-duration.wav',
            'duration' => '',
            'status' => 'Active',
        ]);
        $this->get('/archive-recordings?campaign='.$campaign->id.'&year=2026&month=9')
            ->assertOk()
            ->assertSee('00:00:05');
        $this->assertSame('00:00:05', ArchiveRecording::query()->where('file_name', 'existing-duration.wav')->value('duration'));

        foreach (ArchiveRecording::query()->get() as $imported) {
            @unlink(storage_path('app/private/'.$imported->storage_path));
            @unlink(storage_path('app/'.$imported->storage_path));
        }
        @unlink($existingPath);
    }

    private function silentWav(int $seconds): string
    {
        $sampleRate = 8000;
        $samples = $seconds * $sampleRate;
        $data = str_repeat("\x00\x00", $samples);
        $fmt = pack('v', 1).pack('v', 1).pack('V', $sampleRate).pack('V', $sampleRate * 2).pack('v', 2).pack('v', 16);

        return 'RIFF'.pack('V', 36 + strlen($data)).'WAVEfmt '.pack('V', 16).$fmt.'data'.pack('V', strlen($data)).$data;
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
            'files' => [$one, $two],
        ])->assertOk()->assertJson(['ok' => true, 'records' => 2]);

        $this->assertSame(2, ArchiveRecording::count());
        $this->assertTrue(ArchiveRecording::query()->where('campaign_id', $campaign->id)->where('file_name', 'RCBC_20260901_102345.wav')->exists());
        $record = ArchiveRecording::query()->where('file_name', 'RCBC_20260901_102345.wav')->first();
        $this->assertSame('2026-09-01 10:23:45', $record->called_at->format('Y-m-d H:i:s'));
        $this->assertNotEmpty($record->storage_path);
        $this->assertFileExists(storage_path('app/private/'.$record->storage_path));
        $this->get('/archive-recordings?campaign='.$campaign->id.'&year=2026&month=9')
            ->assertOk()
            ->assertSee('RCBC_20260901_102345.wav')
            ->assertSee('RCBC_20260901_110512.mp3');
        $this->get('/archive-recordings/'.$record->id.'/play')->assertOk();
        $this->get('/archive-recordings/'.$record->id.'/download')->assertOk()->assertDownload('RCBC_20260901_102345.wav');
        $this->assertStringContainsString('campaign='.$campaign->id, (string) $response->json('redirect'));
        $this->assertStringContainsString('year=2026', (string) $response->json('redirect'));
        $this->assertStringContainsString('month=9', (string) $response->json('redirect'));

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

    public function test_individual_and_bulk_delete_remove_recordings(): void
    {
        $this->actingAs($this->admin);
        $campaign = ChannelAllocationCampaign::create(['name' => 'Atome']);
        $one = ArchiveRecording::create([
            'campaign_id' => $campaign->id,
            'file_name' => 'ATOME_DEL_1.wav',
            'called_at' => '2026-09-01 10:00:00',
            'server' => '',
            'storage_path' => '',
            'status' => 'Active',
        ]);
        $two = ArchiveRecording::create([
            'campaign_id' => $campaign->id,
            'file_name' => 'ATOME_DEL_2.wav',
            'called_at' => '2026-09-01 11:00:00',
            'server' => '',
            'storage_path' => '',
            'status' => 'Active',
        ]);
        $three = ArchiveRecording::create([
            'campaign_id' => $campaign->id,
            'file_name' => 'ATOME_DEL_3.wav',
            'called_at' => '2026-09-01 12:00:00',
            'server' => '',
            'storage_path' => '',
            'status' => 'Active',
        ]);

        $this->delete('/archive-recordings/'.$one->id)->assertRedirect();
        $this->assertFalse(ArchiveRecording::query()->whereKey($one->id)->exists());

        $this->delete('/archive-recordings/bulk', ['ids' => [$two->id, $three->id]])->assertRedirect();
        $this->assertSame(0, ArchiveRecording::count());
    }
}
