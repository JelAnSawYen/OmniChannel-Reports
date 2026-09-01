<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\InventoryImportService;
use App\Services\XlsxService;
use App\Support\PublicError;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

trait HandlesInventoryImport
{
    abstract protected function inventoryImportConfig(Request $request): array;

    public function importTemplate(Request $request, XlsxService $xlsx, InventoryImportService $import): BinaryFileResponse|\Illuminate\Http\RedirectResponse
    {
        $config = $this->inventoryImportConfig($request);
        try {
            $path = $xlsx->export($import->templateHeaders($config), $import->templateRows($config), $config['filename'].'-template.xlsx');
        } catch (\Throwable $exception) {
            return back()->with('error', PublicError::failed('Template download', $exception));
        }

        return response()->download($path, $config['filename'].'-template.xlsx')->deleteFileAfterSend(true);
    }

    public function importPreview(Request $request, InventoryImportService $import, XlsxService $xlsx)
    {
        $uploaded = $request->file('file');
        if ($uploaded instanceof UploadedFile && ! $uploaded->isValid()) {
            return response()->json([
                'ok' => false,
                'message' => 'The file failed to upload. '.$uploaded->getErrorMessage(),
            ], 422);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
        ]);

        $file = $request->file('file');
        $extension = strtolower((string) $file?->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'xls'], true)) {
            return response()->json([
                'ok' => false,
                'message' => 'Only .xlsx and .xls files are allowed.',
            ], 422);
        }

        $config = $this->inventoryImportConfig($request);

        try {
            $preview = $import->preview($file, $xlsx, $config);
        } catch (\Throwable $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception instanceof \RuntimeException
                    ? $exception->getMessage()
                    : PublicError::failed('Preview', $exception),
            ], 422);
        }

        $token = $import->storePreview($config['key'], $preview);

        return response()->json([
            'ok' => true,
            'token' => $token,
            'valid' => $preview['valid'],
            'summary' => $preview['summary'],
            'rows' => $preview['rows'],
            'headers' => $preview['headers'],
        ]);
    }

    public function importConfirm(Request $request, InventoryImportService $import)
    {
        $request->validate(['token' => ['required', 'string']]);
        $config = $this->inventoryImportConfig($request);
        $stored = $import->previewFromSession($config['key'], (string) $request->input('token'));
        if (! is_array($stored) || ! ($stored['valid'] ?? false) || ($stored['payload'] ?? []) === []) {
            return response()->json([
                'ok' => false,
                'message' => 'There are errors in some rows. Please review the details below and fix them in your file.',
            ], 422);
        }

        try {
            $count = $import->commit($stored['payload'], $config);
        } catch (\Throwable $exception) {
            return response()->json([
                'ok' => false,
                'message' => PublicError::failed('Import', $exception),
            ], 422);
        }

        $import->forget($config['key']);
        AuditLogger::log('Imported', $config['title'], 'Imported '.$count.' '.$config['title'].' records', null, $request);

        return response()->json([
            'ok' => true,
            'records' => $count,
        ]);
    }

    public function importErrors(Request $request, InventoryImportService $import, XlsxService $xlsx): BinaryFileResponse|\Illuminate\Http\RedirectResponse
    {
        $config = $this->inventoryImportConfig($request);
        $stored = $import->previewFromSession($config['key'], (string) $request->query('token'));
        if (! is_array($stored)) {
            return back()->with('error', 'No import preview is available. Upload and validate the file again.');
        }

        $headers = array_merge(['Row #'], $stored['headers'] ?? array_values($config['fields']), ['Status', 'Error Reason']);
        $rows = [];
        foreach ($stored['rows'] as $row) {
            if ($row['valid']) {
                continue;
            }
            $line = [$row['row']];
            foreach (array_keys($config['fields']) as $field) {
                $line[] = $row[$field] ?? '';
            }
            $line[] = $row['status'];
            $line[] = $row['error'];
            $rows[] = $line;
        }

        try {
            $path = $xlsx->export($headers, $rows, $config['filename'].'-import-errors.xlsx');
        } catch (\Throwable $exception) {
            return back()->with('error', PublicError::failed('Error report download', $exception));
        }

        return response()->download($path, $config['filename'].'-import-errors.xlsx')->deleteFileAfterSend(true);
    }
}
