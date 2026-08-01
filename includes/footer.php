            </div><!-- end content-wrapper -->
        </main>
    </div>
    
    <?php if(isset($_SESSION['user_id'])): ?>
    <!-- BOTTOM NAVIGATION (MOBILE) -->
    <nav class="bottom-nav">
        <a href="<?= BASE_URL ?>pages/dashboard.php" class="bn-item <?= strpos($_SERVER['PHP_SELF'], 'dashboard') !== false ? 'active' : '' ?>">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <?php if (isAdmin()): ?>
        <a href="<?= BASE_URL ?>siswa/" class="bn-item <?= strpos($_SERVER['PHP_SELF'], 'siswa') !== false && strpos($_SERVER['PHP_SELF'], 'daftar-ulang') === false ? 'active' : '' ?>">
            <i class="fas fa-user-graduate"></i>
            <span>Siswa</span>
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>pembayaran/" class="bn-item <?= strpos($_SERVER['PHP_SELF'], 'pembayaran') !== false && strpos($_SERVER['PHP_SELF'], 'verifikasi') === false && strpos($_SERVER['PHP_SELF'], 'kirim') === false ? 'active' : '' ?>">
            <i class="fas fa-history"></i>
            <span>Riwayat</span>
        </a>
        <a href="<?= BASE_URL ?>pembayaran/verifikasi.php" class="bn-item <?= strpos($_SERVER['PHP_SELF'], 'verifikasi') !== false ? 'active' : '' ?>">
            <div class="bn-icon-wrap">
                <i class="fas fa-check-shield"></i>
                <?php 
                $countPendingFooter = 0;
                if(isset($pdo)) {
                    $countPendingFooter = $pdo->query("SELECT COUNT(*) FROM pembayaran WHERE status = 'pending'")->fetchColumn(); 
                }
                if ($countPendingFooter > 0): 
                ?>
                    <span class="bn-badge"><?= $countPendingFooter ?></span>
                <?php endif; ?>
            </div>
            <span>Verifikasi</span>
        </a>
        <a href="#" class="bn-item" onclick="document.getElementById('sidebar').classList.toggle('active'); event.stopPropagation(); return false;">
            <i class="fas fa-bars"></i>
            <span>Lainnya</span>
        </a>
    </nav>
    <?php endif; ?>
    
    <script src="<?= BASE_URL ?>assets/js/script.js?v=2.0.0" defer></script>
    <script>
        // Ultimate fallback: Force click listener on the document level
        document.addEventListener('click', function(e) {
            var toggleWrap = e.target.closest('.theme-switch-wrap') || e.target.closest('.theme-switch');
            if (toggleWrap) {
                e.preventDefault();
                e.stopPropagation();
                if (typeof toggleSiksTheme === 'function') {
                    toggleSiksTheme();
                } else {
                    var isLight = document.body.classList.toggle('light-mode');
                    localStorage.setItem('siks-theme', isLight ? 'light' : 'dark');
                }
            }
        });
    </script>
</body>
</html>
