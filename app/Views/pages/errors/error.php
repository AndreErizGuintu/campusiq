<?php
/**
 * Error page for 403 / 404 / 419 / 500.
 * @var int $status
 * @var string $title
 * @var string $message
 * @var string|null $detail only in APP_ENV=local
 */
use App\Core\Auth;

$home = Auth::check() ? Auth::home() : '/';
?>
<main class="flex min-h-screen flex-col items-center justify-center bg-page px-4 py-10">
    <a href="<?= e(url('/')) ?>" class="mb-8"><?= partial('logo') ?></a>
    <div class="card w-full max-w-md p-8 text-center">
        <div class="font-mono text-5xl font-semibold text-primary"><?= (int) $status ?></div>
        <h1 class="mt-3 text-xl font-semibold"><?= e($title) ?></h1>
        <p class="mt-2 text-sm leading-relaxed text-muted"><?= e($message) ?></p>
        <?php if (!empty($detail)): ?>
            <pre class="mt-4 overflow-x-auto rounded-lg bg-page p-3 text-left text-xs whitespace-pre-wrap text-pdf"><?= e($detail) ?></pre>
        <?php endif; ?>
        <div class="mt-6 flex justify-center gap-2.5">
            <a href="<?= e(url($home)) ?>" class="btn btn-primary"><?= Auth::check() ? 'Back to ' . (Auth::is('staff') ? 'dashboard' : 'my records') : 'Go to home' ?></a>
            <?php if (!Auth::check()): ?>
                <a href="<?= e(url('/login')) ?>" class="btn btn-ghost">Log in</a>
            <?php endif; ?>
        </div>
    </div>
</main>
