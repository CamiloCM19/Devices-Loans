$ErrorActionPreference = "Continue"

$root = Resolve-Path (Join-Path $PSScriptRoot "..")
$python = Join-Path $root ".venv\Scripts\python.exe"
$bridge = Join-Path $root "scripts\nfc_bridge.py"

Write-Host "[rfid] NFC bridge launcher started"
Write-Host "[rfid] Project root: $root"

if (!(Test-Path $bridge)) {
    Write-Host "[rfid] ERROR: Bridge script not found: $bridge"
    while ($true) { Start-Sleep -Seconds 30 }
}

if (!(Test-Path $python)) {
    Write-Host "[rfid] ERROR: Python venv not found: $python"
    Write-Host "[rfid] Run: py -3 -m venv .venv && .\.venv\Scripts\python.exe -m pip install pyserial pyautogui"
    while ($true) { Start-Sleep -Seconds 30 }
}

while ($true) {
    Write-Host "[rfid] Launching bridge..."
    & $python "-u" $bridge
    $code = $LASTEXITCODE
    Write-Host "[rfid] Bridge exited with code $code. Restarting in 2 seconds..."
    Start-Sleep -Seconds 2
}
