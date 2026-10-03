<?php

namespace App\Core;

/**
 * One CSRF token per session. Checked on every POST (form field _csrf or X-CSRF-Token header).
 */
class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    public static function rotate(): void
    {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    public static function verify(Request $request): bool
    {
        $sent = $request->input('_csrf') ?? $request->header('X-CSRF-Token');

        return is_string($sent) && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $sent);
    }
}
