<?php
/**
 * Staff dashboard.
 * @var array $user
 * @var array $summary
 * @var array $days
 * @var array $activity
 */
$isToday = $summary['attendance_day'] === date('Y-m-d');
$presentLabel = $isToday || !$summary['attendance_day']
    ? 'Present today'
    : 'Present · ' . date('D', strtotime($summary['attendance_day'])) . ', ' . fmt_date($summary['attendance_day']);
$emails = $summary['emails'];
$emailNote = $emails['total']
    ? implode(' · ', array_filter([
        $emails['sent'] ? "{$emails['sent']} delivered" : null,
        $emails['demo'] ? "{$emails['demo']} demo" : null,
        $emails['failed'] ? "{$emails['failed']} failed" : null,
    ]))
    : 'None yet';
$activityStyle = [
    'record' => ['records', 'bg-ink/[.06] text-ink'],
    'email' => ['mail', 'bg-mail/10 text-mail'],
    'report' => ['pdf', 'bg-pdf/[.08] text-pdf'],
    'ai' => ['ai', 'bg-primary/10 text-primary'],
];
?>
<div class="flex flex-col gap-4 md:gap-[18px]">
    <div class="flex items-center gap-3 md:gap-4">
        <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-peach font-mono text-[13px] font-semibold text-lib md:size-[52px] md:text-[17px]" aria-hidden="true"><?= e(initials($user['name'])) ?></div>
        <div>
            <h1 class="text-[17px] font-semibold md:text-[22px]">Welcome back, <?= e($user['name']) ?></h1>
            <p class="mt-0.5 text-[11.5px] text-muted md:text-[13px]"><?= e($user['staff_title'] ?? 'Staff') ?> · Sections <?= e(implode(', ', $summary['sections'])) ?> · <?= e(date('l') . ', ' . fmt_date(date('Y-m-d'))) ?></p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-2.5 md:gap-4 xl:grid-cols-4">
        <?= partial('stat-card', ['label' => 'Students', 'value' => $summary['students'], 'note' => 'Grade ' . implode(' and ', $summary['sections'])]) ?>
        <?= partial('stat-card', [
            'label' => $presentLabel,
            'value' => $summary['present'],
            'suffix' => '/' . $summary['marked'],
            'note' => $summary['present_rate'] !== null ? $summary['present_rate'] . '% attendance' : 'No attendance yet',
            'noteClass' => 'text-mail',
        ]) ?>
        <?= partial('stat-card', ['label' => 'Emails sent this week', 'value' => $emails['total'], 'note' => $emailNote]) ?>
        <?= partial('stat-card', [
            'label' => 'PDF reports this week',
            'value' => $summary['reports']['total'],
            'note' => $summary['reports']['total'] ? $summary['reports']['by_portal'] . ' by students or parents' : 'None yet',
        ]) ?>
    </div>

    <div class="flex flex-col gap-4 md:gap-[18px] xl:flex-row">
        <section class="card flex min-w-0 grow flex-col gap-2.5 px-4 py-4 md:px-5 md:py-[18px]" aria-labelledby="chart-title">
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <h2 id="chart-title" class="card-title">Attendance, last <?= count($days) ?: 5 ?> school days</h2>
                <div class="text-xs text-muted">students present out of <?= (int) $summary['students'] ?></div>
            </div>
            <?php if ($days): ?>
                <?= partial('attendance-chart', ['days' => $days, 'total' => $summary['students']]) ?>
            <?php else: ?>
                <?= partial('empty-state', ['title' => 'No attendance yet', 'text' => 'Add attendance records and the chart fills in.', 'icon' => 'dashboard']) ?>
            <?php endif; ?>
        </section>

        <section class="flex shrink-0 flex-col gap-2 md:rounded-xl md:border md:border-line md:bg-white md:gap-2.5 md:px-5 md:py-[18px] xl:w-[380px]" aria-labelledby="qa-title">
            <h2 id="qa-title" class="card-title text-[13px] md:text-sm">Quick access</h2>
            <?= partial('quick-access') ?>
        </section>
    </div>

    <section class="card px-4 pt-3.5 pb-1.5 md:px-5" aria-labelledby="activity-title">
        <div class="mb-0.5 flex items-center justify-between">
            <h2 id="activity-title" class="card-title">Recent activity</h2>
            <a href="<?= e(url('/students')) ?>" class="text-[12.5px] font-medium text-primary hover:underline">All records &rarr;</a>
        </div>
        <?php if ($activity): ?>
            <ul class="grid grid-cols-[minmax(0,1fr)] lg:grid-cols-2 lg:gap-x-8">
                <?php foreach ($activity as $item): [$iconName, $iconClass] = $activityStyle[$item['kind']]; ?>
                    <li class="border-b border-line-soft last:border-b-0 lg:[&:nth-last-child(2):nth-child(odd)]:border-b-0">
                        <a href="<?= e(url($item['href'])) ?>" class="flex items-center gap-3 py-[11px] hover:text-primary">
                            <span class="flex size-[30px] shrink-0 items-center justify-center rounded-lg <?= $iconClass ?>"><?= icon($iconName, 'size-4') ?></span>
                            <span class="min-w-0 grow truncate text-[13px]"><?= e($item['text']) ?></span>
                            <span class="shrink-0 text-[11.5px] text-faint"><?= e(time_ago($item['at'])) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <?= partial('empty-state', ['title' => 'Nothing yet', 'text' => 'Records, emails, reports and AI questions show up here.', 'icon' => 'records']) ?>
        <?php endif; ?>
    </section>
</div>
