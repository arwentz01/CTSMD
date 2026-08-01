<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$phpFiles = [
    'public/index.php',
    'src/Http/Response.php',
    'src/Http/Router.php',
    'src/Auth/Auth.php',
    'src/Database/Connection.php',
    'src/Repository/AdminRepository.php',
    'src/Repository/ChannelRepository.php',
    'src/Repository/ModerationRepository.php',
    'src/Repository/NotificationRepository.php',
    'src/Repository/SafeguardingRepository.php',
    'src/Support/Csrf.php',
    'src/Support/Environment.php',
    'src/View/View.php',
    'config/app.php',
    'config/database.php',
    'tools/migrate.php',
    'tools/demo_seed.php',
];

$failures = [];

foreach ($phpFiles as $file) {
    $command = escapeshellcmd(PHP_BINARY) . ' -l ' . escapeshellarg($basePath . '/' . $file);
    exec($command, $output, $status);

    if ($status !== 0) {
        $failures[] = $file . ': ' . implode("\n", $output);
    }
}

$requiredFiles = [
    '.env.example',
    'database/migrations/001_foundation_schema.sql',
    'docs/ARCHITECTURE.md',
    'docs/DEPLOYMENT_BLUEHOST.md',
    'docs/PRODUCT_CHARTER.md',
    'views/channel.php',
    'views/conversation.php',
];

foreach ($requiredFiles as $file) {
    if (!is_file($basePath . '/' . $file)) {
        $failures[] = 'Missing required file: ' . $file;
    }
}

$schema = file_get_contents($basePath . '/database/migrations/001_foundation_schema.sql');
if ($schema === false) {
    $failures[] = 'Unable to read foundation schema.';
} else {
    $requiredTables = [
        'users',
        'organization_memberships',
        'roles',
        'guardian_student_relationships',
        'production_groups',
        'channels',
        'conversations',
        'conversation_participants',
        'messages',
        'audit_logs',
        'notifications',
    ];

    foreach ($requiredTables as $table) {
        if (!preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?' . preg_quote($table, '/') . '\s*\(/i', $schema)) {
            $failures[] = 'Schema missing table: ' . $table;
        }
    }

    $requiredFragments = [
        "type ENUM('direct', 'safeguarded')",
        "participant_kind ENUM('adult', 'student', 'guardian')",
        'required_participant_is_guardian',
        'guardian_student_not_self',
        "channel ENUM('in_app', 'email', 'push')",
    ];

    foreach ($requiredFragments as $fragment) {
        if (!str_contains($schema, $fragment)) {
            $failures[] = 'Schema missing safeguard fragment: ' . $fragment;
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "CTSMD foundation checks failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "CTSMD foundation checks passed.\n";
