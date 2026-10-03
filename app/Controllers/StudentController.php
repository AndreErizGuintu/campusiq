<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Record;
use App\Models\Student;
use App\Services\EmailTemplateService;

class StudentController extends Controller
{
    public function index(Request $request): Response
    {
        $query = mb_substr((string) $request->query('q', ''), 0, 80);

        return $this->view('students/index', [
            'title' => 'Students & records · CampusIQ',
            'topbar' => ['title' => 'Students & records'],
            'students' => Student::search($query),
            'query' => $query,
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $student = $this->studentOr404($id);
        $type = in_array($request->query('type'), Record::TYPES, true) ? $request->query('type') : null;

        $editing = null;
        if ($editId = (int) $request->query('edit', 0)) {
            $editing = Record::find($editId);
            if (!$editing || (int) $editing['student_id'] !== $id) {
                Response::error(404);
            }
        }

        $highlight = $_SESSION['_highlight_record'] ?? null;
        unset($_SESSION['_highlight_record']);

        return $this->view('students/show', [
            'title' => Student::fullName($student) . ' · CampusIQ',
            'topbar' => [
                'crumbs' => [['Students', '/students'], [Student::fullName($student), null]],
                'demo' => array_slice(EmailTemplateService::missingKeys(), 0, 1),
            ],
            'student' => $student,
            'records' => Record::forStudent($id, $type),
            'counts' => Record::countsByType($id),
            'type' => $type,
            'editing' => $editing,
            'highlight' => $highlight,
            'scripts' => ['email.js'],
            'pendingEmail' => $this->takePendingEmail(),
        ]);
    }

    /** An auto email queued by RecordController, sent by email.js on this page load. */
    protected function takePendingEmail(): ?array
    {
        $pending = $_SESSION['_pending_email'] ?? null;
        unset($_SESSION['_pending_email']);

        return $pending;
    }
}
