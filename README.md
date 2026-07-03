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

Configuración del servicio (supervisor):
```
/etc/supervisor/conf.d/sri-scraper.conf
```

### Supervisor — comandos frecuentes

```bash
# Estado
supervisorctl status sri-scraper

# Reiniciar (tras deploy --update-only)
supervisorctl restart sri-scraper

# Detener / arrancar
supervisorctl stop sri-scraper
supervisorctl start sri-scraper

# Health check del scraper
curl -s http://127.0.0.1:8765/health
```

### Logs

```bash
# Tiempo real
supervisorctl tail -f sri-scraper

# Últimas líneas del archivo de log
tail -n 100 /var/log/sri-scraper.log
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
supervisorctl stop sri-scraper
rm -rf /opt/sri-scraper/browser-session
rm -f /opt/sri-scraper/browser-session/Singleton*
supervisorctl start sri-scraper
```

---

## Queue Worker

Gestionado con supervisor (`/etc/supervisor/conf.d/fa-declarame.conf`).

```bash
# Estado
supervisorctl status fa-declarame-worker:*

# Reiniciar graceful (deploy lo hace automáticamente)
php artisan queue:restart
supervisorctl restart fa-declarame-worker:*

# Logs en tiempo real
supervisorctl tail -f fa-declarame-worker:fa-declarame-worker_00

# Últimas líneas del archivo de log
tail -n 100 /var/www/fa-declarame/storage/logs/worker.log
```

### Instalación inicial del servicio (una sola vez)

```bash
cp /var/www/fa-declarame/deployment/supervisor.conf /etc/supervisor/conf.d/fa-declarame.conf
supervisorctl reread
supervisorctl update
supervisorctl start fa-declarame-worker:*
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
# Scraper — tiempo real
supervisorctl tail -f sri-scraper

# Scraper — archivo
tail -n 100 /var/log/sri-scraper.log

# Queue worker — tiempo real
supervisorctl tail -f fa-declarame-worker:fa-declarame-worker_00

# Queue worker — archivo
tail -n 100 /var/www/fa-declarame/storage/logs/worker.log
```
