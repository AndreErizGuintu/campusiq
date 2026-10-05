<?php
/**
 * Student detail with records table and add / edit / delete.
 * @var array $student
 * @var array $records
 * @var array $counts
 * @var string|null $type
 * @var array|null $editing
 * @var int|null $highlight id of the record just saved
 */
use App\Models\Record;
use App\Models\Student;

$name = Student::fullName($student);
$total = array_sum($counts);
$filters = ['' => ['All', $total], 'grade' => ['Grades', $counts['grade']], 'attendance' => ['Attendance', $counts['attendance']], 'library' => ['Library', $counts['library']]];
$columns = 'md:grid-cols-[120px_minmax(0,1fr)_120px_90px_72px]';
?>
<div class="flex flex-col gap-4 md:gap-5">
    <section class="card flex flex-col gap-4 p-4 md:flex-row md:items-center md:gap-5 md:px-6 md:py-5">
        <div class="flex items-center gap-4 md:contents">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-full bg-lilac font-mono text-base font-semibold text-primary md:size-[60px] md:text-[19px]" aria-hidden="true"><?= e(initials($name)) ?></div>
            <div class="flex min-w-0 grow flex-col gap-3">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-lg font-semibold md:text-xl"><?= e($name) ?></h1>
                    <span class="pill <?= $student['status'] === 'active' ? 'pill-ok' : 'pill-muted' ?>"><?= e(ucfirst($student['status'])) ?></span>
                </div>
                <dl class="hidden flex-wrap gap-x-9 gap-y-2 md:flex">
                    <?php foreach ([['Student ID', $student['student_no']], ['Section', 'Grade ' . $student['section']], ['Student email', $student['email']], ['Guardian', $student['guardian_name']], ['Guardian email', $student['guardian_email']]] as [$label, $value]): ?>
                        <div><dt class="text-[11.5px] text-faint"><?= e($label) ?></dt><dd class="mt-0.5 text-[13px] break-all"><?= e($value) ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            </div>
        </div>
        <dl class="grid grid-cols-2 gap-x-4 gap-y-2 md:hidden">
            <?php foreach ([['Student ID', $student['student_no']], ['Section', 'Grade ' . $student['section']], ['Student email', $student['email']], ['Guardian email', $student['guardian_email']]] as [$label, $value]): ?>
                <div class="min-w-0"><dt class="text-[11.5px] text-faint"><?= e($label) ?></dt><dd class="mt-0.5 truncate text-[13px]"><?= e($value) ?></dd></div>
            <?php endforeach; ?>
        </dl>
        <div class="grid grid-cols-2 gap-2.5 md:flex md:shrink-0 md:self-start">
            <a href="<?= e(url('/ai', ['student' => $student['id']])) ?>" class="btn btn-ghost text-[13px]"><?= icon('ai', 'size-4') ?>Summarize</a>
            <a href="<?= e(url('/reports', ['student' => $student['id']])) ?>" class="btn btn-outline text-[13px]"><?= icon('pdf', 'size-4') ?>Generate PDF report</a>
        </div>
    </section>

    <div class="flex flex-col-reverse gap-4 md:gap-5 lg:flex-row lg:items-start">
        <section class="card min-w-0 grow overflow-hidden" aria-labelledby="records-title">
            <div class="flex flex-col gap-3 border-b border-line px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between md:px-5">
                <div class="flex items-baseline gap-3">
                    <h2 id="records-title" class="card-title">Records</h2>
                    <span class="text-[12.5px] text-muted"><?= count($records) ?> <?= $type ? 'shown' : 'total' ?></span>
                </div>
                <nav class="-mx-1 flex gap-2 overflow-x-auto px-1" aria-label="Filter records">
                    <?php foreach ($filters as $key => [$label, $count]): $on = ($type ?? '') === $key; ?>
                        <a href="<?= e(url('/students/' . $student['id'], ['type' => $key])) ?>" class="chip<?= $on ? ' is-on' : '' ?>"<?= $on ? ' aria-current="true"' : '' ?>><?= e($label) ?> <span class="ml-1 opacity-60"><?= (int) $count ?></span></a>
                    <?php endforeach; ?>
                </nav>
            </div>

            <?php if (!$records): ?>
                <?= partial('empty-state', [
                    'title' => $type ? 'No ' . strtolower($filters[$type][0]) . ' records yet' : 'No records yet',
                    'text' => 'Use the form to add the first one.',
                    'icon' => 'records',
                ]) ?>
            <?php else: ?>
                <div class="data-head hidden <?= $columns ?> md:grid"><div>Type</div><div>Detail</div><div>By</div><div>Date</div><div class="sr-only">Actions</div></div>
                <ul>
                    <?php foreach ($records as $record): $isNew = (int) $highlight === (int) $record['id']; ?>
                        <li class="data-row grid grid-cols-[1fr_auto] items-center gap-x-3 gap-y-1.5 px-4 md:px-5 <?= $columns ?><?= $isNew ? ' bg-mail/[.06]' : '' ?><?= ($editing && (int) $editing['id'] === (int) $record['id']) ? ' bg-primary/[.04]' : '' ?>" id="record-<?= (int) $record['id'] ?>">
                            <span class="flex items-center gap-2"><?= partial('record-pill', ['type' => $record['type']]) ?></span>
                            <span class="col-span-2 row-start-2 flex min-w-0 flex-wrap items-center gap-2 md:col-span-1 md:row-start-auto">
                                <span class="min-w-0 break-words"><?= e(record_detail($record)) ?></span>
                                <?php if ($isNew): ?><span class="rounded-full bg-mail/[.12] px-[7px] py-0.5 text-[10.5px] font-semibold tracking-[.04em] text-mail uppercase">New</span><?php endif; ?>
                                <?php if (!empty($record['note']) && $record['type'] !== 'attendance'): ?><span class="w-full text-xs text-muted"><?= e($record['note']) ?></span><?php endif; ?>
                            </span>
                            <span class="col-span-2 row-start-3 text-xs text-muted md:col-span-1 md:row-start-auto md:text-[13.5px]">
                                <?= e(Record::byLabel($record)) ?><span class="md:hidden"> · <?= e(fmt_date($record['recorded_on'], true)) ?></span>
                            </span>
                            <span class="hidden <?= $isNew ? 'font-medium text-mail' : 'text-muted' ?> md:block"><?= $isNew ? 'Just now' : e(fmt_date($record['recorded_on'])) ?></span>
                            <span class="col-start-2 row-start-1 flex justify-end gap-1 md:col-start-auto md:row-start-auto">
                                <a href="<?= e(url('/students/' . $student['id'], ['edit' => $record['id'], 'type' => $type])) ?>#record-form" class="flex size-8 items-center justify-center rounded-lg text-muted hover:bg-page hover:text-primary" aria-label="Edit <?= e(record_detail($record)) ?>" title="Edit"><?= icon('edit', 'size-4') ?></a>
                                <form method="post" action="<?= e(url('/records/' . $record['id'] . '/delete')) ?>" data-confirm="Delete this record? <?= e(record_detail($record)) ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="flex size-8 items-center justify-center rounded-lg text-muted hover:bg-pdf/5 hover:text-pdf" aria-label="Delete <?= e(record_detail($record)) ?>" title="Delete"><?= icon('trash', 'size-4') ?></button>
                                </form>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <div class="lg:sticky lg:top-24 lg:w-[360px] lg:shrink-0">
            <?= partial('record-form', ['student' => $student, 'editing' => $editing]) ?>
        </div>
    </div>
</div>
