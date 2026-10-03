<?php
/**
 * @var string $label
 * @var string|int $value
 * @var string|null $note
 * @var string|null $noteClass
 * @var string|null $suffix small text after the value, e.g. "/38"
 */
?>
<div class="card flex flex-col gap-1 px-4 py-3.5 md:px-5 md:py-4">
    <div class="text-xs text-muted"><?= e($label) ?></div>
    <div class="font-mono text-[22px] font-semibold md:text-[26px]"><?= e($value) ?><?php if (!empty($suffix)): ?><span class="text-[13px] font-medium text-faint"><?= e($suffix) ?></span><?php endif; ?></div>
    <?php if (!empty($note)): ?>
        <div class="text-xs <?= e($noteClass ?? 'text-muted') ?>"><?= e($note) ?></div>
    <?php endif; ?>
</div>
