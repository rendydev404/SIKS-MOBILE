$ErrorActionPreference = 'Stop'

$compiler = Join-Path $env:WINDIR 'Microsoft.NET\Framework64\v4.0.30319\csc.exe'
if (-not (Test-Path -LiteralPath $compiler)) {
    $compiler = Join-Path $env:WINDIR 'Microsoft.NET\Framework\v4.0.30319\csc.exe'
}
if (-not (Test-Path -LiteralPath $compiler)) {
    throw 'Compiler .NET Framework tidak ditemukan di Windows ini.'
}

$testDirectory = Join-Path $PSScriptRoot '.test-bin'
$testExecutable = Join-Path $testDirectory 'SiksWaProtocolTests.exe'
New-Item -ItemType Directory -Force -Path $testDirectory | Out-Null

& $compiler /nologo /target:exe /optimize+ /debug- /warn:4 /out:$testExecutable `
    (Join-Path $PSScriptRoot 'SiksWaProtocol.cs') `
    (Join-Path $PSScriptRoot 'tests\SiksWaProtocolTests.cs')

if ($LASTEXITCODE -ne 0) {
    throw "Build unit test gagal (exit code $LASTEXITCODE)."
}

& $testExecutable
if ($LASTEXITCODE -ne 0) {
    throw "Unit test helper gagal (exit code $LASTEXITCODE)."
}
