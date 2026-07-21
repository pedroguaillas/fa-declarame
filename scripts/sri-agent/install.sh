#!/usr/bin/env bash
# =============================================================================
# SRI Agent — Instalador / Actualizador
# =============================================================================
# Instala el agente local de Declárame que descarga comprobantes del SRI
# usando un navegador real en tu computadora (sin servidor remoto).
#
# Uso (primera vez o actualización):
#   curl -sSL https://declarame.facec.ec/agent/install.sh | bash
#
# Con dominio personalizado:
#   AGENT_URL=https://mi-dominio.com/agent bash <(curl -sSL ...)
#
# Plataformas: macOS, Linux (Debian/Ubuntu/Fedora)
# =============================================================================

set -euo pipefail

# ─── Configuración ────────────────────────────────────────────────────────────

AGENT_URL="${AGENT_URL:-https://declarame.facec.ec/agent}"
INSTALL_DIR="$HOME/.sri-agent"
PORT=8765
SERVICE_LABEL="com.declarame.sri-agent"

# ─── Colores ──────────────────────────────────────────────────────────────────

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; BOLD='\033[1m'; NC='\033[0m'

step()    { echo -e "${GREEN}[sri-agent]${NC} $*"; }
warning() { echo -e "${YELLOW}[sri-agent]${NC} $*"; }
error()   { echo -e "${RED}[sri-agent]${NC} $*" >&2; exit 1; }
header()  { echo -e "\n${BOLD}$*${NC}"; }

# ─── Sistema operativo ────────────────────────────────────────────────────────

OS="$(uname -s)"
[[ "$OS" == "Darwin" || "$OS" == "Linux" ]] || error "Sistema no soportado: $OS. Usa el instalador para Windows."

header "SRI Agent — Instalador"
echo "  URL del agente : $AGENT_URL"
echo "  Directorio     : $INSTALL_DIR"
echo "  Puerto         : $PORT"
echo ""

# ─── Python 3.9+ ─────────────────────────────────────────────────────────────

step "Buscando Python 3.9+..."

PYTHON=""
for candidate in python3.13 python3.12 python3.11 python3.10 python3.9 python3; do
    if command -v "$candidate" &>/dev/null; then
        _ver="$($candidate --version 2>&1 | awk '{print $2}')"
        _major="${_ver%%.*}"
        _minor="${_ver#*.}"; _minor="${_minor%%.*}"
        if [[ "$_major" -ge 3 && "$_minor" -ge 9 ]]; then
            PYTHON="$(command -v "$candidate")"
            break
        fi
    fi
done

if [[ -z "$PYTHON" ]]; then
    if [[ "$OS" == "Darwin" ]] && command -v brew &>/dev/null; then
        step "Instalando Python 3 via Homebrew..."
        brew install python@3.12
        PYTHON="$(command -v python3.12 || command -v python3)"
    elif command -v apt-get &>/dev/null; then
        step "Instalando Python 3 via apt..."
        sudo apt-get update -qq
        sudo apt-get install -y -qq python3 python3-pip python3-venv
        PYTHON="$(command -v python3)"
    elif command -v dnf &>/dev/null; then
        step "Instalando Python 3 via dnf..."
        sudo dnf install -y python3 python3-pip
        PYTHON="$(command -v python3)"
    else
        error "Python 3.9+ no encontrado. Instálalo desde https://python.org y vuelve a ejecutar este script."
    fi
fi

step "Python encontrado: $("$PYTHON" --version)"

# ─── Directorio de instalación ────────────────────────────────────────────────

step "Preparando directorio $INSTALL_DIR ..."
mkdir -p "$INSTALL_DIR"
mkdir -p "$INSTALL_DIR/browser-session"

# ─── Descargar scripts del agente ────────────────────────────────────────────

step "Descargando scripts desde $AGENT_URL ..."
curl -sSL --fail "$AGENT_URL/server.py"       -o "$INSTALL_DIR/server.py"
curl -sSL --fail "$AGENT_URL/test-scraper.py" -o "$INSTALL_DIR/test-scraper.py"
step "Scripts descargados."

# ─── Entorno virtual Python ───────────────────────────────────────────────────

if [[ ! -d "$INSTALL_DIR/venv" ]]; then
    step "Creando entorno virtual Python..."
    "$PYTHON" -m venv "$INSTALL_DIR/venv"
fi

VENV_PY="$INSTALL_DIR/venv/bin/python"
VENV_PIP="$INSTALL_DIR/venv/bin/pip"
VENV_PLAYWRIGHT="$INSTALL_DIR/venv/bin/playwright"

step "Actualizando pip..."
"$VENV_PIP" install --quiet --upgrade pip

