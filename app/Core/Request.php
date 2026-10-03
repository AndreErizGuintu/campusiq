<?php

namespace App\Core;

/**
 * The current HTTP request: method, app-relative path, input.
 */
class Request
{
    private ?array $jsonBody = null;

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $query,
        private readonly array $post,
        private readonly array $server,
    ) {
    }

    public static function capture(): self
    {
        $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = rawurldecode($uri);
        $base = base_path();
        if ($base !== '' && stripos($path, $base) === 0) {
            $path = substr($path, strlen($base));
        }
        // Requests that came in as /public/... (direct hits) map to the same routes.
        if (str_starts_with($path, '/public/')) {
            $path = substr($path, 7);
        }
        if ($path === '/index.php' || $path === '/public' ) {
            $path = '/';
        }
        $path = '/' . trim($path, '/');

        return new self(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), $path, $_GET, $_POST, $_SERVER);
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /**
     * One scalar form or JSON field, trimmed. Arrays (e.g. ?q[]=x) give the default instead,
     * so a crafted request can't reach code that expects a string. Use array() for list fields.
     */
    public function input(string $key, mixed $default = null): mixed
    {
        $value = array_key_exists($key, $this->post) ? $this->post[$key] : ($this->json()[$key] ?? null);

        return self::scalar($value, $default);
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return self::scalar($this->query[$key] ?? null, $default);
    }

    /** A list field (sections[], recipients): always an array of trimmed strings. */
    public function array(string $key): array
    {
        $value = array_key_exists($key, $this->post) ? $this->post[$key] : ($this->json()[$key] ?? []);

        return is_array($value) ? array_values(array_map('trim', array_filter($value, 'is_scalar'))) : [];
    }

    private static function scalar(mixed $value, mixed $default): mixed
    {
        if ($value === null || !is_scalar($value)) {
            return $default;
        }

        return is_string($value) ? trim($value) : $value;
    }

    /** Only the given form fields, trimmed. */
    public function only(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->input($key, '');
        }
        return $out;
    }

    public function json(): array
    {
        if ($this->jsonBody === null) {
            $this->jsonBody = [];
            if (str_contains($this->header('Content-Type') ?? '', 'application/json')) {
                $decoded = json_decode((string) file_get_contents('php://input'), true);
                $this->jsonBody = is_array($decoded) ? $decoded : [];
            }
        }
        return $this->jsonBody;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if ($name === 'Content-Type') {
            return $this->server['CONTENT_TYPE'] ?? $this->server[$key] ?? null;
        }
        return $this->server[$key] ?? null;
    }

    /** API routes and fetch() calls get JSON errors instead of HTML pages. */
    public function wantsJson(): bool
    {
        return str_starts_with($this->path, '/api/')
            || str_contains($this->header('Accept') ?? '', 'application/json');
    }
}
