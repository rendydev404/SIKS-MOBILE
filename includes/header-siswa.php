<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" media="(prefers-color-scheme: dark)" content="#1e293b">
    <meta name="theme-color" media="(prefers-color-scheme: light)" content="#f1f5f9">
    <title><?= $pageTitle ?> - SIKS SMK Al Amin</title>
    <script>
        if (navigator.userAgent.indexOf('SIKSApp/') !== -1) {
            document.documentElement.classList.add('native-app-mode');
        }
    </script>
    
    <!-- Preconnect & Preload for performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="preload" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>" as="style">
    <link rel="preload" href="../assets/css/responsive.css?v=<?= filemtime(__DIR__ . '/../assets/css/responsive.css') ?>" as="style">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
    <link rel="stylesheet" href="../assets/css/responsive.css?v=<?= filemtime(__DIR__ . '/../assets/css/responsive.css') ?>">
    <style>
        :root {
            --accent-yellow: #fbbf24;
            --accent-blue: #3b82f6;
            --accent-green: #10b981;
            --accent-red: #ef4444;
        }
        body {
            background: radial-gradient(circle at top right, #1e293b, #0f172a);
            overflow-x: hidden;
            min-height: 100vh;
        }
        body.light-mode {
            background: radial-gradient(circle at top right, #e2e8f0, #f1f5f9) !important;
        }
        .container { max-width: 1000px; margin: 0 auto; padding: 30px 20px 60px; }
        
        .siswa-portal-header {
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255,255,255,0.05);
            padding: 16px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        body.light-mode .siswa-portal-header {
            background: rgba(255,255,255,0.92);
            border-bottom-color: #e2e8f0;
        }
        .brand-section h1 { font-size: 20px; font-weight: 700; color: #fff; margin: 0; }
        body.light-mode .brand-section h1 { color: #0f172a; }
        .brand-section p { font-size: 13px; color: var(--text-secondary); margin: 0; }
        
        .header-actions { display: flex; gap: 10px; align-items: center; }
        .action-btn { 
            width: 42px; height: 42px; border-radius: 12px; display: flex; 
            align-items: center; justify-content: center; transition: all 0.3s ease;
            cursor: pointer; position: relative; text-decoration: none;
        }
        .action-btn:hover { transform: translateY(-3px); }
        .action-btn.pass { background: rgba(255,255,255,0.05); color: var(--text-primary); border: 1px solid rgba(255,255,255,0.1); }
        body.light-mode .action-btn.pass { background: #e2e8f0; border-color: #cbd5e1; color: #334155; }
        .action-btn.logout { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2); }

        .stat-card.glass {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        /* Detail Page Styles */
        .status-hero {
            padding: 40px; border-radius: 24px; text-align: center; margin-bottom: 30px;
        }
        
        /* Grid */
        .month-flow { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 15px; }
        .month-node { 
            background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); 
            border-radius: 16px; padding: 20px 15px; text-align: center;
            transition: all 0.3s ease; display: flex; flex-direction: column; align-items: center; gap: 10px;
        }
        body.light-mode .month-node { background: #fff; border-color: #e2e8f0; }
        .month-node.lunas { border-color: rgba(16, 185, 129, 0.3); background: rgba(16, 185, 129, 0.03); }
        body.light-mode .month-node.lunas { background: #f0fdf4; border-color: rgba(16,185,129,0.3); }
        .status-badge { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 10px; text-transform: uppercase; }

        @media (max-width: 992px) {
            .siswa-portal-header { padding: 16px 20px; flex-direction: column; gap: 12px; height: auto; }
            .header-actions { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <script>
        // Apply saved theme instantly
        if(localStorage.getItem('siks-theme') === 'light') document.body.classList.add('light-mode');
        
        // Global toggle function
        function toggleSiksTheme() {
            var isLight = document.body.classList.toggle('light-mode');
            localStorage.setItem('siks-theme', isLight ? 'light' : 'dark');
        }
    </script>
    <header class="siswa-portal-header">
        <div class="brand-section">
            <h1>Portal Siswa</h1>
            <p>SMK AL AMIN</p>
        </div>
        <div class="header-actions">
            <!-- Premium Theme Toggle Switch -->
            <button class="theme-switch-wrap" onclick="toggleSiksTheme()" style="background:transparent; border:none; padding:0; cursor:pointer; z-index:999999; display:flex; align-items:center; gap:8px; outline:none;">
                <span class="theme-switch-label">Mode</span>
                <div class="theme-switch" title="Mode Terang / Gelap" style="pointer-events:none;">
                    <span class="ts-stars"></span>
                    <i class="fas fa-sun ts-sun"></i>
                    <i class="fas fa-moon ts-moon"></i>
                </div>
            </button>
            <a href="ganti-password.php" class="action-btn pass" title="Ganti Password">
                <i class="fas fa-key"></i>
            </a>
            <a href="logout.php" class="action-btn logout" title="Keluar">
                <i class="fas fa-power-off"></i>
            </a>
        </div>
    </header>
