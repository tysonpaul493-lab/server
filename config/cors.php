<?php

$rawOrigins = trim((string) env('CORS_ALLOWED_ORIGINS', '*'));
$allowedOrigins = $rawOrigins === '*'
    ? ['*']
    : array_values(array_filter(array_map('trim', explode(',', $rawOrigins))));

return [
    'paths' => ['api/*', 'broadcasting/*', 'health', 'up'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => $allowedOrigins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept', 'X-Admin-Token', 'X-Requested-With'],
    'exposed_headers' => [],
    'max_age' => 3600,
    'supports_credentials' => false,
];
