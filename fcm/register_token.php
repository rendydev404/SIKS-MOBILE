<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/announcement_notifications.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']); exit;
}
$input = json_decode(file_get_contents('php://input'), true);
$token = trim((string) ($input['fcm_token'] ?? ''));
if (strlen($token) < 20 || strlen($token) > 4096) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Token FCM tidak valid']); exit;
}
$userId = $_SESSION['user_id'] ?? null;
$siswaId = $_SESSION['siswa_id'] ?? null;
if (!$userId && !$siswaId) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Belum login']); exit;
}

try {
    ensureAnnouncementNotificationSchema($pdo);
    $ownerType = $siswaId ? 'siswa' : 'user';
    // token_hash is unique: when the same phone logs in to another account its
    // ownership is atomically moved, preventing notifications crossing accounts.
    $stmt = $pdo->prepare("INSERT INTO fcm_devices
        (token_hash, fcm_token, owner_type, siswa_id, user_id, platform, is_active, last_seen_at)
        VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
        ON DUPLICATE KEY UPDATE fcm_token=VALUES(fcm_token), owner_type=VALUES(owner_type),
        siswa_id=VALUES(siswa_id), user_id=VALUES(user_id), platform=VALUES(platform),
        is_active=1, last_seen_at=NOW()");
    $stmt->execute([
        hash('sha256', $token), $token, $ownerType,
        $siswaId ? (int) $siswaId : null, $userId ? (int) $userId : null,
        substr((string) ($input['platform'] ?? 'android'), 0, 32),
    ]);
    echo json_encode(['status' => 'success', 'message' => 'Perangkat notifikasi tersimpan']);
} catch (PDOException $e) {
    error_log('FCM device register: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan perangkat']);
}
