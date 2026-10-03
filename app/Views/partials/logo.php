<?php
/**
 * CQ mark + name.
 * @var string|null $variant 'dark' (white text, on sidebar/footer), 'light' (on white), 'hero' (white mark on indigo)
 * @var string|null $size 'sm' | 'md'
 */
$variant ??= 'light';
$size ??= 'md';
$mark = match ($variant) {
    'dark' => 'bg-[#6366f1] text-white',
    'hero' => 'bg-white text-hero',
    default => 'bg-primary text-white',
};
$box = $size === 'sm' ? 'size-7 rounded-[7px] text-xs' : 'size-[30px] rounded-lg text-[13px]';
$text = $size === 'sm' ? 'text-[15px]' : 'text-[15px] md:text-base';
?>
<span class="flex items-center gap-2.5">
    <span class="flex <?= $box ?> items-center justify-center font-mono font-semibold <?= $mark ?>">CQ</span>
    <span class="font-mono <?= $text ?> font-semibold">CampusIQ</span>
</span>
