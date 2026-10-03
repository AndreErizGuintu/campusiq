<?php
/**
 * Reports made from this student's record (by staff or by the student / parent).
 * @var array $student
 * @var array $reports
 * @var bool $live
 * @var bool $isParent
 */
use App\Models\Report;
?>
<div class="flex flex-col gap-4 md:gap-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold md:text-[22px]">Reports</h1>
            <p class="mt-1 text-[13px] text-muted">PDF copies of <?= $isParent ? e($student['first_name']) . '\'s' : 'your' ?> record. Make a fresh one any time; it always matches the latest records.</p>
        </div>
        <button type="button" class="btn btn-primary shrink-0" data-portal-report><?= icon('pdf', 'size-4', 1.7) ?>Make a new PDF</button>
    </div>

    <section class="card overflow-hidden" aria-label="Reports">
        <?php if (!$reports): ?>
            <?= partial('empty-state', [
                'title' => 'No reports yet',
                'text' => 'Press "Make a new PDF" to turn ' . ($isParent ? $student['first_name'] . '\'s' : 'your') . ' current record into a PDF.',
                'icon' => 'pdf',
            ]) ?>
        <?php else: ?>
            <div class="data-head hidden grid-cols-[minmax(0,1fr)_150px_110px_100px] md:grid"><div>Report</div><div>Made by</div><div>Date</div><div class="sr-only">Download</div></div>
            <ul>
                <?php foreach ($reports as $report): $live = $report['mode'] === 'live'; ?>
                    <li class="data-row grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-x-3 px-4 md:grid-cols-[minmax(0,1fr)_150px_110px_100px] md:px-5">
                        <span class="flex h-10 w-[34px] shrink-0 items-end justify-center rounded bg-pdf pb-[5px] text-[9px] font-semibold text-white md:hidden"><?= $live ? 'PDF' : 'HTML' ?></span>
                        <span class="min-w-0">
                            <span class="block truncate font-medium"><?= e(Report::fileName($report)) ?></span>
                            <span class="block text-xs text-muted"><?= e(Report::typeLabel($report['report_type'])) ?> · <?= e(format_bytes((int) $report['size_bytes'])) ?><?= $live ? '' : ' · demo' ?><span class="md:hidden"> · <?= e(fmt_date($report['created_at'], true)) ?></span></span>
                        </span>
                        <span class="hidden text-muted md:block"><?= e($report['created_by_name'] ?? '—') ?></span>
                        <span class="hidden text-muted md:block"><?= e(fmt_date($report['created_at'], true)) ?></span>
                        <a href="<?= e(url('/reports/' . $report['id'] . '/download')) ?>" class="btn btn-ghost btn-sm justify-self-end"<?= $live ? '' : ' target="_blank" rel="noopener"' ?>>
                            <?= icon('download', 'size-3.5', 1.7) ?>Download
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<?= partial('report-progress', ['variant' => 'portal', 'live' => $live]) ?>
