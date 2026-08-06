<?php
/**
 * Helper Functions
 * Sistem Informasi Pembayaran SPP - SMK Al Amin
 */

session_start();

// Auto update database structure
require_once __DIR__ . '/../config/auto_update.php';

/**
 * Format angka ke format Rupiah
 */
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

/**
 * Format tanggal ke format Indonesia
 */
function formatTanggal($tanggal, $format = 'd F Y') {
    $bulan = [
        'January' => 'Januari',
        'February' => 'Februari',
        'March' => 'Maret',
        'April' => 'April',
        'May' => 'Mei',
        'June' => 'Juni',
        'July' => 'Juli',
        'August' => 'Agustus',
        'September' => 'September',
        'October' => 'Oktober',
        'November' => 'November',
        'December' => 'Desember'
    ];
    
    $tanggal = date($format, strtotime($tanggal));
    return strtr($tanggal, $bulan);
}

/**
 * Cek apakah user sudah login
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Cek login dan redirect jika belum
 */
function checkLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL);
        exit;
    }
}

/**
 * Cek role user
 */
function checkRole($roles) {
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    if (!in_array($_SESSION['role'], $roles)) {
        header('Location: ' . BASE_URL . 'pages/dashboard.php');
        exit;
    }
}

/**
 * Cek apakah user adalah admin
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Cek apakah user adalah bendahara
 */
function isBendahara() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'bendahara';
}

/**
 * Escape output untuk mencegah XSS
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate alert message
 */
function setAlert($type, $message) {
    $_SESSION['alert'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Display alert message
 */
function showAlert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        $type = $alert['type'];
        $message = $alert['message'];
        
        $class = match($type) {
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            default => 'alert-info'
        };
        
        echo "<div class='alert {$class}'>{$message}</div>";
        unset($_SESSION['alert']);
    }
}

/**
 * Get bulan dalam bahasa Indonesia
 */
