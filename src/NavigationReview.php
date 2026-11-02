<?php

declare(strict_types=1);

require_once __DIR__ . '/AppNavigation.php';

final class NavigationReview
{
    public static function render(string $basePath, array $data): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $user = $data['user'];

        header('Content-Type: text/html; charset=utf-8');
        ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#a6192e">
    <title>Navigation & IA · CTSMD Connect</title>
    <link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>">
</head>
<body class="app-body unified-body">
<div class="unified-shell">
    <?php AppNavigation::renderSidebar('/navigation', $basePath, $user); ?>
    <main class="unified-main">
        <?php AppNavigation::renderHeader('Navigation consolidation', 'CTSMD Connect information architecture', $basePath); ?>
        <div class="unified-page">
            <section class="nav-review-hero">
                <span>NAVIGATION CONTRACT</span>
                <h2>One app. Different responsibilities. Predictable places.</h2>
                <p>The shell stays consistent while role visibility and local section navigation change. A parent, volunteer, production manager, and safeguarding administrator should all feel like they are using the same CTSMD Connect.</p>
            </section>

            <section class="nav-principles">
                <article><b>1</b><h3>Home is personal</h3><p>Today, family actions, notifications, and immediate responsibilities converge here.</p></article>
                <article><b>2</b><h3>Production is contextual</h3><p>Schedule, calls, resources, Playbill, and production work live under the selected show.</p></article>
                <article><b>3</b><h3>Community is shared</h3><p>Channels and announcements are distinct from direct or safeguarded conversations.</p></article>
                <article><b>4</b><h3>Volunteer is readiness-first</h3><p>Eligibility and requirements come before shift signup, not after a failed action.</p></article>
                <article><b>5</b><h3>Operations is permissioned</h3><p>People, compliance, safeguarding, and organization controls appear only for authorized staff.</p></article>
            </section>

            <div class="nav-review-grid">
                <section class="nav-map-card">
                    <header><small>GLOBAL NAVIGATION</small><h3>Five destinations</h3></header>
                    <div class="nav-map-list">
                        <a href="<?= $url('/app') ?>"><span>⌂</span><div><b>Home</b><small>Today · Family · Forms · Notifications</small></div></a>
                        <a href="<?= $url('/production') ?>"><span>★</span><div><b>Production</b><small>Overview · Schedule · Resources · Playbill</small></div></a>
                        <a href="<?= $url('/channels') ?>"><span>#</span><div><b>Community</b><small>Channels · Announcements</small></div></a>
                        <a href="<?= $url('/messages') ?>"><span>✉</span><div><b>Messages</b><small>Direct and safeguarded conversations</small></div></a>
                        <a href="<?= $url('/volunteer-readiness') ?>"><span>♡</span><div><b>Volunteer</b><small>Readiness · Opportunities · Commitments</small></div></a>
                    </div>
                </section>

                <section class="nav-map-card">
                    <header><small>STAFF EXTENSION</small><h3>Operations</h3></header>
                    <p>These destinations use the same shell, but only appear when the signed-in role has operational permission.</p>
                    <div class="nav-map-list compact">
                        <a href="<?= $url('/people') ?>"><span>♟</span><div><b>People</b><small>Families · Roles · Access · Assignments</small></div></a>
                        <a href="<?= $url('/volunteers') ?>"><span>♡</span><div><b>Volunteer operations</b><small>Coverage · Compliance · Shift management</small></div></a>
                        <a href="<?= $url('/safeguarding') ?>"><span>●</span><div><b>Safeguarding</b><small>Restricted exceptions · Review · Audit</small></div></a>
                        <a href="<?= $url('/admin') ?>"><span>⚙</span><div><b>Organization settings</b><small>Roles · Configuration · Integrations</small></div></a>
                    </div>
                </section>
            </div>

            <section class="nav-patterns">
                <header><small>PAGE HIERARCHY</small><h3>Global shell → section tabs → page actions</h3></header>
                <div class="nav-pattern-demo">
                    <div class="nav-demo-global"><span>★ Production</span><span># Community</span><span>✉ Messages</span></div>
                    <div class="nav-demo-section"><b><?= $esc($data['playbills'][0]['title'] ?? 'Current production') ?></b><span class="active">Overview</span><span>Schedule</span><span>Resources</span><span>Playbill</span><span>Volunteers</span></div>
                    <div class="nav-demo-page"><div><small>PRODUCTION · SCHEDULE</small><h4>Production day</h4></div><button>Edit schedule</button></div>
                </div>
                <p class="nav-caption">A page never creates a new navigation system just because its workflow is specialized.</p>
            </section>

            <section class="nav-role-table">
                <header><small>ROLE VISIBILITY</small><h3>Same architecture, appropriate doors</h3></header>
                <div class="nav-table-wrap">
                    <table>
                        <thead><tr><th>Destination</th><th>Parent / Guardian</th><th>Student</th><th>Volunteer</th><th>Production Staff</th><th>Admin / Safety</th></tr></thead>
                        <tbody>
                            <tr><td>Home</td><td>✓</td><td>✓</td><td>✓</td><td>✓</td><td>✓</td></tr>
                            <tr><td>Production</td><td>✓ assigned</td><td>✓ assigned</td><td>✓ relevant</td><td>✓</td><td>✓</td></tr>
                            <tr><td>Community</td><td>✓ allowed</td><td>✓ allowed</td><td>✓ allowed</td><td>✓</td><td>✓</td></tr>
                            <tr><td>Messages</td><td>✓</td><td>Safeguarded only</td><td>✓</td><td>✓</td><td>✓</td></tr>
                            <tr><td>Volunteer</td><td>Optional</td><td>—</td><td>✓</td><td>✓ relevant</td><td>✓</td></tr>
                            <tr><td>People</td><td>—</td><td>—</td><td>—</td><td>Permissioned</td><td>✓</td></tr>
                            <tr><td>Safeguarding</td><td>—</td><td>—</td><td>—</td><td>Restricted</td><td>✓ restricted</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="nav-mobile-contract">
                <div><small>MOBILE CONTRACT</small><h3>Four primary destinations + More</h3><p>Phone navigation should not reproduce a ten-item desktop sidebar. The highest-frequency destinations remain one tap away, while role-specific tools move into More.</p></div>
                <div class="nav-phone"><div class="nav-phone-screen"><span>Today</span><strong>What needs your attention</strong><i></i><i></i><i></i></div><nav><b>⌂<small>Home</small></b><b>★<small>Production</small></b><b>✉<small>Messages</small></b><b>♡<small>Volunteer</small></b><b>•••<small>More</small></b></nav></div>
            </section>
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
