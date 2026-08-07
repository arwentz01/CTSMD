<?php

declare(strict_types=1);

require_once __DIR__ . '/AppNavigation.php';

final class HomeExperience
{
    private const ROUTES = ['/app', '/family-hub', '/notifications'];

    public static function handles(string $route): bool
    {
        return in_array($route, self::ROUTES, true);
    }

    public static function render(string $route, string $basePath, array $data): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $user = $data['user'];
        $openForms = array_values(array_filter($data['forms'], static fn(array $form): bool => $form['status'] !== 'Completed'));
        $eligibleShifts = array_values(array_filter($data['shifts'], static fn(array $shift): bool => $shift['status'] === 'eligible'));
        $linkedPeople = $data['family_context']['linked_people'] ?? [];
        $announcements = $data['announcements'];
        $schedule = $data['schedule'];

        $titles = [
            '/app' => ['Home', 'Today'],
            '/family-hub' => ['Home', 'My family'],
            '/notifications' => ['Home', 'Notifications'],
        ];
        [$eyebrow, $title] = $titles[$route];

        $subnav = [
            ['label' => 'Today', 'href' => '/app', 'active' => $route === '/app'],
            ['label' => 'My family', 'href' => '/family-hub', 'active' => $route === '/family-hub'],
            ['label' => 'Forms', 'href' => '/forms', 'active' => false],
            ['label' => 'Notifications', 'href' => '/notifications', 'active' => $route === '/notifications'],
        ];

        header('Content-Type: text/html; charset=utf-8');
        ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#171419">
    <title><?= $esc($title) ?> · CTSMD Connect</title>
    <link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>">
    <link rel="stylesheet" href="<?= $url('/assets/css/home-experience.css') ?>">
</head>
<body class="app-body implementation-body">
<div class="unified-shell">
    <?php AppNavigation::renderSidebar($route, $basePath, $user); ?>
    <main class="unified-main">
        <?php AppNavigation::renderHeader($eyebrow, $title, $basePath, $subnav); ?>
        <div class="home-page">

        <?php if ($route === '/app'): ?>
            <section class="home-hero">
                <div><span>FRIDAY · AUGUST 7</span><h2>Good morning, <?= $esc(explode(' ', $user['name'])[0]) ?>.</h2><p>Here is what matters for your theatre day.</p></div>
                <a class="button" href="<?= $url('/family-hub') ?>">View my family</a>
            </section>

            <div class="home-priority-grid">
                <section class="home-priority-stack">
                    <header class="home-section-head"><div><span>NEEDS YOU</span><h3>Action center</h3></div><b><?= count($openForms) + (count($eligibleShifts) ? 1 : 0) ?></b></header>
                    <?php if ($openForms): ?>
                        <?php foreach (array_slice($openForms, 0, 2) as $form): ?>
                        <article class="home-action <?= $form['status'] === 'Missing' ? 'urgent' : '' ?>"><i>!</i><div><small>FORM · <?= $esc(strtoupper($form['status'])) ?></small><b><?= $esc($form['title']) ?></b><span>Due <?= $esc($form['due']) ?></span></div><a href="<?= $url('/forms') ?>">Review →</a></article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if ($eligibleShifts): $shift = $eligibleShifts[0]; ?>
                        <article class="home-action"><i>♡</i><div><small>VOLUNTEER</small><b><?= $esc($shift['title']) ?></b><span><?= $esc($shift['when']) ?> · <?= $esc($shift['slots']) ?></span></div><a href="<?= $url('/volunteer-readiness') ?>">View →</a></article>
                    <?php endif; ?>
                    <?php if (!$openForms && !$eligibleShifts): ?><div class="home-empty"><b>You are caught up.</b><span>No family actions need attention right now.</span></div><?php endif; ?>
                </section>

                <aside class="home-today-card">
                    <span>NEXT UP</span>
                    <?php if ($schedule): $next = $schedule[0]; ?>
                    <strong><?= $esc($next['time']) ?></strong><h3><?= $esc($next['title']) ?></h3><p><?= $esc($next['detail']) ?></p><a href="<?= $url('/schedule') ?>">Full schedule →</a>
                    <?php else: ?><h3>No upcoming activity</h3><p>The schedule is clear.</p><?php endif; ?>
                </aside>
            </div>

