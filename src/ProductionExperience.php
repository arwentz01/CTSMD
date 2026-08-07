<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/AccessPolicy.php';

final class ProductionExperience
{
    private const ROUTES = ['/production', '/schedule', '/production/day', '/production/edit', '/resources', '/playbills'];

    public static function handles(string $route): bool
    {
        return in_array($route, self::ROUTES, true);
    }

    public static function render(string $route, string $basePath): never
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $db = Database::connect(dirname(__DIR__));
        $user = self::currentUser($db);
        $_SESSION['production_csrf'] ??= bin2hex(random_bytes(24));

        if ($route === '/production/edit' && !AccessPolicy::canManageProduction($user)) {
            self::forbidden($basePath, $user);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $route === '/production/edit') {
            self::handleScheduleEdit($db, $user, $basePath);
        }

        $production = self::currentProduction($db);
        $productionId = $production ? (int)$production['id'] : 0;
        $schedule = self::schedule($db, $productionId);
        $announcements = self::announcements($db, $productionId);
        $channels = self::channels($db, $productionId);
        $coverage = self::coverage($db, $productionId);
        $playbill = self::playbill($db, $productionId);
        $notices = self::changeNotices($db, $productionId);
        $editItem = null;

        if ($route === '/production/edit') {
            $itemId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
            $editItem = self::scheduleItem($db, $productionId, (int)$itemId);
        }

