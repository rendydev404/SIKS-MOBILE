# Worker antrean notifikasi pengumuman

Tambahkan Cron Job Hostinger setiap 5 menit dengan URL berikut:

```
https://sikssmkalamin.absensismkalamin.my.id/fcm/process_queue.php?key=SECRET_CRON
```

Sebelum mengaktifkan Cron, atur tiga environment variable pada hosting:

- `FCM_PROJECT_ID`: Firebase project ID dari `google-services.json`.
- `FCM_SERVICE_ACCOUNT_PATH`: path absolut service-account JSON yang berada di luar `public_html`.
- `FCM_CRON_SECRET`: nilai acak panjang yang sama dengan parameter `key` pada URL Cron.

Service-account memerlukan izin Firebase Cloud Messaging API. Jangan menaruh JSON atau secret di repository.
