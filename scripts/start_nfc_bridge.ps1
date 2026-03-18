$ErrorActionPreference = "Continue"

$root = Resolve-Path (Join-Path $PSScriptRoot "..")
$python = Join-Path $root ".venv\Scripts\python.exe"
$bridge = Join-Path $root "scripts\nfc_bridge.py"
$mode = if ($env:RFID_BRIDGE_MODE) { $env:RFID_BRIDGE_MODE } else { "api" }
$apiUrl = if ($env:RFID_BRIDGE_API_URL) { $env:RFID_BRIDGE_API_URL } else { "http://127.0.0.1:8000/inventory/scan/esp" }
$source = if ($env:RFID_BRIDGE_SOURCE) { $env:RFID_BRIDGE_SOURCE } else { "usb-bridge-$env:COMPUTERNAME" }

Write-Host "[rfid] NFC bridge launcher started"
Write-Host "[rfid] Project root: $root"
Write-Host "[rfid] Mode: $mode"
if ($mode -eq "api") {
    Write-Host "[rfid] API URL: $apiUrl"
}
Write-Host "[rfid] Source: $source"

if (!(Test-Path $bridge)) {
    Write-Host "[rfid] ERROR: Bridge script not found: $bridge"
    while ($true) { Start-Sleep -Seconds 30 }
}

if (!(Test-Path $python)) {
    Write-Host "[rfid] ERROR: Python venv not found: $python"
    Write-Host "[rfid] Run: py -3 -m venv .venv && .\.venv\Scripts\python.exe -m pip install pyserial"
    Write-Host "[rfid] Optional for keyboard mode: .\.venv\Scripts\python.exe -m pip install pyautogui"
    while ($true) { Start-Sleep -Seconds 30 }
}

while ($true) {
    $bridgeArgs = @("-u", $bridge, "--mode", $mode, "--source", $source)
    if ($mode -eq "api") {
        $bridgeArgs += @("--api-url", $apiUrl)
    }
    if ($env:RFID_ESP_TOKEN) {
        $bridgeArgs += @("--token", $env:RFID_ESP_TOKEN)
    }
    if ($env:RFID_BRIDGE_PORT) {
        $bridgeArgs += @("--port", $env:RFID_BRIDGE_PORT)
    }
    if ($env:RFID_BRIDGE_BAUD) {
        $bridgeArgs += @("--baud", $env:RFID_BRIDGE_BAUD)
    }
    if ($env:RFID_BRIDGE_INCLUDE_BLUETOOTH -eq "1") {
        $bridgeArgs += @("--include-bluetooth")
    }
    if ($env:RFID_BRIDGE_DEBUG -eq "1") {
        $bridgeArgs += @("--debug")
    }

    Write-Host "[rfid] Launching bridge..."
    & $python @bridgeArgs
    $code = $LASTEXITCODE
    Write-Host "[rfid] Bridge exited with code $code. Restarting in 2 seconds..."
    Start-Sleep -Seconds 2
}
