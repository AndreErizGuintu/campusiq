<?php
/**
 * @var string $title
 * @var string|null $text
 * @var string|null $icon
 * @var string|null $action trusted HTML for a button or link
 */
?>
<div class="flex flex-col items-center gap-2 px-6 py-10 text-center">
    <span class="mb-1 flex size-11 items-center justify-center rounded-xl bg-ink/5 text-muted"><?= icon($icon ?? 'file', 'size-5') ?></span>
    <div class="text-sm font-semibold"><?= e($title) ?></div>
    <?php if (!empty($text)): ?><p class="max-w-sm text-[13px] text-muted"><?= e($text) ?></p><?php endif; ?>
    <?php if (!empty($action)): ?><div class="mt-2"><?= $action ?></div><?php endif; ?>
</div>
