<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\EmailLog;
use App\Models\Record;
use App\Models\Report;
use App\Models\Setting;
use App\Models\Student;
use App\Services\EmailTemplateService as Templates;

/**
 * API 2: Email Alerts. Emails are sent in the browser by the EmailJS SDK; the server builds
 * the content, keeps the trigger settings and logs every result.
 */
class EmailController extends Controller
{
    public function index(Request $request): Response
    {
        $students = Student::ordered();
        $selected = (int) $request->query('student', 0) ?: (int) ($students[0]['id'] ?? 0);
        $template = array_key_exists((string) $request->query('template'), Templates::TEMPLATES) ? $request->query('template') : 'grade_posted';
        $recent = EmailLog::recent(8);

        return $this->view('email/index', [
            'title' => 'Email Alerts · CampusIQ',
            'topbar' => [
                'title' => 'Email Alerts',
                'tag' => 'API 2 · EmailJS',
                'tagClass' => 'bg-mail/10 text-mail',
                'demo' => array_slice(Templates::missingKeys(), 0, 1),
            ],
            'students' => $students,
            'selected' => $selected,
            'template' => $template,
            'reportId' => (int) $request->query('report', 0) ?: null,
            'to' => in_array($request->query('to'), ['student', 'guardian'], true) ? $request->query('to') : 'both',
            'triggers' => Templates::triggerStates(),
            'recent' => $recent,
            'last' => $recent[0] ?? null,
            'weekCount' => EmailLog::thisWeek(),
            'scripts' => ['email.js'],
        ]);
    }

    /** GET /api/email/compose?student_id=&template=&to=&report= : recipients, subject and message preview. */
    public function compose(Request $request): Response
    {
        $student = Student::find((int) $request->query('student_id', 0));
        $key = (string) $request->query('template', '');
        if (!$student || !array_key_exists($key, Templates::TEMPLATES)) {
            return $this->json(['ok' => false, 'error' => 'Pick a student and a template.'], 422);
        }
        $to = in_array($request->query('to'), ['student', 'guardian'], true) ? $request->query('to') : 'both';

        $record = null;
        if ($key === 'report_ready') {
            $report = ($id = (int) $request->query('report', 0)) ? Report::find($id) : Report::latestFor((int) $student['id']);
            if (!$report || (int) $report['student_id'] !== (int) $student['id']) {
                return $this->json(['ok' => false, 'error' => $student['first_name'] . ' has no PDF reports yet. Make one on the PDF Reports page first.']);
            }
            $record = ['recorded_on' => substr($report['created_at'], 0, 10), 'label' => ' (' . strtolower(Report::typeLabel($report['report_type'])) . ')'];
        } elseif ($key !== 'custom') {
            $record = Templates::latestRecordFor($key, (int) $student['id']);
            if (!$record) {
                $what = ['grade_posted' => 'grades', 'absence_logged' => 'absences', 'late_arrival' => 'late arrivals', 'book_overdue' => 'overdue books'][$key];
                return $this->json(['ok' => false, 'error' => "{$student['first_name']} has no {$what} on record, so there is nothing to send for this template."]);
            }
        }

        $composed = Templates::compose($key, $student, $record);
        $payload = Templates::payload($key, $student, $key === 'report_ready' ? null : $record, $composed, Templates::recipients($student, $to));

        return $this->json(['ok' => true, 'email' => $payload]);
    }

    /** GET /api/email/logs/{id} : the payload to retry a logged email. */
    public function show(Request $request, int $id): Response
    {
        $log = EmailLog::findDetailed($id) ?? Response::error(404);
        $student = Student::find((int) $log['student_id']);
        $emails = array_filter(array_map('trim', explode(',', $log['recipients'])));
        $recipients = array_map(static fn ($email) => [
            'email' => $email,
            'name' => strcasecmp($email, $student['guardian_email']) === 0 ? $student['guardian_name'] : Student::fullName($student),
            'role' => strcasecmp($email, $student['guardian_email']) === 0 ? 'guardian' : 'student',
        ], $emails);

        return $this->json(['ok' => true, 'email' => [
            'log_id' => (int) $log['id'],
            'student_id' => (int) $log['student_id'],
            'record_id' => $log['record_id'] ? (int) $log['record_id'] : null,
            'trigger_key' => $log['trigger_key'],
            'trigger_label' => Templates::label($log['trigger_key']),
            'student_name' => Student::fullName($student),
            'subject' => $log['subject'],
            'message' => $log['message'],
            'recipients' => array_values($recipients),
        ]]);
    }

