# =============================================================================
# SRI Agent — Instalador para Windows (PowerShell)
# =============================================================================
# Instala el agente local de Declarame que descarga comprobantes del SRI
# usando un navegador real en tu computadora.
#
# Uso (abrir PowerShell y ejecutar):
#   Set-ExecutionPolicy Bypass -Scope Process -Force
#   iwr https://declarame.facec.ec/agent/install.ps1 -UseBasicParsing | iex
#
# Con dominio personalizado:
#   $env:AGENT_URL = "https://mi-dominio.com/agent"
#   iwr https://declarame.facec.ec/agent/install.ps1 -UseBasicParsing | iex
#
# Para actualizar: mismos comandos de arriba.
# =============================================================================

$ErrorActionPreference = "Stop"

# Captura errores no manejados — sin esto la ventana se cierra antes de que el usuario lea el error
trap {
    Write-Host "`n[sri-agent] ERROR inesperado: $_`n" -ForegroundColor Red
    Write-Host "Presiona Enter para cerrar..." -ForegroundColor Yellow
    $null = Read-Host
    exit 1
}

# UTF-8 en consola para mostrar caracteres especiales correctamente
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$OutputEncoding = [System.Text.Encoding]::UTF8

# ─── Configuracion ────────────────────────────────────────────────────────────

$AgentUrl  = if ($env:AGENT_URL) { $env:AGENT_URL.TrimEnd('/') } else { "https://declarame.facec.ec/agent" }
$InstallDir = "$env:USERPROFILE\.sri-agent"
$Port      = 8765
$TaskName  = "SRI-Agent-Declarame"

# ─── Helpers ──────────────────────────────────────────────────────────────────

function Step($msg)    { Write-Host "[sri-agent] $msg" -ForegroundColor Green }
function Warn($msg)    { Write-Host "[sri-agent] AVISO: $msg" -ForegroundColor Yellow }
function Fail($msg) {
    Write-Host "`n[sri-agent] ERROR: $msg`n" -ForegroundColor Red
    Write-Host "Presiona Enter para cerrar..." -ForegroundColor Yellow
    $null = Read-Host
    exit 1
}

Write-Host ""
Write-Host " SRI Agent - Instalador para Windows " -ForegroundColor White -BackgroundColor DarkBlue
Write-Host ""
Step "URL del agente : $AgentUrl"
Step "Directorio     : $InstallDir"
Step "Puerto         : $Port"
Write-Host ""

# ─── Python 3.9+ ─────────────────────────────────────────────────────────────

Step "Buscando Python 3.9+..."

$Python = $null
foreach ($candidate in @("python", "py", "python3")) {
    try {
        $verLine = & $candidate --version 2>&1
        if ($verLine -match "Python (\d+)\.(\d+)") {
            $maj = [int]$Matches[1]; $min = [int]$Matches[2]
            if ($maj -ge 3 -and $min -ge 9) {
                $Python = $candidate
                Step "Python encontrado: $verLine"
                break
            }
        }
    } catch { }
}

