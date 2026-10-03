<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\EmailLog;
use App\Models\Record;
use App\Models\Report;
use App\Models\User;
use App\Services\EmailTemplateService;
use App\Services\PdfShiftService;

/**
 * Student / parent portal. Every page shows only the student linked to the logged-in account.
 */
class PortalController extends Controller
{
    public function records(Request $request): Response
    {
        $student = $this->linkedStudent();
        $type = in_array($request->query('type'), Record::TYPES, true) ? $request->query('type') : null;

        return $this->portalView('portal/records', $student, [
            'title' => 'My records · CampusIQ',
            'topbar' => [
                'crumbs' => [['Home', '/'], [$this->isParent() ? $student['first_name'] . '\'s records' : 'My records', null]],
                'demo' => (new PdfShiftService())->isLive() ? [] : ['PDFSHIFT_API_KEY'],
            ],
            'records' => Record::forStudent((int) $student['id'], $type),
            'counts' => Record::countsByType((int) $student['id']),
            'type' => $type,
            'stats' => $this->stats((int) $student['id']),
            'alertsOn' => in_array(true, EmailTemplateService::triggerStates(), true),
            'live' => (new PdfShiftService())->isLive(),
            'scripts' => ['reports.js'],
        ]);
    }

    public function reports(Request $request): Response
    {
        $student = $this->linkedStudent();

        return $this->portalView('portal/reports', $student, [
            'title' => 'Reports · CampusIQ',
            'topbar' => [
                'crumbs' => [['Home', '/'], ['Reports', null]],
                'demo' => (new PdfShiftService())->isLive() ? [] : ['PDFSHIFT_API_KEY'],
            ],
            'reports' => Report::forStudent((int) $student['id']),
            'live' => (new PdfShiftService())->isLive(),
            'scripts' => ['reports.js'],
        ]);
    }

    public function notifications(Request $request): Response
    {
        $student = $this->linkedStudent();
        $user = Auth::user();
        $emails = EmailLog::inbox((int) $student['id'], $this->inboxAddress($student));

        $selectedId = (int) $request->query('id', 0);
        $selected = null;
        foreach ($emails as $email) {
            if ((int) $email['id'] === $selectedId) {
                $selected = $email;
            }
        }
        $selected ??= $emails[0] ?? null;

        // Unread = arrived after the last visit. Opening the inbox marks everything as seen.
        $seenAt = $user['notifications_seen_at'];
        $unreadIds = array_map('intval', array_column(
            array_filter($emails, static fn ($e) => $seenAt === null || $e['created_at'] > $seenAt),
            'id'
        ));
        User::update((int) $user['id'], ['notifications_seen_at' => date('Y-m-d H:i:s')]);

        $record = $selected && $selected['record_id'] ? Record::findDetailed((int) $selected['record_id']) : null;

        return $this->portalView('portal/notifications', $student, [
            'title' => 'Notifications · CampusIQ',
            'topbar' => ['crumbs' => [['Home', '/'], ['Notifications', null]]],
            'emails' => $emails,
            'selected' => $selected,
            'record' => $record,
            'unreadIds' => $unreadIds,
            'explicit' => $selectedId > 0,
            'unread' => 0,
        ]);
    }

    private function portalView(string $page, array $student, array $data): Response
    {
        $user = Auth::user();
        $data += [
            'student' => $student,
            'unread' => EmailLog::unreadCount((int) $student['id'], $this->inboxAddress($student), $user['notifications_seen_at']),
            'isParent' => $this->isParent(),
        ];

        return $this->view($page, $data, 'portal');
    }

    /** Parents read the mail sent to the guardian address, students the mail sent to their own. */
    private function inboxAddress(array $student): string
    {
        return $this->isParent() ? $student['guardian_email'] : $student['email'];
    }

    private function isParent(): bool
    {
        return Auth::role() === 'parent';
    }

    /** The four stat cards on My records (design-ref 10). */
    private function stats(int $studentId): array
    {
        $month = Database::one(
            "SELECT COUNT(*) AS days, SUM(value = 'Late') AS late, SUM(value = 'Absent') AS absent
             FROM records WHERE student_id = ? AND type = 'attendance' AND recorded_on >= ? AND recorded_on <= CURDATE()",
            [$studentId, date('Y-m-01')]
        );
        $days = (int) $month['days'];

        $latestGrade = Database::one(
            "SELECT title, value, recorded_on FROM records WHERE student_id = ? AND type = 'grade' ORDER BY recorded_on DESC, id DESC LIMIT 1",
            [$studentId]
        );

        // Latest status per book; anything not returned is still out.
        $books = [];
        foreach (Database::all("SELECT title, value, note FROM records WHERE student_id = ? AND type = 'library' ORDER BY recorded_on DESC, id DESC", [$studentId]) as $row) {
            $books[mb_strtolower($row['title'])] ??= $row;
        }
        $out = array_values(array_filter($books, static fn ($b) => $b['value'] !== 'Returned'));

        $last = Database::one(
            'SELECT r.created_at, u.name FROM records r LEFT JOIN users u ON u.id = r.recorded_by
             WHERE r.student_id = ? ORDER BY r.created_at DESC, r.id DESC LIMIT 1',
            [$studentId]
        );

        return [
            'month_rate' => $days ? (int) round(($days - (int) $month['absent']) / $days * 100) : null,
            'month_note' => $days ? (int) $month['late'] . ' late, ' . (int) $month['absent'] . ' absent' : 'No school days yet this month',
            'grade' => $latestGrade,
            'books_out' => count($out),
            'books_note' => $out ? (in_array('Overdue', array_column($out, 'value'), true) ? 'One is overdue' : ($out[0]['note'] ?: 'Borrowed')) : 'None out',
            'last_at' => $last['created_at'] ?? null,
            'last_by' => $last['name'] ?? null,
        ];
    }
}
