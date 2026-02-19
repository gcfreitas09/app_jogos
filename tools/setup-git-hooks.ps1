[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $repoRoot

git config core.hooksPath .githooks

Write-Host "core.hooksPath configurado para .githooks"
Write-Host "Pre-commit ativo: valida sintaxe PHP e bloqueia BOM UTF-8."
