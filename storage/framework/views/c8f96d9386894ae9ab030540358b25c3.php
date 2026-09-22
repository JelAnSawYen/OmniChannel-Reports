<?php
    $previewFields = $previewFields ?? [];
    $previewUrl = $previewUrl ?? '';
    $confirmUrl = $confirmUrl ?? '';
    $errorsUrl = $errorsUrl ?? '';
?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const transferButton = document.getElementById('transferButton');
    const transferMenu = document.getElementById('transferMenu');
    const closeTransferMenu = () => {
        transferMenu?.classList.remove('open');
        transferButton?.setAttribute('aria-expanded', 'false');
    };
    transferButton?.addEventListener('click', (event) => {
        event.stopPropagation();
        const willOpen = !transferMenu?.classList.contains('open');
        closeTransferMenu();
        if (!willOpen || !transferMenu) return;
        transferMenu.classList.add('open');
        transferButton.setAttribute('aria-expanded', 'true');
    });
    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element) || !event.target.closest('.transfer')) {
            closeTransferMenu();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeTransferMenu();
    });

    const importModal = document.getElementById('importModal');
    const importPreviewModal = document.getElementById('importPreviewModal');
    const importSuccessModal = document.getElementById('importSuccessModal');
    const importFileInput = document.getElementById('importFileInput');
    const importPreviewButton = document.getElementById('importPreviewButton');
    const importConfirmButton = document.getElementById('importConfirmButton');
    const importFileStatus = document.getElementById('importFileStatus');
    const importUploadError = document.getElementById('importUploadError');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const previewUrl = <?php echo json_encode($previewUrl, 15, 512) ?>;
    const confirmUrl = <?php echo json_encode($confirmUrl, 15, 512) ?>;
    const errorsUrl = <?php echo json_encode($errorsUrl, 15, 512) ?>;
    const previewFields = <?php echo json_encode($previewFields, 15, 512) ?>;
    let importToken = '';

    function resetImportUpload() {
        importToken = '';
        if (importFileInput) importFileInput.value = '';
        if (importFileStatus) {
            importFileStatus.textContent = 'No file chosen';
            importFileStatus.classList.remove('ready');
        }
        if (importPreviewButton) importPreviewButton.disabled = true;
        if (importUploadError) {
            importUploadError.hidden = true;
            importUploadError.textContent = '';
        }
    }

    function showImportError(message) {
        if (!importUploadError) return;
        importUploadError.hidden = !message;
        importUploadError.textContent = message || '';
    }

    async function readJsonResponse(response) {
        const text = await response.text();
        const start = text.indexOf('{');
        if (start < 0) {
            throw new Error('invalid-json');
        }
        return JSON.parse(text.slice(start));
    }

    function importFailureMessage(data, fallback) {
        return data?.message || data?.errors?.file?.[0] || fallback;
    }

    function escapeImport(value) {
        const replacements = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(value ?? '').replace(/[&<>"']/g, (char) => replacements[char] || char);
    }

    document.getElementById('importDataButton')?.addEventListener('click', () => {
        closeTransferMenu();
        if (!importModal) return;
        resetImportUpload();
        importPreviewModal?.classList.remove('visible');
        importSuccessModal?.classList.remove('visible');
        importModal.classList.add('visible');
    });

    importFileInput?.addEventListener('change', () => {
        const file = importFileInput.files && importFileInput.files[0];
        showImportError('');
        if (!file) {
            resetImportUpload();
            return;
        }
        const name = file.name || '';
        const ok = /\.(xlsx|xls)$/i.test(name);
        if (importFileStatus) {
            importFileStatus.textContent = name;
            importFileStatus.classList.toggle('ready', ok);
        }
        importPreviewButton.disabled = !ok;
        if (!ok) showImportError('Only .xlsx, .xls files are allowed.');
    });

    importPreviewButton?.addEventListener('click', async () => {
        const file = importFileInput?.files && importFileInput.files[0];
        if (!file || !previewUrl) return;
        showImportError('');
        importPreviewButton.disabled = true;
        const body = new FormData();
        body.append('file', file);
        try {
            const response = await fetch(previewUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body
            });
            const data = await readJsonResponse(response);
            if (!response.ok || !data.ok) {
                showImportError(importFailureMessage(data, 'Preview failed. Please try again.'));
                importPreviewButton.disabled = false;
                return;
            }
            importToken = data.token || '';
            renderImportPreview(data);
            importModal?.classList.remove('visible');
            importPreviewModal?.classList.add('visible');
        } catch (error) {
            showImportError('Preview failed. Please try again.');
        }
        importPreviewButton.disabled = !(importFileInput?.files && importFileInput.files[0]);
    });

    function renderImportPreview(data) {
        const summary = data.summary || {};
        const stats = document.getElementById('importStats');
        if (stats) {
            stats.innerHTML = [
                ['Total Rows', summary.total ?? 0, ''],
                ['Valid Rows', summary.valid ?? 0, 'ok'],
                ['Error Rows', summary.errors ?? 0, (summary.errors ?? 0) > 0 ? 'bad' : 'ok']
            ].map(([label, value, tone]) => `<div class="import-stat ${tone}"><span>${label}</span><strong>${value}</strong></div>`).join('');
        }
        const hasErrors = !data.valid;
        document.getElementById('importErrorBanner')?.toggleAttribute('hidden', !hasErrors);
        document.getElementById('importSuccessBanner')?.toggleAttribute('hidden', hasErrors);
        const report = document.getElementById('importErrorReport');
        if (report) {
            report.href = errorsUrl + (importToken ? ('?token=' + encodeURIComponent(importToken)) : '');
            report.style.visibility = hasErrors ? 'visible' : 'hidden';
        }
        if (importConfirmButton) {
            importConfirmButton.disabled = hasErrors;
            importConfirmButton.classList.toggle('success', !hasErrors);
        }
        const tbody = document.getElementById('importPreviewRows');
        if (!tbody) return;
        tbody.innerHTML = (data.rows || []).map((row) => {
            const err = row.valid ? '' : 'import-row-error';
            const cells = previewFields.map((field) => `<td>${escapeImport(row[field])}</td>`).join('');
            return `<tr class="${err}">
                <td>${row.row}</td>
                ${cells}
                <td class="${row.valid ? 'import-status-ok' : 'import-status-bad'}">${escapeImport(row.status)}</td>
                <td>${escapeImport(row.error)}</td>
            </tr>`;
        }).join('');
    }

    document.getElementById('importBackButton')?.addEventListener('click', () => {
        importPreviewModal?.classList.remove('visible');
        importModal?.classList.add('visible');
        if (importConfirmButton) importConfirmButton.disabled = true;
    });

    importConfirmButton?.addEventListener('click', async () => {
        if (!importToken || importConfirmButton.disabled || !confirmUrl) return;
        importConfirmButton.disabled = true;
        try {
            const response = await fetch(confirmUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ token: importToken })
            });
            const data = await readJsonResponse(response);
            if (!response.ok || !data.ok) {
                if (Array.isArray(data.rows)) {
                    renderImportPreview(data);
                } else {
                    importConfirmButton.disabled = false;
                }
                const banner = document.getElementById('importErrorBanner');
                if (banner) {
                    banner.hidden = false;
                    banner.textContent = importFailureMessage(data, 'Import failed. Please try again.');
                }
                return;
            }
            importPreviewModal?.classList.remove('visible');
            const message = document.getElementById('importSuccessMessage');
            if (message) {
                message.textContent = 'Import completed successfully! ' + (data.records ?? 0) + ' records imported.';
            }
            importSuccessModal?.classList.add('visible');
        } catch (error) {
            importConfirmButton.disabled = false;
        }
    });

    function finishImportSuccess() {
        window.location.reload();
    }
    document.getElementById('importSuccessClose')?.addEventListener('click', finishImportSuccess);
    document.getElementById('importSuccessDismiss')?.addEventListener('click', finishImportSuccess);
});
</script>
<?php /**PATH C:\xampp\htdocs\OmniChannel\OmniChannel_Inventory_Production_Updated\resources\views/partials/inventory-import-script.blade.php ENDPATH**/ ?>