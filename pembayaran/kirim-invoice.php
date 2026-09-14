<?php
/**
 * Kirim Invoice WA SPP - SMK Al Aminstem Informasi Pembayaran SPP - SMK Al Amin
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
checkLogin();

$pageTitle = 'Kirim Tagihan WA';

// Get data
$filterKelas = $_GET['kelas'] ?? '';
$filterBulan = $_GET['bulan'] ?? date('n');
$tahun = $_GET['tahun'] ?? date('Y');

$bulanList = getBulanIndonesia();
$bulanName = $bulanList[(int)$filterBulan - 1] ?? '';

// Get siswa
$sql = "SELECT s.*, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.status = 'aktif'";
$params = [];
if ($filterKelas) {
    $sql .= " AND s.kelas_id = ?";
    $params[] = $filterKelas;
}
$sql .= " ORDER BY k.tingkat, k.jurusan, s.nama";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$siswaList = $stmt->fetchAll();

$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, jurusan")->fetchAll();

include '../includes/header.php';
?>

<style>
    .invoice-search {
        flex: 0 1 360px;
        min-width: 280px;
    }

    .invoice-search input {
        min-height: 52px;
        padding: 14px 46px 14px 48px;
        border: 1.5px solid var(--border-color);
        border-radius: 14px;
        font-size: 15px;
        box-shadow: var(--shadow-sm);
    }

    .invoice-search input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.12);
    }

    .invoice-search .search-clear {
        display: none;
        position: absolute;
        right: 10px;
        top: 50%;
        width: 32px;
        height: 32px;
        padding: 0;
        transform: translateY(-50%);
        border: 0;
        border-radius: 50%;
        background: transparent;
        color: var(--text-muted);
        cursor: pointer;
    }

    .invoice-search .search-clear:hover {
        background: var(--bg-hover);
        color: var(--text-primary);
    }

    .invoice-search .search-clear.is-visible {
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .invoice-search .search-clear i {
        position: static;
        transform: none;
        color: inherit;
    }

    .invoice-search-hint {
        display: block;
        margin: 7px 0 0 2px;
        color: var(--text-muted);
        font-size: 12px;
    }

    .invoice-search-empty {
        display: none;
        margin: 0 0 16px;
        padding: 14px 16px;
        border: 1px dashed var(--border-color);
        border-radius: 12px;
        color: var(--text-secondary);
        text-align: center;
    }

    .invoice-search-empty.is-visible { display: block; }

    @media (max-width: 600px) {
        .invoice-search { flex-basis: 100%; min-width: 0; }
    }
</style>

<div class="toolbar">
    <div class="alert alert-info" style="margin-bottom: 20px; border-left: 5px solid #25d366; background: rgba(37, 211, 102, 0.08); display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 15px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 38px; height: 38px; border-radius: 10px; background: #25d366; color: white; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                <i class="fab fa-whatsapp"></i>
            </div>
            <div>
                <h5 style="margin: 0; font-size: 15px; font-weight: 700;">WhatsApp Gateway Multi-Device (Anti-Ban)</h5>
                <p style="margin: 2px 0 0; font-size: 13px; color: var(--text-muted);">
                    Pengiriman otomatis invoice kartu visual & rincian tagihan via 2 nomor bergantian dengan jeda acak 20-45 detik.
                </p>
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 10px;">
            <span id="gatewayPillDev1" class="badge" style="background: var(--bg-hover); color: var(--text-muted); font-size: 11px;">Nomor 1: Memeriksa...</span>
            <span id="gatewayPillDev2" class="badge" style="background: var(--bg-hover); color: var(--text-muted); font-size: 11px;">Nomor 2: Memeriksa...</span>
            <a href="../keuangan/whatsapp-gateway.php" class="btn btn-secondary btn-sm" style="font-size: 12px;">
                <i class="fas fa-qrcode"></i> Kelola Perangkat
            </a>
        </div>
    </div>

    <!-- Toolbar Seleksi & Aksi Blast -->
    <div style="background: var(--bg-card); border: 1.5px solid var(--border-color); border-radius: 14px; padding: 14px 18px; margin-bottom: 20px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <label style="display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 14px; cursor: pointer; user-select: none;">
                <input type="checkbox" id="selectAllCheckbox" style="width: 18px; height: 18px; cursor: pointer;">
                <span>Pilih Semua Siswa</span>
            </label>
            <span id="selectionCounter" class="badge" style="background: var(--primary); color: white; font-size: 12px; padding: 4px 8px;">0 dipilih</span>
        </div>
        <div style="display: flex; gap: 10px;">
            <button type="button" id="btnOpenRunningMonitor" class="btn btn-info btn-sm" style="display: none;">
                <i class="fas fa-chart-line"></i> Pantau Pengiriman Aktif
            </button>
            <button type="button" id="btnStartBlast" class="btn btn-success btn-sm" style="font-weight: 600; padding: 8px 16px; background: #22c55e; border-color: #16a34a;" disabled>
                <i class="fab fa-whatsapp"></i> Kirim Tagihan Otomatis ke WA (<span id="btnSelectCount">0</span>)
            </button>
        </div>
    </div>

    <div class="search-box invoice-search">
        <i class="fas fa-search"></i>
        <input type="search" id="searchInput" placeholder="Cari nama atau NIS siswa" aria-label="Cari siswa berdasarkan nama atau NIS" autocomplete="off">
        <button type="button" id="clearSearch" class="search-clear" aria-label="Hapus pencarian" title="Hapus pencarian">
            <i class="fas fa-times"></i>
        </button>
        <span id="searchStatus" class="invoice-search-hint" aria-live="polite">Cari berdasarkan nama atau NIS siswa.</span>
    </div>
    <div class="filter-group">
        <form method="GET" style="display: flex; gap: 12px; flex-wrap: wrap;">
            <select name="kelas" class="form-control form-control-simple" style="width: auto;" onchange="this.form.submit()">
                <option value="">Semua Kelas</option>
                <?php foreach ($kelasList as $kelas): ?>
                    <option value="<?= $kelas['id'] ?>" <?= $filterKelas == $kelas['id'] ? 'selected' : '' ?>>
                        <?= e($kelas['nama_kelas']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="bulan" class="form-control form-control-simple" style="width: auto;" onchange="this.form.submit()">
                <?php foreach ($bulanList as $i => $bulan): ?>
                    <option value="<?= $i + 1 ?>" <?= $filterBulan == ($i + 1) ? 'selected' : '' ?>>
                        <?= $bulan ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="tahun" class="form-control form-control-simple" style="width: auto;" onchange="this.form.submit()">
                <?php 
                $startYear = 2024;
                $endYear = date('Y') + 2;
                for ($y = $startYear; $y <= $endYear; $y++): 
                ?>
                    <option value="<?= $y ?>" <?= $tahun == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>
</div>

<div id="searchEmpty" class="invoice-search-empty" role="status">
    <i class="fas fa-search" aria-hidden="true"></i> Tidak ada siswa yang cocok dengan pencarian.
</div>

                    <?php 
                    // Menggunakan filter bulan & tahun yang dipilih
                    $ceilBulan = (int)$filterBulan;
                    $ceilTahun = (int)$tahun;
                    $ceilBulanName = $bulanList[$ceilBulan - 1];
                    ?>
<div class="alert alert-info" style="border-left: 5px solid #8b5cf6;">
    <i class="fas fa-calendar-alt"></i> <strong>Sistem Penagihan Sesuai Filter:</strong><br>
    Sistem saat ini menampilkan tagihan SPP sampai dengan bulan <strong><?= $ceilBulanName ?> <?= $ceilTahun ?></strong> sesuai dengan filter yang Anda pilih di atas.
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Daftar Siswa - Invoice Tagihan s/d <?= $ceilBulanName ?> <?= $ceilTahun ?></h2>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th width="4%" style="text-align: center;">
                            <input type="checkbox" id="tableHeaderCheck" title="Pilih semua baris yang tampil">
                        </th>
                        <th width="4%">No</th>
                        <th>NIS</th>
                        <th>Nama</th>
                        <th>Kelas</th>
                        <th>Status SPP <?= $bulanName ?></th>
                        <th>Rincian Tunggakan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = 1; 
                    ?>
                    <?php if (count($siswaList) > 0): ?>

                    <?php foreach ($siswaList as $siswa): 
                        // Hitung tunggakan berdasarkan filter
                        $dataTunggakan = hitungTunggakan($pdo, $siswa['id'], true, $ceilBulan, $ceilTahun);
                        $tunggakan = $dataTunggakan['total'];
                        $tunggakanBulan = $dataTunggakan['bulan'];
                        
                        // Jika tidak ada tunggakan, SKIP siswa ini (Sembunyikan yg lunas)
                        if ($tunggakan <= 0) continue;

                        $noWa = formatNomorWA($siswa['no_whatsapp'] ?? '');
                    ?>
                    <tr class="invoice-row" data-search="<?= e(strtolower($siswa['nis'] . ' ' . $siswa['nama'])) ?>">
                        <td style="text-align: center;">
                            <input type="checkbox" class="student-row-check" value="<?= $siswa['id'] ?>" data-nama="<?= e($siswa['nama']) ?>" data-wa="<?= e($noWa ?: '') ?>">
                        </td>
                        <td style="text-align: center;"><?= $no++ ?></td>
                        <td><?= e($siswa['nis']) ?></td>
                        <td>
                            <strong><?= e($siswa['nama']) ?></strong>
                            <?php if ($noWa): ?>
                                <br><small style="color: #22c55e;"><i class="fab fa-whatsapp"></i> <?= e($noWa) ?></small>
                            <?php else: ?>
                                <br><small style="color: var(--danger);"><i class="fas fa-exclamation-triangle"></i> No WA belum diisi</small>
                            <?php endif; ?>
                        </td>
                        <td><?= e($siswa['nama_kelas'] ?? '-') ?></td>
                        <td>
                            <?php 
                            // Cek status cicilan/pembayaran khusus bulan filter
                            $cekStat = $pdo->prepare("SELECT status FROM pembayaran WHERE siswa_id = ? AND bulan = ? AND tahun = ? AND jenis_pembayaran = 'SPP' ORDER BY created_at DESC LIMIT 1");
                            $cekStat->execute([$siswa['id'], $bulanName, $tahun]);
                            $resStat = $cekStat->fetch();
                            $lastStatus = $resStat['status'] ?? null;

                            if ($lastStatus == 'pending'): ?>
                                <span class="badge" style="background: var(--warning); color: #000;">Verifikasi Admin</span>
                            <?php else: 
                                $cekBulan = cekPembayaran($pdo, $siswa['id'], $bulanName, $tahun, 'SPP', (int)($siswa['tahun_masuk'] ?? 0));
                                if ($cekBulan['status'] == 'lunas'): ?>
                                    <span class="badge badge-success">Lunas</span>
                                <?php elseif ($cekBulan['status'] == 'nyicil'): ?>
                                    <span class="badge" style="background: #0ea5e9; color: #fff;">Nyicil (Kurang <?= number_format($cekBulan['sisa'], 0, ',', '.') ?>)</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Belum</span>
                                <?php endif; 
                            endif; ?>
                        </td>
                        <td>
                            <strong class="text-danger"><?= formatRupiah($tunggakan) ?></strong>
                            <br>
                            <span style="font-size: 11px; color: var(--text-muted); display: block; max-width: 250px; line-height: 1.2;">
                                <?= implode(', ', array_slice($tunggakanBulan, 0, 5)) ?>
                                <?= count($tunggakanBulan) > 5 ? '...' : '' ?>
                            </span>
                        </td>
                        <td>
                             <a href="invoice-view.php?siswa_id=<?= $siswa['id'] ?>&bulan=<?= $filterBulan ?>&tahun=<?= $tahun ?>" 
                                class="btn btn-secondary btn-sm" title="Lihat Manual Invoice">
                                <i class="fas fa-eye"></i> Manual
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <?php if ($no == 1): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">
                            <i class="fas fa-check-circle" style="font-size: 40px; margin-bottom: 10px; color: var(--success); display: block;"></i>
                            Semua siswa sudah lunas untuk periode ini!
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Realtime Monitor Blast -->
<div id="blastMonitorModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.75); backdrop-filter: blur(4px); z-index: 99999; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: var(--bg-card); border-radius: 20px; max-width: 580px; width: 100%; border: 1.5px solid var(--border-color); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); overflow: hidden;">
        <div style="padding: 20px 24px; background: linear-gradient(135deg, #1e293b, #0f172a); color: white; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 36px; height: 36px; border-radius: 10px; background: #22c55e; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                    <i class="fab fa-whatsapp"></i>
                </div>
                <div>
                    <h4 style="margin: 0; font-size: 16px; font-weight: 700;">Live Monitor Broadcast Tagihan</h4>
                    <span id="monitorStatusPill" class="badge" style="background: #22c55e; color: white; font-size: 11px; margin-top: 4px;">RUNNING</span>
                </div>
            </div>
            <button type="button" onclick="closeMonitorModal()" style="background: transparent; border: none; color: white; font-size: 20px; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div style="padding: 24px;">
            <div class="alert alert-info" style="margin-bottom: 20px; font-size: 12px; border-left: 4px solid #0ea5e9;">
                <i class="fas fa-info-circle"></i> <strong>Pengiriman berjalan di background VPS:</strong> Anda bebas menutup browser atau laptop, proses pengiriman tetap berlanjut sampai tuntas.
            </div>

            <!-- Progress Bar -->
            <div style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; font-size: 14px; font-weight: 600; margin-bottom: 8px;">
                    <span>Kemajuan Pengiriman</span>
                    <span id="progressPercentText">0%</span>
                </div>
                <div style="width: 100%; height: 16px; background: var(--bg-body); border-radius: 999px; overflow: hidden; border: 1px solid var(--border-color);">
                    <div id="progressBarFill" style="width: 0%; height: 100%; background: linear-gradient(90deg, #22c55e, #10b981); transition: width 0.4s ease;"></div>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--text-muted); margin-top: 6px;">
                    <span id="progressCountText">0 dari 0 Siswa</span>
                    <span>Sukses: <strong id="progressSuccessCount" style="color: #22c55e;">0</strong> | Gagal: <strong id="progressFailedCount" style="color: var(--danger);">0</strong></span>
                </div>
            </div>

            <!-- Current Active Student Card -->
            <div id="activeStudentCard" style="background: var(--bg-body); border-radius: 12px; padding: 14px; margin-bottom: 20px; border: 1px solid var(--border-color);">
                <div style="font-size: 11px; text-transform: uppercase; color: var(--text-muted); font-weight: 700; letter-spacing: 0.5px;">Sedang Diproses</div>
                <div id="activeStudentName" style="font-size: 15px; font-weight: 700; margin: 4px 0 2px;">Menyiapkan antrean...</div>
                <div style="display: flex; gap: 12px; font-size: 12px; color: var(--text-muted);">
                    <span id="activeDeviceName"><i class="fas fa-sim-card"></i> Menentukan nomor pengirim...</span>
                    <span id="activeDelayCountdown" style="color: #f59e0b; font-weight: 600;"><i class="fas fa-stopwatch"></i> Menghitung jeda...</span>
                </div>
            </div>

            <!-- Cooldown Notice -->
            <div id="cooldownAlert" style="display: none; background: rgba(245, 158, 11, 0.1); border: 1px solid #f59e0b; border-radius: 12px; padding: 12px; margin-bottom: 20px; font-size: 12px; color: #b45309;">
                <i class="fas fa-coffee"></i> <strong>Batch Cooldown Aktif:</strong> Bot sedang istirahat sejenak untuk menjaga reputasi nomor dari algoritma spam WhatsApp. Pengiriman akan otomatis lanjut setelah timer selesai.
            </div>

            <!-- Controls -->
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--border-color);">
                <button type="button" id="btnControlPause" class="btn btn-warning btn-sm" onclick="sendBlastControl('pause')">
                    <i class="fas fa-pause"></i> Jeda (Pause)
                </button>
                <button type="button" id="btnControlResume" class="btn btn-success btn-sm" style="display: none;" onclick="sendBlastControl('resume')">
                    <i class="fas fa-play"></i> Lanjutkan (Resume)
                </button>
                <button type="button" id="btnControlStop" class="btn btn-danger btn-sm" onclick="sendBlastControl('stop')">
                    <i class="fas fa-stop"></i> Batalkan (Stop)
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeMonitorModal()">
                    Tutup Jendela
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Search & Filter
const searchInput = document.getElementById('searchInput');
const clearSearch = document.getElementById('clearSearch');
const searchStatus = document.getElementById('searchStatus');
const searchEmpty = document.getElementById('searchEmpty');
const invoiceRows = Array.from(document.querySelectorAll('.invoice-row'));

function filterInvoices() {
    const query = searchInput.value.trim().toLowerCase();
    let visibleCount = 0;

    invoiceRows.forEach((row) => {
        const matches = !query || row.dataset.search.includes(query);
        row.hidden = !matches;
        if (matches) visibleCount += 1;
    });

    clearSearch.classList.toggle('is-visible', query.length > 0);
    searchEmpty.classList.toggle('is-visible', query.length > 0 && visibleCount === 0);
    searchStatus.textContent = query
        ? `${visibleCount} siswa ditemukan untuk “${searchInput.value.trim()}”.`
        : 'Cari berdasarkan nama atau NIS siswa.';
}

searchInput.addEventListener('input', filterInvoices);
clearSearch.addEventListener('click', () => {
    searchInput.value = '';
    filterInvoices();
    searchInput.focus();
});

// Selection & Batch Logic
const selectAllCheckbox = document.getElementById('selectAllCheckbox');
const tableHeaderCheck = document.getElementById('tableHeaderCheck');
const studentChecks = Array.from(document.querySelectorAll('.student-row-check'));
const selectionCounter = document.getElementById('selectionCounter');
const btnSelectCount = document.getElementById('btnSelectCount');
const btnStartBlast = document.getElementById('btnStartBlast');
const btnOpenRunningMonitor = document.getElementById('btnOpenRunningMonitor');

function updateSelectionState() {
    const selected = studentChecks.filter(c => c.checked);
    const count = selected.length;
    
    selectionCounter.textContent = `${count} dipilih`;
    btnSelectCount.textContent = count;
    btnStartBlast.disabled = (count === 0);

    const visibleChecks = studentChecks.filter(c => !c.closest('tr').hidden);
    const allVisibleChecked = visibleChecks.length > 0 && visibleChecks.every(c => c.checked);
    selectAllCheckbox.checked = (count > 0 && count === studentChecks.length);
    tableHeaderCheck.checked = allVisibleChecked;
}

selectAllCheckbox.addEventListener('change', (e) => {
    const isChecked = e.target.checked;
    studentChecks.forEach(c => { c.checked = isChecked; });
    tableHeaderCheck.checked = isChecked;
    updateSelectionState();
});

tableHeaderCheck.addEventListener('change', (e) => {
    const isChecked = e.target.checked;
    // Hanya centang baris yang saat ini terlihat sesuai filter search
    studentChecks.forEach(c => {
        if (!c.closest('tr').hidden) {
            c.checked = isChecked;
        }
    });
    updateSelectionState();
});

studentChecks.forEach(c => {
    c.addEventListener('change', updateSelectionState);
});

// Gateway Status Check
const pillDev1 = document.getElementById('gatewayPillDev1');
const pillDev2 = document.getElementById('gatewayPillDev2');

async function checkGatewayPills() {
    try {
        const res = await fetch('../keuangan/whatsapp-gateway.php?ajax=status');
        const data = await res.json();
        
        if (data && data.success && data.devices) {
            const dev1 = data.devices.device_1;
            const dev2 = data.devices.device_2;

            if (dev1?.status === 'connected') {
                pillDev1.className = 'badge badge-success';
                pillDev1.innerHTML = `<i class="fas fa-check-circle"></i> Nomor 1: ${dev1.phone || 'Online'}`;
            } else {
                pillDev1.className = 'badge badge-danger';
                pillDev1.innerHTML = `<i class="fas fa-times-circle"></i> Nomor 1: Terputus`;
            }

            if (dev2?.status === 'connected') {
                pillDev2.className = 'badge badge-success';
                pillDev2.innerHTML = `<i class="fas fa-check-circle"></i> Nomor 2: ${dev2.phone || 'Online'}`;
            } else {
                pillDev2.className = 'badge badge-danger';
                pillDev2.innerHTML = `<i class="fas fa-times-circle"></i> Nomor 2: Terputus`;
            }
        } else {
            pillDev1.className = 'badge badge-danger';
            pillDev1.textContent = 'Gateway Offline';
            pillDev2.className = 'badge badge-danger';
            pillDev2.textContent = 'Gateway Offline';
        }
    } catch (_) {
        pillDev1.className = 'badge badge-danger';
        pillDev1.textContent = 'Gateway Offline';
        pillDev2.className = 'badge badge-danger';
        pillDev2.textContent = 'Gateway Offline';
    }
}

checkGatewayPills();

// Modal & Live Monitor
const monitorModal = document.getElementById('blastMonitorModal');
const monitorStatusPill = document.getElementById('monitorStatusPill');
const progressPercentText = document.getElementById('progressPercentText');
const progressBarFill = document.getElementById('progressBarFill');
const progressCountText = document.getElementById('progressCountText');
const progressSuccessCount = document.getElementById('progressSuccessCount');
const progressFailedCount = document.getElementById('progressFailedCount');
const activeStudentName = document.getElementById('activeStudentName');
const activeDeviceName = document.getElementById('activeDeviceName');
const activeDelayCountdown = document.getElementById('activeDelayCountdown');
const cooldownAlert = document.getElementById('cooldownAlert');
const btnControlPause = document.getElementById('btnControlPause');
const btnControlResume = document.getElementById('btnControlResume');

let pollInterval = null;

function openMonitorModal() {
    monitorModal.style.display = 'flex';
}

function closeMonitorModal() {
    monitorModal.style.display = 'none';
}

async function pollProgress() {
    try {
        const res = await fetch('ajax-blast.php?action=progress');
        const data = await res.json();
        const p = data?.progress;

        if (!p) return;

        if (p.status === 'running' || p.status === 'paused') {
            btnOpenRunningMonitor.style.display = 'inline-block';
        } else {
            btnOpenRunningMonitor.style.display = 'none';
        }

        progressPercentText.textContent = `${p.percentage || 0}%`;
        progressBarFill.style.width = `${p.percentage || 0}%`;
        progressCountText.textContent = `${(p.success || 0) + (p.failed || 0)} dari ${p.total || 0} Siswa`;
        progressSuccessCount.textContent = p.success || 0;
        progressFailedCount.textContent = p.failed || 0;

        if (p.status === 'running') {
            monitorStatusPill.className = 'badge badge-success';
            monitorStatusPill.textContent = 'RUNNING';
            btnControlPause.style.display = 'inline-block';
            btnControlResume.style.display = 'none';
        } else if (p.status === 'paused') {
            monitorStatusPill.className = 'badge badge-warning';
            monitorStatusPill.textContent = 'PAUSED';
            btnControlPause.style.display = 'none';
            btnControlResume.style.display = 'inline-block';
        } else if (p.status === 'idle' && p.total > 0) {
            monitorStatusPill.className = 'badge badge-success';
            monitorStatusPill.textContent = 'SELESAI';
            btnControlPause.style.display = 'none';
            btnControlResume.style.display = 'none';
        }

        // Active student
        if (p.currentStudent) {
            activeStudentName.textContent = `${p.currentStudent.nama} (${p.currentStudent.kelas})`;
        } else if (p.status === 'idle') {
            activeStudentName.textContent = 'Semua pengiriman selesai!';
        }

        // Device
        if (p.currentDevice) {
            activeDeviceName.innerHTML = `<i class="fas fa-sim-card"></i> Pengirim: <strong>${p.currentDevice === 'device_1' ? 'Nomor 1' : 'Nomor 2'}</strong>`;
        }

        // Delay & cooldown
        cooldownAlert.style.display = p.isCoolingDown ? 'block' : 'none';
        if (p.delayRemaining > 0) {
            activeDelayCountdown.innerHTML = `<i class="fas fa-stopwatch"></i> Jeda aman: ${p.delayRemaining} detik`;
        } else {
            activeDelayCountdown.innerHTML = `<i class="fas fa-paper-plane"></i> Mengirim...`;
        }

    } catch (err) {
        console.error('Error polling blast progress:', err);
    }
}

// Start Broadcast Button
btnStartBlast.addEventListener('click', async () => {
    const selected = studentChecks.filter(c => c.checked).map(c => c.value);
    if (selected.length === 0) return;

    if (!confirm(`Mulai kirim tagihan otomatis untuk ${selected.length} siswa terpilih?\n\nPengiriman menggunakan 2 nomor secara bergantian dengan jeda acak 20-45 detik per siswa.`)) {
        return;
    }

    btnStartBlast.disabled = true;
    btnStartBlast.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mempersiapkan...';

    const formData = new FormData();
    formData.append('action', 'start');
    selected.forEach(id => formData.append('siswa_ids[]', id));
    formData.append('bulan', '<?= $filterBulan ?>');
    formData.append('tahun', '<?= $tahun ?>');

    try {
        const res = await fetch('ajax-blast.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (data.success) {
            openMonitorModal();
            pollProgress();
            if (!pollInterval) pollInterval = setInterval(pollProgress, 2500);
        } else {
            alert('Gagal memulai broadcast: ' + (data.error || 'Terjadi kesalahan'));
        }
    } catch (err) {
        alert('Gagal menghubungi server.');
    } finally {
        btnStartBlast.disabled = false;
        updateSelectionState();
    }
});

btnOpenRunningMonitor.addEventListener('click', openMonitorModal);

async function sendBlastControl(subAction) {
    const formData = new FormData();
    formData.append('action', 'control');
    formData.append('sub_action', subAction);

    try {
        const res = await fetch('ajax-blast.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        pollProgress();
    } catch (_) {
        alert('Gagal mengirim perintah kontrol.');
    }
}

// Cek saat halaman dimuat apakah ada broadcast yang sedang jalan
pollProgress().then(() => {
    pollInterval = setInterval(pollProgress, 3000);
});
</script>

<?php include '../includes/footer.php'; ?>
