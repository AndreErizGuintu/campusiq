<?php
/**
 * Sign up for students and parents (design-ref 02, Sign up tab).
 * @var string $tab
 */
$role = old('role', 'student');
$field = static function (string $name, string $label, string $type, string $autocomplete, string $placeholder = '', string $hint = ''): string {
    $error = error_for($name);
    $describedBy = trim(($hint ? "{$name}-hint " : '') . ($error ? "{$name}-error" : ''));
    $value = in_array($type, ['password'], true) ? '' : old($name);
    return '<div class="field">'
        . '<label class="label" for="' . $name . '">' . e($label) . '</label>'
        . '<input class="input h-11 text-sm' . ($error ? ' is-invalid' : '') . '" id="' . $name . '" name="' . $name . '" type="' . $type . '"'
        . ' autocomplete="' . $autocomplete . '" required value="' . e($value) . '" placeholder="' . e($placeholder) . '"'
        . ($describedBy ? ' aria-describedby="' . $describedBy . '"' : '') . ($error ? ' aria-invalid="true"' : '') . '>'
        . ($hint ? '<p class="text-xs text-faint" id="' . $name . '-hint" data-hint="' . $name . '">' . e($hint) . '</p>' : '')
        . ($error ? '<p class="field-error" id="' . $name . '-error">' . e($error) . '</p>' : '')
        . '</div>';
};
?>
<div class="flex min-h-screen flex-col bg-page md:flex-row">
    <?= partial('auth-aside', ['tab' => $tab]) ?>

    <div class="flex grow items-start justify-center px-4 py-8 md:items-center md:px-8">
        <div class="flex w-full max-w-[400px] flex-col gap-6">
            <?= partial('auth-tabs', ['tab' => $tab]) ?>

            <div>
                <h1 class="mb-1.5 text-2xl font-semibold">Create your account</h1>
                <p class="text-[13.5px] text-[#5b6072]">For students and parents. Staff accounts are set up by the school.</p>
            </div>

            <form method="post" action="<?= e(url('/signup')) ?>" class="flex flex-col gap-3.5" novalidate data-signup-form>
                <?= csrf_field() ?>
                <fieldset class="flex flex-col gap-1.5">
                    <legend class="label mb-1.5">I am a</legend>
                    <div class="grid grid-cols-2 gap-2">
                        <?php foreach (['student' => ['Student', 'Use your school email'], 'parent' => ['Parent or guardian', 'Use the guardian email']] as $value => [$label, $hint]): ?>
                            <label class="flex cursor-pointer flex-col gap-0.5 rounded-[9px] border border-line bg-white px-3 py-2.5 has-checked:border-[1.5px] has-checked:border-primary has-checked:bg-primary/[.035]">
                                <span class="flex items-center gap-2 text-[13px] font-semibold">
                                    <input type="radio" name="role" value="<?= $value ?>" class="accent-primary" <?= $role === $value ? 'checked' : '' ?>><?= e($label) ?>
                                </span>
                                <span class="pl-5 text-[11.5px] text-muted"><?= e($hint) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($error = error_for('role')): ?><p class="field-error"><?= e($error) ?></p><?php endif; ?>
                </fieldset>

                <?= $field('student_no', 'Student number', 'text', 'off', 'e.g. 10-24031') ?>
                <?= $field('email', 'Email on file with the school', 'email', 'email', 'name@example.com', $role === 'parent'
                    ? 'The guardian email the school has for this student.'
                    : 'The student email the school has for you.') ?>
                <?= $field('password', 'Password', 'password', 'new-password', '', 'At least 8 characters.') ?>
                <?= $field('password_confirmation', 'Confirm password', 'password', 'new-password') ?>

                <button type="submit" class="btn btn-primary btn-lg mt-2.5 w-full">Create account</button>
            </form>

            <p class="text-center text-[13px] text-[#5b6072]">Already have an account? <a href="<?= e(url('/login')) ?>" class="font-medium text-primary hover:underline">Log in</a></p>
        </div>
    </div>
</div>
