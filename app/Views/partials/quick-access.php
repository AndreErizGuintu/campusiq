<?php
/** Links to the three API pages (design-ref 03 desktop, 15 mobile). */
$links = [
    ['/ai', 'ai', 'bg-primary/10 text-primary', 'AI Assistant', 'API 1', 'Ask about records · Gemini'],
    ['/emails', 'mail', 'bg-mail/10 text-mail', 'Email Alerts', 'API 2', 'Notify students & parents · EmailJS'],
    ['/reports', 'pdf', 'bg-pdf/[.08] text-pdf', 'PDF Reports', 'API 3', 'Download any record · PDFShift'],
];
?>
<?php foreach ($links as [$href, $iconName, $iconClass, $name, $tag, $sub]): ?>
    <a href="<?= e(url($href)) ?>" class="flex items-center gap-3.5 rounded-[10px] border border-line bg-white p-3 text-ink transition-colors hover:border-primary hover:bg-[#fafaff] md:p-3.5">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-[9px] md:size-10 md:rounded-[10px] <?= $iconClass ?>"><?= icon($iconName, 'size-5') ?></span>
        <span class="grow">
            <span class="flex items-center gap-2 text-sm font-semibold"><?= e($name) ?><span class="api-tag"><?= e($tag) ?></span></span>
            <span class="mt-0.5 block text-xs text-muted"><?= e($sub) ?></span>
        </span>
        <?= icon('chevron-right', 'size-4 text-faint') ?>
    </a>
<?php endforeach; ?>
