<?php
/**
 * Email Alerts.
 * @var array $students
 * @var int $selected
 * @var string $template
 * @var int|null $reportId
 * @var string $to
 * @var array $triggers key => bool
 * @var array $recent
 * @var array|null $last
 * @var int $weekCount
 */
use App\Models\Student;
use App\Services\EmailTemplateService as Templates;
?>
<div class="flex flex-col gap-4">
    <p class="text-[13px] text-muted">Emails go out automatically when a record is saved. You can also send one yourself here.</p>

    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:gap-[18px]">
        <div class="flex flex-col gap-4 xl:w-[610px] xl:shrink-0">
            <form class="card flex flex-col gap-[13px] px-4 py-4 md:px-5 md:py-[18px]" data-email-form data-no-lock novalidate
                  data-report="<?= (int) $reportId ?>" data-to="<?= e($to) ?>">
                <h2 class="card-title">Send a notification</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="field">
                        <label class="label" for="email-student">Student</label>
                        <select class="input" id="email-student" name="student_id">
                            <?php foreach ($students as $s): ?>
                                <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $selected ? 'selected' : '' ?>><?= e(Student::fullName($s)) ?> · <?= e($s['student_no']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="label" for="email-template">Template</label>
                        <select class="input" id="email-template" name="template">
                            <?php foreach (Templates::TEMPLATES as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $key === $template ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <div class="label" id="recipients-label">Recipients</div>
                    <div class="flex min-h-8 flex-wrap gap-2" data-recipients aria-labelledby="recipients-label">
                        <span class="text-[12.5px] text-faint">Loading…</span>
                    </div>
                </div>

                <div class="field">
                    <label class="label" for="email-subject">Subject</label>
                    <input class="input" id="email-subject" name="subject" type="text" maxlength="200" required>
                </div>

                <div class="field">
                    <label class="label" for="email-message">Preview</label>
                    <textarea class="input min-h-[96px] bg-[#fafbfd] leading-relaxed text-[#2b2f3e] read-only:cursor-default" id="email-message" name="message" maxlength="5000" rows="4" readonly></textarea>
                    <p class="text-xs text-faint" data-message-hint>Built from the student's latest matching record. Choose "Custom message" to write your own.</p>
                </div>

                <p class="hidden rounded-lg bg-pdf/[.06] px-3 py-2.5 text-[12.5px] text-pdf" data-compose-error role="alert"></p>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-xs text-faint">Goes to the student and guardian emails on file</div>
                    <button type="submit" class="btn btn-mail h-[42px] px-5" data-send-button disabled><?= icon('send', 'size-4', 1.7) ?>Send email</button>
                </div>
            </form>

            <section class="card flex flex-col gap-2.5 px-4 py-4 md:px-5" aria-labelledby="triggers-title">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="card-title" id="triggers-title">Automatic triggers</h2>
                    <div class="text-xs text-muted">send when a record is saved</div>
                </div>
                <div class="grid gap-2 sm:grid-cols-2" data-triggers>
                    <?php foreach (Templates::TRIGGERS as $key => $label): ?>
                        <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-line-soft px-3 py-2.5 text-[13px] hover:border-line">
                            <input class="switch" type="checkbox" name="<?= e($key) ?>" <?= $triggers[$key] ? 'checked' : '' ?>>
                            <?= e($label) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <div class="flex min-w-0 grow flex-col gap-4">
            <?= partial('email-last', ['log' => $last]) ?>

            <section class="card px-4 pt-3.5 pb-1 md:px-5" aria-labelledby="recent-title">
                <div class="mb-1 flex items-baseline justify-between">
                    <h2 class="card-title" id="recent-title">Recent emails</h2>
                    <div class="text-xs text-muted"><span data-week-count><?= (int) $weekCount ?></span> this week</div>
                </div>
                <div class="hidden grid-cols-[minmax(0,1fr)_90px_80px] gap-x-3 py-2 text-[11px] font-semibold tracking-[.05em] text-faint uppercase sm:grid">
                    <div>To · trigger</div><div>Sent</div><div>Status</div>
                </div>
                <ul data-email-list>
                    <?php foreach ($recent as $log): ?>
                        <?= partial('email-row', ['log' => $log]) ?>
                    <?php endforeach; ?>
                </ul>
                <?php if (!$recent): ?>
                    <div data-email-empty><?= partial('empty-state', ['title' => 'No emails yet', 'text' => 'They show up here as soon as one is sent.', 'icon' => 'mail']) ?></div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>
