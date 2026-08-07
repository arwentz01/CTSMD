<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/FamilyDashboardService.php';
require_once __DIR__ . '/PlatformOnboardingExperience.php';

final class FamilyDashboardExperience
{
    private const ROUTES = ['/family-hub','/parent','/onboarding','/family/manage'];

    public static function handles(string $route): bool
    {
        return in_array($route, self::ROUTES, true);
    }

    public static function render(string $route, string $basePath): never
    {
        if(in_array($route,['/onboarding','/family/manage'],true))PlatformOnboardingExperience::render($route,$basePath);
        Auth::startSession();
        $db = Database::connect(dirname(__DIR__));
        $user = Auth::currentUser($db);
        if (!$user) self::redirect($basePath . '/login');

        $data = FamilyDashboardService::build($db, $user);
        self::page($route, $basePath, $user, $data);
    }

    private static function page(string $route, string $basePath, array $user, array $data): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $summary = $data['summary'];
        $children = $data['children'];
        $events = $data['events'];
        $forms = $data['forms'];
        $volunteer = $data['volunteer'];
        $notifications = $data['notifications'];
        $conflicts = $data['household_conflicts'];
        $firstName = trim((string)($user['first_name'] ?? strtok((string)$user['name'], ' ')));
        $subnav = [
            ['label'=>'Today','href'=>'/app','active'=>false],
            ['label'=>'My family','href'=>'/family-hub','active'=>true],
            ['label'=>'Manage household','href'=>'/family/manage','active'=>false],
            ['label'=>'Forms','href'=>'/forms','active'=>false],
            ['label'=>'Notifications','href'=>'/notifications','active'=>false],
        ];

        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#171419">
    <title>My family · CTSMD Connect</title>
    <link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>">
    <link rel="stylesheet" href="<?= $url('/assets/css/family-dashboard.css') ?>">
</head>
<body class="app-body">
<div class="unified-shell">
<?php AppNavigation::renderSidebar($route,$basePath,$user); ?>
<main class="unified-main">
<?php AppNavigation::renderHeader('Home','My family',$basePath,$subnav); ?>
<div class="family-dashboard">
    <section class="fd-hero">
        <div>
            <small>YOUR CTSMD HOUSEHOLD</small>
            <h2><?= $children ? 'Everything your family needs, in one place.' : 'Your family dashboard is ready.' ?></h2>
            <p><?= $children ? 'Calls, forms, volunteer commitments and updates across every active production.' : 'Add your child profiles to your household, then CTSMD staff can connect verified students to the appropriate production when ready.' ?></p>
        </div>
        <div><a class="fd-calendar-link" href="<?= $url('/family/manage') ?>">Manage household →</a> <a class="fd-calendar-link" href="<?= $url('/calendar') ?>">Open full calendar →</a></div>
    </section>

    <section class="fd-stats" aria-label="Family summary">
        <article><strong><?= (int)$summary['children'] ?></strong><span>linked student<?= (int)$summary['children']===1?'':'s' ?></span></article>
        <article><strong><?= (int)$summary['active_productions'] ?></strong><span>active production<?= (int)$summary['active_productions']===1?'':'s' ?></span></article>
        <article class="<?= $summary['open_forms'] ? 'attention' : '' ?>"><strong><?= (int)$summary['open_forms'] ?></strong><span>open form<?= (int)$summary['open_forms']===1?'':'s' ?></span></article>
        <article class="<?= $summary['conflicts'] ? 'attention' : '' ?>"><strong><?= (int)$summary['conflicts'] ?></strong><span>logistics conflict<?= (int)$summary['conflicts']===1?'':'s' ?></span></article>
        <article><strong><?= (int)$summary['volunteer_commitments'] ?></strong><span>volunteer commitment<?= (int)$summary['volunteer_commitments']===1?'':'s' ?></span></article>
        <article class="<?= $summary['unread_notifications'] ? 'attention' : '' ?>"><strong><?= (int)$summary['unread_notifications'] ?></strong><span>unread update<?= (int)$summary['unread_notifications']===1?'':'s' ?></span></article>
    </section>

