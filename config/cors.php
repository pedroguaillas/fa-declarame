<?php

return [
    // Rutas donde se aplicará CORS. 
    // Incluimos 'login', 'logout' y 'sanctum/csrf-cookie' para Inertia/Sanctum.
    'paths' => ['api/*', 'web/*', 'sanctum/csrf-cookie', 'login', 'login2', 'logout', 'register'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost',
        'http://127.0.0.1',
        'http://*.localhost', // Esto habilita foo.localhost, bar.localhost, etc.
    ],

    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,

];
