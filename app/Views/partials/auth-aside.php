<?php
/**
 * Left indigo panel on the login / sign up screens. Collapses to a slim header on mobile.
 * @var string $tab 'login' | 'signup'
 */
?>
<aside class="dots flex shrink-0 flex-col justify-between bg-hero p-5 text-white md:w-[42%] md:max-w-[560px] md:p-14">
    <a href="<?= e(url('/')) ?>" class="self-start"><?= partial('logo', ['variant' => 'hero']) ?></a>
    <div class="hidden flex-col gap-[18px] md:flex">
        <div class="font-mono text-[30px] leading-tight font-semibold">Your records, your questions, your reports.</div>
        <p class="max-w-[400px] text-[14.5px] leading-relaxed text-white/75">
            <?php if ($tab === 'signup'): ?>
                Students and parents can create an account with the student number and the email the school has on file.
            <?php else: ?>
                Log in to reach the dashboard and the three tools: AI Assistant, Email Alerts and PDF Reports.
            <?php endif; ?>
        </p>
    </div>
    <a href="<?= e(url('/')) ?>" class="hidden text-[13px] text-white/70 hover:text-white md:block">&larr; Back to home</a>
</aside>
