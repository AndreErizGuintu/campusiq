<?php
/**
 * Runtime config for the browser. Only public values: the Gemini and PDFShift keys never reach the page.
 * @var array|null $user
 * @var array|null $pendingEmail auto email waiting to be sent on this page load
 */
use App\Core\Env;

$config = [
    'baseUrl' => base_path(),
    'csrf' => csrf_token(),
];
if (($user['role'] ?? null) === 'staff') {
    $config['emailjs'] = [
        'publicKey' => Env::get('EMAILJS_PUBLIC_KEY', ''),
        'serviceId' => Env::get('EMAILJS_SERVICE_ID', ''),
        'templateId' => Env::get('EMAILJS_TEMPLATE_ID', ''),
        'demo' => !Env::has('EMAILJS_PUBLIC_KEY') || !Env::has('EMAILJS_SERVICE_ID') || !Env::has('EMAILJS_TEMPLATE_ID'),
    ];
    $config['pendingEmail'] = $pendingEmail ?? null;
}
?>
<script type="application/json" id="app-config"><?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?></script>
