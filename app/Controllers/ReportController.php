<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\Report;
use App\Models\Student;
use App\Services\PdfShiftService;
use RuntimeException;

/**
 * API 3: PDF Reports. Generation is a JSON endpoint used by staff (any student) and by
 * students / parents (their own linked student only). Files are only served through
 * routes that check ownership; storage/ itself is blocked from the web.
 */
class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $pdf = new PdfShiftService();
        $students = Student::ordered();
        $selected = (int) $request->query('student', 0) ?: (int) ($students[0]['id'] ?? 0);
        $latest = $selected ? Report::latestFor($selected) : null;

        return $this->view('reports/index', [
            'title' => 'PDF Reports · CampusIQ',
            'topbar' => [
                'title' => 'PDF Reports',
                'tag' => 'API 3 · PDFShift',
                'tagClass' => 'bg-pdf/[.08] text-pdf',
                'demo' => $pdf->isLive() ? [] : ['PDFSHIFT_API_KEY'],
            ],
            'students' => $students,
            'selected' => $selected,
            'latest' => $latest ? Report::findDetailed((int) $latest['id']) : null,
            'recent' => Report::recent(5),
            'weekCount' => Report::thisWeek(),
            'live' => $pdf->isLive(),
            'sandbox' => $pdf->isSandbox(),
            'scripts' => ['reports.js'],
        ]);
    }

    /** POST /api/reports */
    public function generate(Request $request): Response
    {
        $user = Auth::user();

        if ($user['role'] === 'staff') {
            $student = Student::find((int) $request->input('student_id', 0));
            $type = (string) $request->input('report_type', 'full');
            $from = $this->date($request->input('date_from'));
            $to = $this->date($request->input('date_to'));
            $sections = array_values(array_intersect(array_keys(Report::SECTIONS), (array) $request->input('sections', [])));

            $error = match (true) {
                !$student => 'Pick a student.',
                !array_key_exists($type, Report::TYPES) => 'Pick a report type.',
                $from === false || $to === false => 'Use valid dates.',
                $from && $to && $from > $to => 'The "From" date must be before the "To" date.',
                !array_intersect($sections, ['grades', 'attendance', 'library']) => 'Include at least grades, attendance or library.',
                default => null,
            };
            if ($error) {
                return $this->json(['ok' => false, 'error' => $error], 422);
            }
        } else {
            // Students and parents: always their own linked student, the full record.
            $student = $this->linkedStudent();
            $type = 'full';
            $from = $to = null;
            $sections = ['grades', 'attendance', 'library'];
        }

        try {
            $result = (new PdfShiftService())->generate($student, $type, $from ?: null, $to ?: null, $sections, $user);
        } catch (RuntimeException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 502);
        }

        $report = $result['report'];

        return $this->json([
            'ok' => true,
            'report' => [
                'id' => (int) $report['id'],
                'file_name' => Report::fileName($report),
                'size' => format_bytes((int) $report['size_bytes']),
                'pages' => $result['pages'],
                'seconds' => $result['seconds'],
                'mode' => $report['mode'],
                'record_count' => $result['record_count'],
                'download_url' => url('/reports/' . $report['id'] . '/download'),
                'preview_url' => url('/reports/' . $report['id'] . '/preview'),
            ],
            'result_html' => View::partial('report-result', ['report' => $report, 'meta' => $result]),
            'row_html' => View::partial('report-row', ['report' => $report]),
        ]);
    }

    /** GET /reports/{id}/preview : the HTML snapshot, shown in the preview frame. */
    public function preview(Request $request, int $id): Response
    {
        $report = $this->ownedReport($id);
        $path = PdfShiftService::snapshotPath($report);
        if (!is_file($path)) {
            Response::error(404, null, 'This report file is no longer on the server. Generate it again.');
        }

        return Response::file($path, 'preview.html', 'text/html; charset=UTF-8', true);
    }

    /** GET /reports/{id}/download : the PDF (live) or the print-friendly HTML (demo). */
    public function download(Request $request, int $id): Response
    {
        $report = $this->ownedReport($id);
        $path = PdfShiftService::absolutePath($report['file_path']);
        if (!is_file($path)) {
            Response::error(404, null, 'This report file is no longer on the server. Generate it again.');
        }

        if ($report['mode'] === 'live') {
            return Response::file($path, Report::fileName($report), 'application/pdf');
        }

        // Demo: open the print-friendly page with a small toolbar to print or save it as a PDF.
        $toolbar = '<div class="no-print" style="position:sticky;top:0;z-index:9;display:flex;gap:10px;align-items:center;justify-content:center;flex-wrap:wrap;'
            . 'padding:10px 16px;background:#15172a;color:#fff;font:13px \'IBM Plex Sans\',Arial,sans-serif">'
            . '<span>Demo mode: no PDFSHIFT_API_KEY yet, so this is the print-ready page.</span>'
            . '<button type="button" onclick="window.print()" style="height:32px;padding:0 14px;border:0;border-radius:8px;background:#b3123c;color:#fff;font:inherit;cursor:pointer">Print / Save as PDF</button></div>';
        $html = preg_replace('/<body>/', '<body>' . $toolbar, (string) file_get_contents($path), 1);

        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="' . Report::fileName($report) . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Staff can open any report; students and parents only their linked student's. */
    private function ownedReport(int $id): array
    {
        $report = Report::findDetailed($id) ?? Response::error(404);
        $user = Auth::user();
        if ($user['role'] !== 'staff' && (int) $report['student_id'] !== (int) $user['student_id']) {
            Response::error(403, null, 'You can only open reports for your own record.');
        }

        return $report;
    }

    /** '' => null, valid Y-m-d => string, anything else => false */
    private function date(mixed $value): string|false|null
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return ($date && $date->format('Y-m-d') === $value) ? $value : false;
    }
}