    /** POST /api/email/log : record what EmailJS did (or the demo). Retries update the same row. */
    public function log(Request $request): Response
    {
        $student = Student::find((int) $request->input('student_id', 0));
        if (!$student) {
            return $this->json(['ok' => false, 'error' => 'Unknown student.'], 422);
        }

        $allowed = [strtolower($student['email']), strtolower($student['guardian_email'])];
        $recipients = array_values(array_unique(array_filter(array_map(
            static fn ($e) => strtolower(trim((string) $e)),
            (array) $request->input('recipients', [])
        ))));
        if (!$recipients || array_diff($recipients, $allowed)) {
            return $this->json(['ok' => false, 'error' => 'Emails can only go to the student and their guardian on file.'], 422);
        }

        $key = (string) $request->input('trigger_key', '');
        $status = (string) $request->input('status', '');
        $subject = trim((string) $request->input('subject', ''));
        $message = trim((string) $request->input('message', ''));
        if (!array_key_exists($key, Templates::TEMPLATES) || !in_array($status, EmailLog::STATUSES, true)) {
            return $this->json(['ok' => false, 'error' => 'Invalid email log.'], 422);
        }
        if ($subject === '' || $message === '' || mb_strlen($subject) > 200 || mb_strlen($message) > 5000) {
            return $this->json(['ok' => false, 'error' => 'Subject and message are required (subject up to 200 characters).'], 422);
        }
        // Without EmailJS keys nothing can really be sent: never log a fake "sent".
        if (Templates::isDemo()) {
            $status = 'demo';
        }

        $recordId = (int) $request->input('record_id', 0) ?: null;
        if ($recordId) {
            $record = Record::find($recordId);
            if (!$record || (int) $record['student_id'] !== (int) $student['id']) {
                $recordId = null;
            }
        }

        $data = [
            'student_id' => (int) $student['id'],
            'record_id' => $recordId,
            'trigger_key' => $key,
            'recipients' => implode(', ', $recipients),
            'subject' => $subject,
            'message' => $message,
            'status' => $status,
            'error' => $status === 'failed' ? mb_substr((string) $request->input('error', 'Unknown error'), 0, 255) : null,
            'sent_by' => Auth::id(),
        ];

        $logId = (int) $request->input('log_id', 0);
        $existing = $logId ? EmailLog::find($logId) : null;
        if ($existing && (int) $existing['student_id'] === (int) $student['id']) {
            EmailLog::update($logId, $data);
            $id = $logId;
        } else {
            $id = EmailLog::create($data);
        }

        $log = EmailLog::findDetailed($id);

        return $this->json([
            'ok' => true,
            'id' => $id,
            'status' => $status,
            'row_html' => View::partial('email-row', ['log' => $log]),
            'last_html' => View::partial('email-last', ['log' => $log]),
            'week_count' => EmailLog::thisWeek(),
        ]);
    }

    /** POST /api/email/triggers : turn one automatic trigger on or off. */
    public function triggers(Request $request): Response
    {
        $key = (string) $request->input('key', '');
        if (!array_key_exists($key, Templates::TRIGGERS)) {
            return $this->json(['ok' => false, 'error' => 'Unknown trigger.'], 422);
        }
        $enabled = filter_var($request->input('enabled'), FILTER_VALIDATE_BOOLEAN);
        Setting::set('trigger_' . $key, $enabled ? '1' : '0');

        return $this->json(['ok' => true, 'key' => $key, 'enabled' => $enabled]);
    }
}
