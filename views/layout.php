<?php
/** @var string $content */
/** @var string $title */
/** @var string $page */
/** @var array<string, mixed> $app */
$assetBase = (string) ($app['base_path'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="CTSMD Connect — a safer, smarter way to keep our theatre community connected.">
    <title><?= htmlspecialchars($title) ?> · CTSMD Connect</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($assetBase, ENT_QUOTES, 'UTF-8') ?>/assets/css/app.css">
</head>
<body>
    <a class="skip-link" href="#main">Skip to content</a>
    <header class="site-header">
        <a class="brand" href="/" aria-label="CTSMD Connect home">
            <span class="brand-mark" aria-hidden="true">C</span>
            <span><strong>CTSMD</strong><small>Connect</small></span>
        </a>
        <nav aria-label="Main navigation">
            <a href="/" <?= $page === 'home' ? 'aria-current="page"' : '' ?>>Welcome</a>
            <a href="/admin" <?= $page === 'admin' ? 'aria-current="page"' : '' ?>>Admin preview</a>
            <a href="/login" <?= $page === 'login' ? 'aria-current="page"' : '' ?>>Sign in</a>
        </nav>
        <span class="build-badge">Build 009</span>
    </header>
    <main id="main"><?= $content ?></main>
    <footer>
        <span>Children's Theatre of Southern Maryland</span>
        <span>Community belongs backstage, too.</span>
    </footer>
</body>
</html>
