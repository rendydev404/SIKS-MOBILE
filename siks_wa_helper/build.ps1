$ErrorActionPreference = 'Stop'

$compiler = Join-Path $env:WINDIR 'Microsoft.NET\Framework64\v4.0.30319\csc.exe'
if (-not (Test-Path -LiteralPath $compiler)) {
    $compiler = Join-Path $env:WINDIR 'Microsoft.NET\Framework\v4.0.30319\csc.exe'
}
if (-not (Test-Path -LiteralPath $compiler)) {
    throw 'Compiler .NET Framework tidak ditemukan di Windows ini.'
}

$projectRoot = Split-Path -Parent $PSScriptRoot
$outputDirectory = Join-Path $projectRoot 'downloads'
$outputFile = Join-Path $outputDirectory 'SIKSWhatsAppHelper.exe'
$frameworkDirectory = Split-Path -Parent $compiler
$wpfDirectory = Join-Path $frameworkDirectory 'WPF'
$uiAutomationClient = Join-Path $wpfDirectory 'UIAutomationClient.dll'
$uiAutomationTypes = Join-Path $wpfDirectory 'UIAutomationTypes.dll'
$windowsBase = Join-Path $wpfDirectory 'WindowsBase.dll'
New-Item -ItemType Directory -Force -Path $outputDirectory | Out-Null

& $compiler /nologo /target:winexe /optimize+ /debug- /warn:4 /out:$outputFile `
    /r:System.dll /r:System.Core.dll /r:System.Drawing.dll /r:System.Windows.Forms.dll `
    "/r:$uiAutomationClient" "/r:$uiAutomationTypes" "/r:$windowsBase" `
    (Join-Path $PSScriptRoot 'SiksWaProtocol.cs') `
    (Join-Path $PSScriptRoot 'SIKSWhatsAppHelper.cs')

if ($LASTEXITCODE -ne 0) {
    throw "Build helper gagal (exit code $LASTEXITCODE)."
}

Write-Host "Helper berhasil dibuat: $outputFile"
