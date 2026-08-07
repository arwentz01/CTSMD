<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/ProductionContext.php';

final class FormManagementExperience
{
    private const ROUTES = ['/admin/forms/manage', '/admin/forms/manage/edit', '/admin/forms/manage/assign'];

    public static function handles(string $route): bool
    {
        return in_array($route, self::ROUTES, true);
    }

    public static function render(string $route, string $basePath): never
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $db = Database::connect(dirname(__DIR__));
        $user = self::currentUser($db);
        if (!AccessPolicy::isStaff($user)) self::forbidden($basePath, $user);
        $_SESSION['form_manage_csrf'] ??= bin2hex(random_bytes(24));

        if ($_SERVER['REQUEST_METHOD'] === 'POST') self::handlePost($db, $user, $route, $basePath);

        $selectedProduction = ProductionContext::selected($db, $user);
        $form = null;
        if (in_array($route, ['/admin/forms/manage/edit', '/admin/forms/manage/assign'], true)) {
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
            $form = self::form($db, (int)$id);
        }

        self::page($route, $basePath, $db, $user, $selectedProduction, $form);
    }

    private static function handlePost(PDO $db, array $user, string $route, string $basePath): never
    {
        self::assertCsrf();
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'save_form') {
                $id = filter_input(INPUT_POST, 'form_id', FILTER_VALIDATE_INT) ?: 0;
                $saved = self::saveForm($db, $user, (int)$id, $_POST);
                self::flash('success', $id ? 'Form settings updated.' : 'Form created.');
                self::redirect($basePath . '/admin/forms/manage/edit?id=' . $saved);
            }
            if ($action === 'toggle_active') {
                $id = filter_input(INPUT_POST, 'form_id', FILTER_VALIDATE_INT) ?: 0;
                self::toggleActive($db, $user, (int)$id);
                self::flash('success', 'Form availability updated.');
                self::redirect($basePath . '/admin/forms/manage');
            }
            if ($action === 'assign') {
                $id = filter_input(INPUT_POST, 'form_id', FILTER_VALIDATE_INT) ?: 0;
                $count = self::assign($db, $user, (int)$id, $_POST);
                self::flash('success', $count . ' new assignment' . ($count === 1 ? '' : 's') . ' created.');
                self::redirect($basePath . '/admin/forms/manage/assign?id=' . (int)$id);
            }
            throw new RuntimeException('Choose a valid forms operation.');
        } catch (RuntimeException $e) {
            self::flash('error', $e->getMessage());
            $id = filter_input(INPUT_POST, 'form_id', FILTER_VALIDATE_INT) ?: 0;
            $fallback = $route === '/admin/forms/manage/assign' && $id ? '/admin/forms/manage/assign?id=' . $id : ($route === '/admin/forms/manage/edit' && $id ? '/admin/forms/manage/edit?id=' . $id : '/admin/forms/manage');
            self::redirect($basePath . $fallback);
        }
    }

    private static function saveForm(PDO $db, array $actor, int $formId, array $input): int
    {
        $title = trim((string)($input['title'] ?? ''));
        $type = trim((string)($input['form_type'] ?? 'acknowledgment'));
        $instructions = trim((string)($input['instructions'] ?? ''));
        $completionMode = (string)($input['completion_mode'] ?? 'acknowledgment');
        $reviewRequired = isset($input['review_required']) ? 1 : 0;
        $scope = (string)($input['scope'] ?? 'organization');

        if ($title === '' || mb_strlen($title) > 190) throw new RuntimeException('Enter a form title no longer than 190 characters.');
        if ($type === '' || mb_strlen($type) > 80) throw new RuntimeException('Enter a form type no longer than 80 characters.');
        if ($instructions === '' || mb_strlen($instructions) > 10000) throw new RuntimeException('Enter instructions up to 10,000 characters.');
        if (!in_array($completionMode, ['acknowledgment','signature','submission'], true)) throw new RuntimeException('Choose a valid completion mode.');
        if (!in_array($scope, ['organization','production'], true)) throw new RuntimeException('Choose a valid form scope.');

        $productionId = null;
        if ($scope === 'production') {
            $selected = ProductionContext::selected($db, $actor);
            if (!$selected) throw new RuntimeException('Select an active working production before creating a production form.');
            $productionId = (int)$selected['id'];
        }

        $db->beginTransaction();
        try {
            if ($formId > 0) {
                $beforeStmt = $db->prepare('SELECT id,production_id,title,form_type,instructions,completion_mode,review_required,active FROM forms WHERE id=:id FOR UPDATE');
                $beforeStmt->execute(['id'=>$formId]);
                $before = $beforeStmt->fetch();
                if (!$before) throw new RuntimeException('That form no longer exists.');
                $assignmentCountStmt = $db->prepare('SELECT COUNT(*) FROM form_assignments WHERE form_id=:id');
                $assignmentCountStmt->execute(['id'=>$formId]);
                if ((int)$assignmentCountStmt->fetchColumn() > 0 && (int)($before['production_id'] ?? 0) !== (int)($productionId ?? 0)) {
                    throw new RuntimeException('A form with assignments cannot change between organization and production scope. Create a new form instead.');
                }
                $stmt = $db->prepare('UPDATE forms SET production_id=:production_id,title=:title,form_type=:type,instructions=:instructions,completion_mode=:mode,review_required=:review WHERE id=:id');
                $stmt->execute(['production_id'=>$productionId,'title'=>$title,'type'=>$type,'instructions'=>$instructions,'mode'=>$completionMode,'review'=>$reviewRequired,'id'=>$formId]);
                self::audit($db,(int)$actor['id'],'form.definition_updated','form',$formId,'Updated form definition.',['before'=>$before,'after'=>['production_id'=>$productionId,'title'=>$title,'form_type'=>$type,'completion_mode'=>$completionMode,'review_required'=>(bool)$reviewRequired]]);
            } else {
                $stmt = $db->prepare('INSERT INTO forms (production_id,title,form_type,instructions,completion_mode,review_required,active,created_by_user_id) VALUES (:production_id,:title,:type,:instructions,:mode,:review,1,:creator)');
                $stmt->execute(['production_id'=>$productionId,'title'=>$title,'type'=>$type,'instructions'=>$instructions,'mode'=>$completionMode,'review'=>$reviewRequired,'creator'=>(int)$actor['id']]);
                $formId = (int)$db->lastInsertId();
                self::audit($db,(int)$actor['id'],'form.definition_created','form',$formId,'Created form definition.',['production_id'=>$productionId,'title'=>$title,'form_type'=>$type,'completion_mode'=>$completionMode,'review_required'=>(bool)$reviewRequired]);
            }
            $db->commit();
            return $formId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof RuntimeException) throw $e;
            throw new RuntimeException('The form could not be saved.');
        }
    }

    private static function toggleActive(PDO $db, array $actor, int $formId): void
    {
        if ($formId < 1) throw new RuntimeException('That form could not be found.');
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT id,title,active FROM forms WHERE id=:id FOR UPDATE');
            $stmt->execute(['id'=>$formId]);
            $form = $stmt->fetch();
            if (!$form) throw new RuntimeException('That form no longer exists.');
            $next = (int)$form['active'] ? 0 : 1;
            $db->prepare('UPDATE forms SET active=:active WHERE id=:id')->execute(['active'=>$next,'id'=>$formId]);
            self::audit($db,(int)$actor['id'],$next ? 'form.definition_activated' : 'form.definition_deactivated','form',$formId,$next ? 'Activated form definition.' : 'Deactivated form definition.',['title'=>$form['title']]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof RuntimeException) throw $e;
            throw new RuntimeException('The form availability could not be changed.');
        }
    }

    private static function assign(PDO $db, array $actor, int $formId, array $input): int
    {
        $form = self::form($db, $formId);
        if (!$form || !(bool)$form['active']) throw new RuntimeException('Choose an active form to assign.');
        $audience = (string)($input['audience'] ?? 'selected');
        $due = trim((string)($input['due_at'] ?? ''));
        $dueAt = null;
        if ($due !== '') {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $due);
            if (!$parsed) throw new RuntimeException('Enter a valid due date and time.');
            $dueAt = $parsed->format('Y-m-d H:i:s');
        }

        $productionId = $form['production_id'] !== null ? (int)$form['production_id'] : null;
        if ($productionId !== null) {
            $selected = ProductionContext::selected($db, $actor);
            if (!$selected || (int)$selected['id'] !== $productionId) throw new RuntimeException('Switch to this form’s production before assigning it.');
        }

        $userIds = self::resolveAudience($db, $productionId, $audience, (array)($input['user_ids'] ?? []));
        if (!$userIds) throw new RuntimeException('That audience does not currently contain any eligible people.');

        $db->beginTransaction();
        try {
            $created = 0;
            $existing = $db->prepare('SELECT id FROM form_assignments WHERE form_id=:form_id AND user_id=:user_id AND ((production_id IS NULL AND :production_id_null IS NULL) OR production_id=:production_id) LIMIT 1');
            $insert = $db->prepare("INSERT INTO form_assignments (form_id,production_id,user_id,status,due_at,completed_at,assigned_by_user_id) VALUES (:form_id,:production_id,:user_id,'missing',:due_at,NULL,:assigner)");
            foreach ($userIds as $userId) {
                $existing->execute(['form_id'=>$formId,'user_id'=>$userId,'production_id_null'=>$productionId,'production_id'=>$productionId]);
                if ($existing->fetchColumn()) continue;
                $insert->execute(['form_id'=>$formId,'production_id'=>$productionId,'user_id'=>$userId,'due_at'=>$dueAt,'assigner'=>(int)$actor['id']]);
                $assignmentId = (int)$db->lastInsertId();
                self::notify($db,$userId,'form_assignment',$assignmentId,'New form assigned · '.$form['title'],$dueAt ? 'A new CTSMD form is due ' . date('M j, Y', strtotime($dueAt)) . '.' : 'A new CTSMD form has been assigned to you.','/forms/view?id='.$assignmentId);
                $created++;
            }
            self::audit($db,(int)$actor['id'],'form.assignments_created','form',$formId,'Assigned form to audience.',['production_id'=>$productionId,'audience'=>$audience,'requested_user_ids'=>$userIds,'created_count'=>$created,'due_at'=>$dueAt]);
            $db->commit();
            return $created;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof RuntimeException) throw $e;
            throw new RuntimeException('The form assignments could not be created.');
        }
    }

    private static function resolveAudience(PDO $db, ?int $productionId, string $audience, array $selectedIds): array
    {
        $allowed = $productionId !== null
            ? ['selected','production_all','production_students','production_guardians','production_staff']
            : ['selected','all_active','adults','students','staff','volunteers'];
        if (!in_array($audience, $allowed, true)) throw new RuntimeException('Choose a valid assignment audience.');

        if ($audience === 'selected') {
            $ids = array_values(array_unique(array_filter(array_map('intval',$selectedIds),static fn(int $id):bool=>$id>0)));
            if (!$ids) return [];
            $placeholders = implode(',',array_fill(0,count($ids),'?'));
            if ($productionId !== null) {
                $stmt = $db->prepare("SELECT DISTINCT u.id FROM users u JOIN production_memberships pm ON pm.user_id=u.id WHERE u.active=1 AND pm.production_id=? AND pm.status='active' AND u.id IN ($placeholders)");
                $stmt->execute(array_merge([$productionId],$ids));
            } else {
                $stmt = $db->prepare("SELECT id FROM users WHERE active=1 AND id IN ($placeholders)");
                $stmt->execute($ids);
            }
            return array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
        }

        if ($productionId !== null) {
            $types = match($audience){'production_students'=>['student'],'production_guardians'=>['guardian'],'production_staff'=>['staff'],default=>['student','guardian','staff']};
            $ph = implode(',',array_fill(0,count($types),'?'));
            $stmt=$db->prepare("SELECT DISTINCT u.id FROM production_memberships pm JOIN users u ON u.id=pm.user_id WHERE pm.production_id=? AND pm.status='active' AND u.active=1 AND pm.audience_type IN ($ph) ORDER BY u.id");
            $stmt->execute(array_merge([$productionId],$types));
            return array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
        }

        $sql = match($audience){
            'all_active' => "SELECT id FROM users WHERE active=1 ORDER BY id",
            'students' => "SELECT id FROM users WHERE active=1 AND display_role LIKE '%Student%' ORDER BY id",
            'staff' => "SELECT id FROM users WHERE active=1 AND (display_role LIKE '%Director%' OR display_role LIKE '%Manager%' OR display_role LIKE '%Staff%' OR display_role LIKE '%Admin%') ORDER BY id",
            'volunteers' => "SELECT u.id FROM users u JOIN volunteer_profiles vp ON vp.user_id=u.id AND vp.active=1 WHERE u.active=1 ORDER BY u.id",
            'adults' => "SELECT id FROM users WHERE active=1 AND display_role NOT LIKE '%Student%' ORDER BY id",
            default => "SELECT id FROM users WHERE 1=0",
        };
        return array_map('intval',$db->query($sql)->fetchAll(PDO::FETCH_COLUMN));
    }

    private static function forms(PDO $db): array
    {
        return $db->query("SELECT f.id,f.production_id,f.title,f.form_type,f.completion_mode,f.review_required,f.active,p.title production_title,COUNT(fa.id) assignment_count,SUM(fa.status='completed') completed_count,SUM(fa.status='requires_review') review_count FROM forms f LEFT JOIN productions p ON p.id=f.production_id LEFT JOIN form_assignments fa ON fa.form_id=f.id GROUP BY f.id,f.production_id,f.title,f.form_type,f.completion_mode,f.review_required,f.active,p.title ORDER BY f.active DESC,p.title IS NOT NULL,p.title,f.title")->fetchAll();
    }

    private static function form(PDO $db, int $id): ?array
    {
        if ($id<1) return null;
        $stmt=$db->prepare("SELECT f.*,p.title production_title,p.is_active production_active FROM forms f LEFT JOIN productions p ON p.id=f.production_id WHERE f.id=:id LIMIT 1");
        $stmt->execute(['id'=>$id]);
        return $stmt->fetch() ?: null;
    }

    private static function assignments(PDO $db, int $formId): array
    {
        $stmt=$db->prepare("SELECT fa.id,fa.status,fa.due_at,fa.completed_at,fa.assigned_at,CONCAT(u.first_name,' ',u.last_name) assignee,u.display_role,p.title production_title FROM form_assignments fa JOIN users u ON u.id=fa.user_id LEFT JOIN productions p ON p.id=fa.production_id WHERE fa.form_id=:form_id ORDER BY CASE fa.status WHEN 'requires_review' THEN 1 WHEN 'missing' THEN 2 WHEN 'due_soon' THEN 3 ELSE 4 END,u.last_name,u.first_name");
        $stmt->execute(['form_id'=>$formId]);
        return $stmt->fetchAll();
    }

    private static function people(PDO $db, ?int $productionId): array
    {
        if ($productionId !== null) {
            $stmt=$db->prepare("SELECT DISTINCT u.id,CONCAT(u.first_name,' ',u.last_name) name,u.display_role role FROM users u JOIN production_memberships pm ON pm.user_id=u.id WHERE u.active=1 AND pm.production_id=:production_id AND pm.status='active' ORDER BY u.last_name,u.first_name");
            $stmt->execute(['production_id'=>$productionId]);
            return $stmt->fetchAll();
        }
        return $db->query("SELECT id,CONCAT(first_name,' ',last_name) name,display_role role FROM users WHERE active=1 ORDER BY last_name,first_name")->fetchAll();
    }

    private static function currentUser(PDO $db): array
    {
        $row=$db->query("SELECT id,CONCAT(first_name,' ',last_name) name,display_role role,initials FROM users WHERE is_demo_current_user=1 AND active=1 LIMIT 1")->fetch();
        if(!$row) throw new RuntimeException('Demo user is missing.');
        return $row;
    }

    private static function audit(PDO $db,int $actorId,string $event,string $subjectType,int $subjectId,string $summary,array $metadata):void
    {
        $stmt=$db->prepare('INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,:event,:subject_type,:subject_id,:summary,:metadata)');
        $stmt->execute(['actor'=>$actorId,'event'=>$event,'subject_type'=>$subjectType,'subject_id'=>$subjectId,'summary'=>$summary,'metadata'=>json_encode($metadata,JSON_THROW_ON_ERROR)]);
    }

    private static function notify(PDO $db,int $recipientId,string $sourceType,int $sourceId,string $title,string $body,string $path):void
    {
        $stmt=$db->prepare("INSERT INTO app_notifications (recipient_user_id,source_type,source_id,title,body,action_path) VALUES (:recipient,:source_type,:source_id,:title,:body,:path) ON DUPLICATE KEY UPDATE title=VALUES(title),body=VALUES(body),action_path=VALUES(action_path),read_at=NULL,created_at=CURRENT_TIMESTAMP");
        $stmt->execute(['recipient'=>$recipientId,'source_type'=>$sourceType,'source_id'=>$sourceId,'title'=>$title,'body'=>$body,'path'=>$path]);
    }

    private static function assertCsrf():void
    {
        $token=(string)($_POST['csrf_token']??'');
        if(!hash_equals((string)($_SESSION['form_manage_csrf']??''),$token)) throw new RuntimeException('Your session token expired. Please try again.');
    }
    private static function flash(string $type,string $message):void{$_SESSION['form_manage_flash']=['type'=>$type,'message'=>$message];}
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}

    private static function page(string $route,string $basePath,PDO $db,array $user,?array $selectedProduction,?array $form):never
    {
        $url=static fn(string $path):string=>($basePath?:'').$path;
        $esc=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');
        $flash=$_SESSION['form_manage_flash']??null;unset($_SESSION['form_manage_flash']);
        $forms=self::forms($db);
        $assignments=$form?self::assignments($db,(int)$form['id']):[];
        $people=$form?self::people($db,$form['production_id']!==null?(int)$form['production_id']:null):[];
        $editing=$route==='/admin/forms/manage/edit';
        $assigning=$route==='/admin/forms/manage/assign';
        $title=$editing?($form['title']??'Form settings'):($assigning?($form['title']??'Assign form'):'Forms management');
        $subnav=[
            ['label'=>'Review queue','href'=>'/admin/forms','active'=>false],
            ['label'=>'Manage forms','href'=>'/admin/forms/manage','active'=>true],
        ];
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#a6192e"><title><?= $esc($title) ?> · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/form-management.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar($route,$basePath,$user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Operations',$title,$basePath,$subnav); ?><div class="fm-page">
        <?php if($flash):?><div class="fm-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif; ?>
        <?php if($route==='/admin/forms/manage'):?>
        <section class="fm-hero"><div><small>FORM LIBRARY</small><h2>Create once. Assign with context.</h2><p>Organization forms can serve anyone. Production forms stay attached to one show and can be bulk-assigned to its cast, guardians, or staff.</p></div><a class="button" href="<?= $url('/admin/forms/manage/edit') ?>">Create form</a></section>
        <div class="fm-grid"><?php if(!$forms):?><div class="fm-empty"><b>No forms yet.</b></div><?php endif; ?><?php foreach($forms as $row):?><article class="fm-card<?= !$row['active']?' inactive':'' ?>"><header><div><small><?= $row['production_title']?$esc($row['production_title']):'ORGANIZATION' ?></small><h3><?= $esc($row['title']) ?></h3></div><span><?= $row['active']?'ACTIVE':'INACTIVE' ?></span></header><p><?= $esc(ucfirst($row['completion_mode'])) ?> · <?= $row['review_required']?'Staff review':'Automatic completion' ?></p><div><b><?= (int)$row['assignment_count'] ?></b><small>assigned</small><b><?= (int)$row['completed_count'] ?></b><small>complete</small><b><?= (int)$row['review_count'] ?></b><small>review</small></div><footer><a href="<?= $url('/admin/forms/manage/edit?id='.(int)$row['id']) ?>">Settings</a><a href="<?= $url('/admin/forms/manage/assign?id='.(int)$row['id']) ?>">Assign</a><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['form_manage_csrf']) ?>"><input type="hidden" name="action" value="toggle_active"><input type="hidden" name="form_id" value="<?= (int)$row['id'] ?>"><button type="submit"><?= $row['active']?'Deactivate':'Activate' ?></button></form></footer></article><?php endforeach; ?></div>
        <?php elseif($editing): ?>
        <?php $f=$form??['id'=>0,'production_id'=>null,'title'=>'','form_type'=>'acknowledgment','instructions'=>'','completion_mode'=>'acknowledgment','review_required'=>0,'active'=>1]; ?>
        <section class="fm-head"><div><small><?= $form?'FORM SETTINGS':'NEW FORM' ?></small><h2><?= $form?$esc($form['title']):'Create a form' ?></h2><p>Production scope uses your current working production. Once assignments exist, scope cannot be changed.</p></div><a href="<?= $url('/admin/forms/manage') ?>">← Form library</a></section>
        <form class="fm-form" method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['form_manage_csrf']) ?>"><input type="hidden" name="action" value="save_form"><input type="hidden" name="form_id" value="<?= (int)$f['id'] ?>"><label>Title<input name="title" maxlength="190" required value="<?= $esc((string)$f['title']) ?>" placeholder="Cast participation agreement"></label><div class="fm-pair"><label>Form type<input name="form_type" maxlength="80" required value="<?= $esc((string)$f['form_type']) ?>" placeholder="agreement"></label><label>Scope<select name="scope"><option value="organization"<?= $f['production_id']===null?' selected':'' ?>>Organization-wide</option><option value="production"<?= $f['production_id']!==null?' selected':'' ?>>Working production<?= $selectedProduction?' · '.$esc($selectedProduction['title']):'' ?></option></select></label></div><label>Instructions<textarea name="instructions" rows="8" maxlength="10000" required><?= $esc((string)$f['instructions']) ?></textarea></label><div class="fm-pair"><label>Completion mode<select name="completion_mode"><?php foreach(['acknowledgment'=>'Acknowledgment','signature'=>'Typed signature','submission'=>'Information submission'] as $value=>$label):?><option value="<?= $value ?>"<?= $f['completion_mode']===$value?' selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label><label class="fm-check"><input type="checkbox" name="review_required" value="1"<?= (bool)$f['review_required']?' checked':'' ?>><span><b>Staff review required</b><small>Submission remains pending until staff approves it.</small></span></label></div><footer><a href="<?= $url('/admin/forms/manage') ?>">Cancel</a><button class="button" type="submit">Save form</button></footer></form>
        <?php else: ?>
        <?php if(!$form):?><section class="fm-empty"><b>Form not found.</b><a class="button" href="<?= $url('/admin/forms/manage') ?>">Back to forms</a></section><?php else:?>
        <section class="fm-head"><div><small><?= $form['production_title']?$esc($form['production_title']):'ORGANIZATION FORM' ?></small><h2><?= $esc($form['title']) ?></h2><p>Assign this form in bulk or choose specific people. Existing assignments are never duplicated.</p></div><a href="<?= $url('/admin/forms/manage') ?>">← Form library</a></section>
        <div class="fm-layout"><form class="fm-form" method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['form_manage_csrf']) ?>"><input type="hidden" name="action" value="assign"><input type="hidden" name="form_id" value="<?= (int)$form['id'] ?>"><label>Audience<select name="audience"><?php if($form['production_id']!==null):?><option value="production_all">Everyone in production</option><option value="production_students">Students / cast</option><option value="production_guardians">Guardians</option><option value="production_staff">Production staff</option><?php else:?><option value="all_active">All active users</option><option value="adults">Adults only</option><option value="students">Students</option><option value="staff">Staff</option><option value="volunteers">Active volunteers</option><?php endif; ?><option value="selected">Selected people</option></select></label><label>Due date <span>optional</span><input type="datetime-local" name="due_at"></label><fieldset><legend>Selected people</legend><p>Used only when “Selected people” is chosen.</p><div class="fm-people"><?php foreach($people as $person):?><label><input type="checkbox" name="user_ids[]" value="<?= (int)$person['id'] ?>"><span><b><?= $esc($person['name']) ?></b><small><?= $esc($person['role']) ?></small></span></label><?php endforeach; ?></div></fieldset><button class="button" type="submit">Create assignments</button></form><aside class="fm-side"><small>ASSIGNMENT STATUS</small><h3><?= count($assignments) ?> people assigned</h3><p>Submitting creates a notification for each newly assigned person. Existing assignments in the same form + production context are skipped.</p><a href="<?= $url('/admin/forms') ?>">Open review queue →</a></aside></div>
        <section class="fm-roster"><header><div><small>CURRENT ASSIGNMENTS</small><h3>Completion roster</h3></div><span><?= count($assignments) ?></span></header><?php if(!$assignments):?><div class="fm-empty compact"><b>No assignments yet.</b></div><?php else:foreach($assignments as $row):?><article><div><b><?= $esc($row['assignee']) ?></b><small><?= $esc($row['display_role']) ?><?= $row['production_title']?' · '.$esc($row['production_title']):'' ?></small></div><span class="<?= $esc($row['status']) ?>"><?= $esc(ucwords(str_replace('_',' ',$row['status']))) ?></span><time><?= $row['due_at']?'Due '.$esc(date('M j, Y',strtotime($row['due_at']))):'No due date' ?></time></article><?php endforeach;endif;?></section>
        <?php endif;endif; ?>
        </div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php exit;
    }

    private static function forbidden(string $basePath,array $user):never
    {
        $url=static fn(string $path):string=>($basePath?:'').$path;
        http_response_code(403);header('Content-Type:text/html; charset=utf-8');
        ?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/forms',$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Forms','Restricted',$basePath);?></main></div></body></html><?php exit;
    }
}
