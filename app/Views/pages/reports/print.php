<?php
/**
 * Print-ready student report. Self-contained (inline CSS) because PDFShift renders it on its own servers.
 * @var array $student
 * @var string $type full|grade_slip|attendance|library
 * @var string $title
 * @var string|null $from
 * @var string|null $to
 * @var string[] $sections
 * @var array $records
 * @var array|null $createdBy
 * @var string $generatedAt
 * @var bool $demo
 */
use App\Models\Record;
use App\Models\Report;
use App\Models\Student;
use App\Services\PdfShiftService;

$name = Student::fullName($student);
$notes = in_array('notes', $sections, true);
$byType = ['grade' => [], 'attendance' => [], 'library' => []];
foreach ($records as $r) {
    $byType[$r['type']][] = $r;
}
$summary = PdfShiftService::summary($records);
$period = ($from || $to) ? trim(($from ? fmt_date($from, true) : 'Start') . ' to ' . ($to ? fmt_date($to, true) : 'today')) : 'All records';
$heading = $type === 'full' ? 'Student Record' : $title;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= e($heading . ' · ' . $name) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500;600&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
<style>
    @page { size: A4; margin: 14mm 14mm 16mm; }
    * { box-sizing: border-box; }
    html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    body { margin: 0; background: #eef0f5; font-family: 'IBM Plex Sans', Arial, sans-serif; color: #1b1e27; font-size: 12px; line-height: 1.5; }
    .page { max-width: 794px; margin: 24px auto; background: #fff; padding: 40px 44px; box-shadow: 0 6px 18px rgba(20, 22, 40, .10); }
    .mono { font-family: 'IBM Plex Mono', 'Courier New', monospace; }
    header { display: flex; justify-content: space-between; align-items: center; }
    .brand { display: flex; align-items: center; gap: 8px; color: #4338ca; font-weight: 600; font-size: 14px; }
    .mark { width: 24px; height: 24px; border-radius: 6px; background: #4338ca; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 10px; }
    .date { color: #9095a5; font-size: 11px; }
    .rule { height: 2px; background: #4338ca; margin: 12px 0 18px; }
    h1 { margin: 0 0 4px; font-size: 22px; font-weight: 600; }
    .who { color: #6b7083; font-size: 12.5px; }
    .meta { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 18px 0 8px; }
    .meta div { border: 1px solid #e3e6ee; border-radius: 8px; padding: 8px 10px; }
    .meta span { display: block; color: #9095a5; font-size: 10px; text-transform: uppercase; letter-spacing: .05em; font-weight: 600; }
    .meta b { font-size: 15px; font-weight: 600; }
    h2 { font-size: 13px; font-weight: 600; margin: 22px 0 6px; display: flex; justify-content: space-between; align-items: baseline; }
    h2 small { font-family: 'IBM Plex Sans', Arial, sans-serif; font-weight: 400; color: #9095a5; font-size: 11px; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .05em; color: #9095a5; font-weight: 600; padding: 6px 8px; border-bottom: 1px solid #e3e6ee; }
    td { padding: 7px 8px; border-bottom: 1px solid #eef0f5; vertical-align: top; }
    tr { page-break-inside: avoid; }
    .tag { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 10.5px; font-weight: 500; }
    .t-grade { background: rgba(67, 56, 202, .1); color: #4338ca; }
    .t-attendance { background: rgba(15, 118, 110, .1); color: #0f766e; }
    .t-library { background: rgba(180, 83, 9, .1); color: #9a4a08; }
    .bad { color: #b3123c; font-weight: 500; }
    .warn { color: #9a4a08; font-weight: 500; }
    .num { font-family: 'IBM Plex Mono', monospace; font-weight: 600; }
    .muted { color: #6b7083; }
    .empty { color: #9095a5; font-style: italic; padding: 10px 8px; }
    footer { margin-top: 28px; padding-top: 10px; border-top: 1px solid #e3e6ee; display: flex; justify-content: space-between; color: #9095a5; font-size: 10.5px; }
    .demo-note { margin-top: 14px; padding: 8px 10px; border-radius: 8px; background: rgba(180, 83, 9, .08); color: #9a4a08; font-size: 11px; }
    @media print {
        body { background: #fff; }
        .page { margin: 0; padding: 0; max-width: none; box-shadow: none; }
        .no-print { display: none !important; }
    }
    @media (max-width: 640px) {
        .page { margin: 0; padding: 24px 18px; }
        .meta { grid-template-columns: repeat(2, 1fr); }
    }
</style>
</head>
<body>
<div class="page">
    <header>
        <div class="brand mono"><span class="mark">CQ</span>CampusIQ</div>
        <div class="date"><?= e(fmt_date($generatedAt, true)) ?></div>
    </header>
    <div class="rule"></div>

    <h1 class="mono"><?= e($heading) ?></h1>
    <div class="who"><?= e($name) ?> &middot; ID <?= e($student['student_no']) ?> &middot; Grade <?= e($student['section']) ?> &middot; Guardian: <?= e($student['guardian_name']) ?></div>

    <div class="meta">
        <div><span>Period</span><b style="font-size:12px"><?= e($period) ?></b></div>
        <?php if (in_array('attendance', $sections, true)): ?>
            <div><span>Attendance</span><b class="mono"><?= $summary['rate'] !== null ? $summary['rate'] . '%' : '—' ?></b></div>
            <div><span>Late / absent</span><b class="mono"><?= (int) $summary['late'] ?> / <?= (int) $summary['absent'] ?></b></div>
        <?php endif; ?>
        <?php if (in_array('grades', $sections, true)): ?>
            <div><span>Average grade</span><b class="mono"><?= $summary['average'] ?? '—' ?></b></div>
        <?php endif; ?>
        <?php if (!in_array('attendance', $sections, true)): ?>
            <div><span>Records</span><b class="mono"><?= count($records) ?></b></div>
        <?php endif; ?>
    </div>

    <?php if (in_array('grades', $sections, true)): ?>
        <h2 class="mono">Grades <small><?= count($byType['grade']) ?> posted</small></h2>
        <table>
            <thead><tr><th style="width:46%">Subject and period</th><th style="width:14%">Grade</th><th>Recorded by</th><th style="width:16%">Date</th></tr></thead>
            <tbody>
            <?php foreach ($byType['grade'] as $r): ?>
                <tr>
                    <td><?= e($r['title']) ?><?php if ($notes && $r['note']): ?><div class="muted"><?= e($r['note']) ?></div><?php endif; ?></td>
                    <td class="num<?= is_numeric($r['value']) && $r['value'] < 80 ? ' bad' : '' ?>"><?= e($r['value']) ?></td>
                    <td class="muted"><?= e(Record::byLabel($r)) ?></td>
                    <td class="muted"><?= e(fmt_date($r['recorded_on'], true)) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$byType['grade']): ?><tr><td colspan="4" class="empty">No grades in this period.</td></tr><?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if (in_array('attendance', $sections, true)): ?>
        <h2 class="mono">Attendance <small><?= (int) $summary['days'] ?> school days &middot; <?= (int) $summary['present'] ?> present (<?= (int) $summary['late'] ?> late) &middot; <?= (int) $summary['absent'] ?> absent</small></h2>
        <table>
            <thead><tr><th style="width:24%">Date</th><th style="width:20%">Status</th><th><?= $notes ? 'Note' : 'Recorded by' ?></th></tr></thead>
            <tbody>
            <?php foreach ($byType['attendance'] as $r): ?>
                <tr>
                    <td><?= e(date('D', strtotime($r['recorded_on'])) . ', ' . fmt_date($r['recorded_on'], true)) ?></td>
                    <td class="<?= $r['value'] === 'Absent' ? 'bad' : ($r['value'] === 'Late' ? 'warn' : '') ?>"><?= e($r['value']) ?></td>
                    <td class="muted"><?= e($notes ? ($r['note'] ?? '') : Record::byLabel($r)) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$byType['attendance']): ?><tr><td colspan="3" class="empty">No attendance in this period.</td></tr><?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if (in_array('library', $sections, true)): ?>
        <h2 class="mono">Library <small><?= count($byType['library']) ?> transactions</small></h2>
        <table>
            <thead><tr><th style="width:42%">Book</th><th style="width:16%">Status</th><th>Details</th><th style="width:16%">Date</th></tr></thead>
            <tbody>
            <?php foreach ($byType['library'] as $r): ?>
                <tr>
                    <td>"<?= e($r['title']) ?>"</td>
                    <td class="<?= $r['value'] === 'Overdue' ? 'bad' : '' ?>"><?= e($r['value']) ?></td>
                    <td class="muted"><?= e($r['note'] ?? '') ?></td>
                    <td class="muted"><?= e(fmt_date($r['recorded_on'], true)) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$byType['library']): ?><tr><td colspan="4" class="empty">No library activity in this period.</td></tr><?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if ($demo): ?>
        <div class="demo-note no-print">Demo mode: this is the HTML that would be sent to PDFShift. Add PDFSHIFT_API_KEY to .env to get a real PDF. Use your browser's Print &rarr; Save as PDF for now.</div>
    <?php endif; ?>

    <footer>
        <span>Generated by CampusIQ<?= $createdBy ? ' for ' . e($createdBy['name']) : '' ?> &middot; <?= e(date('M j, Y g:i A', strtotime($generatedAt))) ?></span>
        <span><?= e(Report::typeLabel($type)) ?> &middot; <?= e($student['student_no']) ?></span>
    </footer>
</div>
</body>
</html>
