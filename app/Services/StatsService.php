<?php

namespace App\Services;

use App\Core\Database;

/**
 * Numbers for the staff dashboard, all from the database.
 */
class StatsService
{
    /** The four stat cards. */
    public function summary(): array
    {
        $students = (int) Database::value("SELECT COUNT(*) FROM students WHERE status = 'active'");
        $sections = Database::all('SELECT DISTINCT section FROM students ORDER BY section');

        // "Today" = the latest school day with attendance on or before today.
        $day = Database::value("SELECT MAX(recorded_on) FROM records WHERE type = 'attendance' AND recorded_on <= CURDATE()");
        $present = $day ? (int) Database::value(
            "SELECT COUNT(*) FROM records WHERE type = 'attendance' AND recorded_on = ? AND value IN ('Present', 'Late')",
            [$day]
        ) : 0;
        $marked = $day ? (int) Database::value("SELECT COUNT(*) FROM records WHERE type = 'attendance' AND recorded_on = ?", [$day]) : 0;

        $emails = Database::one(
            "SELECT COUNT(*) AS total, SUM(status = 'sent') AS sent, SUM(status = 'demo') AS demo, SUM(status = 'failed') AS failed
             FROM email_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        $reports = Database::one(
            "SELECT COUNT(*) AS total, SUM(u.role IN ('student', 'parent')) AS by_portal
             FROM reports r LEFT JOIN users u ON u.id = r.created_by
             WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );

        return [
            'students' => $students,
            'sections' => array_column($sections, 'section'),
            'attendance_day' => $day,
            'present' => $present,
            'marked' => $marked,
            'present_rate' => $marked ? (int) round($present / $marked * 100) : null,
            'emails' => array_map('intval', $emails ?? []),
            'reports' => array_map('intval', $reports ?? []),
            'records_week' => (int) Database::value('SELECT COUNT(*) FROM records WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'),
        ];
    }

    /** Students present (present + late) on the last N school days that have attendance. */
    public function attendanceDays(int $days = 5): array
    {
        $rows = Database::all(
            "SELECT recorded_on,
                    SUM(value IN ('Present', 'Late')) AS present,
                    SUM(value = 'Late') AS late,
                    COUNT(*) AS marked
             FROM records
             WHERE type = 'attendance' AND recorded_on <= CURDATE()
             GROUP BY recorded_on
             ORDER BY recorded_on DESC
             LIMIT {$days}"
        );

        return array_reverse(array_map(static fn ($r) => [
            'date' => $r['recorded_on'],
            'present' => (int) $r['present'],
            'late' => (int) $r['late'],
            'marked' => (int) $r['marked'],
        ], $rows));
    }

    /**
     * Latest activity across records, emails, reports and AI questions.
     * Routine "Present" attendance is skipped so the feed shows what changed.
     */
    public function recentActivity(int $limit = 6, ?int $viewerId = null): array
    {
        $items = [];

        foreach (Database::all(
            "SELECT r.id, r.type, r.title, r.value, r.note, r.created_at, s.id AS student_id, s.first_name, s.last_name
             FROM records r JOIN students s ON s.id = r.student_id
             WHERE NOT (r.type = 'attendance' AND r.value = 'Present')
             ORDER BY r.created_at DESC, r.id DESC LIMIT {$limit}"
        ) as $r) {
            $name = $r['first_name'] . ' ' . $r['last_name'];
            $text = match ($r['type']) {
                'grade' => "Grade added for {$name} · " . preg_replace('/^Quarter \d+\s+/', '', $r['title']) . ' ' . $r['value'],
                'attendance' => "{$name} marked " . strtolower($r['value']),
                default => "{$name} · {$r['value']} \"{$r['title']}\"",
            };
            $items[] = ['kind' => 'record', 'text' => $text, 'at' => $r['created_at'], 'href' => '/students/' . $r['student_id']];
        }

        foreach (Database::all(
            "SELECT e.status, e.recipients, e.created_at, s.first_name, s.last_name
             FROM email_logs e JOIN students s ON s.id = e.student_id
             ORDER BY e.created_at DESC, e.id DESC LIMIT {$limit}"
        ) as $e) {
            $count = count(array_filter(array_map('trim', explode(',', $e['recipients']))));
            $who = "{$e['first_name']} {$e['last_name']}" . ($count > 1 ? ' + guardian' : '');
            $text = match ($e['status']) {
                'failed' => "Email to {$who} failed",
                'demo' => "Email to {$who} logged (demo)",
                default => "Email sent to {$who}",
            };
            $items[] = ['kind' => 'email', 'text' => $text, 'at' => $e['created_at'], 'href' => '/emails'];
        }

        foreach (Database::all(
            "SELECT r.created_at, s.first_name, s.last_name
             FROM reports r JOIN students s ON s.id = r.student_id
             ORDER BY r.created_at DESC, r.id DESC LIMIT {$limit}"
        ) as $p) {
            $items[] = ['kind' => 'report', 'text' => "PDF report made for {$p['first_name']} {$p['last_name']}", 'at' => $p['created_at'], 'href' => '/reports'];
        }

        foreach (Database::all(
            "SELECT q.question, q.created_at, q.user_id, u.name
             FROM ai_queries q LEFT JOIN users u ON u.id = q.user_id
             ORDER BY q.created_at DESC, q.id DESC LIMIT {$limit}"
        ) as $q) {
            $who = ($viewerId !== null && (int) $q['user_id'] === $viewerId) ? 'You' : ($q['name'] ?? 'Staff');
            $question = mb_strimwidth($q['question'], 0, 48, '…');
            $items[] = ['kind' => 'ai', 'text' => "{$who} asked AI: \"{$question}\"", 'at' => $q['created_at'], 'href' => '/ai'];
        }

        usort($items, static fn ($a, $b) => strcmp($b['at'], $a['at']));

        return array_slice($items, 0, $limit);
    }
}
