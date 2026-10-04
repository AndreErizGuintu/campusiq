<?php
/**
 * Smoke test: hits every route over HTTP and fails on an unexpected status code
 * or on any PHP error text in the body.
 *
 * Usage: php tests/smoke.php
 */

// Full bootstrap so the test can create (and remove) fixture rows. It never prints .env values.
require dirname(__DIR__) . '/app/bootstrap.php';

$base = rtrim(App\Core\Env::get('APP_URL', 'http://localhost/campusiq'), '/');
$failures = 0;
$checks = 0;

/** Fetch a URL with a cookie jar per "session" name. */
function fetch(string $url, string $session = 'guest', string $method = 'GET', array $fields = []): array
{
    $jar = sys_get_temp_dir() . "/campusiq-smoke-{$session}.txt";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HEADER => true,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $raw = curl_exec($ch);
    if ($raw === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['status' => 0, 'body' => $error, 'location' => null];
    }
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $headers = substr($raw, 0, $headerSize);
    preg_match('/^Location:\s*(.+)$/mi', $headers, $m);

    return ['status' => $status, 'body' => substr($raw, $headerSize), 'location' => isset($m[1]) ? trim($m[1]) : null];
}

function check(string $label, array $response, array $expected): void
{
    global $failures, $checks;
    $checks++;
    $problems = [];

    if (!in_array($response['status'], $expected, true)) {
        $problems[] = "status {$response['status']} (expected " . implode('/', $expected) . ')';
    }
    if (preg_match('/(Fatal error|Parse error|Warning:|Notice:|Deprecated:|Uncaught |Stack trace)/', $response['body'], $m)) {
        $problems[] = "PHP error text in body: \"{$m[1]}\"";
    }

    if ($problems) {
        $failures++;
        echo "  FAIL  {$label}: " . implode('; ', $problems) . "\n";
    } else {
        echo "  ok    {$label} [{$response['status']}]\n";
    }
}

function csrfFrom(string $html): string
{
    preg_match('/name="_csrf" value="([^"]+)"/', $html, $m);
    return $m[1] ?? '';
}

function loginAs(string $session, string $login): void
{
    global $base;
    @unlink(sys_get_temp_dir() . "/campusiq-smoke-{$session}.txt");
    $page = fetch("{$base}/login", $session);
    $response = fetch("{$base}/login", $session, 'POST', [
        '_csrf' => csrfFrom($page['body']),
        'login' => $login,
        'password' => 'password123',
    ]);
    check("login as {$login}", $response, [302]);
}

echo "CampusIQ smoke test against {$base}\n\n";

// Public pages and blocked paths
echo "Public\n";
foreach ([
    '/' => [200],
    '/.env' => [403],
    '/app/Core/Env.php' => [403],
    '/database/schema.sql' => [403],
    '/storage/logs/' => [403],
    '/design-ref/01_1_Index_landing_page.html' => [403],
    '/assets/css/app.css' => [200],
    '/assets/js/app.js' => [200],
    '/login' => [200],
    '/signup' => [200],
    '/does-not-exist' => [404],
] as $path => $expected) {
    check("GET {$path}", fetch($base . $path), $expected);
}

// Every protected page sends guests to /login
echo "\nGuests are redirected\n";
$staffPages = ['/dashboard', '/students', '/students?q=juan', '/students/1', '/students/1?type=grade', '/ai', '/ai?student=1', '/emails', '/emails?student=2&template=absence_logged', '/reports', '/reports?student=3', '/api/emails/compose?student_id=1&template=grade_posted'];
$portalPages = ['/my/records', '/my/records?type=grade', '/my/reports', '/my/notifications', '/my/notifications?id=1'];
foreach ([...$staffPages, ...$portalPages] as $path) {
    // Pages redirect to /login; JSON endpoints answer 401.
    check("GET {$path} as guest", fetch($base . $path), str_starts_with($path, '/api/') ? [401] : [302]);
}
$loginPage = fetch("{$base}/login", 'arrays');
check('POST /login with login[] (array input)', fetch("{$base}/login", 'arrays', 'POST', ['_csrf' => csrfFrom($loginPage['body']), 'login' => ['x'], 'password' => 'y']), [302]);
$signupPage = fetch("{$base}/signup", 'arrays');
check('POST /signup with email[] (array input)', fetch("{$base}/signup", 'arrays', 'POST', ['_csrf' => csrfFrom($signupPage['body']), 'role' => 'student', 'student_no' => '10-24031', 'email' => ['x'], 'password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh']), [302]);
check('POST /login without CSRF', fetch("{$base}/login", 'guest', 'POST', ['login' => 'T-0012', 'password' => 'password123']), [403]);

