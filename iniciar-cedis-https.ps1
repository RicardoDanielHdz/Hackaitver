$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$ProjectDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $ProjectDir

Write-Host '======================================================================' -ForegroundColor DarkGray
Write-Host '  CEDIS CDMX - ARRANQUE AUTOMATICO (SERVIDOR + TUNEL HTTPS MOVIL)' -ForegroundColor White
Write-Host '======================================================================' -ForegroundColor DarkGray
Write-Host ''

function Resolve-PhpBinary {
    $cmd = Get-Command php -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }

    $wingetPhp = Get-ChildItem -Path "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP*" -Filter 'php.exe' -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($wingetPhp) { return $wingetPhp.FullName }

    return $null
}

function Resolve-CloudflaredBinary {
    $cmd = Get-Command cloudflared -ErrorAction SilentlyContinue
    if ($cmd) { return @{ Type = 'exe'; Path = $cmd.Source } }

    $candidates = @(
        "$env:ProgramFiles\cloudflared\cloudflared.exe",
        "${env:ProgramFiles(x86)}\cloudflared\cloudflared.exe",
        "$env:LOCALAPPDATA\Programs\cloudflared\cloudflared.exe"
    )
    foreach ($candidate in $candidates) {
        if (Test-Path $candidate) {
            return @{ Type = 'exe'; Path = $candidate }
        }
    }

    $npx = Get-Command npx.cmd -ErrorAction SilentlyContinue
    if (-not $npx) { $npx = Get-Command npx -ErrorAction SilentlyContinue }
    if ($npx) { return @{ Type = 'npx'; Path = $npx.Source } }

    return $null
}

function Test-PortInUse {
    param([int]$Port)
    try {
        $client = New-Object System.Net.Sockets.TcpClient
        $iar = $client.BeginConnect('127.0.0.1', $Port, $null, $null)
        $connected = $iar.AsyncWaitHandle.WaitOne(250, $false)
        if ($connected) { $client.EndConnect($iar) }
        $client.Close()
        return $connected
    } catch {
        return $false
    }
}

$phpBin = Resolve-PhpBinary
if (-not $phpBin) {
    Write-Host '[ERROR] No se encontro PHP en este equipo.' -ForegroundColor Red
    exit 1
}

$cfBin = Resolve-CloudflaredBinary
if (-not $cfBin) {
    Write-Host '[ERROR] No se encontro cloudflared ni Node.js (npx) para abrir el tunel HTTPS.' -ForegroundColor Red
    exit 1
}

# Preparacion automatica si es la primera vez en otra PC
if (-not (Test-Path (Join-Path $ProjectDir '.env')) -and (Test-Path (Join-Path $ProjectDir '.env.example'))) {
    Write-Host '[1/4] Creando archivo .env y llave de aplicacion...' -ForegroundColor Yellow
    Copy-Item (Join-Path $ProjectDir '.env.example') (Join-Path $ProjectDir '.env')
    & $phpBin artisan key:generate --force | Out-Null
}

$sqliteFile = Join-Path $ProjectDir 'database\database.sqlite'
if (-not (Test-Path $sqliteFile)) {
    Write-Host '[1/4] Inicializando base de datos SQLite y datos base...' -ForegroundColor Yellow
    New-Item -ItemType File -Path $sqliteFile -Force | Out-Null
    & $phpBin artisan migrate --force
    & $phpBin artisan db:seed --force
}

# Verificar si ya hay un servidor Laravel en 8000 o elegir un puerto libre
$port = 8000
$phpProcess = $null
$tunnelProcess = $null
$startedLocalServer = $false

$alreadyUp = $false
try {
    $probe = Invoke-WebRequest -Uri "http://127.0.0.1:$port/up" -UseBasicParsing -TimeoutSec 2
    if ($probe.StatusCode -eq 200) { $alreadyUp = $true }
} catch {
    $alreadyUp = $false
}

