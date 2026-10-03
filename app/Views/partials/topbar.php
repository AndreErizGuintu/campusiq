<?php
/**
 * Top bar for staff and portal pages.
 * @var array $topbar title, tag, tagClass, crumbs [[label, href|null]], search (bool), logoutButton (bool), demo (string[] env keys)
 * @var array $user
 * @var array|null $student linked student (portal)
 */
$topbar ??= [];
$isStaff = $user['role'] === 'staff';
$subtitle = match ($user['role']) {
    'staff' => $user['staff_title'] ?? 'Staff',
    'parent' => 'Parent' . (!empty($student) ? ' of ' . $student['first_name'] . ' ' . $student['last_name'] : ''),
    default => !empty($student) ? 'Grade ' . $student['section'] . ' · ' . $student['student_no'] : 'Student',
};
?>
<header class="sticky top-0 z-20 flex h-14 shrink-0 items-center justify-between gap-3 border-b border-line bg-white px-4 md:h-16 md:px-8">
    <div class="flex min-w-0 grow items-center gap-2.5">
        <a href="<?= e(url($isStaff ? '/dashboard' : '/my/records')) ?>" class="shrink-0 md:hidden" aria-label="CampusIQ home">
            <span class="flex size-7 items-center justify-center rounded-[7px] bg-primary font-mono text-xs font-semibold text-white">CQ</span>
        </a>

        <?php if (!empty($topbar['search'])): ?>
            <form action="<?= e(url('/students')) ?>" method="get" role="search" class="flex h-[38px] w-full max-w-[360px] items-center gap-2 rounded-lg border border-line bg-page px-3 text-faint focus-within:border-primary">
                <?= icon('search', 'size-4 shrink-0') ?>
                <label for="topbar-search" class="sr-only">Search students</label>
                <input id="topbar-search" name="q" type="search" placeholder="Search students, IDs, sections…" class="w-full min-w-0 bg-transparent text-[13px] text-ink outline-none placeholder:text-faint">
            </form>
        <?php elseif (!empty($topbar['crumbs'])): ?>
            <nav aria-label="Breadcrumb" class="truncate text-[13px] text-muted">
                <?php foreach ($topbar['crumbs'] as $i => [$label, $href]): ?>
                    <?php if ($i > 0): ?><span class="mx-1 text-[#c3c7d4]">/</span><?php endif; ?>
                    <?php if ($href): ?>
                        <a href="<?= e(url($href)) ?>" class="hover:text-ink"><?= e($label) ?></a>
                    <?php else: ?>
                        <span class="font-medium text-ink" aria-current="page"><?= e($label) ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
        <?php elseif (!empty($topbar['title'])): ?>
            <h1 class="truncate font-mono text-[15px] font-semibold md:text-[17px]"><?= e($topbar['title']) ?></h1>
            <?php if (!empty($topbar['tag'])): ?>
                <span class="hidden shrink-0 rounded-md px-2 py-[3px] font-mono text-[11px] font-semibold sm:inline <?= e($topbar['tagClass'] ?? 'bg-primary/10 text-primary') ?>"><?= e($topbar['tag']) ?></span>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="flex shrink-0 items-center gap-3">
        <?php foreach ($topbar['demo'] ?? [] as $key): ?>
            <?= partial('demo-badge', ['key' => $key]) ?>
        <?php endforeach; ?>

        <?php if (!empty($topbar['logoutButton'])): ?>
            <form method="post" action="<?= e(url('/logout')) ?>" class="hidden md:block">
                <?= csrf_field() ?>
                <button type="submit" class="inline-flex h-9 items-center gap-[7px] rounded-lg border border-line px-3.5 text-[13px] text-body hover:border-ink/30"><?= icon('logout', 'size-4') ?>Log out</button>
            </form>
        <?php else: ?>
            <div class="hidden text-right lg:block">
                <div class="text-[13px] font-medium"><?= e($user['name']) ?></div>
                <div class="text-[11.5px] text-muted"><?= e($subtitle) ?></div>
            </div>
            <div class="avatar hidden sm:flex <?= $isStaff ? 'bg-peach text-lib' : 'bg-lilac text-primary' ?>" aria-hidden="true"><?= e(initials($user['name'])) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('/logout')) ?>" class="md:hidden">
            <?= csrf_field() ?>
            <button type="submit" class="flex size-10 items-center justify-center rounded-lg text-body hover:bg-page" aria-label="Log out"><?= icon('logout', 'size-5') ?></button>
        </form>
    </div>
</header>
