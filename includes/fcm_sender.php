<?php
/**
 * Fungsi untuk mengirim Push Notification via FCM
 */

function sendFCMNotification($toToken, $title, $body, $data = []) {
    // TODO: Ganti dengan Server Key dari Firebase Console > Project Settings > Cloud Messaging
    $serverKey = 'ISI_DENGAN_SERVER_KEY_FIREBASE_ANDA';
    
    // Fallback: Coba ambil dari config/firebase.php jika ada
    if (file_exists(__DIR__ . '/../config/firebase.php')) {
        require __DIR__ . '/../config/firebase.php';
        if (defined('FCM_SERVER_KEY')) {
            $serverKey = FCM_SERVER_KEY;
        }
    }

    if (empty($toToken) || $serverKey === 'ISI_DENGAN_SERVER_KEY_FIREBASE_ANDA') {
        error_log("FCM Sender: Token atau Server Key kosong.");
        return false;
    }

    $url = 'https://fcm.googleapis.com/fcm/send';

    $fields = [
        'to' => $toToken, // Bisa juga menggunakan 'registration_ids' untuk array token
        'notification' => [
            'title' => $title,
            'body' => $body,
            'sound' => 'default'
        ],
        'data' => $data
    ];

    $headers = [
        'Authorization: key=' . $serverKey,
        'Content-Type: application/json'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));

    $result = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("FCM Error: " . $error);
        return false;
    }

    return $result;
}
?>