    <?php if ($children): ?>
    <section class="fd-section">
        <header class="fd-section-head"><div><small>MY PEOPLE</small><h3>Children & productions</h3></div><span><?= count($children) ?> linked to <?= $e($firstName) ?></span></header>
        <div class="fd-child-grid">
            <?php foreach ($children as $child): ?>
            <article class="fd-child-card">
                <header><span class="fd-avatar"><?= $e((string)$child['initials']) ?></span><div><h4><?= $e((string)$child['name']) ?></h4><p><?= $e(ucfirst((string)$child['relationship_type'])) ?> relationship<?= !empty($child['is_primary']) ? ' · Primary' : '' ?></p></div></header>
                <div class="fd-production-list">
                    <?php if ($child['productions']): foreach ($child['productions'] as $production): ?>
                    <span><b><?= $e((string)$production['title']) ?></b><?= !empty($production['participation_role']) ? ' · '.$e((string)$production['participation_role']) : '' ?></span>
                    <?php endforeach; else: ?><span class="muted">No active production membership.</span><?php endif; ?>
                </div>
                <?php if ($child['next_event']): $next=$child['next_event']; ?>
                <div class="fd-next"><small>NEXT CALL</small><b><?= $e(date('D, M j · g:i A',strtotime((string)$next['starts_at']))) ?></b><span><?= $e((string)$next['title']) ?> · <?= $e((string)$next['production_title']) ?></span></div>
                <?php else: ?><div class="fd-next empty"><small>NEXT CALL</small><span>No upcoming calls in the next 45 days.</span></div><?php endif; ?>
                <footer><span class="<?= $child['open_form_count'] ? 'warn' : '' ?>"><?= (int)$child['open_form_count'] ?> open form<?= (int)$child['open_form_count']===1?'':'s' ?></span><span class="<?= $child['conflict_count'] ? 'warn' : '' ?>"><?= (int)$child['conflict_count'] ?> overlapping call<?= (int)$child['conflict_count']===1?'':'s' ?></span></footer>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($conflicts): ?>
    <section class="fd-section fd-conflicts">
        <header class="fd-section-head"><div><small>CHECK THE LOGISTICS</small><h3>Household conflicts</h3></div><a href="<?= $url('/calendar') ?>">Open calendar</a></header>
        <div class="fd-conflict-list">
        <?php foreach (array_slice($conflicts,0,5) as $conflict): $a=$conflict['a'];$b=$conflict['b']; ?>
            <article><i>!</i><div><b><?= $e((string)$a['child_name']) ?> and <?= $e((string)$b['child_name']) ?> have overlapping calls</b><p><?= $e(date('M j · g:i A',strtotime((string)$a['starts_at']))) ?> — <?= $e((string)$a['title']) ?> at <?= $e((string)$a['location']) ?> / <?= $e((string)$b['title']) ?> at <?= $e((string)$b['location']) ?></p></div></article>
        <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <div class="fd-main-grid">
        <section class="fd-panel fd-schedule">
            <header class="fd-section-head"><div><small>NEXT 45 DAYS</small><h3>Family schedule</h3></div><a href="<?= $url('/calendar') ?>">Full calendar</a></header>
            <?php if ($events): foreach (array_slice($events,0,10) as $event): ?>
            <article class="fd-schedule-row<?= !empty($event['has_conflict']) ? ' conflict' : '' ?>">
                <time><b><?= $e(date('M j',strtotime((string)$event['starts_at']))) ?></b><span><?= $e(date('g:i A',strtotime((string)$event['starts_at']))) ?></span></time>
                <span class="fd-avatar small"><?= $e((string)$event['child_initials']) ?></span>
                <div><b><?= $e((string)$event['title']) ?></b><span><?= $e((string)$event['child_name']) ?> · <?= $e((string)$event['production_title']) ?></span><small><?= $e((string)$event['location']) ?><?= !empty($event['group_names']) ? ' · '. $e(implode(' + ',$event['group_names'])) : '' ?></small></div>
                <?= !empty($event['has_conflict']) ? '<em>Conflict</em>' : '' ?>
            </article>
            <?php endforeach; else: ?><div class="fd-empty"><b>No upcoming family calls.</b><span>The next 45 days are clear.</span></div><?php endif; ?>
        </section>

