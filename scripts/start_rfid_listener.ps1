$ErrorActionPreference = "Continue"

$root = Resolve-Path (Join-Path $PSScriptRoot "..")
$php = if ($env:PHP_BIN) { $env:PHP_BIN } else { "php" }
$reader = if ($env:RFID_READER_DRIVER) { $env:RFID_READER_DRIVER } else { "mfrc522" }
$defaultSourcePrefix = if ($reader -eq "auto") { "nfc-reader" } else { $reader }
$device = if ($env:RFID_SERIAL_DEVICE) { $env:RFID_SERIAL_DEVICE } else { "auto" }
$baud = if ($env:RFID_SERIAL_BAUD) { $env:RFID_SERIAL_BAUD } else { "115200" }
$source = if ($env:RFID_READER_SOURCE) { $env:RFID_READER_SOURCE } else { "$defaultSourcePrefix-$env:COMPUTERNAME" }
$dedupeMs = if ($env:RFID_SERIAL_DEDUPE_MS) { $env:RFID_SERIAL_DEDUPE_MS } else { "1200" }
$frameIdleMs = if ($env:RFID_SERIAL_FRAME_IDLE_MS) { $env:RFID_SERIAL_FRAME_IDLE_MS } else { "150" }
$reconnectDelay = if ($env:RFID_SERIAL_RECONNECT_DELAY) { $env:RFID_SERIAL_RECONNECT_DELAY } else { "2" }
$appPort = if ($env:APP_PORT) { $env:APP_PORT } else { "8000" }
$sharedUrl = if ($env:APP_URL) { $env:APP_URL } else { $null }
$remoteApiUrl = if ($env:RFID_REMOTE_API_URL) { $env:RFID_REMOTE_API_URL } else { $null }

function Test-IsLoopbackUrl {
    param(
        [string]$Url
    )

    if (-not $Url) {
        return $true
    }

    try {
        $uri = [Uri]$Url
        return $uri.Host -in @("localhost", "127.0.0.1", "::1", "0.0.0.0")
    } catch {
        return $true
    }
}

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
                $_.IPAddress -notlike "127.*" -and
                $_.IPAddress -notlike "169.254.*" -and
                $_.PrefixOrigin -ne "WellKnown" -and
                $_.AddressState -eq "Preferred"
            } |
            Select-Object -First 1 -ExpandProperty IPAddress
    } catch {
        return $null
    }
}

if (Test-IsLoopbackUrl $sharedUrl) {
    try {
        $ipv4 = Get-BestLanIPv4
        if ($ipv4) {
            $sharedUrl = "http://$ipv4`:$appPort"
        }
    } catch {
    }
}

if ($sharedUrl) {
    $env:APP_URL = $sharedUrl
}

Write-Host "[rfid] NFC/RFID serial listener started"
Write-Host "[rfid] Project root: $root"
Write-Host "[rfid] PHP: $php"
Write-Host "[rfid] Reader profile: $reader"
Write-Host "[rfid] Device: $device"
Write-Host "[rfid] Baud: $baud"
Write-Host "[rfid] Source: $source"
if ($sharedUrl) {
    Write-Host "[rfid] Shared inventory URL: $sharedUrl/inventory"
}
if ($remoteApiUrl) {
    Write-Host "[rfid] Remote server target: $remoteApiUrl"
}

try {
    Get-Command $php -ErrorAction Stop | Out-Null
} catch {
    Write-Host "[rfid] ERROR: PHP CLI not found: $php"
    while ($true) { Start-Sleep -Seconds 30 }
}

while ($true) {
    $listenerArgs = @(
        "artisan",
        "rfid:listen-serial",
        "--reader=$reader",
        "--device=$device",
        "--baud=$baud",
        "--source=$source",
        "--dedupe-ms=$dedupeMs",
        "--frame-idle-ms=$frameIdleMs",
        "--reconnect-delay=$reconnectDelay"
    )

    if ($env:RFID_SERIAL_DEBUG -eq "1") {
        $listenerArgs += @("--debug")
    }

    Write-Host "[rfid] Launching listener..."
    & $php @listenerArgs
    $code = $LASTEXITCODE
    Write-Host "[rfid] Listener exited with code $code. Restarting in 2 seconds..."
    Start-Sleep -Seconds 2
}
