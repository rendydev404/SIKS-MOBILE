<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
            <span>SIKS SMK Al Amin</span>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="<?= BASE_URL ?>pages/dashboard.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'dashboard') !== false ? 'active' : '' ?>">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <?php if (isAdmin()): ?>
            <li class="nav-section">Master Data</li>
            
            <li class="nav-item">
                <a href="<?= BASE_URL ?>siswa/" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'siswa') !== false && strpos($_SERVER['PHP_SELF'], 'daftar-ulang') === false ? 'active' : '' ?>">
                    <i class="fas fa-user-graduate"></i>
                    <span>Data Siswa</span>
                </a>
            </li>
            <?php endif; ?>
            
            <li class="nav-section">Data Transaksi</li>
            
            <li class="nav-item">
                <a href="<?= BASE_URL ?>pembayaran/" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'pembayaran') !== false && strpos($_SERVER['PHP_SELF'], 'verifikasi') === false && strpos($_SERVER['PHP_SELF'], 'kirim') === false ? 'active' : '' ?>">
                    <i class="fas fa-history"></i>
                    <span>Riwayat Transaksi</span>
                </a>
            </li>
            
            <li class="nav-item">
                <?php 
                $countPending = $pdo->query("SELECT COUNT(*) FROM pembayaran WHERE status = 'pending'")->fetchColumn(); 
                ?>
                <a href="<?= BASE_URL ?>pembayaran/verifikasi.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'verifikasi') !== false ? 'active' : '' ?>" style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-shield"></i>
                        <span>Verifikasi</span>
                    </span>
                    <?php if ($countPending > 0): ?>
                        <span class="badge" style="background: var(--warning); color: #000; font-size: 10px; padding: 2px 6px;"><?= $countPending ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="<?= BASE_URL ?>pembayaran/kirim-invoice.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'kirim-invoice') !== false ? 'active' : '' ?>">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Kirim Tagihan WA</span>
                </a>
            </li>

            <li class="nav-section">Keuangan Lainnya</li>

            <li class="nav-item">
                <a href="<?= BASE_URL ?>keuangan/" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'keuangan') !== false ? 'active' : '' ?>">
                    <i class="fas fa-th-large"></i>
                    <span>Modul Pembayaran</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="<?= BASE_URL ?>siswa/daftar-ulang.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'daftar-ulang') !== false ? 'active' : '' ?>">
                    <i class="fas fa-user-check"></i>
                    <span>Daftar Ulang</span>
                </a>
            </li>
            
            <li class="nav-section">Laporan</li>
            
            <li class="nav-item">
                <a href="<?= BASE_URL ?>laporan/" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'laporan') !== false && strpos($_SERVER['PHP_SELF'], 'per-') === false ? 'active' : '' ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Laporan Pembayaran</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="<?= BASE_URL ?>laporan/per-bulan.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'per-bulan') !== false ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Laporan Per Bulan</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="<?= BASE_URL ?>laporan/tunggakan.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'tunggakan') !== false ? 'active' : '' ?>">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Laporan Tunggakan</span>
                </a>
            </li>
            
            <?php if (isAdmin()): ?>
            <li class="nav-section">Pengaturan</li>
            
            <li class="nav-item">
                <a href="<?= BASE_URL ?>spp/" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'spp') !== false ? 'active' : '' ?>">
                    <i class="fas fa-cog"></i>
                    <span>Setting Pembayaran</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="<?= BASE_URL ?>tahun-ajaran/" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'tahun-ajaran') !== false ? 'active' : '' ?>">
                    <i class="fas fa-calendar-check"></i>
                    <span>Tahun Ajaran</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="<?= BASE_URL ?>pengumuman/" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'pengumuman') !== false ? 'active' : '' ?>">
                    <i class="fas fa-bullhorn"></i>
                    <span>Pengumuman</span>
                </a>
            </li>

            <li class="nav-section">Data Pendukung</li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>kelas/" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'kelas') !== false ? 'active' : '' ?>">
                    <i class="fas fa-school"></i>
                    <span>Data Kelas</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="<?= BASE_URL ?>users/" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'users') !== false ? 'active' : '' ?>">
                    <i class="fas fa-users-cog"></i>
                    <span>Data User</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= BASE_URL ?>users/ganti-password.php" class="nav-link <?= strpos($_SERVER['PHP_SELF'], 'ganti-password') !== false ? 'active' : '' ?>">
                    <i class="fas fa-key"></i>
                    <span>Ganti Password</span>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    
    <div class="sidebar-footer">
        <p>&copy; <?= date('Y') ?> SMK Al Amin</p>
    </div>
</aside>