        self::page($route, $basePath, $user, $production, $schedule, $announcements, $channels, $coverage, $playbill, $notices, $editItem, $db);
    }

    private static function handleScheduleEdit(PDO $db, array $user, string $basePath): never
    {
        if (!AccessPolicy::canManageProduction($user)) {
            self::forbidden($basePath, $user);
        }

        $token = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals((string)($_SESSION['production_csrf'] ?? ''), $token)) {
            self::flash('error', 'Your session token expired. Please try again.');
            self::redirect($basePath . '/schedule');
        }

        $itemId = filter_input(INPUT_POST, 'schedule_item_id', FILTER_VALIDATE_INT) ?: 0;
        try {
            self::saveScheduleChange($db, $user, (int)$itemId, $_POST);
            self::flash('success', 'Schedule updated and a communication draft was created for the affected audience.');
        } catch (RuntimeException $e) {
            self::flash('error', $e->getMessage());
            self::redirect($basePath . '/production/edit?id=' . (int)$itemId);
        }

        self::redirect($basePath . '/schedule');
    }

    private static function saveScheduleChange(PDO $db, array $user, int $itemId, array $input): void
    {
        if ($itemId < 1) {
            throw new RuntimeException('That schedule item could not be found.');
        }

        $title = trim((string)($input['title'] ?? ''));
        $location = trim((string)($input['location'] ?? ''));
        $visibility = (string)($input['visibility'] ?? 'all');
        $itemType = trim((string)($input['item_type'] ?? 'activity'));
        $startsAt = self::parseLocalDateTime((string)($input['starts_at'] ?? ''), 'Start time');
        $endsAt = self::parseOptionalLocalDateTime((string)($input['ends_at'] ?? ''), 'End time');
        $familyCallAt = self::parseOptionalLocalDateTime((string)($input['family_call_at'] ?? ''), 'Family call');

        if ($title === '' || mb_strlen($title) > 190) {
            throw new RuntimeException('Enter a schedule title no longer than 190 characters.');
        }
        if ($location === '' || mb_strlen($location) > 190) {
            throw new RuntimeException('Enter a location no longer than 190 characters.');
        }
        if ($itemType === '' || mb_strlen($itemType) > 80) {
            throw new RuntimeException('Enter a valid activity type.');
        }
        if (!in_array($visibility, ['family', 'staff', 'all'], true)) {
            throw new RuntimeException('Choose a valid audience.');
        }
        if ($endsAt !== null && $endsAt <= $startsAt) {
            throw new RuntimeException('The end time must be after the start time.');
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT id, production_id, title, starts_at, ends_at, family_call_at, location, visibility, item_type FROM schedule_items WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $itemId]);
            $before = $stmt->fetch();
            if (!$before) {
                throw new RuntimeException('That schedule item no longer exists.');
            }

            $after = [
                'title' => $title,
                'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                'ends_at' => $endsAt?->format('Y-m-d H:i:s'),
                'family_call_at' => $familyCallAt?->format('Y-m-d H:i:s'),
                'location' => $location,
                'visibility' => $visibility,
                'item_type' => $itemType,
            ];

            $changed = false;
            foreach ($after as $key => $value) {
                if (($before[$key] ?? null) !== $value) {
                    $changed = true;
                    break;
                }
            }
            if (!$changed) {
                throw new RuntimeException('Nothing changed, so no update was saved.');
            }

            $update = $db->prepare('UPDATE schedule_items SET title = :title, starts_at = :starts_at, ends_at = :ends_at, family_call_at = :family_call_at, location = :location, visibility = :visibility, item_type = :item_type WHERE id = :id');
            $update->execute($after + ['id' => $itemId]);

            $audience = self::audienceMembers($db, (int)$before['production_id'], $visibility);
            $subject = 'Schedule update · ' . $title;
            $body = self::communicationCopy($after);

            $notice = $db->prepare("INSERT INTO schedule_change_notices (schedule_item_id, production_id, created_by_user_id, audience_scope, audience_count, subject, body, status) VALUES (:item, :production, :actor, :scope, :audience_count, :subject, :body, 'draft')");
            $notice->execute([
                'item' => $itemId,
                'production' => (int)$before['production_id'],
                'actor' => (int)$user['id'],
                'scope' => $visibility,
                'audience_count' => count($audience),
                'subject' => $subject,
                'body' => $body,
            ]);
            $noticeId = (int)$db->lastInsertId();

            $audit = $db->prepare('INSERT INTO audit_events (actor_user_id, event_type, subject_type, subject_id, summary, metadata_json) VALUES (:actor, :event_type, :subject_type, :subject_id, :summary, :metadata)');
            $audit->execute([
                'actor' => (int)$user['id'],
                'event_type' => 'schedule.updated',
                'subject_type' => 'schedule_item',
                'subject_id' => $itemId,
                'summary' => 'Updated schedule item and created communication draft.',
                'metadata' => json_encode([
                    'before' => array_intersect_key($before, $after),
                    'after' => $after,
                    'notice_id' => $noticeId,
                    'audience_scope' => $visibility,
                    'audience_user_ids' => array_map(static fn(array $row): int => (int)$row['id'], $audience),
                ], JSON_THROW_ON_ERROR),
            ]);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('The schedule change could not be saved.');
        }
    }

    private static function parseLocalDateTime(string $value, string $label): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', trim($value));
        if (!$date) {
            throw new RuntimeException($label . ' is required.');
        }
        return $date;
    }

    private static function parseOptionalLocalDateTime(string $value, string $label): ?DateTimeImmutable
    {
        if (trim($value) === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', trim($value));
        if (!$date) {
            throw new RuntimeException($label . ' is not a valid date and time.');
        }
        return $date;
    }

    private static function communicationCopy(array $item): string
    {
        $start = new DateTimeImmutable($item['starts_at']);
        $parts = [
            $item['title'] . ' has been updated.',
            $start->format('l, F j \a\t g:i A') . ' at ' . $item['location'] . '.',
        ];
        if (!empty($item['family_call_at'])) {
            $parts[] = 'Family call: ' . (new DateTimeImmutable($item['family_call_at']))->format('g:i A') . '.';
        }
        $parts[] = 'Please review the current production schedule in CTSMD Connect for the latest details.';
        return implode(' ', $parts);
    }

    private static function audienceMembers(PDO $db, int $productionId, string $visibility): array
    {
        $types = match ($visibility) {
            'family' => ['student', 'guardian'],
            'staff' => ['staff'],
            default => ['student', 'guardian', 'staff'],
        };
        $placeholders = implode(',', array_fill(0, count($types), '?'));
        $sql = "SELECT DISTINCT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name, pm.audience_type FROM production_memberships pm JOIN users u ON u.id = pm.user_id WHERE pm.production_id = ? AND pm.status = 'active' AND u.active = 1 AND pm.audience_type IN ($placeholders) ORDER BY u.last_name, u.first_name";
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge([$productionId], $types));
        return $stmt->fetchAll();
    }

    private static function currentUser(PDO $db): array
    {
        $row = $db->query("SELECT id, CONCAT(first_name, ' ', last_name) AS name, display_role AS role, initials FROM users WHERE is_demo_current_user = 1 AND active = 1 LIMIT 1")->fetch();
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

    private static function scheduleItem(PDO $db, int $productionId, int $itemId): ?array
    {
        if ($productionId < 1 || $itemId < 1) return null;
        $stmt = $db->prepare('SELECT id, production_id, title, starts_at, ends_at, family_call_at, location, visibility, item_type FROM schedule_items WHERE production_id = :production_id AND id = :id LIMIT 1');
        $stmt->execute(['production_id' => $productionId, 'id' => $itemId]);
        return $stmt->fetch() ?: null;
    }

    private static function changeNotices(PDO $db, int $productionId): array
    {
        if ($productionId < 1) return [];
        $stmt = $db->prepare("SELECT scn.id, scn.schedule_item_id, scn.audience_scope, scn.audience_count, scn.subject, scn.body, scn.status, scn.created_at, CONCAT(u.first_name, ' ', u.last_name) AS creator FROM schedule_change_notices scn LEFT JOIN users u ON u.id = scn.created_by_user_id WHERE scn.production_id = :production_id ORDER BY scn.created_at DESC, scn.id DESC LIMIT 8");
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

    private static function page(string $route, string $basePath, array $user, ?array $production, array $schedule, array $announcements, array $channels, array $coverage, ?array $playbill, array $notices, ?array $editItem, PDO $db): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $staff = AccessPolicy::canManageProduction($user);
        $flash = $_SESSION['production_flash'] ?? null;
        unset($_SESSION['production_flash']);
        $title = match ($route) {
            '/production' => 'Production home',
            '/schedule' => 'Schedule',
            '/production/day' => 'Production day',
            '/production/edit' => 'Edit schedule item',
            '/resources' => 'Resources',
            '/playbills' => 'Playbill',
            default => 'Production',
        };
        $subnav = [
            ['label' => 'Overview', 'href' => '/production', 'active' => $route === '/production'],
            ['label' => 'Schedule', 'href' => '/schedule', 'active' => in_array($route, ['/schedule','/production/day','/production/edit'], true)],
            ['label' => 'Resources', 'href' => '/resources', 'active' => $route === '/resources'],
            ['label' => 'Playbill', 'href' => '/playbills', 'active' => $route === '/playbills'],
        ];
        $next = $schedule[0] ?? null;

        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#a6192e">
<title><?= $esc($title) ?> · CTSMD Connect</title>
<link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/production-implementation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/production-edit.css') ?>">
</head>
<body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar($route, $basePath, $user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Production', $title, $basePath, $subnav); ?><div class="prod-page">
<?php if ($flash): ?><div class="prod-edit-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif; ?>
<?php if (!$production): ?>
<section class="prod-empty"><b>No current production</b><p>When a production is marked current, its workspace will appear here.</p></section>
<?php elseif ($route === '/production'): ?>
<section class="prod-hero"><div><small>CURRENT PRODUCTION</small><h2><?= $esc($production['title']) ?></h2><p><?= $esc((string)$production['season']) ?> · One home for calls, schedule, communication, volunteer coverage and Playbill access.</p></div><span><?= $esc(ucfirst($production['status'])) ?></span></section>
<div class="prod-overview-grid"><section class="prod-panel dark"><small>NEXT UP</small><?php if ($next): ?><h3><?= $esc($next['title']) ?></h3><p><?= $esc(date('l, M j · g:i A', strtotime($next['starts_at']))) ?></p><p><?= $esc($next['location']) ?><?= $next['family_call_at'] ? ' · Family call ' . $esc(date('g:i A', strtotime($next['family_call_at']))) : '' ?></p><a href="<?= $url('/production/day') ?>">Open production day →</a><?php else: ?><h3>Nothing scheduled</h3><p>The current production has no schedule items.</p><?php endif; ?></section><section class="prod-panel"><small>PRODUCTION PULSE</small><div class="prod-kpis"><span><b><?= count($schedule) ?></b><small>schedule items</small></span><span><b><?= (int)$coverage['open_slots'] ?></b><small>open volunteer slots</small></span><span><b><?= count($notices) ?></b><small>change drafts</small></span></div></section></div>
<div class="prod-module-grid"><a href="<?= $url('/schedule') ?>"><b>Schedule</b><span>Rehearsals, performances, call times and locations.</span></a><a href="<?= $url('/resources') ?>"><b>Resources</b><span>Production information grouped by purpose rather than buried in posts.</span></a><a href="<?= $url('/channels') ?>"><b>Community</b><span><?= count($channels) ?> active production channel<?= count($channels) === 1 ? '' : 's' ?>.</span></a><a href="<?= $url('/volunteer-shifts') ?>"><b>Volunteer coverage</b><span><?= (int)$coverage['open_slots'] ?> open slots.</span></a><a href="<?= $url('/forms') ?>"><b>Forms</b><span>Family and volunteer requirements.</span></a><a href="<?= $url('/playbills') ?>"><b>Playbill</b><span><?= $playbill ? $esc(ucfirst($playbill['status'])) : 'Not available' ?>.</span></a></div>

<?php elseif ($route === '/schedule'): ?>
<section class="prod-heading"><div><small><?= $esc(strtoupper($production['title'])) ?></small><h2>Schedule</h2><p>A single source of truth for production activity. Staff edits now create an auditable communication draft for the affected production audience.</p></div><?php if ($staff): ?><span class="prod-permission">Staff editing enabled</span><?php endif; ?></section>
<div class="prod-schedule-list"><?php if (!$schedule): ?><div class="prod-empty"><b>No schedule items</b></div><?php else: foreach ($schedule as $item): ?><article><div class="prod-date"><b><?= $esc(date('M', strtotime($item['starts_at']))) ?></b><span><?= $esc(date('j', strtotime($item['starts_at']))) ?></span></div><div class="prod-schedule-copy"><small><?= $esc(strtoupper($item['item_type'])) ?> · <?= $esc(strtoupper($item['visibility'])) ?></small><h3><?= $esc($item['title']) ?></h3><p><?= $esc(date('g:i A', strtotime($item['starts_at']))) ?><?= $item['ends_at'] ? '–' . $esc(date('g:i A', strtotime($item['ends_at']))) : '' ?> · <?= $esc($item['location']) ?></p><?php if ($item['family_call_at']): ?><span>Family call <?= $esc(date('g:i A', strtotime($item['family_call_at']))) ?></span><?php endif; ?></div><?php if ($staff): ?><a class="prod-edit-link" href="<?= $url('/production/edit?id=' . (int)$item['id']) ?>">Edit →</a><?php endif; ?></article><?php endforeach; endif; ?></div>
<?php if ($staff): ?><section class="prod-drafts"><header><div><small>CHANGE COMMUNICATION</small><h3>Drafts awaiting delivery</h3></div><span><?= count($notices) ?> recent</span></header><?php if (!$notices): ?><div class="prod-empty compact"><b>No change drafts yet</b><p>Edit a schedule item and CTSMD will prepare the affected-audience message here.</p></div><?php else: foreach ($notices as $notice): ?><article><div><small><?= $esc(strtoupper($notice['audience_scope'])) ?> · <?= (int)$notice['audience_count'] ?> PEOPLE · <?= $esc(strtoupper($notice['status'])) ?></small><h4><?= $esc($notice['subject']) ?></h4><p><?= $esc($notice['body']) ?></p></div><time><?= $esc(date('M j · g:i A', strtotime($notice['created_at']))) ?></time></article><?php endforeach; endif; ?></section><?php endif; ?>

<?php elseif ($route === '/production/edit'): ?>
<?php if (!$editItem): ?><section class="prod-empty"><b>Schedule item not found</b><p>It may have been removed or belong to another production.</p><a class="button" href="<?= $url('/schedule') ?>">Back to schedule</a></section><?php else: $audience = self::audienceMembers($db, (int)$production['id'], (string)$editItem['visibility']); ?>
<section class="prod-edit-head"><div><small>SCHEDULE CHANGE</small><h2><?= $esc($editItem['title']) ?></h2><p>Save the operational change first. CTSMD will create a communication draft based on the resulting visibility and production membership.</p></div><a href="<?= $url('/schedule') ?>">← Schedule</a></section>
<div class="prod-edit-layout"><form class="prod-edit-form" method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_csrf']) ?>"><input type="hidden" name="schedule_item_id" value="<?= (int)$editItem['id'] ?>"><label>Title<input name="title" maxlength="190" required value="<?= $esc($editItem['title']) ?>"></label><div class="prod-edit-pair"><label>Start<input type="datetime-local" name="starts_at" required value="<?= $esc(date('Y-m-d\TH:i', strtotime($editItem['starts_at']))) ?>"></label><label>End<input type="datetime-local" name="ends_at" value="<?= $editItem['ends_at'] ? $esc(date('Y-m-d\TH:i', strtotime($editItem['ends_at']))) : '' ?>"></label></div><div class="prod-edit-pair"><label>Family call<input type="datetime-local" name="family_call_at" value="<?= $editItem['family_call_at'] ? $esc(date('Y-m-d\TH:i', strtotime($editItem['family_call_at']))) : '' ?>"></label><label>Location<input name="location" maxlength="190" required value="<?= $esc($editItem['location']) ?>"></label></div><div class="prod-edit-pair"><label>Activity type<input name="item_type" maxlength="80" required value="<?= $esc($editItem['item_type']) ?>"></label><label>Audience<select name="visibility"><option value="family"<?= $editItem['visibility'] === 'family' ? ' selected' : '' ?>>Family</option><option value="staff"<?= $editItem['visibility'] === 'staff' ? ' selected' : '' ?>>Staff</option><option value="all"<?= $editItem['visibility'] === 'all' ? ' selected' : '' ?>>Everyone in production</option></select></label></div><footer><a href="<?= $url('/schedule') ?>">Cancel</a><button class="button" type="submit">Save change & create draft</button></footer></form><aside class="prod-impact-card"><small>CURRENT IMPACT</small><b><?= count($audience) ?></b><h3>people in this audience</h3><p>The final draft audience is recalculated on the server after you save, including any visibility change.</p><div><?php foreach ($audience as $member): ?><span><i><?= $esc(strtoupper(substr($member['audience_type'],0,1))) ?></i><?= $esc($member['name']) ?><small><?= $esc(ucfirst($member['audience_type'])) ?></small></span><?php endforeach; ?></div><em>No message is sent automatically.</em></aside></div>
<?php endif; ?>

<?php elseif ($route === '/production/day'): ?>
<section class="prod-heading"><div><small><?= $esc(strtoupper($production['title'])) ?></small><h2>Production day</h2><p>A denser operational view of the next scheduled production activity.</p></div><a href="<?= $url('/schedule') ?>">Full schedule →</a></section><?php if ($next): ?><div class="prod-day-grid"><section class="prod-panel"><small>NEXT ACTIVITY</small><h3><?= $esc($next['title']) ?></h3><div class="prod-day-detail"><span><b>Start</b><?= $esc(date('l, M j · g:i A', strtotime($next['starts_at']))) ?></span><span><b>End</b><?= $next['ends_at'] ? $esc(date('g:i A', strtotime($next['ends_at']))) : 'Not specified' ?></span><span><b>Location</b><?= $esc($next['location']) ?></span><span><b>Visibility</b><?= $esc(ucfirst($next['visibility'])) ?></span></div></section><aside class="prod-panel"><small>FAMILY VIEW</small><h3><?= $next['family_call_at'] ? $esc(date('g:i A', strtotime($next['family_call_at']))) : 'No family call set' ?></h3><p><?= $next['family_call_at'] ? 'The family-facing call time is stored separately from the activity start time.' : 'This item does not currently include a separate family call.' ?></p></aside></div><?php else: ?><section class="prod-empty"><b>No upcoming activity</b></section><?php endif; ?>

<?php elseif ($route === '/resources'): ?>
<section class="prod-heading"><div><small><?= $esc(strtoupper($production['title'])) ?></small><h2>Production resources</h2><p>This hub only links to information that actually exists in CTSMD Connect.</p></div></section><div class="prod-resource-grid"><a href="<?= $url('/schedule') ?>"><i>◷</i><small>SCHEDULE</small><h3>Calls & dates</h3><p>Current rehearsal, performance and production timing.</p></a><a href="<?= $url('/channels') ?>"><i>#</i><small>COMMUNITY</small><h3>Production channels</h3><p><?= count($channels) ?> active channels.</p></a><a href="<?= $url('/playbills') ?>"><i>▤</i><small>PLAYBILL</small><h3>Digital Playbill</h3><p><?= $playbill ? 'Current Playbill record is available.' : 'No Playbill is currently attached.' ?></p></a><a href="<?= $url('/volunteer-shifts') ?>"><i>♡</i><small>VOLUNTEER</small><h3>Coverage</h3><p><?= (int)$coverage['open_slots'] ?> unfilled volunteer slots.</p></a><a href="<?= $url('/forms') ?>"><i>✓</i><small>FORMS</small><h3>Requirements</h3><p>Family and volunteer forms relevant to participation.</p></a><a href="<?= $url('/messages') ?>"><i>✉</i><small>MESSAGES</small><h3>Direct communication</h3><p>Protected conversations remain separate from broadcast information.</p></a></div>

<?php else: ?>
<section class="prod-playbill"><small>DIGITAL PLAYBILL</small><h2><?= $esc($production['title']) ?></h2><p><?= $esc((string)$production['season']) ?></p><?php if ($playbill): ?><span class="prod-playbill-status"><?= $esc(ucfirst($playbill['status'])) ?></span><div class="prod-ticket"><b>CTSMD</b><span><?= $esc($production['title']) ?></span><small>Children's Theatre of Southern Maryland</small></div><?php else: ?><div class="prod-empty"><b>No Playbill record</b></div><?php endif; ?></section>
<?php endif; ?>
</div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php
        exit;
    }

    private static function forbidden(string $basePath, array $user): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Restricted · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/production', $basePath, $user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Production', 'Restricted', $basePath); ?><div class="prod-page"><section class="prod-empty"><b>Staff only</b><p>Your current role cannot edit the production schedule.</p><a class="button" href="<?= $url('/schedule') ?>">View schedule</a></section></div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php
        exit;
    }

    private static function flash(string $type, string $message): void
    {
        $_SESSION['production_flash'] = ['type' => $type, 'message' => $message];
    }

    private static function redirect(string $url): never
    {
        header('Location: ' . $url, true, 303);
        exit;
    }
}
