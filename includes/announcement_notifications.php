<?php
/** Storage and queue helpers for announcement notifications. */

function ensureAnnouncementNotificationSchema(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS fcm_devices (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        token_hash CHAR(64) NOT NULL UNIQUE,
        fcm_token TEXT NOT NULL,
        owner_type ENUM('siswa','user') NOT NULL,
        siswa_id INT NULL,
        user_id INT NULL,
        platform VARCHAR(32) NOT NULL DEFAULT 'android',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        last_seen_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_fcm_devices_siswa (siswa_id, is_active),
        INDEX idx_fcm_devices_user (user_id, is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS announcement_notification_deliveries (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        pengumuman_id INT NOT NULL,
        device_id BIGINT UNSIGNED NOT NULL,
        status ENUM('pending','processing','retry','sent','failed') NOT NULL DEFAULT 'pending',
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        next_attempt_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        locked_at DATETIME NULL,
        lock_token CHAR(36) NULL,
        last_error TEXT NULL,
        sent_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_announcement_device (pengumuman_id, device_id),
        INDEX idx_delivery_worker (status, next_attempt_at),
        INDEX idx_delivery_lock (lock_token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $column = $pdo->query("SHOW COLUMNS FROM pengumuman LIKE 'notification_queued_at'")->fetch();
    if (!$column) $pdo->exec("ALTER TABLE pengumuman ADD COLUMN notification_queued_at DATETIME NULL DEFAULT NULL");
    $revision = $pdo->query("SHOW COLUMNS FROM pengumuman LIKE 'notification_revision'")->fetch();
    if (!$revision) $pdo->exec("ALTER TABLE pengumuman ADD COLUMN notification_revision INT UNSIGNED NOT NULL DEFAULT 0");
}

/**
 * Clears the guards that keep an announcement from being sent twice, so an
 * edited one reaches students again.
 *
 * Three things block a resend: the delivery rows are unique per
 * (announcement, device), notification_queued_at marks the announcement as
 * already handled, and the app ignores a notification id it has shown before.
 * Bumping the revision gives the message a new id; clearing the other two
 * lets the queue refill.
 */
function resetAnnouncementNotification(PDO $pdo, $announcementId) {
    $pdo->prepare('DELETE FROM announcement_notification_deliveries WHERE pengumuman_id = ?')
        ->execute([$announcementId]);
    $pdo->prepare('UPDATE pengumuman SET notification_revision = notification_revision + 1, notification_queued_at = NULL WHERE id = ?')
        ->execute([$announcementId]);
}

function queueAnnouncementNotification(PDO $pdo, $announcementId) {
    $mark = $pdo->prepare('UPDATE pengumuman SET notification_queued_at = NOW() WHERE id = ? AND is_active = 1 AND notification_queued_at IS NULL');
    $mark->execute([$announcementId]);
    if ($mark->rowCount() !== 1) return 0;

    $hasStatus = (bool) $pdo->query("SHOW COLUMNS FROM siswa LIKE 'status'")->fetch();
    $activeStudentClause = $hasStatus ? " AND s.status = 'aktif'" : '';
    $sql = "INSERT IGNORE INTO announcement_notification_deliveries (pengumuman_id, device_id)
            SELECT ?, d.id FROM fcm_devices d
            INNER JOIN siswa s ON s.id = d.siswa_id
            WHERE d.owner_type = 'siswa' AND d.is_active = 1" . $activeStudentClause;
    $queue = $pdo->prepare($sql);
    $queue->execute([$announcementId]);
    return $queue->rowCount();
}
