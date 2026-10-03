<?php /** @var string $key the missing .env key */ ?>
<span class="inline-flex max-w-[320px] items-center gap-1.5 rounded-full border border-[#b45309]/25 bg-[#b45309]/10 px-2.5 py-1 text-[11.5px] font-medium text-lib" title="Demo mode: add <?= e($key) ?> to .env to go live">
    <span class="size-1.5 shrink-0 rounded-full bg-lib"></span>
    <span class="truncate"><span class="hidden sm:inline">Demo mode: add </span><?= e($key) ?><span class="hidden sm:inline"> to .env</span></span>
</span>