        <aside class="fd-side-stack">
            <section class="fd-panel">
                <header class="fd-section-head"><div><small>NEEDS YOU</small><h3>Forms</h3></div><a href="<?= $url('/forms') ?>">All forms</a></header>
                <?php if ($forms): foreach (array_slice($forms,0,6) as $form): ?>
                <article class="fd-action-row <?= in_array($form['status'],['missing','due_soon'],true) ? 'urgent' : '' ?>"><span class="fd-avatar small"><?= $e((string)$form['person_initials']) ?></span><div><b><?= $e((string)$form['title']) ?></b><span><?= $e((string)$form['person_name']) ?><?= $form['production_title'] ? ' · '.$e((string)$form['production_title']) : '' ?></span><small><?= $e(ucwords(str_replace('_',' ',(string)$form['status']))) ?><?= $form['due_at'] ? ' · Due '. $e(date('M j',strtotime((string)$form['due_at']))) : '' ?></small></div></article>
                <?php endforeach; else: ?><div class="fd-empty"><b>Forms are caught up.</b><span>No open assignments for you or your linked students.</span></div><?php endif; ?>
            </section>

            <section class="fd-panel">
                <header class="fd-section-head"><div><small>YOUR COMMITMENTS</small><h3>Volunteer</h3></div><a href="<?= $url('/volunteer-shifts') ?>">Volunteer hub</a></header>
                <?php if ($volunteer): foreach (array_slice($volunteer,0,5) as $shift): ?>
                <article class="fd-simple-row"><div><b><?= $e((string)$shift['title']) ?></b><span><?= $e(date('M j · g:i A',strtotime((string)$shift['starts_at']))) ?> · <?= $e((string)$shift['location']) ?></span><small><?= $e(ucwords(str_replace('_',' ',(string)$shift['status']))) ?><?= $shift['production_title'] ? ' · '.$e((string)$shift['production_title']) : '' ?></small></div></article>
                <?php endforeach; else: ?><div class="fd-empty"><b>No upcoming volunteer commitments.</b><span>Your next 45 days have no signed-up shifts.</span></div><?php endif; ?>
            </section>
        </aside>
    </div>

    <section class="fd-section fd-updates">
        <header class="fd-section-head"><div><small>WHAT CHANGED</small><h3>Recent updates</h3></div><div><a href="<?= $url('/notifications') ?>">All notifications</a> · <a href="<?= $url('/notification-preferences') ?>">Email preferences</a></div></header>
        <div class="fd-update-grid">
        <?php if ($notifications['items']): foreach (array_slice($notifications['items'],0,6) as $note): ?>
            <a href="<?= $url((string)($note['action_path'] ?: '/notifications')) ?>" class="fd-update<?= $note['read_at']===null ? ' unread' : '' ?>"><small><?= $note['read_at']===null ? 'NEW · ' : '' ?><?= $e(strtoupper(str_replace('_',' ',(string)$note['source_type']))) ?></small><b><?= $e((string)$note['title']) ?></b><p><?= $e((string)$note['body']) ?></p><span><?= $e(date('M j · g:i A',strtotime((string)$note['created_at']))) ?></span></a>
        <?php endforeach; else: ?><div class="fd-empty"><b>No recent updates.</b><span>Production notifications will appear here.</span></div><?php endif; ?>
        </div>
    </section>
</div>
</main>
</div>
<script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script>
</body>
</html><?php
        exit;
    }

    private static function redirect(string $url): never
    {
        header('Location: '.$url,true,303);
        exit;
    }
}
