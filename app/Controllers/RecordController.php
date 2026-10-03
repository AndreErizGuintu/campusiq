<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Models\Record;

class RecordController extends Controller
{
    public function store(Request $request, int $studentId): Response
    {
        $this->studentOr404($studentId);
        [$data, $errors] = $this->validate($request);
        if ($errors) {
            return $this->back("/students/{$studentId}", $errors, $request->only(self::FIELDS));
        }

        $id = Record::create($data + ['student_id' => $studentId, 'recorded_by' => Auth::id()]);
        $_SESSION['_highlight_record'] = $id;
        flash('success', 'Record saved');

        return $this->redirect("/students/{$studentId}");
    }

    public function update(Request $request, int $id): Response
    {
        $record = Record::find($id) ?? Response::error(404);
        [$data, $errors] = $this->validate($request);
        if ($errors) {
            return $this->back("/students/{$record['student_id']}?edit={$id}", $errors, $request->only(self::FIELDS));
        }

        Record::update($id, $data);
        $_SESSION['_highlight_record'] = $id;
        flash('success', 'Record updated');

        return $this->redirect("/students/{$record['student_id']}");
    }

    public function destroy(Request $request, int $id): Response
    {
        $record = Record::find($id) ?? Response::error(404);
        Record::delete($id);
        flash('success', 'Record deleted');

        return $this->redirect("/students/{$record['student_id']}");
    }

    private const FIELDS = ['type', 'title', 'value_grade', 'value_attendance', 'value_library', 'recorded_on', 'note'];

    /** @return array{0: array, 1: array} [clean data, field errors] */
    private function validate(Request $request): array
    {
        $type = (string) $request->input('type', '');
        $title = mb_substr((string) $request->input('title', ''), 0, 200);
        $note = (string) $request->input('note', '');
        $date = (string) $request->input('recorded_on', '');
        $errors = [];

        if (!in_array($type, Record::TYPES, true)) {
            return [[], ['type' => 'Choose grade, attendance or library.']];
        }

        $value = (string) $request->input("value_{$type}", '');
        switch ($type) {
            case 'grade':
                if ($title === '') {
                    $errors['title'] = 'Enter the subject and period, e.g. Quarter 2 Math.';
                }
                if (!is_numeric($value) || $value < 0 || $value > 100) {
                    $errors['value_grade'] = 'Enter a grade from 0 to 100.';
                } else {
                    $value = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
                }
                break;
            case 'attendance':
                $title = 'Daily attendance';
                if (!in_array($value, Record::ATTENDANCE_VALUES, true)) {
                    $errors['value_attendance'] = 'Choose present, late or absent.';
                }
                break;
            case 'library':
                if ($title === '') {
                    $errors['title'] = 'Enter the book title.';
                }
                if (!in_array($value, Record::LIBRARY_VALUES, true)) {
                    $errors['value_library'] = 'Choose borrowed, returned or overdue.';
                }
                break;
        }

        if (mb_strlen($title) > 150) {
            $errors['title'] = 'Keep it under 150 characters.';
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            $errors['recorded_on'] = 'Pick a valid date.';
        } elseif ($parsed > new \DateTimeImmutable('+1 year')) {
            $errors['recorded_on'] = 'That date is too far in the future.';
        }
        if (mb_strlen($note) > 255) {
            $errors['note'] = 'Keep the note under 255 characters.';
        }

        return [[
            'type' => $type,
            'title' => $title,
            'value' => $value,
            'note' => $note === '' ? null : $note,
            'recorded_on' => $date,
        ], $errors];
    }
}