function getBulanIndonesia() {
    return [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
}

/**
 * Get tahun ajaran aktif
 */
function getTahunAjaranAktif($pdo) {
    $stmt = $pdo->query("SELECT * FROM tahun_ajaran WHERE is_active = 1 LIMIT 1");
    return $stmt->fetch();
}

/**
 * Get SPP aktif
 */
function getSppAktif($pdo) {
    $stmt = $pdo->query("SELECT s.*, t.tahun, t.semester FROM spp s 
                         JOIN tahun_ajaran t ON s.tahun_ajaran_id = t.id 
                         WHERE t.is_active = 1 LIMIT 1");
    return $stmt->fetch();
}

/**
 * Get nominal pembayaran dari setting (SPP, Infak, Komputer)
 */
function getNominalPembayaran($pdo, $jenis = null, $tahun_masuk = null, $bulanTagihan = null, $tahunTagihan = null) {
    try {
        $stmt = $pdo->query("SELECT jenis, nominal, tahun_masuk FROM setting_pembayaran");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $settingsDefault = [];
        $settingsSpecific = [];
        foreach ($results as $s) {
            if ($s['tahun_masuk'] == 0) {
                $settingsDefault[$s['jenis']] = (int)$s['nominal'];
            } elseif ($tahun_masuk !== null && $s['tahun_masuk'] == $tahun_masuk) {
                $settingsSpecific[$s['jenis']] = (int)$s['nominal'];
            }
        }
        
        $settings = array_merge($settingsDefault, $settingsSpecific);
        
        $fallbacks = [
            'SPP' => 50000, 'Infak' => 25000, 'Komputer' => 50000,
            
            // Umum
            'Pendaftaran' => 150000, 'MPLS' => 100000, 'DSP' => 500000,
            'Almamater' => 150000, 'Atribut' => 50000, 'Seragam Olahraga' => 150000,
            'Werpak TKJ' => 200000, 'Rapot' => 50000,
            
            // Kelas 10
            'UTS 1 (Kelas 10)' => 50000, 'UTS 2 (Kelas 10)' => 50000,
            'UAS 1 (Kelas 10)' => 50000, 'UAS 2 (Kelas 10)' => 50000,
            
            // Kelas 11
            'Daftar Ulang Kelas 11' => 200000, 'PKL (Praktik Kerja Lapangan)' => 300000,
            'UTS 1 (Kelas 11)' => 50000, 'UTS 2 (Kelas 11)' => 50000,
            'UAS 1 (Kelas 11)' => 50000, 'UAS 2 (Kelas 11)' => 50000,
            
            // Kelas 12
            'Daftar Ulang Kelas 12' => 200000, 'DAT' => 100000,
            'UTS 1 (Kelas 12)' => 50000, 'UTS 2 (Kelas 12)' => 50000,
            'UAS 1 (Kelas 12)' => 50000
        ];
        
        if ($jenis) {
            if ($jenis === 'SPP' && $bulanTagihan !== null && $tahunTagihan !== null) {
                // Hitung tahun ajaran dari bulan/tahun tagihan
                $bulanNum = array_search($bulanTagihan, getBulanIndonesia()) + 1;
                if ($bulanNum == 0) $bulanNum = 1; // Fallback
                $academicYearTagihan = ($bulanNum < 7) ? $tahunTagihan - 1 : $tahunTagihan;
                
                // Aturan khusus: Tagihan SPP sebelum Tahun Ajaran 2026/2027 tetap 50.000
                if ($academicYearTagihan < 2026) {
                    return 50000;
                }
            }
            if (isset($settings[$jenis])) {
                return $settings[$jenis];
            }
            return $fallbacks[$jenis] ?? 50000;
        }
        
        foreach($fallbacks as $k => $v) {
            if(!isset($settings[$k])) $settings[$k] = $v;
        }
        return $settings;
    } catch (Exception $e) {
        $fallbacks = ['SPP' => 50000];
        return $jenis ? 50000 : $fallbacks;
    }
}

/**
 * Cek apakah jenis pembayaran adalah tahunan (rutin tiap tahun)
 */
function isYearlyPayment($jenis) {
    if (in_array($jenis, ['SPP', 'Infak', 'Komputer'])) return false; // Ini bulanan
    // Karena semua pembayaran ujian/daftar ulang sekarang dipisah per kelas,
    // maka semuanya bersifat "sekali bayar" (one-time) per siswa selama masa studinya.
    $yearly = []; 
    return in_array($jenis, $yearly);
}

/**
 * Cek status pembayaran dan total yang sudah dibayar (mendukung cicilan dan verifikasi)
 */
function cekPembayaran($pdo, $siswaId, $bulan, $tahun, $jenis = 'SPP', $tahun_masuk = null) {
    $isMonthly = in_array($jenis, ['SPP', 'Infak', 'Komputer']);
    $isYearly = isYearlyPayment($jenis);
    
    $where = "WHERE siswa_id = ? AND status != 'ditolak' AND jenis_pembayaran = ?";
    $params = [$siswaId, $jenis];
    
    if ($isMonthly) {
        if ($bulan) {
            $where .= " AND bulan = ?";
            $params[] = $bulan;
        }
        if ($tahun) {
            $where .= " AND tahun = ?";
            $params[] = $tahun;
        }
    } elseif ($isYearly && $tahun) {
        $where .= " AND tahun = ?";
        $params[] = $tahun;
    }
    
    $sql = "SELECT 
                IFNULL(SUM(CASE WHEN status = 'lunas' THEN jumlah_bayar ELSE 0 END), 0) as total_lunas,
                IFNULL(SUM(CASE WHEN status = 'pending' THEN jumlah_bayar ELSE 0 END), 0) as total_pending
            FROM pembayaran $where";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    
    $totalLunas = (float)$result['total_lunas'];
    $totalPending = (float)$result['total_pending'];
    $totalSemua = $totalLunas + $totalPending;
    
    $nominalTagihan = (float)getNominalPembayaran($pdo, $jenis, $tahun_masuk, $bulan, $tahun);
    
    $status = 'belum';
    if ($totalLunas >= $nominalTagihan) {
        $status = 'lunas';
    } elseif ($totalPending > 0 && $totalSemua >= $nominalTagihan) {
        $status = 'pending'; // Sudah bayar cukup, tapi ada yang masih pending
    } elseif ($totalSemua > 0) {
        $status = 'nyicil';
    }

    // NEW: Aturan "SPP sudah termasuk dengan Infak dan Komputer"
    if ($status !== 'lunas' && ($jenis === 'Infak' || $jenis === 'Komputer')) {
        // Cek apakah SPP sudah lunas untuk periode yang sama
        $cekSpp = cekPembayaran($pdo, $siswaId, $bulan, $tahun, 'SPP', $tahun_masuk);
        if ($cekSpp['lunas']) {
            return [
                'lunas' => true,
                'status' => 'lunas',
                'total_dibayar' => $nominalTagihan,
                'total_pending' => 0,
                'sisa' => 0,
                'nominal_tagihan' => $nominalTagihan,
                'keterangan' => 'Ditanggung oleh pembayaran SPP'
            ];
        }
    }
    
    return [
        'lunas' => ($status === 'lunas'),
        'status' => $status,
        'total_dibayar' => $totalLunas, // Hanya kembalikan yang SUDAH VERIFIKASI sebagai 'dibayar'
        'total_pending' => $totalPending,
        'sisa' => max(0, $nominalTagihan - $totalSemua), // Sisa riil (hitung juga yang masih pending agar tidak bayar dobel)
        'nominal_tagihan' => $nominalTagihan
    ];
}

/**
 * Hitung total tunggakan siswa (Mendukung SPP dan Biaya Lainnya)
 */
function hitungTunggakan($pdo, $siswaId, $getDetails = false, $targetMonth = null, $targetYear = null) {
    // Get student info
    $stmt = $pdo->prepare("SELECT s.*, k.nama_kelas, k.tingkat, k.jurusan FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
    $stmt->execute([$siswaId]);
    $siswa = $stmt->fetch();
    if (!$siswa) return $getDetails ? ['total' => 0, 'spp' => [], 'lainnya' => [], 'kenaikan' => 0] : 0;
    
    $tingkatSiswa = $siswa['tingkat'] ?? '';
    $jurusanSiswa = strtoupper($siswa['jurusan'] ?? '');
    
    // Hitung tahun akademik saat ini
    $bulanReal = (int)date('n');
    $tahunReal = (int)date('Y');
    $currentAcademicYear = ($bulanReal < 7) ? $tahunReal - 1 : $tahunReal;
    
    // Tentukan secara presisi kapan siswa ini masuk (mengabaikan db jika tidak valid)
    $startYear = (int)$siswa['tahun_masuk'];
    if ($startYear <= 0) {
        $statusSiswa = $siswa['status'] ?? 'aktif';
        if ($statusSiswa === 'aktif') {
            if ($tingkatSiswa === 'X') {
                $startYear = $currentAcademicYear;
            } elseif ($tingkatSiswa === 'XI') {
                $startYear = $currentAcademicYear - 1;
            } elseif ($tingkatSiswa === 'XII' || $tingkatSiswa === 'Alumni') {
                $startYear = $currentAcademicYear - 2;
            }
        }
    }
    
    if ($targetMonth !== null && $targetYear !== null) {
        $currentYear = (int)$targetYear;
        $currentMonth = (int)$targetMonth;
    } else {
        $nextMonthPadding = (date('j') > 25) ? "+1 month" : "now"; 
        $targetDate = strtotime($nextMonthPadding);
        $currentYear = (int)date('Y', $targetDate);
        $currentMonth = (int)date('n', $targetDate);
    }
    
    $nominalSppStandard = 50000; // Nominal standar dasar
    $nominalSppSekarang = (float)getNominalPembayaran($pdo, 'SPP', $startYear);
    $kenaikanSpp = max(0, $nominalSppSekarang - $nominalSppStandard);
    
    $bulanList = getBulanIndonesia();
    $totalDebt = 0;
    $tunggakanSpp = [];
    $tunggakanLainnya = [];
    $totalKenaikan = 0;
    
    // 1. Hitung Tunggakan SPP (Bulanan)
    for ($y = $startYear; $y <= $currentYear; $y++) {
        $maxMonth = ($y == $currentYear) ? $currentMonth : 12;
        $startM = ($y == $startYear) ? 6 : 0; // Mulai dari Juli (indeks 6)
        
        for ($m = $startM; $m < $maxMonth; $m++) {
            $bln = $bulanList[$m];
            $cek = cekPembayaran($pdo, $siswaId, $bln, $y, 'SPP', $startYear);
            
            if (!$cek['lunas']) {
                $totalDebt += $cek['sisa'];
                $label = $bln . ($y != date('Y') ? " $y" : "");
                
                if ($cek['status'] === 'pending') {
                    $tunggakanSpp[] = $label . " (Proses)";
                } elseif ($cek['status'] === 'nyicil') {
                    $tunggakanSpp[] = $label . " (Kurang " . number_format($cek['sisa'], 0, ',', '.') . ")";
                } else {
                    $tunggakanSpp[] = $label;
                }
                
                // Hitung akumulasi kenaikan biaya jika ada
                if ($kenaikanSpp > 0) {
                    $totalKenaikan += $kenaikanSpp;
                }
            }
        }
    }
    
    // 2. Hitung Tunggakan Biaya Lainnya (Non-Bulanan)
    // Limit pencarian berdasarkan tingkat saat ini
    $tahunLimit = $startYear;
    if ($tingkatSiswa === 'XI') $tahunLimit = $startYear + 1;
    elseif ($tingkatSiswa === 'XII' || $tingkatSiswa === 'Alumni') $tahunLimit = $startYear + 2;

    $categories = getPaymentCategories();
    $categorySummary = []; // NEW: Untuk dashboard agar sinkron

    foreach ($categories as $group => $items) {
        foreach ($items as $name => $meta) {
            // --- Aturan filter kelas/jurusan ---
            if (str_contains($name, 'Werpak TKJ') && $jurusanSiswa !== 'TKJ') continue;
            
            // Filter berdasarkan kelas
            if (str_contains($name, '(Kelas 10)') && !in_array($tingkatSiswa, ['X', 'XI', 'XII', 'Alumni'])) continue;
            if (str_contains($name, 'Kelas 11') || str_contains($name, 'PKL')) {
                if (!in_array($tingkatSiswa, ['XI', 'XII', 'Alumni'])) continue;
            }
            if (str_contains($name, 'Kelas 12') || str_contains($name, 'DAT')) {
                if (!in_array($tingkatSiswa, ['XII', 'Alumni'])) continue;
            }
            // -----------------------------------

            $catStatus = 'lunas';
            $catTotalSisa = 0;

            // Biaya sekali bayar (Karena yearly sudah dihapus, masuk kesini semua)
            $cek = cekPembayaran($pdo, $siswaId, null, null, $name, $startYear);
            if ($cek['sisa'] > 0) {
                $totalDebt += $cek['sisa'];
                $tunggakanLainnya[] = [
                    'nama'   => $name,
                    'sisa'   => $cek['sisa'],
                    'status' => $cek['status']
                ];
                $catStatus = $cek['status'];
            }
            $categorySummary[$name] = $catStatus;
        }
    }
    
    if ($getDetails) {
        return [
            'total' => $totalDebt,
            'spp' => $tunggakanSpp, 
            'lainnya' => $tunggakanLainnya, 
            'categories' => $categorySummary, // NEW: Digunakan Dashboard
            'kenaikan' => $totalKenaikan,
            'bulan' => array_merge($tunggakanSpp, array_column($tunggakanLainnya, 'nama'))
        ];
    }
    
    return $totalDebt;
}

/**
 * Generate nomor transaksi unik
 */
function generateNoTransaksi() {
    return 'TRX-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

/**
 * Menormalkan nomor telepon/WhatsApp
 * Menghapus spasi, strip, dan karakter non-digit lainnya.
 * Mengubah awalan 08 menjadi 628.
 */
function formatNomorWA($nomor) {
    if (empty($nomor)) return null;
    
    // Hapus semua karakter non-digit (kecuali plus di awal jika ada, tapi kita nggah butuh plus buat link WA)
    $clean = preg_replace('/[^0-9]/', '', $nomor);
    
    // Jika diawali 0, ganti dengan 62
    if (strpos($clean, '0') === 0) {
        $clean = '62' . substr($clean, 1);
    }
    
    return $clean;
}

/**
 * Mendapatkan nama file bukti transfer dari keterangan
 */
function getBuktiTransfer($keterangan) {
    if (!$keterangan) return null;
    
    // Ekstrak semua file yang berawalan bukti_ dan memiliki ekstensi gambar
    if (preg_match('/(bukti_[a-zA-Z0-9_]+\.(?:jpg|jpeg|png|gif))/i', $keterangan, $matches)) {
        return $matches[1];
    }
    return null;
}
/**
 * Get all payment categories (Non-SPP)
 */
function getPaymentCategories() {
    return [
        'Pembayaran Rutin' => [
            'SPP' => ['icon' => 'fa-calendar-check', 'color' => '#10b981'],
        ],
        'Pembayaran Umum' => [
            'Pendaftaran' => ['icon' => 'fa-user-plus', 'color' => '#10b981'],
            'MPLS' => ['icon' => 'fa-id-badge', 'color' => '#3b82f6'],
            'DSP' => ['icon' => 'fa-building', 'color' => '#64748b'],
            'Almamater' => ['icon' => 'fa-user-tie', 'color' => '#8b5cf6'],
            'Atribut' => ['icon' => 'fa-tags', 'color' => '#ec4899'],
            'Seragam Olahraga' => ['icon' => 'fa-tshirt', 'color' => '#f59e0b'],
            'Werpak TKJ' => ['icon' => 'fa-tools', 'color' => '#6366f1'],
            'Rapot' => ['icon' => 'fa-folder-open', 'color' => '#06b6d4'],
        ],
        'Kelas 10' => [
            'UTS 1 (Kelas 10)' => ['icon' => 'fa-file-alt', 'color' => '#f43f5e'],
            'UTS 2 (Kelas 10)' => ['icon' => 'fa-book-open', 'color' => '#a855f7'],
            'UAS 1 (Kelas 10)' => ['icon' => 'fa-file-signature', 'color' => '#d946ef'],
            'UAS 2 (Kelas 10)' => ['icon' => 'fa-book', 'color' => '#8b5cf6'],
        ],
        'Kelas 11' => [
            'Daftar Ulang Kelas 11' => ['icon' => 'fa-user-check', 'color' => '#10b981'],
            'UTS 1 (Kelas 11)' => ['icon' => 'fa-file-alt', 'color' => '#f43f5e'],
            'UTS 2 (Kelas 11)' => ['icon' => 'fa-book-open', 'color' => '#a855f7'],
            'UAS 1 (Kelas 11)' => ['icon' => 'fa-file-signature', 'color' => '#d946ef'],
            'UAS 2 (Kelas 11)' => ['icon' => 'fa-book', 'color' => '#8b5cf6'],
            'PKL (Praktik Kerja Lapangan)' => ['icon' => 'fa-briefcase', 'color' => '#0ea5e9'],
        ],
        'Kelas 12' => [
            'Daftar Ulang Kelas 12' => ['icon' => 'fa-user-check', 'color' => '#10b981'],
            'UTS 1 (Kelas 12)' => ['icon' => 'fa-file-alt', 'color' => '#f43f5e'],
            'UTS 2 (Kelas 12)' => ['icon' => 'fa-book-open', 'color' => '#a855f7'],
            'UAS 1 (Kelas 12)' => ['icon' => 'fa-file-signature', 'color' => '#d946ef'],
            'DAT' => ['icon' => 'fa-graduation-cap', 'color' => '#1e293b'],
        ]
    ];
}
