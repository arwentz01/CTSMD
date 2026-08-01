<?php

declare(strict_types=1);

return [
    'name' => 'CTSMD Connect',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL),
    'url' => rtrim($_ENV['APP_URL'] ?? 'http://localhost:8080', '/'),
    'base_path' => rtrim($_ENV['APP_BASE_PATH'] ?? '', '/'),
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'America/New_York',
    'session_name' => $_ENV['SESSION_NAME'] ?? 'ctsmd_connect_session',
];
