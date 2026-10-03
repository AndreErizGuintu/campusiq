<?php
/**
 * Global view and app helpers: e(), url(), asset(), csrf_field(), old(), flash() and friends.
 */

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\View;

/** Escape for HTML output. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL path prefix of the app, e.g. "/campusiq". */
function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $base = rtrim((string) parse_url((string) Env::get('APP_URL', ''), PHP_URL_PATH), '/');
    }
    return $base;
}

/** App URL for a route path: url('/students/3') => /campusiq/students/3 */
function url(string $path = '/', array $query = []): string
{
    $path = '/' . ltrim($path, '/');
    $query = array_filter($query, static fn ($v) => $v !== null && $v !== '');
    return base_path() . ($path === '/' ? '/' : $path) . ($query ? '?' . http_build_query($query) : '');
}

/** Public asset URL with a cache-busting version. */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = BASE_PATH . '/public/assets/' . $path;
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return base_path() . '/assets/' . $path . '?v=' . $version;
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

/** Previous form input after a failed validation. */
function old(string $key, mixed $default = ''): string
{
    return (string) ($_SESSION['_old'][$key] ?? $default);
}

/** Validation error for a field after a failed POST. */
function error_for(string $key): ?string
{
    return $_SESSION['_errors'][$key] ?? null;
}

/** Set a flash message (two args) or read and clear one (one arg). */
function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function auth(): ?array
{
    return Auth::user();
}

/** Render a partial from app/Views/partials. */
function partial(string $name, array $data = []): string
{
    return View::partial($name, $data);
}

/** Avatar initials from the first two words, as in the designs: "Ms. Santos" => MS, "Juan Dela Cruz" => JD. */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [''];
    return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
}

/** Format a date: "Sept 25" style, matching the designs. */
function fmt_date(?string $date, bool $withYear = false): string
{
    if (!$date) {
        return '';
    }
    $time = strtotime($date);
    $month = date('n', $time) === '9' ? 'Sept' : date('M', $time);
    return $month . ' ' . date('j', $time) . ($withYear ? ', ' . date('Y', $time) : '');
}

/** "just now", "14 min ago", "2 hr ago", "Yesterday", then a date. */
function time_ago(?string $datetime): string
{
    if (!$datetime) {
        return '';
    }
    $seconds = time() - strtotime($datetime);
    return match (true) {
        $seconds < 60 => 'Just now',
        $seconds < 3600 => intdiv($seconds, 60) . ' min ago',
        $seconds < 86400 => intdiv($seconds, 3600) . ' hr ago',
        $seconds < 172800 => 'Yesterday',
        default => fmt_date($datetime),
    };
}

function format_bytes(int $bytes): string
{
    return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
}

function log_message(string $level, string $message): void
{
    $line = sprintf("[%s] %s: %s\n", date('Y-m-d H:i:s'), strtoupper($level), $message);
    @file_put_contents(BASE_PATH . '/storage/logs/app.log', $line, FILE_APPEND | LOCK_EX);
}

/** Is the current request path inside this section? Used for active nav states. */
function nav_active(string $prefix): bool
{
    $current = $GLOBALS['__request_path'] ?? '/';
    return $prefix === '/' ? $current === '/' : ($current === $prefix || str_starts_with($current, rtrim($prefix, '/') . '/'));
}

/** Inline SVG icon from the design set (20x20 grid, stroked). */
function icon(string $name, string $class = 'size-[18px]', float $stroke = 1.6): string
{
    $paths = [
        'home' => '<path d="M3 9.5 10 4l7 5.5V16a1 1 0 0 1-1 1h-3.5v-4.5h-5V17H4a1 1 0 0 1-1-1z"/>',
        'dashboard' => '<rect x="3" y="3" width="6" height="6" rx="1"/><rect x="11" y="3" width="6" height="6" rx="1"/><rect x="3" y="11" width="6" height="6" rx="1"/><rect x="11" y="11" width="6" height="6" rx="1"/>',
        'students' => '<circle cx="8" cy="7" r="3"/><path d="M2.5 17c.6-3 2.8-4.5 5.5-4.5s4.9 1.5 5.5 4.5M13 4.2a3 3 0 0 1 0 5.6M15 12.8c1.4.6 2.3 2 2.6 4.2"/>',
        'ai' => '<path d="M10 3l1.6 4.4L16 9l-4.4 1.6L10 15l-1.6-4.4L4 9l4.4-1.6z"/>',
        'mail' => '<rect x="2.5" y="4.5" width="15" height="11" rx="1.5"/><path d="M3 5.5 10 11l7-5.5"/>',
        'pdf' => '<path d="M5 3h7l4 4v10H5z"/><path d="M10.5 9v5M8.5 12l2 2 2-2"/>',
        'file' => '<path d="M5 3h7l4 4v10H5z"/><path d="M12 3v4h4"/>',
        'records' => '<path d="M5 3h7l4 4v10H5z"/><path d="M12 3v4h4M8 11h5M8 14h5"/>',
        'logout' => '<path d="M8 4H4v12h4M12 7l3 3-3 3M15 10H8"/>',
        'search' => '<circle cx="9" cy="9" r="5"/><path d="M13 13l4 4"/>',
        'download' => '<path d="M10 3v10M6 9l4 4 4-4M4 16h12"/>',
        'plus' => '<path d="M10 4v12M4 10h12"/>',
        'bolt' => '<path d="M11 3 5 11h5l-1 6 6-8h-5z"/>',
        'send' => '<path d="M3 10 17 3l-4 14-3-6z"/><path d="M10 11l7-8"/>',
        'menu' => '<path d="M3 6h14M3 10h14M3 14h14"/>',
        'check' => '<path d="M5 10.5 8.5 14 15 6.5"/>',
        'arrow-right' => '<path d="M4 10h11M11 5l5 5-5 5"/>',
        'chevron-right' => '<path d="M8 5l5 5-5 5"/>',
        'database' => '<ellipse cx="10" cy="5" rx="6" ry="2.5"/><path d="M4 5v10c0 1.4 2.7 2.5 6 2.5s6-1.1 6-2.5V5M4 10c0 1.4 2.7 2.5 6 2.5s6-1.1 6-2.5"/>',
        'close' => '<path d="M5 5l10 10M15 5 5 15"/>',
        'edit' => '<path d="M4 16h3l8.5-8.5-3-3L4 13z"/><path d="M11 6l3 3"/>',
        'trash' => '<path d="M4 6h12M8 6V4h4v2M6 6l1 11h6l1-11"/>',
        'alert' => '<circle cx="10" cy="10" r="7"/><path d="M10 6.5v4M10 13.5v.01"/>',
        'eye' => '<path d="M2 10s3-5.5 8-5.5S18 10 18 10s-3 5.5-8 5.5S2 10 2 10z"/><circle cx="10" cy="10" r="2.5"/>',
    ];
    $body = $paths[$name] ?? $paths['file'];
    return '<svg class="' . e($class) . '" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="' . $stroke
        . '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
}

/** Human detail line for a record, as shown in the record tables. */
function record_detail(array $record): string
{
    return match ($record['type']) {
        'grade' => $record['title'] . ' — ' . $record['value'],
        'attendance' => $record['value'] . (!empty($record['note']) ? ' · ' . preg_replace('/^Arrived\s+/i', '', $record['note']) : ''),
        'library' => $record['value'] . ' "' . $record['title'] . '"',
        default => $record['title'] . ' ' . $record['value'],
    };
}

function record_type_label(string $type): string
{
    return ['grade' => 'Grade', 'attendance' => 'Attendance', 'library' => 'Library'][$type] ?? ucfirst($type);
}
