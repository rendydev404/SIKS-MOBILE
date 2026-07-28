<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?>SIKS SMK Al Amin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/responsive.css?v=<?= time() ?>">
</head>
<body>
    <script>
        // Apply saved theme instantly
        if(localStorage.getItem('siks-theme') === 'light') document.body.classList.add('light-mode');
        
        // Global toggle function to guarantee it works regardless of DOM load order
        function toggleSiksTheme() {
            var isLight = document.body.classList.toggle('light-mode');
            localStorage.setItem('siks-theme', isLight ? 'light' : 'dark');
        }
    </script>
    <div class="app-container">
        <?php include __DIR__ . '/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="top-header">
                <div class="header-left">
                    <button class="menu-toggle" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="page-title"><?= isset($pageTitle) ? e($pageTitle) : 'Dashboard' ?></h1>
                </div>
                <div class="header-right" style="display:flex;align-items:center;gap:12px; position:relative; z-index:999999;">
                    <!-- Premium Theme Toggle Switch -->
                    <button class="theme-switch-wrap" onclick="toggleSiksTheme()" style="background:transparent; border:none; padding:0; cursor:pointer; z-index:999999; display:flex; align-items:center; gap:8px; outline:none;">
                        <span class="theme-switch-label">Mode</span>
                        <div class="theme-switch" title="Mode Terang / Gelap" style="pointer-events:none;">
                            <span class="ts-stars"></span>
                            <i class="fas fa-sun ts-sun"></i>
                            <i class="fas fa-moon ts-moon"></i>
                        </div>
                    </button>
                    <div class="user-info">
                        <span class="user-name"><?= e($_SESSION['nama_lengkap'] ?? 'User') ?></span>
                        <span class="user-role badge badge-<?= $_SESSION['role'] ?? 'user' ?>">
                            <?= ucfirst($_SESSION['role'] ?? 'User') ?>
                        </span>
                    </div>
                    <a href="<?= BASE_URL ?>logout.php" class="btn-logout" title="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </header>
            
            <div class="content-wrapper">
                <?php showAlert(); ?>
