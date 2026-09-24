#!/usr/bin/env bash
# deploy.sh — Despliegue y actualización de fa-declarame
# Uso: bash deployment/deploy.sh
set -euo pipefail

APP_DIR="/var/www/fa-declarame"
PHP="php8.4"

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

echo "── Despliegue completado ✓ ──────────────"
