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

    /**
     * The portal inbox: emails about one student that were addressed to this viewer
     * (the student's own email, or the guardian email for parents).
     * Failed sends never reached anyone, so they're left out.
     */
    public static function inbox(int $studentId, string $recipient): array
    {
        return Database::all(
            self::SELECT . " WHERE e.student_id = ? AND e.status IN ('sent', 'demo') AND CONCAT(', ', LOWER(e.recipients), ',') LIKE ?
                            ORDER BY e.created_at DESC, e.id DESC",
            [$studentId, self::recipientPattern($recipient)]
        );
    }

    public static function unreadCount(int $studentId, string $recipient, ?string $since): int
    {
        return (int) Database::value(
            "SELECT COUNT(*) FROM email_logs WHERE student_id = ? AND status IN ('sent', 'demo') AND created_at > ?
             AND CONCAT(', ', LOWER(recipients), ',') LIKE ?",
            [$studentId, $since ?? '1970-01-01', self::recipientPattern($recipient)]
        );
    }

    /** Matches one address inside the comma-separated recipients list. */
    private static function recipientPattern(string $email): string
    {
        $escaped = addcslashes(strtolower(trim($email)), '\\%_');

        return '%, ' . $escaped . ',%';
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
