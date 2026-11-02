<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$projectBase = rtrim(dirname(dirname($scriptName)), '/');
$frontScript = ($projectBase === '' ? '' : $projectBase) . '/front.php';

$_SERVER['SCRIPT_NAME'] = $frontScript;
$_SERVER['PHP_SELF'] = $frontScript;
$_SERVER['SCRIPT_FILENAME'] = $projectRoot . '/front.php';

require $projectRoot . '/front.php';
