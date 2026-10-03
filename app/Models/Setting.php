<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/** Key/value settings, used for the automatic email triggers. */
class Setting extends Model
{
    protected static string $table = 'settings';

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = Database::value('SELECT value FROM settings WHERE `key` = ?', [$key]);

        return $value === null ? $default : (string) $value;
    }

    public static function set(string $key, string $value): void
    {
        Database::run(
            'INSERT INTO settings (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [$key, $value]
        );
    }

    public static function enabled(string $key): bool
    {
        return static::get($key, '0') === '1';
    }
}
