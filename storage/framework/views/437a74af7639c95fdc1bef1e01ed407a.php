<?php
    $inputId = $inputId ?? 'campaign_combo';
    $inputName = $inputName ?? 'campaign';
    $menuId = $menuId ?? $inputId.'_menu';
    $required = $required ?? true;
    $campaigns = $campaigns ?? collect();
?>
<div class="pin-campaign-combo" data-campaign-combo>
    <input class="form-control" name="<?php echo e($inputName); ?>" id="<?php echo e($inputId); ?>" placeholder="Select or type a campaign..." autocomplete="off" <?php if($required): ?> required <?php endif; ?> aria-autocomplete="list" aria-controls="<?php echo e($menuId); ?>">
    <div class="pin-campaign-menu" id="<?php echo e($menuId); ?>" hidden role="listbox">
        <?php $__empty_1 = true; $__currentLoopData = $campaigns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $campaign): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <button class="pin-campaign-option" type="button" role="option" data-name="<?php echo e($campaign->name); ?>" data-id="<?php echo e($campaign->id); ?>" data-fte="<?php echo e($campaign->fte); ?>"><?php echo e($campaign->name); ?></button>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="pin-campaign-empty">No campaigns yet. Type a new name.</div>
        <?php endif; ?>
    </div>
</div>
<?php /**PATH C:\xampp\htdocs\OmniChannel\OmniChannel_Inventory_Production_Updated\resources\views/partials/campaign-combo.blade.php ENDPATH**/ ?>