<?php

namespace Tests\Feature;

use App\Models\SignalBooster;
use App\Models\User;
use App\Models\UserType;
use App\Services\XlsxService;
use App\Support\InventoryImportCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class SignalBoostersPageTest extends TestCase
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

    public function test_table_form_and_transfer_include_specs_and_drop_last_updated(): void
    {
        $this->actingAs($this->admin);
        $specs = "Frequency Band: 900/1800 MHz\nGain: 65 dB\nCoverage Area: 500 m²\nTechnology: 4G/LTE";

        SignalBooster::create([
            'model' => 'SB-900',
            'specs' => $specs,
            'serial_number' => 'SB-900-1',
            'location' => 'Estancia',
            'status' => 'Active',
        ]);

        $page = $this->get('/signal-boosters')->assertOk();
        $html = $page->getContent();
        $css = file_get_contents(resource_path('css/app.css'));
        $tableHtml = Str::between($html, '<table class="sb-table"', '</table>');

        $page->assertSee('class="sb-table"', false)
            ->assertSee('class="sb-specs-col"', false)
            ->assertSee('id="field_specs"', false)
            ->assertSee('sb-specs-field', false)
            ->assertSee('Frequency Band: 900/1800 MHz')
            ->assertSee('Gain: 65 dB')
            ->assertSee('Coverage Area: 500 m²')
            ->assertSee('Technology: 4G/LTE')
            ->assertSee('SB-900-1');

        $this->assertStringContainsString('>Id</th>', $tableHtml);
        $this->assertStringContainsString('>Model</th>', $tableHtml);
        $this->assertStringContainsString('>Specifications</th>', $tableHtml);
        $this->assertStringContainsString('>Serial Number</th>', $tableHtml);
        $this->assertStringContainsString('>Location</th>', $tableHtml);
        $this->assertStringContainsString('>Status</th>', $tableHtml);
        $this->assertStringContainsString('>Actions</th>', $tableHtml);
        $this->assertStringNotContainsString('Last Updated', $tableHtml);
        $this->assertTrue(strpos($tableHtml, '>Model</th>') < strpos($tableHtml, '>Specifications</th>'));
        $this->assertTrue(strpos($tableHtml, '>Specifications</th>') < strpos($tableHtml, '>Serial Number</th>'));
        $this->assertMatchesRegularExpression('/\.sb-table > thead > tr > th,[\s\S]*?text-align:\s*center/', $css);
        $this->assertMatchesRegularExpression('/\.sb-table > tbody > tr > td:nth-child\(3\) \{ width: 32%/', $css);
        $this->assertMatchesRegularExpression('/td\.sb-specs-col[\s\S]*?white-space:\s*pre-line/', $css);
        $this->assertStringContainsString('id="field_specs"', $html);
        $this->assertStringContainsString('for="field_specs">Specifications</label>', $html);
        $this->assertStringNotContainsString('>Specs</th>', $tableHtml);
        $this->assertStringNotContainsString('>Specs</label>', $html);
        $this->assertStringContainsString('rows="6"', $html);

        $this->post('/signal-boosters', [
            'model' => 'SB-910',
            'specs' => $specs,
            'serial_number' => 'SB-910-1',
            'location' => 'CTN',
            'status' => 'Active',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('signal_boosters', [
            'serial_number' => 'SB-910-1',
            'specs' => $specs,
        ]);
    }

    public function test_data_transfer_preserves_multiline_specs_and_does_not_carry_blanks(): void
    {
        $this->actingAs($this->admin);
        $specs = "Frequency Band: 900/1800 MHz\nGain: 65 dB\nCoverage Area: 500 m²\nTechnology: 4G/LTE";

        $template = $this->get('/signal-boosters/import/template')->assertOk();
        [$templateHeaders] = app(XlsxService::class)->read($template->getFile()->getPathname());
        $this->assertSame(['Id', 'Model', 'Specifications', 'Serial Number', 'Location', 'Status'], $templateHeaders);
        $this->assertNotContains('Last Updated', $templateHeaders);
        $this->assertNotContains('Actions', $templateHeaders);
        $this->assertSame(['specs'], InventoryImportCatalog::operation('signal-boosters')['no_carry']);

        $path = app(XlsxService::class)->export(
            ['Id', 'Model', 'Specifications', 'Serial Number', 'Location', 'Status'],
            [
                ['1', 'SB-A', $specs, 'SB-A-1', 'Alcar', 'Active'],
                ['2', 'SB-B', '', 'SB-B-1', 'SC5', 'Inactive'],
            ],
            'signal-boosters-import.xlsx'
        );

        $preview = $this->postJson('/signal-boosters/import/preview', [
            'file' => new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ])->assertOk()->json();

        $this->assertTrue($preview['valid'], $preview['rows'][0]['error'] ?? $preview['rows'][1]['error'] ?? '');
        $this->assertSame(['Model', 'Specifications', 'Serial Number', 'Location', 'Status'], $preview['headers']);
        $this->assertNotContains('Last Updated', $preview['headers']);
        $this->assertSame($specs, $preview['rows'][0]['specs']);
        $this->assertSame('', $preview['rows'][1]['specs']);

        $this->postJson('/signal-boosters/import/confirm', ['token' => $preview['token']])->assertOk();
        $this->assertSame($specs, SignalBooster::query()->where('serial_number', 'SB-A-1')->value('specs'));
        $this->assertNull(SignalBooster::query()->where('serial_number', 'SB-B-1')->value('specs'));

        $export = $this->get('/signal-boosters/export')->assertOk()->assertDownload('signal-boosters.xlsx');
        [$exportHeaders, $exportRows] = app(XlsxService::class)->read($export->getFile()->getPathname());
        $this->assertSame(['Id', 'Model', 'Specifications', 'Serial Number', 'Location', 'Status'], $exportHeaders);
        $this->assertNotContains('Last Updated', $exportHeaders);
        $exported = collect($exportRows)->first(fn ($row) => ($row[3] ?? '') === 'SB-A-1');
        $this->assertSame($specs, $exported[2] ?? null);
    }

    public function test_import_does_not_copy_serial_number_and_treats_a_dash_as_empty(): void
    {
        $this->actingAs($this->admin);
        $blocked = $this->postJson('/signal-boosters/import/preview', [
            'file' => new UploadedFile(
                app(XlsxService::class)->export(
                    ['Model', 'Specifications', 'Serial Number', 'Location', 'Status'],
                    [
                        ['SB-A', 'Band A', 'SB-A-1', 'Alcar', 'Active'],
                        ['', 'Band B', '', '', ''],
                    ],
                    'signal-boosters-import.xlsx'
                ),
                'import.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            ),
        ])->assertOk()->json();

        $this->assertFalse($blocked['valid']);
        $this->assertSame('SB-A', $blocked['rows'][1]['model']);
        $this->assertSame('Band B', $blocked['rows'][1]['specs']);
        $this->assertSame('', $blocked['rows'][1]['serial_number']);
        $this->assertSame('Alcar', $blocked['rows'][1]['location']);
        $this->assertSame('Active', $blocked['rows'][1]['status']);
        $this->assertStringContainsString('Serial Number is required', $blocked['rows'][1]['error']);

        $preview = $this->postJson('/signal-boosters/import/preview', [
            'file' => new UploadedFile(
                app(XlsxService::class)->export(
                    ['Model', 'Specifications', 'Serial Number', 'Location', 'Status'],
                    [
                        ['SB-A', 'Band A', 'SB-A-1', 'Alcar', 'Active'],
                        ['SB-B', '-', 'SB-B-1', '', ''],
                    ],
                    'signal-boosters-import.xlsx'
                ),
                'import.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            ),
        ])->assertOk()->json();

        $this->assertTrue($preview['valid'], $preview['rows'][1]['error'] ?? '');
        $this->assertSame('', $preview['rows'][1]['specs']);
        $this->assertSame('Alcar', $preview['rows'][1]['location']);
        $this->assertSame('Active', $preview['rows'][1]['status']);
        $this->postJson('/signal-boosters/import/confirm', ['token' => $preview['token']])->assertOk();
        $this->assertNull(SignalBooster::query()->where('serial_number', 'SB-B-1')->value('specs'));
        $this->assertSame('Alcar', SignalBooster::query()->where('serial_number', 'SB-B-1')->value('location'));
    }
}
