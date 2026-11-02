<?php

declare(strict_types=1);

$requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$scriptName = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$isHealthRoute = rtrim($requestPath, '/') === '/health' || str_ends_with(rtrim($requestPath, '/'), '/health');
$isDirectIndex = $scriptName === 'index.php';

if (!$isHealthRoute && !$isDirectIndex) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found';
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status' => 'ok',
    'app' => 'CTSMD Connect',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
