<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/PrototypeDataRepository.php';

try {
    $db = Database::connect(dirname(__DIR__));
    $data = (new PrototypeDataRepository($db))->all();

    $userId = (int)($data['user']['id'] ?? 0);
    if ($userId > 0) {
        $stmt = $db->prepare("SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name, u.initials, u.display_role AS role
                              FROM family_relationships fr
                              JOIN users u ON u.id = fr.student_user_id
                              WHERE fr.guardian_user_id = :guardian_id AND fr.status = 'active' AND u.active = 1
                              ORDER BY fr.is_primary DESC, u.last_name, u.first_name");
        $stmt->execute(['guardian_id' => $userId]);
        $data['family_context']['linked_people'] = array_map(static fn(array $row): array => [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'initials' => $row['initials'],
            'role' => $row['role'],
        ], $stmt->fetchAll());
    }

    return $data;
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');

    $message = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CTSMD Connect setup required</title><style>body{font-family:system-ui,sans-serif;background:#111;color:#fff;padding:40px;line-height:1.5}.card{max-width:760px;margin:auto;background:#1d1d1d;border:1px solid #444;border-radius:18px;padding:28px}code{background:#000;padding:2px 6px;border-radius:5px;color:#ffd15c}h1{color:#ffd15c}</style></head><body><div class="card"><h1>CTSMD Connect needs its local demo database.</h1><p>The prototype no longer falls back to hardcoded records.</p><ol><li>Create the <code>ctsmd</code> database.</li><li>Import <code>database/schema.sql</code>.</li><li>Import <code>database/seeds/001_demo.sql</code>.</li><li>Reload the page.</li></ol><p><strong>Database error:</strong> ' . $message . '</p></div></body></html>';
    exit;
}
