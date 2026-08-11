<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
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
        Auth::startSession();
        $db = Database::connect(dirname(__DIR__));
        $user = Auth::currentUser($db);
        if (!$user) self::redirect(($basePath ?: '') . '/login');
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
                self::setActive($db, $user, (int)$id, true);
                self::flash('success', 'Production activated. Other active productions remain active.');
            } elseif ($action === 'deactivate') {
                self::setActive($db, $user, (int)$id, false);
                self::flash('success', 'Production deactivated. Its production-only community spaces are now hidden from former cast and families.');
            } elseif ($action === 'select_workspace') {
                self::selectWorkspace($db, $user, (int)$id);
                self::flash('success', 'Production workspace selected. Other active productions remain active.');
            } elseif ($action === 'restore') {
                self::restorePlanning($db, $user, (int)$id);
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
        $stmt = $db->prepare("INSERT INTO productions (title, season, status, is_active) VALUES (:title, :season, 'planning', 0)");
        $stmt->execute(['title' => $title, 'season' => $season]);
        $id = (int)$db->lastInsertId();
        self::audit($db, (int)$actor['id'], 'production.created', $id, 'Created production in Planning.', ['title'=>$title,'season'=>$season,'is_active'=>false]);
        return $id;
    }

    private static function updateProduction(PDO $db, array $actor, int $id, array $input): void
    {
        [$title, $season] = self::validateDetails($input);
        $before = self::production($db, $id);
        if (!$before) throw new RuntimeException('That production no longer exists.');
        $stmt = $db->prepare('UPDATE productions SET title = :title, season = :season WHERE id = :id');
        $stmt->execute(['title'=>$title,'season'=>$season,'id'=>$id]);
        self::audit($db, (int)$actor['id'], 'production.updated', $id, 'Updated production identity.', ['before'=>['title'=>$before['title'],'season'=>$before['season']],'after'=>['title'=>$title,'season'=>$season]]);
    }

    private static function setActive(PDO $db, array $actor, int $id, bool $active): void
    {
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT id,title,status,is_active FROM productions WHERE id=:id FOR UPDATE');
            $stmt->execute(['id'=>$id]);
            $production = $stmt->fetch();
            if (!$production) throw new RuntimeException('That production no longer exists.');
            if ((bool)$production['is_active'] === $active) { $db->commit(); return; }

            if ($active) {
                $hasWorkspace = (bool)$db->query("SELECT 1 FROM productions WHERE is_active=1 AND status='current' LIMIT 1")->fetchColumn();
                $status = $hasWorkspace ? 'planning' : 'current';
                $update = $db->prepare('UPDATE productions SET is_active=1, status=:status, activated_at=CURRENT_TIMESTAMP, deactivated_at=NULL WHERE id=:id');
                $update->execute(['status'=>$status,'id'=>$id]);
            } else {
                $wasWorkspace = $production['status'] === 'current';
                $update = $db->prepare("UPDATE productions SET is_active=0, status='archived', deactivated_at=CURRENT_TIMESTAMP WHERE id=:id");
                $update->execute(['id'=>$id]);
                if ($wasWorkspace) {
                    $replacement = $db->query("SELECT id FROM productions WHERE is_active=1 AND id <> " . (int)$id . " ORDER BY activated_at DESC, id DESC LIMIT 1 FOR UPDATE")->fetchColumn();
                    if ($replacement) {
                        $db->exec("UPDATE productions SET status='planning' WHERE is_active=1 AND status='current'");
                        $promote = $db->prepare("UPDATE productions SET status='current' WHERE id=:id AND is_active=1");
                        $promote->execute(['id'=>(int)$replacement]);
                    }
                }
            }

            self::audit($db, (int)$actor['id'], $active ? 'production.activated' : 'production.deactivated', $id, $active ? 'Activated production without affecting other active productions.' : 'Deactivated production and removed production-only member access.', ['before_active'=>(bool)$production['is_active'],'after_active'=>$active]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof RuntimeException) throw $e;
            throw new RuntimeException('The production activity state could not be changed.');
        }
    }

    private static function selectWorkspace(PDO $db, array $actor, int $id): void
    {
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT id,title,is_active FROM productions WHERE id=:id FOR UPDATE');
            $stmt->execute(['id'=>$id]);
            $production = $stmt->fetch();
            if (!$production || !(bool)$production['is_active']) throw new RuntimeException('Only an active production can be selected as the working production.');
            $db->exec("UPDATE productions SET status='planning' WHERE is_active=1 AND status='current'");
            $set = $db->prepare("UPDATE productions SET status='current' WHERE id=:id AND is_active=1");
            $set->execute(['id'=>$id]);
            self::audit($db, (int)$actor['id'], 'production.workspace_selected', $id, 'Selected active production as the operational workspace.', []);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof RuntimeException) throw $e;
            throw new RuntimeException('The production workspace could not be changed.');
        }
    }

    private static function restorePlanning(PDO $db, array $actor, int $id): void
    {
        $production = self::production($db, $id);
        if (!$production) throw new RuntimeException('That production no longer exists.');
        $stmt = $db->prepare("UPDATE productions SET status='planning', is_active=0, deactivated_at=NULL WHERE id=:id");
        $stmt->execute(['id'=>$id]);
        self::audit($db, (int)$actor['id'], 'production.restored_to_planning', $id, 'Returned inactive production to Planning.', []);
    }

    private static function validateDetails(array $input): array
    {
        $title = trim((string)($input['title'] ?? ''));
        $season = trim((string)($input['season'] ?? ''));
        if ($title === '' || mb_strlen($title) > 190) throw new RuntimeException('Enter a production title no longer than 190 characters.');
        if ($season === '' || mb_strlen($season) > 100) throw new RuntimeException('Enter a season label no longer than 100 characters.');
        return [$title,$season];
    }

    private static function production(PDO $db, int $id): ?array
    {
        if ($id < 1) return null;
        $stmt = $db->prepare("SELECT p.id,p.title,p.season,p.status,p.is_active,p.activated_at,p.deactivated_at,p.created_at,
            (SELECT COUNT(*) FROM production_memberships pm WHERE pm.production_id=p.id AND pm.status='active') active_members,
            (SELECT COUNT(*) FROM schedule_items si WHERE si.production_id=p.id) schedule_items,
            (SELECT COUNT(*) FROM volunteer_shifts vs WHERE vs.production_id=p.id) volunteer_shifts,
            (SELECT COUNT(*) FROM channels c WHERE c.production_id=p.id AND c.archived_at IS NULL) channels,
            (SELECT COUNT(*) FROM playbills pb WHERE pb.production_id=p.id) playbills
            FROM productions p WHERE p.id=:id LIMIT 1");
        $stmt->execute(['id'=>$id]);
        return $stmt->fetch() ?: null;
    }

    private static function productions(PDO $db): array
    {
        return $db->query("SELECT p.id,p.title,p.season,p.status,p.is_active,p.created_at,
            (SELECT COUNT(*) FROM production_memberships pm WHERE pm.production_id=p.id AND pm.status='active') active_members,
            (SELECT COUNT(*) FROM schedule_items si WHERE si.production_id=p.id) schedule_items
            FROM productions p ORDER BY p.is_active DESC, (p.status='current') DESC, p.id DESC")->fetchAll();
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
        $active = array_values(array_filter($productions, static fn(array $p): bool => (bool)$p['is_active']));
        $workspace = array_values(array_filter($active, static fn(array $p): bool => $p['status']==='current'))[0] ?? null;
        $planning = count(array_filter($productions, static fn(array $p): bool => !(bool)$p['is_active'] && $p['status']==='planning'));
        $inactive = count($productions) - count($active) - $planning;
        $title = $route === '/admin/productions/view' ? ($selected['title'] ?? 'Production') : 'Productions & seasons';
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#a6192e"><title><?= $esc($title) ?> · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/production-lifecycle.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar($route,$basePath,$user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Production',$title,$basePath); ?><div class="pl-page">
        <?php if ($flash): ?><div class="pl-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif; ?>
        <?php if ($route === '/admin/productions'): ?>
        <section class="pl-hero"><div><small>CONCURRENT PRODUCTIONS</small><h2>More than one show can be live.</h2><p>Active productions coexist. Selecting a workspace only chooses which show the production tools are editing; it never deactivates another show.</p></div><div class="pl-current"><small>WORKSPACE</small><b><?= $workspace ? $esc($workspace['title']) : 'None selected' ?></b><span><?= count($active) ?> active production<?= count($active)===1?'':'s' ?></span></div></section>
        <div class="pl-stats"><article><b><?= count($active) ?></b><span>active productions</span></article><article><b><?= $planning ?></b><span>planning</span></article><article><b><?= $inactive ?></b><span>inactive / archived</span></article></div>
        <div class="pl-layout"><section class="pl-panel"><header><small>PRODUCTION HISTORY</small><h3>Productions</h3></header><div class="pl-list"><?php foreach($productions as $p): ?><a class="pl-row <?= (bool)$p['is_active'] ? 'current' : $esc($p['status']) ?>" href="<?= $url('/admin/productions/view?id='.(int)$p['id']) ?>"><div><small><?= (bool)$p['is_active'] ? (($p['status']==='current'?'WORKSPACE · ':'').'ACTIVE') : $esc(strtoupper($p['status'])) ?></small><h4><?= $esc($p['title']) ?></h4><p><?= $esc((string)$p['season']) ?></p><span><?= (int)$p['active_members'] ?> people · <?= (int)$p['schedule_items'] ?> schedule items</span></div><em>Manage →</em></a><?php endforeach; ?></div></section><aside class="pl-panel create"><small>NEXT SHOW</small><h3>Create a production</h3><p>New productions begin in Planning and can be activated whenever their community and operational spaces should become available.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_lifecycle_csrf']) ?>"><input type="hidden" name="action" value="create"><label>Production title<input name="title" maxlength="190" required></label><label>Season / cycle<input name="season" maxlength="100" required></label><button class="button full" type="submit">Create planning production</button></form></aside></div>
        <?php else: ?>
        <?php if (!$selected): ?><section class="pl-empty"><b>Production not found.</b><a class="button" href="<?= $url('/admin/productions') ?>">Back to productions</a></section><?php else: ?>
        <section class="pl-detail-head"><div><small><?= (bool)$selected['is_active'] ? (($selected['status']==='current'?'WORKSPACE · ':'').'ACTIVE') : $esc(strtoupper($selected['status'])) ?> · <?= $esc((string)$selected['season']) ?></small><h2><?= $esc($selected['title']) ?></h2><p><?= (int)$selected['active_members'] ?> people · <?= (int)$selected['schedule_items'] ?> schedule items · <?= (int)$selected['volunteer_shifts'] ?> shifts · <?= (int)$selected['channels'] ?> channels</p></div><a href="<?= $url('/admin/productions') ?>">← All productions</a></section>
        <div class="pl-detail-layout"><section class="pl-panel"><small>IDENTITY</small><h3>Production details</h3><form method="post" class="pl-detail-form"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_lifecycle_csrf']) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="production_id" value="<?= (int)$selected['id'] ?>"><label>Title<input name="title" maxlength="190" required value="<?= $esc($selected['title']) ?>"></label><label>Season<input name="season" maxlength="100" required value="<?= $esc((string)$selected['season']) ?>"></label><button class="button" type="submit">Save details</button></form></section><aside class="pl-panel create"><small>ACTIVITY</small><h3><?= (bool)$selected['is_active'] ? 'This show is active' : 'This show is inactive' ?></h3><p><?= (bool)$selected['is_active'] ? 'Cast and family production access is available while their memberships remain active.' : 'Production-specific community access is closed to cast and families; public organization channels remain available.' ?></p><?php if ((bool)$selected['is_active']): ?><?php if ($selected['status']!=='current'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_lifecycle_csrf']) ?>"><input type="hidden" name="production_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="action" value="select_workspace"><button class="button full" type="submit">Select as working production</button></form><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_lifecycle_csrf']) ?>"><input type="hidden" name="production_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="action" value="deactivate"><button class="secondary" type="submit">Deactivate show</button></form><?php else: ?><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_lifecycle_csrf']) ?>"><input type="hidden" name="production_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="action" value="activate"><button class="button full" type="submit">Activate show</button></form><?php if ($selected['status']==='archived'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_lifecycle_csrf']) ?>"><input type="hidden" name="production_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="action" value="restore"><button class="secondary" type="submit">Return to Planning</button></form><?php endif; ?><?php endif; ?></aside></div>
        <?php endif; ?><?php endif; ?>
        </div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php exit;
    }

    private static function forbidden(string $basePath, array $user): never
    {
        http_response_code(403); echo 'Restricted'; exit;
    }
    private static function flash(string $type,string $message): void { $_SESSION['production_lifecycle_flash']=['type'=>$type,'message'=>$message]; }
    private static function redirect(string $url): never { header('Location: '.$url,true,303); exit; }
}
