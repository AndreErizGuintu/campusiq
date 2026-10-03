<?php
/**
 * Server-rendered SVG bar chart: students present on the last school days (design-ref 03).
 * One series, so no legend: the card title names it. Each bar has a <title> tooltip,
 * and a visually hidden table carries the same numbers for screen readers.
 *
 * @var array $days [['date' => 'Y-m-d', 'present' => int, 'late' => int, 'marked' => int], ...]
 * @var int $total students on roll (y axis max)
 */
$width = 560;
$height = 190;
$left = 40;
$right = 550;
$base = 160;
$top = 26;
$barWidth = 40;
$radius = 4;

$max = max(1, $total, ...array_column($days, 'present'));
$step = 1;
foreach ([1, 2, 5, 10, 20, 50, 100] as $candidate) {
    $step = $candidate;
    if ($max / $candidate <= 4) {
        break;
    }
}
$y = static fn (float $value): float => $base - ($value / $max) * ($base - $top);
$slot = ($right - $left) / max(1, count($days));

$summary = implode(', ', array_map(
    static fn ($d) => date('D j', strtotime($d['date'])) . ' ' . $d['present'],
    $days
));
?>
<figure class="m-0">
    <svg viewBox="0 0 <?= $width ?> <?= $height ?>" class="block h-auto w-full" role="img"
         aria-label="Bar chart of students present out of <?= (int) $total ?>: <?= e($summary) ?>.">
        <?php for ($tick = 0; $tick <= $max; $tick += $step): $ty = round($y($tick), 1); ?>
            <line x1="<?= $left ?>" y1="<?= $ty ?>" x2="<?= $right ?>" y2="<?= $ty ?>" stroke="<?= $tick === 0 ? '#d3d7e2' : '#eef0f5' ?>" stroke-width="1"/>
            <text x="30" y="<?= $ty + 4 ?>" text-anchor="end" class="fill-faint font-sans text-[18px] sm:text-[14px] xl:text-[11px]"><?= $tick ?></text>
        <?php endfor; ?>

        <?php foreach ($days as $i => $day):
            $cx = $left + $slot * ($i + 0.5);
            $x = $cx - $barWidth / 2;
            $barTop = $y($day['present']);
            $r = min($radius, max(0, $base - $barTop));
            $label = date('D j', strtotime($day['date']));
            $path = sprintf(
                'M%.1f,%d V%.1f Q%.1f,%.1f %.1f,%.1f H%.1f Q%.1f,%.1f %.1f,%.1f V%d Z',
                $x, $base, $barTop + $r, $x, $barTop, $x + $r, $barTop,
                $x + $barWidth - $r, $x + $barWidth, $barTop, $x + $barWidth, $barTop + $r, $base
            );
        ?>
            <g class="group">
                <title><?= e(fmt_date($day['date'])) ?>: <?= $day['present'] ?> of <?= $day['marked'] ?> present<?= $day['late'] ? " ({$day['late']} late)" : '' ?></title>
                <rect x="<?= round($cx - $slot / 2, 1) ?>" y="<?= $top ?>" width="<?= round($slot, 1) ?>" height="<?= $base - $top ?>" fill="transparent"/>
                <path d="<?= $path ?>" class="fill-primary transition-opacity group-hover:opacity-80"/>
                <text x="<?= round($cx, 1) ?>" y="<?= round($barTop - 6, 1) ?>" text-anchor="middle" class="fill-body font-mono text-[18px] font-semibold sm:text-[14px] xl:text-[11px]"><?= $day['present'] ?></text>
                <text x="<?= round($cx, 1) ?>" y="180" text-anchor="middle" class="fill-faint font-sans text-[18px] sm:text-[14px] xl:text-[11px]"><?= e($label) ?></text>
            </g>
        <?php endforeach; ?>
    </svg>
    <table class="sr-only">
        <caption>Students present per school day</caption>
        <thead><tr><th scope="col">Day</th><th scope="col">Present</th><th scope="col">Late</th><th scope="col">Marked</th></tr></thead>
        <tbody>
            <?php foreach ($days as $day): ?>
                <tr><th scope="row"><?= e(fmt_date($day['date'], true)) ?></th><td><?= $day['present'] ?></td><td><?= $day['late'] ?></td><td><?= $day['marked'] ?></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</figure>
