<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;
use App\Core\View;
use App\Models\Student;

/**
 * Shared helpers for controllers.
 */
abstract class Controller
{
    protected function view(string $page, array $data = [], ?string $layout = 'app'): Response
    {
        return Response::html(View::render($page, $data + ['user' => Auth::user()], $layout));
    }

    protected function redirect(string $to): Response
    {
        return Response::redirect($to);
    }

    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    /** Redirect back to a form with field errors and the old input. */
    protected function back(string $to, array $errors, array $old = []): Response
    {
        unset($old['password'], $old['password_confirmation'], $old['_csrf']);
        $_SESSION['_next_errors'] = $errors;
        $_SESSION['_next_old'] = $old;

        return Response::redirect($to);
    }

    /** The student a student/parent account is linked to. 403 if none. */
    protected function linkedStudent(): array
    {
        $studentId = (int) (Auth::user()['student_id'] ?? 0);
        $student = $studentId ? Student::find($studentId) : null;
        if (!$student) {
            Response::error(403, null, 'This account isn\'t linked to a student yet. Ask the school office to link it.');
        }

        return $student;
    }

    /** Student or 404. */
    protected function studentOr404(int $id): array
    {
        return Student::find($id) ?? Response::error(404);
    }
}
