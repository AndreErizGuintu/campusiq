<?php
/**
 * "Generating…" dialog shown while the PDF is made (design-ref 09 for staff, 11 for students).
 * reports.js drives the steps; the portal variant also switches it to the "ready" state (design-ref 12).
 * @var string $variant 'staff' | 'portal'
 * @var bool $live PDFShift key present
 */
$steps = [
    ['records', $variant === 'portal' ? 'Pulling your records from the database' : 'Pulling records'],
    ['build', $variant === 'portal' ? 'Laying them out as a report page' : 'Building the report page'],
    ['convert', $live ? 'Sending to PDFShift, converting…' : 'Demo mode: skipping PDFShift, saving the HTML page'],
    ['ready', $variant === 'portal' ? 'Ready to download' : 'Download'],
];
?>
<div class="fixed inset-0 z-[60] flex items-center justify-center bg-[rgba(20,22,40,.45)] p-4" data-report-modal hidden>
    <div role="dialog" aria-modal="true" aria-labelledby="report-modal-title" class="flex max-h-full w-full overflow-y-auto rounded-2xl bg-white shadow-[0_24px_60px_rgba(20,22,40,.25)] <?= $variant === 'portal' ? 'max-w-[480px]' : 'max-w-[760px]' ?>">
        <div class="flex grow flex-col gap-5 p-6 md:p-7" data-report-progress>
            <div class="flex items-start gap-4">
                <?php if ($variant === 'portal'): ?>
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"><?= icon('pdf', 'size-6') ?></span>
                <?php endif; ?>
                <div>
                    <h2 id="report-modal-title" class="text-[17px] font-semibold"><?= $variant === 'portal' ? 'Generating your PDF…' : 'Generating report…' ?></h2>
                    <p class="mt-1 text-[13px] leading-normal text-muted" data-report-subtitle>
                        <?= $variant === 'portal' ? 'Your record is being turned into a PDF' . ($live ? ' by PDFShift' : '') . '. This usually takes a few seconds.' : '' ?>
                    </p>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <div class="h-2 overflow-hidden rounded-full bg-[#eceef4]"><div class="h-2 w-[8%] rounded-full bg-primary transition-[width] duration-500" data-report-bar></div></div>
                <div class="flex justify-between text-xs text-muted"><span data-report-step-label>Step 1 of 4</span><span data-report-percent>8%</span></div>
            </div>

            <ol class="flex flex-col gap-3.5">
                <?php foreach ($steps as $i => [$key, $text]): ?>
                    <li class="flex items-center gap-3 text-[13.5px] <?= $i === 0 ? 'font-medium text-primary' : 'text-faint' ?>" data-report-step="<?= $key ?>">
                        <span class="flex size-[22px] shrink-0 items-center justify-center rounded-full <?= $i === 0 ? '' : 'border-[1.5px] border-[#d3d7e2]' ?>" data-dot><?= $i === 0 ? '<span class="spin"></span>' : '' ?></span>
                        <span data-text><?= e($text) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>

            <p class="hidden rounded-lg bg-pdf/[.06] px-3 py-2.5 text-[12.5px] text-pdf" data-report-error role="alert"></p>

            <div class="flex items-center justify-between border-t border-line-soft pt-3.5">
                <button type="button" class="text-[13px] text-muted hover:text-ink" data-report-cancel>Cancel</button>
                <span class="text-[12px] text-faint">A4<?= $live ? '' : ' · demo mode' ?></span>
            </div>
        </div>

        <?php if ($variant === 'staff'): ?>
            <div class="hidden w-[300px] shrink-0 flex-col items-center justify-center gap-3 bg-[#eef0f5] p-6 md:flex" aria-hidden="true">
                <div class="flex h-[300px] w-[212px] flex-col gap-[7px] rounded-[3px] bg-white px-3.5 py-4 shadow-[0_6px_18px_rgba(20,22,40,.12)]">
                    <div class="flex items-center justify-between"><span class="font-mono text-[9px] font-semibold text-primary">CampusIQ</span><span class="text-[7px] text-faint"><?= e(fmt_date(date('Y-m-d'), true)) ?></span></div>
                    <div class="h-0.5 bg-primary"></div>
                    <div class="text-[11px] font-semibold" data-preview-title>Student Report</div>
                    <div class="text-[8px] text-muted" data-preview-student></div>
                    <div class="shimmer mt-2 flex flex-col gap-[7px]">
                        <?php foreach (['w-[90%]', 'w-3/4', 'w-[85%]', 'w-3/5', 'w-[80%]', 'w-[70%]', 'w-[88%]', 'w-1/2'] as $w): ?><div class="skel <?= $w ?>"></div><?php endforeach; ?>
                    </div>
                </div>
                <div class="text-[11.5px] text-muted">Preview, still rendering</div>
            </div>
        <?php endif; ?>
    </div>
</div>
