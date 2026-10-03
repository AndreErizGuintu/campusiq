<?php
/**
 * Public layout: landing, login, sign up, error pages.
 * @var string $content
 * @var string|null $title
 * @var string|null $bodyClass
 */
?>
<!doctype html>
<html lang="en">
<head>
    <?= partial('head', ['title' => $title ?? 'CampusIQ']) ?>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
    <?= $content ?>
    <?= partial('flash') ?>
    <?= partial('config', ['user' => null]) ?>
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
