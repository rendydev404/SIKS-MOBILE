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
    <title>Invoice - <?= e($siswa['nama']) ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f4f8;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .invoice-card {
            background-color: #a5c6e8;
            width: 500px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            position: relative;
            color: #333;
        }
        .header-bar {
            background-color: #3b82f6;
            height: 10px;
            width: 100%;
            position: absolute;
            top: 0;
            left: 0;
        }
        .invoice-badge {
            background-color: #ffffff;
            color: #000;
            font-weight: bold;
            display: inline-block;
            padding: 5px 15px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
        }
        .logo-container {
            text-align: right;
            position: absolute;
            top: 20px;
            right: 30px;
        }
        .logo-container img {
            width: 80px;
            height: auto;
        }
        .salutation {
            font-style: italic;
            margin-bottom: 5px;
            clear: both;
        }
        .tagline {
            margin-bottom: 25px;
        }
        .details {
            margin-bottom: 20px;
        }
        .details-row {
            display: flex;
            margin-bottom: 5px;
        }
        .details-label {
            width: 80px;
            font-weight: normal;
        }
        .details-separator {
            width: 20px;
        }
        .details-value {
            font-weight: bold;
        }
        .table-container {
            margin-top: 20px;
        }
        .table-title {
            text-align: center;
            font-weight: bold;
            margin-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #ffffff;
        }
        th, td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #e2e8f0;
        }
        .bg-grey {
            background-color: #cbd5e1;
        }
        .bank-info {
            margin-top: 30px;
            border: 1px solid #000;
            background-color: #cbd5e1;
            padding: 5px;
        }
        .bank-info-row {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            font-size: 14px;
        }
        .wa-instructions {
            margin-top: 20px;
        }
        .wa-number {
            font-weight: bold;
        }
        .signature {
            margin-top: 40px;
            text-align: left;
            margin-left: 20px;
            position: relative;
        }
        .stamp-container {
            position: relative;
            margin-top: 10px;
        }
        .signature-name {
            margin-top: 60px;
            font-weight: bold;
            text-decoration: underline;
        }
        .actions {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        .btn-action {
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            border-radius: 4px;
            font-weight: bold;
        }
        .btn-print { background: #3b82f6; color: white; }
        .btn-copy { background: #f59e0b; color: white; }
        .btn-download { background: #10b981; color: white; }
        .btn-back { background: #6b7280; color: white; }

        /* Toast Styling */
        #toast {
            visibility: hidden;
            min-width: 250px;
            background-color: #333;
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
            .invoice-card { box-shadow: none; border: 1px solid #eee; }
        }
    </style>
</head>
<body>

<div class="actions">
    <button class="btn-action btn-print" onclick="window.print()">
        <i class="fas fa-print"></i> Cetak PDF
    </button>
    <button id="shareBtn" class="btn-action" style="background: #0ea5e9; display: none;">
        <i class="fas fa-share-nodes"></i> Bagikan
    </button>
    <button id="copyAndWaBtn" class="btn-action btn-copy" style="background: #25d366;">
        <i class="fab fa-whatsapp"></i> Salin & Buka WA
    </button>
    <button id="downloadBtn" class="btn-action btn-download">
        <i class="fas fa-image"></i> Download Gambar
    </button>
    <button class="btn-action btn-back" onclick="window.history.back()">
        <i class="fas fa-arrow-left"></i> Kembali
    </button>
</div>

<div id="toast">Gambar berhasil disalin! Tempel (Ctrl+v) di WhatsApp.</div>

<div class="invoice-card" id="invoiceArea">
    <div class="header-bar"></div>
    <div class="logo-container">
        <img src="../assets/img/logo_sekolah.png" alt="Logo Sekolah">
    </div>
    <div class="invoice-badge">INVOICE</div>
    
    <div class="salutation">Assalamu'alikum Wr. Wb.</div>
    <?php
    // Tampilkan bulan sesuai dengan filter yang dipilih
    $taglineBulan = $bulanName;
    $taglineTahun = $tahun;
    ?>
    <div class="tagline">Berikut kami rincian tagihan sampai <?= $taglineBulan ?> <?= $taglineTahun ?></div>

    <div class="details">
        <div class="details-row">
            <div class="details-label">Nama</div>
            <div class="details-separator">:</div>
            <div class="details-value"><?= e($siswa['nama']) ?></div>
        </div>
        <div class="details-row">
            <div class="details-label">Jurusan</div>
            <div class="details-separator">:</div>
            <div class="details-value"><?= e($siswa['nama_kelas'] ?: $siswa['jurusan']) ?></div>
        </div>
    </div>

    <div class="table-container">
        <div class="table-title">Rincian Tagihan SPP</div>
        <table>
            <tr>
                <th width="35%">SPP Bulan s/d <?= $taglineBulan ?></th>
                <td width="10%">Rp</td>
                <td align="right"><?= number_format($totalSppHanya, 0, ',', '.') ?></td>
            </tr>
            <?php if ($totalKenaikan > 0): ?>
            <tr>
                <th>Penyesuaian/Kenaikan Biaya</th>
                <td>Rp</td>
                <td align="right"><?= number_format($totalKenaikan, 0, ',', '.') ?></td>
            </tr>
            <?php endif; ?>
            <tr class="bg-grey" style="font-weight: bold;">
                <td align="center">Total Tagihan SPP</td>
                <td>Rp</td>
                <td align="right"><?= number_format($totalSppHanya + $totalKenaikan, 0, ',', '.') ?></td>
            </tr>
        </table>
    </div>

    <div style="margin-top: 20px;">Pembayaran hanya dapat dilakukan melalui Rek:</div>
    <div class="bank-info">
        <div class="bank-info-row">
            <span>SeaBank</span>
            <span>901612378561</span>
            <span>Mira Humairoh</span>
        </div>
    </div>

    <div class="wa-instructions">
        Setelah itu mohon kirimkan tanda bukti transfer via WhatsApp:<br>
        <span class="wa-number">085880719956</span>
    </div>

    <div class="signature">
        <div>TTD</div>
        <div>Keuangan SMK Al Amin</div>
        
        <div class="stamp-container">
            <div class="signature-name">Memen Rohmatul Ummah S.Pd</div>
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
            try {
                const item = new ClipboardItem({ "image/png": blob });
                navigator.clipboard.write([item]).then(() => {
                    showToast("Gambar berhasil disalin! Membuka WhatsApp...");
                    setTimeout(() => {
                        window.open("<?= $waLink ?>", "_blank");
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fab fa-whatsapp"></i> Salin & Buka WA';
                    }, 1000);
                }).catch(err => {
                    console.error(err);
                    showToast("Gagal menyalin gambar secara otomatis.");
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fab fa-whatsapp"></i> Salin & Buka WA';
                });
            } catch (err) {
                console.error(err);
                showToast("Browser tidak mendukung fitur salin gambar.");
                btn.disabled = false;
                btn.innerHTML = '<i class="fab fa-whatsapp"></i> Salin & Buka WA';
            }
        }, 'image/png');
    });
});
</script>

</body>
</html>
<?php exit; ?>
