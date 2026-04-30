#!/usr/bin/env bash
# setup-server.sh — Configuración inicial del droplet (Ubuntu 24.04)
# Ejecutar como root: bash setup-server.sh
set -euo pipefail

DOMAIN="declarame.facec.ec"
APP_DIR="/var/www/fa-declarame"
DB_NAME="fa_declarame"
DB_USER="fa_declarame"
DB_PASS="$(openssl rand -base64 24)"
REPO_URL="git@github.com:pedroguaillas/fa-declarame.git"

echo "──────────────────────────────────────────"
echo "  fa-declarame — Setup servidor"
echo "──────────────────────────────────────────"

# ── 1. Actualizar sistema ────────────────────────────────────────────────────
apt-get update && apt-get upgrade -y

# ── 2. Instalar dependencias ─────────────────────────────────────────────────
apt-get install -y \
    curl git unzip zip supervisor ufw fail2ban \
    nginx certbot python3-certbot-nginx python3-certbot-dns-digitalocean \
    redis-server \
    postgresql postgresql-contrib

# ── 3. PHP 8.3 + extensiones ────────────────────────────────────────────────
add-apt-repository ppa:ondrej/php -y
apt-get update
apt-get install -y \
    php8.3-fpm php8.3-cli php8.3-pgsql php8.3-redis \
    php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip \
    php8.3-bcmath php8.3-intl php8.3-soap php8.3-gd

# ── 4. Node.js 22 ───────────────────────────────────────────────────────────
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt-get install -y nodejs

# ── 5. Composer ─────────────────────────────────────────────────────────────
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# ── 6. PostgreSQL — crear base de datos y usuario ───────────────────────────
sudo -u postgres psql <<SQL
CREATE USER $DB_USER WITH PASSWORD '$DB_PASS';
CREATE DATABASE $DB_NAME OWNER $DB_USER;
GRANT ALL PRIVILEGES ON DATABASE $DB_NAME TO $DB_USER;
SQL

echo ""
echo "✓ PostgreSQL: usuario=$DB_USER  db=$DB_NAME  pass=$DB_PASS"
echo "  (Guarda estos datos para el .env)"
echo ""

# ── 7. Directorio de la app ──────────────────────────────────────────────────
mkdir -p "$APP_DIR"
chown www-data:www-data "$APP_DIR"

# ── 8. Firewall ──────────────────────────────────────────────────────────────
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

# ── 9. Nginx ─────────────────────────────────────────────────────────────────
# El archivo nginx.conf del repo se copia manualmente después del clone
rm -f /etc/nginx/sites-enabled/default

# ── 10. PHP-FPM ──────────────────────────────────────────────────────────────
sed -i 's/^;*upload_max_filesize.*/upload_max_filesize = 64M/' /etc/php/8.3/fpm/php.ini
sed -i 's/^;*post_max_size.*/post_max_size = 64M/' /etc/php/8.3/fpm/php.ini
sed -i 's/^;*memory_limit.*/memory_limit = 256M/' /etc/php/8.3/fpm/php.ini
sed -i 's/^;*max_execution_time.*/max_execution_time = 120/' /etc/php/8.3/fpm/php.ini

systemctl restart php8.3-fpm

echo ""
echo "──────────────────────────────────────────"
echo "  Servidor listo. Próximos pasos:"
echo "  1. Clonar el repo en $APP_DIR"
echo "  2. Configurar .env (ver deployment/.env.production)"
echo "  3. Ejecutar deployment/deploy.sh"
echo "  4. Emitir certificado SSL (ver instrucciones en deploy.sh)"
echo "──────────────────────────────────────────"