step "Instalando dependencias Python (playwright, playwright-stealth)..."
"$VENV_PIP" install --quiet --upgrade playwright playwright-stealth

# ─── Playwright Chromium ──────────────────────────────────────────────────────

step "Instalando Chromium para Playwright (puede tardar unos minutos)..."
"$VENV_PLAYWRIGHT" install chromium

if [[ "$OS" == "Linux" ]]; then
    step "Instalando dependencias del sistema para Chromium..."
    "$VENV_PLAYWRIGHT" install-deps chromium 2>/dev/null || true
fi

# ─── Servicio del sistema ─────────────────────────────────────────────────────

AGENT_CMD_ARGS=(
    "--host=127.0.0.1"
    "--port=$PORT"
    "--update-url=$AGENT_URL"
    "--user-data-dir=$INSTALL_DIR/browser-session"
)

if [[ "$OS" == "Darwin" ]]; then
    # ── macOS: launchd ────────────────────────────────────────────────────────
    PLIST_DIR="$HOME/Library/LaunchAgents"
    PLIST="$PLIST_DIR/$SERVICE_LABEL.plist"
    mkdir -p "$PLIST_DIR"

    step "Configurando servicio launchd ($SERVICE_LABEL)..."

    cat > "$PLIST" <<PLIST
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>${SERVICE_LABEL}</string>
    <key>ProgramArguments</key>
    <array>
        <string>${VENV_PY}</string>
        <string>${INSTALL_DIR}/server.py</string>
        <string>--host=127.0.0.1</string>
        <string>--port=${PORT}</string>
        <string>--update-url=${AGENT_URL}</string>
        <string>--user-data-dir=${INSTALL_DIR}/browser-session</string>
    </array>
    <key>RunAtLoad</key>
    <true/>
    <key>KeepAlive</key>
    <true/>
    <key>StandardOutPath</key>
    <string>${INSTALL_DIR}/agent.log</string>
    <key>StandardErrorPath</key>
    <string>${INSTALL_DIR}/agent.log</string>
    <key>EnvironmentVariables</key>
    <dict>
        <key>HOME</key>
        <string>${HOME}</string>
    </dict>
</dict>
</plist>
PLIST

    # Detener instancia anterior si existe
    launchctl unload "$PLIST" 2>/dev/null || true
    # Limpiar Singleton locks de Chromium de sesiones anteriores
    rm -f "$INSTALL_DIR/browser-session/Singleton"* 2>/dev/null || true
    launchctl load -w "$PLIST"
    step "Servicio launchd registrado. Arranca automáticamente al iniciar sesión."

elif [[ "$OS" == "Linux" ]]; then
    # ── Linux: systemd user ───────────────────────────────────────────────────
    SERVICE_DIR="$HOME/.config/systemd/user"
    mkdir -p "$SERVICE_DIR"

    step "Configurando servicio systemd usuario ($SERVICE_LABEL)..."

    cat > "$SERVICE_DIR/sri-agent.service" <<SERVICE
[Unit]
Description=SRI Agent — Declárame
After=network.target

[Service]
ExecStart=${VENV_PY} ${INSTALL_DIR}/server.py --host=127.0.0.1 --port=${PORT} --update-url=${AGENT_URL} --user-data-dir=${INSTALL_DIR}/browser-session
Restart=on-failure
RestartSec=10
Environment=HOME=${HOME}

[Install]
WantedBy=default.target
SERVICE

    rm -f "$INSTALL_DIR/browser-session/Singleton"* 2>/dev/null || true
    systemctl --user daemon-reload
    systemctl --user enable sri-agent
    systemctl --user restart sri-agent
    step "Servicio systemd registrado. Arranca automáticamente con el usuario."
fi

# ─── Verificar salud ──────────────────────────────────────────────────────────

step "Esperando que el agente arranque (hasta 30 s)..."
STARTED=false
for i in $(seq 1 10); do
    if curl -sf "http://127.0.0.1:$PORT/health" > /dev/null 2>&1; then
        STARTED=true
        break
    fi
    sleep 3
done

if [[ "$STARTED" == true ]]; then
    VERSION="$(curl -s "http://127.0.0.1:$PORT/health" | "$VENV_PY" -c "import sys,json; print(json.load(sys.stdin).get('version','?'))")"
    echo ""
    echo -e "${GREEN}${BOLD}✓ SRI Agent instalado y corriendo.${NC}"
    echo "  Versión : $VERSION"
    echo "  Puerto  : $PORT"
    echo "  Logs    : $INSTALL_DIR/agent.log"
else
    warning "El agente no respondió en 30 s. Revisa los logs: $INSTALL_DIR/agent.log"
fi

echo ""
echo "  Para actualizar en el futuro:"
echo "  curl -sSL $AGENT_URL/install.sh | bash"
echo ""
