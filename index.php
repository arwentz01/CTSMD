<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status' => 'ok',
    'app' => 'CTSMD Connect',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
