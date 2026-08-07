<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AccessPolicy.php';

final class DevIdentityExperience
{
    public static function render(string $basePath): never
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $db = Database::connect(dirname(__DIR__));
        if (!AccessPolicy::localIdentitySwitchEnabled()) {
            http_response_code(404);
            exit('Not found');
        }

        $_SESSION['dev_identity_csrf'] ??= bin2hex(random_bytes(24));
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = (string)($_POST['csrf_token'] ?? '');
            if (!hash_equals((string)$_SESSION['dev_identity_csrf'], $token)) {
                $_SESSION['dev_identity_flash'] = ['type' => 'error', 'message' => 'Your session token expired. Please try again.'];
                self::redirect($basePath . '/dev/identity');
            }

            $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT) ?: 0;
            $stmt = $db->prepare('SELECT id FROM users WHERE id = :id AND active = 1');
            $stmt->execute(['id' => $userId]);
            if (!$stmt->fetchColumn()) {
                $_SESSION['dev_identity_flash'] = ['type' => 'error', 'message' => 'That local test identity is unavailable.'];
                self::redirect($basePath . '/dev/identity');
            }

            $db->beginTransaction();
            try {
                $db->exec('UPDATE users SET is_demo_current_user = 0');
                $update = $db->prepare('UPDATE users SET is_demo_current_user = 1 WHERE id = :id');
                $update->execute(['id' => $userId]);
                $db->commit();
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }

            $_SESSION['dev_identity_flash'] = ['type' => 'success', 'message' => 'Local identity changed. All implemented screens will now use this person.'];
            self::redirect($basePath . '/dev/identity');
        }

        $people = $db->query("SELECT id, CONCAT(first_name, ' ', last_name) AS name, email, initials, display_role AS role, is_demo_current_user FROM users WHERE active = 1 ORDER BY last_name, first_name")->fetchAll();
        $flash = $_SESSION['dev_identity_flash'] ?? null;
        unset($_SESSION['dev_identity_flash']);
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Local identity · CTSMD Connect</title>
    <link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= $url('/assets/css/dev-identity.css') ?>">
</head>
<body class="dev-id-body">
<main class="dev-id-page">
    <header class="dev-id-head"><div><small>LOCAL DEVELOPMENT ONLY</small><h1>Who are you testing as?</h1><p>This changes the single seeded current-user marker used by CTSMD Connect. It is disabled outside <code>APP_ENV=local</code>.</p></div><a href="<?= $url('/app') ?>">Back to app →</a></header>
    <?php if ($flash): ?><div class="dev-id-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif; ?>
    <section class="dev-id-grid">
        <?php foreach ($people as $person): ?>
        <form method="post" class="dev-id-card<?= $person['is_demo_current_user'] ? ' current' : '' ?>">
            <input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['dev_identity_csrf']) ?>">
            <input type="hidden" name="user_id" value="<?= (int)$person['id'] ?>">
            <div class="dev-id-avatar"><?= $esc($person['initials']) ?></div>
            <div><small><?= $esc($person['role']) ?></small><h2><?= $esc($person['name']) ?></h2><p><?= $esc($person['email'] ?: 'No email') ?></p></div>
            <?php if ($person['is_demo_current_user']): ?><span class="dev-id-current">CURRENT</span><?php else: ?><button type="submit">Test as this person</button><?php endif; ?>
        </form>
        <?php endforeach; ?>
    </section>
    <aside class="dev-id-note"><b>Why this exists</b><p>This is not authentication. It is a local-only development harness so we can test real role-based behavior before building production sign-in, invitations, password reset, sessions, and account security.</p></aside>
</main>
</body>
</html><?php
        exit;
    }

    private static function redirect(string $url): never
    {
        header('Location: ' . $url, true, 303);
        exit;
    }
}
