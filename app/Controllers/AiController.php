<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\AiQuery;
use App\Models\Student;
use App\Services\GeminiService;
use RuntimeException;

/**
 * API 1: AI Assistant. The page is a chat; questions go to POST /api/ai/ask (JSON, CSRF, staff only).
 */
class AiController extends Controller
{
    public function index(Request $request): Response
    {
        $gemini = new GeminiService();
        $student = ($id = (int) $request->query('student', 0)) ? Student::find($id) : null;

        return $this->view('ai/index', [
            'title' => 'AI Assistant · CampusIQ',
            'topbar' => [
                'title' => 'AI Assistant',
                'tag' => 'API 1 · Gemini',
                'tagClass' => 'bg-primary/10 text-primary',
                'demo' => $gemini->isLive() ? [] : ['GEMINI_API_KEY'],
            ],
            'history' => AiQuery::recentFor((int) Auth::id(), 6),
            'student' => $student,
            'studentCount' => Student::count(),
            'scripts' => ['ai.js'],
        ]);
    }

    public function ask(Request $request): Response
    {
        $question = trim((string) $request->input('question', ''));
        $studentId = (int) $request->input('student_id', 0) ?: null;

        if (mb_strlen($question) < 3) {
            return $this->json(['ok' => false, 'error' => 'Type a question first.'], 422);
        }
        if (mb_strlen($question) > 500) {
            return $this->json(['ok' => false, 'error' => 'Keep the question under 500 characters.'], 422);
        }

        try {
            $result = (new GeminiService())->ask($question, $studentId);
        } catch (RuntimeException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 502);
        }

        AiQuery::create([
            'user_id' => Auth::id(),
            'question' => $question,
            'answer' => $result['answer'] . $this->tableAsText($result['table']),
            'records_used' => $result['records_used'],
            'mode' => $result['mode'],
        ]);

        return $this->json([
            'ok' => true,
            'mode' => $result['mode'],
            'answer' => $result['answer'],
            'records_used' => $result['records_used'],
            'html' => View::partial('ai-answer', ['result' => $result]),
        ]);
    }

    /** Tables are kept in the history as plain lines under the answer. */
    private function tableAsText(?array $table): string
    {
        if (!$table) {
            return '';
        }
        // "• Ana Lim · Late: 3 · Absent: 0" so the history keeps the column names.
        $columns = $table['columns'];
        $lines = array_map(static function ($row) use ($columns) {
            $cells = [array_shift($row)];
            foreach ($row as $i => $cell) {
                $cells[] = ($columns[$i + 1] ?? '') !== '' ? $columns[$i + 1] . ': ' . $cell : $cell;
            }
            return '• ' . implode(' · ', $cells);
        }, $table['rows']);

        return "\n" . implode("\n", $lines);
    }
}
