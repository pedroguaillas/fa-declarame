<?php

return [

    'url' => env('SRI_CATASTRO_URL'),

    'captcha' => [
        'api_key' => env('SRI_CAPTCHA_API_KEY'),
    ],

    'scraper' => [
        'timeout' => (int) env('SRI_SCRAPER_TIMEOUT', 600),
        'engine' => env('SRI_SCRAPER_ENGINE', 'python'), // 'python' or 'node'
        'node_path' => env('SRI_SCRAPER_NODE_PATH', 'node'),
        'python_path' => env('SRI_SCRAPER_PYTHON_PATH', 'python3'),
        'script_path' => base_path('scripts/sri-scraper/scrape.mjs'),
        'python_script_path' => base_path('scripts/sri-scraper/test-scraper.py'),
        'chrome_path' => env('SRI_SCRAPER_CHROME_PATH'),
        'user_data_dir' => env('SRI_SCRAPER_USER_DATA_DIR', storage_path('app/private/sri-browser-session')),
    ],

    'urls' => [
        'portal' => 'https://srienlinea.sri.gob.ec/sri-en-linea/contribuyente/perfil',
        'compras' => 'https://srienlinea.sri.gob.ec/tuportal-internet/accederAplicacion.jspa?redireccion=57&idGrupo=55',
        'ventas' => 'https://srienlinea.sri.gob.ec/tuportal-internet/accederAplicacion.jspa?redireccion=60&idGrupo=55',
    ],

];
