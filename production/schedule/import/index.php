<?php

declare(strict_types=1);

$requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$basePath = preg_replace('#/production/schedule/import/?$#', '', rtrim($requestPath, '/')) ?: '';
$projectRoot = dirname(__DIR__, 3);
$_ENV['APP_BASE_PATH'] = $basePath;
$_SERVER['APP_BASE_PATH'] = $basePath;
putenv('APP_BASE_PATH=' . $basePath);

require_once $projectRoot . '/src/RuntimeSchemaGuard.php';
RuntimeSchemaGuard::requireCurrentSchema($projectRoot, $basePath);
require_once $projectRoot . '/src/ScheduleImportExperience.php';
ScheduleImportExperience::render($basePath);
