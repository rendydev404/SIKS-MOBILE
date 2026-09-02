<?php
/**
 * Firebase Cloud Messaging configuration.
 *
 * Values come from the hosting environment when it offers one. Shared hosting
 * usually has no way to set environment variables for PHP, so two files are
 * read as well:
 *
 *   firebase.local.php  - deployed with the code, so non-secret values only.
 *   firebase.secret.php - kept out of Git and written on the server.
 *
 * The service-account JSON itself must live outside the public web directory,
 * or the web server will hand out the private key to anyone who asks for it.
 */

$fcmLocal = [];
$fcmLocalFile = __DIR__ . '/firebase.local.php';
if (is_readable($fcmLocalFile)) {
    $loaded = require $fcmLocalFile;
    if (is_array($loaded)) $fcmLocal = $loaded;
}

// Anything genuinely secret goes in firebase.secret.php, which is kept out of
// Git and written on the server. firebase.local.php is deployed with the code,
// so it must never hold one.
$fcmSecretFile = __DIR__ . '/firebase.secret.php';
if (is_readable($fcmSecretFile)) {
    $secret = require $fcmSecretFile;
    if (is_array($secret)) $fcmLocal = array_merge($fcmLocal, $secret);
}

define('FCM_PROJECT_ID', getenv('FCM_PROJECT_ID') ?: ($fcmLocal['project_id'] ?? ''));
// The path may be a single string or a list of candidates, so a deploy does
// not break just because the JSON sits one folder over. The first readable one
// wins; an empty result is reported by the diagnostics page.
$fcmPath = getenv('FCM_SERVICE_ACCOUNT_PATH') ?: ($fcmLocal['service_account_path'] ?? '');
if (is_array($fcmPath)) {
    $found = '';
    foreach ($fcmPath as $candidate) {
        if ($candidate && is_readable($candidate)) { $found = $candidate; break; }
    }
    $fcmPath = $found;
}
define('FCM_SERVICE_ACCOUNT_PATH', $fcmPath);
define('FCM_CRON_SECRET', getenv('FCM_CRON_SECRET') ?: ($fcmLocal['cron_secret'] ?? 'GANTI_DENGAN_SECRET_CRON_YANG_PANJANG'));

unset($fcmLocal, $fcmLocalFile, $fcmSecretFile, $loaded, $secret, $fcmPath, $found, $candidate);
