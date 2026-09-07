<?php
    $isSim = \App\Support\OperationCatalog::isSim($module);
    $transferColumns = $isSim ? \App\Support\OperationCatalog::simTransferColumns() : $config['columns'];
    $numericColumns = $isSim ? [] : ['monthly_cost', 'retention_days', 'port_number', 'number', 'asset_code'];
    $emptyColspan = count($config['columns']) + ($isSim ? 1 : 3);
?>
<?php $__env->startSection('content'); ?>
<div class="page-head">
    <div>
        <h1 class="page-title"><?php echo e($config['title']); ?></h1>
        <p class="page-subtitle"><?php echo e($config['description']); ?></p>
    </div>
    <div class="toolbar">
        <form class="search-box media-search-form" id="operationSearchForm" method="GET" action="<?php echo e(route($module)); ?>">
            <span class="search-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
            </span>
            <input id="operationSearchInput" name="search" value="<?php echo e($search); ?>" placeholder="Search <?php echo e($config['title']); ?>" aria-label="Search <?php echo e($config['title']); ?>" autocomplete="off">
            <button type="button" class="search-clear" data-clear-search aria-label="Clear search" title="Clear search">×</button>
            <?php if(request('status')): ?><input type="hidden" name="status" value="<?php echo e(request('status')); ?>"><?php endif; ?>
            <?php if(request('per_page')): ?><input type="hidden" name="per_page" value="<?php echo e(request('per_page')); ?>"><?php endif; ?>
        </form>
        <?php if(count($statusOptions)): ?>
            <form method="GET" action="<?php echo e(route($module)); ?>">
                <?php if($search !== ''): ?><input type="hidden" name="search" value="<?php echo e($search); ?>"><?php endif; ?>
                <?php if(request('per_page')): ?><input type="hidden" name="per_page" value="<?php echo e(request('per_page')); ?>"><?php endif; ?>
                <select class="select" name="status" onchange="this.form.submit()" aria-label="Filter by status">
                    <option value="">All Statuses</option>
                    <?php $__currentLoopData = $statusOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $opt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($opt); ?>" <?php echo e(request('status')===$opt?'selected':''); ?>><?php echo e($opt); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </form>
        <?php endif; ?>
        <button class="btn primary" type="submit" form="operationSearchForm">Search</button>
        <?php if($search !== '' || request('status')): ?>
            <a class="btn secondary" href="<?php echo e(route($module)); ?>">Reset</a>
        <?php endif; ?>
        <?php if(auth()->user()->hasPermission('media.export')): ?>
        <?php echo $__env->make('partials.data-transfer', [
            'canExport' => true,
            'canImport' => auth()->user()->hasPermission('media.create'),
            'exportUrl' => route($module.'.export', request()->query()),
            'templateUrl' => auth()->user()->hasPermission('media.create') ? route($module.'.import.template') : '',
            'previewUrl' => auth()->user()->hasPermission('media.create') ? route($module.'.import.preview') : '',
            'confirmUrl' => auth()->user()->hasPermission('media.create') ? route($module.'.import.confirm') : '',
            'errorsUrl' => auth()->user()->hasPermission('media.create') ? route($module.'.import.errors') : '',
            'previewHeaders' => array_values($transferColumns),
            'entityTitle' => $config['title'],
        ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?>
        <?php if(auth()->user()->hasPermission('media.create')): ?>
            <button class="plus-btn" type="button" id="operationAddButton" aria-label="Add <?php echo e($config['title']); ?>" title="Add <?php echo e($config['title']); ?>">+</button>
        <?php endif; ?>
    </div>
</div>

<div class="table-card table-wrap">
<table class="<?php echo \Illuminate\Support\Arr::toCssClasses(['sim-table' => $isSim]); ?>" aria-label="<?php echo e($config['title']); ?>">
<thead>
<tr>
    <?php if (! ($isSim)): ?>
        <th>Id</th>
    <?php endif; ?>
    <?php $__currentLoopData = $config['columns']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <th class="<?php echo \Illuminate\Support\Arr::toCssClasses(['num-col' => in_array($field, $numericColumns, true)]); ?>"><?php echo e($label); ?></th>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php if (! ($isSim)): ?>
        <th>Last Updated</th>
    <?php endif; ?>
    <th class="actions-column">Actions</th>
</tr>
</thead>
<tbody>
<?php $__empty_1 = true; $__currentLoopData = $records; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $record): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<tr>
    <?php if (! ($isSim)): ?>
        <td><?php echo e(($records->firstItem() ?? 1) + $loop->index); ?></td>
    <?php endif; ?>
    <?php $__currentLoopData = $config['columns']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php $isNumericCol = in_array($field, $numericColumns, true); ?>
        <td class="<?php echo \Illuminate\Support\Arr::toCssClasses(['num-col' => $isNumericCol]); ?>">
            <?php if($field==='monthly_cost'): ?>
                <span class="num-align" data-label="<?php echo e($label); ?>">₱<?php echo e(number_format((float) $record->$field, 2)); ?></span>
            <?php elseif($field==='status'): ?>
                <span class="status-pill <?php echo e(in_array($record->$field, ['Active', 'Available']) ? 'online' : (in_array($record->$field, ['In Use', 'Expiring']) ? 'unknown' : 'offline')); ?>"><?php echo e($record->$field); ?></span>
            <?php elseif(in_array($field, ['contract_start', 'contract_end'], true)): ?>
                <?php
                    $dateValue = $record->$field;
                    if ($dateValue instanceof \DateTimeInterface) {
                        $dateValue = $dateValue->format('Y-m-d');
                    }
                    $dateValue = $isSim ? \App\Support\PdcEndorseDate::display($dateValue) : $dateValue;
                ?>
                <?php echo e($dateValue); ?>

            <?php elseif($isNumericCol): ?>
                <span class="num-align" data-label="<?php echo e($label); ?>"><?php echo e($record->$field); ?></span>
            <?php else: ?>
                <?php echo e($record->$field); ?>

            <?php endif; ?>
        </td>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php
        $recordId = (int) $record->getKey();
        $editValues = [];
        foreach ($config['fields'] as $field) {
            $value = $record->{$field};
            if ($value instanceof \DateTimeInterface) {
                $value = $isSim
                    ? \App\Support\PdcEndorseDate::display($value->format('Y-m-d'))
                    : $value->format('Y-m-d');
            }
            $editValues[$field] = $value;
        }
    ?>
    <?php if (! ($isSim)): ?>
        <td><?php echo e($record->updated_at?->format('M d, Y h:i A') ?? '—'); ?></td>
    <?php endif; ?>
    <td class="actions-column">
        <div class="row-actions">
            <?php if(auth()->user()->hasPermission('media.edit')): ?>
                <button class="action-btn edit" type="button" data-operation-edit data-id="<?php echo e($recordId); ?>" data-values='<?php echo json_encode($editValues, 15, 512) ?>' title="Edit <?php echo e($config['title']); ?>" aria-label="Edit <?php echo e($config['title']); ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                </button>
            <?php endif; ?>
            <?php if(auth()->user()->hasPermission('media.delete')): ?>
                <form method="POST" action="<?php echo e(route($module.'.destroy', ['id' => $recordId])); ?>" data-confirm="Delete this record?" data-confirm-title="Delete Record" data-confirm-ok="Delete">
                    <?php echo csrf_field(); ?>
                    <?php echo method_field('DELETE'); ?>
                    <button class="action-btn delete" type="submit" title="Delete <?php echo e($config['title']); ?>" aria-label="Delete <?php echo e($config['title']); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </td>
</tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
<tr><td colspan="<?php echo e($emptyColspan); ?>"><div class="empty-state">No <?php echo e($config['title']); ?> records found.</div></td></tr>
<?php endif; ?>
</tbody>
</table>
<div class="table-footer">
    <span>Showing <?php echo e($records->firstItem() ?? 0); ?> to <?php echo e($records->lastItem() ?? 0); ?> of <?php echo e($records->total()); ?> entries</span>
    <div class="footer-right">
        <span>Records per page:</span>
        <select class="per-page-select" onchange="location.href=this.value" aria-label="Records per page">
            <?php $__currentLoopData = [5,10,25,50]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $size): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e(request()->fullUrlWithQuery(['per_page'=>$size,'page'=>1])); ?>" <?php echo e($perPage===$size?'selected':''); ?>><?php echo e($size); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <div class="pager">
            <?php for($page=1;$page<=$records->lastPage();$page++): ?>
                <?php if($page===$records->currentPage()): ?>
                    <span class="page-number active"><?php echo e($page); ?></span>
                <?php else: ?>
                    <a class="page-number" href="<?php echo e($records->url($page)); ?>"><?php echo e($page); ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    </div>
