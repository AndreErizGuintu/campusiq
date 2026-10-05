<?php
/**
 * Notifications inbox from email_logs. On mobile the list and the message are separate views.
 * @var array $student
 * @var array $emails
 * @var array|null $selected
 * @var array|null $record the record the selected email is about
 * @var int[] $unreadIds
 * @var bool $explicit a message was picked (?id=)
 * @var bool $isParent
 */
use App\Models\Record;
use App\Services\EmailTemplateService;

$recipients = static function (array $email) use ($student): string {
    $list = array_map('trim', explode(',', $email['recipients']));
    return implode(', ', array_map(
        static fn ($r) => strcasecmp($r, $student['guardian_email']) === 0 ? $r . ' (guardian)' : $r,
        $list
    ));
};
$preview = static fn (string $message): string => mb_strimwidth(preg_replace('/\s+/', ' ', $message), 0, 110, '…');
?>
<div class="flex flex-col gap-4 md:gap-5">
    <div class="<?= $explicit ? 'hidden md:block' : '' ?>">
        <h1 class="text-xl font-semibold md:text-[22px]">Notifications</h1>
        <p class="mt-1 text-[13px] text-muted">Every email CampusIQ sent you<?= $isParent ? ' about ' . e($student['first_name']) : '' ?>. They go out on their own when staff change the record.</p>
    </div>

    <?php if (!$emails): ?>
        <div class="card"><?= partial('empty-state', ['title' => 'No emails yet', 'text' => 'When staff post a grade, log an absence or a book is overdue, the email shows up here too.', 'icon' => 'mail']) ?></div>
    <?php else: ?>
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:gap-5">
            <nav class="card shrink-0 overflow-hidden md:w-[380px] lg:w-[440px] <?= $explicit ? 'hidden md:block' : '' ?>" aria-label="Emails">
                <ul>
                    <?php foreach ($emails as $email):
                        $isSel = $selected && (int) $selected['id'] === (int) $email['id'];
                        $isUnread = in_array((int) $email['id'], $unreadIds, true);
                    ?>
                        <li class="border-b border-line-soft last:border-b-0">
                            <a href="<?= e(url('/my/notifications', ['id' => $email['id']])) ?>"
                               class="flex gap-3 px-4 py-4 hover:bg-[#fafaff] md:px-[18px] <?= $isSel ? 'md:bg-primary/5 md:shadow-[inset_3px_0_0_var(--color-primary)]' : '' ?>"
                               <?= $isSel ? 'aria-current="true"' : '' ?>>
                                <span class="mt-1.5 size-2 shrink-0 rounded-full <?= $isUnread ? 'bg-primary' : '' ?>"><?php if ($isUnread): ?><span class="sr-only">Unread</span><?php endif; ?></span>
                                <span class="min-w-0 grow">
                                    <span class="flex justify-between gap-2.5">
                                        <span class="truncate text-[13.5px] <?= $isUnread ? 'font-semibold' : 'font-medium' ?>"><?= e($email['subject']) ?></span>
                                        <span class="shrink-0 text-[11.5px] text-faint"><?= e(fmt_date($email['created_at'])) ?></span>
                                    </span>
                                    <span class="mt-[3px] block truncate text-[12.5px] text-muted"><?= e($preview($email['message'])) ?></span>
                                </span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

            <?php if ($selected): $isDemo = $selected['status'] === 'demo'; ?>
                <article class="card min-w-0 grow flex-col gap-[18px] px-5 py-6 md:px-8 md:py-7 <?= $explicit ? 'flex' : 'hidden md:flex' ?>" aria-labelledby="mail-subject">
                    <a href="<?= e(url('/my/notifications')) ?>" class="-mt-1 text-[13px] text-muted hover:text-ink md:hidden">&larr; All notifications</a>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <h2 id="mail-subject" class="text-base font-semibold md:text-[17px]"><?= e($selected['subject']) ?></h2>
                        <span class="inline-flex h-6 shrink-0 items-center gap-1.5 self-start rounded-full px-2.5 text-xs font-medium <?= $isDemo ? 'bg-[#b45309]/10 text-lib' : 'bg-mail/10 text-mail' ?>">
                            <?= icon($isDemo ? 'mail' : 'check', 'size-3.5', 2) ?><?= $isDemo ? 'Demo' : 'Delivered' ?>
                        </span>
                    </div>
                    <dl class="grid grid-cols-[60px_1fr] gap-y-1.5 border-b border-line-soft pb-4 text-[12.5px]">
                        <dt class="text-faint">From</dt><dd>CampusIQ</dd>
                        <dt class="text-faint">To</dt><dd class="break-all"><?= e($recipients($selected)) ?></dd>
                        <dt class="text-faint">Sent</dt><dd><?= e(fmt_date($selected['created_at'], true) . ' · ' . date('g:i A', strtotime($selected['created_at']))) ?></dd>
                    </dl>
                    <div class="max-w-[560px] text-sm leading-[1.7] text-[#2b2f3e]">
                        <p class="whitespace-pre-line"><?= e($selected['message']) ?></p>
                        <?php if ($record): ?>
                            <dl class="mt-3 grid grid-cols-[110px_1fr] gap-y-1.5 rounded-[10px] border border-line px-4 py-3.5 text-[13px]">
                                <dt class="text-muted">Type</dt><dd><?= e(record_type_label($record['type'])) ?></dd>
                                <?php if ($record['type'] === 'grade'): ?>
                                    <dt class="text-muted">Subject</dt><dd><?= e($record['title']) ?></dd>
                                    <dt class="text-muted">Grade</dt><dd class="font-semibold"><?= e($record['value']) ?></dd>
                                <?php elseif ($record['type'] === 'library'): ?>
                                    <dt class="text-muted">Book</dt><dd>"<?= e($record['title']) ?>"</dd>
                                    <dt class="text-muted">Status</dt><dd class="font-semibold"><?= e($record['value']) ?></dd>
                                <?php else: ?>
                                    <dt class="text-muted">Status</dt><dd class="font-semibold"><?= e($record['value']) ?></dd>
                                <?php endif; ?>
                                <?php if (!empty($record['note'])): ?><dt class="text-muted">Note</dt><dd><?= e($record['note']) ?></dd><?php endif; ?>
                                <dt class="text-muted">Date</dt><dd><?= e(fmt_date($record['recorded_on'], true)) ?></dd>
                                <dt class="text-muted">Recorded by</dt><dd><?= e(Record::byLabel($record)) ?></dd>
                            </dl>
                        <?php endif; ?>
                    </div>
                    <div class="grow"></div>
                    <p class="flex items-start gap-1.5 text-[11.5px] text-faint">
                        <span class="mt-px"><?= icon('bolt', 'size-3.5') ?></span>
                        <?php if ($isDemo): ?>
                            Demo mode: this message is saved here, but it wasn't sent to your email.
                        <?php elseif (array_key_exists($selected['trigger_key'], EmailTemplateService::TRIGGERS)): ?>
                            Sent automatically when the record was saved.
                        <?php else: ?>
                            Sent by school staff.
                        <?php endif; ?>
                    </p>
                </article>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
