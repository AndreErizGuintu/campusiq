<?php

namespace App\Services;

use App\Core\Env;
use App\Core\Http;
use App\Core\View;
use App\Models\Record;
use App\Models\Report;
use RuntimeException;

/**
 * API 3: builds a print-ready HTML report from a student's records and converts it to a PDF with PDFShift.
 * The API key stays on the server. Docs: https://docs.pdfshift.io/api-reference/convert-to-pdf
 *
 * Demo mode (no PDFSHIFT_API_KEY): the HTML is stored and served as a print-friendly page instead of a PDF.
 * Every report also keeps an .html snapshot next to it, used for the on-screen preview.
 */
class PdfShiftService
{
    private const ENDPOINT = 'https://api.pdfshift.io/v3/convert/pdf';
    private const STORAGE = BASE_PATH . '/storage/';

    private ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = Env::get('PDFSHIFT_API_KEY');
    }

    public function isLive(): bool
    {
        return $this->apiKey !== null;
    }

    public function isSandbox(): bool
    {
        return Env::bool('PDFSHIFT_SANDBOX', true);
    }

    /**
     * @param string[] $sections subset of grades, attendance, library, notes
     * @return array{report: array, record_count: int, seconds: float, pages: ?int}
     */
    public function generate(array $student, string $type, ?string $from, ?string $to, array $sections, ?array $createdBy): array
    {
        $started = microtime(true);

        $types = array_values(array_intersect_key(
            ['grades' => 'grade', 'attendance' => 'attendance', 'library' => 'library'],
            array_flip($sections)
        ));
        $records = array_values(array_filter(
            Record::forStudent((int) $student['id'], null, $from, $to),
            static fn ($r) => in_array($r['type'], $types, true)
        ));

        $html = View::render('reports/print', [
            'student' => $student,
            'type' => $type,
            'title' => Report::typeLabel($type),
            'from' => $from,
            'to' => $to,
            'sections' => $sections,
            'records' => $records,
            'createdBy' => $createdBy,
            'generatedAt' => date('Y-m-d H:i:s'),
            'demo' => !$this->isLive(),
        ], null);

        $base = 'reports/' . date('Ymd-His') . '-' . bin2hex(random_bytes(6));
        if (!is_dir(self::STORAGE . 'reports')) {
            mkdir(self::STORAGE . 'reports', 0775, true);
        }
        file_put_contents(self::STORAGE . $base . '.html', $html);

        $pages = null;
        if ($this->isLive()) {
            try {
                $pdf = $this->convert($html);
            } catch (RuntimeException $e) {
                @unlink(self::STORAGE . $base . '.html');
                throw $e;
            }
            file_put_contents(self::STORAGE . $base . '.pdf', $pdf);
            $filePath = $base . '.pdf';
            $pages = $this->countPages($pdf);
        } else {
            usleep(600000); // a short pause so the demo shows the same "converting" step as the real call
            $filePath = $base . '.html';
        }

        $id = Report::create([
            'student_id' => $student['id'],
            'report_type' => $type,
            'date_from' => $from,
            'date_to' => $to,
            'sections' => implode(',', $sections),
            'file_path' => $filePath,
            'size_bytes' => filesize(self::STORAGE . $filePath),
            'mode' => $this->isLive() ? 'live' : 'demo',
            'created_by' => $createdBy['id'] ?? null,
        ]);

        return [
            'report' => Report::findDetailed($id),
            'record_count' => count($records),
            'seconds' => round(microtime(true) - $started, 1),
            'pages' => $pages,
        ];
    }

    /** POST the HTML to PDFShift and return the PDF bytes. */
    private function convert(string $html): string
    {
        $response = Http::postJson(self::ENDPOINT, [
            'source' => $html,
            'sandbox' => $this->isSandbox(),
            'format' => 'A4',
            'margin' => ['top' => '14mm', 'right' => '14mm', 'bottom' => '16mm', 'left' => '14mm'],
            'use_print' => true,
        ], ['X-API-Key' => (string) $this->apiKey], 60);

        if ($response['status'] === 200 && str_starts_with($response['body'], '%PDF')) {
            return $response['body'];
        }

        $data = json_decode($response['body'], true) ?: [];
        $detail = is_string($data['error'] ?? null) ? $data['error'] : ($data['message'] ?? $response['error'] ?? 'no response');
        log_message('error', "PDFShift HTTP {$response['status']}: " . (is_string($detail) ? $detail : json_encode($detail)));

        throw new RuntimeException(match (true) {
            $response['status'] === 0 && $response['error'] === 'timeout' => 'Making the PDF took too long. Try again in a moment.',
            $response['status'] === 0 => 'Could not reach the PDF service. Check the internet connection and try again.',
            $response['status'] === 401 => 'PDF reports aren\'t set up correctly right now. Please let the school office know.',
            $response['status'] === 403 => 'PDF reports are unavailable right now. Please let the school office know.',
            $response['status'] === 408 => 'Making the PDF took too long. Try again.',
            $response['status'] === 429 => 'Too many PDFs at once. Wait a minute and try again.',
            $response['status'] >= 500 => 'The PDF service is having trouble right now. Try again in a moment.',
            default => 'The PDF couldn\'t be made (error ' . $response['status'] . ').',
        });
    }

    /** Rough page count from the PDF bytes; null if it can't tell. */
    private function countPages(string $pdf): ?int
    {
        if (preg_match_all('#/Type\s*/Page[^s]#', $pdf) > 0) {
            return preg_match_all('#/Type\s*/Page[^s]#', $pdf);
        }
        return preg_match('#/Count\s+(\d+)#', $pdf, $m) ? (int) $m[1] : null;
    }

    public static function absolutePath(string $relative): string
    {
        return self::STORAGE . ltrim(str_replace(['..', '\\'], ['', '/'], $relative), '/');
    }

    /** The .html snapshot used for previews (same name as the PDF). */
    public static function snapshotPath(array $report): string
    {
        return self::absolutePath(preg_replace('/\.(pdf|html)$/', '', $report['file_path']) . '.html');
    }

    /** Student summary numbers used on the report and in the portal. */
    public static function summary(array $records): array
    {
        $att = array_filter($records, static fn ($r) => $r['type'] === 'attendance');
        $grades = array_filter($records, static fn ($r) => $r['type'] === 'grade' && is_numeric($r['value']));
        $absent = count(array_filter($att, static fn ($r) => $r['value'] === 'Absent'));
        $late = count(array_filter($att, static fn ($r) => $r['value'] === 'Late'));

        return [
            'days' => count($att),
            'present' => count($att) - $absent,
            'late' => $late,
            'absent' => $absent,
            'rate' => $att ? (int) round((count($att) - $absent) / count($att) * 100) : null,
            'average' => $grades ? round(array_sum(array_column($grades, 'value')) / count($grades), 1) : null,
        ];
    }
}
