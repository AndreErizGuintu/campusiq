<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class Student extends Model
{
    protected static string $table = 'students';

    public static function fullName(array $student): string
    {
        return $student['first_name'] . ' ' . $student['last_name'];
    }

    public static function findByNumber(string $studentNo): ?array
    {
        return static::findBy('student_no', trim($studentNo));
    }

    /** Students for selects and lists, ordered by section then name. */
    public static function ordered(): array
    {
        return Database::all('SELECT * FROM students ORDER BY section, last_name, first_name');
    }

    /**
     * Students with summary numbers, optionally filtered by a search over
     * name, student number, section and emails.
     */
    public static function search(string $query = ''): array
    {
        $sql = "SELECT s.*,
                    COUNT(r.id) AS record_count,
                    SUM(r.type = 'attendance') AS attendance_count,
                    SUM(r.type = 'attendance' AND r.value IN ('Present', 'Late')) AS present_count,
                    SUM(r.type = 'attendance' AND r.value = 'Absent') AS absent_count,
                    MAX(r.recorded_on) AS last_record_on
                FROM students s LEFT JOIN records r ON r.student_id = s.id";
        $params = [];
        $query = trim($query);
        if ($query !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query) . '%';
            $sql .= " WHERE CONCAT(s.first_name, ' ', s.last_name) LIKE ? OR s.student_no LIKE ? OR s.section LIKE ?
                      OR s.email LIKE ? OR s.guardian_name LIKE ? OR s.guardian_email LIKE ?";
            $params = array_fill(0, 6, $like);
        }
        $sql .= ' GROUP BY s.id ORDER BY s.section, s.last_name, s.first_name';

        return Database::all($sql, $params);
    }
}
