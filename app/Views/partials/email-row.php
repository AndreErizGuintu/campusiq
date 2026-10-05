<?php
/**
 * One row in "Recent emails".
 * @var array $log email_logs row joined with student fields
 */
use App\Models\EmailLog;
use App\Services\EmailTemplateService;

$pill = ['sent' => ['pill-ok', 'Delivered'], 'failed' => ['pill-bad', 'Failed'], 'demo' => ['pill-demo', 'Demo']][$log['status']];
$sentAt = strtotime($log['updated_at'] ?? $log['created_at']);
$when = date('Y-m-d', $sentAt) === date('Y-m-d') ? (time() - $sentAt < 120 ? 'Just now' : date('g:i A', $sentAt)) : fmt_date(date('Y-m-d', $sentAt));
?>
<li class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3 border-b border-line-soft py-[11px] text-[13px] last:border-b-0 sm:grid-cols-[minmax(0,1fr)_90px_80px]" data-email-row="<?= (int) $log['id'] ?>">
    <div class="min-w-0">
        <div class="truncate"><?= e(EmailLog::audience($log)) ?></div>
        <div class="truncate text-[11.5px] text-faint" title="<?= e($log['subject']) ?>">
            <?= e(EmailTemplateService::label($log['trigger_key'])) ?>
            <?php if ($log['status'] === 'failed'): ?>
                · <button type="button" class="font-medium text-primary hover:underline" data-retry="<?= (int) $log['id'] ?>">Retry</button>
            <?php endif; ?>
        </div>
    </div>
    <div class="hidden text-muted sm:block"><?= e($when) ?></div>
    <span class="pill <?= $pill[0] ?> h-[22px] text-[11.5px]" title="<?= e($log['error'] ?? '') ?>"><?= $pill[1] ?></span>
</li>
