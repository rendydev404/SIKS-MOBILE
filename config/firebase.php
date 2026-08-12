<?php
/**
 * Firebase Cloud Messaging configuration.
 *
 * Set these values in the hosting environment. The service-account JSON must
 * live outside the public web directory and must never be committed to Git.
 */
define('FCM_PROJECT_ID', getenv('FCM_PROJECT_ID') ?: '');
define('FCM_SERVICE_ACCOUNT_PATH', getenv('FCM_SERVICE_ACCOUNT_PATH') ?: '');
define('FCM_CRON_SECRET', getenv('FCM_CRON_SECRET') ?: 'GANTI_DENGAN_SECRET_CRON_YANG_PANJANG');
