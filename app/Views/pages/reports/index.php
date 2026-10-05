<?php
/**
 * PDF Reports.
 * @var array $students
 * @var int $selected
 * @var array|null $latest
 * @var array $recent
 * @var int $weekCount
 * @var bool $live
 * @var bool $sandbox
 */
use App\Models\Report;
use App\Models\Student;

$defaultFrom = date('Y-m-d', strtotime('-60 days'));
?>
<div class="flex flex-col gap-4">
    <p class="text-[13px] text-muted">
        Pick a student and what to include. CampusIQ turns it into a PDF you can download or send.
        <?php if ($live && $sandbox): ?><span class="text-lib">Test mode is on: PDFs come with a watermark.</span><?php endif; ?>
    </p>

    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:gap-[18px]">
        <form class="card flex flex-col gap-3.5 px-4 py-4 md:px-5 md:py-[18px] xl:w-[540px] xl:shrink-0" data-report-form data-no-lock novalidate>
            <h2 class="card-title">Create a report</h2>

            <div class="field">
                <label class="label" for="report-student">Student</label>
                <select class="input" id="report-student" name="student_id">
                    <?php foreach ($students as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" data-name="<?= e(Student::fullName($s)) ?>" data-meta="<?= e($s['student_no'] . ' · ' . $s['section']) ?>" <?= (int) $s['id'] === $selected ? 'selected' : '' ?>>
                            <?= e(Student::fullName($s)) ?> · <?= e($s['student_no']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <fieldset class="flex flex-col gap-1.5">
                <legend class="label mb-1.5">Report type</legend>
                <div class="grid grid-cols-2 gap-2">
                    <?php foreach (Report::TYPES as $key => [$label, $hint, $includes]): ?>
                        <label class="flex cursor-pointer flex-col gap-[3px] rounded-[9px] border border-line px-3 py-[11px] has-checked:border-[1.5px] has-checked:border-pdf has-checked:bg-pdf/[.035] has-focus-visible:ring-2 has-focus-visible:ring-pdf/30">
                            <input type="radio" name="report_type" value="<?= e($key) ?>" class="sr-only" data-includes="<?= e(implode(',', $includes)) ?>" <?= $key === 'full' ? 'checked' : '' ?>>
                            <span class="text-[13px] font-semibold"><?= e($label) ?></span>
                            <span class="text-[11.5px] text-muted"><?= e($hint) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="grid grid-cols-2 gap-3">
                <div class="field">
                    <label class="label" for="report-from">From</label>
                    <input class="input" id="report-from" name="date_from" type="date" value="<?= e($defaultFrom) ?>">
                </div>
                <div class="field">
                    <label class="label" for="report-to">To</label>
                    <input class="input" id="report-to" name="date_to" type="date" value="<?= e(date('Y-m-d')) ?>">
                </div>
            </div>

            <fieldset class="flex flex-col gap-2">
                <legend class="label mb-2">Include</legend>
                <div class="flex flex-wrap gap-x-5 gap-y-2">
                    <?php foreach (Report::SECTIONS as $key => $label): ?>
                        <label class="flex items-center gap-2 text-[13px]">
                            <input type="checkbox" name="sections[]" value="<?= e($key) ?>" class="size-4 accent-pdf" <?= $key !== 'notes' ? 'checked' : '' ?>><?= e($label) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <p class="hidden rounded-lg bg-pdf/[.06] px-3 py-2.5 text-[12.5px] text-pdf" data-form-error role="alert"></p>

            <div class="flex flex-col-reverse gap-3 pt-1 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-xs text-faint">A4 · usually ready in 2 to 4 seconds</div>
                <button type="submit" class="btn btn-pdf h-[42px] px-5"><?= icon('pdf', 'size-4', 1.7) ?>Generate PDF</button>
            </div>
        </form>

        <div class="flex min-w-0 grow flex-col gap-4">
            <div data-report-slot>
                <?php if ($latest): ?>
                    <?= partial('report-result', ['report' => $latest]) ?>
                <?php else: ?>
                    <div class="card"><?= partial('empty-state', ['title' => 'No report for this student yet', 'text' => 'Choose what to include and press Generate PDF. The result and a preview show up here.', 'icon' => 'pdf']) ?></div>
                <?php endif; ?>
            </div>

            <section class="card px-4 pt-3.5 pb-1 md:px-5" aria-labelledby="recent-reports-title">
                <div class="mb-0.5 flex items-baseline justify-between">
                    <h2 class="card-title" id="recent-reports-title">Recent reports</h2>
                    <div class="text-xs text-muted"><span data-report-week><?= (int) $weekCount ?></span> this week</div>
                </div>
                <ul data-report-list>
                    <?php foreach ($recent as $report): ?>
                        <?= partial('report-row', ['report' => $report]) ?>
                    <?php endforeach; ?>
                </ul>
                <?php if (!$recent): ?>
                    <p class="py-4 text-[13px] text-muted" data-report-empty>No reports yet.</p>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>

<?= partial('report-progress', ['variant' => 'staff', 'live' => $live]) ?>
