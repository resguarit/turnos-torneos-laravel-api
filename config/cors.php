<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['https://rgturnos.com.ar', '*.localhost:5173', 'http://localhost:5173', 'https://51dd-191-84-236-181.ngrok-free.app/'], // Your Vite dev server URL
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];