[CmdletBinding()]
param(
    [string]$PhpExe = 'C:\xampp2\php\php.exe'
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path $PhpExe)) {
    if (Get-Command php -ErrorAction SilentlyContinue) {
        $PhpExe = 'php'
    } else {
        throw "PHP CLI nao encontrado. Ajuste o caminho em -PhpExe."
    }
}

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $repoRoot

$phpFiles = Get-ChildItem -Recurse -File -Filter *.php | Where-Object {
    $_.FullName -notmatch '\\vendor\\'
}

$hasError = $false

foreach ($file in $phpFiles) {
    $bytes = [System.IO.File]::ReadAllBytes($file.FullName)
    if ($bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
        Write-Host "BOM detectado: $($file.FullName)" -ForegroundColor Red
        $hasError = $true
    }

    $output = & $PhpExe -l $file.FullName 2>&1
    if ($LASTEXITCODE -ne 0) {
        Write-Host "Erro de sintaxe: $($file.FullName)" -ForegroundColor Red
        Write-Host ($output -join "`n")
        $hasError = $true
    }
}

if ($hasError) {
    throw "Falha na validacao PHP."
}

Write-Host "Validacao PHP concluida sem erros." -ForegroundColor Green