echo "\nStaff (T-0012)\n";
loginAs('staff', 'T-0012');
foreach ($staffPages as $path) {
    check("GET {$path}", fetch($base . $path, 'staff'), [200]);
}
// Crafted array input must never crash a page (was a 500 before the review fixes).
foreach (['/students?q[]=x', '/emails?template[]=x&student[]=1', '/reports?student[]=1', '/ai?student[]=1'] as $path) {
    check("GET {$path} (array input)", fetch($base . $path, 'staff'), [200]);
}
check('GET /students/9999 (missing)', fetch("{$base}/students/9999", 'staff'), [404]);
foreach ($portalPages as $path) {
    check("GET {$path} (portal only)", fetch($base . $path, 'staff'), [403]);
}

// The smoke test never calls a paid API: /api/reports is checked on validation only (no PDFShift call),
// and download ownership uses two temporary fixture reports (Juan = student 1, Maria = student 2), removed at the end.
$staffPage = fetch("{$base}/reports", 'staff');
preg_match('/name="csrf-token" content="([^"]+)"/', $staffPage['body'], $m);
$noSections = fetch("{$base}/api/reports", 'staff', 'POST', ['_csrf' => $m[1] ?? '', 'student_id' => 1, 'report_type' => 'full']);
check('POST /api/reports without sections (422, no API call)', $noSections, [422]);

$reportIds = [];
$fixtureFiles = [];
foreach ([1, 2] as $sid) {
    $relative = 'reports/smoke-' . bin2hex(random_bytes(4)) . '.html';
    file_put_contents(BASE_PATH . '/storage/' . $relative, '<!doctype html><html><body><h1>Smoke fixture</h1></body></html>');
    $fixtureFiles[] = BASE_PATH . '/storage/' . $relative;
    $reportIds[$sid] = App\Models\Report::create([
        'student_id' => $sid, 'report_type' => 'full', 'sections' => 'grades,attendance,library',
        'file_path' => $relative, 'size_bytes' => filesize(end($fixtureFiles)), 'mode' => 'demo', 'created_by' => 1,
    ]);
}
register_shutdown_function(static function () use ($reportIds, $fixtureFiles): void {
    foreach ($reportIds as $id) {
        App\Models\Report::delete($id);
    }
    array_map('unlink', array_filter($fixtureFiles, 'is_file'));
});
check('GET /reports/{juan}/download', fetch("{$base}/reports/{$reportIds[1]}/download", 'staff'), [200]);
check('GET /reports/{juan}/preview', fetch("{$base}/reports/{$reportIds[1]}/preview", 'staff'), [200]);
check('GET /reports/9999/download (missing)', fetch("{$base}/reports/9999/download", 'staff'), [404]);
check('GET /reports/{juan}/download as guest', fetch("{$base}/reports/{$reportIds[1]}/download"), [302]);
check('GET /storage/reports/ direct', fetch("{$base}/storage/reports/"), [403]);

foreach (['student' => '10-24031', 'parent' => 'P-10-24031'] as $session => $login) {
    echo "\n" . ucfirst($session) . " ({$login})\n";
    loginAs($session, $login);
    foreach ($portalPages as $path) {
        check("GET {$path}", fetch($base . $path, $session), [200]);
    }
    foreach ($staffPages as $path) {
        check("GET {$path} (staff only)", fetch($base . $path, $session), [403]);
    }
    check("GET /api/emails/compose (staff API)", fetch("{$base}/api/emails/compose?student_id=1&template=grade_posted", $session), [403]);
    check("GET own report download", fetch("{$base}/reports/{$reportIds[1]}/download", $session), [200]);
    check("GET other student's report (blocked)", fetch("{$base}/reports/{$reportIds[2]}/download", $session), [403]);
}

echo "\n{$checks} checks, {$failures} failed\n";
exit($failures > 0 ? 1 : 0);