if (-not $Python) {
    # Intento 1: winget
    $wingetOk = $false
    if (Get-Command winget -ErrorAction SilentlyContinue) {
        Step "Python no encontrado. Intentando instalar via winget..."
        try {
            winget install --id Python.Python.3.12 --silent --accept-package-agreements --accept-source-agreements 2>&1 | Out-Null
            $env:PATH = [System.Environment]::GetEnvironmentVariable("PATH","Machine") + ";" +
                        [System.Environment]::GetEnvironmentVariable("PATH","User")
            # Verificar que realmente quedo instalado
            $verLine = & python --version 2>&1
            if ($verLine -match "Python (\d+)\.(\d+)" -and [int]$Matches[1] -ge 3 -and [int]$Matches[2] -ge 9) {
                $Python = "python"
                $wingetOk = $true
                Step "Python instalado via winget: $verLine"
            }
        } catch { }
    }

    # Intento 2: descargar instalador directamente desde python.org
    if (-not $wingetOk) {
        Step "Descargando Python 3.12 desde python.org (puede tardar unos minutos)..."
        $PyVersion  = "3.12.7"
        $PyUrl      = "https://www.python.org/ftp/python/$PyVersion/python-$PyVersion-amd64.exe"
        $PyInstaller = "$env:TEMP\python-sri-agent.exe"
        try {
            [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12
            Invoke-WebRequest $PyUrl -OutFile $PyInstaller -UseBasicParsing
            Step "Instalando Python $PyVersion (puede requerir permisos de administrador)..."
            $proc = Start-Process -FilePath $PyInstaller `
                -ArgumentList "/quiet InstallAllUsers=0 PrependPath=1 Include_pip=1 SimpleInstall=1" `
                -Wait -PassThru
            Remove-Item $PyInstaller -ErrorAction SilentlyContinue
            if ($proc.ExitCode -ne 0) { throw "Instalador salio con codigo $($proc.ExitCode)" }
            # Recargar PATH
            $env:PATH = [System.Environment]::GetEnvironmentVariable("PATH","Machine") + ";" +
                        [System.Environment]::GetEnvironmentVariable("PATH","User")
            $Python = "python"
            Step "Python $PyVersion instalado correctamente."
        } catch {
            Remove-Item $PyInstaller -ErrorAction SilentlyContinue
            Fail ("No se pudo instalar Python automaticamente.`n" +
                  "Instala manualmente desde: https://www.python.org/downloads/`n" +
                  "IMPORTANTE: marca la casilla 'Add Python to PATH' durante la instalacion.`n" +
                  "Luego vuelve a ejecutar este instalador.")
        }
    }
}

# ─── Directorios ──────────────────────────────────────────────────────────────

Step "Preparando directorio $InstallDir..."
New-Item -ItemType Directory -Force -Path $InstallDir         | Out-Null
New-Item -ItemType Directory -Force -Path "$InstallDir\browser-session" | Out-Null

# ─── Descargar scripts ────────────────────────────────────────────────────────

Step "Descargando scripts desde $AgentUrl..."
[System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12

Invoke-WebRequest "$AgentUrl/server.py"       -OutFile "$InstallDir\server.py"       -UseBasicParsing
Invoke-WebRequest "$AgentUrl/test-scraper.py" -OutFile "$InstallDir\test-scraper.py" -UseBasicParsing
Step "Scripts descargados."

# ─── Entorno virtual Python ───────────────────────────────────────────────────

if (-not (Test-Path "$InstallDir\venv")) {
    Step "Creando entorno virtual Python..."
    & $Python -m venv "$InstallDir\venv"
}

$VenvPy         = "$InstallDir\venv\Scripts\python.exe"
$VenvPip        = "$InstallDir\venv\Scripts\pip.exe"
$VenvPlaywright = "$InstallDir\venv\Scripts\playwright.exe"

Step "Actualizando pip..."
# pip escribe a stderr incluso en exito; try-catch evita que StopOnError lo trate como fallo
try { & $VenvPip install --quiet --upgrade pip 2>&1 | Out-Null } catch { }

Step "Instalando dependencias Python (playwright, playwright-stealth)..."
& $VenvPip install --upgrade playwright playwright-stealth

Step "Instalando Chromium para Playwright (puede tardar unos minutos)..."
# NODE_TLS_REJECT_UNAUTHORIZED=0 evita errores de certificado en equipos con
# reloj desincronizado o proxies corporativos. Solo aplica a esta descarga.
$env:NODE_TLS_REJECT_UNAUTHORIZED = "0"
& $VenvPlaywright install --force chromium
$playwrightExit = $LASTEXITCODE
Remove-Item env:NODE_TLS_REJECT_UNAUTHORIZED -ErrorAction SilentlyContinue
if ($playwrightExit -ne 0) {
    Fail ("No se pudo instalar Chromium.`n" +
          "Verifica: 1) conexion a internet  2) fecha y hora del sistema sean correctas`n" +
          "Luego vuelve a ejecutar este instalador.")
}

# ─── Script lanzador (para Task Scheduler) ────────────────────────────────────
# Task Scheduler no redirige stdout/stderr; usamos un wrapper .ps1 que
# captura la salida en agent.log mientras Chromium corre visible.

$LauncherPath = "$InstallDir\start-agent.ps1"

@"
# Auto-generado por install.ps1 — no editar manualmente
Remove-Item "$InstallDir\browser-session\Singleton*" -ErrorAction SilentlyContinue
& "$VenvPy" "$InstallDir\server.py" --host=127.0.0.1 --port=$Port --update-url=$AgentUrl --user-data-dir="$InstallDir\browser-session" *>> "$InstallDir\agent.log"
"@ | Set-Content -Path $LauncherPath -Encoding UTF8

Step "Script lanzador creado: $LauncherPath"

# ─── Task Scheduler (auto-inicio sin necesidad de admin) ──────────────────────

Step "Registrando tarea en Task Scheduler ($TaskName)..."

$Action = New-ScheduledTaskAction `
    -Execute "powershell.exe" `
    -Argument "-ExecutionPolicy Bypass -WindowStyle Hidden -File `"$LauncherPath`"" `
    -WorkingDirectory $InstallDir

$Trigger = New-ScheduledTaskTrigger -AtLogOn -User $env:USERNAME

$Settings = New-ScheduledTaskSettingsSet `
    -ExecutionTimeLimit ([TimeSpan]::Zero) `
    -RestartCount 3 `
    -RestartInterval (New-TimeSpan -Minutes 2) `
    -MultipleInstances IgnoreNew `
    -StartWhenAvailable

$Principal = New-ScheduledTaskPrincipal `
    -UserId $env:USERNAME `
    -LogonType Interactive `
    -RunLevel Limited

# Eliminar tarea anterior si existe
Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false -ErrorAction SilentlyContinue

Register-ScheduledTask `
    -TaskName  $TaskName `
    -Action    $Action `
    -Trigger   $Trigger `
    -Settings  $Settings `
    -Principal $Principal `
    -Description "SRI Agent para Declarame — descarga comprobantes del SRI" | Out-Null

Step "Tarea registrada. Arranca automaticamente al iniciar sesion en Windows."

# ─── Iniciar agente ahora mismo ───────────────────────────────────────────────

Step "Iniciando agente..."
Remove-Item "$InstallDir\browser-session\Singleton*" -ErrorAction SilentlyContinue

# Start-ScheduledTask no dispara de inmediato en todos los entornos Windows;
# lanzamos el proceso directamente y dejamos la tarea para el auto-inicio en login.
Start-Process powershell.exe -ArgumentList "-ExecutionPolicy Bypass -WindowStyle Hidden -File `"$LauncherPath`"" -WorkingDirectory $InstallDir

# ─── Health check ─────────────────────────────────────────────────────────────

Step "Esperando que el agente arranque (hasta 45 s)..."
$Started = $false
$Version = "?"

for ($i = 0; $i -lt 15; $i++) {
    Start-Sleep -Seconds 3
    try {
        $health  = Invoke-RestMethod "http://127.0.0.1:$Port/health" -ErrorAction Stop
        $Started = $true
        $Version = $health.version
        break
    } catch { }
}

Write-Host ""
if ($Started) {
    Write-Host "  ✓ SRI Agent instalado y corriendo." -ForegroundColor Green
    Write-Host "    Version : $Version"
    Write-Host "    Puerto  : $Port"
    Write-Host "    Logs    : $InstallDir\agent.log"
} else {
    Warn "El agente no respondio en 45 s. Revisa los logs:"
    Write-Host "    $InstallDir\agent.log"
}

Write-Host ""
Write-Host "  Para actualizar en el futuro:" -ForegroundColor Cyan
Write-Host "    Set-ExecutionPolicy Bypass -Scope Process -Force"
Write-Host "    iwr $AgentUrl/install.ps1 -UseBasicParsing | iex"
Write-Host ""
Write-Host "Presiona Enter para cerrar..." -ForegroundColor Yellow
$null = Read-Host
