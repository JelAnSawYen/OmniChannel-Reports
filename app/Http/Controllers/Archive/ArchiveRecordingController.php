<?php

namespace App\Http\Controllers\Archive;

use App\Http\Controllers\Controller;
use App\Models\ArchiveRecording;
use App\Models\ChannelAllocationCampaign;
use App\Services\Archive\ArchiveRecordingImportService;
use App\Services\Logs\AuditLogger;
use App\Services\XlsxService;
use App\Support\Archive\ArchiveStorage;
use App\Support\Archive\AudioDuration;
use App\Support\OperationCatalog;
use App\Support\PublicError;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArchiveRecordingController extends Controller
{
    private const ARCHIVE_YEARS = [2026, 2025, 2024, 2023, 2022, 2021, 2020, 2019, 2018];

    private const MAX_AUDIO_BYTES = 104857600;

    private const AUDIO_EXTENSIONS = ['wav', 'mp3', 'mpeg', 'mpga', 'ogg', 'oga', 'webm', 'm4a', 'aac', 'flac', 'wma'];

    private const MONTH_NAMES = [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December',
    ];

    public function index(Request $request): View
    {
        $campaigns = ChannelAllocationCampaign::optionsForDropdown();
        $selectedCampaignId = (int) $request->query('campaign', 0);
        $selectedYear = (int) $request->query('year', 0);
        $selectedMonth = (int) $request->query('month', 0);
        if ($selectedCampaignId > 0 && ! $campaigns->contains('id', $selectedCampaignId)) {
            $selectedCampaignId = 0;
        }
        if ($selectedYear > 0 && ! in_array($selectedYear, self::ARCHIVE_YEARS, true)) {
            $selectedYear = 0;
            $selectedMonth = 0;
        }
        if ($selectedMonth < 1 || $selectedMonth > 12) {
            $selectedMonth = 0;
        }

        $recordingsByCampaign = ArchiveRecording::query()
            ->select(['id', 'campaign_id', 'file_name', 'called_at', 'location', 'status'])
            ->orderBy('file_name')
            ->orderBy('id')
            ->get()
            ->groupBy('campaign_id');

        $tree = $campaigns->map(function (ChannelAllocationCampaign $campaign) use ($recordingsByCampaign) {
            $items = collect($recordingsByCampaign->get($campaign->id, []));
            $years = $items
                ->filter(fn (ArchiveRecording $recording) => $recording->called_at !== null)
                ->groupBy(fn (ArchiveRecording $recording) => (int) $recording->called_at->year)
                ->sortKeysDesc()
                ->map(function ($yearItems, $year) {
                    $months = $yearItems
                        ->groupBy(fn (ArchiveRecording $recording) => (int) $recording->called_at->month)
                        ->sortKeysDesc()
                        ->map(function ($monthItems, $month) {
                            $monthNumber = (int) $month;

                            return [
                                'month' => $monthNumber,
                                'label' => self::MONTH_NAMES[$monthNumber] ?? (string) $monthNumber,
                                'recordings' => $monthItems->values(),
                            ];
                        })
                        ->values();

                    return [
                        'year' => (int) $year,
                        'months' => $months,
                    ];
                })
                ->values();

            return [
                'campaign' => $campaign,
                'years' => $years,
            ];
        });

        return view('archive-recordings.index', [
            'campaigns' => $campaigns,
            'tree' => $tree,
            'selectedCampaignId' => $selectedCampaignId > 0 ? $selectedCampaignId : null,
            'selectedYear' => $selectedYear > 0 ? $selectedYear : null,
            'selectedMonth' => $selectedMonth > 0 ? $selectedMonth : null,
            'monthNames' => self::MONTH_NAMES,
            'yearOptions' => self::ARCHIVE_YEARS,
            'locationOptions' => collect(OperationCatalog::locationMapSites())
                ->map(function (array $site, string $slug) {
                    $isPdc = $slug === 'pdc' || strcasecmp($site['name'], 'PDC') === 0;
                    if ($isPdc) {
                        return ['value' => 'WFH', 'label' => 'WFH'];
                    }

                    return [
                        'value' => $site['name'],
                        'label' => OperationCatalog::locations()[$slug] ?? $site['name'],
                    ];
                })
                ->values(),
        ]);
    }

    public function play(ArchiveRecording $recording): BinaryFileResponse|StreamedResponse|RedirectResponse
    {
        $path = $this->resolveFile($recording);
        if ($path === null) {
            return back()->with('error', 'The recording file is not available.');
        }

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="'.$this->downloadName($recording).'"',
        ]);
    }

    public function download(ArchiveRecording $recording): BinaryFileResponse|RedirectResponse
    {
        $path = $this->resolveFile($recording);
        if ($path === null) {
            return back()->with('error', 'The recording file is not available.');
        }

        return response()->download($path, $this->downloadName($recording));
    }

    public function certificate(ArchiveRecording $recording): BinaryFileResponse|RedirectResponse
    {
        if (! $recording->isDeleted()) {
            return back()->with('error', 'No certificate is available for this recording.');
        }

        $path = $this->resolveCertificate($recording);
        if ($path === null) {
            return back()->with('error', 'The certificate file is not available.');
        }

        $name = trim((string) $recording->certificate_name);
        if ($name === '') {
            $name = 'certificate-of-deletion.pdf';
        }

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="'.$name.'"',
        ]);
    }

    public function destroy(Request $request, ArchiveRecording $recording): RedirectResponse
    {
        if ($recording->isDeleted()) {
            return back()->with('error', 'This recording is already deleted.');
        }

        $uploaded = $request->file('certificate');
        if ($uploaded instanceof UploadedFile && ! $uploaded->isValid()) {
            return back()->with('error', 'The certificate failed to upload. '.$uploaded->getErrorMessage());
        }

        $validator = Validator::make($request->all(), [
            'certificate' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ], [
            'certificate.required' => 'A Certificate of Deletion PDF is required.',
            'certificate.mimes' => 'Only PDF files are allowed.',
            'certificate.max' => 'The certificate must be 10 MB or smaller.',
        ]);
        if ($validator->fails()) {
            return back()->with('error', (string) $validator->errors()->first());
        }

        $file = $request->file('certificate');
        if (! $file instanceof UploadedFile) {
            return back()->with('error', 'A Certificate of Deletion PDF is required.');
        }

        try {
            DB::transaction(function () use ($file, $recording) {
                $original = basename(str_replace('\\', '/', (string) $file->getClientOriginalName()));
                if ($original === '' || $original === '.' || $original === '..') {
                    $original = 'certificate-of-deletion.pdf';
                }
                $storedName = $recording->id.'_cert_'.Str::uuid()->toString().'_'.$original;
                $path = $file->storeAs('archive-recordings/certificates', $storedName);
                if (! is_string($path) || $path === '') {
                    throw new \RuntimeException('The certificate file could not be stored.');
                }

                $recording->forceFill([
                    'status' => 'Deleted',
                    'certificate_path' => $path,
                    'certificate_name' => $original,
                ])->save();
            });
        } catch (\Throwable $exception) {
            return back()->with('error', PublicError::failed('Certificate upload', $exception));
        }

        AuditLogger::log('Deleted', 'Archive Recordings', 'Archive Recordings record marked deleted with certificate', $recording->id, $request);

        return back()->with('success', 'Recording marked as deleted.');
    }

    public function bulkDestroy(): RedirectResponse
    {
        return back()->with('error', 'Recordings cannot be permanently deleted. Attach a Certificate of Deletion for each recording.');
    }

    public function export(Request $request, XlsxService $xlsx): BinaryFileResponse|RedirectResponse
    {
        $query = ArchiveRecording::query()->with('campaign')->orderBy('called_at')->orderBy('id');
        $this->constrainExport($query, $request);
        $headers = array_values(app(ArchiveRecordingImportService::class)->fields());
        $rows = $query->get()->map(function (ArchiveRecording $record) {
            return [
                $record->campaign?->name,
                $record->file_name,
                $record->calledAtDisplay() === '—' ? '' : $record->calledAtDisplay(),
                $record->caller_number,
                $record->agent_number,
                $record->durationDisplay() === '—' ? '' : $record->durationDisplay(),
                $record->location,
                $record->storage_path,
            ];
        });

        try {
            $path = $xlsx->export($headers, $rows, 'archive-recordings.xlsx');
        } catch (\Throwable $exception) {
            return back()->with('error', PublicError::failed('Export', $exception));
        }

        AuditLogger::log('Exported', 'Archive Recordings', 'Exported Archive Recordings records', null, $request);

        return response()->download($path, 'archive-recordings.xlsx')->deleteFileAfterSend(true);
    }

    public function importTemplate(XlsxService $xlsx, ArchiveRecordingImportService $import): BinaryFileResponse|RedirectResponse
    {
        try {
            $path = $xlsx->export($import->templateHeaders(), $import->templateRows(), 'archive-recordings-template.xlsx');
        } catch (\Throwable $exception) {
            return back()->with('error', PublicError::failed('Template download', $exception));
        }

        return response()->download($path, 'archive-recordings-template.xlsx')->deleteFileAfterSend(true);
    }

    public function importPreview(Request $request, ArchiveRecordingImportService $import, XlsxService $xlsx)
    {
        $uploaded = $request->file('file');
        if ($uploaded instanceof UploadedFile && ! $uploaded->isValid()) {
            return response()->json([
                'ok' => false,
                'message' => 'The file failed to upload. '.$uploaded->getErrorMessage(),
            ], 422);
        }

        $request->validate(['file' => ['required', 'file', 'max:5120']]);
        $file = $request->file('file');
        $extension = strtolower((string) $file?->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'xls'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Only .xlsx and .xls files are allowed.',
            ], 422);
        }

        try {
            $preview = $import->preview($file, $xlsx);
        } catch (\Throwable $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception instanceof \RuntimeException
                    ? $exception->getMessage()
                    : PublicError::failed('Preview', $exception),
            ], 422);
        }

        $token = $import->storePreview($preview);

        return response()->json([
            'ok' => true,
            'token' => $token,
            'valid' => $preview['valid'],
            'summary' => $preview['summary'],
            'rows' => $preview['rows'],
            'headers' => $preview['headers'],
        ]);
    }

    public function importConfirm(Request $request, ArchiveRecordingImportService $import)
    {
        $request->validate(['token' => ['required', 'string']]);
        $stored = $import->previewFromSession((string) $request->input('token'));
        if (! is_array($stored) || ! ($stored['valid'] ?? false) || ($stored['payload'] ?? []) === []) {
            return response()->json([
                'ok' => false,
                'message' => 'There are errors in some rows. Please review the details below and fix them in your file.',
            ], 422);
        }

        try {
            $count = $import->commit($stored['payload']);
        } catch (\Throwable $exception) {
            return response()->json([
                'ok' => false,
                'message' => PublicError::failed('Import', $exception),
            ], 422);
        }

        $import->forget();
        AuditLogger::log('Imported', 'Archive Recordings', 'Imported '.$count.' Archive Recordings records', null, $request);

        return response()->json([
            'ok' => true,
            'records' => $count,
        ]);
    }

    public function importAudio(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'campaign' => ['required_without:campaign_id', 'nullable', 'string', 'max:255'],
            'campaign_id' => ['required_without:campaign', 'nullable', 'integer'],
            'year' => ['required', 'integer', Rule::in(self::ARCHIVE_YEARS)],
            'month' => ['required', 'integer', 'between:1,12'],
            'location' => ['nullable', 'string', 'max:255'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'max:102400'],
        ], [
            'campaign.required_without' => 'Please select a campaign.',
            'campaign_id.required_without' => 'Please select a campaign.',
            'year.required' => 'Please select a year.',
            'year.in' => 'Please select a year from 2026 to 2018.',
            'month.required' => 'Please select a month.',
            'month.between' => 'Please select a valid month.',
            'files.required' => 'Select at least one recording file.',
            'files.min' => 'Select at least one recording file.',
            'files.*.max' => 'Each recording file must be 100 MB or smaller.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $files = $request->file('files', []);
        if (! is_array($files)) {
            $files = [$files];
        }

        $rejected = [];
        $validFiles = [];
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $name = $file->getClientOriginalName() ?: 'audio file';
            if (! $file->isValid()) {
                $rejected[] = $name.' could not be uploaded. Each recording file must be 100 MB or smaller.';

                continue;
            }
            if ($file->getSize() > self::MAX_AUDIO_BYTES) {
                $rejected[] = $name.' exceeds the 100 MB limit.';

                continue;
            }
            $extension = strtolower((string) $file->getClientOriginalExtension());
            if (! in_array($extension, self::AUDIO_EXTENSIONS, true)) {
                $rejected[] = $name.' is not a supported audio format. Use WAV, MP3, or another allowed audio format.';

                continue;
            }
            $validFiles[] = $file;
        }

        if ($rejected !== []) {
            return response()->json([
                'ok' => false,
                'message' => $rejected[0],
            ], 422);
        }

        if ($validFiles === []) {
            return response()->json([
                'ok' => false,
                'message' => 'Select at least one recording file.',
            ], 422);
        }

        $campaign = ChannelAllocationCampaign::fromFormValue(
            $request->input('campaign'),
            $request->input('campaign_id')
        );
        if ($campaign === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Please select a campaign.',
            ], 422);
        }

        $campaignId = (int) $campaign->id;
        $year = (int) $request->input('year');
        $month = (int) $request->input('month');
        $location = trim((string) $request->input('location', ''));
        $imported = 0;

        try {
            DB::transaction(function () use ($validFiles, $campaignId, $year, $month, $location, &$imported) {
                foreach ($validFiles as $file) {
                    $original = basename(str_replace('\\', '/', (string) $file->getClientOriginalName()));
                    if ($original === '' || $original === '.' || $original === '..') {
                        $original = 'recording.wav';
                    }
                    $storedName = $campaignId.'_'.$year.str_pad((string) $month, 2, '0', STR_PAD_LEFT).'_'.Str::uuid()->toString().'_'.$original;
                    $path = $file->storeAs('archive-recordings', $storedName);
                    if (! is_string($path) || $path === '') {
                        throw new \RuntimeException('The recording file could not be stored.');
                    }

                    $recording = ArchiveRecording::create([
                        'campaign_id' => $campaignId,
                        'file_name' => $original,
                        'called_at' => $this->calledAtForImport($original, $year, $month),
                        'server' => '',
                        'storage_path' => $path,
                        'status' => 'Available',
                        'location' => $location !== '' ? $location : null,
                    ]);
                    $duration = AudioDuration::formatFromRecording($recording);
                    if ($duration !== null) {
                        $recording->forceFill(['duration' => $duration])->save();
                    }
                    $imported++;
                }
            });
        } catch (\Throwable $exception) {
            return response()->json([
                'ok' => false,
                'message' => PublicError::failed('Import', $exception),
            ], 422);
        }

        AuditLogger::log('Created', 'Archive Recordings', 'Added '.$imported.' archive record'.($imported === 1 ? '' : 's'), null, $request);

        $label = $imported === 1 ? '1 record' : $imported.' records';

        return response()->json([
            'ok' => true,
            'records' => $imported,
            'message' => 'Added '.$label.'.',
            'redirect' => route('archive-recordings', [
                'campaign' => $campaignId,
                'year' => $year,
                'month' => $month,
            ]),
        ]);
    }

    public function importErrors(Request $request, ArchiveRecordingImportService $import, XlsxService $xlsx): BinaryFileResponse|RedirectResponse
    {
        $stored = $import->previewFromSession((string) $request->query('token'));
        if (! is_array($stored)) {
            return back()->with('error', 'No import preview is available. Upload and validate the file again.');
        }

        $headers = array_merge(['Row #'], $stored['headers'] ?? $import->templateHeaders(), ['Status', 'Error Reason']);
        $rows = [];
        foreach ($stored['rows'] as $row) {
            if ($row['valid']) {
                continue;
            }
            $line = [$row['row']];
            foreach (array_keys($import->fields()) as $field) {
                $line[] = $row[$field] ?? '';
            }
            $line[] = $row['status'];
            $line[] = $row['error'];
            $rows[] = $line;
        }

        try {
            $path = $xlsx->export($headers, $rows, 'archive-recordings-import-errors.xlsx');
        } catch (\Throwable $exception) {
            return back()->with('error', PublicError::failed('Error report download', $exception));
        }

        return response()->download($path, 'archive-recordings-import-errors.xlsx')->deleteFileAfterSend(true);
    }

    /**
     * @return list<array{month: int, total: int}>
     */
    private function availableMonths(int $campaignId, int $year): array
    {
        $counts = ArchiveRecording::query()
            ->where('campaign_id', $campaignId)
            ->whereNotNull('called_at')
            ->whereRaw($this->yearExpression().' = ?', [$year])
            ->selectRaw($this->monthExpression().' as month, COUNT(*) as total')
            ->groupByRaw($this->monthExpression())
            ->pluck('total', 'month');

        $months = [];
        foreach (array_keys(self::MONTH_NAMES) as $monthNumber) {
            $months[] = [
                'month' => $monthNumber,
                'total' => (int) ($counts[$monthNumber] ?? $counts[(string) $monthNumber] ?? 0),
            ];
        }

        return $months;
    }

    private function scopedQuery(int $campaignId, int $year, int $month): Builder
    {
        return ArchiveRecording::query()
            ->where('campaign_id', $campaignId)
            ->whereNotNull('called_at')
            ->whereRaw($this->yearExpression().' = ?', [$year])
            ->whereRaw($this->monthExpression().' = ?', [$month]);
    }

    private function applyRecordingFilters(Builder $query, string $search, string $from, string $to, string $caller, string $agent): Builder
    {
        if ($search !== '') {
            $query->where(function (Builder $recordings) use ($search) {
                $recordings->where('file_name', 'like', "%{$search}%")
                    ->orWhere('caller_number', 'like', "%{$search}%")
                    ->orWhere('agent_number', 'like', "%{$search}%")
                    ->orWhere('duration', 'like', "%{$search}%")
                    ->orWhere('called_at', 'like', "%{$search}%");
            });
        }
        if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) === 1) {
            $query->whereDate('called_at', '>=', $from);
        }
        if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) === 1) {
            $query->whereDate('called_at', '<=', $to);
        }
        if ($caller !== '') {
            $query->where('caller_number', $caller);
        }
        if ($agent !== '') {
            $query->where('agent_number', $agent);
        }

        return $query;
    }

    private function constrainExport(Builder $query, Request $request): void
    {
        $campaignId = (int) $request->query('campaign', 0);
        $year = (int) $request->query('year', 0);
        $month = (int) $request->query('month', 0);
        if ($campaignId > 0) {
            $query->where('campaign_id', $campaignId);
        }
        if ($year > 0) {
            $query->whereRaw($this->yearExpression().' = ?', [$year]);
        }
        if ($month > 0) {
            $query->whereRaw($this->monthExpression().' = ?', [$month]);
        }
        $this->applyRecordingFilters(
            $query,
            trim((string) $request->query('search')),
            trim((string) $request->query('from')),
            trim((string) $request->query('to')),
            trim((string) $request->query('caller')),
            trim((string) $request->query('agent')),
        );
    }

    private function yearExpression(): string
    {
        return $this->isSqlite()
            ? "CAST(strftime('%Y', called_at) AS INTEGER)"
            : 'YEAR(called_at)';
    }

    private function monthExpression(): string
    {
        return $this->isSqlite()
            ? "CAST(strftime('%m', called_at) AS INTEGER)"
            : 'MONTH(called_at)';
    }

    private function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    /**
     * @param  iterable<int, ArchiveRecording>  $records
     */
    private function hydrateRecordingDurations(iterable $records): void
    {
        foreach ($records as $record) {
            if (trim((string) $record->duration) !== '') {
                continue;
            }
            $duration = AudioDuration::formatFromRecording($record);
            if ($duration === null) {
                continue;
            }
            $record->duration = $duration;
            $record->saveQuietly();
        }
    }

    private function resolveFile(ArchiveRecording $recording): ?string
    {
        return ArchiveStorage::resolveRecording($recording);
    }

    private function resolveCertificate(ArchiveRecording $recording): ?string
    {
        return ArchiveStorage::resolveCertificate($recording);
    }

    private function deleteStoredFile(ArchiveRecording $recording): void
    {
        $path = $this->resolveFile($recording);
        if ($path !== null) {
            @unlink($path);
        }
    }

    private function downloadName(ArchiveRecording $recording): string
    {
        $name = trim((string) $recording->file_name);

        return $name !== '' ? $name : 'recording.wav';
    }

    private function calledAtForImport(string $fileName, int $year, int $month): string
    {
        $day = 1;
        $hour = 0;
        $minute = 0;
        $second = 0;
        if (preg_match('/(\d{8})_(\d{6})/', $fileName, $match) === 1) {
            $parsedYear = (int) substr($match[1], 0, 4);
            $parsedMonth = (int) substr($match[1], 4, 2);
            $parsedDay = (int) substr($match[1], 6, 2);
            if ($parsedYear === $year && $parsedMonth === $month && checkdate($parsedMonth, $parsedDay, $parsedYear)) {
                $day = $parsedDay;
            }
            $hour = (int) substr($match[2], 0, 2);
            $minute = (int) substr($match[2], 2, 2);
            $second = (int) substr($match[2], 4, 2);
            if ($hour > 23 || $minute > 59 || $second > 59) {
                $hour = 0;
                $minute = 0;
                $second = 0;
            }
        }

        $maxDay = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
        if ($day < 1 || $day > $maxDay) {
            $day = 1;
        }

        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
    }
}
