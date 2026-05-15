#!/usr/bin/env bash
# =============================================================================
# SRI Scraper — Deploy Script
# =============================================================================
# Uso:
#   1. Edita las variables VPS_USER y VPS_HOST a continuación.
#   2. Ejecuta desde la raíz del repo:
#        bash scripts/sri-scraper/deploy.sh
#
# Qué hace:
#   - Copia server.py y test-scraper.py al VPS
#   - Instala dependencias del sistema, Python y Playwright (Chromium)
#   - Configura Xvfb (display virtual, necesario para headless=False)
#   - Crea y activa un servicio systemd sri-scraper
# =============================================================================

set -euo pipefail

# ─── Configuración ───────────────────────────────────────────────────────────

VPS_USER="root"              # Usuario SSH del VPS
VPS_HOST="147.182.223.172"  # IP del droplet de Digital Ocean (mismo servidor que Laravel)
VPS_PORT="22"                # Puerto SSH (normalmente 22)
REMOTE_DIR="/opt/sri-scraper"
SERVICE_USER="www-data"      # Usuario del sistema que corre el servicio

# ─── Colores ─────────────────────────────────────────────────────────────────

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

info()    { echo -e "${GREEN}[deploy]${NC} $*"; }
warning() { echo -e "${YELLOW}[deploy]${NC} $*"; }
error()   { echo -e "${RED}[deploy]${NC} $*" >&2; exit 1; }

# ─── Validaciones locales ─────────────────────────────────────────────────────

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

[[ -f "$SCRIPT_DIR/server.py" ]]      || error "No se encontró server.py"
[[ -f "$SCRIPT_DIR/test-scraper.py" ]] || error "No se encontró test-scraper.py"
[[ "$VPS_HOST" == "TU_IP_O_DOMINIO" ]] && error "Edita VPS_HOST en el script antes de ejecutar"

SSH_OPTS="-p $VPS_PORT -o StrictHostKeyChecking=accept-new -o IdentitiesOnly=no -o PreferredAuthentications=password"

# ─── 1. Copiar archivos al VPS ───────────────────────────────────────────────

info "Copiando archivos a $VPS_USER@$VPS_HOST:$REMOTE_DIR ..."

ssh $SSH_OPTS "$VPS_USER@$VPS_HOST" "mkdir -p $REMOTE_DIR"

scp -P "$VPS_PORT" \
    "$SCRIPT_DIR/server.py" \
    "$SCRIPT_DIR/test-scraper.py" \
    "$VPS_USER@$VPS_HOST:$REMOTE_DIR/"

info "Archivos copiados."

# ─── 2. Setup remoto ─────────────────────────────────────────────────────────

info "Ejecutando setup remoto..."

ssh $SSH_OPTS "$VPS_USER@$VPS_HOST" bash <<REMOTE
set -euo pipefail

GREEN='\033[0;32m'; NC='\033[0m'
step() { echo -e "\${GREEN}[vps]\${NC} \$*"; }

# ── Sistema ──────────────────────────────────────────────────────────────────

step "Actualizando paquetes del sistema..."
apt-get update -qq

step "Instalando dependencias del sistema..."
apt-get install -y -qq \
    python3 python3-pip python3-venv \
    libnss3 libatk1.0-0 libatk-bridge2.0-0 libcups2 \
    libdrm2 libxkbcommon0 libxcomposite1 libxdamage1 \
    libxrandr2 libgbm1 libasound2t64 libpangocairo-1.0-0 \
    libpango-1.0-0 libcairo2 libatspi2.0-0 \
    fonts-liberation wget curl 2>/dev/null || \
    apt-get install -y -qq \
    python3 python3-pip python3-venv \
    libnss3 libatk1.0-0 libatk-bridge2.0-0 libcups2 \
    libdrm2 libxkbcommon0 libxcomposite1 libxdamage1 \
    libxrandr2 libgbm1 libasound2 libpangocairo-1.0-0 \
    libpango-1.0-0 libcairo2 libatspi2.0-0 \
    fonts-liberation wget curl

# ── Python venv ───────────────────────────────────────────────────────────────

