<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/AccessPolicy.php';

final class ScheduleCreateExperience
{
    private const ROUTES = ['/production/schedule/new'];

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
        if (!AccessPolicy::canManageProduction($user)) {
            self::forbidden($basePath, $user);
        }

        $_SESSION['schedule_create_csrf'] ??= bin2hex(random_bytes(24));
        $production = self::currentProduction($db);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::handlePost($db, $user, $production, $basePath);
        }

        self::page($basePath, $user, $production);
    }

    private static function handlePost(PDO $db, array $user, ?array $production, string $basePath): never
    {
        $token = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals((string)($_SESSION['schedule_create_csrf'] ?? ''), $token)) {
            self::flash('error', 'Your session token expired. Please try again.');
            self::redirect($basePath . '/production/schedule/new');
        }

        if (!$production) {
            self::flash('error', 'There is no current production. Make a production current before creating schedule items.');
            self::redirect($basePath . '/admin/productions');
        }

        try {
            $itemId = self::createScheduleItem($db, $user, $production, $_POST);
            $_SESSION['production_flash'] = ['type' => 'success', 'message' => 'Schedule item created. Review it below or return to the schedule.'];
            self::redirect($basePath . '/production/edit?id=' . $itemId);
        } catch (RuntimeException $e) {
            self::flash('error', $e->getMessage());
            self::redirect($basePath . '/production/schedule/new');
        }
    }

    private static function createScheduleItem(PDO $db, array $user, array $production, array $input): int
    {
        $title = trim((string)($input['title'] ?? ''));
        $location = trim((string)($input['location'] ?? ''));
        $itemType = trim((string)($input['item_type'] ?? 'rehearsal'));
        $visibility = (string)($input['visibility'] ?? 'all');
        $startsAt = self::parseLocalDateTime((string)($input['starts_at'] ?? ''), 'Start time');
        $endsAt = self::parseOptionalLocalDateTime((string)($input['ends_at'] ?? ''), 'End time');
        $familyCallAt = self::parseOptionalLocalDateTime((string)($input['family_call_at'] ?? ''), 'Family call');
        $prepareNotice = isset($input['prepare_notice']);

        if ($title === '' || mb_strlen($title) > 190) {
            throw new RuntimeException('Enter a schedule title no longer than 190 characters.');
        }
        if ($location === '' || mb_strlen($location) > 190) {
            throw new RuntimeException('Enter a location no longer than 190 characters.');
        }
        if ($itemType === '' || mb_strlen($itemType) > 80) {
            throw new RuntimeException('Enter an activity type no longer than 80 characters.');
        }
        if (!in_array($visibility, ['family', 'staff', 'all'], true)) {
            throw new RuntimeException('Choose a valid audience.');
        }
        if ($endsAt !== null && $endsAt <= $startsAt) {
            throw new RuntimeException('The end time must be after the start time.');
        }

        $db->beginTransaction();
        try {
            $insert = $db->prepare('INSERT INTO schedule_items (production_id, title, starts_at, ends_at, family_call_at, location, visibility, item_type) VALUES (:production_id, :title, :starts_at, :ends_at, :family_call_at, :location, :visibility, :item_type)');
            $insert->execute([
                'production_id' => (int)$production['id'],
                'title' => $title,
                'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                'ends_at' => $endsAt?->format('Y-m-d H:i:s'),
                'family_call_at' => $familyCallAt?->format('Y-m-d H:i:s'),
                'location' => $location,
                'visibility' => $visibility,
                'item_type' => $itemType,
            ]);
            $itemId = (int)$db->lastInsertId();

            $noticeId = null;
            $audience = self::audienceMembers($db, (int)$production['id'], $visibility);
            if ($prepareNotice) {
                $notice = $db->prepare("INSERT INTO schedule_change_notices (schedule_item_id, production_id, created_by_user_id, audience_scope, audience_count, subject, body, status) VALUES (:item, :production, :actor, :scope, :audience_count, :subject, :body, 'draft')");
                $notice->execute([
                    'item' => $itemId,
                    'production' => (int)$production['id'],
                    'actor' => (int)$user['id'],
                    'scope' => $visibility,
                    'audience_count' => count($audience),
                    'subject' => 'New schedule item · ' . $title,
                    'body' => self::communicationCopy([
                        'title' => $title,
                        'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                        'family_call_at' => $familyCallAt?->format('Y-m-d H:i:s'),
                        'location' => $location,
                    ]),
                ]);
                $noticeId = (int)$db->lastInsertId();
            }

            $audit = $db->prepare('INSERT INTO audit_events (actor_user_id, event_type, subject_type, subject_id, summary, metadata_json) VALUES (:actor, :event_type, :subject_type, :subject_id, :summary, :metadata)');
            $audit->execute([
                'actor' => (int)$user['id'],
                'event_type' => 'schedule.created',
                'subject_type' => 'schedule_item',
                'subject_id' => $itemId,
                'summary' => 'Created production schedule item.',
                'metadata' => json_encode([
                    'production_id' => (int)$production['id'],
                    'title' => $title,
                    'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                    'ends_at' => $endsAt?->format('Y-m-d H:i:s'),
                    'family_call_at' => $familyCallAt?->format('Y-m-d H:i:s'),
                    'location' => $location,
                    'visibility' => $visibility,
                    'item_type' => $itemType,
                    'communication_draft_id' => $noticeId,
                    'audience_user_ids' => array_map(static fn(array $row): int => (int)$row['id'], $audience),
                ], JSON_THROW_ON_ERROR),
            ]);

            $db->commit();
            return $itemId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('The schedule item could not be created.');
        }
    }

    private static function audienceMembers(PDO $db, int $productionId, string $visibility): array
    {
        $types = match ($visibility) {
            'family' => ['student', 'guardian'],
            'staff' => ['staff'],
            default => ['student', 'guardian', 'staff'],
        };
        $placeholders = implode(',', array_fill(0, count($types), '?'));
        $sql = "SELECT DISTINCT u.id, u.last_name AS sort_last_name, u.first_name AS sort_first_name FROM production_memberships pm JOIN users u ON u.id = pm.user_id WHERE pm.production_id = ? AND pm.status = 'active' AND u.active = 1 AND pm.audience_type IN ($placeholders) ORDER BY sort_last_name, sort_first_name";
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge([$productionId], $types));
        return $stmt->fetchAll();
    }

    private static function communicationCopy(array $item): string
    {
        $start = new DateTimeImmutable($item['starts_at']);
        $parts = [
            'A new production schedule item has been added: ' . $item['title'] . '.',
            $start->format('l, F j \a\t g:i A') . ' at ' . $item['location'] . '.',
        ];
        if (!empty($item['family_call_at'])) {
            $parts[] = 'Family call: ' . (new DateTimeImmutable($item['family_call_at']))->format('g:i A') . '.';
        }
        $parts[] = 'Please review the production schedule in CTSMD Connect for the current details.';
        return implode(' ', $parts);
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

    private static function currentProduction(PDO $db): ?array
    {
        $row = $db->query("SELECT id, title, season, status FROM productions WHERE status = 'current' ORDER BY id DESC LIMIT 1")->fetch();
        return $row ?: null;
    }

    private static function currentUser(PDO $db): array
    {
        $row = $db->query("SELECT id, CONCAT(first_name, ' ', last_name) AS name, display_role AS role, initials FROM users WHERE is_demo_current_user = 1 AND active = 1 LIMIT 1")->fetch();
        if (!$row) {
            throw new RuntimeException('Demo user is missing. Re-import the local seed data.');
        }
        return $row;
    }

    private static function page(string $basePath, array $user, ?array $production): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $flash = $_SESSION['schedule_create_flash'] ?? null;
        unset($_SESSION['schedule_create_flash']);
        $defaultStart = (new DateTimeImmutable('tomorrow 18:00'))->format('Y-m-d\\TH:i');
        $defaultEnd = (new DateTimeImmutable('tomorrow 20:30'))->format('Y-m-d\\TH:i');

        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#a6192e">
<title>New schedule item · CTSMD Connect</title>
<link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/schedule-create.css') ?>">
</head>
<body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/production/schedule/new', $basePath, $user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Production', 'New schedule item', $basePath, [
    ['label' => 'Overview', 'href' => '/production', 'active' => false],
    ['label' => 'Schedule', 'href' => '/schedule', 'active' => true],
    ['label' => 'Resources', 'href' => '/resources', 'active' => false],
    ['label' => 'Playbill', 'href' => '/playbills', 'active' => false],
]); ?><div class="sc-page">
<?php if ($flash): ?><div class="sc-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif; ?>
<?php if (!$production): ?>
<section class="sc-empty"><small>NO CURRENT PRODUCTION</small><h2>Create the production context first.</h2><p>Schedule items belong to one production. Make a planning production current before building its schedule.</p><a class="button" href="<?= $url('/admin/productions') ?>">Manage productions</a></section>
<?php else: ?>
<section class="sc-hero"><div><small><?= $esc(strtoupper($production['title'])) ?></small><h2>Add something to the callboard.</h2><p>Create the operational event first, then decide whether CTSMD should prepare a communication draft for its audience.</p></div><a href="<?= $url('/schedule') ?>">← Back to schedule</a></section>
<div class="sc-layout"><form class="sc-form" method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['schedule_create_csrf']) ?>">
<label>Title<input name="title" maxlength="190" required placeholder="Full Cast Rehearsal"></label>
<div class="sc-pair"><label>Activity type<select name="item_type"><option value="rehearsal">Rehearsal</option><option value="performance">Performance</option><option value="meeting">Meeting</option><option value="orientation">Orientation</option><option value="volunteer">Volunteer activity</option><option value="call">Call / check-in</option><option value="other">Other</option></select></label><label>Audience<select name="visibility"><option value="all">Everyone in production</option><option value="family">Students + guardians</option><option value="staff">Staff only</option></select></label></div>
<div class="sc-pair"><label>Start<input type="datetime-local" name="starts_at" required value="<?= $esc($defaultStart) ?>"></label><label>End<input type="datetime-local" name="ends_at" value="<?= $esc($defaultEnd) ?>"></label></div>
<div class="sc-pair"><label>Family call <span>optional</span><input type="datetime-local" name="family_call_at"></label><label>Location<input name="location" maxlength="190" required placeholder="Main Stage"></label></div>
<label class="sc-check"><input type="checkbox" name="prepare_notice" value="1" checked><span><b>Prepare a communication draft</b><small>Creates a reviewable draft for the selected audience. Nothing is sent automatically.</small></span></label>
<footer><a href="<?= $url('/schedule') ?>">Cancel</a><button class="button" type="submit">Create schedule item</button></footer></form>
<aside class="sc-side"><small>WHAT HAPPENS NEXT</small><h3>The schedule becomes the source of truth.</h3><ol><li>The item is written to the current production.</li><li>The audience is resolved from active production memberships.</li><li>If selected, a communication draft is prepared.</li><li>The creation is recorded in the audit trail.</li></ol><div><b>No automatic blast.</b><span>Staff still reviews and publishes communication separately.</span></div></aside></div>
<?php endif; ?>
</div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php
        exit;
    }

    private static function forbidden(string $basePath, array $user): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Restricted · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/production', $basePath, $user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Production', 'Restricted', $basePath); ?><div class="sc-page"><section class="sc-empty"><b>Staff only</b><p>Your current role cannot create production schedule items.</p><a class="button" href="<?= $url('/schedule') ?>">View schedule</a></section></div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php
        exit;
    }

    private static function flash(string $type, string $message): void
    {
        $_SESSION['schedule_create_flash'] = ['type' => $type, 'message' => $message];
    }

    private static function redirect(string $url): never
    {
        header('Location: ' . $url, true, 303);
        exit;
    }
}
