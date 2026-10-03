<?php
/**
 * Log in (design-ref 02).
 * @var string $tab
 */
use App\Core\Env;

$loginError = error_for('login');
$passwordError = error_for('password');
$showDemo = Env::get('APP_ENV') === 'local';
?>
<div class="flex min-h-screen flex-col bg-page md:flex-row">
    <?= partial('auth-aside', ['tab' => $tab]) ?>

    <div class="flex grow items-start justify-center px-4 py-8 md:items-center md:px-8">
        <div class="flex w-full max-w-[400px] flex-col gap-6">
            <?= partial('auth-tabs', ['tab' => $tab]) ?>

            <div>
                <h1 class="mb-1.5 text-2xl font-semibold">Welcome back</h1>
                <p class="text-[13.5px] text-[#5b6072]">Use the ID your school gave you, or your email.</p>
            </div>

            <form method="post" action="<?= e(url('/login')) ?>" class="flex flex-col gap-3.5" novalidate data-login-form>
                <?= csrf_field() ?>
                <div class="field">
                    <label class="label" for="login">ID number or email</label>
                    <input class="input h-11 text-sm<?= $loginError ? ' is-invalid' : '' ?>" id="login" name="login" type="text" autocomplete="username" required
                           value="<?= e(old('login')) ?>" placeholder="e.g. T-0012" <?= $loginError ? 'aria-invalid="true" aria-describedby="login-error"' : '' ?> autofocus>
                    <?php if ($loginError): ?><p class="field-error" id="login-error"><?= e($loginError) ?></p><?php endif; ?>
                </div>
                <div class="field">
                    <label class="label" for="password">Password</label>
                    <input class="input h-11 text-sm<?= $passwordError ? ' is-invalid' : '' ?>" id="password" name="password" type="password" autocomplete="current-password" required
                           <?= $passwordError ? 'aria-invalid="true" aria-describedby="password-error"' : '' ?>>
                    <?php if ($passwordError): ?><p class="field-error" id="password-error"><?= e($passwordError) ?></p><?php endif; ?>
                </div>
                <label class="flex items-center gap-2 text-[12.5px] text-[#5b6072]">
                    <input type="checkbox" name="remember" value="1" class="size-4 accent-primary"> Remember me for 7 days
                </label>
                <button type="submit" class="btn btn-primary btn-lg mt-2.5 w-full">Log in</button>
            </form>

            <?php if ($showDemo): ?>
                <div class="flex items-center gap-3 text-xs text-faint"><span class="h-px grow bg-line"></span>demo shortcuts<span class="h-px grow bg-line"></span></div>
                <div class="grid grid-cols-3 gap-2.5">
                    <?php foreach ([['T-0012', 'staff'], ['10-24031', 'student'], ['P-10-24031', 'parent']] as [$id, $label]): ?>
                        <button type="button" class="btn btn-ghost h-10 px-2 text-[13px] font-normal" data-demo-login="<?= e($id) ?>" data-demo-password="password123">Enter as <?= e($label) ?></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <p class="text-center text-[13px] text-[#5b6072]">No account yet? <a href="<?= e(url('/signup')) ?>" class="font-medium text-primary hover:underline">Sign up</a></p>
        </div>
    </div>
</div>