step "Creando entorno virtual Python en $REMOTE_DIR/.venv ..."
python3 -m venv $REMOTE_DIR/.venv

step "Instalando paquetes Python..."
$REMOTE_DIR/.venv/bin/pip install --quiet --upgrade pip
$REMOTE_DIR/.venv/bin/pip install --quiet playwright playwright-stealth requests

# ── Playwright Chromium ───────────────────────────────────────────────────────

step "Instalando Chromium para Playwright..."
PLAYWRIGHT_BROWSERS_PATH=/opt/playwright-browsers \
    $REMOTE_DIR/.venv/bin/playwright install chromium

step "Instalando dependencias del sistema para Chromium..."
PLAYWRIGHT_BROWSERS_PATH=/opt/playwright-browsers \
    $REMOTE_DIR/.venv/bin/playwright install-deps chromium 2>/dev/null || true

# ── Permisos ──────────────────────────────────────────────────────────────────

step "Ajustando permisos..."
chown -R $SERVICE_USER:$SERVICE_USER $REMOTE_DIR 2>/dev/null || true
chown -R $SERVICE_USER:$SERVICE_USER /opt/playwright-browsers 2>/dev/null || true
mkdir -p $REMOTE_DIR/browser-session
chown -R $SERVICE_USER:$SERVICE_USER $REMOTE_DIR/browser-session 2>/dev/null || true

# ── Systemd: sri-scraper ──────────────────────────────────────────────────────

step "Creando servicio systemd sri-scraper..."
cat > /etc/systemd/system/sri-scraper.service <<SERVICE
[Unit]
Description=SRI Scraper HTTP Server
After=network.target

[Service]
Type=simple
User=$SERVICE_USER
WorkingDirectory=$REMOTE_DIR
Environment=PLAYWRIGHT_BROWSERS_PATH=/opt/playwright-browsers
ExecStart=$REMOTE_DIR/.venv/bin/python $REMOTE_DIR/server.py \\
    --host=127.0.0.1 \\
    --port=8765 \\
    --user-data-dir=$REMOTE_DIR/browser-session
Restart=on-failure
RestartSec=10
StandardOutput=journal
StandardError=journal
SyslogIdentifier=sri-scraper

[Install]
WantedBy=multi-user.target
SERVICE

# ── Activar servicios ─────────────────────────────────────────────────────────

step "Activando servicios..."
systemctl daemon-reload
systemctl enable sri-scraper
systemctl restart sri-scraper
sleep 3

# ── Verificar salud ───────────────────────────────────────────────────────────

step "Verificando salud del servidor..."
for i in 1 2 3 4 5; do
    if curl -sf http://127.0.0.1:8765/health > /dev/null 2>&1; then
        echo -e "\${GREEN}[vps]\${NC} Servidor saludable. Deploy completado."
        curl -s http://127.0.0.1:8765/health
        echo
        break
    fi
    echo "[vps] Esperando que el servidor arranque... (intento \$i/5)"
    sleep 4
done

step "Logs recientes:"
journalctl -u sri-scraper --no-pager -n 20

REMOTE

info "Deploy completado. Para ver logs en tiempo real:"
echo "  ssh -p $VPS_PORT $VPS_USER@$VPS_HOST 'journalctl -u sri-scraper -f'"
echo ""
info "Para actualizar solo los scripts Python (sin reinstalar todo):"
echo "  bash scripts/sri-scraper/deploy.sh --update-only"
echo ""

# ─── Modo --update-only ───────────────────────────────────────────────────────

if [[ "${1:-}" == "--update-only" ]]; then
    info "Modo --update-only: solo copiando archivos y reiniciando servicio..."
    scp -P "$VPS_PORT" \
        "$SCRIPT_DIR/server.py" \
        "$SCRIPT_DIR/test-scraper.py" \
        "$VPS_USER@$VPS_HOST:$REMOTE_DIR/"
    ssh $SSH_OPTS "$VPS_USER@$VPS_HOST" "systemctl restart sri-scraper && sleep 2 && curl -s http://127.0.0.1:8765/health"
    info "Actualización completada."
fi
