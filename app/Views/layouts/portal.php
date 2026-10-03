<?php
/**
 * Student / parent layout.
 * @var string $content
 * @var array $user
 * @var array $student the linked student
 * @var int|null $unread unread notifications
 * @var string|null $title
 * @var array|null $topbar
 * @var string[]|null $scripts
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
        <?= partial('sidebar', ['area' => 'portal', 'unread' => $unread ?? 0]) ?>
        <div class="has-tabbar flex min-w-0 flex-1 flex-col">
            <?= partial('topbar', ['topbar' => $topbar ?? [], 'user' => $user, 'student' => $student]) ?>
            <main id="main" class="flex-1 px-4 py-5 md:px-8 md:py-7">
                <?= $content ?>
            </main>
        </div>
    </div>
    <?= partial('tabbar', ['area' => 'portal', 'unread' => $unread ?? 0]) ?>
    <?= partial('flash') ?>
    <?= partial('config', ['user' => $user]) ?>
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
    <?php foreach ($scripts ?? [] as $script): ?>
        <script src="<?= e(asset('js/' . $script)) ?>" defer></script>
    <?php endforeach; ?>
</body>
</html>
