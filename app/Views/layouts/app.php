<?php
/**
 * Staff layout: sidebar (desktop) or bottom tab bar (mobile), top bar, content.
 * @var string $content
 * @var array $user
 * @var string|null $title
 * @var array|null $topbar
 * @var string[]|null $scripts extra JS files from public/assets/js
 * @var array|null $pendingEmail
 */
?>
<!doctype html>
<html lang="en">
<head>
    <?= partial('head', ['title' => $title ?? 'CampusIQ']) ?>
</head>
<body>
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-white focus:px-3 focus:py-2">Skip to content</a>
    <div class="flex min-h-screen">
        <?= partial('sidebar', ['area' => 'staff']) ?>
        <div class="has-tabbar flex min-w-0 flex-1 flex-col">
            <?= partial('topbar', ['topbar' => $topbar ?? [], 'user' => $user]) ?>
            <main id="main" class="flex-1 px-4 py-5 md:px-8 md:py-6">
                <?= $content ?>
            </main>
        </div>
    </div>
    <?= partial('tabbar', ['area' => 'staff']) ?>
    <?= partial('flash') ?>
    <?= partial('config', ['user' => $user, 'pendingEmail' => $pendingEmail ?? null]) ?>
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
    <?php if (in_array('email.js', $scripts ?? [], true) && !App\Services\EmailTemplateService::isDemo()): ?>
        <script src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js" defer></script>
    <?php endif; ?>
    <?php foreach ($scripts ?? [] as $script): ?>
        <script src="<?= e(asset('js/' . $script)) ?>" defer></script>
    <?php endforeach; ?>
</body>
</html>
