<#
.SYNOPSIS
    Raise the plugin version.

.DESCRIPTION
    The version in the plugin header is the source of truth for releases:
    pushing a commit that carries a new version publishes it. This script bumps
    the two places that version lives, so they can never drift apart.

.PARAMETER Part
    patch (default), minor, major, or an explicit version like 2.1.0.

.EXAMPLE
    .\bump.ps1
    .\bump.ps1 minor
    .\bump.ps1 2.1.0
#>

[CmdletBinding()]
param(
    [string]$Part = 'patch'
)

$ErrorActionPreference = 'Stop'

$slug     = 'fyos-registration-form'
$file     = Join-Path $PSScriptRoot "$slug.php"

if (-not (Test-Path $file)) {
    throw "Main plugin file not found: $file"
}

$content = Get-Content $file -Raw

$match = [regex]::Match($content, '\*\s*Version:\s*(\d+)\.(\d+)\.(\d+)')
if (-not $match.Success) {
    throw 'Could not read the current version from the plugin header.'
}

$current = "$($match.Groups[1].Value).$($match.Groups[2].Value).$($match.Groups[3].Value)"

if ($Part -match '^\d+\.\d+\.\d+$') {
    $new = $Part
} else {
    $major = [int]$match.Groups[1].Value
    $minor = [int]$match.Groups[2].Value
    $patch = [int]$match.Groups[3].Value

    switch ($Part) {
        'major' { $new = "$($major + 1).0.0" }
        'minor' { $new = "$major.$($minor + 1).0" }
        'patch' { $new = "$major.$minor.$($patch + 1)" }
        default { throw 'Usage: .\bump.ps1 [patch|minor|major|X.Y.Z]' }
    }
}

# Preserve the whitespace alignment in the header.
$content = [regex]::Replace($content, '(\*\s*Version:\s+)\d+\.\d+\.\d+', "`${1}$new")
$content = [regex]::Replace($content, "define\( 'FRF_VERSION', '[^']*' \);", "define( 'FRF_VERSION', '$new' );")

# UTF-8 without BOM — a BOM before <?php would be sent to the browser.
[System.IO.File]::WriteAllText($file, $content, (New-Object System.Text.UTF8Encoding $false))

Write-Host "$current -> $new"
Select-String -Path $file -Pattern '\* Version:|FRF_VERSION' | ForEach-Object { $_.Line.Trim() }
Write-Host ''
Write-Host "Commit this with your change; the push publishes v$new."
