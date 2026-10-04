<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Core\Http;
use RuntimeException;

/**
 * API 1: answers staff questions about records with Gemini.
 *
 * 1. Guess the scope from the question (student, section, record types, dates) and pull those rows (capped).
 * 2. Live: send the question + rows + pre-computed totals to Gemini generateContent, ask for a short JSON answer.
 *    Demo (no GEMINI_API_KEY): build a canned answer from the same rows.
 *
 * The API key stays on the server. Docs: https://ai.google.dev/api/generate-content
 */
class GeminiService
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';
    private const MAX_ROWS = 400;
    private const DEFAULT_MODEL = 'gemini-3.8-flash';
    private const MAX_TRIES = 3;
    /** Busy / rate-limited answers worth retrying with backoff. */
    private const RETRY_STATUSES = [429, 500, 503, 504];

    private ?string $apiKey;
    private string $model;
    private ?string $thinkingLevel;

    public function __construct()
    {
        $this->apiKey = Env::get('GEMINI_API_KEY');
        $this->model = Env::get('GEMINI_MODEL', self::DEFAULT_MODEL);
        // Gemini 3 thinking depth: minimal | low | medium | high. Empty = the model's own default.
        $this->thinkingLevel = Env::get('GEMINI_THINKING_LEVEL', 'low');
    }

    public function isLive(): bool
    {
        return $this->apiKey !== null;
    }

    /**
     * @return array{answer: string, table: ?array, records_used: int, scope: string, mode: string, student: ?array}
     * @throws RuntimeException with a friendly message when the live call fails
     */
    public function ask(string $question, ?int $studentId = null): array
    {
        $context = $this->gatherContext($question, $studentId);
        $result = $this->isLive() ? $this->callGemini($question, $context) : $this->demoAnswer($question, $context);

        return $result + [
            'records_used' => count($context['rows']),
            'scope' => $context['scope'],
            'mode' => $this->isLive() ? 'live' : 'demo',
            'student' => count($context['students']) === 1 ? $context['students'][0] : null,
        ];
    }

    // ------------------------------------------------------------------ scope

    /** Work out which records the question is about and load them. */
    private function gatherContext(string $question, ?int $studentId): array
    {
        $q = mb_strtolower($question);

        // Students named in the question (full name, first/last name, or student number).
        $all = Database::all('SELECT id, student_no, first_name, last_name, section, email, guardian_name FROM students');
        $students = [];
        foreach ($all as $s) {
            $full = mb_strtolower($s['first_name'] . ' ' . $s['last_name']);
            $names = [$full, mb_strtolower($s['first_name']), mb_strtolower($s['last_name'])];
            $hit = str_contains($q, mb_strtolower($s['student_no'])) || ($studentId !== null && (int) $s['id'] === $studentId);
            foreach ($names as $name) {
                if (mb_strlen($name) >= 3 && preg_match('/\b' . preg_quote($name, '/') . '(\'s)?\b/u', $q)) {
                    $hit = true;
                }
            }
            if ($hit) {
                $students[$s['id']] = $s;
            }
        }
        // "Juan Dela Cruz" also matches the surname "Cruz" of another student: prefer full-name matches.
        $fullMatches = array_filter($students, static fn ($s) => str_contains($q, mb_strtolower($s['first_name'] . ' ' . $s['last_name'])));
        if ($fullMatches) {
            $students = $fullMatches;
        }
        $students = array_values($students);

        $section = null;
        if (preg_match('/\b(?:10\s*-?\s*|section\s+|grade\s*10\s*-?\s*)([ab])\b/i', $question, $m)) {
            $section = '10-' . strtoupper($m[1]);
        }

        $types = [];
        if (preg_match('/grade|score|math|science|english|subject|risk|fail|average|top|best|honor/i', $q)) {
            $types[] = 'grade';
        }
        if (preg_match('/absen|late|tard|attend|present|miss/i', $q)) {
            $types[] = 'attendance';
        }
        if (preg_match('/book|librar|overdue|borrow|return/i', $q)) {
            $types[] = 'library';
        }

        // "Summarize Juan's record" means everything on file for Juan, not one record type.
        if ($students && preg_match('/summar|record|overview|doing|progress|report/i', $q)) {
            $types = [];
        }

        [$from, $to, $rangeLabel] = $this->dateRange($q);

        $where = [];
        $params = [];
        if ($students) {
            $where[] = 'r.student_id IN (' . implode(',', array_fill(0, count($students), '?')) . ')';
            array_push($params, ...array_column($students, 'id'));
        }
        if ($section && !$students) {
            $where[] = 's.section = ?';
            $params[] = $section;
        }
        if ($types && !($students && count($types) === 3)) {
            $where[] = 'r.type IN (' . implode(',', array_fill(0, count($types), '?')) . ')';
            array_push($params, ...$types);
        }
        if ($from) {
            $where[] = 'r.recorded_on >= ?';
            $params[] = $from;
        }
        if ($to) {
            $where[] = 'r.recorded_on <= ?';
            $params[] = $to;
        }

        $rows = Database::all(
            'SELECT r.id, r.type, r.title, r.value, r.note, r.recorded_on, s.id AS student_id, s.student_no,
                    s.first_name, s.last_name, s.section, u.name AS recorded_by
             FROM records r JOIN students s ON s.id = r.student_id LEFT JOIN users u ON u.id = r.recorded_by'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY r.recorded_on DESC, r.id DESC LIMIT ' . self::MAX_ROWS,
            $params
        );

        // Scope line shown under the answer: "Based on 50 attendance records, Sept 28 to Oct 2"
        $typeLabel = count($types) === 1 ? $types[0] . ' ' : '';
        if (count($students) === 1) {
            $scope = 'records for student ' . $students[0]['student_no'];
        } else {
            $scope = $typeLabel . 'records' . ($section ? ' in ' . $section : '');
        }
        if ($rows) {
            $dates = array_column($rows, 'recorded_on');
            $first = min($dates);
            $last = max($dates);
            $scope .= ', ' . ($first === $last ? fmt_date($first) : fmt_date($first) . ' to ' . fmt_date($last));
        }

        $understood = $students || $section || $types || $from;

        return compact('rows', 'students', 'section', 'types', 'from', 'to', 'rangeLabel', 'scope', 'understood');
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string} from, to, label */
    private function dateRange(string $q): array
    {
        $latest = Database::value("SELECT MAX(recorded_on) FROM records WHERE type = 'attendance' AND recorded_on <= CURDATE()") ?? date('Y-m-d');
        return match (true) {
            str_contains($q, 'yesterday') => [
                $d = Database::value("SELECT MAX(recorded_on) FROM records WHERE type = 'attendance' AND recorded_on < ?", [$latest]) ?? $latest,
                $d,
                'yesterday',
            ],
            (bool) preg_match('/\btoday\b/', $q) => [$latest, $latest, 'today'],
            str_contains($q, 'last week') => [date('Y-m-d', strtotime('monday last week')), date('Y-m-d', strtotime('sunday last week')), 'last week'],
            str_contains($q, 'this week') || str_contains($q, 'week') => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d'), 'this week'],
            str_contains($q, 'last month') => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('first day of last month')), 'last month'],
            str_contains($q, 'this month') || str_contains($q, 'month') => [date('Y-m-01'), date('Y-m-d'), 'this month'],
            (bool) preg_match('/(?:last|past)\s+(\d{1,3})\s+days?/', $q, $m) => [date('Y-m-d', strtotime('-' . (int) $m[1] . ' days')), date('Y-m-d'), "last {$m[1]} days"],
            default => [null, null, null],
        };
    }

    // ------------------------------------------------------------------ live

    private function callGemini(string $question, array $context): array
    {
        $prompt = "Question from a staff member:\n{$question}\n\n"
            . 'Today is ' . date('l, F j, Y') . ".\n"
            . ($context['rangeLabel'] ? "The question is about: {$context['rangeLabel']}.\n" : '')
            . 'Records provided: ' . count($context['rows']) . " (newest first, capped at " . self::MAX_ROWS . ").\n\n"
            . "PER-STUDENT TOTALS (computed from the records below):\n" . $this->totals($context['rows']) . "\n\n"
            . "RECORDS (date | student | student no | section | type | title | value | note | recorded by):\n"
            . $this->rowsAsText($context['rows']);

        $payload = [
            'systemInstruction' => ['parts' => [['text' =>
                'You are the AI Assistant inside CampusIQ, a school records system. Staff ask about grades, attendance and library records. '
                . 'Answer ONLY from the records and totals given. Never invent students, dates or numbers. If the records do not answer the question, say so plainly. '
                . 'Keep the answer short and plain: 1 to 3 sentences, no markdown, no bullet points. Attendance values are Present, Late or Absent; "present" counts include late. '
                . 'Grades below 80 are "at risk". When the answer is a list of several students, also return a small table (at most 3 columns, at most 8 rows) and keep the sentence short. '
                . 'Otherwise return no table.',
            ]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 2048,
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'answer' => ['type' => 'STRING', 'description' => 'Short plain-language answer, 1 to 3 sentences.'],
                        'table' => [
                            'type' => 'OBJECT',
                            'description' => 'Optional small table when listing several students.',
                            'properties' => [
                                'columns' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                                'rows' => ['type' => 'ARRAY', 'items' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']]],
                            ],
                            'required' => ['columns', 'rows'],
                        ],
                    ],
                    'required' => ['answer'],
                ],
            ],
        ];
        // Gemini 3 models default to medium/high thinking; a lower level keeps answers fast and cheap.
        if ($this->thinkingLevel !== null) {
            $payload['generationConfig']['thinkingConfig'] = ['thinkingLevel' => strtolower($this->thinkingLevel)];
        }

        // Up to 3 tries with backoff (1 s, then 2 s) when Gemini is busy or rate limited.
        for ($try = 1; ; $try++) {
            $response = $this->send($payload);
            $data = json_decode($response['body'], true) ?: [];
            if ($response['status'] === 200) {
                break;
            }

            $apiMessage = $data['error']['message'] ?? $response['error'] ?? 'no response';
            log_message('error', "Gemini HTTP {$response['status']} (try {$try} of " . self::MAX_TRIES . "): {$apiMessage}");
            $retryable = in_array($response['status'], self::RETRY_STATUSES, true);
            if ($retryable && $try < self::MAX_TRIES) {
                usleep((int) (1000000 * 2 ** ($try - 1) + random_int(0, 250000)));
                continue;
            }
            throw new RuntimeException($retryable
                ? 'AI is busy, try again in a minute.'
                : $this->friendlyError($response['status'], (string) ($data['error']['status'] ?? ''), (string) $apiMessage));
        }

        if (!empty($data['promptFeedback']['blockReason'])) {
            throw new RuntimeException('Gemini declined to answer that question. Try rephrasing it.');
        }

        $text = '';
        foreach ($data['candidates'][0]['content']['parts'] ?? [] as $part) {
            $text .= $part['text'] ?? '';
        }
        $parsed = json_decode($text, true);
        if (!is_array($parsed) || empty($parsed['answer'])) {
            if (trim($text) === '') {
                log_message('error', 'Gemini returned no text. finishReason=' . ($data['candidates'][0]['finishReason'] ?? '?'));
                throw new RuntimeException('Gemini sent back an empty answer. Try asking again.');
            }
            return ['answer' => trim($text), 'table' => null];
        }

        return ['answer' => trim((string) $parsed['answer']), 'table' => $this->cleanTable($parsed['table'] ?? null)];
    }

    /** One generateContent request. */
    protected function send(array $payload): array
    {
        return Http::postJson(
            sprintf(self::ENDPOINT, rawurlencode($this->model)),
            $payload,
            ['x-goog-api-key' => (string) $this->apiKey],
            25
        );
    }

    private function friendlyError(int $status, string $apiStatus, string $message): string
    {
        return match (true) {
            $status === 0 && $message === 'timeout' => 'Gemini took too long to answer. Try again, or ask a narrower question.',
            $status === 0 => 'Could not reach Gemini. Check the internet connection and try again.',
            $status === 400 && stripos($message, 'api key') !== false,
            $status === 401, $status === 403 => 'Gemini rejected the API key. Check GEMINI_API_KEY in .env.',
            $status === 404 => 'Gemini doesn\'t know the model "' . $this->model . '". Check GEMINI_MODEL in .env.',
            $status === 429 || $apiStatus === 'RESOURCE_EXHAUSTED' => 'The Gemini quota is used up for now. Wait a minute and try again.',
            $status >= 500 => 'Gemini is busy right now. Try again in a moment.',
            default => 'Gemini couldn\'t answer that (error ' . $status . '). Try rephrasing the question.',
        };
    }

    private function rowsAsText(array $rows): string
    {
        $lines = [];
        foreach ($rows as $r) {
            $lines[] = implode(' | ', [
                $r['recorded_on'], $r['first_name'] . ' ' . $r['last_name'], $r['student_no'], $r['section'],
                $r['type'], $r['title'], $r['value'], $r['note'] ?? '', $r['recorded_by'] ?? ($r['type'] === 'library' ? 'Library desk' : ''),
            ]);
        }

        return $lines ? implode("\n", $lines) : '(no records matched)';
    }

    /** Per-student totals so the model doesn't have to count hundreds of rows itself. */
    private function totals(array $rows): string
    {
        $by = [];
        foreach ($rows as $r) {
            $key = $r['first_name'] . ' ' . $r['last_name'] . ' (' . $r['student_no'] . ', ' . $r['section'] . ')';
            $by[$key] ??= ['Present' => 0, 'Late' => [], 'Absent' => [], 'grades' => [], 'library' => []];
            if ($r['type'] === 'attendance') {
                if ($r['value'] === 'Present') {
                    $by[$key]['Present']++;
                } else {
                    $by[$key][$r['value']][] = $r['recorded_on'];
                }
            } elseif ($r['type'] === 'grade') {
                $by[$key]['grades'][] = "{$r['title']} {$r['value']}";
            } else {
                $by[$key]['library'][] = "{$r['value']} \"{$r['title']}\"" . ($r['note'] ? " ({$r['note']})" : '');
            }
        }

        $lines = [];
        foreach ($by as $name => $t) {
            $parts = [];
            $days = $t['Present'] + count($t['Late']) + count($t['Absent']);
            if ($days) {
                $parts[] = "attendance: {$days} days, " . count($t['Late']) . ' late' . ($t['Late'] ? ' (' . implode(', ', $t['Late']) . ')' : '')
                    . ', ' . count($t['Absent']) . ' absent' . ($t['Absent'] ? ' (' . implode(', ', $t['Absent']) . ')' : '');
            }
            if ($t['grades']) {
                $parts[] = 'grades: ' . implode(', ', $t['grades']);
            }
            if ($t['library']) {
                $parts[] = 'library: ' . implode(', ', $t['library']);
            }
            $lines[] = "- {$name}: " . implode('; ', $parts);
        }

        return $lines ? implode("\n", $lines) : '(none)';
    }

    private function cleanTable(mixed $table): ?array
    {
        if (!is_array($table) || empty($table['columns']) || empty($table['rows']) || !is_array($table['rows'])) {
            return null;
        }
        $columns = array_slice(array_map('strval', (array) $table['columns']), 0, 4);
        $rows = [];
        foreach (array_slice($table['rows'], 0, 10) as $row) {
            if (is_array($row)) {
                $rows[] = array_slice(array_map(static fn ($c) => is_scalar($c) ? (string) $c : '', $row), 0, count($columns));
            }
        }

        return $rows ? ['columns' => $columns, 'rows' => $rows] : null;
    }

    // ------------------------------------------------------------------ demo

    /** Canned answer built from the real rows, so demo mode still says true things. */
    private function demoAnswer(string $question, array $context): array
    {
        $q = mb_strtolower($question);
        $rows = $context['rows'];
        $when = $context['rangeLabel'] ? ' ' . $context['rangeLabel'] : '';

        if (!$rows) {
            return ['answer' => 'I couldn\'t find any records that match that question' . $when . '. Try naming a student, a section like 10-A, or a record type.', 'table' => null];
        }

        if (count($context['students']) === 1) {
            return $this->demoStudentSummary($context['students'][0], $rows);
        }

        if (!$context['understood']) {
            return $this->demoOverview($rows);
        }

        $types = $context['types'];
        if (in_array('library', $types, true) && !in_array('attendance', $types, true)) {
            return $this->demoLibrary($rows, $q);
        }
        if (in_array('grade', $types, true) && !in_array('attendance', $types, true)) {
            return $this->demoGrades($rows, $q);
        }

        return $this->demoAttendance($rows, $q, $when);
    }

    private function demoStudentSummary(array $student, array $rows): array
    {
        $first = $student['first_name'];
        $att = array_filter($rows, static fn ($r) => $r['type'] === 'attendance');
        $att = array_reverse($att);
        $late = array_values(array_filter($att, static fn ($r) => $r['value'] === 'Late'));
        $absent = array_values(array_filter($att, static fn ($r) => $r['value'] === 'Absent'));
        $grades = array_values(array_filter($rows, static fn ($r) => $r['type'] === 'grade'));
        $books = $this->booksOut($rows);

        $sentences = [];
        if ($att) {
            $rate = (int) round((count($att) - count($absent)) / count($att) * 100);
            $sentences[] = "{$first}'s attendance is {$rate}% over " . count($att) . ' school days, with '
                . $this->countPhrase(count($late), 'late arrival') . (count($late) ? ' (' . implode(', ', array_map(static fn ($r) => fmt_date($r['recorded_on']), $late)) . ')' : '')
                . ' and ' . $this->countPhrase(count($absent), 'absence') . (count($absent) ? ' (' . implode(', ', array_map(static fn ($r) => fmt_date($r['recorded_on']), $absent)) . ')' : '') . '.';
        }
        if ($grades) {
            $list = array_map(static fn ($r) => "{$r['title']} {$r['value']}", $grades);
            $avg = round(array_sum(array_column($grades, 'value')) / count($grades), 1);
            $sentences[] = 'Posted grades: ' . implode(', ', $list) . " (average {$avg}).";
        }
        if ($books) {
            $sentences[] = 'Library: ' . implode('; ', array_map(static fn ($b) => "\"{$b['title']}\" is " . strtolower($b['value']) . ($b['note'] ? " ({$b['note']})" : ''), $books)) . '.';
        } elseif (array_filter($rows, static fn ($r) => $r['type'] === 'library')) {
            $sentences[] = 'No library books are out.';
        }

        return ['answer' => $sentences ? implode(' ', $sentences) : "{$first} has no records in that range yet.", 'table' => null];
    }

    private function demoAttendance(array $rows, string $q, string $when): array
    {
        $per = [];
        foreach ($rows as $r) {
            if ($r['type'] !== 'attendance') {
                continue;
            }
            $name = $r['first_name'] . ' ' . $r['last_name'];
            $per[$name] ??= ['Late' => [], 'Absent' => [], 'days' => 0];
            $per[$name]['days']++;
            if ($r['value'] !== 'Present') {
                $per[$name][$r['value']][] = $r['recorded_on'];
            }
        }
        if (!$per) {
            return ['answer' => 'There are no attendance records' . $when . ' yet.', 'table' => null];
        }

        $threshold = preg_match('/more than (twice|two|2)/', $q) ? 3 : (preg_match('/more than (once|one|1)/', $q) ? 2 : 1);
        $onlyAbsent = str_contains($q, 'absen') && !str_contains($q, 'late');
        $onlyLate = str_contains($q, 'late') && !str_contains($q, 'absen');

        $flagged = [];
        foreach ($per as $name => $t) {
            $count = $onlyAbsent ? count($t['Absent']) : ($onlyLate ? count($t['Late']) : count($t['Absent']) + count($t['Late']));
            if ($count >= $threshold) {
                $flagged[$name] = $t + ['count' => $count];
            }
        }
        uasort($flagged, static fn ($a, $b) => $b['count'] <=> $a['count']);

        $days = array_unique(array_column(array_filter($rows, static fn ($r) => $r['type'] === 'attendance'), 'recorded_on'));
        $totalMarks = array_sum(array_column($per, 'days'));
        $totalAbsent = array_sum(array_map(static fn ($t) => count($t['Absent']), $per));
        $totalLate = array_sum(array_map(static fn ($t) => count($t['Late']), $per));
        $rate = $totalMarks ? (int) round(($totalMarks - $totalAbsent) / $totalMarks * 100) : 0;

        if (!$flagged) {
            return ['answer' => "No one matches that{$when}. Attendance was {$rate}% across " . $this->countPhrase(count($days), 'school day') . " ({$totalLate} late, {$totalAbsent} absent).", 'table' => null];
        }

        $what = $onlyAbsent ? 'absent' : ($onlyLate ? 'late' : 'absent or late');
        $howOften = match ($threshold) { 3 => ' more than twice', 2 => ' more than once', default => '' };
        $answer = ucfirst($this->countPhrase(count($flagged), 'student')) . ' ' . (count($flagged) === 1 ? 'was' : 'were') . " {$what}{$howOften}{$when}. "
            . "Overall attendance was {$rate}% across " . $this->countPhrase(count($days), 'school day') . '.';

        $table = ['columns' => ['Student', 'Times', 'Days'], 'rows' => []];
        foreach (array_slice($flagged, 0, 8, true) as $name => $t) {
            $times = array_filter([
                count($t['Absent']) && !$onlyLate ? count($t['Absent']) . ' absent' : null,
                count($t['Late']) && !$onlyAbsent ? count($t['Late']) . ' late' : null,
            ]);
            $dates = array_merge($onlyLate ? [] : $t['Absent'], $onlyAbsent ? [] : $t['Late']);
            sort($dates);
            $table['rows'][] = [$name, implode(', ', $times), implode(', ', array_map(static fn ($d) => date('D j', strtotime($d)), $dates))];
        }

        return ['answer' => $answer, 'table' => $table];
    }

    private function demoGrades(array $rows, string $q): array
    {
        $grades = array_values(array_filter($rows, static fn ($r) => $r['type'] === 'grade'));
        if (preg_match('/\b(math|science|english)\b/', $q, $m)) {
            $grades = array_values(array_filter($grades, static fn ($r) => stripos($r['title'], $m[1]) !== false));
        }
        if (!$grades) {
            return ['answer' => 'No grades match that yet.', 'table' => null];
        }

        if (preg_match('/risk|fail|low|below|struggl/', $q)) {
            $low = array_values(array_filter($grades, static fn ($r) => (float) $r['value'] < 80));
            usort($low, static fn ($a, $b) => (float) $a['value'] <=> (float) $b['value']);
            if (!$low) {
                return ['answer' => 'No one is at risk: every posted grade there is 80 or above.', 'table' => null];
            }
            return [
                'answer' => ucfirst($this->countPhrase(count($low), 'grade')) . ' ' . (count($low) === 1 ? 'is' : 'are') . ' below 80, the at-risk line. A quick check-in with these students may help.',
                'table' => ['columns' => ['Student', 'Subject', 'Grade'], 'rows' => array_map(
                    static fn ($r) => [$r['first_name'] . ' ' . $r['last_name'], $r['title'], $r['value']],
                    array_slice($low, 0, 8)
                )],
            ];
        }

        usort($grades, static fn ($a, $b) => (float) $b['value'] <=> (float) $a['value']);
        $avg = round(array_sum(array_column($grades, 'value')) / count($grades), 1);

        return [
            'answer' => 'The average across ' . count($grades) . " posted grades is {$avg}. The highest is {$grades[0]['first_name']} {$grades[0]['last_name']} with {$grades[0]['value']} in {$grades[0]['title']}.",
            'table' => ['columns' => ['Student', 'Subject', 'Grade'], 'rows' => array_map(
                static fn ($r) => [$r['first_name'] . ' ' . $r['last_name'], $r['title'], $r['value']],
                array_slice($grades, 0, 5)
            )],
        ];
    }

    private function demoLibrary(array $rows, string $q): array
    {
        $books = $this->booksOut($rows);
        $overdue = array_values(array_filter($books, static fn ($b) => $b['value'] === 'Overdue'));
        $list = str_contains($q, 'overdue') ? $overdue : $books;

        if (!$list) {
            return ['answer' => str_contains($q, 'overdue') ? 'No library books are overdue right now.' : 'No library books are out right now.', 'table' => null];
        }

        $answer = str_contains($q, 'overdue')
            ? ucfirst($this->countPhrase(count($overdue), 'book')) . ' ' . (count($overdue) === 1 ? 'is' : 'are') . ' overdue. ' . (count($books) - count($overdue)) . ' more are borrowed and not yet due.'
            : ucfirst($this->countPhrase(count($books), 'book')) . ' ' . (count($books) === 1 ? 'is' : 'are') . ' out, ' . count($overdue) . ' of them overdue.';

        return ['answer' => $answer, 'table' => ['columns' => ['Student', 'Book', 'Status'], 'rows' => array_map(
            static fn ($b) => [$b['student'], $b['title'], $b['value'] . ($b['note'] ? ' · ' . $b['note'] : '')],
            array_slice($list, 0, 8)
        )]];
    }

    /** Latest status per (student, book); keep the ones still out. */
    private function booksOut(array $rows): array
    {
        $latest = [];
        foreach ($rows as $r) { // rows are newest first
            if ($r['type'] !== 'library') {
                continue;
            }
            $key = $r['student_id'] . '|' . mb_strtolower($r['title']);
            $latest[$key] ??= ['student' => $r['first_name'] . ' ' . $r['last_name'], 'title' => $r['title'], 'value' => $r['value'], 'note' => $r['note']];
        }

        return array_values(array_filter($latest, static fn ($b) => $b['value'] !== 'Returned'));
    }

    /** Question the demo can't place: say what it can do, with a true overview. */
    private function demoOverview(array $rows): array
    {
        $att = array_filter($rows, static fn ($r) => $r['type'] === 'attendance');
        $absent = count(array_filter($att, static fn ($r) => $r['value'] === 'Absent'));
        $rate = $att ? (int) round((count($att) - $absent) / count($att) * 100) : 0;
        $students = count(array_unique(array_column($rows, 'student_id')));
        $books = count($this->booksOut($rows));

        return ['answer' => "I can only answer from the school's records. Right now they cover {$students} students: attendance is {$rate}% overall, "
            . $this->countPhrase($books, 'library book') . ' ' . ($books === 1 ? 'is' : 'are') . ' out. Ask about attendance, grades, library books, a section like 10-A, or a student by name.', 'table' => null];
    }

    private function countPhrase(int $n, string $noun): string
    {
        $words = ['no', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten'];
        return ($words[$n] ?? (string) $n) . ' ' . $noun . ($n === 1 ? '' : 's');
    }
}
