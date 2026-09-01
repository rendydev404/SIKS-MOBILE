<?php
/** Firebase Cloud Messaging HTTP v1 sender. */

function fcmBase64Url($value) {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function fcmConfig() {
    require_once __DIR__ . '/../config/firebase.php';
    if (empty(FCM_PROJECT_ID) || empty(FCM_SERVICE_ACCOUNT_PATH) || !is_readable(FCM_SERVICE_ACCOUNT_PATH)) {
        return null;
    }
    $config = json_decode(file_get_contents(FCM_SERVICE_ACCOUNT_PATH), true);
    return !empty($config['client_email']) && !empty($config['private_key']) ? $config : null;
}

function fcmAccessToken() {
    static $cachedToken = null;
    static $expiresAt = 0;
    if ($cachedToken && $expiresAt > time() + 60) return $cachedToken;

    $config = fcmConfig();
    if (!$config) return null;
    $now = time();
    $header = fcmBase64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claims = fcmBase64Url(json_encode([
        'iss' => $config['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
    ]));
    $unsigned = $header . '.' . $claims;
    if (!openssl_sign($unsigned, $signature, $config['private_key'], OPENSSL_ALGO_SHA256)) return null;

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $unsigned . '.' . fcmBase64Url($signature),
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $payload = json_decode($response ?: '', true);
    if ($httpCode !== 200 || empty($payload['access_token'])) return null;
    $cachedToken = $payload['access_token'];
    $expiresAt = $now + (int) ($payload['expires_in'] ?? 3600);
    return $cachedToken;
}

/**
 * @return array{success: bool, invalid_token: bool, retryable: bool, error: string, http_code: int}
 */
function sendFCMNotification($toToken, $title, $body, $data = []) {
    require_once __DIR__ . '/../config/firebase.php';
    $accessToken = fcmAccessToken();
    if (empty($toToken) || !$accessToken || empty(FCM_PROJECT_ID)) {
        return ['success' => false, 'invalid_token' => false, 'retryable' => true, 'error' => 'FCM belum dikonfigurasi', 'http_code' => 0];
    }

    $type = $data['type'] ?? 'payment';
    $channelId = $type === 'announcement' ? 'announcement_channel' : 'siks_channel';
    $androidNotification = [
        'channel_id' => $channelId,
        'sound' => 'default',
    ];
    $tag = $data['notification_tag'] ?? $data['notification_id'] ?? '';
    if (!empty($tag)) $androidNotification['tag'] = (string) $tag;
    $message = [
        'token' => $toToken,
        'notification' => ['title' => $title, 'body' => $body],
        'data' => array_map('strval', $data),
        'android' => [
            'priority' => 'HIGH',
            'notification' => $androidNotification,
        ],
    ];
    $ch = curl_init('https://fcm.googleapis.com/v1/projects/' . rawurlencode(FCM_PROJECT_ID) . '/messages:send');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['message' => $message]),
    ]);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'invalid_token' => false, 'retryable' => false, 'error' => '', 'http_code' => $httpCode];
    }
    $decoded = json_decode($response ?: '', true);
    $status = $decoded['error']['status'] ?? '';
    $errorCode = $decoded['error']['details'][0]['errorCode'] ?? '';
    $invalid = in_array($status, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)
        || in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT'], true);
    $retryable = !$invalid && ($httpCode === 0 || $httpCode === 429 || $httpCode >= 500);
    return ['success' => false, 'invalid_token' => $invalid, 'retryable' => $retryable, 'error' => $error ?: ($decoded['error']['message'] ?? 'FCM HTTP ' . $httpCode), 'http_code' => $httpCode];
}
