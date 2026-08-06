<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Pastikan request method adalah POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Ambil input JSON
$input = json_decode(file_get_contents('php://input'), true);
$fcm_token = $input['fcm_token'] ?? null;

if (!$fcm_token) {
    echo json_encode(['status' => 'error', 'message' => 'Token FCM tidak valid']);
    exit;
}

// Cek siapa yang login (admin atau siswa)
$userId = $_SESSION['user_id'] ?? null;
$siswaId = $_SESSION['siswa_id'] ?? null;

try {
    if ($userId) {
        // Update token untuk admin/user
        $stmt = $pdo->prepare("UPDATE users SET fcm_token = ? WHERE id = ?");
        $stmt->execute([$fcm_token, $userId]);
        echo json_encode(['status' => 'success', 'message' => 'Token FCM Admin berhasil disimpan']);
    } elseif ($siswaId) {
        // Update token untuk siswa
        $stmt = $pdo->prepare("UPDATE siswa SET fcm_token = ? WHERE id = ?");
        $stmt->execute([$fcm_token, $siswaId]);
        echo json_encode(['status' => 'success', 'message' => 'Token FCM Siswa berhasil disimpan']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Belum login']);
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
