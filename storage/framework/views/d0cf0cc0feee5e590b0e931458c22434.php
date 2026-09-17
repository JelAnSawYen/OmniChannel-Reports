<?php
    $window = \App\Support\PageWindow::make($paginator->currentPage(), $paginator->lastPage());
    $dataPage = $dataPage ?? false;
?>
<div class="pager"<?php if(!empty($pagerId)): ?> id="<?php echo e($pagerId); ?>"<?php endif; ?>>
    <?php if($paginator->onFirstPage()): ?>
        <span class="page-number disabled">‹</span>
    <?php else: ?>
        <a class="page-number" href="<?php echo e($paginator->previousPageUrl()); ?>"<?php if($dataPage): ?> data-page="<?php echo e($paginator->currentPage() - 1); ?>"<?php endif; ?>>‹</a>
    <?php endif; ?>
    <?php if($window['hasStartEllipsis']): ?>
        <span class="pager-ellipsis">...</span>
    <?php endif; ?>
    <?php $__currentLoopData = $window['pages']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $page): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if($page === $paginator->currentPage()): ?>
            <span class="page-number active"><?php echo e($page); ?></span>
        <?php else: ?>
            <a class="page-number" href="<?php echo e($paginator->url($page)); ?>"<?php if($dataPage): ?> data-page="<?php echo e($page); ?>"<?php endif; ?>><?php echo e($page); ?></a>
        <?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    <?php if($window['hasEndEllipsis']): ?>
        <span class="pager-ellipsis">...</span>
    <?php endif; ?>
    <?php if($paginator->hasMorePages()): ?>
        <a class="page-number" href="<?php echo e($paginator->nextPageUrl()); ?>"<?php if($dataPage): ?> data-page="<?php echo e($paginator->currentPage() + 1); ?>"<?php endif; ?>>›</a>
    <?php else: ?>
        <span class="page-number disabled">›</span>
    <?php endif; ?>
</div>
<?php /**PATH C:\xampp\htdocs\OmniChannel\OmniChannel_Inventory_Production_Updated\resources\views/partials/table-pager.blade.php ENDPATH**/ ?>