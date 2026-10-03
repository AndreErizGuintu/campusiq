<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class Record extends Model
{
    protected static string $table = 'records';

    public const TYPES = ['grade', 'attendance', 'library'];
    public const ATTENDANCE_VALUES = ['Present', 'Late', 'Absent'];
    public const LIBRARY_VALUES = ['Borrowed', 'Returned', 'Overdue'];

    /** Records for one student, newest first, with the name of who recorded them. */
    public static function forStudent(int $studentId, ?string $type = null, ?string $from = null, ?string $to = null): array
    {
        $sql = 'SELECT r.*, u.name AS recorded_by_name
                FROM records r LEFT JOIN users u ON u.id = r.recorded_by
                WHERE r.student_id = ?';
        $params = [$studentId];
        if ($type !== null && in_array($type, self::TYPES, true)) {
            $sql .= ' AND r.type = ?';
            $params[] = $type;
        }
        if ($from) {
            $sql .= ' AND r.recorded_on >= ?';
            $params[] = $from;
        }
        if ($to) {
            $sql .= ' AND r.recorded_on <= ?';
            $params[] = $to;
        }
        $sql .= ' ORDER BY r.recorded_on DESC, r.created_at DESC, r.id DESC';

        return Database::all($sql, $params);
    }

    /** One record with the student's name and the recorder's name. */
    public static function findDetailed(int $id): ?array
    {
        return Database::one(
            'SELECT r.*, u.name AS recorded_by_name, s.first_name, s.last_name, s.student_no, s.section
             FROM records r JOIN students s ON s.id = r.student_id LEFT JOIN users u ON u.id = r.recorded_by
             WHERE r.id = ?',
            [$id]
        );
    }

    /** "Who recorded it" label used in tables. */
    public static function byLabel(array $record): string
    {
        return $record['recorded_by_name'] ?? ($record['type'] === 'library' ? 'Library desk' : 'Staff');
    }

    /** Count per type for one student: ['grade' => 3, ...]. */
    public static function countsByType(int $studentId): array
    {
        $rows = Database::all('SELECT type, COUNT(*) AS n FROM records WHERE student_id = ? GROUP BY type', [$studentId]);

        return array_column($rows, 'n', 'type') + ['grade' => 0, 'attendance' => 0, 'library' => 0];
    }
}
