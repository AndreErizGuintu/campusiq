<?php
/** @var string $type grade|attendance|library */
$classes = ['grade' => 'pill-grade', 'attendance' => 'pill-attendance', 'library' => 'pill-library'];
?>
<span class="pill <?= $classes[$type] ?? 'pill-muted' ?>"><?= e(record_type_label($type)) ?></span>
