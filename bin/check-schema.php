<?php
declare(strict_types=1);

/**
 * CTSMD deployment preflight (CLI only, read-only).
 * Run: php bin/check-schema.php
 * Exit 0 = checked feature schema present; exit 1 = missing or inaccessible.
 * This does NOT apply migrations or report credentials.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once dirname(__DIR__).'/src/Database.php';
$required = [
    '079 casting roles' => ['production_cast_roles', 'production_cast_role_assignments'],
    '080 offer responses' => ['production_cast_offers'],
    '081 checklist assignments' => ['production_checklist_assignments'],
    '082 volunteer leads' => ['production_volunteer_leads'],
    '084 late pickup' => [
        'production_late_pickup_settings', 'late_pickup_occurrences',
        'late_pickup_occurrence_revisions', 'late_pickup_reviews',
        'late_pickup_notice_drafts', 'late_pickup_notice_revisions',
        'late_pickup_notice_recipients'
    ],
];
try {
    $db = Database::connect(dirname(__DIR__));
    $tableStmt = $db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
    $tables = array_fill_keys($tableStmt->fetchAll(PDO::FETCH_COLUMN), true);
    $colStmt = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column");
    $missing = [];
    foreach ($required as $migration => $tableNames) {
        foreach ($tableNames as $tableName) {
            if (!isset($tables[$tableName])) {
                $missing[] = $migration.': '.$tableName;
            }
        }
    }
    $colStmt->execute(['table'=>'attendance_records','column'=>'arrived_at']);
    if (!(int)$colStmt->fetchColumn()) {
        $missing[] = '083 attendance arrival time: attendance_records.arrived_at';
    }
    if ($missing) {
        fwrite(STDERR, "CTSMD schema preflight FAILED. Missing required schema:\n");
        foreach ($missing as $item) fwrite(STDERR, " - {$item}\n");
        fwrite(STDERR, "Review and apply the appropriate migrations with the established deployment procedure.\n");
        exit(1);
    }
    fwrite(STDOUT, "CTSMD schema preflight passed: migrations 079–084 feature tables and attendance arrival column exist.\n");
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, "CTSMD schema preflight FAILED: could not inspect database schema. Check connection, permissions and server logs.\n");
    exit(1);
}
