<?php

require dirname(__DIR__) . '/app/Core/Env.php';

App\Core\Env::load(dirname(__DIR__) . '/.env');
$appName = htmlspecialchars(App\Core\Env::get('APP_NAME', 'CampusIQ'), ENT_QUOTES, 'UTF-8');
$base = rtrim((string) parse_url(App\Core\Env::get('APP_URL', ''), PHP_URL_PATH), '/');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $appName ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
    <link rel="icon" href="<?= $base ?>/assets/img/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?= $base ?>/assets/css/app.css">
</head>
<body class="dots flex min-h-screen items-center justify-center bg-hero p-6">
    <div class="card max-w-md p-8 text-center">
        <div class="mx-auto mb-4 flex size-10 items-center justify-center rounded-lg bg-primary font-mono text-sm font-semibold text-white">CQ</div>
        <h1 class="text-2xl font-semibold"><?= $appName ?> works</h1>
        <p class="mt-2 text-sm text-muted">PHP <?= PHP_VERSION ?> &middot; Tailwind CSS build is loaded.</p>
    </div>
</body>
</html>
