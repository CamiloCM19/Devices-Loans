$ErrorActionPreference = "Stop"

$root = Resolve-Path (Join-Path $PSScriptRoot "..")
$php = if ($env:PHP_BIN) { $env:PHP_BIN } else { "php" }
$hostAddress = if ($env:APP_HOST) { $env:APP_HOST } else { "0.0.0.0" }
$port = if ($env:APP_PORT) { $env:APP_PORT } else { "8000" }
$configuredUrl = if ($env:APP_URL) { $env:APP_URL.TrimEnd('/') } else { $null }

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

function Test-IsAdministrator {
    try {
        $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
        $principal = [Security.Principal.WindowsPrincipal]::new($identity)
        return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
    } catch {
        return $false
    }
}

function Ensure-FirewallRule {
    param(
        [string]$Port
    )

    $ruleName = "Control Camaras Laravel $Port"

    try {
        $existingRule = Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue
        if ($existingRule) {
            return $ruleName
        }
    } catch {
        return $null
    }

    if (-not (Test-IsAdministrator)) {
        Write-Host "[server] Warning: run PowerShell as administrator if another PC still cannot open port $Port."
        return $null
    }

    try {
        New-NetFirewallRule `
            -DisplayName $ruleName `
            -Direction Inbound `
            -Action Allow `
            -Protocol TCP `
            -LocalPort $Port `
            -Profile Private | Out-Null
        Write-Host "[server] Firewall rule created for inbound TCP $Port on private networks."
        return $ruleName
    } catch {
        Write-Host "[server] Warning: unable to create the Windows Firewall rule for port $Port."
        return $null
    }
}

$sharedUrl = $configuredUrl
if (Test-IsLoopbackUrl $configuredUrl) {
    $lanIp = Get-BestLanIPv4
    if ($lanIp) {
        $sharedUrl = "http://$lanIp`:$port"
        $env:APP_URL = $sharedUrl
    }
}

$firewallRule = Ensure-FirewallRule -Port $port

Write-Host "[server] Project root: $root"
Write-Host "[server] PHP: $php"
Write-Host "[server] Listening on: $hostAddress`:$port"
Write-Host "[server] Local inventory URL: http://localhost:$port/inventory"
if ($sharedUrl) {
    Write-Host "[server] Shared inventory URL: $sharedUrl/inventory"
}
if ($firewallRule) {
    Write-Host "[server] Firewall rule ready: $firewallRule"
}

Push-Location $root

try {
    & $php artisan serve --host=$hostAddress --port=$port
    exit $LASTEXITCODE
} finally {
    Pop-Location
}
