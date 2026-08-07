<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/AccessPolicy.php';

final class ProductionLifecycleExperience
{
    private const ROUTES = ['/admin/productions', '/admin/productions/view'];

    public static function handles(string $route): bool
    {
        return in_array($route, self::ROUTES, true);
    }

    public static function render(string $route, string $basePath): never
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $db = Database::connect(dirname(__DIR__));
        $user = self::currentUser($db);
        if (!AccessPolicy::canManageProduction($user)) self::forbidden($basePath, $user);
        $_SESSION['production_lifecycle_csrf'] ??= bin2hex(random_bytes(24));

        if ($_SERVER['REQUEST_METHOD'] === 'POST') self::handlePost($db, $user, $basePath);

        $selected = null;
        if ($route === '/admin/productions/view') {
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
            $selected = self::production($db, (int)$id);
        }
        self::page($route, $basePath, $db, $user, $selected);
    }

    private static function handlePost(PDO $db, array $user, string $basePath): never
    {
        $token = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals((string)($_SESSION['production_lifecycle_csrf'] ?? ''), $token)) {
            self::flash('error', 'Your session token expired. Please try again.');
            self::redirect($basePath . '/admin/productions');
        }

        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'create') {
                $id = self::createProduction($db, $user, $_POST);
                self::flash('success', 'Production created in Planning.');
                self::redirect($basePath . '/admin/productions/view?id=' . $id);
            }

            $id = filter_input(INPUT_POST, 'production_id', FILTER_VALIDATE_INT) ?: 0;
            if ($id < 1) throw new RuntimeException('That production could not be found.');

            if ($action === 'update') {
                self::updateProduction($db, $user, (int)$id, $_POST);
                self::flash('success', 'Production details updated.');
            } elseif ($action === 'activate') {
                self::activateProduction($db, $user, (int)$id);
                self::flash('success', 'Current production changed. The previous current production was archived.');
            } elseif ($action === 'archive') {
                self::setStatus($db, $user, (int)$id, 'archived');
                self::flash('success', 'Production archived.');
            } elseif ($action === 'restore') {
                self::setStatus($db, $user, (int)$id, 'planning');
                self::flash('success', 'Production returned to Planning.');
            } else {
                throw new RuntimeException('Choose a valid production action.');
            }
            self::redirect($basePath . '/admin/productions/view?id=' . (int)$id);
        } catch (RuntimeException $e) {
            self::flash('error', $e->getMessage());
            self::redirect($basePath . '/admin/productions');
        }
    }

    private static function createProduction(PDO $db, array $actor, array $input): int
    {
        [$title, $season] = self::validateDetails($input);
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO productions (title, season, status) VALUES (:title, :season, 'planning')");
            $stmt->execute(['title' => $title, 'season' => $season]);
            $id = (int)$db->lastInsertId();
            self::audit($db, (int)$actor['id'], 'production.created', $id, 'Created production in Planning.', ['title' => $title, 'season' => $season]);
            $db->commit();
            return $id;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw new RuntimeException('The production could not be created.');
        }
    }

    private static function updateProduction(PDO $db, array $actor, int $id, array $input): void
    {
        [$title, $season] = self::validateDetails($input);
        $before = self::production($db, $id);
        if (!$before) throw new RuntimeException('That production no longer exists.');
        $stmt = $db->prepare('UPDATE productions SET title = :title, season = :season WHERE id = :id');
        $stmt->execute(['title' => $title, 'season' => $season, 'id' => $id]);
        self::audit($db, (int)$actor['id'], 'production.updated', $id, 'Updated production identity.', [
            'before' => ['title' => $before['title'], 'season' => $before['season']],
            'after' => ['title' => $title, 'season' => $season],
        ]);
    }

    private static function activateProduction(PDO $db, array $actor, int $id): void
    {
        $db->beginTransaction();
        try {
            $targetStmt = $db->prepare('SELECT id, title, status FROM productions WHERE id = :id FOR UPDATE');
            $targetStmt->execute(['id' => $id]);
            $target = $targetStmt->fetch();
            if (!$target) throw new RuntimeException('That production no longer exists.');
            if ($target['status'] === 'current') { $db->commit(); return; }

            $current = $db->query("SELECT id, title FROM productions WHERE status = 'current' FOR UPDATE")->fetchAll();
            $db->exec("UPDATE productions SET status = 'archived' WHERE status = 'current'");
            $activate = $db->prepare("UPDATE productions SET status = 'current' WHERE id = :id");
            $activate->execute(['id' => $id]);
            self::audit($db, (int)$actor['id'], 'production.activated', $id, 'Made production current and archived prior current production.', [
                'previous_status' => $target['status'],
                'previous_current' => array_map(static fn(array $row): array => ['id' => (int)$row['id'], 'title' => $row['title']], $current),
            ]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof RuntimeException) throw $e;
            throw new RuntimeException('The current production could not be changed.');
        }
    }

    private static function setStatus(PDO $db, array $actor, int $id, string $status): void
    {
        if (!in_array($status, ['planning','archived'], true)) throw new RuntimeException('Choose a valid production status.');
        $production = self::production($db, $id);
        if (!$production) throw new RuntimeException('That production no longer exists.');
        if ($production['status'] === $status) return;
        if ($production['status'] === 'current' && $status === 'planning') throw new RuntimeException('A current production cannot move directly to Planning. Archive it or make another production current first.');

        $stmt = $db->prepare('UPDATE productions SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
        self::audit($db, (int)$actor['id'], 'production.status_changed', $id, 'Changed production lifecycle status.', ['before' => $production['status'], 'after' => $status]);
    }

    private static function validateDetails(array $input): array
    {
        $title = trim((string)($input['title'] ?? ''));
        $season = trim((string)($input['season'] ?? ''));
        if ($title === '' || mb_strlen($title) > 190) throw new RuntimeException('Enter a production title no longer than 190 characters.');
        if ($season === '' || mb_strlen($season) > 100) throw new RuntimeException('Enter a season label no longer than 100 characters.');
        return [$title, $season];
    }

    private static function production(PDO $db, int $id): ?array
    {
        if ($id < 1) return null;
        $stmt = $db->prepare("SELECT p.id,p.title,p.season,p.status,p.created_at,
            (SELECT COUNT(*) FROM production_memberships pm WHERE pm.production_id=p.id AND pm.status='active') active_members,
            (SELECT COUNT(*) FROM schedule_items si WHERE si.production_id=p.id) schedule_items,
            (SELECT COUNT(*) FROM volunteer_shifts vs WHERE vs.production_id=p.id) volunteer_shifts,
            (SELECT COUNT(*) FROM channels c WHERE c.production_id=p.id AND c.archived_at IS NULL) channels,
            (SELECT COUNT(*) FROM playbills pb WHERE pb.production_id=p.id) playbills
            FROM productions p WHERE p.id=:id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    private static function productions(PDO $db): array
    {
        return $db->query("SELECT p.id,p.title,p.season,p.status,p.created_at,
            (SELECT COUNT(*) FROM production_memberships pm WHERE pm.production_id=p.id AND pm.status='active') active_members,
            (SELECT COUNT(*) FROM schedule_items si WHERE si.production_id=p.id) schedule_items
            FROM productions p ORDER BY FIELD(p.status,'current','planning','archived'),p.id DESC")->fetchAll();
    }

    private static function currentUser(PDO $db): array
    {
        $row = $db->query("SELECT id,CONCAT(first_name,' ',last_name) name,display_role role,initials FROM users WHERE is_demo_current_user=1 AND active=1 LIMIT 1")->fetch();
        if (!$row) throw new RuntimeException('Demo user is missing. Re-import the local seed data.');
        return $row;
    }

    private static function audit(PDO $db, int $actorId, string $event, int $subjectId, string $summary, array $metadata): void
    {
        $stmt = $db->prepare('INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,:event,:subject_type,:subject_id,:summary,:metadata)');
        $stmt->execute(['actor'=>$actorId,'event'=>$event,'subject_type'=>'production','subject_id'=>$subjectId,'summary'=>$summary,'metadata'=>json_encode($metadata, JSON_THROW_ON_ERROR)]);
    }

    private static function page(string $route, string $basePath, PDO $db, array $user, ?array $selected): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        $flash = $_SESSION['production_lifecycle_flash'] ?? null; unset($_SESSION['production_lifecycle_flash']);
        $productions = self::productions($db);
        $current = array_values(array_filter($productions, static fn(array $p): bool => $p['status']==='current'))[0] ?? null;
        $planning = count(array_filter($productions, static fn(array $p): bool => $p['status']==='planning'));
        $archived = count(array_filter($productions, static fn(array $p): bool => $p['status']==='archived'));
        $title = $route === '/admin/productions/view' ? ($selected['title'] ?? 'Production') : 'Productions & seasons';
        $subnav = [
            ['label'=>'Overview','href'=>'/production','active'=>false],
            ['label'=>'Schedule','href'=>'/schedule','active'=>false],
            ['label'=>'People & Cast','href'=>'/production/people','active'=>false],
            ['label'=>'Productions','href'=>'/admin/productions','active'=>true],
        ];
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#a6192e"><title><?= $esc($title) ?> · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/production-lifecycle.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar($route,$basePath,$user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Production',$title,$basePath,$subnav); ?><div class="pl-page">
        <?php if ($flash): ?><div class="pl-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif; ?>
        <?php if ($route === '/admin/productions'): ?>
        <section class="pl-hero"><div><small>SEASON LIFECYCLE</small><h2>One current show. A clear past and future.</h2><p>Build the next show in Planning, promote it deliberately, and preserve completed productions as operational history.</p></div><div class="pl-current"><small>CURRENT</small><b><?= $current ? $esc($current['title']) : 'None selected' ?></b><span><?= $current ? $esc((string)$current['season']) : 'Production-facing pages will show an intentional empty state.' ?></span></div></section>
        <div class="pl-stats"><article><b><?= count($productions) ?></b><span>total productions</span></article><article><b><?= $planning ?></b><span>planning</span></article><article><b><?= $archived ?></b><span>archived</span></article></div>
        <div class="pl-layout"><section class="pl-panel"><header><small>SEASON HISTORY</small><h3>Productions</h3></header><div class="pl-list"><?php foreach($productions as $p): ?><a class="pl-row <?= $esc($p['status']) ?>" href="<?= $url('/admin/productions/view?id='.(int)$p['id']) ?>"><div><small><?= $esc(strtoupper($p['status'])) ?></small><h4><?= $esc($p['title']) ?></h4><p><?= $esc((string)$p['season']) ?></p><span><?= (int)$p['active_members'] ?> active people · <?= (int)$p['schedule_items'] ?> schedule items</span></div><em>Manage →</em></a><?php endforeach; ?></div></section><aside class="pl-panel create"><small>NEXT SHOW</small><h3>Create a production</h3><p>New productions start in Planning. Nothing becomes family-facing until staff explicitly makes it current.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_lifecycle_csrf']) ?>"><input type="hidden" name="action" value="create"><label>Production title<input name="title" maxlength="190" required placeholder="e.g. Seussical Jr."></label><label>Season / cycle<input name="season" maxlength="100" required placeholder="e.g. Fall 2026"></label><button class="button full" type="submit">Create planning production</button></form><div class="pl-note"><b>Activation rule</b><span>Making one production current automatically archives the previous current production.</span></div></aside></div>
        <?php else: ?>
        <?php if (!$selected): ?><section class="pl-empty"><b>Production not found.</b><a class="button" href="<?= $url('/admin/productions') ?>">Back to productions</a></section><?php else: ?>
        <section class="pl-detail-head"><div><small><?= $esc(strtoupper($selected['status'])) ?> · <?= $esc((string)$selected['season']) ?></small><h2><?= $esc($selected['title']) ?></h2><p><?= (int)$selected['active_members'] ?> active people · <?= (int)$selected['schedule_items'] ?> schedule items · <?= (int)$selected['volunteer_shifts'] ?> shifts · <?= (int)$selected['channels'] ?> channels · <?= (int)$selected['playbills'] ?> Playbill record(s)</p></div><a href="<?= $url('/admin/productions') ?>">← All productions</a></section>
        <div class="pl-detail-layout"><section class="pl-panel"><small>IDENTITY</small><h3>Production details</h3><form class="pl-detail-form" method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_lifecycle_csrf']) ?>"><input type="hidden" name="production_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="action" value="update"><label>Production title<input name="title" maxlength="190" required value="<?= $esc($selected['title']) ?>"></label><label>Season / cycle<input name="season" maxlength="100" required value="<?= $esc((string)$selected['season']) ?>"></label><button class="button" type="submit">Save details</button></form></section><aside class="pl-panel lifecycle"><small>LIFECYCLE</small><h3><?= $selected['status']==='current' ? 'This is the live production.' : 'Choose the next state deliberately.' ?></h3><?php if($selected['status']!=='current'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_lifecycle_csrf']) ?>"><input type="hidden" name="production_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="action" value="activate"><button class="button full" type="submit">Make current production</button></form><p class="pl-warning">This archives any production that is currently live.</p><?php endif; ?><?php if($selected['status']!=='archived'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_lifecycle_csrf']) ?>"><input type="hidden" name="production_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="action" value="archive"><button class="button secondary full" type="submit">Archive production</button></form><?php else: ?><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_lifecycle_csrf']) ?>"><input type="hidden" name="production_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="action" value="restore"><button class="button secondary full" type="submit">Return to Planning</button></form><?php endif; ?><div class="pl-note"><b>History is preserved.</b><span>Archiving does not delete schedule, cast, volunteer, channel, or Playbill records.</span></div></aside></div>
        <?php endif; ?>
        <?php endif; ?>
        </div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php exit;
    }

    private static function flash(string $type,string $message): void { $_SESSION['production_lifecycle_flash']=['type'=>$type,'message'=>$message]; }
    private static function redirect(string $url): never { header('Location: '.$url,true,303); exit; }
    private static function forbidden(string $basePath,array $user): never { http_response_code(403); header('Content-Type: text/plain; charset=utf-8'); echo 'Staff only.'; exit; }
}
