<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class Report extends Model
{
    protected static string $table = 'reports';

    /** Report types with what they include by default. */
    public const TYPES = [
        'full' => ['Full record', 'Everything on file', ['grades', 'attendance', 'library']],
        'grade_slip' => ['Grade slip', 'Grades only', ['grades']],
        'attendance' => ['Attendance summary', 'Present, late, absent', ['attendance']],
        'library' => ['Library receipt', 'Borrowed and returned', ['library']],
    ];

    public const SECTIONS = ['grades' => 'Grades', 'attendance' => 'Attendance', 'library' => 'Library', 'notes' => 'Staff notes'];

    public static function typeLabel(string $type): string
    {
        return self::TYPES[$type][0] ?? ucfirst($type);
    }

    public static function latestFor(int $studentId): ?array
    {
        return Database::one('SELECT * FROM reports WHERE student_id = ? ORDER BY created_at DESC, id DESC LIMIT 1', [$studentId]);
    }

    private const SELECT = 'SELECT r.*, s.first_name, s.last_name, s.student_no, u.name AS created_by_name, u.role AS created_by_role
                            FROM reports r JOIN students s ON s.id = r.student_id LEFT JOIN users u ON u.id = r.created_by';

    public static function recent(int $limit = 5): array
    {
        return Database::all(self::SELECT . ' ORDER BY r.created_at DESC, r.id DESC LIMIT ' . max(1, $limit));
    }

    public static function forStudent(int $studentId, int $limit = 20): array
    {
        return Database::all(self::SELECT . ' WHERE r.student_id = ? ORDER BY r.created_at DESC, r.id DESC LIMIT ' . max(1, $limit), [$studentId]);
    }

    public static function findDetailed(int $id): ?array
    {
        return Database::one(self::SELECT . ' WHERE r.id = ?', [$id]);
    }

    public static function thisWeek(): int
    {
        return (int) Database::value('SELECT COUNT(*) FROM reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
    }

    /** "JuanDelaCruz_Record.pdf" */
    public static function fileName(array $report): string
    {
        $suffix = ['full' => 'Record', 'grade_slip' => 'GradeSlip', 'attendance' => 'Attendance', 'library' => 'LibraryReceipt'][$report['report_type']] ?? 'Report';
        $name = preg_replace('/[^A-Za-z0-9]/', '', $report['first_name'] . $report['last_name']);

        return $name . '_' . $suffix . ($report['mode'] === 'demo' ? '.html' : '.pdf');
    }
}
