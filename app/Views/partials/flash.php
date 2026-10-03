<?php
/** Flash messages as toasts (bottom right), like the "Record saved" toast in design-ref 08. */
$messages = array_filter([
    'success' => flash('success'),
    'error' => flash('error'),
    'info' => flash('info'),
]);
$dot = ['success' => 'bg-mail', 'error' => 'bg-pdf', 'info' => 'bg-primary'];
$glyph = ['success' => 'check', 'error' => 'close', 'info' => 'alert'];
?>
<div id="toasts" class="fixed right-4 bottom-[76px] z-50 flex w-[min(380px,calc(100vw-32px))] flex-col gap-2.5 md:right-7 md:bottom-7" aria-live="polite">
    <?php foreach ($messages as $type => $message): ?>
        <div role="status" data-toast data-autoclose class="toast-in flex items-center gap-3 rounded-xl bg-side px-4 py-3.5 text-white shadow-[0_12px_30px_rgba(20,22,40,.25)]">
            <span class="flex size-[22px] shrink-0 items-center justify-center rounded-full <?= $dot[$type] ?>"><?= icon($glyph[$type], 'size-3 text-white', 2.4) ?></span>
            <span class="grow text-[13.5px]"><?= e($message) ?></span>
            <button type="button" data-toast-close class="text-[#8f94ad] hover:text-white" aria-label="Dismiss"><?= icon('close', 'size-4') ?></button>
        </div>
    <?php endforeach; ?>
</div>
