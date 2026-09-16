<?php

namespace Tests\Feature;

use App\Models\DefectiveGsm;
use App\Models\User;
use App\Models\UserType;
use App\Services\XlsxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DefectiveGsmPageTest extends TestCase
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

    public function test_serial_tag_issue_textarea_and_mdy_reported_on(): void
    {
        $this->actingAs($this->admin);
        $issue = "Port 4 no TX\nIntermittent power drop\nNeeds replacement board";

        DefectiveGsm::create([
            'asset_code' => 'GSM-114',
            'location' => 'Alcar',
            'issue' => $issue,
            'reported_on' => '2026-08-01',
            'status' => 'Open',
        ]);

        $page = $this->get('/defective-gsm')->assertOk();
        $html = $page->getContent();
        $css = file_get_contents(resource_path('css/app.css'));
        $tableHtml = \Illuminate\Support\Str::between($html, '<table class="dg-table"', '</table>');

        $page->assertSee('class="dg-table"', false)
            ->assertSee('class="dg-issue-col"', false)
            ->assertSee('id="field_asset_code"', false)
            ->assertSee('id="field_issue"', false)
            ->assertSee('id="field_reported_on"', false)
            ->assertSee('Port 4 no TX')
            ->assertSee('Intermittent power drop')
            ->assertSee('8/1/2026')
            ->assertSee('GSM-114');

        $this->assertStringContainsString('>Serial Tag</th>', $tableHtml);
        $this->assertStringContainsString('>Issue</th>', $tableHtml);
        $this->assertStringContainsString('>Reported On</th>', $tableHtml);
        $this->assertStringNotContainsString('>Asset Code</th>', $tableHtml);
        $this->assertStringContainsString('for="field_asset_code">Serial Tag</label>', $html);
        $this->assertStringNotContainsString('>Asset Code</label>', $html);
        $this->assertStringContainsString('dg-issue-field', $html);
        $this->assertStringContainsString('rows="6"', $html);
        $this->assertStringContainsString('pdc-date-field', $html);
        $this->assertStringContainsString('placeholder="M/D/YYYY"', $html);
        $this->assertMatchesRegularExpression('/\.dg-table > thead > tr > th,[\s\S]*?text-align:\s*center/', $css);
        $this->assertMatchesRegularExpression('/\.dg-table > tbody > tr > td:nth-child\(4\) \{ width: 28%/', $css);
        $this->assertMatchesRegularExpression('/td\.dg-issue-col[\s\S]*?white-space:\s*pre-line/', $css);

        $this->post('/defective-gsm', [
            'asset_code' => 'GSM-200',
            'location' => 'CTN',
            'issue' => $issue,
            'reported_on' => '9/3/2026',
            'status' => 'Open',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $created = DefectiveGsm::query()->where('asset_code', 'GSM-200')->firstOrFail();
        $this->assertSame($issue, $created->issue);
        $this->assertSame('2026-09-03', $created->reported_on?->format('Y-m-d'));

        $this->post('/defective-gsm', [
            'asset_code' => 'GSM-201',
            'location' => 'SC5',
            'issue' => 'Dead',
            'reported_on' => '12/31/1999',
            'status' => 'Open',
        ])->assertRedirect()->assertSessionHasErrors('reported_on');
    }

    public function test_data_transfer_uses_serial_tag_and_preserves_multiline_issue(): void
    {
        $this->actingAs($this->admin);
        $issue = "Port 4 no TX\nIntermittent power drop";

        $template = $this->get('/defective-gsm/import/template')->assertOk();
        [$templateHeaders] = app(XlsxService::class)->read($template->getFile()->getPathname());
        $this->assertSame(['Id', 'Serial Tag', 'Location', 'Issue', 'Reported On', 'Status'], $templateHeaders);
        $this->assertNotContains('Asset Code', $templateHeaders);

        $path = app(XlsxService::class)->export(
            ['Id', 'Serial Tag', 'Location', 'Issue', 'Reported On', 'Status'],
            [
                ['1', 'GSM-A', 'Alcar', $issue, '8/1/2026', 'Open'],
            ],
            'defective-gsm-import.xlsx'
        );

        $preview = $this->postJson('/defective-gsm/import/preview', [
            'file' => new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ])->assertOk()->json();

        $this->assertTrue($preview['valid'], $preview['rows'][0]['error'] ?? '');
        $this->assertSame(['Serial Tag', 'Location', 'Issue', 'Reported On', 'Status'], $preview['headers']);
        $this->assertSame($issue, $preview['rows'][0]['issue']);
        $this->assertSame('8/1/2026', $preview['rows'][0]['reported_on']);

        $this->postJson('/defective-gsm/import/confirm', ['token' => $preview['token']])->assertOk();
        $this->assertSame($issue, DefectiveGsm::query()->where('asset_code', 'GSM-A')->value('issue'));
        $this->assertSame('2026-08-01', optional(DefectiveGsm::query()->where('asset_code', 'GSM-A')->first())->reported_on?->format('Y-m-d'));

        $export = $this->get('/defective-gsm/export')->assertOk()->assertDownload('defective-gsm.xlsx');
        [$exportHeaders, $exportRows] = app(XlsxService::class)->read($export->getFile()->getPathname());
        $this->assertSame(['Id', 'Serial Tag', 'Location', 'Issue', 'Reported On', 'Status'], $exportHeaders);
        $exported = collect($exportRows)->first(fn ($row) => ($row[1] ?? '') === 'GSM-A');
        $this->assertSame($issue, $exported[3] ?? null);
        $this->assertSame('8/1/2026', $exported[4] ?? null);
    }
}
