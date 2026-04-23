param(
    [ValidateSet("basic", "rfid", "serial")]
    [string]$Mode = "rfid",

    [switch]$DryRun,

    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$ComposerArgs = @()
)

$ErrorActionPreference = "Stop"

$root = Resolve-Path (Join-Path $PSScriptRoot "..")
$composer = if ($env:COMPOSER_BIN) { $env:COMPOSER_BIN } else { "composer" }
$hostAddress = "0.0.0.0"
$port = if ($env:APP_PORT) { $env:APP_PORT } else { "8000" }
$localUrl = "http://localhost:$port"
$inventoryUrl = "$localUrl/inventory"
$lanUrl = $null
$lanInventoryUrl = $null
$sharedInventoryUrl = $null

function Get-BestLanIPv4 {
    try {
        $route = Get-NetRoute -AddressFamily IPv4 -DestinationPrefix "0.0.0.0/0" |
            Sort-Object RouteMetric, InterfaceMetric |
            Select-Object -First 1

        if ($route) {
            $address = Get-NetIPAddress -AddressFamily IPv4 -InterfaceIndex $route.InterfaceIndex |
                Where-Object {
                    $_.IPAddress -notlike "127.*" -and
                    $_.IPAddress -notlike "169.254.*" -and
                    $_.PrefixOrigin -ne "WellKnown" -and
                    $_.AddressState -eq "Preferred"
                } |
                Select-Object -First 1 -ExpandProperty IPAddress

            if ($address) {
                return $address
            }
        }
    } catch {
    }

    try {
        return Get-NetIPAddress -AddressFamily IPv4 |
            Where-Object {
                $_.IPAddress -notlike '127.*' -and
                $_.IPAddress -notlike '169.254.*' -and
                $_.PrefixOrigin -ne 'WellKnown' -and
                $_.AddressState -eq 'Preferred'
            } |
            Select-Object -First 1 -ExpandProperty IPAddress
    } catch {
        return $null
    }
}

try {
    $ipv4 = Get-BestLanIPv4
    if ($ipv4) {
        $lanUrl = "http://$ipv4`:$port"
        $lanInventoryUrl = "$lanUrl/inventory"
    }
} catch {
}

if ($env:APP_URL) {
    $configuredUrl = $env:APP_URL.TrimEnd('/')

    try {
        $configuredHost = ([Uri]$configuredUrl).Host
    } catch {
        $configuredHost = $null
    }

    if ($configuredHost -and $configuredHost -notin @("localhost", "127.0.0.1", "::1")) {
        $sharedInventoryUrl = "$configuredUrl/inventory"
    } elseif ($lanInventoryUrl) {
        $sharedInventoryUrl = $lanInventoryUrl
    }
} elseif ($lanInventoryUrl) {
    $sharedInventoryUrl = $lanInventoryUrl
}

if ($sharedInventoryUrl) {
    $env:APP_URL = $sharedInventoryUrl.Replace('/inventory', '')
}

$scriptName = switch ($Mode) {
    "basic" { "dev:win" }
    "serial" { "dev:win:serial" }
    default { "dev:win:rfid" }
}

$commandArgs = @("run-script", $scriptName) + $ComposerArgs

Write-Host "[dev] Project root: $root"
Write-Host "[dev] Composer: $composer"
Write-Host "[dev] Mode: $Mode"
Write-Host "[dev] Script: $scriptName"
Write-Host "[dev] Server host: $hostAddress"
Write-Host "[dev] Server port: $port"
Write-Host "[dev] Open here: $inventoryUrl"
if ($lanInventoryUrl -and $lanInventoryUrl -ne $inventoryUrl) {
    Write-Host "[dev] Open from another device: $lanInventoryUrl"
}
if ($sharedInventoryUrl -and $sharedInventoryUrl -ne $inventoryUrl) {
    Write-Host "[dev] Shared app URL: $sharedInventoryUrl"
}
Write-Host "[dev] Command: $composer $($commandArgs -join ' ')"

try {
    Get-Command $composer -ErrorAction Stop | Out-Null
} catch {
    Write-Error "[dev] Composer was not found. Set COMPOSER_BIN if you use a custom path."
}

if ($DryRun) {
    Write-Host "[dev] Dry run complete. No processes were started."
    exit 0
}

Push-Location $root

try {
    & $composer @commandArgs
    exit $LASTEXITCODE
} finally {
    Pop-Location
}
