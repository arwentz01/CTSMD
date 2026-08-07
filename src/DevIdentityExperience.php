<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/Auth.php';

final class DevIdentityExperience
{
    public static function render(string $basePath): never
    {
        Auth::startSession();
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
            try {
                Auth::loginAsLocalUser($db,(int)$userId);
                $_SESSION['dev_identity_flash'] = ['type' => 'success', 'message' => 'Local authenticated identity changed for this browser session.'];
            } catch (RuntimeException $e) {
                $_SESSION['dev_identity_flash'] = ['type' => 'error', 'message' => $e->getMessage()];
            }
            self::redirect($basePath . '/dev/identity');
        }

        $people = $db->query("SELECT id, CONCAT(first_name, ' ', last_name) AS name, email, initials, display_role AS role FROM users WHERE active = 1 ORDER BY last_name, first_name")->fetchAll();
        $currentId=Auth::userId();
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
    <header class="dev-id-head"><div><small>LOCAL DEVELOPMENT ONLY</small><h1>Who are you testing as?</h1><p>This creates a browser-session login for the selected seeded person. It does not change another browser's identity.</p></div><a href="<?= $url('/app') ?>">Back to app →</a></header>
    <?php if ($flash): ?><div class="dev-id-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif; ?>
    <section class="dev-id-grid">
        <?php foreach ($people as $person): $current=$currentId===(int)$person['id']; ?>
        <form method="post" class="dev-id-card<?= $current ? ' current' : '' ?>">
            <input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['dev_identity_csrf']) ?>">
            <input type="hidden" name="user_id" value="<?= (int)$person['id'] ?>">
            <div class="dev-id-avatar"><?= $esc($person['initials']) ?></div>
            <div><small><?= $esc($person['role']) ?></small><h2><?= $esc($person['name']) ?></h2><p><?= $esc($person['email'] ?: 'No email') ?></p></div>
            <?php if ($current): ?><span class="dev-id-current">CURRENT SESSION</span><?php else: ?><button type="submit">Test as this person</button><?php endif; ?>
        </form>
        <?php endforeach; ?>
    </section>
    <aside class="dev-id-note"><b>Local test shortcut</b><p>Production users sign in through CTSMD authentication. This page remains local-only so development can switch identities quickly while exercising the same session-based current-user path.</p></aside>
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
