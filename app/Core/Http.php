<?php

namespace App\Core;

/**
 * Small cURL wrapper for the server-side API calls (Gemini, PDFShift).
 */
class Http
{
    /**
     * POST a JSON body.
     *
     * @return array{status: int, body: string, error: ?string, seconds: float}
     *         status 0 means the request never got an HTTP response (timeout, DNS, TLS).
     */
    public static function postJson(string $url, array $payload, array $headers = [], int $timeout = 30): array
    {
        $ch = curl_init($url);
        $headerLines = ['Content-Type: application/json', 'Accept: */*'];
        foreach ($headers as $name => $value) {
            $headerLines[] = "{$name}: {$value}";
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        // XAMPP ships a CA bundle; use it if php.ini doesn't point at one.
        $bundle = 'C:/xampp/apache/bin/curl-ca-bundle.crt';
        if (!ini_get('curl.cainfo') && is_file($bundle)) {
            curl_setopt($ch, CURLOPT_CAINFO, $bundle);
        }

        $started = microtime(true);
        $body = curl_exec($ch);
        $seconds = microtime(true) - $started;
        $error = $body === false ? curl_error($ch) : null;
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($errno === CURLE_OPERATION_TIMEDOUT) {
            $error = 'timeout';
        }

        return ['status' => $body === false ? 0 : $status, 'body' => $body === false ? '' : (string) $body, 'error' => $error, 'seconds' => $seconds];
    }
}
