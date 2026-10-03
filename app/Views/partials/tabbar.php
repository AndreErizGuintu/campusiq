<?php
/**
 * Mobile bottom tab bar (under 768px), replaces the sidebar.
 * @var string $area 'staff' | 'portal'
 * @var int|null $unread
 */
$tabs = $area === 'staff'
    ? [['/dashboard', 'dashboard', 'Dashboard'], ['/students', 'students', 'Students'], ['/ai', 'ai', 'AI'], ['/emails', 'mail', 'Email'], ['/reports', 'pdf', 'PDF']]
    : [['/my/records', 'records', 'Records'], ['/my/reports', 'pdf', 'Reports'], ['/my/notifications', 'mail', 'Inbox']];
?>
<nav class="fixed inset-x-0 bottom-0 z-30 flex border-t border-line bg-white px-1 pb-[env(safe-area-inset-bottom)] md:hidden" aria-label="Main">
    <?php foreach ($tabs as [$href, $iconName, $label]): $active = nav_active($href); ?>
        <a class="tab-link relative<?= $active ? ' is-active' : '' ?>" href="<?= e(url($href)) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
            <?= icon($iconName, 'size-5') ?>
            <span><?= e($label) ?></span>
            <?php if ($href === '/my/notifications' && !empty($unread)): ?>
                <span class="absolute top-1.5 left-1/2 ml-2 rounded-full bg-primary px-1.5 text-[10px] leading-4 font-semibold text-white"><?= (int) $unread ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</nav>
