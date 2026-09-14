# PowerShell Script Deploy WhatsApp Gateway ke VPS
param (
    [string]$VpsHost = "76.13.193.138",
    [string]$VpsUser = "root"
)

$ErrorActionPreference = "Stop"

Write-Host "=========================================" -ForegroundColor Cyan
Write-Host "🚀 Memulai Deployment siks-wa-gateway ke VPS ($VpsHost)" -ForegroundColor Green
Write-Host "=========================================" -ForegroundColor Cyan

$localPath = "$PSScriptRoot"
$vpsDir = "/opt/siks-wa-gateway"

# 1. Pastikan direktori tujuan ada di VPS
Write-Host "1. Menyiapkan direktori di VPS..." -ForegroundColor Yellow
ssh -o BatchMode=yes "$VpsUser@$VpsHost" "mkdir -p $vpsDir/src/services $vpsDir/src/templates $vpsDir/data/sessions"

# 2. Salin file konfigurasi dan source code ke VPS
Write-Host "2. Mengunggah file source code & Docker config..." -ForegroundColor Yellow
scp "$localPath/package.json" "$VpsUser@$VpsHost`:$vpsDir/package.json"
if (Test-Path "$localPath/package-lock.json") {
    scp "$localPath/package-lock.json" "$VpsUser@$VpsHost`:$vpsDir/package-lock.json"
}
scp "$localPath/Dockerfile" "$VpsUser@$VpsHost`:$vpsDir/Dockerfile"
scp "$localPath/docker-compose.yml" "$VpsUser@$VpsHost`:$vpsDir/docker-compose.yml"
scp "$localPath/.env.example" "$VpsUser@$VpsHost`:$vpsDir/.env"
scp -r "$localPath/src/*" "$VpsUser@$VpsHost`:$vpsDir/src/"

# 3. Salin logo sekolah ke direktori template VPS
$schoolLogo = "$localPath/../assets/img/logo_sekolah.png"
if (Test-Path $schoolLogo) {
    scp $schoolLogo "$VpsUser@$VpsHost`:$vpsDir/src/templates/logo_sekolah.png"
}

# 4. Build dan jalankan Docker container di VPS
Write-Host "3. Membangun dan menjalankan Docker Container di VPS..." -ForegroundColor Yellow
ssh -o BatchMode=yes "$VpsUser@$VpsHost" "cd $vpsDir && docker compose up -d --build"

# 5. Cek status container
Write-Host "4. Memeriksa status kesehatan container..." -ForegroundColor Yellow
Start-Sleep -Seconds 5
$check = ssh -o BatchMode=yes "$VpsUser@$VpsHost" "docker ps --filter name=siks-wa-gateway --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}' && curl -s http://localhost:3050/health || true"
Write-Host $check -ForegroundColor Green

Write-Host "=========================================" -ForegroundColor Cyan
Write-Host "✅ Deployment Selesai! Gateway berjalan di http://$VpsHost`:3050" -ForegroundColor Green
Write-Host "=========================================" -ForegroundColor Cyan
