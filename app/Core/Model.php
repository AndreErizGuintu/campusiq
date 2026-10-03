<?php

namespace App\Core;

use InvalidArgumentException;

/**
 * Base model. Column names are whitelisted from DESCRIBE, so models stay schema agnostic:
 * unknown keys are dropped on insert/update and can never reach SQL as identifiers.
 */
abstract class Model
{
    protected static string $table;

    /** @var array<string, string[]> */
    private static array $columns = [];

    /** @return string[] */
    public static function columns(): array
    {
        $table = static::$table;
        if (!isset(self::$columns[$table])) {
            self::$columns[$table] = array_column(Database::all('DESCRIBE `' . $table . '`'), 'Field');
        }

        return self::$columns[$table];
    }

    /** Keep only real, writable columns. */
    public static function only(array $data): array
    {
        return array_intersect_key($data, array_flip(array_diff(static::columns(), ['id'])));
    }

    protected static function column(string $name): string
    {
        if (!in_array($name, static::columns(), true)) {
            throw new InvalidArgumentException("Unknown column {$name} on " . static::$table);
        }

        return '`' . $name . '`';
    }

    /** "created_at DESC" => safe ORDER BY clause */
    protected static function orderBy(string $order): string
    {
        $parts = preg_split('/\s+/', trim($order));
        $direction = strtoupper($parts[1] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

        return static::column($parts[0]) . ' ' . $direction;
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM `' . static::$table . '` WHERE id = ?', [$id]);
    }

    public static function findBy(string $column, mixed $value): ?array
    {
        return Database::one('SELECT * FROM `' . static::$table . '` WHERE ' . static::column($column) . ' = ? LIMIT 1', [$value]);
    }

    public static function where(string $column, mixed $value, string $order = 'id ASC', int $limit = 0): array
    {
        $sql = 'SELECT * FROM `' . static::$table . '` WHERE ' . static::column($column) . ' = ? ORDER BY ' . static::orderBy($order);
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit;
        }

        return Database::all($sql, [$value]);
    }

    public static function all(string $order = 'id ASC'): array
    {
        return Database::all('SELECT * FROM `' . static::$table . '` ORDER BY ' . static::orderBy($order));
    }

    public static function count(?string $column = null, mixed $value = null): int
    {
        if ($column === null) {
            return (int) Database::value('SELECT COUNT(*) FROM `' . static::$table . '`');
        }

        return (int) Database::value('SELECT COUNT(*) FROM `' . static::$table . '` WHERE ' . static::column($column) . ' = ?', [$value]);
    }

    public static function create(array $data): int
    {
        $data = static::only($data);
        if (!$data) {
            throw new InvalidArgumentException('Nothing to insert into ' . static::$table);
        }
        $columns = implode(', ', array_map(static fn ($c) => '`' . $c . '`', array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        Database::run('INSERT INTO `' . static::$table . "` ({$columns}) VALUES ({$placeholders})", array_values($data));

        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $data = static::only($data);
        unset($data['created_at']);
        if (!$data) {
            return;
        }
        $sets = implode(', ', array_map(static fn ($c) => '`' . $c . '` = ?', array_keys($data)));
        Database::run('UPDATE `' . static::$table . "` SET {$sets} WHERE id = ?", [...array_values($data), $id]);
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM `' . static::$table . '` WHERE id = ?', [$id]);
    }
}