            <div class="home-content-grid">
                <section class="home-card"><header class="home-section-head"><div><span>WHAT CHANGED</span><h3>Latest updates</h3></div><a href="<?= $url('/notifications') ?>">All notifications</a></header><?php foreach (array_slice($announcements, 0, 3) as $announcement): ?><article class="home-update"><div class="home-update-marker <?= $esc($announcement['tone']) ?>"></div><div><b><?= $esc($announcement['title']) ?></b><p><?= $esc($announcement['body']) ?></p><small><?= $esc($announcement['meta']) ?></small></div></article><?php endforeach; ?></section>
                <section class="home-card"><header class="home-section-head"><div><span>MY PEOPLE</span><h3>Family at CTSMD</h3></div><a href="<?= $url('/family-hub') ?>">Open family</a></header><?php if ($linkedPeople): ?><?php foreach ($linkedPeople as $person): ?><article class="home-person"><i><?= $esc($person['initials']) ?></i><div><b><?= $esc($person['name']) ?></b><span><?= $esc($person['role']) ?></span></div><small>Linked</small></article><?php endforeach; ?><?php else: ?><div class="home-empty"><b>No linked family members yet.</b><span>Assigned relationships will appear here automatically.</span></div><?php endif; ?></section>
            </div>

        <?php elseif ($route === '/family-hub'): ?>
            <section class="home-hero compact"><div><span>MY FAMILY</span><h2>Your theatre household.</h2><p>People, requirements and upcoming activity without admin clutter.</p></div></section>
            <div class="family-summary-grid"><article><strong><?= count($linkedPeople) ?></strong><span>linked students</span></article><article><strong><?= count($openForms) ?></strong><span>open forms</span></article><article><strong><?= count($eligibleShifts) ?></strong><span>eligible volunteer shifts</span></article></div>
            <div class="home-content-grid">
                <section class="home-card"><header class="home-section-head"><div><span>PEOPLE</span><h3>Linked family</h3></div></header><?php if ($linkedPeople): ?><?php foreach ($linkedPeople as $person): ?><article class="family-person-card"><i><?= $esc($person['initials']) ?></i><div><b><?= $esc($person['name']) ?></b><span><?= $esc($person['role']) ?></span><small>Guardian visibility is applied automatically where required.</small></div><button type="button">View profile</button></article><?php endforeach; ?><?php else: ?><div class="home-empty"><b>No family relationship is assigned.</b><span>This is an intentional empty state, not placeholder data.</span></div><?php endif; ?></section>
                <section class="home-card"><header class="home-section-head"><div><span>UPCOMING</span><h3>Family schedule</h3></div><a href="<?= $url('/schedule') ?>">Full schedule</a></header><?php foreach (array_slice($schedule, 0, 4) as $item): ?><article class="family-schedule-row"><strong><?= $esc($item['time']) ?></strong><div><b><?= $esc($item['title']) ?></b><span><?= $esc($item['detail']) ?></span></div><small><?= $esc($item['tag']) ?></small></article><?php endforeach; ?></section>
            </div>

        <?php elseif ($route === '/notifications'): ?>
            <section class="home-hero compact"><div><span>NOTIFICATION CENTER</span><h2>What needs action, and what simply changed.</h2><p>CTSMD should not train families to ignore alerts by treating every post as urgent.</p></div></section>
            <div class="notification-columns">
                <section class="home-card"><header class="home-section-head"><div><span>ACTION REQUIRED</span><h3>Needs you</h3></div><b><?= count($openForms) ?></b></header><?php if ($openForms): ?><?php foreach ($openForms as $form): ?><article class="notification-row <?= $form['status'] === 'Missing' ? 'urgent' : '' ?>"><i>!</i><div><b><?= $esc($form['title']) ?></b><span><?= $esc($form['status']) ?> · Due <?= $esc($form['due']) ?></span></div><a href="<?= $url('/forms') ?>">Review</a></article><?php endforeach; ?><?php else: ?><div class="home-empty"><b>Nothing needs action.</b><span>You are caught up on assigned forms.</span></div><?php endif; ?></section>
                <section class="home-card"><header class="home-section-head"><div><span>UPDATES</span><h3>What changed</h3></div></header><?php foreach ($announcements as $announcement): ?><article class="notification-row"><i>#</i><div><b><?= $esc($announcement['title']) ?></b><span><?= $esc($announcement['meta']) ?></span><p><?= $esc($announcement['body']) ?></p></div><a href="<?= $url('/channels') ?>">Open</a></article><?php endforeach; ?></section>
            </div>
        <?php endif; ?>

        </div>
    </main>
</div>
<script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script>
</body>
</html>
<?php
        exit;
    }
}