if (-not $alreadyUp) {
    while (Test-PortInUse -Port $port) {
        $port++
        if ($port -gt 8020) {
            Write-Host '[ERROR] No hay puertos libres entre 8000 y 8020.' -ForegroundColor Red
            exit 1
        }
    }
}

$logDir = Join-Path $env:TEMP 'cedis-cdmx-launcher'
New-Item -ItemType Directory -Path $logDir -Force | Out-Null
$phpOutLog = Join-Path $logDir "php-$port.out.log"
$phpErrLog = Join-Path $logDir "php-$port.err.log"
$cfOutLog = Join-Path $logDir "cloudflared-$port.out.log"
$cfErrLog = Join-Path $logDir "cloudflared-$port.err.log"
Remove-Item $phpOutLog, $phpErrLog, $cfOutLog, $cfErrLog -ErrorAction SilentlyContinue

try {
    if ($alreadyUp) {
        Write-Host "[2/4] Servidor Laravel ya activo en http://127.0.0.1:$port" -ForegroundColor Green
    } else {
        Write-Host "[2/4] Iniciando servidor Laravel en http://127.0.0.1:$port ..." -ForegroundColor Cyan
        $phpProcess = Start-Process -FilePath $phpBin `
            -ArgumentList @('artisan', 'serve', '--host=127.0.0.1', "--port=$port") `
            -WorkingDirectory $ProjectDir `
            -WindowStyle Hidden `
            -RedirectStandardOutput $phpOutLog `
            -RedirectStandardError $phpErrLog `
            -PassThru
        $startedLocalServer = $true

        $serverReady = $false
        for ($i = 0; $i -lt 30; $i++) {
            Start-Sleep -Milliseconds 500
            if ($phpProcess.HasExited) { break }
            try {
                $r = Invoke-WebRequest -Uri "http://127.0.0.1:$port/up" -UseBasicParsing -TimeoutSec 2
                if ($r.StatusCode -eq 200) {
                    $serverReady = $true
                    break
                }
            } catch {}
        }

        if (-not $serverReady) {
            Write-Host '[ERROR] El servidor Laravel no respondio a tiempo.' -ForegroundColor Red
            if (Test-Path $phpErrLog) { Get-Content $phpErrLog }
            exit 1
        }
        Write-Host "[2/4] Servidor local listo (200 OK)." -ForegroundColor Green
    }

    Write-Host '[3/4] Abriendo tunel HTTPS publico (Cloudflare)...' -ForegroundColor Cyan
    if ($cfBin.Type -eq 'exe') {
        $tunnelProcess = Start-Process -FilePath $cfBin.Path `
            -ArgumentList @('tunnel', '--url', "http://127.0.0.1:$port") `
            -WorkingDirectory $ProjectDir `
            -WindowStyle Hidden `
            -RedirectStandardOutput $cfOutLog `
            -RedirectStandardError $cfErrLog `
            -PassThru
    } else {
        $tunnelProcess = Start-Process -FilePath $cfBin.Path `
            -ArgumentList @('--yes', 'cloudflared', 'tunnel', '--url', "http://127.0.0.1:$port") `
            -WorkingDirectory $ProjectDir `
            -WindowStyle Hidden `
            -RedirectStandardOutput $cfOutLog `
            -RedirectStandardError $cfErrLog `
            -PassThru
    }

    $publicUrl = $null
    for ($i = 0; $i -lt 60; $i++) {
        Start-Sleep -Milliseconds 500
        if ($tunnelProcess.HasExited) { break }
        foreach ($logFile in @($cfErrLog, $cfOutLog)) {
            if (Test-Path $logFile) {
                $content = Get-Content $logFile -Raw -ErrorAction SilentlyContinue
                if ($content -match 'https://[a-z0-9-]+\.trycloudflare\.com') {
                    $publicUrl = $matches[0]
                    break
                }
            }
        }
        if ($publicUrl) { break }
    }

    if (-not $publicUrl) {
        Write-Host '[ERROR] No se pudo obtener la URL HTTPS del tunel Cloudflare.' -ForegroundColor Red
        if (Test-Path $cfErrLog) { Get-Content $cfErrLog }
        exit 1
    }

    Write-Host '[4/4] Verificando acceso HTTPS de extremo a extremo...' -ForegroundColor Cyan
    $httpsReady = $false
    for ($i = 0; $i -lt 15; $i++) {
        try {
            $respHome = Invoke-WebRequest -Uri "$publicUrl/" -UseBasicParsing -TimeoutSec 4
            $respUp = Invoke-WebRequest -Uri "$publicUrl/up" -UseBasicParsing -TimeoutSec 4
            if ($respHome.StatusCode -eq 200 -and $respUp.StatusCode -eq 200) {
                $httpsReady = $true
                break
            }
        } catch {}
        Start-Sleep -Seconds 1
    }

    try { Set-Clipboard -Value $publicUrl } catch {}

    Write-Host ''
    Write-Host '======================================================================' -ForegroundColor DarkGreen
    Write-Host '  LISTO PARA USAR EN COMPUTADORA Y CELULAR (CAMARA QR ACTIVA)' -ForegroundColor Green
    Write-Host '======================================================================' -ForegroundColor DarkGreen
    Write-Host ("  URL Local (PC):     http://127.0.0.1:{0}" -f $port) -ForegroundColor White
    Write-Host ("  URL HTTPS (Movil):  {0}" -f $publicUrl) -ForegroundColor Yellow
    if ($httpsReady) {
        Write-Host '  Estado HTTPS:       200 OK (/ y /up verificados)' -ForegroundColor Green
    } else {
        Write-Host '  Estado HTTPS:       Tunel creado (puede tardar unos segundos en propagar DNS)' -ForegroundColor Yellow
    }
    Write-Host '  (La URL HTTPS ya se copio automaticamente al portapapeles)' -ForegroundColor Gray
    Write-Host ''
    Write-Host '  Cuentas de prueba:' -ForegroundColor Cyan
    Write-Host '   - Gerente:   gerente@cedis.com   / Gerente#2026' -ForegroundColor Gray
    Write-Host '   - Embarques: embarque@cedis.com  / Embarque#2026' -ForegroundColor Gray
    Write-Host '   - Operador:  operador@cedis.com  / Operador#2026' -ForegroundColor Gray
    Write-Host '   - Proveedor: proveedor@cedis.com / Proveedor#2026' -ForegroundColor Gray
    Write-Host '   - Sucursal:  sucursal@cedis.com  / Sucursal#2026' -ForegroundColor Gray
    Write-Host ''
    Write-Host '  NO CIERRES ESTA VENTANA mientras uses la pagina.' -ForegroundColor Red
    Write-Host '  Presiona Ctrl + C para apagar el servidor y el tunel.' -ForegroundColor DarkGray
    Write-Host '======================================================================' -ForegroundColor DarkGreen

    while ($true) {
        Start-Sleep -Seconds 2
        if ($tunnelProcess -and $tunnelProcess.HasExited) {
            Write-Host '[AVISO] El tunel de Cloudflare se detuvo.' -ForegroundColor Yellow
            break
        }
        if ($startedLocalServer -and $phpProcess -and $phpProcess.HasExited) {
            Write-Host '[AVISO] El servidor Laravel se detuvo.' -ForegroundColor Yellow
            break
        }
    }
} finally {
    Write-Host ''
    Write-Host 'Deteniendo servicios...' -ForegroundColor Yellow
    if ($tunnelProcess -and -not $tunnelProcess.HasExited) {
        try { Stop-Process -Id $tunnelProcess.Id -Force -ErrorAction SilentlyContinue } catch {}
    }
    if ($startedLocalServer -and $phpProcess -and -not $phpProcess.HasExited) {
        try { Stop-Process -Id $phpProcess.Id -Force -ErrorAction SilentlyContinue } catch {}
    }
}
