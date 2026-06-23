## DECLARAME

Sistema Tributario para el procesamiento de información de Compras, Ventas y Retenciones de los contribuyentes que los contadores llevan contabilidad externa.

---

## Instalación inicial

```bash
# Instalar dependencias con Docker
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs

# Copiar configuración
cp .env.example .env
```

---

## Actualizar repositorio

```bash
git fetch origin
git merge origin/main
```

---

## Base de datos

```bash
# Migrar base de datos de tenants
./vendor/bin/sail artisan tenants:migrate

# Migrar un tenant específico
./vendor/bin/sail artisan tenants:migrate --tenants=ID

# Seed faltante de tipos de identificación
./vendor/bin/sail php artisan tenant:seed-identification-types
```

---

## Dependencias especiales

```bash
# Instalar maatwebsite/excel ignorando incompatibilidades
./vendor/bin/sail composer require maatwebsite/excel --ignore-platform-reqs
./vendor/bin/sail composer require maatwebsite/excel:"4.x-dev as 4.0.0"
```

---

## Scraper SRI

```bash
# Iniciar servidor scraper (local)
cd scripts/sri-scraper
source venv/bin/activate
python server.py

# Probar job automático de scraping
./vendor/bin/sail artisan sri:daily-scrape

# Desplegar scraper (local → remoto)
bash scripts/sri-scraper/deploy.sh --update-only
bash scripts/sri-scraper/deploy.sh IP-REMOTE --update-only --remote
```

Configuración del servicio systemd:
```
ExecStart=/opt/sri-scraper/.venv/bin/python /opt/sri-scraper/server.py \
    --host=127.0.0.1 \
    --port=8765 \
    --user-data-dir=/opt/sri-scraper/browser-session \
    --headless
```

---

## Despliegue

```bash
bash /var/www/fa-declarame/deployment/deploy.sh
```

---

## Backup y restauración de tenant DB

```bash
# Backup
./vendor/bin/sail exec pgsql pg_dump -U sail tenant_abc123 > backup_tenant_abc123_$(date +%Y%m%d).sql

# Restore
./vendor/bin/sail exec -T pgsql psql -U sail tenant_[UUID] < backup_tenant_[UUID]_YYYYMMDD.sql
```

---

## Logs

```bash
# Últimas N líneas del scraper
journalctl -u sri-scraper -n 100 --no-pager

# Seguimiento en tiempo real
journalctl -u sri-scraper -f
```
