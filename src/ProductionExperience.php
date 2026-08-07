<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AppNavigation.php';

final class ProductionExperience
{
    private const ROUTES = ['/production', '/schedule', '/production/day', '/resources', '/playbills'];

    public static function handles(string $route): bool
    {
        return in_array($route, self::ROUTES, true);
    }

    public static function render(string $route, string $basePath): never
    {
        $db = Database::connect(dirname(__DIR__));
        $user = self::currentUser($db);
        $production = self::currentProduction($db);
        $schedule = self::schedule($db, $production ? (int)$production['id'] : 0);
        $announcements = self::announcements($db, $production ? (int)$production['id'] : 0);
        $channels = self::channels($db, $production ? (int)$production['id'] : 0);
        $coverage = self::coverage($db, $production ? (int)$production['id'] : 0);
        $playbill = self::playbill($db, $production ? (int)$production['id'] : 0);
        self::page($route, $basePath, $user, $production, $schedule, $announcements, $channels, $coverage, $playbill);
    }

    private static function currentUser(PDO $db): array
    {
        $row = $db->query("SELECT id, CONCAT(first_name, ' ', last_name) AS name, display_role AS role, initials FROM users WHERE is_demo_current_user = 1 LIMIT 1")->fetch();
        if (!$row) {
            throw new RuntimeException('Demo user is missing. Re-import the local seed data.');
        }
        return $row;
    }

    private static function currentProduction(PDO $db): ?array
    {
        $row = $db->query("SELECT id, title, season, status FROM productions WHERE status = 'current' ORDER BY id DESC LIMIT 1")->fetch();
        return $row ?: null;
    }

    private static function schedule(PDO $db, int $productionId): array
    {
        if ($productionId < 1) return [];
        $stmt = $db->prepare("SELECT id, title, starts_at, ends_at, family_call_at, location, visibility, item_type FROM schedule_items WHERE production_id = :production_id ORDER BY starts_at ASC");
        $stmt->execute(['production_id' => $productionId]);
        return $stmt->fetchAll();
    }

    private static function announcements(PDO $db, int $productionId): array
    {
        if ($productionId < 1) return [];
        $stmt = $db->prepare("SELECT title, body, context_label, tone, published_at FROM announcements WHERE production_id = :production_id ORDER BY pinned DESC, published_at DESC LIMIT 5");
        $stmt->execute(['production_id' => $productionId]);
        return $stmt->fetchAll();
    }

    private static function channels(PDO $db, int $productionId): array
    {
        if ($productionId < 1) return [];
        $stmt = $db->prepare("SELECT name, description, channel_type FROM channels WHERE production_id = :production_id AND archived_at IS NULL ORDER BY sort_order, name");
        $stmt->execute(['production_id' => $productionId]);
        return $stmt->fetchAll();
    }

    private static function coverage(PDO $db, int $productionId): array
    {
        if ($productionId < 1) return ['open_slots' => 0, 'shift_count' => 0];
        $stmt = $db->prepare("SELECT COUNT(vs.id) AS shift_count, COALESCE(SUM(GREATEST(vs.required_slots - COALESCE(s.active_signups,0),0)),0) AS open_slots FROM volunteer_shifts vs LEFT JOIN (SELECT shift_id, COUNT(*) AS active_signups FROM volunteer_shift_signups WHERE status IN ('signed_up','checked_in','completed') GROUP BY shift_id) s ON s.shift_id = vs.id WHERE vs.production_id = :production_id");
        $stmt->execute(['production_id' => $productionId]);
        return $stmt->fetch() ?: ['open_slots' => 0, 'shift_count' => 0];
    }

