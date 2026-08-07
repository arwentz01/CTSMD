<?php

declare(strict_types=1);

/*
 * Local/shared-hosting front controller shim.
 *
 * Derive the application base path from the URL Apache used to execute this
 * file. This keeps CTSMD working from /ctsmd, /CTSMD, a renamed subdirectory,
 * or a site root without depending on SetEnv behavior.
 */
$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$detectedBasePath = rtrim(str_replace('/front.php', '', $scriptName), '/');

$_ENV['APP_BASE_PATH'] = $detectedBasePath;
$_SERVER['APP_BASE_PATH'] = $detectedBasePath;
putenv('APP_BASE_PATH=' . $detectedBasePath);

$requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$route = $requestPath;
if ($detectedBasePath !== '' && str_starts_with($route, $detectedBasePath)) {
    $route = substr($route, strlen($detectedBasePath)) ?: '/';
}
$route = rtrim($route, '/') ?: '/';

if ($route === '/navigation') {
    require_once __DIR__ . '/src/NavigationReview.php';
    $data = require __DIR__ . '/src/mock-data.php';
    NavigationReview::render($detectedBasePath, $data);
}

require_once __DIR__ . '/src/VisualPass3.php';
if (VisualPass3::handles($route)) {
    $data = require __DIR__ . '/src/mock-data.php';
    VisualPass3::render($route, $detectedBasePath, $data);
}

require_once __DIR__ . '/src/VisualPass.php';
if (VisualPass::handles($route)) {
    $data = require __DIR__ . '/src/mock-data.php';
    VisualPass::render($route, $detectedBasePath, $data);
}

require __DIR__ . '/index.php';
