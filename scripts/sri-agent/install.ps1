# =============================================================================
# SRI Agent — Instalador para Windows (PowerShell)
# =============================================================================
# Instala el agente local de Declarame que descarga comprobantes del SRI
# usando un navegador real en tu computadora.
#
# Uso (abrir PowerShell y ejecutar):
#   Set-ExecutionPolicy Bypass -Scope Process -Force
#   iwr https://declarame.facec.ec/agent/install.ps1 | iex
#
# Con dominio personalizado:
#   $env:AGENT_URL = "https://mi-dominio.com/agent"
#   iwr https://declarame.facec.ec/agent/install.ps1 | iex
#
# Para actualizar: mismos comandos de arriba.
# =============================================================================

$ErrorActionPreference = "Stop"

# ─── Configuracion ────────────────────────────────────────────────────────────

$AgentUrl  = if ($env:AGENT_URL) { $env:AGENT_URL.TrimEnd('/') } else { "https://declarame.facec.ec/agent" }
$InstallDir = "$env:USERPROFILE\.sri-agent"
$Port      = 8765
$TaskName  = "SRI-Agent-Declarame"

# ─── Helpers ──────────────────────────────────────────────────────────────────

function Step($msg)    { Write-Host "[sri-agent] $msg" -ForegroundColor Green }
function Warn($msg)    { Write-Host "[sri-agent] AVISO: $msg" -ForegroundColor Yellow }
function Fail($msg)    { Write-Host "`n[sri-agent] ERROR: $msg`n" -ForegroundColor Red; exit 1 }

Write-Host ""
Write-Host " SRI Agent — Instalador para Windows " -ForegroundColor White -BackgroundColor DarkBlue
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
    Step "Python no encontrado. Intentando instalar via winget..."
    try {
        winget install --id Python.Python.3.12 --silent --accept-package-agreements --accept-source-agreements | Out-Null
        # Recargar PATH de la sesion actual
        $env:PATH = [System.Environment]::GetEnvironmentVariable("PATH","Machine") + ";" +
                    [System.Environment]::GetEnvironmentVariable("PATH","User")
        $Python = "python"
        Step "Python instalado via winget."
    } catch {
        Fail ("Python 3.9+ no encontrado y no se pudo instalar automaticamente.`n" +
              "Descargalo desde: https://www.python.org/downloads/`n" +
              "Marca la casilla 'Add Python to PATH' durante la instalacion.")
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
& $VenvPip install --quiet --upgrade pip 2>&1 | Out-Null

Step "Instalando dependencias Python (playwright, playwright-stealth)..."
& $VenvPip install --quiet --upgrade playwright playwright-stealth

Step "Instalando Chromium para Playwright (puede tardar unos minutos)..."
& $VenvPlaywright install chromium

# ─── Script lanzador (para Task Scheduler) ────────────────────────────────────
# Task Scheduler no redirige stdout/stderr; usamos un wrapper .ps1 que
# captura la salida en agent.log mientras Chromium corre visible.

$LauncherPath = "$InstallDir\start-agent.ps1"

@"
# Auto-generado por install.ps1 — no editar manualmente
`$LogFile = "$InstallDir\agent.log"
`$PythonExe = "$VenvPy"
`$ScriptPath = "$InstallDir\server.py"

# Limpiar Singleton locks de Chromium de sesiones anteriores
Remove-Item "$InstallDir\browser-session\Singleton*" -ErrorAction SilentlyContinue

# Iniciar el agente redirigiendo stdout/stderr al log
`$psi = New-Object System.Diagnostics.ProcessStartInfo
`$psi.FileName = `$PythonExe
`$psi.Arguments = "`"`$ScriptPath`" --host=127.0.0.1 --port=$Port --update-url=$AgentUrl --user-data-dir=`"$InstallDir\browser-session`""
`$psi.UseShellExecute = `$false
`$psi.RedirectStandardOutput = `$true
`$psi.RedirectStandardError = `$true
`$psi.CreateNoWindow = `$true
`$psi.WorkingDirectory = "$InstallDir"

`$proc = [System.Diagnostics.Process]::Start(`$psi)

# Escribir salida al log de forma continua
`$writer = [System.IO.StreamWriter]::new(`$LogFile, `$true, [System.Text.Encoding]::UTF8)
`$writer.AutoFlush = `$true

Register-ObjectEvent -InputObject `$proc -EventName "OutputDataReceived" -Action {
    if (`$Event.SourceEventArgs.Data) { `$writer.WriteLine("[OUT] " + `$Event.SourceEventArgs.Data) }
} | Out-Null
Register-ObjectEvent -InputObject `$proc -EventName "ErrorDataReceived" -Action {
    if (`$Event.SourceEventArgs.Data) { `$writer.WriteLine("[ERR] " + `$Event.SourceEventArgs.Data) }
} | Out-Null

`$proc.BeginOutputReadLine()
`$proc.BeginErrorReadLine()
`$proc.WaitForExit()
`$writer.Close()
"@ | Set-Content -Path $LauncherPath -Encoding UTF8

Step "Script lanzador creado: $LauncherPath"

# ─── Task Scheduler (auto-inicio sin necesidad de admin) ──────────────────────

Step "Registrando tarea en Task Scheduler ($TaskName)..."

$Action = New-ScheduledTaskAction `
    -Execute "powershell.exe" `
    -Argument "-WindowStyle Hidden -NonInteractive -ExecutionPolicy Bypass -File `"$LauncherPath`"" `
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

Start-ScheduledTask -TaskName $TaskName

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
Write-Host "    iwr $AgentUrl/install.ps1 | iex"
Write-Host ""
