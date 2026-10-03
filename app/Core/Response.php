<?php

namespace App\Core;

use Throwable;

/**
 * An HTTP response: HTML, JSON, redirect or file.
 */
class Response
{
    private ?string $filePath = null;

    public function __construct(
        private string $body = '',
        private int $status = 200,
        private array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function json(array $data, int $status = 200): self
    {
        return new self(
            (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    /** Redirect to an app path ("/dashboard") or a full URL. */
    public static function redirect(string $to, int $status = 302): self
    {
        $location = preg_match('#^https?://#', $to) ? $to : url($to);
        return new self('', $status, ['Location' => $location]);
    }

    /** Stream a file from disk (never a public URL). */
    public static function file(string $path, string $downloadName, string $mime, bool $inline = false): self
    {
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName);
        $response = new self('', 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) filesize($path),
            'Content-Disposition' => ($inline ? 'inline' : 'attachment') . '; filename="' . $safeName . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
        $response->filePath = $path;
        return $response;
    }

    /** Render the error page (404, 403, 500...) as HTML or JSON. */
    public static function error(int $status, ?Throwable $e = null, ?string $message = null, ?string $title = null): never
    {
        $titles = [
            403 => 'You can\'t open this page',
            404 => 'Page not found',
            405 => 'Method not allowed',
            500 => 'Something went wrong',
        ];
        $title ??= $titles[$status] ?? 'Something went wrong';
        $message ??= match ($status) {
            403 => 'Your account doesn\'t have access to this page.',
            404 => 'The page you\'re looking for doesn\'t exist or was moved.',
            default => 'We hit an unexpected error. It was logged; please try again.',
        };

        $wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || str_contains((string) ($_SERVER['REQUEST_URI'] ?? ''), '/api/');

        if ($wantsJson) {
            self::json(['ok' => false, 'error' => $message], $status)->send();
            exit;
        }

        $detail = ($e && Env::get('APP_ENV') === 'local') ? get_class($e) . ': ' . $e->getMessage() : null;
        try {
            $body = View::render('errors/error', compact('status', 'title', 'message', 'detail'), 'public');
        } catch (Throwable) {
            $body = '<h1>' . e($title) . '</h1><p>' . e($message) . '</p>';
        }
        self::html($body, $status)->send();
        exit;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            header('X-Frame-Options: SAMEORIGIN');
            header('Referrer-Policy: same-origin');
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        if ($this->filePath !== null) {
            readfile($this->filePath);
            return;
        }

        echo $this->body;
    }
}
