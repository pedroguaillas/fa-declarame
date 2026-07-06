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
#   - Crea y activa un programa supervisor sri-scraper (no systemd)
# =============================================================================

set -euo pipefail

# ─── Configuración ───────────────────────────────────────────────────────────

VPS_USER="root"                    # Usuario SSH del VPS
VPS_PORT="22"                      # Puerto SSH (normalmente 22)
REMOTE_DIR="/opt/sri-scraper"
SERVICE_USER="www-data"            # Usuario del sistema que corre el servicio

# Defaults; los argumentos los sobreescriben (se parsean más abajo, en cualquier orden).
VPS_HOST="147.182.223.172"         # IP del droplet (default: servidor Laravel)
REMOTE_MODE=false                  # --remote: bind 0.0.0.0 + abre UFW puerto 8765
UPDATE_ONLY=false                  # --update-only: solo copia scripts y reinicia (sin install)
SCRAPER_BIND_HOST="127.0.0.1"

# ─── Colores ─────────────────────────────────────────────────────────────────

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

info()    { echo -e "${GREEN}[deploy]${NC} $*"; }
warning() { echo -e "${YELLOW}[deploy]${NC} $*"; }
error()   { echo -e "${RED}[deploy]${NC} $*" >&2; exit 1; }

# ─── Argumentos (host + flags en cualquier orden) ─────────────────────────────
# Uso:
#   bash deploy.sh <IP> [--remote] [--update-only]
# El primer argumento que no empiece con -- se toma como host.

_host_set=false
for arg in "$@"; do
    case "$arg" in
        --remote)      REMOTE_MODE=true ;;
        --update-only) UPDATE_ONLY=true ;;
        --*)           warning "Flag desconocido ignorado: $arg" ;;
        *)             if [[ "$_host_set" == false ]]; then VPS_HOST="$arg"; _host_set=true; fi ;;
    esac
done

[[ "$REMOTE_MODE" == true ]] && SCRAPER_BIND_HOST="0.0.0.0"

# ─── Validaciones locales ─────────────────────────────────────────────────────

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

[[ -f "$SCRIPT_DIR/server.py" ]]      || error "No se encontró server.py"
[[ -f "$SCRIPT_DIR/test-scraper.py" ]] || error "No se encontró test-scraper.py"
[[ "$VPS_HOST" == "TU_IP_O_DOMINIO" ]] && error "Edita VPS_HOST en el script antes de ejecutar"

SSH_OPTS="-p $VPS_PORT -o StrictHostKeyChecking=accept-new -o IdentitiesOnly=no -o PreferredAuthentications=password"

# ─── Modo --update-only: copia scripts + reinicia, sin reinstalar nada ─────────

if [[ "$UPDATE_ONLY" == true ]]; then
    info "Modo --update-only: copiando scripts y reiniciando servicio en $VPS_HOST ..."
    scp -P "$VPS_PORT" \
        "$SCRIPT_DIR/server.py" \
        "$SCRIPT_DIR/test-scraper.py" \
        "$VPS_USER@$VPS_HOST:$REMOTE_DIR/"
    ssh $SSH_OPTS "$VPS_USER@$VPS_HOST" "supervisorctl restart sri-scraper && sleep 3 && curl -s http://127.0.0.1:8765/health"
    info "Actualización completada."
    exit 0
fi

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
    fonts-liberation wget curl xvfb xauth supervisor 2>/dev/null || \
    apt-get install -y -qq \
    python3 python3-pip python3-venv \
    libnss3 libatk1.0-0 libatk-bridge2.0-0 libcups2 \
    libdrm2 libxkbcommon0 libxcomposite1 libxdamage1 \
    libxrandr2 libgbm1 libasound2 libpangocairo-1.0-0 \
    libpango-1.0-0 libcairo2 libatspi2.0-0 \
    fonts-liberation wget curl xvfb xauth supervisor

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

# ── Supervisor: sri-scraper ───────────────────────────────────────────────────

step "Asegurando que supervisor esté activo..."
systemctl enable --now supervisor 2>/dev/null || service supervisor start 2>/dev/null || true
mkdir -p /etc/supervisor/conf.d

step "Creando config supervisor sri-scraper..."
cat > /etc/supervisor/conf.d/sri-scraper.conf <<SUPCONF
[program:sri-scraper]
# Corre el navegador VISIBLE dentro de un display virtual Xvfb (no --headless):
# reCAPTCHA v3 penaliza el modo headless, así que presentamos un Chrome real con
# ventana sobre un framebuffer sin pantalla física.
command=xvfb-run -a --server-args="-screen 0 1366x768x24" $REMOTE_DIR/.venv/bin/python $REMOTE_DIR/server.py --host=$SCRAPER_BIND_HOST --port=8765 --user-data-dir=$REMOTE_DIR/browser-session
directory=$REMOTE_DIR
user=$SERVICE_USER
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
environment=PLAYWRIGHT_BROWSERS_PATH=/opt/playwright-browsers,HOME=$REMOTE_DIR,TMPDIR=/tmp
redirect_stderr=true
stdout_logfile=/var/log/sri-scraper.log
stopwaitsecs=30
SUPCONF

# ── Firewall (solo modo --remote) ────────────────────────────────────────────

if [[ "$SCRAPER_BIND_HOST" == "0.0.0.0" ]]; then
    step "Abriendo puerto 8765 solo desde servidor Laravel (147.182.223.172)..."
    ufw allow from 10.116.0.4 to any port 8765 comment 'sri-scraper from srv-declarame vpc' 2>/dev/null || true
fi

# ── Activar supervisor ────────────────────────────────────────────────────────

step "Activando supervisor..."
supervisorctl stop sri-scraper 2>/dev/null || true
# Clean up Chromium lock files left by previous instance
rm -f $REMOTE_DIR/browser-session/Singleton*
# Wait for port 8765 to be fully released
for i in \$(seq 1 15); do
    ss -tlnp | grep -q ':8765' || break
    sleep 2
done
supervisorctl reread
supervisorctl update
supervisorctl start sri-scraper
sleep 5

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
supervisorctl tail sri-scraper

REMOTE

info "Deploy completado. Para ver logs en tiempo real:"
echo "  ssh -p $VPS_PORT $VPS_USER@$VPS_HOST 'supervisorctl tail -f sri-scraper'"
echo ""
info "Para actualizar solo los scripts Python (sin reinstalar todo):"
echo "  bash scripts/sri-scraper/deploy.sh $VPS_HOST --update-only"
echo ""
