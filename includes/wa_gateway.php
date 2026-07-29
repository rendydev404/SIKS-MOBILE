<?php
/**
 * WhatsApp Gateway Integration (Fonnte API)
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Get WA Gateway API Token from database
 */
function getWAGatewayToken($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT nominal FROM setting_pembayaran WHERE jenis = 'wa_gateway_token' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row && !empty($row['nominal'])) {
            return trim($row['nominal']);
        }
        
        // Cek juga dari keterangan / setting alternatif
        $stmtAlt = $pdo->prepare("SELECT keterangan FROM setting_pembayaran WHERE jenis = 'wa_gateway_token' LIMIT 1");
        $stmtAlt->execute();
        $rowAlt = $stmtAlt->fetch();
        if ($rowAlt && !empty($rowAlt['keterangan'])) {
            return trim($rowAlt['keterangan']);
        }
    } catch (PDOException $e) {
        // Fallback gracefully
    }
    return defined('WA_GATEWAY_TOKEN') ? WA_GATEWAY_TOKEN : '';
}

/**
 * Save WA Gateway Token
 */
function saveWAGatewayToken($pdo, $token) {
    $token = trim($token);
    $stmt = $pdo->prepare("SELECT id FROM setting_pembayaran WHERE jenis = 'wa_gateway_token' LIMIT 1");
    $stmt->execute();
    $exist = $stmt->fetch();
    
    if ($exist) {
        $update = $pdo->prepare("UPDATE setting_pembayaran SET nominal = 0, keterangan = ? WHERE jenis = 'wa_gateway_token'");
        $update->execute([$token]);
    } else {
        $insert = $pdo->prepare("INSERT INTO setting_pembayaran (jenis, nominal, keterangan) VALUES ('wa_gateway_token', 0, ?)");
        $insert->execute([$token]);
    }
    return true;
}

/**
 * Kirim Pesan & Gambar Otomatis via Fonnte API
 */
function kirimWA_Otomatis($pdo, $noWa, $pesan, $imageUrl = null) {
    $token = getWAGatewayToken($pdo);
    
    if (empty($token)) {
        return [
            'status' => false,
            'message' => 'Token WA Gateway (Fonnte) belum dikonfigurasi.'
        ];
    }
    
    $cleanNo = formatNomorWA($noWa);
    if (!$cleanNo) {
        return [
            'status' => false,
            'message' => 'Nomor WhatsApp tidak valid atau kosong.'
        ];
    }
    
    $postData = [
        'target' => $cleanNo,
        'message' => $pesan,
        'countryCode' => '62'
    ];
    
    if ($imageUrl) {
        $postData['url'] = $imageUrl;
    }
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.fonnte.com/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $token
        ],
    ]);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    if ($err) {
        return [
            'status' => false,
            'message' => 'cURL Error: ' . $err
        ];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['status']) && $result['status'] === true) {
        return [
            'status' => true,
            'message' => 'Pesan & Gambar berhasil dikirim otomatis!',
            'detail' => $result
        ];
    } else {
        return [
            'status' => false,
            'message' => $result['reason'] ?? $result['message'] ?? 'Gagal mengirim via WA Gateway.',
            'detail' => $result
        ];
    }
}
