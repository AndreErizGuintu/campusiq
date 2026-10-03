<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class User extends Model
{
    protected static string $table = 'users';

    /** Log in with either the ID number or the email. */
    public static function findByLogin(string $login): ?array
    {
        return Database::one(
            'SELECT * FROM users WHERE id_number = ? OR email = ? LIMIT 1',
            [trim($login), strtolower(trim($login))]
        );
    }

    public static function existsForStudent(int $studentId, string $role): bool
    {
        return (bool) Database::value('SELECT COUNT(*) FROM users WHERE student_id = ? AND role = ?', [$studentId, $role]);
    }
}
