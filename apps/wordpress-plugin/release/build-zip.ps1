$ErrorActionPreference = "Stop"

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..\..")
$pluginRoot = Resolve-Path (Join-Path $PSScriptRoot "..")
$zipPath = Join-Path $PSScriptRoot "navai-voice.zip"

if (Test-Path $zipPath) {
  Remove-Item $zipPath -Force
}

$includePaths = @(
  "navai-voice.php",
  "README.es.md",
  "assets",
  "includes"
)

$fileStream = [System.IO.File]::Open($zipPath, [System.IO.FileMode]::CreateNew, [System.IO.FileAccess]::ReadWrite, [System.IO.FileShare]::None)
try {
  $archive = New-Object System.IO.Compression.ZipArchive($fileStream, [System.IO.Compression.ZipArchiveMode]::Create, $false)
  try {
    foreach ($relative in $includePaths) {
      $fullPath = Join-Path $pluginRoot $relative
      if (!(Test-Path $fullPath)) {
        throw "Missing expected plugin path: $relative"
      }

      if (Test-Path $fullPath -PathType Container) {
        Get-ChildItem -Path $fullPath -Recurse -File | ForEach-Object {
          $sourceFile = $_.FullName
          $internalRelative = $sourceFile.Substring($pluginRoot.Path.Length).TrimStart('\', '/')
          $entryName = "navai-voice/" + ($internalRelative -replace "\\", "/")
          [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $archive,
            $sourceFile,
            $entryName,
            [System.IO.Compression.CompressionLevel]::Optimal
          ) | Out-Null
        }
      } else {
        $entryName = "navai-voice/" + ($relative -replace "\\", "/")
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
          $archive,
          $fullPath,
          $entryName,
          [System.IO.Compression.CompressionLevel]::Optimal
        ) | Out-Null
      }
    }
  } finally {
    $archive.Dispose()
  }
} finally {
  $fileStream.Dispose()
}

Write-Output ("ZIP_READY: " + $zipPath)
