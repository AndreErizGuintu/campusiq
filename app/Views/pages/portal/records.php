<?php
/**
 * My records for students and parents (design-ref 10). "Download as PDF" opens design-refs 11 and 12.
 * @var array $student
 * @var array $records
 * @var array $counts
 * @var string|null $type
 * @var array $stats
 * @var bool $alertsOn
 * @var bool $live
 * @var bool $isParent
 */
use App\Models\Record;

$filters = ['' => 'All', 'grade' => 'Grades', 'attendance' => 'Attendance', 'library' => 'Library'];
$columns = 'md:grid-cols-[150px_minmax(0,1fr)_180px_130px]';
$whose = $isParent ? $student['first_name'] . '\'s' : 'My';
?>
<div class="flex flex-col gap-4 md:gap-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold md:text-[22px]"><?= e($whose) ?> records</h1>
            <p class="mt-1 text-[13px] text-muted">
                <?= $isParent
                    ? 'Everything the school has on file for ' . e($student['first_name'] . ' ' . $student['last_name']) . ', updated as staff add records.'
                    : 'Everything the school has on file for you, updated as staff add records.' ?>
            </p>
        </div>
        <button type="button" class="btn btn-primary shrink-0" data-portal-report><?= icon('download', 'size-4', 1.7) ?>Download as PDF</button>
    </div>

    <div class="grid grid-cols-2 gap-2.5 md:gap-4 xl:grid-cols-4">
        <?= partial('stat-card', [
            'label' => 'Attendance this month',
            'value' => $stats['month_rate'] !== null ? $stats['month_rate'] . '%' : '—',
            'note' => $stats['month_note'],
            'noteClass' => 'text-mail',
        ]) ?>
        <?= partial('stat-card', [
            'label' => 'Latest grade',
            'value' => $stats['grade']['value'] ?? '—',
            'note' => $stats['grade']['title'] ?? 'No grades yet',
        ]) ?>
        <?= partial('stat-card', ['label' => 'Library books out', 'value' => $stats['books_out'], 'note' => $stats['books_note']]) ?>
        <div class="card flex flex-col gap-1 px-4 py-3.5 md:px-5 md:py-4">
            <div class="text-xs text-muted">Last updated</div>
            <div class="pt-1 font-mono text-lg font-semibold md:text-xl"><?= $stats['last_at'] ? e(fmt_date($stats['last_at'])) : '—' ?></div>
            <div class="text-xs text-muted"><?= $stats['last_by'] ? 'by ' . e($stats['last_by']) : '&nbsp;' ?></div>
        </div>
    </div>

    <?php if ($alertsOn): ?>
        <div class="flex items-start gap-3 rounded-[10px] border border-mail/35 bg-mail/[.07] px-4 py-3 md:items-center">
            <span class="text-mail"><?= icon('mail', 'size-[18px]') ?></span>
            <p class="text-[13px] text-mail-dark">
                <span class="font-semibold">Email alerts are on.</span>
                <?= $isParent ? 'You and ' . e($student['first_name']) . ' get' : 'You and your guardian get' ?> an email automatically whenever something here changes.
                <a href="<?= e(url('/my/notifications')) ?>" class="font-medium underline-offset-2 hover:underline">See them</a>
            </p>
        </div>
    <?php endif; ?>

    <section class="card overflow-hidden" aria-labelledby="records-title">
        <div class="flex flex-col gap-3 border-b border-line px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between md:px-5 md:py-4">
            <h2 id="records-title" class="card-title">Recent records</h2>
            <nav class="-mx-1 flex gap-2 overflow-x-auto px-1" aria-label="Filter records">
                <?php foreach ($filters as $key => $label): $on = ($type ?? '') === $key; ?>
                    <a href="<?= e(url('/my/records', ['type' => $key])) ?>" class="chip<?= $on ? ' is-on' : '' ?>"<?= $on ? ' aria-current="true"' : '' ?>>
                        <?= e($label) ?><?php if ($key !== ''): ?> <span class="ml-1 opacity-60"><?= (int) $counts[$key] ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
        <?php if (!$records): ?>
            <?= partial('empty-state', [
                'title' => $type ? 'No ' . strtolower($filters[$type]) . ' records yet' : 'No records yet',
                'text' => 'Records show up here as soon as staff add them.',
                'icon' => 'records',
            ]) ?>
        <?php else: ?>
            <div class="data-head hidden <?= $columns ?> md:grid"><div>Type</div><div>Detail</div><div>Recorded by</div><div>Date</div></div>
            <ul>
                <?php foreach ($records as $record): ?>
                    <li class="data-row grid grid-cols-[1fr_auto] items-center gap-x-3 gap-y-1.5 px-4 md:px-5 <?= $columns ?>">
                        <span><?= partial('record-pill', ['type' => $record['type']]) ?></span>
                        <span class="col-span-2 row-start-2 min-w-0 md:col-span-1 md:row-start-auto">
                            <?= e(record_detail($record)) ?>
                            <?php if (!empty($record['note']) && $record['type'] === 'library'): ?><span class="block text-xs text-muted"><?= e($record['note']) ?></span><?php endif; ?>
                        </span>
                        <span class="col-span-2 row-start-3 text-xs text-muted md:col-span-1 md:row-start-auto md:text-[13.5px]"><?= e(Record::byLabel($record)) ?></span>
                        <span class="col-start-2 row-start-1 text-xs text-muted md:col-start-auto md:row-start-auto md:text-[13.5px]"><?= e(fmt_date($record['recorded_on'], true)) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<?= partial('report-progress', ['variant' => 'portal', 'live' => $live]) ?>
