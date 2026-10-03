<?php
/**
 * Seeds CampusIQ with demo data. Run through database/reset.sh, or: php database/seed.php
 *
 * - 2 staff, 10 students in 10-A and 10-B, ~3 weeks of attendance (last 15 school days),
 *   a few grades and library records, 1 student login and 1 parent login for Juan.
 * - All demo passwords: password123
 * - If DEMO_EMAIL is set (me@gmail.com), student and guardian emails use plus addressing
 *   (me+juan@gmail.com, me+juan.guardian@gmail.com) so live emails land in one inbox.
 *   Otherwise they use @example.com.
 */

if (PHP_SAPI !== 'cli') {
    exit("Run from the command line.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Core\Env;

mt_srand(2026);
$pdo = Database::pdo();
$now = new DateTimeImmutable('now');

// ---------------------------------------------------------------- emails

$demoEmail = Env::get('DEMO_EMAIL');
$demoParts = null;
if ($demoEmail !== null && filter_var($demoEmail, FILTER_VALIDATE_EMAIL)) {
    [$local, $domain] = explode('@', $demoEmail, 2);
    $demoParts = [explode('+', $local)[0], $domain];
}

function seedEmail(?array $demoParts, string $tag, string $fallback): string
{
    return $demoParts ? "{$demoParts[0]}+{$tag}@{$demoParts[1]}" : $fallback;
}

// ---------------------------------------------------------------- staff

$password = password_hash('password123', PASSWORD_DEFAULT);
$insertUser = $pdo->prepare(
    'INSERT INTO users (role, id_number, name, email, password_hash, student_id, staff_title, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$seedStart = $now->modify('-60 days')->format('Y-m-d 08:00:00');

$insertUser->execute(['staff', 'T-0012', 'Ms. Santos', 'm.santos@example.com', $password, null, 'Class Adviser', $seedStart]);
$santosId = (int) $pdo->lastInsertId();
$insertUser->execute(['staff', 'T-0015', 'Mr. Garcia', 'r.garcia@example.com', $password, null, 'Teacher', $seedStart]);
$garciaId = (int) $pdo->lastInsertId();

// ---------------------------------------------------------------- students

$students = [
    // student_no, first, last, section, guardian name, slug for email
    ['10-24031', 'Juan', 'Dela Cruz', '10-A', 'Rosa Dela Cruz', 'juan'],
    ['10-24032', 'Maria', 'Reyes', '10-A', 'Elena Reyes', 'maria'],
    ['10-24033', 'Paolo', 'Cruz', '10-A', 'Ramon Cruz', 'paolo'],
    ['10-24034', 'Ana', 'Lim', '10-A', 'Grace Lim', 'ana'],
    ['10-24035', 'Kevin', 'Tan', '10-A', 'Henry Tan', 'kevin'],
    ['10-24036', 'Bea', 'Santiago', '10-B', 'Liza Santiago', 'bea'],
    ['10-24037', 'Carlo', 'Mendoza', '10-B', 'Teresa Mendoza', 'carlo'],
    ['10-24038', 'Jasmine', 'Villanueva', '10-B', 'Mark Villanueva', 'jasmine'],
    ['10-24039', 'Miguel', 'Bautista', '10-B', 'Joy Bautista', 'miguel'],
    ['10-24040', 'Sofia', 'Navarro', '10-B', 'Paul Navarro', 'sofia'],
];

$insertStudent = $pdo->prepare(
    'INSERT INTO students (student_no, first_name, last_name, section, email, guardian_name, guardian_email, status, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$studentIds = [];
foreach ($students as [$no, $first, $last, $section, $guardian, $slug]) {
    $lastSlug = strtolower(str_replace(' ', '', $last));
    $guardianSlug = strtolower($guardian[0]) . '.' . $lastSlug;
    $insertStudent->execute([
        $no, $first, $last, $section,
        seedEmail($demoParts, $slug, "{$slug}.{$lastSlug}@example.com"),
        $guardian,
        seedEmail($demoParts, "{$slug}.guardian", "{$guardianSlug}@example.com"),
        'active',
        $seedStart,
    ]);
    $studentIds[$slug] = (int) $pdo->lastInsertId();
}

// ---------------------------------------------------------------- logins for Juan

$juan = $pdo->query("SELECT * FROM students WHERE student_no = '10-24031'")->fetch();
$insertUser->execute(['student', '10-24031', 'Juan Dela Cruz', $juan['email'], $password, $juan['id'], null, $seedStart]);
$insertUser->execute(['parent', 'P-10-24031', $juan['guardian_name'], $juan['guardian_email'], $password, $juan['id'], null, $seedStart]);

// ---------------------------------------------------------------- attendance

// The last 15 school days (Mon to Fri), oldest first, ending today if today is a school day.
$schoolDays = [];
$day = $now;
while (count($schoolDays) < 15) {
    if ((int) $day->format('N') <= 5) {
        array_unshift($schoolDays, $day);
    }
    $day = $day->modify('-1 day');
}
$lastWeek = array_slice($schoolDays, -5);

$insertRecord = $pdo->prepare(
    'INSERT INTO records (student_id, type, title, value, note, recorded_by, recorded_on, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);

/** Fixed story beats so the demo data matches the design screens. */
function scripted(string $slug, int $index, int $total): ?string
{
    $isLastWeek = $index >= $total - 5;
    $weekday = $index % 5;
    return match (true) {
        $slug === 'juan' && $index === 3 => 'Late',
        $slug === 'juan' => 'Present',
        $slug === 'maria' && $isLastWeek && in_array($weekday, [0, 1, 3], true) => 'Absent',
        $slug === 'paolo' && $isLastWeek && in_array($weekday, [0, 2], true) => 'Late',
        $slug === 'paolo' && $isLastWeek && $weekday === 4 => 'Absent',
        $slug === 'ana' && $isLastWeek && in_array($weekday, [1, 2, 4], true) => 'Late',
        default => null,
    };
}

$recordCount = 0;
foreach ($schoolDays as $index => $date) {
    foreach ($studentIds as $slug => $studentId) {
        $status = scripted($slug, $index, count($schoolDays));
        if ($status === null) {
            $roll = mt_rand(1, 100);
            $status = $roll <= 88 ? 'Present' : ($roll <= 95 ? 'Late' : 'Absent');
        }
        $note = $status === 'Late' ? sprintf('Arrived 7:%02d AM', mt_rand(41, 58)) : null;
        $createdAt = $date->format('Y-m-d') . sprintf(' 07:%02d:%02d', mt_rand(30, 59), mt_rand(0, 59));
        $insertRecord->execute([$studentId, 'attendance', 'Daily attendance', $status, $note, $santosId, $date->format('Y-m-d'), $createdAt]);
        $recordCount++;
    }
}

// ---------------------------------------------------------------- grades

$gradeDay = $schoolDays[6];
$gradeDay2 = $schoolDays[9];
$grades = [
    // slug => [Q1 Math, Q1 Science, Q1 English]
    'juan' => [95, 90, 92],
    'maria' => [84, 86, 89],
    'paolo' => [81, 88, 83],
    'ana' => [90, 89, 94],
    'kevin' => [74, 79, 80],
    'bea' => [88, 91, 87],
    'carlo' => [76, 82, 78],
    'jasmine' => [93, 95, 90],
    'miguel' => [79, 77, 85],
    'sofia' => [91, 87, 92],
];
foreach ($grades as $slug => [$math, $science, $english]) {
    $sid = $studentIds[$slug];
    $insertRecord->execute([$sid, 'grade', 'Quarter 1 Math', (string) $math, null, $garciaId, $gradeDay->format('Y-m-d'), $gradeDay->format('Y-m-d 15:12:00')]);
    $insertRecord->execute([$sid, 'grade', 'Quarter 1 Science', (string) $science, null, $garciaId, $gradeDay2->format('Y-m-d'), $gradeDay2->format('Y-m-d 14:05:00')]);
    $insertRecord->execute([$sid, 'grade', 'Quarter 1 English', (string) $english, null, $santosId, $gradeDay2->format('Y-m-d'), $gradeDay2->format('Y-m-d 15:40:00')]);
    $recordCount += 3;
}

// ---------------------------------------------------------------- library

$library = [
    // slug, title, value, school day index, due offset in days (null = no due date)
    ['juan', 'Intro to Algorithms', 'Returned', 7, null],
    ['juan', 'Noli Me Tangere', 'Borrowed', 8, 14],
    ['ana', 'El Filibusterismo', 'Borrowed', 10, 14],
    ['kevin', 'Florante at Laura', 'Borrowed', 0, 7],
    ['kevin', 'Florante at Laura', 'Overdue', 12, null],
    ['bea', 'Ibong Adarna', 'Borrowed', 11, 14],
    ['miguel', 'Physics for Everyone', 'Returned', 9, null],
    ['paolo', 'World History Atlas', 'Borrowed', 13, 14],
];
foreach ($library as [$slug, $title, $value, $dayIndex, $dueIn]) {
    $date = $schoolDays[$dayIndex];
    $note = $dueIn !== null ? 'Due ' . $date->modify("+{$dueIn} days")->format('M j, Y') : null;
    if ($value === 'Overdue') {
        $note = 'Was due ' . $schoolDays[0]->modify('+7 days')->format('M j, Y');
    }
    $insertRecord->execute([$studentIds[$slug], 'library', $title, $value, $note, null, $date->format('Y-m-d'), $date->format('Y-m-d 11:20:00')]);
    $recordCount++;
}

// ---------------------------------------------------------------- settings (auto email triggers, all ON)

$insertSetting = $pdo->prepare('INSERT INTO settings (`key`, value) VALUES (?, ?)');
foreach (['trigger_grade_posted', 'trigger_absence_logged', 'trigger_late_arrival', 'trigger_book_overdue'] as $key) {
    $insertSetting->execute([$key, '1']);
}

// ---------------------------------------------------------------- summary

$counts = [];
foreach (['users', 'students', 'records', 'email_logs', 'reports', 'ai_queries', 'settings'] as $table) {
    $counts[] = $table . '=' . $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
}
echo 'Seeded: ' . implode(', ', $counts) . "\n";
echo 'Emails: ' . ($demoParts ? 'plus addressing on DEMO_EMAIL' : '@example.com (DEMO_EMAIL is empty)') . "\n";
echo "Attendance days: {$schoolDays[0]->format('M j')} to {$schoolDays[14]->format('M j, Y')}\n";
