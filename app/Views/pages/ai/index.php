<?php
/**
 * AI Assistant chat.
 * @var array $history recent ai_queries for this user, oldest first
 * @var array|null $student student passed with ?student= (from "Summarize")
 * @var int $studentCount
 */
use App\Models\Student;

$suggestions = [
    'Who\'s been absent or late more than twice this week?',
    'Which books are overdue?',
    'Who\'s at risk in Math?',
    'Summarize attendance this month',
];
if ($student) {
    array_unshift($suggestions, 'Summarize ' . Student::fullName($student) . '\'s record');
}
?>
<div class="-mx-4 -my-5 flex min-h-[calc(100dvh-56px-56px)] flex-col md:-mx-8 md:-my-6 md:min-h-[calc(100dvh-64px)]">
    <section class="flex grow flex-col gap-3.5 px-4 pt-5 pb-4 md:px-12" aria-label="Conversation" aria-live="polite" data-ai-thread>
        <div class="self-center rounded-full bg-[#eceef4] px-3 py-1 text-center text-[11.5px] text-faint">
            Answers come from CampusIQ records · <?= (int) $studentCount ?> students, grades, attendance and library
        </div>

        <?php if (!$history): ?>
            <div class="mx-auto my-6 flex max-w-md flex-col items-center gap-2 text-center" data-ai-empty>
                <span class="flex size-12 items-center justify-center rounded-xl bg-primary/10 text-primary"><?= icon('ai', 'size-6') ?></span>
                <h2 class="text-base font-semibold">Ask about any student, section or record</h2>
                <p class="text-[13px] text-muted">Try "Who was absent this week?", "Summarize Juan Dela Cruz's record" or "Which books are overdue?". Answers say how many records they used.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($history as $item): ?>
            <div class="max-w-[85%] self-end rounded-[14px_14px_4px_14px] bg-primary px-4 py-3 text-sm leading-normal text-white md:max-w-[520px]"><?= e($item['question']) ?></div>
            <?= partial('ai-answer', ['result' => [
                'answer' => $item['answer'],
                'table' => null,
                'records_used' => (int) $item['records_used'],
                'scope' => 'records · ' . time_ago($item['created_at']),
                'mode' => $item['mode'],
            ]]) ?>
        <?php endforeach; ?>
    </section>

    <div class="sticky bottom-14 z-10 flex flex-col gap-3 border-t border-line bg-page px-4 pt-3 pb-4 md:bottom-0 md:px-12 md:pb-[22px]">
        <div class="-mx-4 flex gap-2 overflow-x-auto px-4 md:mx-0 md:flex-wrap md:px-0" data-ai-suggestions>
            <?php foreach ($suggestions as $suggestion): ?>
                <button type="button" class="inline-flex h-[34px] shrink-0 items-center rounded-full border border-[#d6d8f5] bg-white px-3.5 text-[12.5px] whitespace-nowrap text-primary hover:bg-primary/5" data-suggestion><?= e($suggestion) ?></button>
            <?php endforeach; ?>
        </div>
        <form class="flex items-center gap-2.5 rounded-xl border border-[#d6d8f5] bg-white py-2 pr-2 pl-4 shadow-[0_4px_14px_rgba(67,56,202,.06)] focus-within:border-primary" data-ai-form data-no-lock>
            <input type="hidden" name="student_id" value="<?= $student ? (int) $student['id'] : '' ?>">
            <label for="ask" class="sr-only">Ask a question</label>
            <input id="ask" name="question" type="text" maxlength="500" autocomplete="off" required
                   placeholder="Ask about any student, section, or record…"
                   value="<?= $student ? e('Summarize ' . Student::fullName($student) . '\'s record') : '' ?>"
                   class="h-9 min-w-0 grow bg-transparent text-sm outline-none placeholder:text-faint">
            <button type="submit" class="flex size-10 shrink-0 items-center justify-center rounded-[9px] bg-primary text-white hover:bg-primary-dark disabled:opacity-60" aria-label="Send"><?= icon('send', 'size-[18px]', 1.7) ?></button>
        </form>
    </div>
</div>