    private static function playbill(PDO $db, int $productionId): ?array
    {
        if ($productionId < 1) return null;
        $stmt = $db->prepare("SELECT pb.status, pb.public_slug, p.title, p.season FROM playbills pb JOIN productions p ON p.id = pb.production_id WHERE pb.production_id = :production_id ORDER BY FIELD(pb.status,'current','draft','archived') LIMIT 1");
        $stmt->execute(['production_id' => $productionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function isStaff(array $user): bool
    {
        $role = strtolower((string)($user['role'] ?? ''));
        return str_contains($role, 'staff') || str_contains($role, 'manager') || str_contains($role, 'admin') || str_contains($role, 'director');
    }

    private static function page(string $route, string $basePath, array $user, ?array $production, array $schedule, array $announcements, array $channels, array $coverage, ?array $playbill): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $title = match ($route) {
            '/production' => 'Production home',
            '/schedule' => 'Schedule',
            '/production/day' => 'Production day',
            '/resources' => 'Resources',
            '/playbills' => 'Playbill',
            default => 'Production',
        };
        $subnav = [
            ['label' => 'Overview', 'href' => '/production', 'active' => $route === '/production'],
            ['label' => 'Schedule', 'href' => '/schedule', 'active' => in_array($route, ['/schedule','/production/day'], true)],
            ['label' => 'Resources', 'href' => '/resources', 'active' => $route === '/resources'],
            ['label' => 'Playbill', 'href' => '/playbills', 'active' => $route === '/playbills'],
        ];
        $next = $schedule[0] ?? null;
        $staff = self::isStaff($user);

        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#a6192e">
<title><?= $esc($title) ?> · CTSMD Connect</title>
<link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>">
<link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>">
<link rel="stylesheet" href="<?= $url('/assets/css/production-implementation.css') ?>">
</head>
<body class="app-body">
<div class="unified-shell">
<?php AppNavigation::renderSidebar($route, $basePath, $user); ?>
<main class="unified-main">
<?php AppNavigation::renderHeader('Production', $title, $basePath, $subnav); ?>
<div class="prod-page">
<?php if (!$production): ?>
<section class="prod-empty"><b>No current production</b><p>When a production is marked current, its schedule and production workspace will appear here.</p></section>
<?php elseif ($route === '/production'): ?>
<section class="prod-hero"><div><small>CURRENT PRODUCTION</small><h2><?= $esc($production['title']) ?></h2><p><?= $esc((string)$production['season']) ?> · One home for calls, schedule, communication, volunteer coverage and Playbill access.</p></div><span><?= $esc(ucfirst($production['status'])) ?></span></section>
<div class="prod-overview-grid">
<section class="prod-panel dark"><small>NEXT UP</small><?php if ($next): ?><h3><?= $esc($next['title']) ?></h3><p><?= $esc(date('l, M j · g:i A', strtotime($next['starts_at']))) ?></p><p><?= $esc($next['location']) ?><?= $next['family_call_at'] ? ' · Family call ' . $esc(date('g:i A', strtotime($next['family_call_at']))) : '' ?></p><a href="<?= $url('/production/day') ?>">Open production day →</a><?php else: ?><h3>Nothing scheduled</h3><p>The current production has no schedule items.</p><?php endif; ?></section>
<section class="prod-panel"><small>PRODUCTION PULSE</small><div class="prod-kpis"><span><b><?= count($schedule) ?></b><small>schedule items</small></span><span><b><?= (int)$coverage['open_slots'] ?></b><small>open volunteer slots</small></span><span><b><?= count($announcements) ?></b><small>recent updates</small></span></div></section>
</div>
<div class="prod-module-grid"><a href="<?= $url('/schedule') ?>"><b>Schedule</b><span>Rehearsals, performances, call times and locations.</span></a><a href="<?= $url('/resources') ?>"><b>Resources</b><span>Production information grouped by purpose rather than buried in posts.</span></a><a href="<?= $url('/channels') ?>"><b>Community</b><span><?= count($channels) ?> active production channel<?= count($channels) === 1 ? '' : 's' ?>.</span></a><a href="<?= $url('/volunteer-shifts') ?>"><b>Volunteer coverage</b><span><?= (int)$coverage['open_slots'] ?> open slot<?= (int)$coverage['open_slots'] === 1 ? '' : 's' ?> across <?= (int)$coverage['shift_count'] ?> shifts.</span></a><a href="<?= $url('/forms') ?>"><b>Forms</b><span>Family and volunteer requirements linked from the production context.</span></a><a href="<?= $url('/playbills') ?>"><b>Playbill</b><span><?= $playbill ? $esc(ucfirst($playbill['status'])) : 'Not available' ?>.</span></a></div>

<?php elseif ($route === '/schedule'): ?>
<section class="prod-heading"><div><small><?= $esc(strtoupper($production['title'])) ?></small><h2>Schedule</h2><p>A single source of truth for production activity. Visibility is stored with each schedule item.</p></div><?php if ($staff): ?><span class="prod-permission">Staff editing available in a later write pass</span><?php endif; ?></section>
<div class="prod-schedule-list"><?php if (!$schedule): ?><div class="prod-empty"><b>No schedule items</b><p>This production does not have anything scheduled yet.</p></div><?php else: foreach ($schedule as $item): ?><article><div class="prod-date"><b><?= $esc(date('M', strtotime($item['starts_at']))) ?></b><span><?= $esc(date('j', strtotime($item['starts_at']))) ?></span></div><div><small><?= $esc(strtoupper($item['item_type'])) ?> · <?= $esc(strtoupper($item['visibility'])) ?></small><h3><?= $esc($item['title']) ?></h3><p><?= $esc(date('g:i A', strtotime($item['starts_at']))) ?><?= $item['ends_at'] ? '–' . $esc(date('g:i A', strtotime($item['ends_at']))) : '' ?> · <?= $esc($item['location']) ?></p><?php if ($item['family_call_at']): ?><span>Family call <?= $esc(date('g:i A', strtotime($item['family_call_at']))) ?></span><?php endif; ?></div></article><?php endforeach; endif; ?></div>

<?php elseif ($route === '/production/day'): ?>
<section class="prod-heading"><div><small><?= $esc(strtoupper($production['title'])) ?></small><h2>Production day</h2><p>A denser operational view of the next scheduled production activity.</p></div><a href="<?= $url('/schedule') ?>">Full schedule →</a></section>
<?php if ($next): ?><div class="prod-day-grid"><section class="prod-panel"><small>NEXT ACTIVITY</small><h3><?= $esc($next['title']) ?></h3><div class="prod-day-detail"><span><b>Start</b><?= $esc(date('l, M j · g:i A', strtotime($next['starts_at']))) ?></span><span><b>End</b><?= $next['ends_at'] ? $esc(date('g:i A', strtotime($next['ends_at']))) : 'Not specified' ?></span><span><b>Location</b><?= $esc($next['location']) ?></span><span><b>Visibility</b><?= $esc(ucfirst($next['visibility'])) ?></span></div></section><aside class="prod-panel"><small>FAMILY VIEW</small><h3><?= $next['family_call_at'] ? $esc(date('g:i A', strtotime($next['family_call_at']))) : 'No family call set' ?></h3><p><?= $next['family_call_at'] ? 'The family-facing call time is stored separately from the activity start time.' : 'This item does not currently include a separate family call.' ?></p></aside></div><?php else: ?><section class="prod-empty"><b>No upcoming activity</b><p>The production schedule is currently empty.</p></section><?php endif; ?>

<?php elseif ($route === '/resources'): ?>
<section class="prod-heading"><div><small><?= $esc(strtoupper($production['title'])) ?></small><h2>Production resources</h2><p>For now, this hub only links to information that actually exists in CTSMD Connect. We will add a document/resource model when we are ready to persist files and links.</p></div></section>
<div class="prod-resource-grid"><a href="<?= $url('/schedule') ?>"><i>◷</i><small>SCHEDULE</small><h3>Calls & dates</h3><p>Current rehearsal, performance and production timing.</p></a><a href="<?= $url('/channels') ?>"><i>#</i><small>COMMUNITY</small><h3>Production channels</h3><p><?= count($channels) ?> active channel<?= count($channels) === 1 ? '' : 's' ?> tied to this production.</p></a><a href="<?= $url('/playbills') ?>"><i>▤</i><small>PLAYBILL</small><h3>Digital Playbill</h3><p><?= $playbill ? 'Current Playbill record is available.' : 'No Playbill is currently attached.' ?></p></a><a href="<?= $url('/volunteer-shifts') ?>"><i>♡</i><small>VOLUNTEER</small><h3>Coverage</h3><p><?= (int)$coverage['open_slots'] ?> unfilled volunteer slot<?= (int)$coverage['open_slots'] === 1 ? '' : 's' ?>.</p></a><a href="<?= $url('/forms') ?>"><i>✓</i><small>FORMS</small><h3>Requirements</h3><p>Family and volunteer forms relevant to participation.</p></a><a href="<?= $url('/messages') ?>"><i>✉</i><small>MESSAGES</small><h3>Direct communication</h3><p>Protected conversations remain separate from broadcast information.</p></a></div>

<?php else: ?>
<section class="prod-playbill"><small>DIGITAL PLAYBILL</small><h2><?= $esc($production['title']) ?></h2><p><?= $esc((string)$production['season']) ?></p><?php if ($playbill): ?><span class="prod-playbill-status"><?= $esc(ucfirst($playbill['status'])) ?></span><div class="prod-ticket"><b>CTSMD</b><span><?= $esc($production['title']) ?></span><small>Children's Theatre of Southern Maryland</small></div><?php else: ?><div class="prod-empty"><b>No Playbill record</b><p>A Playbill has not been created for this production.</p></div><?php endif; ?></section>
<?php endif; ?>
</div>
</main>
</div>
<script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script>
</body>
</html><?php
        exit;
    }
}
