<?php
/**
 * Boots CampusIQ for both web requests and CLI scripts:
 * autoload, .env, timezone, error handling, session.
 */

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/app/helpers.php';

App\Core\Env::load(BASE_PATH . '/.env');

date_default_timezone_set('Asia/Manila');
mb_internal_encoding('UTF-8');

// Errors: never shown raw to the browser, always logged to storage/logs/app.log.
error_reporting(E_ALL);
ini_set('display_errors', PHP_SAPI === 'cli' ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/app.log');

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

if (PHP_SAPI !== 'cli') {
    set_exception_handler(static function (Throwable $e): void {
        log_message('error', get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        App\Core\Response::error(500, $e);
    });

    // Sessions live as long as the "Remember me" cookie (7 days) instead of PHP's 24-minute default.
    ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 7));
    session_name('campusiq_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => base_path() === '' ? '/' : base_path() . '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (($_SERVER['HTTPS'] ?? '') === 'on'),
    ]);
    session_start();
}
