<?php
/**
 * AJAX Handler untuk WhatsApp Blast Pengiriman Tagihan
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 1. Ambil Progres Realtime
if ($action === 'progress') {
    $progressRes = getWaBlastProgress();
    
    // Sinkronisasi status terkirim/gagal ke database wa_blast_logs
    if (!empty($progressRes['progress']['results'])) {
        $updateStmt = $pdo->prepare("
            UPDATE wa_blast_logs 
            SET status = ?, nomor_pengirim = ?, device_id = ?, pesan_error = ?, sent_at = NOW() 
            WHERE batch_id = ? AND siswa_id = ? AND status = 'pending'
        ");

        $batchId = $progressRes['progress']['batchId'] ?? '';
        foreach ($progressRes['progress']['results'] as $res) {
            $status = ($res['status'] === 'sent') ? 'sent' : 'failed';
            $updateStmt->execute([
                $status,
                $res['phone'] ?? null,
                $res['deviceId'] ?? null,
                $res['error'] ?? null,
                $batchId,
                $res['siswa_id']
            ]);
        }
    }

    echo json_encode($progressRes);
    exit;
}

// 2. Kontrol Antrean (Pause, Resume, Stop)
if ($action === 'control') {
    $subAction = $_POST['sub_action'] ?? '';
    $res = controlWaBlast($subAction);
    echo json_encode($res);
    exit;
}

// 3. Persiapkan dan Mulai Antrean Blast
if ($action === 'start') {
    $siswaIds = $_POST['siswa_ids'] ?? [];
    $bulan = (int)($_POST['bulan'] ?? date('n'));
    $tahun = (int)($_POST['tahun'] ?? date('Y'));

    if (empty($siswaIds) || !is_array($siswaIds)) {
        echo json_encode(['success' => false, 'error' => 'Pilih minimal satu siswa untuk dikirim tagihan.']);
        exit;
    }

    // Cek status koneksi perangkat di gateway terlebih dahulu
    $gatewayStatus = getWaGatewayStatus();
    if (empty($gatewayStatus['success'])) {
        echo json_encode(['success' => false, 'error' => 'Gateway WhatsApp di VPS sedang offline. Silakan periksa halaman WhatsApp Gateway.']);
        exit;
    }

    $availableDevices = [];
    if (!empty($gatewayStatus['devices']['device_1']['status']) && $gatewayStatus['devices']['device_1']['status'] === 'connected') {
        $availableDevices[] = 'device_1';
    }
    if (!empty($gatewayStatus['devices']['device_2']['status']) && $gatewayStatus['devices']['device_2']['status'] === 'connected') {
        $availableDevices[] = 'device_2';
    }

    if (empty($availableDevices)) {
        echo json_encode(['success' => false, 'error' => 'Belum ada nomor WhatsApp yang terhubung! Silakan scan QR di menu WhatsApp Gateway terlebih dahulu.']);
        exit;
    }

    $bulanList = getBulanIndonesia();
    $bulanName = $bulanList[$bulan - 1] ?? '';

    $batchId = 'BLAST-' . date('Ymd-His') . '-' . strtoupper(substr(uniqid(), -4));
    $items = [];

    // Siapkan statement query siswa & insert log
    $siswaStmt = $pdo->prepare("
        SELECT s.*, k.nama_kelas, k.jurusan 
        FROM siswa s 
        LEFT JOIN kelas k ON s.kelas_id = k.id 
        WHERE s.id = ? AND s.status = 'aktif'
    ");

    $logStmt = $pdo->prepare("
        INSERT INTO wa_blast_logs (batch_id, siswa_id, no_tujuan, bulan, tahun, status) 
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");

    foreach ($siswaIds as $sId) {
        $siswaStmt->execute([(int)$sId]);
        $siswa = $siswaStmt->fetch();
        if (!$siswa) continue;

        // Hitung rincian tunggakan akurat
        $dataTunggakan = hitungTunggakan($pdo, $siswa['id'], true, $bulan, $tahun);
        $tunggakanTotal = $dataTunggakan['total'];
        $tunggakanSppList = $dataTunggakan['spp'];
        $tunggakanLainnya = $dataTunggakan['lainnya'];
        $totalKenaikan = $dataTunggakan['kenaikan'];

        if ($tunggakanTotal <= 0) continue; // Skip jika sudah lunas

        $totalSppHanya = 0;
        $nomBulanan = (float)getNominalPembayaran($pdo, 'SPP');
        foreach ($tunggakanSppList as $sppItem) {
            $totalSppHanya += $nomBulanan;
        }

        // Format Caption WhatsApp Resmi
        $caption = "Assalamu'alaikum Warahmatullahi Wabarakatuh.\n\n";
        $caption .= "Yth. Orang Tua/Wali dari:\n";
        $caption .= "Nama Siswa : *" . $siswa['nama'] . "*\n";
        $caption .= "Kelas : *" . ($siswa['nama_kelas'] ?? '-') . "*\n\n";
        $caption .= "Berikut rincian tagihan sampai *" . $bulanName . " " . $tahun . "*:\n\n";
        $caption .= "*1. Tagihan SPP (Rincian lihat pada Gambar)*\n";

        if (!empty($tunggakanLainnya)) {
            $caption .= "\n*2. Tunggakan Lainnya (Keterangan):*\n";
            foreach ($tunggakanLainnya as $item) {
                $caption .= "- " . $item['nama'] . ": Rp " . number_format($item['sisa'], 0, ',', '.') . "\n";
            }
        }

        $caption .= "\n*TOTAL TAGIHAN KESELURUHAN: Rp " . number_format($tunggakanTotal, 0, ',', '.') . "*\n\n";
        $caption .= "Pembayaran dapat dilakukan melalui transfer ke rekening sekolah:\n";
        $caption .= "*SeaBank: 901612378561 a.n Mira Humairoh*\n\n";
        $caption .= "Mohon kirimkan bukti transfer setelah melakukan pembayaran. Atas perhatiannya kami ucapkan terima kasih.\n";
        $caption .= "Wassalamu'alaikum Warahmatullahi Wabarakatuh.\n\n";
        $caption .= "*Keuangan SMK Al Amin*";

        $cleanPhone = formatNomorWA($siswa['no_whatsapp'] ?? '');

        // Rangkum data kartu invoice untuk Puppeteer
        $invoiceData = [
            'nama' => $siswa['nama'],
            'nis' => $siswa['nis'],
            'kelas' => $siswa['nama_kelas'] ?: $siswa['jurusan'],
            'taglineBulan' => $bulanName,
            'taglineTahun' => $tahun,
            'totalSppHanya' => $totalSppHanya,
            'totalKenaikan' => $totalKenaikan,
            'tunggakanTotal' => $tunggakanTotal,
            'tunggakanLainnya' => array_map(function($l) {
                return ['namaBiaya' => $l['nama'], 'sisa' => $l['sisa']];
            }, $tunggakanLainnya)
        ];

        $items[] = [
            'id' => (int)$siswa['id'],
            'nama' => $siswa['nama'],
            'nis' => $siswa['nis'],
            'kelas' => $siswa['nama_kelas'] ?? '-',
            'phone' => $cleanPhone,
            'caption' => $caption,
            'invoiceData' => $invoiceData
        ];

        // Catat ke wa_blast_logs
        $logStmt->execute([$batchId, $siswa['id'], $cleanPhone ?: '-', $bulanName, $tahun]);
    }

    if (empty($items)) {
        echo json_encode(['success' => false, 'error' => 'Semua siswa yang dipilih tidak memiliki tunggakan atau sudah lunas.']);
        exit;
    }

    // Kirim antrean ke container VPS
    $blastResult = startWaBlast($batchId, $items);

    if (empty($blastResult['success'])) {
        echo json_encode(['success' => false, 'error' => $blastResult['error'] ?? 'Gagal memulai broadcast di VPS']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'batchId' => $batchId,
        'totalItems' => count($items),
        'availableDevices' => $availableDevices,
        'message' => "Broadcast dimulai untuk " . count($items) . " siswa."
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Aksi tidak dikenali']);
exit;
