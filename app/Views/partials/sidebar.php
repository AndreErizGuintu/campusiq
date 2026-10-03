<?php
/**
 * Desktop sidebar. Staff and portal share the look; the links differ.
 * @var string $area 'staff' | 'portal'
 * @var int|null $unread unread notifications (portal)
 */
$link = static function (string $href, string $iconName, string $label, string $activePrefix, string $extra = ''): string {
    $active = nav_active($activePrefix);
    return '<a class="nav-link' . ($active ? ' is-active' : '') . '" href="' . e(url($href)) . '"'
        . ($active ? ' aria-current="page"' : '') . '>' . icon($iconName) . '<span>' . e($label) . '</span>' . $extra . '</a>';
};
?>
<nav class="sticky top-0 hidden h-screen w-[248px] shrink-0 flex-col gap-0.5 overflow-y-auto bg-side px-3.5 py-5 md:flex" aria-label="Main">
    <a href="<?= e(url('/')) ?>" class="px-2.5 pt-1.5 pb-5 text-white"><?= partial('logo', ['variant' => 'dark']) ?></a>

    <?php if ($area === 'staff'): ?>
        <div class="nav-label">Main</div>
        <?= $link('/', 'home', 'Home', '/') ?>
        <?= $link('/dashboard', 'dashboard', 'Dashboard', '/dashboard') ?>
        <?= $link('/students', 'students', 'Students & records', '/students') ?>
        <div class="nav-label">APIs</div>
        <?= $link('/ai', 'ai', 'AI Assistant', '/ai', '<span class="nav-api">API 1</span>') ?>
        <?= $link('/emails', 'mail', 'Email Alerts', '/emails', '<span class="nav-api">API 2</span>') ?>
        <?= $link('/reports', 'pdf', 'PDF Reports', '/reports', '<span class="nav-api">API 3</span>') ?>
    <?php else: ?>
        <div class="nav-label"><?= e(auth()['role'] === 'parent' ? 'Parent' : 'Student') ?></div>
        <?= $link('/my/records', 'records', 'My records', '/my/records') ?>
        <?= $link('/my/reports', 'pdf', 'Reports', '/my/reports') ?>
        <?= $link('/my/notifications', 'mail', 'Notifications', '/my/notifications',
            !empty($unread) ? '<span class="ml-auto rounded-full bg-primary px-[7px] py-px text-[11px] font-semibold text-white">' . (int) $unread . '</span>' : '') ?>
    <?php endif; ?>

    <div class="grow"></div>
    <form method="post" action="<?= e(url('/logout')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="nav-link w-full"><?= icon('logout') ?><span>Log out</span></button>
    </form>
</nav>
