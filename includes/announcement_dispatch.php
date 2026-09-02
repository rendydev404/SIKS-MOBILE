<?php
/**
 * Worker pengiriman notifikasi pengumuman.
 *
 * Dipakai dua pemanggil: cron (process_queue.php) menghabiskan antrean, dan
 * halaman pengumuman mengirim langsung begitu admin menyimpan supaya siswa
 * tidak menunggu putaran cron berikutnya. Logika klaim, retry, dan penonaktifan
 * token hanya ditulis sekali di sini agar keduanya tidak pernah berbeda.
 */

require_once __DIR__ . '/fcm_sender.php';
require_once __DIR__ . '/announcement_notifications.php';

/**
 * Mengirim antrean yang siap dikirim.
 *
 * @param int|null $announcementId Batasi ke satu pengumuman, atau null untuk semua.
 * @param float    $timeBudget     Detik maksimum sebelum sisanya dilepas kembali
 *                                 ke antrean. 0 berarti tanpa batas (untuk cron).
 * @return array{claimed:int, sent:int, retry:int, failed:int, deferred:int}
 */
function processAnnouncementDeliveries(PDO $pdo, $limit = 50, $announcementId = null, $timeBudget = 0) {
    $startedAt = microtime(true);

    // Baris yang tersangkut karena proses sebelumnya mati di tengah jalan.
    $pdo->exec("UPDATE announcement_notification_deliveries
                SET status = 'retry', lock_token = NULL, locked_at = NULL, next_attempt_at = NOW()
                WHERE status = 'processing' AND locked_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");

    $lockToken = bin2hex(random_bytes(16));
    $claimSql = "UPDATE announcement_notification_deliveries
                 SET status = 'processing', lock_token = ?, locked_at = NOW(), attempts = attempts + 1
                 WHERE status IN ('pending','retry') AND next_attempt_at <= NOW()";
    $claimParams = [$lockToken];
    if ($announcementId !== null) {
        $claimSql .= " AND pengumuman_id = ?";
        $claimParams[] = (int) $announcementId;
    }
    $claimSql .= " ORDER BY id LIMIT " . (int) $limit;
    $pdo->prepare($claimSql)->execute($claimParams);

    $jobs = $pdo->prepare("SELECT d.*, p.judul, p.isi, p.notification_revision, fd.fcm_token
                           FROM announcement_notification_deliveries d
                           INNER JOIN pengumuman p ON p.id = d.pengumuman_id
                           INNER JOIN fcm_devices fd ON fd.id = d.device_id
                           WHERE d.lock_token = ?");
    $jobs->execute([$lockToken]);
    $jobRows = $jobs->fetchAll();

    $sent = $retry = $failed = $deferred = 0;

    foreach ($jobRows as $job) {
        // Setiap kiriman adalah satu panggilan HTTP ke Google. Kalau waktunya
        // habis, sisanya dikembalikan ke antrean daripada membuat admin
        // menunggu atau PHP timeout di tengah pengiriman.
        if ($timeBudget > 0 && (microtime(true) - $startedAt) > $timeBudget) {
            $pdo->prepare("UPDATE announcement_notification_deliveries
                           SET status = 'retry', lock_token = NULL, locked_at = NULL, next_attempt_at = NOW()
                           WHERE id = ? AND lock_token = ?")
                ->execute([$job['id'], $lockToken]);
            $deferred++;
            continue;
        }

        // Id berubah tiap revisi supaya pengumuman yang diedit tidak dikira
        // pesan lama oleh aplikasi; tag tetap per pengumuman supaya versi baru
        // menimpa entri lama di tray, bukan menumpuk di sebelahnya.
        $notificationId = 'announcement_' . $job['pengumuman_id'] . '_r' . (int) $job['notification_revision'];
        $result = sendFCMNotification($job['fcm_token'], $job['judul'], $job['isi'], [
            'type' => 'announcement',
            'announcement_id' => $job['pengumuman_id'],
            'notification_id' => $notificationId,
            'notification_tag' => 'announcement_' . $job['pengumuman_id'],
            'url' => BASE_URL . 'siswa-portal/dashboard.php#pengumuman',
        ]);

        if ($result['success']) {
            $pdo->prepare("UPDATE announcement_notification_deliveries
                           SET status = 'sent', sent_at = NOW(), lock_token = NULL, locked_at = NULL, last_error = NULL
                           WHERE id = ? AND lock_token = ?")
                ->execute([$job['id'], $lockToken]);
            $sent++;
        } elseif ($result['invalid_token']) {
            $pdo->prepare('UPDATE fcm_devices SET is_active = 0 WHERE id = ?')->execute([$job['device_id']]);
            $pdo->prepare("UPDATE announcement_notification_deliveries
                           SET status = 'failed', lock_token = NULL, locked_at = NULL, last_error = ?
                           WHERE id = ? AND lock_token = ?")
                ->execute([$result['error'], $job['id'], $lockToken]);
            $failed++;
        } elseif ($result['retryable'] && $job['attempts'] < 5) {
            $minutes = min(60, 5 * (2 ** max(0, $job['attempts'] - 1)));
            $pdo->prepare("UPDATE announcement_notification_deliveries
                           SET status = 'retry', lock_token = NULL, locked_at = NULL,
                               next_attempt_at = DATE_ADD(NOW(), INTERVAL $minutes MINUTE), last_error = ?
                           WHERE id = ? AND lock_token = ?")
                ->execute([$result['error'], $job['id'], $lockToken]);
            $retry++;
        } else {
            $pdo->prepare("UPDATE announcement_notification_deliveries
                           SET status = 'failed', lock_token = NULL, locked_at = NULL, last_error = ?
                           WHERE id = ? AND lock_token = ?")
                ->execute([$result['error'], $job['id'], $lockToken]);
            $failed++;
        }
    }

    return [
        'claimed' => count($jobRows),
        'sent' => $sent,
        'retry' => $retry,
        'failed' => $failed,
        'deferred' => $deferred,
    ];
}
