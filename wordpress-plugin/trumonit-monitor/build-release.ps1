param(
    [string]$OutputZip = ""
)

$ErrorActionPreference = "Stop"

$pluginDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$rootDir = Split-Path -Parent $pluginDir

if ([string]::IsNullOrWhiteSpace($OutputZip)) {
    $stamp = Get-Date -Format "yyyyMMdd-HHmmss"
    $OutputZip = Join-Path $rootDir ("trumonit-monitor-release-" + $stamp + ".zip")
}

$stageRoot = Join-Path $env:TEMP ("trumonit-monitor-release-stage-" + [guid]::NewGuid().ToString("N"))
$stagePlugin = Join-Path $stageRoot "trumonit-monitor"

New-Item -ItemType Directory -Path $stagePlugin -Force | Out-Null

$includeItems = @(
    "trumonit-monitor.php",
    "readme.txt",
    "includes",
    "languages",
    "assets"
)

foreach ($item in $includeItems) {
    $source = Join-Path $pluginDir $item
    if (Test-Path $source) {
        Copy-Item -Path $source -Destination $stagePlugin -Recurse -Force
    }
}

if (Test-Path $OutputZip) {
    Remove-Item $OutputZip -Force
}

Compress-Archive -Path (Join-Path $stageRoot "*") -DestinationPath $OutputZip -CompressionLevel Optimal
Remove-Item $stageRoot -Recurse -Force

Write-Output ("Release package: " + $OutputZip)
