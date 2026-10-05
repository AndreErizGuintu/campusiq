<?php
/**
 * One row in "Recent reports".
 * @var array $report
 */
use App\Models\Report;

$byPortal = in_array($report['created_by_role'] ?? null, ['student', 'parent'], true);
?>
<li class="grid grid-cols-[minmax(0,1fr)_auto_auto] items-center gap-x-3 border-b border-line-soft py-2.5 text-[12.5px] last:border-b-0 sm:grid-cols-[minmax(0,1fr)_90px_70px]" data-report-row="<?= (int) $report['id'] ?>">
    <div class="min-w-0">
        <div class="truncate"><?= e($report['first_name'] . ' ' . $report['last_name']) ?> · <?= e(Report::typeLabel($report['report_type'])) ?></div>
        <div class="text-[11.5px] text-faint"><?= $report['mode'] === 'demo' ? 'Demo (HTML)' : 'PDF' ?><?= $byPortal ? ' · by ' . e($report['created_by_role']) : '' ?></div>
    </div>
    <div class="text-muted"><?= e(time_ago($report['created_at'])) ?></div>
    <a href="<?= e(url('/reports/' . $report['id'] . '/download')) ?>" class="font-medium text-primary hover:underline"<?= $report['mode'] === 'demo' ? ' target="_blank" rel="noopener"' : '' ?>>Download</a>
</li>