</div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('modals'); ?>
<?php if(auth()->user()->hasPermission('media.create') || auth()->user()->hasPermission('media.edit')): ?>
<div class="modal-backdrop" id="moduleModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="moduleModalTitle">Add <?php echo e($config['title']); ?></h3>
            <button type="button" class="close-btn" data-close="moduleModal">×</button>
        </div>
        <form id="moduleForm" method="POST" action="<?php echo e(route($module.'.store')); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="_method" id="moduleMethod" value="POST">
            <div class="modal-body">
                <div class="form-grid">
                    <?php $__currentLoopData = $config['fields']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="form-group <?php echo e($field==='description' ? 'full' : ''); ?>">
                        <label for="field_<?php echo e($field); ?>"><?php echo e($config['columns'][$field] ?? ucwords(str_replace('_', ' ', $field))); ?></label>
                        <?php if($field==='status'): ?>
                            <select class="form-control" name="<?php echo e($field); ?>" id="field_<?php echo e($field); ?>">
                                <?php $__currentLoopData = $statusOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $opt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($opt); ?>"><?php echo e($opt); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        <?php elseif($field==='gateway'): ?>
                            <select class="form-control" name="<?php echo e($field); ?>" id="field_<?php echo e($field); ?>">
                                <option value="">Select Media Gateway</option>
                                <?php $__currentLoopData = $gateways; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gateway): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($gateway->site_code); ?>"><?php echo e($gateway->site_code); ?> — <?php echo e($gateway->site_name); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        <?php elseif($field==='description'): ?>
                            <textarea class="form-control" name="<?php echo e($field); ?>" id="field_<?php echo e($field); ?>"></textarea>
                        <?php elseif($isSim && in_array($field, ['contract_start','contract_end'], true)): ?>
                            <?php echo $__env->make('partials.mdy-date-field', ['field' => $field, 'fieldId' => 'field_'.$field], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        <?php elseif(in_array($field, ['contract_start','contract_end','reported_on'])): ?>
                            <input class="form-control" type="date" name="<?php echo e($field); ?>" id="field_<?php echo e($field); ?>">
                        <?php elseif(in_array($field, ['monthly_cost','retention_days'])): ?>
                            <input class="form-control" type="number" step="0.01" min="0" name="<?php echo e($field); ?>" id="field_<?php echo e($field); ?>">
                        <?php else: ?>
                            <input class="form-control" name="<?php echo e($field); ?>" id="field_<?php echo e($field); ?>" <?php echo e(in_array($field, ['description','channel','gateway','peer','context','codec','imsi','assigned_to','location','role','program','assigned_channel','issue','reported_on','retention_days','network','plan','ip_address','account_number']) ? '' : 'required'); ?>>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn secondary" data-close="moduleModal">Cancel</button>
                <button class="btn primary" type="submit">Save</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('moduleModal');
    const form = document.getElementById('moduleForm');
    const method = document.getElementById('moduleMethod');
    const storeAction = <?php echo json_encode(route($module.'.store'), 15, 512) ?>;
    function resetAdd() {
        if (!form || !method) return;
        method.value = 'POST';
        form.action = storeAction;
        document.getElementById('moduleModalTitle').textContent = <?php echo json_encode('Add '.$config['title'], 15, 512) ?>;
        form.reset();
    }
    document.getElementById('operationAddButton')?.addEventListener('click', () => {
        resetAdd();
        modal?.classList.add('visible');
    });
    document.querySelectorAll('[data-operation-edit]').forEach((button) => button.addEventListener('click', () => {
        const recordId = Number(button.dataset.id);
        if (!Number.isInteger(recordId) || recordId < 1) return;
        const values = JSON.parse(button.dataset.values || '{}');
        document.getElementById('moduleModalTitle').textContent = <?php echo json_encode('Edit '.$config['title'], 15, 512) ?>;
        method.value = 'PUT';
        form.action = <?php echo json_encode(url('/'.$module), 15, 512) ?> + '/' + recordId;
        Object.keys(values).forEach((key) => {
            const field = document.getElementById('field_' + key);
            if (field) field.value = values[key] ?? '';
        });
        modal?.classList.add('visible');
    }));
});
</script>
<?php echo $__env->make('partials.inventory-import-script', [
    'previewUrl' => auth()->user()->hasPermission('media.create') ? route($module.'.import.preview') : '',
    'confirmUrl' => auth()->user()->hasPermission('media.create') ? route($module.'.import.confirm') : '',
    'errorsUrl' => auth()->user()->hasPermission('media.create') ? route($module.'.import.errors') : '',
    'previewFields' => array_keys($transferColumns),
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php if($isSim): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const minDate = <?php echo json_encode(\App\Support\PdcEndorseDate::MIN_DATE, 15, 512) ?>;
    const minYear = Number(minDate.slice(0, 4));
    const maxYear = new Date().getFullYear();
    const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const monthShort = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const toDisplay = (iso) => {
        if (!iso) return '';
        const [year, month, day] = iso.split('-');
        if (!year || !month || !day) return '';
        return Number(month) + '/' + Number(day) + '/' + year;
    };
    const toIso = (display) => {
        const match = String(display || '').trim().match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
        if (!match) return '';
        const month = Number(match[1]);
        const day = Number(match[2]);
        const year = Number(match[3]);
        if (year < minYear || month < 1 || month > 12 || day < 1 || day > 31) return '';
        const probe = new Date(year, month - 1, day);
        if (probe.getFullYear() !== year || probe.getMonth() !== month - 1 || probe.getDate() !== day) return '';
        return year + '-' + String(month).padStart(2, '0') + '-' + String(day).padStart(2, '0');
    };
    const closeAllCals = () => document.querySelectorAll('#moduleForm .pdc-cal').forEach((cal) => cal.setAttribute('hidden', ''));

    function bindMdyDateField(textId) {
        const dateText = document.getElementById(textId);
        const datePicker = document.getElementById(textId + '_picker');
        const cal = document.getElementById(textId + '_cal');
        const calBody = cal?.querySelector('[data-pdc-cal-body]');
        const calMonthBtn = cal?.querySelector('[data-pdc-cal-month]');
        const calYearBtn = cal?.querySelector('[data-pdc-cal-year]');
        let calView = 'days';
        let calYear = maxYear;
        let calMonth = new Date().getMonth();
        let yearPageStart = minYear;

        const parseSelected = () => {
            const iso = toIso(dateText?.value) || datePicker?.value || '';
            const parts = iso.split('-').map(Number);
            if (parts.length === 3 && parts[0] >= minYear) {
                return { year: parts[0], month: parts[1] - 1, day: parts[2] };
            }
            const now = new Date();
            return { year: now.getFullYear(), month: now.getMonth(), day: now.getDate() };
        };
        const setPicked = (year, month, day) => {
            const iso = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
            if (datePicker) datePicker.value = iso;
            if (dateText) {
                dateText.value = toDisplay(iso);
                dateText.setCustomValidity('');
            }
            cal?.setAttribute('hidden', '');
        };
        const renderCal = () => {
            if (!cal || !calBody || !calMonthBtn || !calYearBtn) return;
            const selected = parseSelected();
            calMonthBtn.hidden = calView === 'years';
            if (calView === 'days') {
                calMonthBtn.textContent = monthNames[calMonth];
                calYearBtn.textContent = String(calYear);
            } else if (calView === 'months') {
                calMonthBtn.textContent = '';
                calMonthBtn.hidden = true;
                calYearBtn.textContent = String(calYear);
            } else {
                const end = Math.min(yearPageStart + 11, maxYear);
                calYearBtn.textContent = yearPageStart === end ? String(yearPageStart) : (yearPageStart + ' – ' + end);
            }
            if (calView === 'years') {
                calBody.innerHTML = '';
                const grid = document.createElement('div');
                grid.className = 'pdc-cal-grid';
                for (let year = yearPageStart; year <= Math.min(yearPageStart + 11, maxYear); year++) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'pdc-cal-cell' + (year === selected.year ? ' selected' : '');
                    btn.textContent = String(year);
                    btn.addEventListener('click', () => { calYear = year; calView = 'months'; renderCal(); });
                    grid.appendChild(btn);
                }
                calBody.appendChild(grid);
                return;
            }
            if (calView === 'months') {
                calBody.innerHTML = '';
                const grid = document.createElement('div');
                grid.className = 'pdc-cal-grid';
                monthShort.forEach((label, month) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'pdc-cal-cell' + (month === selected.month && calYear === selected.year ? ' selected' : '');
                    btn.textContent = label;
                    btn.addEventListener('click', () => { calMonth = month; calView = 'days'; renderCal(); });
                    grid.appendChild(btn);
                });
                calBody.appendChild(grid);
                return;
            }
            const first = new Date(calYear, calMonth, 1);
            const startWeekday = first.getDay();
            const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
            const prevDays = new Date(calYear, calMonth, 0).getDate();
            calBody.innerHTML = '';
            const grid = document.createElement('div');
            grid.className = 'pdc-cal-grid days';
            ['S','M','T','W','T','F','S'].forEach((dow) => {
                const el = document.createElement('div');
                el.className = 'pdc-cal-dow';
                el.textContent = dow;
                grid.appendChild(el);
            });
            for (let i = 0; i < 42; i++) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'pdc-cal-cell';
                let year = calYear;
                let month = calMonth;
                let day;
                if (i < startWeekday) {
                    month -= 1;
                    if (month < 0) { month = 11; year -= 1; }
                    day = prevDays - startWeekday + i + 1;
                    btn.classList.add('muted');
                } else if (i >= startWeekday + daysInMonth) {
                    day = i - startWeekday - daysInMonth + 1;
                    month += 1;
                    if (month > 11) { month = 0; year += 1; }
                    btn.classList.add('muted');
                } else {
                    day = i - startWeekday + 1;
                }
                if (year < minYear || year > maxYear) btn.disabled = true;
                if (year === selected.year && month === selected.month && day === selected.day) btn.classList.add('selected');
                btn.textContent = String(day);
                btn.addEventListener('click', () => setPicked(year, month, day));
                grid.appendChild(btn);
            }
            calBody.appendChild(grid);
        };
        const openCal = () => {
            const selected = parseSelected();
            calYear = Math.min(Math.max(selected.year, minYear), maxYear);
            calMonth = selected.month;
            calView = 'days';
            yearPageStart = minYear + Math.floor((calYear - minYear) / 12) * 12;
            closeAllCals();
            renderCal();
            cal?.removeAttribute('hidden');
        };
        ['pointerdown', 'mousedown', 'click'].forEach((evt) => {
            datePicker?.addEventListener(evt, (event) => {
                event.preventDefault();
                event.stopPropagation();
                if (evt === 'click') {
                    if (cal && !cal.hasAttribute('hidden')) cal.setAttribute('hidden', '');
                    else openCal();
                }
            });
        });
        cal?.addEventListener('click', (event) => event.stopPropagation());
        calMonthBtn?.addEventListener('click', () => { calView = 'months'; renderCal(); });
        calYearBtn?.addEventListener('click', () => {
            if (calView === 'years') return;
            yearPageStart = minYear + Math.floor((calYear - minYear) / 12) * 12;
            calView = 'years';
            renderCal();
        });
        cal?.querySelector('[data-pdc-cal-prev]')?.addEventListener('click', () => {
            if (calView === 'days') {
                calMonth -= 1;
                if (calMonth < 0) { calMonth = 11; calYear -= 1; }
                if (calYear < minYear) { calYear = minYear; calMonth = 0; }
            } else if (calView === 'months') {
                calYear = Math.max(minYear, calYear - 1);
            } else {
                yearPageStart = Math.max(minYear, yearPageStart - 12);
            }
            renderCal();
        });
        cal?.querySelector('[data-pdc-cal-next]')?.addEventListener('click', () => {
            if (calView === 'days') {
                calMonth += 1;
                if (calMonth > 11) { calMonth = 0; calYear += 1; }
                if (calYear > maxYear) { calYear = maxYear; calMonth = 11; }
            } else if (calView === 'months') {
                calYear = Math.min(maxYear, calYear + 1);
            } else {
                yearPageStart = Math.min(minYear + Math.floor((maxYear - minYear) / 12) * 12, yearPageStart + 12);
            }
            renderCal();
        });
        datePicker?.addEventListener('change', () => {
            if (datePicker.value) dateText.value = toDisplay(datePicker.value);
        });
        dateText?.addEventListener('blur', () => {
            const iso = toIso(dateText.value);
            if (iso) datePicker.value = iso;
        });
        dateText?.addEventListener('input', () => dateText.setCustomValidity(''));
        return {
            setValue(display) {
                if (!dateText) return;
                dateText.value = display ?? '';
                dateText.setCustomValidity('');
                if (datePicker) datePicker.value = toIso(display || '');
            },
            validate() {
                const raw = String(dateText?.value || '').trim();
                if (!dateText || raw === '') {
                    dateText?.setCustomValidity('');
                    return true;
                }
                const iso = toIso(raw);
                if (!iso) {
                    dateText.setCustomValidity('Contract date must be a valid date on or after 1/1/2000.');
                    dateText.reportValidity();
                    return false;
                }
                dateText.setCustomValidity('');
                return true;
            }
        };
    }

    const startField = bindMdyDateField('field_contract_start');
    const endField = bindMdyDateField('field_contract_end');
    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) return;
        if (event.target.closest('#moduleForm .pdc-date-field')) return;
        closeAllCals();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeAllCals();
    });
    document.getElementById('moduleForm')?.addEventListener('submit', (event) => {
        if (!startField.validate() || !endField.validate()) event.preventDefault();
    });
    document.getElementById('operationAddButton')?.addEventListener('click', () => {
        startField.setValue('');
        endField.setValue('');
        closeAllCals();
    });
    document.querySelectorAll('[data-operation-edit]').forEach((button) => button.addEventListener('click', () => {
        const values = JSON.parse(button.dataset.values || '{}');
        startField.setValue(values.contract_start || '');
        endField.setValue(values.contract_end || '');
        closeAllCals();
    }));
});
</script>
<?php endif; ?>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\OmniChannel\OmniChannel_Inventory_Production_Updated\resources\views/operations/index.blade.php ENDPATH**/ ?>