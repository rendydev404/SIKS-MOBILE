<?php
/**
 * Firebase Cloud Messaging configuration.
 *
 * Values come from the hosting environment when it offers one. Shared hosting
 * usually has no way to set environment variables for PHP, so they can also be
 * written to config/firebase.local.php, which is kept out of Git. Copy
 * firebase.local.example.php to firebase.local.php and fill it in.
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

define('FCM_PROJECT_ID', getenv('FCM_PROJECT_ID') ?: ($fcmLocal['project_id'] ?? ''));
define('FCM_SERVICE_ACCOUNT_PATH', getenv('FCM_SERVICE_ACCOUNT_PATH') ?: ($fcmLocal['service_account_path'] ?? ''));
define('FCM_CRON_SECRET', getenv('FCM_CRON_SECRET') ?: ($fcmLocal['cron_secret'] ?? 'GANTI_DENGAN_SECRET_CRON_YANG_PANJANG'));

unset($fcmLocal, $fcmLocalFile, $loaded);
