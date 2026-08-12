<?php
/** Run every five minutes with: /fcm/process_queue.php?key=FCM_CRON_SECRET */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/firebase.php';
require_once __DIR__ . '/../includes/announcement_notifications.php';
require_once __DIR__ . '/../includes/fcm_sender.php';

header('Content-Type: application/json');
if (PHP_SAPI !== 'cli') {
    $key = $_GET['key'] ?? '';
    if (FCM_CRON_SECRET === 'GANTI_DENGAN_SECRET_CRON_YANG_PANJANG' || !hash_equals(FCM_CRON_SECRET, $key)) {
        http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit;
    }
}
ensureAnnouncementNotificationSchema($pdo);
$pdo->exec("UPDATE announcement_notification_deliveries SET status = 'retry', lock_token = NULL, locked_at = NULL, next_attempt_at = NOW() WHERE status = 'processing' AND locked_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
$lockToken = bin2hex(random_bytes(16));
$claim = $pdo->prepare("UPDATE announcement_notification_deliveries SET status = 'processing', lock_token = ?, locked_at = NOW(), attempts = attempts + 1 WHERE status IN ('pending','retry') AND next_attempt_at <= NOW() ORDER BY id LIMIT 50");
$claim->execute([$lockToken]);
$jobs = $pdo->prepare("SELECT d.*, p.judul, p.isi, fd.fcm_token FROM announcement_notification_deliveries d INNER JOIN pengumuman p ON p.id = d.pengumuman_id INNER JOIN fcm_devices fd ON fd.id = d.device_id WHERE d.lock_token = ?");
$jobs->execute([$lockToken]);
$jobRows = $jobs->fetchAll();
$sent = $retry = $failed = 0;
foreach ($jobRows as $job) {
    $notificationId = 'announcement_' . $job['pengumuman_id'];
    $result = sendFCMNotification($job['fcm_token'], $job['judul'], $job['isi'], [
        'type' => 'announcement', 'announcement_id' => $job['pengumuman_id'],
        'notification_id' => $notificationId,
        'url' => BASE_URL . 'siswa-portal/dashboard.php#pengumuman',
    ]);
    if ($result['success']) {
        $pdo->prepare("UPDATE announcement_notification_deliveries SET status='sent', sent_at=NOW(), lock_token=NULL, locked_at=NULL, last_error=NULL WHERE id=? AND lock_token=?")->execute([$job['id'], $lockToken]); $sent++;
    } elseif ($result['invalid_token']) {
        $pdo->prepare('UPDATE fcm_devices SET is_active=0 WHERE id=?')->execute([$job['device_id']]);
        $pdo->prepare("UPDATE announcement_notification_deliveries SET status='failed', lock_token=NULL, locked_at=NULL, last_error=? WHERE id=? AND lock_token=?")->execute([$result['error'], $job['id'], $lockToken]); $failed++;
    } elseif ($result['retryable'] && $job['attempts'] < 5) {
        $minutes = min(60, 5 * (2 ** max(0, $job['attempts'] - 1)));
        $pdo->prepare("UPDATE announcement_notification_deliveries SET status='retry', lock_token=NULL, locked_at=NULL, next_attempt_at=DATE_ADD(NOW(), INTERVAL $minutes MINUTE), last_error=? WHERE id=? AND lock_token=?")->execute([$result['error'], $job['id'], $lockToken]); $retry++;
    } else {
        $pdo->prepare("UPDATE announcement_notification_deliveries SET status='failed', lock_token=NULL, locked_at=NULL, last_error=? WHERE id=? AND lock_token=?")->execute([$result['error'], $job['id'], $lockToken]); $failed++;
    }
}
echo json_encode(['claimed' => count($jobRows), 'sent' => $sent, 'retry' => $retry, 'failed' => $failed]);
