<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class EmailLog extends Model
{
    protected static string $table = 'email_logs';

    public const STATUSES = ['sent', 'failed', 'demo'];

    private const SELECT = 'SELECT e.*, s.first_name, s.last_name, s.student_no, s.email AS student_email, s.guardian_email, s.guardian_name
                            FROM email_logs e JOIN students s ON s.id = e.student_id';

    public static function recent(int $limit = 8): array
    {
        return Database::all(self::SELECT . ' ORDER BY e.created_at DESC, e.id DESC LIMIT ' . max(1, $limit));
    }

    public static function findDetailed(int $id): ?array
    {
        return Database::one(self::SELECT . ' WHERE e.id = ?', [$id]);
    }

    /** Emails about one student (the portal inbox). Failed sends never reached anyone, so they're left out. */
    public static function forStudent(int $studentId): array
    {
        return Database::all(self::SELECT . " WHERE e.student_id = ? AND e.status IN ('sent', 'demo') ORDER BY e.created_at DESC, e.id DESC", [$studentId]);
    }

    public static function countSince(string $since, ?int $studentId = null): int
    {
        $sql = "SELECT COUNT(*) FROM email_logs WHERE created_at > ? AND status IN ('sent', 'demo')";
        $params = [$since];
        if ($studentId !== null) {
            $sql .= ' AND student_id = ?';
            $params[] = $studentId;
        }
        return (int) Database::value($sql, $params);
    }

    public static function thisWeek(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM email_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
    }

    /** "Juan Dela Cruz + guardian", "Maria Reyes's guardian" */
    public static function audience(array $log): string
    {
        $emails = array_filter(array_map('trim', explode(',', $log['recipients'])));
        $name = $log['first_name'] . ' ' . $log['last_name'];
        $hasStudent = in_array(strtolower($log['student_email']), array_map('strtolower', $emails), true);
        $hasGuardian = in_array(strtolower($log['guardian_email']), array_map('strtolower', $emails), true);

        return match (true) {
            $hasStudent && $hasGuardian => "{$name} + guardian",
            $hasGuardian => "{$name}'s guardian",
            default => $name,
        };
    }
}
