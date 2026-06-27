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

Configuración del servicio (systemd):
```
/etc/systemd/system/sri-scraper.service
```

### Systemd — comandos frecuentes

```bash
# Estado
systemctl status sri-scraper

# Reiniciar (tras deploy --update-only)
systemctl restart sri-scraper

# Detener / arrancar
systemctl stop sri-scraper
systemctl start sri-scraper

# Health check del scraper
curl -s http://127.0.0.1:8765/health
```

### Logs

```bash
# Tiempo real
journalctl -u sri-scraper -f

# Últimas 100 líneas
journalctl -u sri-scraper --no-pager -n 100

# Limpiar log (vaciar journal del servicio)
journalctl --rotate && journalctl --vacuum-time=1s -u sri-scraper
```

### Diagnóstico de conflictos de puerto

```bash
# Ver qué proceso ocupa puerto 8765
ss -tlnp | grep 8765

# Health check del scraper
curl -s http://127.0.0.1:8765/health
```

### Limpiar sesión de browser (si RUC anterior quedó guardado)

```bash
systemctl stop sri-scraper
rm -rf /opt/sri-scraper/browser-session
rm -f /opt/sri-scraper/browser-session/Singleton*
systemctl start sri-scraper
```

---

## Queue Worker

Gestionado con systemd (`/etc/systemd/system/fa-declarame-worker.service`).

```bash
# Estado
systemctl status fa-declarame-worker

# Reiniciar (deploy lo hace automáticamente)
systemctl restart fa-declarame-worker

# Logs en tiempo real
journalctl -u fa-declarame-worker -f

# Últimas 100 líneas
journalctl -u fa-declarame-worker --no-pager -n 100
```

### Instalación inicial del servicio (una sola vez)

```bash
cp /var/www/fa-declarame/deployment/fa-declarame-worker.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable fa-declarame-worker
systemctl start fa-declarame-worker
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
journalctl -u sri-scraper --no-pager -n 100

# Seguimiento en tiempo real
journalctl -u sri-scraper -f
```
