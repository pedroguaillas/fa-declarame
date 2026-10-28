#!/usr/bin/env bash
# deploy.sh — Despliegue y actualización de fa-declarame
# Uso: bash deployment/deploy.sh
set -euo pipefail

# ── Certificado SSL (una sola vez, fuera de este script) ─────────────────────
# El certificado es wildcard (*.declarame.facec.ec), por lo que Let's Encrypt
# exige el challenge DNS-01. El DNS de facec.ec está en dominiosecuador.ec
# (sin API), así que la renovación es MANUAL: no se renueva sola y caduca a
# los 90 días. Renovar unos días antes del vencimiento (el paso 9 avisa
# cuando faltan menos de 14 días):
#
#   1. Iniciar la renovación (dejar la terminal abierta hasta el final):
#        certbot certonly --manual --preferred-challenges dns \
#          -d declarame.facec.ec -d '*.declarame.facec.ec' \
#          --cert-name declarame.facec.ec --force-renewal
#
#   2. Certbot pide DOS valores TXT, ambos para el mismo nombre
#      _acme-challenge.declarame.facec.ec. En el panel de dominiosecuador,
#      REEMPLAZAR los dos TXT existentes (son de la renovación anterior) por
#      los nuevos. Deben quedar exactamente dos registros.
#
#   3. Antes de presionar Enter, confirmar que los nameservers devuelven
#      solo los dos valores nuevos:
#        dig +short TXT _acme-challenge.declarame.facec.ec @ns101.dominiosecuador.ec
#        dig +short TXT _acme-challenge.declarame.facec.ec @ns102.dominiosecuador.ec
#
#   4. Presionar Enter en certbot y recargar nginx:
#        nginx -t && systemctl reload nginx
#
#   5. Verificar el certificado servido:
#        echo | openssl s_client -servername declarame.facec.ec \
#          -connect declarame.facec.ec:443 2>/dev/null | openssl x509 -noout -dates

APP_DIR="/var/www/fa-declarame"
PHP="php8.4"
CERT_FILE="/etc/letsencrypt/live/declarame.facec.ec/fullchain.pem"

fix_permissions() {
    chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
    chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
}

# Si cualquier paso falla a mitad de camino (composer, pnpm, migraciones...),
# los pasos previos pueden haber dejado archivos en storage/ con dueño root
# (el deploy corre como root). Sin este trap, esos archivos quedan huérfanos
# y www-data no puede escribir logs hasta el próximo deploy exitoso.
trap fix_permissions EXIT

echo "── Desplegando fa-declarame ──────────────"

cd "$APP_DIR"

# ── 1. Obtener cambios ───────────────────────────────────────────────────────
git pull origin main

# ── 2. Dependencias PHP ──────────────────────────────────────────────────────
composer install --no-dev --optimize-autoloader --no-interaction

# ── 3. Frontend ──────────────────────────────────────────────────────────────
pnpm install --frozen-lockfile
pnpm run build

# ── 4. Migraciones ───────────────────────────────────────────────────────────
$PHP artisan migrate --force
$PHP artisan tenants:migrate --force

# ── 5. Caché de producción ───────────────────────────────────────────────────
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache

# ── 6. Storage ───────────────────────────────────────────────────────────────
$PHP artisan storage:link 2>/dev/null || true

# ── 7. Permisos ──────────────────────────────────────────────────────────────
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# ── 8. Reiniciar servicios ───────────────────────────────────────────────────
systemctl reload php8.4-fpm
$PHP artisan queue:restart
supervisorctl restart fa-declarame-worker:*

# ── 9. Verificar certificado SSL ─────────────────────────────────────────────
# Solo advierte; no interrumpe el despliegue. Ver instrucciones al inicio.
if [ -f "$CERT_FILE" ]; then
    if ! openssl x509 -checkend $((14 * 86400)) -noout -in "$CERT_FILE" >/dev/null; then
        echo "⚠ Certificado SSL caduca en menos de 14 días ($(openssl x509 -enddate -noout -in "$CERT_FILE"))"
        echo "  Revisar renovación: certbot renew --dry-run"
    fi
else
    echo "⚠ No se encontró $CERT_FILE — emitir certificado (ver instrucciones al inicio)"
fi

echo "── Despliegue completado ✓ ──────────────"
