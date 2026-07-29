<?php
/**
 * Simpan Gambar Kwitansi & Auto Send WA Gateway
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/wa_gateway.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true) ?? $_POST;

$pembayaranId = $input['pembayaran_id'] ?? 0;
$imageData = $input['image_data'] ?? '';

if (!$pembayaranId || !$imageData) {
    echo json_encode(['success' => false, 'message' => 'Parameter pembayaran_id dan image_data wajib diisi']);
    exit;
}

// Extract base64 image
if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $type)) {
    $imageData = substr($imageData, strpos($imageData, ',') + 1);
    $type = strtolower($type[1]); // png, jpg, etc.
} else {
    echo json_encode(['success' => false, 'message' => 'Format image_data tidak valid']);
    exit;
}

$imageData = base64_decode($imageData);
if ($imageData === false) {
    echo json_encode(['success' => false, 'message' => 'Gagal mendecode image base64']);
    exit;
}

// Ensure directory uploads/kwitansi exists
$uploadDir = '../uploads/kwitansi/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$fileName = 'kwitansi_' . (int)$pembayaranId . '.png';
$filePath = $uploadDir . $fileName;

if (file_put_ok($filePath, $imageData) === false) {
    // Fallback file_put_contents
    file_put_contents($filePath, $imageData);
}

// Build full public URL for Kwitansi image
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $protocol . $host . BASE_URL;
$imageUrl = $baseUrl . 'uploads/kwitansi/' . $fileName;

// Fetch payment & student details
$stmt = $pdo->prepare("
    SELECT p.*, s.nama as nama_siswa, s.nis, s.no_whatsapp, k.nama_kelas
    FROM pembayaran p
    JOIN siswa s ON p.siswa_id = s.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    WHERE p.id = ?
");
$stmt->execute([$pembayaranId]);
$pembayaran = $stmt->fetch();

$waSent = false;
$waMsgResponse = '';

if ($pembayaran && !empty($pembayaran['no_whatsapp'])) {
    $isLunas = ($pembayaran['status'] == 'lunas');
    $isDitolak = ($pembayaran['status'] == 'ditolak');

    $pesan = "Assalamu'alaikum Warahmatullahi Wabarakatuh.\n\n";
    $pesan .= "Yth. Orang Tua/Wali dari:\n";
    $pesan .= "Nama Siswa : *" . $pembayaran['nama_siswa'] . "*\n";
    $pesan .= "Kelas : *" . ($pembayaran['nama_kelas'] ?? '-') . "*\n\n";

    if ($isLunas) {
        $pesan .= "Diberitahukan bahwa pembayaran *" . $pembayaran['jenis_pembayaran'] . "* ";
        $pesan .= "periode *" . $pembayaran['bulan'] . " " . $pembayaran['tahun'] . "* ";
        $pesan .= "sebesar *" . formatRupiah($pembayaran['jumlah_bayar']) . "* ";
        $pesan .= "telah *DIVERIFIKASI LUNAS* oleh bagian Keuangan SMK Al Amin.\n\n";
        $pesan .= "📌 *Bukti / Kwitansi pembayaran terlampir pada gambar.*";
    } elseif ($isDitolak) {
        $pesan .= "Diberitahukan bahwa laporan pembayaran *" . $pembayaran['jenis_pembayaran'] . "* ";
        $pesan .= "periode *" . $pembayaran['bulan'] . " " . $pembayaran['tahun'] . "* ";
        $pesan .= "sebesar *" . formatRupiah($pembayaran['jumlah_bayar']) . "* ";
        $pesan .= "*BELUM DAPAT DITERIMA / DITOLAK*.\n\n";
        $pesan .= "Alasan Penolakan: *" . ($pembayaran['admin_note'] ?: 'Bukti tidak terbaca / tidak sesuai') . "*\n\n";
        $pesan .= "Mohon periksa kembali dan upload ulang bukti pembayaran yang valid. Terima kasih.";
    } else {
        $pesan .= "Status pembayaran *" . $pembayaran['jenis_pembayaran'] . "*: *MENUNGGU VERIFIKASI*.";
    }

    $pesan .= "\n\nWassalamu'alaikum Warahmatullahi Wabarakatuh.\n";
    $pesan .= "*Keuangan SMK Al Amin*";

    // Call Fonnte WhatsApp Gateway API automatically
    $resWA = kirimWA_Otomatis($pdo, $pembayaran['no_whatsapp'], $pesan, $imageUrl);
    $waSent = $resWA['status'];
    $waMsgResponse = $resWA['message'];
}

function file_put_ok($path, $data) {
    return file_put_contents($path, $data);
}

echo json_encode([
    'success' => true,
    'file_name' => $fileName,
    'image_url' => $imageUrl,
    'wa_sent' => $waSent,
    'wa_message' => $waMsgResponse
]);
