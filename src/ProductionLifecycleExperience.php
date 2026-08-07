<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/AccessPolicy.php';

final class ProductionLifecycleExperience
{
    private const ROUTES = ['/admin/productions'];

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

        $_SESSION['production_lifecycle_csrf'] ??= bin2hex(random_bytes(24));

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::handlePost($db, $user, $basePath);
        }

        self::page($basePath, $user, self::productions($db));
    }

    private static function handlePost(PDO $db, array $user, string $basePath): never
    {
        $token = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals((string)($_SESSION['production_lifecycle_csrf'] ?? ''), $token)) {
            self::flash('error', 'Your session token expired. Please try again.');
            self::redirect($basePath . '/admin/productions');
        }

        try {
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'create') {
                self::createProduction($db, $user, $_POST);
                self::flash('success', 'Production created in planning status.');
            } elseif ($action === 'activate') {
                $id = filter_input(INPUT_POST, 'production_id', FILTER_VALIDATE_INT) ?: 0;
                self::activateProduction($db, $user, (int)$id);
                self::flash('success', 'Current production changed. The previous current production was archived.');
            } elseif ($action === 'archive') {
                $id = filter_input(INPUT_POST, 'production_id', FILTER_VALIDATE_INT) ?: 0;
                self::setStatus($db, $user, (int)$id, 'archived');
                self::flash('success', 'Production archived.');
            } elseif ($action === 'restore') {
                $id = filter_input(INPUT_POST, 'production_id', FILTER_VALIDATE_INT) ?: 0;
                self::setStatus($db, $user, (int)$id, 'planning');
                self::flash('success', 'Production returned to planning.');
            } else {
                throw new RuntimeException('Choose a valid production action.');
            }
        } catch (RuntimeException $e) {
            self::flash('error', $e->getMessage());
        }

        self::redirect($basePath . '/admin/productions');
    }

    private static function createProduction(PDO $db, array $actor, array $input): void
    {
        $title = trim((string)($input['title'] ?? ''));
        $season = trim((string)($input['season'] ?? ''));

        if ($title === '' || mb_strlen($title) > 190) {
            throw new RuntimeException('Enter a production title no longer than 190 characters.');
        }
        if ($season !== '' && mb_strlen($season) > 100) {
            throw new RuntimeException('Keep the season label under 100 characters.');
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO productions (title, season, status) VALUES (:title, :season, 'planning')");
            $stmt->execute(['title' => $title, 'season' => $season !== '' ? $season : null]);
            $productionId = (int)$db->lastInsertId();

            self::audit($db, (int)$actor['id'], 'production.created', 'production', $productionId, 'Created a production in planning status.', [
                'title' => $title,
                'season' => $season !== '' ? $season : null,
                'status' => 'planning',
            ]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw new RuntimeException('The production could not be created.');
        }
    }

    private static function activateProduction(PDO $db, array $actor, int $productionId): void
    {
        if ($productionId < 1) {
            throw new RuntimeException('That production could not be found.');
        }

        $db->beginTransaction();
        try {
            $targetStmt = $db->prepare('SELECT id, title, status FROM productions WHERE id = :id FOR UPDATE');
            $targetStmt->execute(['id' => $productionId]);
            $target = $targetStmt->fetch();
            if (!$target) {
                throw new RuntimeException('That production no longer exists.');
            }
            if ($target['status'] === 'current') {
                $db->commit();
                return;
            }

            $currentStmt = $db->query("SELECT id, title FROM productions WHERE status = 'current' FOR UPDATE");
            $currents = $currentStmt->fetchAll();
            $previousIds = array_map(static fn(array $row): int => (int)$row['id'], $currents);

            $archive = $db->prepare("UPDATE productions SET status = 'archived' WHERE status = 'current' AND id <> :id");
            $archive->execute(['id' => $productionId]);

            $activate = $db->prepare("UPDATE productions SET status = 'current' WHERE id = :id");
            $activate->execute(['id' => $productionId]);

            self::audit($db, (int)$actor['id'], 'production.activated', 'production', $productionId, 'Made production current and archived any previous current production.', [
                'previous_status' => $target['status'],
                'archived_previous_production_ids' => $previousIds,
            ]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('The current production could not be changed.');
        }
    }

    private static function setStatus(PDO $db, array $actor, int $productionId, string $status): void
    {
        if ($productionId < 1 || !in_array($status, ['planning', 'archived'], true)) {
            throw new RuntimeException('Choose a valid production status.');
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT id, title, status FROM productions WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $productionId]);
            $production = $stmt->fetch();
            if (!$production) {
                throw new RuntimeException('That production no longer exists.');
            }
            if ($production['status'] === $status) {
                $db->commit();
                return;
            }

            $update = $db->prepare('UPDATE productions SET status = :status WHERE id = :id');
            $update->execute(['status' => $status, 'id' => $productionId]);

            self::audit($db, (int)$actor['id'], 'production.status_changed', 'production', $productionId, 'Changed production lifecycle status.', [
                'before' => $production['status'],
                'after' => $status,
            ]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('The production status could not be changed.');
        }
    }

    private static function productions(PDO $db): array
    {
        $sql = "SELECT p.id, p.title, p.season, p.status, p.created_at,
                       (SELECT COUNT(*) FROM production_memberships pm WHERE pm.production_id = p.id AND pm.status = 'active') AS active_members,
                       (SELECT COUNT(*) FROM schedule_items si WHERE si.production_id = p.id) AS schedule_items,
                       (SELECT COUNT(*) FROM volunteer_shifts vs WHERE vs.production_id = p.id) AS volunteer_shifts,
                       (SELECT COUNT(*) FROM channels c WHERE c.production_id = p.id AND c.archived_at IS NULL) AS channels
                FROM productions p
                ORDER BY FIELD(p.status,'current','planning','archived'), p.id DESC";
        return $db->query($sql)->fetchAll();
    }

    private static function currentUser(PDO $db): array
    {
        $row = $db->query("SELECT id, CONCAT(first_name, ' ', last_name) AS name, display_role AS role, initials FROM users WHERE is_demo_current_user = 1 AND active = 1 LIMIT 1")->fetch();
        if (!$row) {
            throw new RuntimeException('Demo user is missing. Re-import the local seed data.');
        }
        return $row;
    }

    private static function audit(PDO $db, int $actorId, string $eventType, string $subjectType, int $subjectId, string $summary, array $metadata): void
    {
        $stmt = $db->prepare('INSERT INTO audit_events (actor_user_id, event_type, subject_type, subject_id, summary, metadata_json) VALUES (:actor, :event_type, :subject_type, :subject_id, :summary, :metadata)');
        $stmt->execute([
            'actor' => $actorId,
            'event_type' => $eventType,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'summary' => $summary,
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);
    }

    private static function page(string $basePath, array $user, array $productions): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $flash = $_SESSION['production_lifecycle_flash'] ?? null;
        unset($_SESSION['production_lifecycle_flash']);
        $current = array_values(array_filter($productions, static fn(array $row): bool => $row['status'] === 'current'))[0] ?? null;
        $planning = count(array_filter($productions, static fn(array $row): bool => $row['status'] === 'planning'));
        $archived = count(array_filter($productions, static fn(array $row): bool => $row['status'] === 'archived'));

        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#a6192e"><title>Productions · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/production-lifecycle.css') ?>"></head>
<body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/admin/productions', $basePath, $user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Operations', 'Productions & seasons', $basePath); ?><div class="pl-page">
<?php if ($flash): ?><div class="pl-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif; ?>
<section class="pl-hero"><div><small>PRODUCTION LIFECYCLE</small><h2>One current show. A clear past and future.</h2><p>Create the next production in planning, deliberately promote it when CTSMD is ready, and preserve completed shows as archived operational history.</p></div><div class="pl-current"><small>CURRENT</small><b><?= $current ? $esc($current['title']) : 'None selected' ?></b><span><?= $current ? $esc((string)$current['season']) : 'Production-facing pages will show an intentional empty state.' ?></span></div></section>
<div class="pl-stats"><article><b><?= count($productions) ?></b><span>total productions</span></article><article><b><?= $planning ?></b><span>planning</span></article><article><b><?= $archived ?></b><span>archived</span></article></div>
<div class="pl-layout"><section class="pl-panel"><header><small>SEASON HISTORY</small><h3>Productions</h3></header><?php if (!$productions): ?><div class="pl-empty">No productions exist yet.</div><?php else: ?><div class="pl-list"><?php foreach ($productions as $production): ?><article class="pl-row <?= $esc($production['status']) ?>"><div><small><?= $esc(strtoupper($production['status'])) ?></small><h4><?= $esc($production['title']) ?></h4><p><?= $esc((string)($production['season'] ?: 'No season label')) ?></p><span><?= (int)$production['active_members'] ?> active people · <?= (int)$production['schedule_items'] ?> schedule items · <?= (int)$production['volunteer_shifts'] ?> volunteer shifts · <?= (int)$production['channels'] ?> channels</span></div><div class="pl-actions"><?php if ($production['status'] !== 'current'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_lifecycle_csrf']) ?>"><input type="hidden" name="production_id" value="<?= (int)$production['id'] ?>"><input type="hidden" name="action" value="activate"><button type="submit">Make current</button></form><?php endif; ?><?php if ($production['status'] !== 'archived'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_lifecycle_csrf']) ?>"><input type="hidden" name="production_id" value="<?= (int)$production['id'] ?>"><input type="hidden" name="action" value="archive"><button class="secondary" type="submit">Archive</button></form><?php else: ?><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_lifecycle_csrf']) ?>"><input type="hidden" name="production_id" value="<?= (int)$production['id'] ?>"><input type="hidden" name="action" value="restore"><button class="secondary" type="submit">Return to planning</button></form><?php endif; ?></div></article><?php endforeach; ?></div><?php endif; ?></section>
<aside class="pl-panel create"><small>NEXT SHOW</small><h3>Create a production</h3><p>New productions start in planning. Nothing becomes family-facing until a staff member explicitly makes it current.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_lifecycle_csrf']) ?>"><input type="hidden" name="action" value="create"><label>Production title<input name="title" maxlength="190" required placeholder="e.g. Seussical Jr."></label><label>Season / cycle<input name="season" maxlength="100" placeholder="e.g. Fall 2026"></label><button class="button full" type="submit">Create planning production</button></form><div class="pl-note"><b>Activation rule</b><span>Making one production current automatically archives any production currently carrying that status.</span></div></aside></div>
</div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php
        exit;
    }

    private static function forbidden(string $basePath, array $user): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Restricted · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/production', $basePath, $user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Operations', 'Restricted', $basePath); ?><div style="padding:32px"><h2>Staff only</h2><p>Your current role cannot manage production lifecycle.</p></div></main></div></body></html><?php
        exit;
    }

    private static function flash(string $type, string $message): void
    {
        $_SESSION['production_lifecycle_flash'] = ['type' => $type, 'message' => $message];
    }

    private static function redirect(string $url): never
    {
        header('Location: ' . $url, true, 303);
        exit;
    }
}
