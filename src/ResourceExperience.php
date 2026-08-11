<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/ProductionContext.php';

final class ResourceExperience
{
    private const ROUTES = ['/resources', '/resources/view', '/admin/resources', '/admin/resources/edit'];
    private const AUDIENCES = [
        'production_all' => 'Everyone in production',
        'production_adults' => 'Production adults',
        'production_students' => 'Students / cast',
        'production_guardians' => 'Guardians',
        'production_staff' => 'Production staff',
    ];

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
        $staff = AccessPolicy::canManageResources($user);
        $admin = str_starts_with($route, '/admin/resources');
        if ($admin && !$staff) self::forbidden($basePath, $user);

        $_SESSION['resource_csrf'] ??= bin2hex(random_bytes(24));
        $production = ProductionContext::selected($db, $user);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $admin) {
            self::handlePost($db, $user, $production, $route, $basePath);
        }

        $resource = null;
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
        if ($id > 0 && in_array($route, ['/resources/view', '/admin/resources/edit'], true)) {
            $resource = self::resource($db, (int)$id);
            if ($resource && (!$production || (int)$resource['production_id'] !== (int)$production['id'])) {
                $resource = null;
            }
            if ($resource && !$staff && !self::canRead($db, $user, $resource)) {
                $resource = null;
            }
        }

        $resources = $production ? self::resources($db, $user, (int)$production['id'], $admin) : [];
        self::page($route, $basePath, $db, $user, $production, $resources, $resource, $staff);
    }

    private static function handlePost(PDO $db, array $user, ?array $production, string $route, string $basePath): never
    {
        $token = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals((string)($_SESSION['resource_csrf'] ?? ''), $token)) {
            self::flash('error', 'Your session token expired. Please try again.');
            self::redirect($basePath . '/admin/resources');
        }
        if (!$production) {
            self::flash('error', 'Select an active production before managing resources.');
            self::redirect($basePath . '/production');
        }

        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'save') {
                $id = filter_input(INPUT_POST, 'resource_id', FILTER_VALIDATE_INT) ?: 0;
                $saved = self::save($db, $user, (int)$production['id'], (int)$id, $_POST);
                self::flash('success', $id ? 'Resource updated.' : 'Resource published to the selected audience.');
                self::redirect($basePath . '/admin/resources/edit?id=' . $saved);
            }
            if ($action === 'toggle_archive') {
                $id = filter_input(INPUT_POST, 'resource_id', FILTER_VALIDATE_INT) ?: 0;
                self::toggleArchive($db, $user, (int)$production['id'], (int)$id);
                self::flash('success', 'Resource availability updated.');
                self::redirect($basePath . '/admin/resources');
            }
            throw new RuntimeException('Choose a valid resource action.');
        } catch (RuntimeException $e) {
            self::flash('error', $e->getMessage());
            $id = filter_input(INPUT_POST, 'resource_id', FILTER_VALIDATE_INT) ?: 0;
            self::redirect($basePath . ($id ? '/admin/resources/edit?id=' . (int)$id : '/admin/resources'));
        }
    }

    private static function save(PDO $db, array $actor, int $productionId, int $resourceId, array $input): int
    {
        $title = trim((string)($input['title'] ?? ''));
        $category = trim((string)($input['category'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $type = (string)($input['resource_type'] ?? 'link');
        $url = trim((string)($input['resource_url'] ?? ''));
        $body = trim((string)($input['body'] ?? ''));
        $pinned = isset($input['pinned']) ? 1 : 0;
        $audiences = array_values(array_unique(array_filter((array)($input['audiences'] ?? []), static fn($value): bool => isset(self::AUDIENCES[(string)$value]))));

        if ($title === '' || mb_strlen($title) > 190) throw new RuntimeException('Enter a title no longer than 190 characters.');
        if ($category === '' || mb_strlen($category) > 100) throw new RuntimeException('Enter a category no longer than 100 characters.');
        if (mb_strlen($description) > 500) throw new RuntimeException('Keep the description under 500 characters.');
        if (!in_array($type, ['link', 'note'], true)) throw new RuntimeException('Choose a valid resource type.');
        if (!$audiences) throw new RuntimeException('Choose at least one audience.');
        if ($type === 'link') {
            if ($url === '' || mb_strlen($url) > 1000 || !filter_var($url, FILTER_VALIDATE_URL)) {
                throw new RuntimeException('Enter a valid resource URL.');
            }
            $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
            if (!in_array($scheme, ['http', 'https'], true)) throw new RuntimeException('Resource links must use http or https.');
            $body = '';
        } else {
            if ($body === '' || mb_strlen($body) > 10000) throw new RuntimeException('Enter resource notes up to 10,000 characters.');
            $url = '';
        }

        $db->beginTransaction();
        try {
            $before = null;
            if ($resourceId > 0) {
                $stmt = $db->prepare('SELECT * FROM production_resources WHERE id=:id AND production_id=:production_id FOR UPDATE');
                $stmt->execute(['id' => $resourceId, 'production_id' => $productionId]);
                $before = $stmt->fetch();
                if (!$before) throw new RuntimeException('That resource no longer exists in this production.');

                $update = $db->prepare('UPDATE production_resources SET title=:title,category=:category,description=:description,resource_type=:type,resource_url=:url,body=:body,audiences_json=:audiences,pinned=:pinned WHERE id=:id');
                $update->execute([
                    'title'=>$title,'category'=>$category,'description'=>$description !== '' ? $description : null,
                    'type'=>$type,'url'=>$url !== '' ? $url : null,'body'=>$body !== '' ? $body : null,
                    'audiences'=>json_encode($audiences, JSON_THROW_ON_ERROR),'pinned'=>$pinned,'id'=>$resourceId,
                ]);
            } else {
                $insert = $db->prepare("INSERT INTO production_resources (production_id,created_by_user_id,title,category,description,resource_type,resource_url,body,audiences_json,pinned,status) VALUES (:production_id,:creator,:title,:category,:description,:type,:url,:body,:audiences,:pinned,'active')");
                $insert->execute([
                    'production_id'=>$productionId,'creator'=>(int)$actor['id'],'title'=>$title,'category'=>$category,
                    'description'=>$description !== '' ? $description : null,'type'=>$type,'url'=>$url !== '' ? $url : null,
                    'body'=>$body !== '' ? $body : null,'audiences'=>json_encode($audiences, JSON_THROW_ON_ERROR),'pinned'=>$pinned,
                ]);
                $resourceId = (int)$db->lastInsertId();
            }

            self::audit($db, (int)$actor['id'], $before ? 'resource.updated' : 'resource.created', 'production_resource', $resourceId, $before ? 'Updated production resource.' : 'Created production resource.', [
                'production_id'=>$productionId,
                'before'=>$before ? ['title'=>$before['title'],'category'=>$before['category'],'resource_type'=>$before['resource_type'],'audiences_json'=>$before['audiences_json'],'pinned'=>(bool)$before['pinned']] : null,
                'after'=>['title'=>$title,'category'=>$category,'resource_type'=>$type,'audiences'=>$audiences,'pinned'=>(bool)$pinned],
            ]);
            $db->commit();
            return $resourceId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof RuntimeException) throw $e;
            throw new RuntimeException('The resource could not be saved.');
        }
    }

    private static function toggleArchive(PDO $db, array $actor, int $productionId, int $resourceId): void
    {
        if ($resourceId < 1) throw new RuntimeException('That resource could not be found.');
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT id,title,status FROM production_resources WHERE id=:id AND production_id=:production_id FOR UPDATE');
            $stmt->execute(['id'=>$resourceId,'production_id'=>$productionId]);
            $resource = $stmt->fetch();
            if (!$resource) throw new RuntimeException('That resource no longer exists in this production.');
            $next = $resource['status'] === 'active' ? 'archived' : 'active';
            $db->prepare('UPDATE production_resources SET status=:status WHERE id=:id')->execute(['status'=>$next,'id'=>$resourceId]);
            self::audit($db,(int)$actor['id'],$next === 'active' ? 'resource.restored' : 'resource.archived','production_resource',$resourceId,$next === 'active' ? 'Restored production resource.' : 'Archived production resource.',['production_id'=>$productionId,'title'=>$resource['title']]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof RuntimeException) throw $e;
            throw new RuntimeException('The resource availability could not be changed.');
        }
    }

    private static function resources(PDO $db, array $user, int $productionId, bool $admin): array
    {
        $stmt = $db->prepare("SELECT pr.*, CONCAT(u.first_name,' ',u.last_name) creator FROM production_resources pr LEFT JOIN users u ON u.id=pr.created_by_user_id WHERE pr.production_id=:production_id " . ($admin ? '' : "AND pr.status='active' ") . "ORDER BY pr.status='active' DESC, pr.pinned DESC, pr.category, pr.title");
        $stmt->execute(['production_id'=>$productionId]);
        $rows = $stmt->fetchAll();
        if ($admin || AccessPolicy::canManageProduction($user)) return $rows;
        return array_values(array_filter($rows, static fn(array $row): bool => self::canRead($db, $user, $row)));
    }

    private static function resource(PDO $db, int $id): ?array
    {
        if ($id < 1) return null;
        $stmt=$db->prepare("SELECT pr.*, CONCAT(u.first_name,' ',u.last_name) creator FROM production_resources pr LEFT JOIN users u ON u.id=pr.created_by_user_id WHERE pr.id=:id LIMIT 1");
        $stmt->execute(['id'=>$id]);
        return $stmt->fetch() ?: null;
    }

    private static function canRead(PDO $db, array $user, array $resource): bool
    {
        if (AccessPolicy::canManageProduction($user)) return true;
        if (($resource['status'] ?? '') !== 'active') return false;
        $productionId=(int)$resource['production_id'];
        $audiences=json_decode((string)$resource['audiences_json'],true);
        if (!is_array($audiences) || !$audiences) return false;

        $stmt=$db->prepare("SELECT pm.audience_type,u.display_role FROM production_memberships pm JOIN users u ON u.id=pm.user_id WHERE pm.production_id=:production_id AND pm.user_id=:user_id AND pm.status='active' AND u.active=1");
        $stmt->execute(['production_id'=>$productionId,'user_id'=>(int)$user['id']]);
        $memberships=$stmt->fetchAll();
        if (!$memberships) return false;

        if (in_array('production_all',$audiences,true)) return true;
        $types=array_unique(array_column($memberships,'audience_type'));
        if (in_array('production_students',$audiences,true) && in_array('student',$types,true)) return true;
        if (in_array('production_guardians',$audiences,true) && in_array('guardian',$types,true)) return true;
        if (in_array('production_staff',$audiences,true) && in_array('staff',$types,true)) return true;
        if (in_array('production_adults',$audiences,true) && !AccessPolicy::isStudent($user)) return true;
        return false;
    }

    private static function audienceLabels(string $json): array
    {
        $items=json_decode($json,true);
        if (!is_array($items)) return [];
        $labels=[];
        foreach($items as $item) if(isset(self::AUDIENCES[$item])) $labels[]=self::AUDIENCES[$item];
        return $labels;
    }

    private static function audit(PDO $db,int $actorId,string $eventType,string $subjectType,int $subjectId,string $summary,array $metadata): void
    {
        $stmt=$db->prepare('INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,:event_type,:subject_type,:subject_id,:summary,:metadata)');
        $stmt->execute(['actor'=>$actorId,'event_type'=>$eventType,'subject_type'=>$subjectType,'subject_id'=>$subjectId,'summary'=>$summary,'metadata'=>json_encode($metadata,JSON_THROW_ON_ERROR)]);
    }

    private static function page(string $route,string $basePath,PDO $db,array $user,?array $production,array $resources,?array $resource,bool $staff): never
    {
        $url=static fn(string $path):string=>($basePath?:'').$path;
        $esc=static fn(string $value):string=>htmlspecialchars($value,ENT_QUOTES,'UTF-8');
        $flash=$_SESSION['resource_flash']??null; unset($_SESSION['resource_flash']);
        $admin=str_starts_with($route,'/admin/resources');
        $editing=$route==='/admin/resources/edit';
        $memberDetail=$route==='/resources/view';
        $selectedAudiences=$resource ? (json_decode((string)$resource['audiences_json'],true) ?: []) : ['production_all'];
        $title=$admin ? ($editing ? ($resource['title'] ?? 'Resource') : 'Resource Library') : ($memberDetail ? ($resource['title'] ?? 'Resource') : 'Resources');
        $subnav=[
            ['label'=>'Overview','href'=>'/production','active'=>false],
            ['label'=>'Schedule','href'=>'/schedule','active'=>false],
            ['label'=>'Resources','href'=>'/resources','active'=>!$admin],
            ['label'=>'Playbill','href'=>'/playbills','active'=>false],
        ];
        if($staff) $subnav[]=['label'=>'Manage resources','href'=>'/admin/resources','active'=>$admin];

        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#a6192e"><title><?= $esc($title) ?> · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/resource-implementation.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar($route,$basePath,$user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Production',$title,$basePath,$subnav); ?><div class="resource-page">
        <?php if($flash):?><div class="resource-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif;?>
        <?php if(!$production):?><section class="resource-empty"><small>NO ACTIVE PRODUCTION</small><h2>Select a production workspace.</h2><p>Resources are organized by production so overlapping shows never share private material accidentally.</p><a class="button" href="<?= $url('/production') ?>">Choose production</a></section>
        <?php elseif($admin && !$editing):?>
            <section class="resource-hero staff"><div><small><?= $esc(strtoupper($production['title'])) ?></small><h2>Build the show’s resource shelf.</h2><p>Publish durable information outside the message stream. Resources stay with this production and can be archived without deleting history.</p></div><a class="button" href="<?= $url('/admin/resources/edit') ?>">Add resource</a></section>
            <div class="resource-admin-list"><?php if(!$resources):?><section class="resource-empty compact"><b>No resources yet.</b><p>Add the first link or reference note for this production.</p></section><?php else:foreach($resources as $row):$labels=self::audienceLabels((string)$row['audiences_json']);?><article class="resource-admin-row <?= $esc($row['status']) ?>"><span class="resource-type"><?= $row['resource_type']==='link'?'↗':'▤' ?></span><div><small><?= $esc(strtoupper($row['category'])) ?><?= $row['pinned']?' · PINNED':'' ?></small><h3><?= $esc($row['title']) ?></h3><p><?= $esc((string)($row['description']?:implode(' · ',$labels))) ?></p><em><?= $esc(implode(' · ',$labels)) ?> · <?= $esc(ucfirst($row['status'])) ?></em></div><div class="resource-row-actions"><a href="<?= $url('/admin/resources/edit?id='.(int)$row['id']) ?>">Edit</a><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['resource_csrf']) ?>"><input type="hidden" name="action" value="toggle_archive"><input type="hidden" name="resource_id" value="<?= (int)$row['id'] ?>"><button type="submit"><?= $row['status']==='active'?'Archive':'Restore' ?></button></form></div></article><?php endforeach;endif;?></div>
        <?php elseif($admin):?>
            <?php if($resource && (int)$resource['production_id']!==(int)$production['id']):$resource=null;endif;?>
            <section class="resource-head"><div><small><?= $esc(strtoupper($production['title'])) ?></small><h2><?= $resource?'Edit resource':'Add resource' ?></h2><p>Choose exactly who in this production should have this material on their shelf.</p></div><a href="<?= $url('/admin/resources') ?>">← Resource library</a></section>
            <div class="resource-edit-layout"><form method="post" class="resource-form"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['resource_csrf']) ?>"><input type="hidden" name="action" value="save"><?php if($resource):?><input type="hidden" name="resource_id" value="<?= (int)$resource['id'] ?>"><?php endif;?>
            <label>Title<input name="title" maxlength="190" required value="<?= $resource?$esc($resource['title']):'' ?>" placeholder="Costume measurement guide"></label><div class="resource-pair"><label>Category<input name="category" maxlength="100" required value="<?= $resource?$esc($resource['category']):'' ?>" placeholder="Costumes"></label><label>Resource type<select name="resource_type"><option value="link"<?= !$resource||$resource['resource_type']==='link'?' selected':'' ?>>Link</option><option value="note"<?= $resource&&$resource['resource_type']==='note'?' selected':'' ?>>Reference note</option></select></label></div><label>Description<input name="description" maxlength="500" value="<?= $resource?$esc((string)$resource['description']):'' ?>" placeholder="What this resource is for"></label><label>Link URL<input type="url" name="resource_url" maxlength="1000" value="<?= $resource?$esc((string)$resource['resource_url']):'' ?>" placeholder="https://..."></label><label>Reference note<textarea name="body" rows="8" maxlength="10000" placeholder="Use this when Resource type is Reference note"><?= $resource?$esc((string)$resource['body']):'' ?></textarea></label><fieldset><legend>Audience</legend><?php foreach(self::AUDIENCES as $value=>$label):?><label class="resource-check"><input type="checkbox" name="audiences[]" value="<?= $esc($value) ?>"<?= in_array($value,$selectedAudiences,true)?' checked':'' ?>><span><?= $esc($label) ?></span></label><?php endforeach;?></fieldset><label class="resource-check pinned"><input type="checkbox" name="pinned" value="1"<?= $resource&&(bool)$resource['pinned']?' checked':'' ?>><span><b>Pin this resource</b><small>Keep it at the top of the resource library.</small></span></label><footer><a href="<?= $url('/admin/resources') ?>">Cancel</a><button class="button" type="submit"><?= $resource?'Save resource':'Publish resource' ?></button></footer></form><aside class="resource-side"><small>RESOURCE MODEL</small><h3>Durable information, not another post.</h3><p>Use Community for conversation and announcements. Use Resources for things people should be able to find again without scrolling through a feed.</p><div><b>Files are separate.</b><span>Upload versioned files from File Operations and use this shelf for durable links and reference notes.</span></div></aside></div>
        <?php elseif($memberDetail):?>
            <?php if(!$resource):?><section class="resource-empty"><b>Resource not available.</b><p>It may belong to another production, be archived, or not be visible to your role.</p><a class="button" href="<?= $url('/resources') ?>">Back to resources</a></section><?php else:?><section class="resource-detail"><div class="resource-detail-top"><span><?= $resource['resource_type']==='link'?'↗':'▤' ?></span><div><small><?= $esc(strtoupper($resource['category'])) ?><?= $resource['pinned']?' · PINNED':'' ?></small><h2><?= $esc($resource['title']) ?></h2><p><?= $esc((string)$resource['description']) ?></p></div></div><?php if($resource['resource_type']==='link'):?><a class="button" href="<?= $esc((string)$resource['resource_url']) ?>" target="_blank" rel="noopener noreferrer">Open resource ↗</a><?php else:?><article class="resource-note"><?= nl2br($esc((string)$resource['body'])) ?></article><?php endif;?><footer><a href="<?= $url('/resources') ?>">← All resources</a><?php if($staff):?><a href="<?= $url('/admin/resources/edit?id='.(int)$resource['id']) ?>">Edit resource</a><?php endif;?></footer></section><?php endif;?>
        <?php else:?>
            <section class="resource-hero"><div><small><?= $esc(strtoupper($production['title'])) ?></small><h2>Everything worth finding twice.</h2><p>Production references, guides, links and standing information—kept out of the message stream and organized for the people who need them.</p></div><?php if($staff):?><a class="button" href="<?= $url('/admin/resources') ?>">Manage resources</a><?php endif;?></section>
            <?php if(!$resources):?><section class="resource-empty"><b>No resources available yet.</b><p>When staff publishes production resources for your audience, they’ll appear here.</p></section><?php else:$groups=[];foreach($resources as $row)$groups[$row['category']][]=$row;foreach($groups as $category=>$items):?><section class="resource-group"><header><small>RESOURCE SHELF</small><h3><?= $esc($category) ?></h3><span><?= count($items) ?> item<?= count($items)===1?'':'s' ?></span></header><div class="resource-grid"><?php foreach($items as $row):?><a class="resource-card<?= $row['pinned']?' pinned':'' ?>" href="<?= $url('/resources/view?id='.(int)$row['id']) ?>"><span><?= $row['resource_type']==='link'?'↗':'▤' ?></span><div><small><?= $row['pinned']?'PINNED · ':'' ?><?= $esc(strtoupper($row['resource_type'])) ?></small><h4><?= $esc($row['title']) ?></h4><p><?= $esc((string)($row['description']?:($row['resource_type']==='link'?'Open reference link':'Read reference note'))) ?></p></div><b>Open →</b></a><?php endforeach;?></div></section><?php endforeach;endif;?>
        <?php endif;?>
        </div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php
        exit;
    }

    private static function forbidden(string $basePath,array $user): never
    {
        $url=static fn(string $path):string=>($basePath?:'').$path;
        http_response_code(403);header('Content-Type: text/html; charset=utf-8');?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Restricted · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/resources',$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Production','Restricted',$basePath);?><div class="resource-page"><section class="resource-empty"><b>Staff only</b><p>Your role cannot manage production resources.</p><a class="button" href="<?= $url('/resources') ?>">View resources</a></section></div></main></div></body></html><?php exit;
    }

    private static function flash(string $type,string $message):void{$_SESSION['resource_flash']=['type'=>$type,'message'=>$message];}
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
}
