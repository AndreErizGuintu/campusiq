<?php
/**
 * One AI answer bubble (design-ref 04). Used for history (server) and new answers (returned as html to ai.js).
 * @var array $result answer, table, records_used, scope, mode, student (optional), created_at (optional)
 */
use App\Models\Student;

$student = $result['student'] ?? null;
$lines = preg_split('/\n/', (string) $result['answer']);
$text = array_shift($lines);
$bullets = array_values(array_filter($lines, static fn ($l) => str_starts_with($l, '• ')));
?>
<div class="flex max-w-[720px] items-start gap-3" data-ai-answer>
    <span class="flex size-8 shrink-0 items-center justify-center rounded-[9px] bg-primary/10 text-primary"><?= icon('ai', 'size-4') ?></span>
    <div class="flex min-w-0 flex-col gap-2.5 rounded-[4px_14px_14px_14px] border border-line bg-white px-4 py-3.5 text-sm leading-relaxed md:px-[18px]">
        <p class="whitespace-pre-line"><?= e($text) ?></p>

        <?php if (!empty($result['table'])): ?>
            <div class="-mx-1 overflow-x-auto px-1">
                <table class="w-full min-w-[320px] text-left text-[13px]">
                    <thead>
                        <tr class="border-b border-line-soft text-[11px] font-semibold tracking-[.05em] text-faint uppercase">
                            <?php foreach ($result['table']['columns'] as $column): ?><th scope="col" class="py-2 pr-4 font-semibold"><?= e($column) ?></th><?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result['table']['rows'] as $row): ?>
                            <tr class="border-b border-line-soft last:border-b-0">
                                <?php foreach ($row as $i => $cell): ?><td class="py-2 pr-4 <?= $i === count($row) - 1 && $i > 0 ? 'text-muted' : '' ?>"><?= e($cell) ?></td><?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($bullets): ?>
            <ul class="flex flex-col gap-1 text-[13px]">
                <?php foreach ($bullets as $bullet): ?><li><?= e($bullet) ?></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($student): ?>
            <div class="flex flex-wrap gap-2">
                <a href="<?= e(url('/reports', ['student' => $student['id']])) ?>" class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-line px-3 text-[12.5px] hover:border-pdf/40 hover:text-pdf"><?= icon('pdf', 'size-3.5') ?>Make this a PDF</a>
                <a href="<?= e(url('/students/' . $student['id'])) ?>" class="inline-flex h-8 items-center rounded-lg border border-line px-3 text-[12.5px] hover:border-primary/40 hover:text-primary">Open <?= e($student['first_name']) ?>'s record</a>
            </div>
        <?php endif; ?>

        <div class="text-[11.5px] text-faint">
            Based on <?= (int) $result['records_used'] ?> <?= e(!empty($result['scope']) ? $result['scope'] : 'records') ?>
            <?php if (($result['mode'] ?? '') === 'demo'): ?> · demo answer<?php endif; ?>
        </div>
    </div>
</div>
