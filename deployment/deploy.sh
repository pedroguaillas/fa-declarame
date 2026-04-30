#!/usr/bin/env bash
# deploy.sh — Despliegue y actualización de fa-declarame
# Uso: bash deployment/deploy.sh
set -euo pipefail

APP_DIR="/var/www/fa-declarame"
PHP="php8.3"

echo "── Desplegando fa-declarame ──────────────"

cd "$APP_DIR"

# ── 1. Obtener cambios ───────────────────────────────────────────────────────
git pull origin main

# ── 2. Dependencias PHP ──────────────────────────────────────────────────────
composer install --no-dev --optimize-autoloader --no-interaction

# ── 3. Frontend ──────────────────────────────────────────────────────────────
npm ci
npm run build

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
systemctl reload php8.3-fpm
supervisorctl restart fa-declarame-worker:*

echo "── Despliegue completado ✓ ──────────────"
