<?php
/**
 * Add / edit a record (design-ref 07 right column). Fields switch with the type (app.js);
 * without JS every field shows and the server uses the ones for the chosen type.
 * @var array $student
 * @var array|null $editing record being edited
 */
$editing ??= null;
$val = static fn (string $key, string $fromRecord = '') => old($key, $fromRecord);
$type = $val('type', $editing['type'] ?? 'grade');
$recordValue = $editing['value'] ?? '';
$action = $editing ? url('/records/' . $editing['id']) : url('/students/' . $student['id'] . '/records');

$fieldError = static function (string $name): string {
    $error = error_for($name);
    return $error ? '<p class="field-error" id="' . $name . '-error">' . e($error) . '</p>' : '';
};
$invalid = static fn (string $name): string => error_for($name) ? ' is-invalid" aria-invalid="true" aria-describedby="' . $name . '-error' : '';
$show = static fn (string ...$types): string => in_array($type, $types, true) ? '' : ' hidden';
?>
<form method="post" action="<?= e($action) ?>" class="card flex flex-col gap-3.5 p-4 md:p-5" data-record-form novalidate id="record-form">
    <?= csrf_field() ?>
    <div class="flex items-center justify-between">
        <h2 class="card-title"><?= $editing ? 'Edit record' : 'Add a record' ?></h2>
        <?php if ($editing): ?>
            <a href="<?= e(url('/students/' . $student['id'])) ?>" class="text-[12.5px] text-muted hover:text-ink">Cancel</a>
        <?php endif; ?>
    </div>

    <div class="field">
        <label class="label" for="rtype">Type</label>
        <select class="input<?= $invalid('type') ?>" id="rtype" name="type" data-record-type>
            <?php foreach (['grade' => 'Grade', 'attendance' => 'Attendance', 'library' => 'Library'] as $value => $label): ?>
                <option value="<?= $value ?>" <?= $type === $value ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
        <?= $fieldError('type') ?>
    </div>

    <div class="field"<?= $show('grade', 'library') ?> data-show-for="grade library">
        <label class="label" for="rtitle">
            <span data-show-for="grade"<?= $show('grade') ?>>Subject and period</span>
            <span data-show-for="library"<?= $show('library') ?>>Book title</span>
        </label>
        <input class="input<?= $invalid('title') ?>" id="rtitle" name="title" type="text" maxlength="150"
               value="<?= e($val('title', ($editing && $editing['type'] !== 'attendance') ? $editing['title'] : '')) ?>"
               placeholder="<?= $type === 'library' ? 'e.g. Noli Me Tangere' : 'e.g. Quarter 2 Math' ?>"
               data-placeholder-grade="e.g. Quarter 2 Math" data-placeholder-library="e.g. Noli Me Tangere">
        <?= $fieldError('title') ?>
    </div>

    <div class="field"<?= $show('grade') ?> data-show-for="grade">
        <label class="label" for="rgrade">Grade</label>
        <input class="input<?= $invalid('value_grade') ?>" id="rgrade" name="value_grade" type="number" min="0" max="100" step="0.01" inputmode="decimal"
               value="<?= e($val('value_grade', ($editing['type'] ?? '') === 'grade' ? $recordValue : '')) ?>" placeholder="0 to 100">
        <?= $fieldError('value_grade') ?>
    </div>

    <div class="field"<?= $show('attendance') ?> data-show-for="attendance">
        <label class="label" for="rattendance">Status</label>
        <select class="input<?= $invalid('value_attendance') ?>" id="rattendance" name="value_attendance">
            <?php $current = $val('value_attendance', ($editing['type'] ?? '') === 'attendance' ? $recordValue : 'Present'); ?>
            <?php foreach (['Present', 'Late', 'Absent'] as $option): ?>
                <option <?= $current === $option ? 'selected' : '' ?>><?= $option ?></option>
            <?php endforeach; ?>
        </select>
        <?= $fieldError('value_attendance') ?>
    </div>

    <div class="field"<?= $show('library') ?> data-show-for="library">
        <label class="label" for="rlibrary">Status</label>
        <select class="input<?= $invalid('value_library') ?>" id="rlibrary" name="value_library">
            <?php $current = $val('value_library', ($editing['type'] ?? '') === 'library' ? $recordValue : 'Borrowed'); ?>
            <?php foreach (['Borrowed', 'Returned', 'Overdue'] as $option): ?>
                <option <?= $current === $option ? 'selected' : '' ?>><?= $option ?></option>
            <?php endforeach; ?>
        </select>
        <?= $fieldError('value_library') ?>
    </div>

    <div class="field">
        <label class="label" for="rdate">Date</label>
        <input class="input<?= $invalid('recorded_on') ?>" id="rdate" name="recorded_on" type="date" required
               value="<?= e($val('recorded_on', $editing['recorded_on'] ?? date('Y-m-d'))) ?>">
        <?= $fieldError('recorded_on') ?>
    </div>

    <div class="field">
        <label class="label" for="rnote">Note (optional)</label>
        <textarea class="input<?= $invalid('note') ?>" id="rnote" name="note" maxlength="255" rows="3"
                  placeholder="<?= ['grade' => 'e.g. Improved on the long test.', 'attendance' => 'e.g. Arrived 7:48 AM', 'library' => 'e.g. Due Oct 12, 2026'][$type] ?>"
                  data-placeholder-grade="e.g. Improved on the long test." data-placeholder-attendance="e.g. Arrived 7:48 AM" data-placeholder-library="e.g. Due Oct 12, 2026"><?= e($val('note', $editing['note'] ?? '')) ?></textarea>
        <?= $fieldError('note') ?>
    </div>

    <?php if (!$editing):
        // Which type/value combinations fire an automatic email right now (Email Alerts page toggles).
        $on = \App\Services\EmailTemplateService::triggerStates();
        $fires = [
            'grade' => $on['grade_posted'],
            'attendance:Absent' => $on['absence_logged'],
            'attendance:Late' => $on['late_arrival'],
            'library:Overdue' => $on['book_overdue'],
        ];
        $demo = \App\Services\EmailTemplateService::isDemo();
    ?>
        <div class="flex items-start gap-2.5 rounded-lg bg-mail/[.07] px-3 py-2.5" data-auto-email='<?= e(json_encode($fires)) ?>' hidden>
            <span class="mt-px text-mail"><?= icon('bolt', 'size-4') ?></span>
            <p class="text-xs leading-normal text-mail-dark">
                Saving emails <?= e($student['first_name']) ?> and <?= e($student['first_name']) ?>'s guardian automatically<?= $demo ? ' (demo mode: logged, not sent)' : '' ?>.
            </p>
        </div>
    <?php endif; ?>

    <button type="submit" class="btn btn-primary h-[42px] w-full"><?= $editing ? 'Save changes' : 'Save record' ?></button>
</form>
