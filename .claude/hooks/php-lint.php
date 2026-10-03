<?php
// PostToolUse hook: run `php -l` on any .php file Claude just edited or wrote.
// Exit code 2 sends the lint error back to Claude so it fixes it straight away.

$payload = json_decode((string) stream_get_contents(STDIN), true);
$file = $payload['tool_input']['file_path'] ?? '';

if ($file === '' || strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'php' || !is_file($file)) {
    exit(0);
}

$output = [];
$code = 0;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);

if ($code !== 0) {
    fwrite(STDERR, "php -l failed for {$file}:\n" . implode("\n", $output) . "\n");
    exit(2);
}

exit(0);
