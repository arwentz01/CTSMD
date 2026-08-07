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

require __DIR__ . '/index.php';
