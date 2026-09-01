@php
    $canExport = $canExport ?? false;
    $canImport = $canImport ?? false;
    $exportUrl = $exportUrl ?? '#';
    $templateUrl = $templateUrl ?? '';
    $previewUrl = $previewUrl ?? '';
    $confirmUrl = $confirmUrl ?? '';
    $errorsUrl = $errorsUrl ?? '';
    $previewHeaders = $previewHeaders ?? [];
    $entityTitle = $entityTitle ?? 'records';
@endphp
@if($canExport)
<div class="transfer">
    <button class="btn" type="button" id="transferButton" aria-haspopup="true" aria-expanded="false" aria-controls="transferMenu">
        <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4v12"></path><path d="m7 11 5 5 5-5"></path><path d="M5 20h14"></path></svg>
        Data Transfer
    </button>
    <div class="transfer-menu" id="transferMenu" role="menu">
        @if($canImport)
        <button type="button" role="menuitem" id="importDataButton">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20V8"></path><path d="m7 13 5-5 5 5"></path><path d="M5 4h14"></path></svg>
            Import Data
        </button>
        @endif
        <a role="menuitem" id="exportDataButton" href="{{ $exportUrl }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4v12"></path><path d="m7 11 5 5 5-5"></path><path d="M5 20h14"></path></svg>
            Export Data
        </a>
    </div>
</div>
@endif

@if($canImport)
<div class="modal-backdrop" id="importModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Import Data</h3>
            <button type="button" class="close-btn" data-close="importModal">×</button>
        </div>
        <div class="modal-body">
            <p class="import-lead">Upload the official Excel template to import {{ $entityTitle }} in bulk. Each row is one record.</p>
            <div class="import-section">
                <h4>1. Download Template</h4>
                <a class="btn primary" href="{{ $templateUrl }}">
                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4v12"></path><path d="m7 11 5 5 5-5"></path><path d="M5 20h14"></path></svg>
                    Download Excel Template
                </a>
            </div>
            <div class="import-section">
                <h4>2. Upload File</h4>
                <label class="import-file-label" for="importFileInput">Choose Excel File</label>
                <div class="import-file-row">
                    <input class="form-control" type="file" id="importFileInput" accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel">
                    <span class="import-file-status" id="importFileStatus">No file chosen</span>
                </div>
                <p class="muted import-file-hint">Only .xlsx, .xls files are allowed.</p>
                <p class="import-upload-error" id="importUploadError" hidden></p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn secondary" data-close="importModal">Cancel</button>
            <button type="button" class="btn primary" id="importPreviewButton" disabled>Preview &amp; Validate</button>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="importPreviewModal">
    <div class="modal import-wide">
        <div class="modal-header">
            <h3>Import Data - Preview</h3>
            <button type="button" class="close-btn" data-close="importPreviewModal">×</button>
        </div>
        <div class="modal-body">
            <div class="import-stats" id="importStats"></div>
            <div class="import-banner error" id="importErrorBanner" hidden>There are errors in some rows. Please review the details below and fix them in your file.</div>
            <div class="import-banner success" id="importSuccessBanner" hidden>All rows are valid and ready to import.</div>
            <div class="table-wrap import-preview-wrap">
                <table class="import-preview-table" aria-label="Import preview">
                    <thead>
                        <tr>
                            <th>Row #</th>
                            @foreach($previewHeaders as $header)
                                <th>{{ $header }}</th>
                            @endforeach
                            <th>Status</th>
                            <th>Error Reason</th>
                        </tr>
                    </thead>
                    <tbody id="importPreviewRows"></tbody>
                </table>
            </div>
        </div>
        <div class="modal-footer import-preview-footer">
            <a class="btn secondary" id="importErrorReport" href="#">Download Error Report</a>
            <div class="import-preview-actions">
                <button type="button" class="btn secondary" id="importBackButton">Back to Upload</button>
                <button type="button" class="btn success" id="importConfirmButton" disabled>Confirm Import</button>
            </div>
        </div>
    </div>
</div>

<div class="modal-backdrop" id="importSuccessModal">
    <div class="modal small">
        <div class="modal-header">
            <h3>Import Data</h3>
            <button type="button" class="close-btn" data-close="importSuccessModal" id="importSuccessDismiss">×</button>
        </div>
        <div class="modal-body import-success-body">
            <div class="import-success-icon" aria-hidden="true">✓</div>
            <p class="import-success-message" id="importSuccessMessage">Import completed successfully!</p>
            <div class="import-banner info">The new data will now appear in the list.</div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn primary" id="importSuccessClose">Close</button>
        </div>
    </div>
</div>
@endif
