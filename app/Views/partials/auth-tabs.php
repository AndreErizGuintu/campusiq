<?php /** @var string $tab 'login' | 'signup' */ ?>
<nav class="grid grid-cols-2 gap-1 rounded-[10px] bg-[#e9ebf2] p-1" aria-label="Log in or sign up">
    <?php foreach (['login' => ['/login', 'Log in'], 'signup' => ['/signup', 'Sign up']] as $key => [$href, $label]): ?>
        <a href="<?= e(url($href)) ?>"
           class="flex h-9 items-center justify-center rounded-lg text-[13px] <?= $tab === $key ? 'bg-white font-medium text-ink shadow-[0_1px_2px_rgba(20,22,40,.08)]' : 'text-[#5b6072] hover:text-ink' ?>"
           <?= $tab === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
