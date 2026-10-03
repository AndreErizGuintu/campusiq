<?php

namespace App\Core;

use App\Models\User;

/**
 * Session login for staff, students and parents.
 */
class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    /** Find a user by ID number or email and check the password. */
    public static function attempt(string $login, string $password): ?array
    {
        $user = User::findByLogin($login);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            User::update((int) $user['id'], ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        }

        return $user;
    }

    public static function login(array $user, bool $remember = false): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        Csrf::rotate();

        if ($remember) {
            $params = session_get_cookie_params();
            setcookie(session_name(), session_id(), [
                'expires' => time() + 60 * 60 * 24 * 7,
                'path' => $params['path'],
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => $params['secure'],
            ]);
        }

        self::$user = null;
        self::$loaded = false;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $params['path']]);
        session_destroy();
        session_start();
        session_regenerate_id(true);
        self::$user = null;
        self::$loaded = true;
    }

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = $_SESSION['user_id'] ?? null;
            self::$user = $id ? User::find((int) $id) : null;
            if ($id && !self::$user) {
                unset($_SESSION['user_id']);
            }
        }

        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        return self::user() ? (int) self::user()['id'] : null;
    }

    public static function role(): ?string
    {
        return self::user()['role'] ?? null;
    }

    public static function is(string ...$roles): bool
    {
        return in_array(self::role(), $roles, true);
    }

    /** Where each role lands after logging in. */
    public static function home(?array $user = null): string
    {
        $user ??= self::user();
        return ($user['role'] ?? null) === 'staff' ? '/dashboard' : '/my/records';
    }
}
