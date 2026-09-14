<?php
/**
 * Konfigurasi & Client Helper WhatsApp Gateway SIKS SMK Al Amin
 * Berkomunikasi dengan microservice Docker siks-wa-gateway di VPS
 */

if (!defined('WA_GATEWAY_URL')) {
    define('WA_GATEWAY_URL', getenv('WA_GATEWAY_URL') ?: 'http://76.13.193.138:3050');
}

if (!defined('WA_GATEWAY_KEY')) {
    define('WA_GATEWAY_KEY', getenv('WA_GATEWAY_KEY') ?: 'siks_secret_wa_key_2026_alamin');
}

/**
 * Melakukan HTTP Call ke microservice WA Gateway di VPS
 *
 * @param string $endpoint Contoh: '/api/status', '/api/blast/start'
 * @param string $method GET atau POST
 * @param array|null $data Payload data jika POST
 * @param int $timeout Timeout dalam detik
 * @return array
 */
function waGatewayCall($endpoint, $method = 'GET', $data = null, $timeout = 12) {
    $url = rtrim(WA_GATEWAY_URL, '/') . '/' . ltrim($endpoint, '/');

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $headers = [
        'X-API-KEY: ' . WA_GATEWAY_KEY,
        'Accept: application/json'
    ];

    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) {
            $jsonBody = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
            $headers[] = 'Content-Type: application/json';
        }
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return [
            'success' => false,
            'error' => 'Gagal terhubung ke WhatsApp Gateway di VPS: ' . ($curlError ?: 'Connection timed out')
        ];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return [
            'success' => false,
            'error' => 'Respon dari Gateway tidak valid (HTTP ' . $httpCode . '): ' . substr(strip_tags($response), 0, 150)
        ];
    }

    return $decoded;
}

/**
 * Mengambil status lengkap gateway dan 2 nomor WhatsApp
 */
function getWaGatewayStatus() {
    return waGatewayCall('/api/status');
}

/**
 * Mengambil QR code perangkat tertentu
 */
function getWaDeviceQR($deviceId) {
    return waGatewayCall('/api/devices/' . urlencode($deviceId) . '/qr');
}

/**
 * Logout dari perangkat WhatsApp tertentu
 */
function logoutWaDevice($deviceId) {
    return waGatewayCall('/api/devices/' . urlencode($deviceId) . '/logout', 'POST');
}

/**
 * Memulai antrean blast massal ke VPS
 */
function startWaBlast($batchId, $items) {
    return waGatewayCall('/api/blast/start', 'POST', [
        'batchId' => $batchId,
        'items' => $items
    ]);
}

/**
 * Mengambil progres blast pengiriman saat ini
 */
function getWaBlastProgress() {
    return waGatewayCall('/api/blast/progress');
}

/**
 * Mengontrol jalannya antrean (pause, resume, stop)
 */
function controlWaBlast($action) {
    return waGatewayCall('/api/blast/control', 'POST', ['action' => $action]);
}

/**
 * Mengirim pesan uji coba langsung ke satu nomor siswa
 */
function sendWaTestMessage($data) {
    return waGatewayCall('/api/send-test', 'POST', $data, 30);
}

