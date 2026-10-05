<?php
/**
 * "Report ready" card with a live preview of the page (right column of PDF Reports).
 * @var array $report reports row joined with student fields
 * @var array|null $meta seconds, pages, record_count (only right after generating)
 */
use App\Models\Report;

$meta ??= null;
$live = $report['mode'] === 'live';
$guardianLink = url('/emails', ['student' => $report['student_id'], 'template' => 'report_ready', 'report' => $report['id'], 'to' => 'guardian']);
?>
<div class="card flex flex-col gap-5 p-4 sm:flex-row md:px-5 md:py-[18px]" data-report-result>
    <div class="relative mx-auto h-[268px] w-[190px] shrink-0 overflow-hidden rounded-[3px] border border-line bg-white shadow-[0_6px_18px_rgba(20,22,40,.10)] sm:mx-0">
        <iframe src="<?= e(url('/reports/' . $report['id'] . '/preview')) ?>" title="Preview of <?= e(Report::fileName($report)) ?>" loading="lazy" tabindex="-1"
                class="pointer-events-none absolute top-0 left-0 h-[1123px] w-[794px] origin-top-left scale-[.2393] border-0"></iframe>
    </div>
    <div class="flex grow flex-col gap-3.5">
        <div class="flex items-center gap-2.5">
            <span class="flex size-[30px] items-center justify-center rounded-full bg-mail/[.12] text-mail"><?= icon('check', 'size-4', 2.2) ?></span>
            <div class="text-sm font-semibold"><?= $meta ? 'Report ready' : 'Latest report' ?></div>
            <?php if (!$live): ?><span class="pill pill-demo h-[22px] text-[11px]">Demo</span><?php endif; ?>
        </div>
        <dl class="grid grid-cols-[70px_1fr] gap-y-[7px] text-[12.5px]">
            <dt class="text-faint">Student</dt><dd><?= e($report['first_name'] . ' ' . $report['last_name']) ?> · <?= e(Report::typeLabel($report['report_type'])) ?></dd>
            <dt class="text-faint">File</dt><dd class="break-all"><?= e(Report::fileName($report)) ?></dd>
            <dt class="text-faint">Pages</dt><dd><?= !empty($meta['pages']) ? (int) $meta['pages'] . ' · ' : '' ?>A4</dd>
            <dt class="text-faint">Size</dt><dd><?= e(format_bytes((int) $report['size_bytes'])) ?></dd>
            <?php if ($meta): ?>
                <dt class="text-faint">Made in</dt><dd><?= e(number_format((float) $meta['seconds'], 1)) ?> s<?= $live ? '' : ' (demo mode)' ?></dd>
                <dt class="text-faint">Records</dt><dd><?= (int) $meta['record_count'] ?></dd>
            <?php else: ?>
                <dt class="text-faint">Made</dt><dd><?= e(time_ago($report['created_at'])) ?></dd>
            <?php endif; ?>
        </dl>
        <div class="grow"></div>
        <div class="flex flex-col gap-2">
            <a href="<?= e(url('/reports/' . $report['id'] . '/download')) ?>" class="btn btn-pdf w-full"<?= $live ? '' : ' target="_blank" rel="noopener"' ?>>
                <?= icon('download', 'size-4', 1.7) ?><?= $live ? 'Download PDF' : 'Open print view' ?>
            </a>
            <a href="<?= e($guardianLink) ?>" class="btn btn-ghost w-full text-[13px] font-normal"><?= icon('mail', 'size-4') ?>Email it to guardian</a>
        </div>
    </div>
</div>
