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
}
