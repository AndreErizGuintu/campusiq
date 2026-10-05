<?php
/**
 * "Last email delivered" status card (right column of Email Alerts).
 * @var array|null $log
 */
use App\Services\EmailTemplateService;

if (!$log) {
    echo '<div class="card px-5 py-[18px]" data-email-last>' . partial('empty-state', ['title' => 'No emails yet', 'text' => 'Save a record or send one from the form, and its delivery shows up here.', 'icon' => 'mail']) . '</div>';
    return;
}
$count = count(array_filter(array_map('trim', explode(',', $log['recipients']))));
$state = [
    'sent' => ['Last email delivered', 'border-mail/45', 'bg-mail/[.12] text-mail', 'check', "Delivered to {$count} " . ($count === 1 ? 'recipient' : 'recipients')],
    'demo' => ['Last email (demo mode)', 'border-[#b45309]/35', 'bg-[#b45309]/10 text-lib', 'mail', 'Saved to the log, not delivered'],
    'failed' => ['Last email failed', 'border-pdf/40', 'bg-pdf/10 text-pdf', 'alert', 'Not delivered: ' . ($log['error'] ?: 'unknown error')],
][$log['status']];
$steps = [
    ['Message filled in from the record', true],
    ['Sent to the email service', $log['status'] !== 'demo'],
    [$state[4], $log['status'] === 'sent'],
];
?>
<div class="card flex flex-col gap-3 px-4 py-4 md:px-5 md:py-[18px] <?= $state[1] ?>" data-email-last>
    <div class="flex items-center gap-3">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-[10px] <?= $state[2] ?>"><?= icon($state[3], 'size-[18px]', 2) ?></span>
        <div class="min-w-0">
            <div class="text-sm font-semibold"><?= e($state[0]) ?></div>
            <div class="truncate text-xs text-muted"><?= e(EmailTemplateService::label($log['trigger_key'])) ?> · <?= e($log['first_name'] . ' ' . $log['last_name']) ?> · <?= e(strtolower(time_ago($log['updated_at'] ?? $log['created_at']))) ?></div>
        </div>
    </div>
    <ol class="flex flex-col gap-2 rounded-lg bg-[#f7f8fb] px-3 py-2.5">
        <?php foreach ($steps as [$text, $done]): ?>
            <li class="flex items-center gap-2.5 text-[12.5px] text-body">
                <span class="flex size-[18px] shrink-0 items-center justify-center rounded-full <?= $done ? 'bg-mail text-white' : 'border-[1.5px] border-[#d3d7e2]' ?>"><?= $done ? icon('check', 'size-2.5', 3) : '' ?></span>
                <span class="min-w-0 break-words"><?= e($text) ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
</div>
