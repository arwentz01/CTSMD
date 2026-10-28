<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/ProductionContext.php';
require_once __DIR__ . '/ProductionListOrganizer.php';
require_once __DIR__ . '/StorageService.php';
require_once __DIR__ . '/ProductionCompletionService.php';
require_once __DIR__ . '/ProductionArchivalWorkflow.php';

final class ProductionLifecycleExperience
{
    private const ROUTES = ['/admin/productions', '/admin/productions/view', '/admin/productions/artwork-image'];

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

        if ($route === '/admin/productions/artwork-image') self::artworkImage($db);
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

            if (in_array($action,['artwork_save','artwork_remove'],true)) {
                self::saveArtwork($db,$user,(int)$id,$action,(string)($_POST['artwork_role']??''),$_FILES['artwork_upload']??[]);
                self::flash('success','Production artwork updated.');
            } elseif ($action === 'announcement_publish' || $action === 'announcement_unpublish') {
                self::setAnnouncementPublished($db,$user,(int)$id,$action==='announcement_publish');
                self::flash('success',$action==='announcement_publish'?'Production basics published.':'Production basics unpublished.');
            } elseif ($action === 'complete_production') {
                if(empty($_POST['acknowledge_outstanding']))throw new RuntimeException('Acknowledge that outstanding items become read-only.');
                ProductionCompletionService::complete($db,$user,(int)$id,(string)($_POST['confirm_title']??''),trim((string)($_POST['completion_note']??'')));
                self::flash('success','Production completed. Historical records were preserved.');
            } elseif ($action === 'archive_production') {
                if(empty($_POST['acknowledge_archive']))throw new RuntimeException('Acknowledge that archival removes the active workspace while retaining historical records.');
                ProductionArchivalWorkflow::archive($db,$user,(int)$id,(string)($_POST['confirm_title']??''),trim((string)($_POST['archive_note']??'')));
                self::flash('success','Production archived. Historical records were preserved.');
            } elseif ($action === 'lifecycle_update') {
                self::updateLifecycle($db,$user,(int)$id,$_POST);
                self::flash('success','Production lifecycle dates and stage updated.');
            } elseif ($action === 'update') {
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
                self::flash('success', 'Working production selected for this session. Other active productions remain active.');
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

    private static function artworkImage(PDO $db): never
    {
        $id=(int)($_GET['id']??0);
        $role=(string)($_GET['role']??'');
        if($id<1||!in_array($role,['poster','banner','thumbnail'],true)){http_response_code(404);exit;}
        $s=$db->prepare('SELECT sv.* FROM production_artwork pa JOIN stored_file_versions sv ON sv.stored_file_id=pa.stored_file_id WHERE pa.production_id=:id AND pa.role=:role ORDER BY sv.version_number DESC LIMIT 1');
        $s->execute(['id'=>$id,'role'=>$role]);
        $image=$s->fetch();
        if(!$image||!in_array($image['mime_type'],['image/jpeg','image/png','image/webp'],true)){http_response_code(404);exit;}
        header('Cache-Control: private, no-store');
        StorageService::stream(dirname(__DIR__),$image,false);
    }

    private static function saveArtwork(PDO $db,array $actor,int $id,string $action,string $role,array $upload):void
    {
        if(!in_array($role,['poster','banner','thumbnail'],true)||!self::production($db,$id))throw new RuntimeException('Invalid production artwork.');
        if($action==='artwork_remove'){
            $s=$db->prepare('DELETE FROM production_artwork WHERE production_id=:id AND role=:role');
            $s->execute(['id'=>$id,'role'=>$role]);
        }else{
            if(($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Choose an image.');
            $mime=(new finfo(FILEINFO_MIME_TYPE))->file((string)$upload['tmp_name']);
            if(!in_array($mime,['image/jpeg','image/png','image/webp'],true))throw new RuntimeException('Only JPG, PNG and WebP images are supported.');
            $db->beginTransaction();
            try{
                $stored=StorageService::store($db,dirname(__DIR__),(int)$actor['id'],$upload);
                $stmt=$db->prepare('INSERT INTO production_artwork (production_id,role,stored_file_id,updated_by_user_id) VALUES (:id,:role,:file,:actor) ON DUPLICATE KEY UPDATE stored_file_id=VALUES(stored_file_id),updated_by_user_id=VALUES(updated_by_user_id)');
                $stmt->execute(['id'=>$id,'role'=>$role,'file'=>$stored['stored_file_id'],'actor'=>$actor['id']]);
                $db->commit();
            }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        }
        self::audit($db,(int)$actor['id'],'production.artwork_changed',$id,'Production artwork updated.',['role'=>$role,'action'=>$action]);
    }

    private static function setAnnouncementPublished(PDO $db,array $actor,int $id,bool $published):void
    {
        if(!self::production($db,$id))throw new RuntimeException('Production unavailable.');
        $s=$db->prepare('UPDATE productions SET announcement_published_at='.($published?'CURRENT_TIMESTAMP':'NULL').' WHERE id=:id');
        $s->execute(['id'=>$id]);
        self::audit($db,(int)$actor['id'],$published?'production.announcement_published':'production.announcement_unpublished',$id,$published?'Approved production basic announcement.':'Withdrew production basic announcement.',[]);
    }

    private static function updateLifecycle(PDO $db,array $actor,int $id,array $input):void
    {
        $stages=['upcoming','planning','in_production','performing','completed','archived'];
        $stage=(string)($input['lifecycle_stage']??'');
        if(!in_array($stage,$stages,true))throw new RuntimeException('Choose a valid lifecycle stage.');
        $before=self::production($db,$id);
        if(!$before)throw new RuntimeException('Production not found.');
        // Terminal transitions cannot bypass the forthcoming audited readiness
        // workflow through the legacy stage/date editor.
        if($stage!==(string)$before['lifecycle_stage'] && (in_array($stage,['completed','archived'],true) || in_array((string)$before['lifecycle_stage'],['completed','archived'],true)))
            throw new RuntimeException('Completion, archival, and reopening require a separate authorized lifecycle confirmation workflow.');
        $dates=[];
        foreach(['audition_starts_at','audition_ends_at','performance_starts_at','performance_ends_at','archive_after_at'] as $field){
            $value=trim((string)($input[$field]??''));
            if($value!==''&&(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$value)||!checkdate((int)substr($value,5,2),(int)substr($value,8,2),(int)substr($value,0,4))))throw new RuntimeException('Use valid dates.');
            $dates[$field]=$value!==''?$value.' 00:00:00':null;
        }
        if($dates['audition_starts_at']&&$dates['audition_ends_at']&&$dates['audition_ends_at']<$dates['audition_starts_at'])throw new RuntimeException('Audition end must follow start.');
        if($dates['performance_starts_at']&&$dates['performance_ends_at']&&$dates['performance_ends_at']<$dates['performance_starts_at'])throw new RuntimeException('Performance end must follow start.');
        $paused=isset($input['lifecycle_automation_paused'])?1:0;
        $note=trim((string)($input['lifecycle_override_note']??''));
        if(mb_strlen($note)>500)throw new RuntimeException('Override note is too long.');
        if(in_array($stage,['performing','completed','archived'],true)&&!$dates['performance_ends_at'])throw new RuntimeException('Set the performance window before selecting this stage.');
        $stmt=$db->prepare('UPDATE productions SET lifecycle_stage=:stage,audition_starts_at=:as,audition_ends_at=:ae,performance_starts_at=:ps,performance_ends_at=:pe,archive_after_at=:archive,lifecycle_override_note=:note,lifecycle_automation_paused=:paused WHERE id=:id');
        $stmt->execute(['stage'=>$stage,'as'=>$dates['audition_starts_at'],'ae'=>$dates['audition_ends_at'],'ps'=>$dates['performance_starts_at'],'pe'=>$dates['performance_ends_at'],'archive'=>$dates['archive_after_at'],'note'=>$note?:null,'paused'=>$paused,'id'=>$id]);
        self::audit($db,(int)$actor['id'],'production.lifecycle_updated',$id,'Updated production lifecycle stage and windows.',['before'=>$before['lifecycle_stage']??null,'after'=>$stage,'override_note'=>$note?:null,'automation_paused'=>$paused]);
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
            $stmt = $db->prepare('SELECT id,title,status,is_active,lifecycle_stage FROM productions WHERE id=:id FOR UPDATE');
            $stmt->execute(['id'=>$id]);
            $production = $stmt->fetch();
            if (!$production) throw new RuntimeException('That production no longer exists.');
            if ((bool)$production['is_active'] === $active) { $db->commit(); return; }

            if ($active) {
                if(!in_array((string)$production['lifecycle_stage'],['upcoming','planning','in_production','performing'],true))
                    throw new RuntimeException('Closed productions cannot be reactivated through legacy controls.');
                $hasLegacyCurrent = (bool)$db->query("SELECT 1 FROM productions WHERE is_active=1 AND status='current' LIMIT 1")->fetchColumn();
                $status = $hasLegacyCurrent ? 'planning' : 'current';
                $update = $db->prepare('UPDATE productions SET is_active=1, status=:status, activated_at=CURRENT_TIMESTAMP, deactivated_at=NULL WHERE id=:id');
                $update->execute(['status'=>$status,'id'=>$id]);
            } else {
                $wasLegacyCurrent = $production['status'] === 'current';
                $update = $db->prepare("UPDATE productions SET is_active=0, status='archived', deactivated_at=CURRENT_TIMESTAMP WHERE id=:id");
                $update->execute(['id'=>$id]);
                if ($wasLegacyCurrent) {
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
        $selected = ProductionContext::select($db, $actor, $id);
        self::audit($db, (int)$actor['id'], 'production.workspace_selected', $id, 'Selected active production as this session’s operational workspace.', ['production_title'=>$selected['title']]);
    }

    private static function restorePlanning(PDO $db, array $actor, int $id): void
    {
        throw new RuntimeException('Restoring an archived production requires a separate safeguarded, audited review.');
        $production = self::production($db, $id);
        if (!$production) throw new RuntimeException('That production no longer exists.');
        if ((bool)$production['is_active']) throw new RuntimeException('Deactivate the production before returning it to Planning.');
        if ($production['status'] !== 'archived') throw new RuntimeException('Only an archived production can be returned to Planning.');
        $stmt = $db->prepare("UPDATE productions SET status='planning', is_active=0, deactivated_at=NULL WHERE id=:id AND is_active=0 AND status='archived'");
        $stmt->execute(['id'=>$id]);
        if ($stmt->rowCount() < 1) throw new RuntimeException('That production changed before it could be returned to Planning. Refresh and try again.');
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
        $stmt = $db->prepare("SELECT p.id,p.title,p.season,p.status,p.is_active,p.activated_at,p.deactivated_at,p.created_at,p.lifecycle_stage,p.audition_starts_at,p.audition_ends_at,p.performance_starts_at,p.performance_ends_at,p.archive_after_at,p.lifecycle_override_note,p.lifecycle_automation_paused,p.announcement_published_at,
            (SELECT COUNT(*) FROM production_memberships pm JOIN users u ON u.id=pm.user_id AND u.active=1 AND u.account_status<>'disabled' WHERE pm.production_id=p.id AND pm.status='active') active_members,
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
        return $db->query("SELECT p.id,p.title,p.season,p.status,p.lifecycle_stage,p.is_active,p.created_at,p.deactivated_at,p.audition_starts_at,p.audition_ends_at,p.performance_starts_at,p.performance_ends_at,
            (SELECT COUNT(*) FROM production_memberships pm JOIN users u ON u.id=pm.user_id AND u.active=1 AND u.account_status<>'disabled' WHERE pm.production_id=p.id AND pm.status='active') active_members,
            (SELECT COUNT(*) FROM schedule_items si WHERE si.production_id=p.id) schedule_items
            FROM productions p ORDER BY p.id DESC")->fetchAll();
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
        $sections = ProductionListOrganizer::split($productions);
        $workspace = ProductionContext::selected($db, $user);
        $planning = count(array_filter($productions, static fn(array $p): bool => !(bool)$p['is_active'] && $p['status']==='planning'));
        $inactive = count($productions) - count($active) - $planning;
        $title = $route === '/admin/productions/view' ? ($selected['title'] ?? 'Production') : 'Productions & seasons';
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#a6192e"><title><?= $esc($title) ?> · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/production-lifecycle.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar($route,$basePath,$user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Production',$title,$basePath); ?><div class="pl-page">
        <?php if ($flash): ?><div class="pl-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif; ?>
        <?php if ($route === '/admin/productions'): ?>
        <section class="pl-hero"><div><small>CONCURRENT PRODUCTIONS</small><h2>More than one show can be live.</h2><p>Active productions coexist. Selecting a workspace chooses which show this browser session is editing; it never changes another staff member’s workspace.</p></div><div class="pl-current"><small>YOUR WORKING PRODUCTION</small><b><?= $workspace ? $esc($workspace['title']) : 'None selected' ?></b><span><?= count($active) ?> active production<?= count($active)===1?'':'s' ?></span></div></section>
        <div class="pl-stats"><article><b><?= count($active) ?></b><span>active productions</span></article><article><b><?= $planning ?></b><span>planning</span></article><article><b><?= $inactive ?></b><span>inactive / archived</span></article></div>
        <div class="pl-layout"><section class="pl-panel"><header><small>PRODUCTION DIRECTORY</small><h3>Current &amp; Upcoming Productions</h3></header><div class="pl-list"><?php if(!$sections['current']):?><p class="pl-directory-empty">No current or upcoming productions.</p><?php endif;?><?php foreach($sections['current'] as $p): $isWorkspace=$workspace&&(int)$workspace['id']===(int)$p['id']; ?><a class="pl-row <?= (bool)$p['is_active'] ? 'current' : $esc($p['status']) ?>" href="<?= $url('/admin/productions/view?id='.(int)$p['id']) ?>"><div><small><?= (bool)$p['is_active'] ? (($isWorkspace?'YOUR WORKSPACE · ':'').'ACTIVE') : $esc(strtoupper($p['status'])) ?></small><h4><?= $esc($p['title']) ?></h4><p><?= $esc((string)$p['season']) ?></p><span><?= (int)$p['active_members'] ?> people · <?= (int)$p['schedule_items'] ?> schedule items</span></div><em>Manage →</em></a><?php endforeach; ?></div><details class="pl-past-productions"><summary><span>Past Productions <small><?= count($sections['past']) ?> completed or archived</small></span><span class="pl-past-chevron" aria-hidden="true">⌄</span></summary><div class="pl-list"><?php foreach($sections['past'] as $p):?><a class="pl-row past" href="<?= $url('/admin/productions/view?id='.(int)$p['id']) ?>"><div><small><?= $esc(strtoupper((string)($p['lifecycle_stage']??$p['status']))) ?></small><h4><?= $esc($p['title']) ?></h4><p><?= $esc((string)$p['season']) ?></p><span><?php if(!empty($p['performance_ends_at'])):?>Last performance <?= $esc(date('M j, Y',strtotime($p['performance_ends_at']))) ?> · <?php endif;?><?= (int)$p['active_members'] ?> people · <?= (int)$p['schedule_items'] ?> schedule items</span></div><em>View →</em></a><?php endforeach;?><?php if(!$sections['past']):?><p class="pl-directory-empty">No past productions yet.</p><?php endif;?></div></details></section><aside class="pl-panel create"><small>NEXT SHOW</small><h3>Create a production</h3><p>New productions begin in Planning and can be activated whenever their community and operational spaces should become available.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_lifecycle_csrf']) ?>"><input type="hidden" name="action" value="create"><label>Production title<input name="title" maxlength="190" required></label><label>Season / cycle<input name="season" maxlength="100" required></label><button class="button full" type="submit">Create planning production</button></form></aside></div>
        <?php else: ?>
        <?php if (!$selected): ?><section class="pl-empty"><b>Production not found.</b><a class="button" href="<?= $url('/admin/productions') ?>">Back to productions</a></section><?php else: $selectedIsWorkspace=$workspace&&(int)$workspace['id']===(int)$selected['id']; ?>
        <section class="pl-detail-head"><div><small><?= (bool)$selected['is_active'] ? (($selectedIsWorkspace?'YOUR WORKSPACE · ':'').'ACTIVE') : $esc(strtoupper($selected['status'])) ?> · <?= $esc((string)$selected['season']) ?></small><h2><?= $esc($selected['title']) ?></h2><p><?= (int)$selected['active_members'] ?> people · <?= (int)$selected['schedule_items'] ?> schedule items · <?= (int)$selected['volunteer_shifts'] ?> shifts · <?= (int)$selected['channels'] ?> channels</p></div><a href="<?= $url('/admin/productions') ?>">← All productions</a></section>
        <?php if((string)$selected['lifecycle_stage']==='completed'): ?>
        <section class="pl-panel"><small>CONTROLLED ARCHIVAL</small><h3>Archive this completed production</h3>
        <p>Archival closes the active production workspace, removes current volunteer opportunities and preserves casting, roster, forms, attendance, Theatre History, historical files and communications subject to authorization.</p>
        <form method="post" class="pl-detail-form"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_lifecycle_csrf']) ?>"><input type="hidden" name="production_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="action" value="archive_production">
        <label>Type the production title to confirm<input name="confirm_title" required autocomplete="off"></label><label>Archive note (optional)<textarea name="archive_note" maxlength="500"></textarea></label>
        <label><input type="checkbox" name="acknowledge_archive" value="1" required> I understand this will close the active workspace while preserving authorized history.</label>
        <button class="button" type="submit">Archive Production</button></form></section>
        <?php endif; ?>
        <?php if(in_array((string)$selected['lifecycle_stage'],['in_production','performing'],true)): $readiness=ProductionCompletionService::readiness($db,(int)$selected['id']); ?>
        <section class="pl-panel"><small>CONTROLLED COMPLETION</small><h3>Review completion readiness</h3><p>Completion makes this production's operational records read-only. Historical records are preserved.</p>
        <ul><?php foreach($readiness as $item): ?><li><?= $esc($item['description']) ?>: <b><?= (int)$item['count'] ?></b> (<?= $esc($item['severity']) ?>)</li><?php endforeach; ?></ul><?php $blocked=count(array_filter($readiness,static fn(array $item):bool=>$item['severity']==='blocker' && $item['count']>0))>0; if($blocked): ?><p role="alert"><b>Completion is blocked.</b> Resolve the blocking items before confirming.</p><?php endif; ?>
        <form method="post" class="pl-detail-form"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_lifecycle_csrf']) ?>"><input type="hidden" name="production_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="action" value="complete_production">
        <label>Type the production title to confirm<input name="confirm_title" required autocomplete="off"></label>
        <label>Completion note (optional)<textarea name="completion_note" maxlength="500"></textarea></label>
        <label><input type="checkbox" name="acknowledge_outstanding" value="1" required> I acknowledge outstanding items will remain as preserved, read-only history.</label>
        <button class="button" type="submit" <?= $blocked?'disabled':'' ?>>Complete Production</button></form></section>
        <?php endif; ?>
        <section class="pl-panel"><small>ANNOUNCEMENT</small><h3>Production discovery</h3><p>Publishing makes only the show's name, season, and lifecycle stage available to other signed-in CTSMD members. Private schedules, groups and casting remain restricted.</p><p><b><?= $selected['announcement_published_at']?'Published':'Not published' ?></b></p><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_lifecycle_csrf']) ?>"><input type="hidden" name="production_id" value="<?= (int)$selected['id'] ?>"><button class="button" type="submit" name="action" value="<?= $selected['announcement_published_at']?'announcement_unpublish':'announcement_publish' ?>"><?= $selected['announcement_published_at']?'Unpublish basics':'Publish basics' ?></button></form></section><section class="pl-panel"><small>LIFECYCLE</small><h3>Production stage &amp; dates</h3><p style="font-size:13px">Stage labels are being migrated. Until permission parity is complete, existing active membership controls still protect schedules and private records.</p><form method="post" class="pl-detail-form"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_lifecycle_csrf']) ?>"><input type="hidden" name="production_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="action" value="lifecycle_update"><label>Stage<select name="lifecycle_stage"><?php foreach(['upcoming'=>'Upcoming','planning'=>'Planning','in_production'=>'In production','performing'=>'Performing','completed'=>'Completed','archived'=>'Archived'] as $key=>$label):?><option value="<?= $key ?>" <?= ($selected['lifecycle_stage']??'')===$key?'selected':'' ?>><?= $label ?></option><?php endforeach;?></select></label><?php foreach(['audition_starts_at'=>'Audition start','audition_ends_at'=>'Audition end','performance_starts_at'=>'Performance start','performance_ends_at'=>'Performance end','archive_after_at'=>'Archive after'] as $key=>$label):?><label><?= $label ?><input type="date" name="<?= $key ?>" value="<?= $esc(substr((string)($selected[$key]??''),0,10)) ?>"></label><?php endforeach;?><label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="lifecycle_automation_paused" value="1" <?= !empty($selected['lifecycle_automation_paused'])?'checked':'' ?>>Pause automatic stage transitions</label><label>Override explanation<input name="lifecycle_override_note" maxlength="500" value="<?= $esc((string)($selected['lifecycle_override_note']??'')) ?>"></label><button class="button" type="submit">Save lifecycle</button></form></section><section class="pl-panel"><small>PRODUCTION VISUAL</small><h3>Production banner</h3><img src="<?= $url('/admin/productions/artwork-image') ?>?id=<?= (int)$selected['id'] ?>&amp;role=banner" alt="<?= $esc((string)$selected['title']) ?> banner" style="display:block;width:100%;max-height:210px;object-fit:cover;border-radius:12px;background:#180e13" onerror="this.style.display='none'"></section><section class="pl-panel"><small>SHOW ARTWORK</small><h3>Production images</h3><p>Upload banner, poster, or thumbnail. JPG, PNG, WebP.</p><?php foreach(['banner','poster','thumbnail'] as $artRole): ?><div style="padding:10px 0"><b><?= $esc(ucfirst($artRole)) ?></b><?php $artCheck=$db->prepare('SELECT COUNT(*) FROM production_artwork WHERE production_id=:id AND role=:role');$artCheck->execute(['id'=>(int)$selected['id'],'role'=>$artRole]);$hasArt=(bool)$artCheck->fetchColumn(); ?><?php if($hasArt): ?><p style="margin:8px 0">Uploaded · <a target="_blank" rel="noopener" href="<?= $url('/admin/productions/artwork-image') ?>?id=<?= (int)$selected['id'] ?>&amp;role=<?= $esc($artRole) ?>">View full image</a></p><img alt="<?= $esc(ucfirst($artRole)) ?> artwork preview" src="<?= $url('/admin/productions/artwork-image') ?>?id=<?= (int)$selected['id'] ?>&amp;role=<?= $esc($artRole) ?>" style="display:block;max-width:100%;max-height:130px;object-fit:contain;border-radius:8px;margin:7px 0"><?php else: ?><p style="margin:8px 0;color:#667085">No image uploaded for this slot.</p><?php endif; ?><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_lifecycle_csrf']) ?>"><input type="hidden" name="production_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="artwork_role" value="<?= $esc($artRole) ?>"><input type="file" name="artwork_upload" accept="image/png,image/jpeg,image/webp" required><button class="button" type="submit" name="action" value="artwork_save">Upload / replace</button></form><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_lifecycle_csrf']) ?>"><input type="hidden" name="production_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="artwork_role" value="<?= $esc($artRole) ?>"><button class="secondary" type="submit" name="action" value="artwork_remove">Remove</button></form></div><?php endforeach;?></section><div class="pl-detail-layout"><section class="pl-panel"><small>IDENTITY</small><h3>Production details</h3><form method="post" class="pl-detail-form"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_lifecycle_csrf']) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="production_id" value="<?= (int)$selected['id'] ?>"><label>Title<input name="title" maxlength="190" required value="<?= $esc($selected['title']) ?>"></label><label>Season<input name="season" maxlength="100" required value="<?= $esc((string)$selected['season']) ?>"></label><button class="button" type="submit">Save details</button></form></section><aside class="pl-panel create"><small>ACTIVITY</small><h3><?= (bool)$selected['is_active'] ? 'This show is active' : 'This show is inactive' ?></h3><p><?= (bool)$selected['is_active'] ? 'Cast and family production access is available while their memberships remain active.' : 'Production-specific community access is closed to cast and families; public organization channels remain available.' ?></p><?php if ((bool)$selected['is_active']): ?><?php if (!$selectedIsWorkspace): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_lifecycle_csrf']) ?>"><input type="hidden" name="production_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="action" value="select_workspace"><button class="button full" type="submit">Work in this production</button></form><?php else: ?><p><b>This is your current working production.</b></p><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_lifecycle_csrf']) ?>"><input type="hidden" name="production_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="action" value="deactivate"><button class="secondary" type="submit">Deactivate show</button></form><?php else: ?><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_lifecycle_csrf']) ?>"><input type="hidden" name="production_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="action" value="activate"><button class="button full" type="submit">Activate show</button></form><?php if ($selected['status']==='archived'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_lifecycle_csrf']) ?>"><input type="hidden" name="production_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="action" value="restore"><button class="secondary" type="submit">Return to Planning</button></form><?php endif; ?><?php endif; ?></aside></div>
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
