<?php
    $fieldId = $fieldId ?? ('field_'.$field);
    $placeholder = $placeholder ?? 'M/D/YYYY';
?>
<div class="pdc-date-field">
    <input class="form-control" type="text" name="<?php echo e($field); ?>" id="<?php echo e($fieldId); ?>" placeholder="<?php echo e($placeholder); ?>" autocomplete="off">
    <span class="pdc-date-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/></svg>
    </span>
    <input type="date" id="<?php echo e($fieldId); ?>_picker" class="pdc-date-native" min="<?php echo e(\App\Support\PdcEndorseDate::MIN_DATE); ?>" tabindex="-1" aria-label="Choose date">
    <div class="pdc-cal" id="<?php echo e($fieldId); ?>_cal" hidden>
        <div class="pdc-cal-head">
            <button type="button" class="pdc-cal-nav" data-pdc-cal-prev aria-label="Previous">‹</button>
            <div class="pdc-cal-caption">
                <button type="button" class="pdc-cal-month" data-pdc-cal-month></button>
                <button type="button" class="pdc-cal-year" data-pdc-cal-year></button>
            </div>
            <button type="button" class="pdc-cal-nav" data-pdc-cal-next aria-label="Next">›</button>
        </div>
        <div class="pdc-cal-body" data-pdc-cal-body></div>
    </div>
</div>
<?php /**PATH C:\xampp\htdocs\OmniChannel\OmniChannel_Inventory_Production_Updated\resources\views/partials/mdy-date-field.blade.php ENDPATH**/ ?>