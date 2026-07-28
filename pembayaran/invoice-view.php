<?php
/**
 * Visual Invoice View
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();

$siswaId = $_GET['siswa_id'] ?? '';
$bulan = $_GET['bulan'] ?? date('n');
$tahun = $_GET['tahun'] ?? date('Y');

if (!$siswaId) {
    die("Pilih siswa terlebih dahulu.");
}

// Get Data Siswa
$stmt = $pdo->prepare("SELECT s.*, k.nama_kelas, k.jurusan FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
$stmt->execute([$siswaId]);
$siswa = $stmt->fetch();

if (!$siswa) {
    die("Siswa tidak ditemukan.");
}

$bulanList = getBulanIndonesia();
$bulanName = $bulanList[(int)$bulan - 1] ?? '';

// Hitung Tunggakan keseluruhan menggunakan fungsi pusat agar sinkron
$dataTunggakan = hitungTunggakan($pdo, $siswaId, true, $bulan, $tahun);
$tunggakanTotal = $dataTunggakan['total'];
$tunggakanSppList = $dataTunggakan['spp'];
$tunggakanLainnya = $dataTunggakan['lainnya'];
$totalKenaikan = $dataTunggakan['kenaikan'];

// Hitung khusus SPP untuk tampilan gambar
$totalSppHanya = 0;
foreach ($tunggakanSppList as $sppLabel) {
    $totalSppHanya += (float)getNominalPembayaran($pdo, 'SPP');
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Invoice - <?= e($siswa['nama']) ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --bg-body: #f3f4f6;
            --bg-card: #ffffff;
            --border-color: #e5e7eb;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .invoice-card {
            background-color: var(--bg-card);
            width: 100%;
            max-width: 480px;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            overflow: hidden;
            position: relative;
        }
        .invoice-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 25px 20px;
            color: white;
            text-align: center;
            position: relative;
        }
        .invoice-header h1 {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }
        .invoice-header p {
            font-size: 14px;
            opacity: 0.9;
        }
        .logo-container {
            position: absolute;
            top: 20px;
            right: 20px;
            background: white;
            padding: 5px;
            border-radius: 8px;
        }
        .logo-container img {
            width: 50px;
            height: auto;
            display: block;
        }
        .invoice-body {
            padding: 25px 20px;
        }
        .salutation {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 15px;
        }
        .student-info {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .student-info-row {
            display: flex;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .student-info-row:last-child { margin-bottom: 0; }
        .info-label { width: 90px; color: var(--text-muted); }
        .info-val { font-weight: 600; flex: 1; }
        
        .rincian-title {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--text-main);
            border-bottom: 2px solid var(--primary);
            display: inline-block;
            padding-bottom: 4px;
        }
        .table-rincian {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .table-rincian th, .table-rincian td {
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .table-rincian th {
            text-align: left;
            font-weight: 500;
            color: var(--text-muted);
        }
        .table-rincian td.amount {
            text-align: right;
            font-weight: 600;
        }
        .table-rincian tr.total-row td {
            border-bottom: none;
            padding-top: 15px;
            font-size: 16px;
        }
        .table-rincian tr.total-row td.amount {
            color: var(--primary);
            font-weight: 700;
            font-size: 18px;
        }
        
        .payment-info {
            background: #eff6ff;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        .payment-info p {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 10px;
        }
        .bank-details {
            font-size: 16px;
            font-weight: 700;
            color: var(--primary-dark);
            letter-spacing: 0.5px;
        }
        .bank-owner {
            font-size: 13px;
            font-weight: 500;
            color: var(--text-main);
            margin-top: 4px;
        }
        
        .footer-note {
            font-size: 12px;
            color: var(--text-muted);
            text-align: center;
            line-height: 1.5;
            margin-bottom: 25px;
        }
        .footer-note strong { color: var(--text-main); }
        
        .signature-area {
            display: flex;
            justify-content: flex-end;
            text-align: center;
            font-size: 13px;
        }
        .signature-box { width: 150px; }
        .signature-name {
            margin-top: 50px;
            font-weight: 700;
            text-decoration: underline;
        }

        .actions {
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            width: 100%;
            max-width: 480px;
        }
        .btn-action {
            padding: 12px 20px;
            cursor: pointer;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 140px;
            justify-content: center;
            transition: opacity 0.2s;
        }
        .btn-action:active { opacity: 0.8; }
        .btn-print { background: var(--bg-card); color: var(--text-main); border: 1px solid var(--border-color); }
        .btn-copy { background: #25d366; color: white; }
        .btn-download { background: var(--primary); color: white; }
        .btn-back { background: var(--text-muted); color: white; }

        #toast {
            visibility: hidden;
            min-width: 250px;
            background-color: #1f2937;
            color: #fff;
            text-align: center;
            border-radius: 8px;
            padding: 16px;
            position: fixed;
            z-index: 1000;
            left: 50%;
            bottom: 30px;
            transform: translateX(-50%);
            font-size: 14px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }
        #toast.show {
            visibility: visible;
            animation: fadein 0.5s, fadeout 0.5s 2.5s;
        }
        @keyframes fadein { from {bottom: 0; opacity: 0;} to {bottom: 30px; opacity: 1;} }
        @keyframes fadeout { from {bottom: 30px; opacity: 1;} to {bottom: 0; opacity: 0;} }

        @media print {
            .actions { display: none; }
            body { background-color: #fff; padding: 0; }
            .invoice-card { box-shadow: none; max-width: 100%; border-radius: 0; }
        }
    </style>
</head>
<body>

<div class="actions">
    <button id="copyAndWaBtn" class="btn-action btn-copy">
        <i class="fab fa-whatsapp"></i> Bagikan WA
    </button>
    <button id="downloadBtn" class="btn-action btn-download">
        <i class="fas fa-download"></i> Simpan
    </button>
    <button class="btn-action btn-print" onclick="window.print()">
        <i class="fas fa-print"></i> Cetak
    </button>
    <button class="btn-action btn-back" onclick="window.history.back()">
        <i class="fas fa-arrow-left"></i> Kembali
    </button>
</div>

<div id="toast">Gambar berhasil disalin!</div>

<div class="invoice-card" id="invoiceArea">
    <div class="invoice-header">
        <h1>INVOICE</h1>
        <?php
        $taglineBulan = $bulanName;
        $taglineTahun = $tahun;
        ?>
        <p>SMK Al Amin • Tagihan s/d <?= $taglineBulan ?> <?= $taglineTahun ?></p>
        <div class="logo-container">
            <img src="../assets/img/logo_sekolah.png" alt="Logo">
        </div>
    </div>
    
    <div class="invoice-body">
        <div class="salutation">Assalamu'alaikum Wr. Wb.</div>
        
        <div class="student-info">
            <div class="student-info-row">
                <div class="info-label">Nama</div>
                <div class="info-val"><?= e($siswa['nama']) ?></div>
            </div>
            <div class="student-info-row">
                <div class="info-label">Jurusan</div>
                <div class="info-val"><?= e($siswa['nama_kelas'] ?: $siswa['jurusan']) ?></div>
            </div>
        </div>

        <div class="rincian-title">Rincian Tagihan</div>
        <table class="table-rincian">
            <tr>
                <th>SPP s/d <?= $taglineBulan ?></th>
                <td class="amount">Rp <?= number_format($totalSppHanya, 0, ',', '.') ?></td>
            </tr>
            <?php if ($totalKenaikan > 0): ?>
            <tr>
                <th>Penyesuaian/Kenaikan</th>
                <td class="amount">Rp <?= number_format($totalKenaikan, 0, ',', '.') ?></td>
            </tr>
            <?php endif; ?>
            
            <?php if (!empty($tunggakanLainnya)): ?>
                <?php foreach ($tunggakanLainnya as $item): ?>
                <tr>
                    <th><?= e($item['nama']) ?></th>
                    <td class="amount">Rp <?= number_format($item['sisa'], 0, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <tr class="total-row">
                <td>Total Tagihan</td>
                <td class="amount">Rp <?= number_format($tunggakanTotal, 0, ',', '.') ?></td>
            </tr>
        </table>

        <div class="payment-info">
            <p>Pembayaran dapat dilakukan melalui transfer ke:</p>
            <div class="bank-details">SeaBank: 9016 1237 8561</div>
            <div class="bank-owner">a.n. Mira Humairoh</div>
        </div>

        <div class="footer-note">
            Harap sertakan tanda bukti transfer via WhatsApp ke nomor:<br>
            <strong>0858-8071-9956</strong>
        </div>

        <div class="signature-area">
            <div class="signature-box">
                <div>Mengetahui,</div>
                <div>Keuangan SMK Al Amin</div>
                <div class="signature-name">Memen Rohmatul Ummah S.Pd</div>
            </div>
        </div>
    </div>
</div>

<?php 
$pesan = "Assalamu'alaikum Warahmatullahi Wabarakatuh.\n\n";
$pesan .= "Yth. Orang Tua/Wali dari:\n";
$pesan .= "Nama Siswa : *" . $siswa['nama'] . "*\n";
$pesan .= "Kelas : *" . ($siswa['nama_kelas'] ?? '-') . "*\n\n";
$pesan .= "Berikut rincian tagihan sampai *" . $taglineBulan . " " . $taglineTahun . "*:\n\n";

// Rincian SPP (Arahkan ke gambar)
$pesan .= "*1. Tagihan SPP (Rincian lihat pada Gambar)*\n";

// Rincian Biaya Lainnya (Ditulis secara tekstual)
if (!empty($tunggakanLainnya)) {
    $pesan .= "\n*2. Tunggakan Lainnya (Keterangan):*\n";
    foreach ($tunggakanLainnya as $i => $item) {
        $pesan .= "- " . $item['nama'] . ": Rp " . number_format($item['sisa'], 0, ',', '.') . "\n";
    }
}

$pesan .= "\n*TOTAL TAGIHAN KESELURUHAN: Rp " . number_format($tunggakanTotal, 0, ',', '.') . "*\n\n";

$pesan .= "Pembayaran dapat dilakukan melalui transfer ke rekening sekolah:\n";
$pesan .= "*SeaBank: 901612378561 a.n Mira Humairoh*\n\n";
$pesan .= "Mohon kirimkan bukti transfer setelah melakukan pembayaran. Atas perhatiannya kami ucapkan terima kasih.\n";
$pesan .= "Wassalamu'alaikum Warahmatullahi Wabarakatuh.\n\n";
$pesan .= "*Keuangan SMK Al Amin*";
$noWa = preg_replace('/[^0-9]/', '', $siswa['no_whatsapp'] ?? '');
if (substr($noWa, 0, 1) == '0') $noWa = '62' . substr($noWa, 1);
$waLink = "https://wa.me/" . $noWa . "?text=" . urlencode($pesan);
?>

<script>
function showToast(msg) {
    const toast = document.getElementById('toast');
    toast.innerText = msg;
    toast.className = "show";
    setTimeout(() => { toast.className = toast.className.replace("show", ""); }, 3000);
}

document.getElementById('downloadBtn').addEventListener('click', function() {
    const btn = this;
    btn.disabled = true;
    html2canvas(document.getElementById('invoiceArea'), {
        useCORS: true,
        scale: 2
    }).then(canvas => {
        let link = document.createElement('a');
        link.download = 'Invoice_<?= e($siswa['nama']) ?>.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
        btn.disabled = false;
    });
});

// Check if Web Share API is supported and if sharing files is allowed
const shareBtn = document.getElementById('shareBtn');
if (navigator.canShare && navigator.share) {
    // We'll show the button if the browser indicates it can share (even if we don't know for sure it can share files yet)
    shareBtn.style.display = 'block';
}

shareBtn.addEventListener('click', function() {
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Proses...';

    html2canvas(document.getElementById('invoiceArea'), {
        useCORS: true,
        scale: 2
    }).then(canvas => {
        canvas.toBlob(blob => {
            const file = new File([blob], 'Invoice_<?= e($siswa['nama']) ?>.png', { type: 'image/png' });
            
            if (navigator.canShare && navigator.canShare({ files: [file] })) {
                navigator.share({
                    files: [file],
                    title: 'Invoice SIKS SMK Al Amin',
                    text: 'Berikut rincian tagihan SPP untuk <?= e($siswa['nama']) ?>'
                })
                .then(() => {
                    showToast("Berhasil dibagikan!");
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-share-nodes"></i> Bagikan';
                })
                .catch(err => {
                    console.error('Sharing failed', err);
                    showToast("Gagal membagikan gambar.");
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-share-nodes"></i> Bagikan';
                });
            } else {
                showToast("Browser tidak mendukung fitur bagi file.");
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-share-nodes"></i> Bagikan';
            }
        }, 'image/png');
    });
});

document.getElementById('copyAndWaBtn').addEventListener('click', function() {
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Proses...';
    
    html2canvas(document.getElementById('invoiceArea'), {
        useCORS: true,
        scale: 2
    }).then(canvas => {
        canvas.toBlob(blob => {
            const openWA = () => {
                window.open("<?= $waLink ?>", "_blank");
                btn.disabled = false;
                btn.innerHTML = '<i class="fab fa-whatsapp"></i> Bagikan WA';
            };

            const downloadImage = () => {
                let link = document.createElement('a');
                link.download = 'Invoice_<?= e($siswa['nama']) ?>.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
            };

            try {
                if (navigator.clipboard && window.ClipboardItem) {
                    const item = new ClipboardItem({ "image/png": blob });
                    navigator.clipboard.write([item]).then(() => {
                        showToast("Gambar disalin! Tempel (Paste) di WA.");
                        setTimeout(openWA, 1500);
                    }).catch(err => {
                        console.error(err);
                        showToast("Salin otomatis tidak didukung. Mengunduh gambar...");
                        downloadImage();
                        setTimeout(openWA, 2000);
                    });
                } else {
                    showToast("Browser tidak mendukung salin otomatis. Mengunduh gambar...");
                    downloadImage();
                    setTimeout(openWA, 2000);
                }
            } catch (err) {
                console.error(err);
                showToast("Gagal menyalin, mengunduh gambar...");
                downloadImage();
                setTimeout(openWA, 2000);
            }
        }, 'image/png');
    });
});
</script>

</body>
</html>
<?php exit; ?>
