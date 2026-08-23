<#
.SYNOPSIS
    Builds a WordPress-installable zip of the FOYS Registration Form plugin.

.DESCRIPTION
    WordPress extracts an uploaded zip into wp-content/plugins/<zip-filename>/,
    so the zip must contain the plugin files at its ROOT — no subdirectory inside.
    The zip is named after the plugin slug (fyos-registration-form.zip) so
    WordPress installs it to the correct folder.

    Development artifacts (.git, .claude, this build script, the output zip,
    editor/OS junk, any stray debug files) are excluded.

.PARAMETER OutputDir
    Where to write the .zip. Defaults to the plugin directory.

.PARAMETER Slug
    The plugin slug, used as both the zip filename and the WordPress folder name.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File .\build-zip.ps1
#>

[CmdletBinding()]
param(
    [string]$OutputDir,
    [string]$Slug = 'fyos-registration-form'
)

$ErrorActionPreference = 'Stop'

# Resolve paths relative to this script so it can be run from anywhere.
$SourceDir = $PSScriptRoot
if ([string]::IsNullOrWhiteSpace($OutputDir)) { $OutputDir = $SourceDir }

# Pull the version out of the main plugin file header (for display only).
$mainFile = Join-Path $SourceDir 'fyos-registration-form.php'
if (-not (Test-Path $mainFile)) {
    throw "Main plugin file not found: $mainFile"
}
$version = 'dev'
$header  = Get-Content $mainFile -TotalCount 30
$match   = $header | Select-String -Pattern '^\s*\*\s*Version:\s*(.+?)\s*$'
if ($match) { $version = $match.Matches[0].Groups[1].Value.Trim() }

# Only these files/folders are part of the distributable plugin.
$include = @(
    'fyos-registration-form.php',
    'includes',
    'assets',
    'README.md'
)

# Stage files directly in a temp folder (NO slug subfolder).
# WordPress will use the zip filename as the plugin folder, so the files
# must sit at the root of the archive.
$stageDir = Join-Path ([System.IO.Path]::GetTempPath()) ("frf-build-" + [System.Guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $stageDir -Force | Out-Null

try {
    foreach ($item in $include) {
        $src = Join-Path $SourceDir $item
        if (-not (Test-Path $src)) {
            Write-Warning "Skipping missing item: $item"
            continue
        }
        Copy-Item -Path $src -Destination $stageDir -Recurse -Force
    }

    # Defensive cleanup: strip anything that should never ship.
    $junkPatterns = @('_debug_*', '_table.txt', '_visible.txt', '_matches.html',
                      '.DS_Store', 'Thumbs.db', '*.log')
    foreach ($pattern in $junkPatterns) {
        Get-ChildItem -Path $stageDir -Recurse -Force -Filter $pattern -ErrorAction SilentlyContinue |
            Remove-Item -Force -Recurse -ErrorAction SilentlyContinue
    }

    # Zip the CONTENTS of $stageDir (files at root, no subdirectory).
    # Use the slug as the filename so WP installs to the right folder.
    #
    # IMPORTANT: ZipFile::CreateFromDirectory uses the OS path separator (\) in
    # entry names on Windows. PHP's ZipArchive on Linux treats \ as a literal
    # character — not a directory separator — so files would land in the wrong
    # place. We use ZipArchive directly and replace all \ with / so the archive
    # conforms to the ZIP spec and extracts correctly on Linux.
    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem

    $zipName = "$Slug.zip"
    $zipPath = Join-Path $OutputDir $zipName
    if (Test-Path $zipPath) { Remove-Item $zipPath -Force }

    $stream  = [System.IO.File]::Open($zipPath, [System.IO.FileMode]::Create)
    $archive = New-Object System.IO.Compression.ZipArchive($stream, [System.IO.Compression.ZipArchiveMode]::Create)
    try {
        Get-ChildItem -Path $stageDir -Recurse -File | ForEach-Object {
            # Relative path from stage root, forward-slashed (ZIP spec requirement).
            $rel   = $_.FullName.Substring($stageDir.Length).TrimStart('\', '/') -replace '\\', '/'
            $entry = $archive.CreateEntry($rel, [System.IO.Compression.CompressionLevel]::Optimal)
            $es    = $entry.Open()
            $fs    = [System.IO.File]::OpenRead($_.FullName)
            try     { $fs.CopyTo($es) }
            finally { $fs.Dispose(); $es.Dispose() }
        }
    } finally {
        $archive.Dispose()
        $stream.Dispose()
    }

    Write-Host ""
    Write-Host "Plugin version : $version" -ForegroundColor Cyan
    Write-Host "Created        : $zipPath" -ForegroundColor Green
    Write-Host "WP installs to : wp-content/plugins/$Slug/" -ForegroundColor Green
    Write-Host ""
    Write-Host "Zip contents (files at root, no subdirectory):" -ForegroundColor Cyan
    $reader = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
    try {
        $reader.Entries | Sort-Object FullName | ForEach-Object {
            Write-Host ("  " + $_.FullName)
        }
    } finally {
        $reader.Dispose()
    }
    Write-Host ""
    Write-Host "Upload via WordPress: Plugins -> Add New -> Upload Plugin -> choose this zip." -ForegroundColor Yellow
}
finally {
    # Always clean up the staging area.
    if (Test-Path $stageDir) {
        Remove-Item $stageDir -Recurse -Force -ErrorAction SilentlyContinue
    }
}
