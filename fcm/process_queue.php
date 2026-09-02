<?php
/** Run every five minutes with: /fcm/process_queue.php?key=FCM_CRON_SECRET */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/announcement_notifications.php';
require_once __DIR__ . '/../includes/announcement_dispatch.php';

header('Content-Type: application/json');
if (PHP_SAPI !== 'cli') {
    $key = $_GET['key'] ?? '';
    if (FCM_CRON_SECRET === 'GANTI_DENGAN_SECRET_CRON_YANG_PANJANG' || !hash_equals(FCM_CRON_SECRET, $key)) {
        http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit;
    }
}
ensureAnnouncementNotificationSchema($pdo);

// Seluruh logika kirim/retry ada di processAnnouncementDeliveries, dipakai
// bersama halaman pengumuman agar keduanya tidak pernah menyimpang.
echo json_encode(processAnnouncementDeliveries($pdo, 50));
