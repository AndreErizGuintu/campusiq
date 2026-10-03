<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Models\Setting;
use App\Models\Student;

/**
 * API 2 helper: which record fires which email, who receives it, and the subject + message text.
 * The actual sending happens in the browser through the EmailJS SDK (public key only).
 */
class EmailTemplateService
{
    /** Automatic triggers (toggled on the Email Alerts page, stored in settings as trigger_<key>). */
    public const TRIGGERS = [
        'grade_posted' => 'Grade posted',
        'absence_logged' => 'Absence logged',
        'late_arrival' => 'Late arrival',
        'book_overdue' => 'Library book overdue',
    ];

    /** Everything that can be picked in the manual "Send a notification" form. */
    public const TEMPLATES = self::TRIGGERS + [
        'report_ready' => 'Report ready',
        'custom' => 'Custom message',
    ];

    public static function isDemo(): bool
    {
        return !Env::has('EMAILJS_PUBLIC_KEY') || !Env::has('EMAILJS_SERVICE_ID') || !Env::has('EMAILJS_TEMPLATE_ID');
    }

    /** EmailJS keys still empty in .env, e.g. ["EMAILJS_PUBLIC_KEY"]. */
    public static function missingKeys(): array
    {
        return array_values(array_filter(
            ['EMAILJS_PUBLIC_KEY', 'EMAILJS_SERVICE_ID', 'EMAILJS_TEMPLATE_ID'],
            static fn ($key) => !Env::has($key)
        ));
    }

    public static function label(string $key): string
    {
        return self::TEMPLATES[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    /** The trigger a saved record fires, or null (e.g. "Present" attendance, a returned book). */
    public static function triggerFor(array $record): ?string
    {
        return match (true) {
            $record['type'] === 'grade' => 'grade_posted',
            $record['type'] === 'attendance' && $record['value'] === 'Absent' => 'absence_logged',
            $record['type'] === 'attendance' && $record['value'] === 'Late' => 'late_arrival',
            $record['type'] === 'library' && $record['value'] === 'Overdue' => 'book_overdue',
            default => null,
        };
    }

    public static function triggerEnabled(string $key): bool
    {
        return Setting::enabled('trigger_' . $key);
    }

    /** @return array<string, bool> */
    public static function triggerStates(): array
    {
        $states = [];
        foreach (array_keys(self::TRIGGERS) as $key) {
            $states[$key] = self::triggerEnabled($key);
        }
        return $states;
    }

    /** @return array<int, array{email: string, name: string, role: string}> */
    public static function recipients(array $student, string $who = 'both'): array
    {
        $list = [];
        if ($who !== 'guardian') {
            $list[] = ['email' => $student['email'], 'name' => Student::fullName($student), 'role' => 'student'];
        }
        if ($who !== 'student') {
            $list[] = ['email' => $student['guardian_email'], 'name' => $student['guardian_name'], 'role' => 'guardian'];
        }
        return $list;
    }

    /**
     * Subject and message for a trigger. $record is the record it is about (null for custom).
     * @return array{subject: string, message: string}
     */
    public static function compose(string $key, array $student, ?array $record = null, ?string $staffName = null): array
    {
        $first = $student['first_name'];
        $date = $record ? fmt_date($record['recorded_on'], true) : fmt_date(date('Y-m-d'), true);
        $by = $staffName ?? ($record['recorded_by_name'] ?? null);
        $byText = $by ? " by {$by}" : '';
        $outro = 'Log in to CampusIQ to see your full record.';

        return match ($key) {
            'grade_posted' => [
                'subject' => "New grade posted: {$record['title']}",
                'message' => "Hi {$first}, a new grade was added to your record: {$record['title']}, {$record['value']}, recorded{$byText} on {$date}. {$outro}",
            ],
            'absence_logged' => [
                'subject' => "Absence logged: {$date}",
                'message' => "Hi {$first}, you were marked absent on {$date}{$byText}. If this is a mistake, please talk to your class adviser. {$outro}",
            ],
            'late_arrival' => [
                'subject' => "Attendance: marked late on {$date}",
                'message' => "Hi {$first}, you were marked late on {$date}" . (!empty($record['note']) ? ' (' . lcfirst($record['note']) . ')' : '') . "{$byText}. {$outro}",
            ],
            'book_overdue' => [
                'subject' => "Library: \"{$record['title']}\" is overdue",
                'message' => "Hi {$first}, the library book \"{$record['title']}\" is overdue" . (!empty($record['note']) ? ' (' . lcfirst($record['note']) . ')' : '') . '. Please return it to the library desk as soon as you can.',
            ],
            'report_ready' => [
                'subject' => 'Your CampusIQ report is ready',
                'message' => "Hi {$first}, a new report" . ($record['label'] ?? '') . " was made from your records on {$date}. Log in to CampusIQ and open Reports to download it.",
            ],
            default => [
                'subject' => 'A message from your school',
                'message' => "Hi {$first}, ",
            ],
        };
    }

    /** The record a manual template is about: the latest matching one for the student. */
    public static function latestRecordFor(string $key, int $studentId): ?array
    {
        $condition = match ($key) {
            'grade_posted' => "r.type = 'grade'",
            'absence_logged' => "r.type = 'attendance' AND r.value = 'Absent'",
            'late_arrival' => "r.type = 'attendance' AND r.value = 'Late'",
            'book_overdue' => "r.type = 'library' AND r.value = 'Overdue'",
            default => null,
        };
        if ($condition === null) {
            return null;
        }

        return Database::one(
            "SELECT r.*, u.name AS recorded_by_name FROM records r LEFT JOIN users u ON u.id = r.recorded_by
             WHERE r.student_id = ? AND {$condition} ORDER BY r.recorded_on DESC, r.id DESC LIMIT 1",
            [$studentId]
        );
    }

    /** Everything email.js needs to send and then log one email. */
    public static function payload(string $key, array $student, ?array $record, array $composed, array $recipients): array
    {
        return [
            'student_id' => (int) $student['id'],
            'record_id' => isset($record['id']) && is_numeric($record['id']) ? (int) $record['id'] : null,
            'trigger_key' => $key,
            'trigger_label' => self::label($key),
            'student_name' => Student::fullName($student),
            'subject' => $composed['subject'],
            'message' => $composed['message'],
            'recipients' => $recipients,
        ];
    }

    /** Payload for an automatic email after a record was saved, or null when no trigger is on for it. */
    public static function autoPayload(array $record, array $student, ?string $staffName): ?array
    {
        $key = self::triggerFor($record);
        if ($key === null || !self::triggerEnabled($key)) {
            return null;
        }

        return self::payload($key, $student, $record, self::compose($key, $student, $record, $staffName), self::recipients($student)) + ['auto' => true];
    }
}
