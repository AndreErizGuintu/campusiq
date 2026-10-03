<?php
/**
 * Students list with search.
 * @var array $students
 * @var string $query
 */
use App\Models\Student;

$rate = static fn (array $s): ?int => $s['attendance_count'] ? (int) round($s['present_count'] / $s['attendance_count'] * 100) : null;
?>
<div class="flex flex-col gap-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold md:text-[22px]"><?= $query !== '' ? 'Search results' : 'All students' ?></h2>
            <p class="mt-1 text-[13px] text-muted">
                <?= count($students) ?> <?= count($students) === 1 ? 'student' : 'students' ?><?= $query !== '' ? ' matching "' . e($query) . '"' : '' ?>. Open a student to see and add records.
            </p>
        </div>
        <form method="get" action="<?= e(url('/students')) ?>" role="search" class="flex w-full gap-2 sm:w-auto">
            <label for="student-search" class="sr-only">Search students</label>
            <div class="relative grow sm:w-72">
                <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-faint"><?= icon('search', 'size-4') ?></span>
                <input id="student-search" name="q" type="search" value="<?= e($query) ?>" placeholder="Name, ID, section or email" class="input pl-9">
            </div>
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if ($query !== ''): ?><a href="<?= e(url('/students')) ?>" class="btn btn-ghost">Clear</a><?php endif; ?>
        </form>
    </div>

    <div class="card overflow-hidden">
        <?php if (!$students): ?>
            <?= partial('empty-state', [
                'title' => 'No students match "' . $query . '"',
                'text' => 'Try a name, a student number like 10-24031, or a section like 10-A.',
                'icon' => 'search',
                'action' => '<a class="btn btn-ghost" href="' . e(url('/students')) . '">Show all students</a>',
            ]) ?>
        <?php else: ?>
            <div class="data-head hidden grid-cols-[minmax(0,1fr)_110px_80px_150px_90px_100px] md:grid">
                <div>Student</div><div>Student ID</div><div>Section</div><div>Attendance</div><div>Records</div><div>Last record</div>
            </div>
            <ul>
                <?php foreach ($students as $s): $r = $rate($s); ?>
                    <li class="border-b border-line-soft last:border-b-0">
                        <a href="<?= e(url('/students/' . $s['id'])) ?>" class="grid grid-cols-[1fr_auto] items-center gap-x-4 gap-y-1 px-4 py-3 text-[13.5px] hover:bg-[#fafaff] md:grid-cols-[minmax(0,1fr)_110px_80px_150px_90px_100px] md:px-5">
                            <span class="flex min-w-0 items-center gap-3">
                                <span class="avatar bg-lilac text-primary" aria-hidden="true"><?= e(initials(Student::fullName($s))) ?></span>
                                <span class="min-w-0">
                                    <span class="block truncate font-medium"><?= e(Student::fullName($s)) ?></span>
                                    <span class="block truncate text-xs text-muted"><?= e($s['email']) ?></span>
                                </span>
                            </span>
                            <span class="text-right font-mono text-xs text-muted md:text-left md:text-[13px] md:text-ink"><?= e($s['student_no']) ?></span>
                            <span class="hidden md:block"><?= e($s['section']) ?></span>
                            <span class="col-span-2 flex items-center gap-2 pl-[46px] text-xs md:col-span-1 md:pl-0 md:text-[13px]">
                                <?php if ($r !== null): ?>
                                    <span class="h-1.5 w-12 overflow-hidden rounded-full bg-line-soft" aria-hidden="true"><span class="block h-full rounded-full <?= $r >= 90 ? 'bg-mail' : ($r >= 80 ? 'bg-[#b45309]' : 'bg-pdf') ?>" style="width: <?= $r ?>%"></span></span>
                                    <span><?= $r ?>%</span>
                                    <?php if ($s['absent_count']): ?><span class="text-muted">· <?= (int) $s['absent_count'] ?> absent</span><?php endif; ?>
                                    <span class="text-muted md:hidden">· <?= e($s['section']) ?> · <?= (int) $s['record_count'] ?> records</span>
                                <?php else: ?>
                                    <span class="text-muted">No attendance yet</span>
                                <?php endif; ?>
                            </span>
                            <span class="hidden md:block"><?= (int) $s['record_count'] ?></span>
                            <span class="hidden text-muted md:block"><?= e(fmt_date($s['last_record_on'])) ?: '—' ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
