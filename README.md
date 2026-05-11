## DECLARAME

Sistema Tributario para el procesamiento de información de Compras, Ventas y Retenciones de los contribuyentes que los contadores llevan contabilidad externa.

# Levantar app con docker

`docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs`

# Copiar .env.example a .env

`cp .env.example .env`

# Descargar cambios del repositorio

`git fetch origin`

# Aplicar cambios para correguir

`git merge origin/main`

# Instalar una dependencia ignorando la incompatibilidad

`./vendor/bin/sail composer require maatwebsite/excel --ignore-platform-reqs`
`./vendor/bin/sail composer require maatwebsite/excel: "4.x-dev as 4.0.0"`

Migrar tenant (opcional) `./vendor/bin/sail artisan tenants:migrate`

Probar script

`cd scripts/sri-scraper`

`source venv/bin/activate`
  
`python server.py`
