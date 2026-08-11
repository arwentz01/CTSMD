<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/AccessPolicy.php';

final class CommunityManagementExperience
{
    private const ROUTES=['/admin/channels','/admin/channels/edit'];
    private const AUDIENCES=[
        'all_members'=>'All members / public community',
        'adults'=>'Adults only',
        'students'=>'Students',
        'staff'=>'Staff only',
        'volunteers'=>'Volunteers',
        'production_members'=>'All active production members',
        'production_adults'=>'Active production adults',
        'production_students'=>'Active production students',
        'production_guardians'=>'Active production guardians',
        'production_staff'=>'Active production staff',
    ];

    public static function handles(string $route): bool { return in_array($route,self::ROUTES,true); }

    public static function render(string $route,string $basePath): never
    {
        Auth::startSession();
        $db=Database::connect(dirname(__DIR__)); $user=Auth::currentUser($db);
        if(!$user) self::redirect(($basePath?:'').'/login');
        if(!AccessPolicy::canManageCommunity($user)) self::forbidden();
        $_SESSION['channel_admin_csrf']??=bin2hex(random_bytes(24));
        if($_SERVER['REQUEST_METHOD']==='POST') self::handlePost($db,$user,$route,$basePath);
        $edit=null;
        if($route==='/admin/channels/edit'){ $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0; $edit=self::channel($db,(int)$id); }
        self::page($route,$basePath,$user,self::channels($db),self::productions($db),$edit);
    }

    private static function handlePost(PDO $db,array $user,string $route,string $basePath): never
    {
        if(!hash_equals((string)($_SESSION['channel_admin_csrf']??''),(string)($_POST['csrf_token']??''))){ self::flash('error','Your session token expired.'); self::redirect($basePath.'/admin/channels'); }
        $action=(string)($_POST['action']??'');
        try{
            if($action==='save'){
                $id=filter_input(INPUT_POST,'channel_id',FILTER_VALIDATE_INT)?:0;
                $saved=self::save($db,$user,(int)$id,$_POST);
                self::flash('success',$id?'Channel settings updated.':'Channel created.');
                self::redirect($basePath.'/admin/channels/edit?id='.$saved);
            }
            $id=filter_input(INPUT_POST,'channel_id',FILTER_VALIDATE_INT)?:0;
            if($action==='archive') self::archive($db,$user,(int)$id,true);
            elseif($action==='restore') self::archive($db,$user,(int)$id,false);
            else throw new RuntimeException('Choose a valid channel action.');
            self::flash('success',$action==='archive'?'Channel archived.':'Channel restored.');
        }catch(RuntimeException $e){ self::flash('error',$e->getMessage()); }
        self::redirect($basePath.'/admin/channels');
    }

    private static function save(PDO $db,array $user,int $id,array $input): int
    {
        $name=trim((string)($input['name']??'')); $type=trim((string)($input['channel_type']??'discussion')); $description=trim((string)($input['description']??''));
        $productionId=filter_var($input['production_id']??null,FILTER_VALIDATE_INT)?:null;
        $read=self::normalizeAudiences($input['read_audiences']??[]); $post=self::normalizeAudiences($input['post_audiences']??[]);
        $sort=filter_var($input['sort_order']??100,FILTER_VALIDATE_INT); $sort=$sort===false?100:(int)$sort;
        if($name===''||mb_strlen($name)>120) throw new RuntimeException('Enter a channel name no longer than 120 characters.');
        if($type===''||mb_strlen($type)>80) throw new RuntimeException('Enter a channel type.');
        if(!$read) throw new RuntimeException('Choose at least one reading audience.');
        if(!$post) throw new RuntimeException('Choose at least one posting audience.');
        $needsProduction=self::needsProduction($read)||self::needsProduction($post);
        if($needsProduction&&!$productionId) throw new RuntimeException('Production-specific audiences require a production.');
        if($productionId){ $s=$db->prepare('SELECT id FROM productions WHERE id=:id LIMIT 1'); $s->execute(['id'=>$productionId]); if(!$s->fetchColumn()) throw new RuntimeException('That production no longer exists.'); }

        $legacyRead=self::legacyScope($read); $legacyPost=self::legacyScope($post);
        $db->beginTransaction();
        try{
            if($id){
                $before=self::channel($db,$id); if(!$before) throw new RuntimeException('That channel no longer exists.');
                $stmt=$db->prepare('UPDATE channels SET production_id=:production,name=:name,channel_type=:type,description=:description,read_scope=:read_scope,post_scope=:post_scope,read_audiences_json=:read_json,post_audiences_json=:post_json,sort_order=:sort WHERE id=:id');
                $stmt->execute(['production'=>$productionId,'name'=>$name,'type'=>$type,'description'=>$description?:null,'read_scope'=>$legacyRead,'post_scope'=>$legacyPost,'read_json'=>json_encode($read,JSON_THROW_ON_ERROR),'post_json'=>json_encode($post,JSON_THROW_ON_ERROR),'sort'=>$sort,'id'=>$id]);
                self::audit($db,(int)$user['id'],'community.channel_updated',$id,'Updated community channel audiences.',['read_audiences'=>$read,'post_audiences'=>$post,'production_id'=>$productionId]);
            }else{
                $stmt=$db->prepare('INSERT INTO channels (production_id,name,channel_type,description,read_scope,post_scope,read_audiences_json,post_audiences_json,sort_order,created_by_user_id) VALUES (:production,:name,:type,:description,:read_scope,:post_scope,:read_json,:post_json,:sort,:creator)');
                $stmt->execute(['production'=>$productionId,'name'=>$name,'type'=>$type,'description'=>$description?:null,'read_scope'=>$legacyRead,'post_scope'=>$legacyPost,'read_json'=>json_encode($read,JSON_THROW_ON_ERROR),'post_json'=>json_encode($post,JSON_THROW_ON_ERROR),'sort'=>$sort,'creator'=>(int)$user['id']]);
                $id=(int)$db->lastInsertId(); self::audit($db,(int)$user['id'],'community.channel_created',$id,'Created community channel.',['read_audiences'=>$read,'post_audiences'=>$post,'production_id'=>$productionId]);
            }
            $db->commit(); return $id;
        }catch(Throwable $e){ if($db->inTransaction())$db->rollBack(); if($e instanceof RuntimeException)throw $e; throw new RuntimeException('The channel could not be saved.'); }
    }

    private static function normalizeAudiences(mixed $value): array
    {
        $values=is_array($value)?$value:[$value]; $out=[];
        foreach($values as $v){ $v=(string)$v; if(isset(self::AUDIENCES[$v]))$out[]=$v; }
        return array_values(array_unique($out));
    }
    private static function needsProduction(array $audiences): bool { foreach($audiences as $a) if(str_starts_with($a,'production_')||$a==='production_members') return true; return false; }
    private static function legacyScope(array $audiences): string
    {
        if(self::needsProduction($audiences)) return 'production_members';
        if($audiences===['staff']) return 'staff';
        return 'all_members';
    }

    private static function archive(PDO $db,array $user,int $id,bool $archive): void
    {
        if($id<1) throw new RuntimeException('That channel could not be found.');
        $stmt=$db->prepare('UPDATE channels SET archived_at=:at WHERE id=:id'); $stmt->execute(['at'=>$archive?date('Y-m-d H:i:s'):null,'id'=>$id]);
        self::audit($db,(int)$user['id'],$archive?'community.channel_archived':'community.channel_restored',$id,$archive?'Archived community channel.':'Restored community channel.',[]);
    }
    private static function channel(PDO $db,int $id): ?array
    {
        if($id<1)return null; $s=$db->prepare('SELECT id,production_id,name,channel_type,description,read_scope,post_scope,read_audiences_json,post_audiences_json,sort_order,archived_at FROM channels WHERE id=:id LIMIT 1'); $s->execute(['id'=>$id]); return $s->fetch()?:null;
    }
    private static function channels(PDO $db): array { return $db->query("SELECT c.id,c.name,c.channel_type,c.description,c.read_audiences_json,c.post_audiences_json,c.archived_at,p.title production_title,p.is_active production_active,COUNT(cp.id) post_count FROM channels c LEFT JOIN productions p ON p.id=c.production_id LEFT JOIN channel_posts cp ON cp.channel_id=c.id GROUP BY c.id,c.name,c.channel_type,c.description,c.read_audiences_json,c.post_audiences_json,c.archived_at,p.title,p.is_active,c.sort_order ORDER BY c.archived_at IS NOT NULL,c.sort_order,c.name")->fetchAll(); }
    private static function productions(PDO $db): array { return $db->query("SELECT id,title,season,status,is_active FROM productions ORDER BY is_active DESC,title")->fetchAll(); }
    private static function decode(?string $json,string $fallback): array { $d=$json?json_decode($json,true):null; return is_array($d)&&$d?$d:[$fallback]; }
    private static function audit(PDO $db,int $actor,string $event,int $id,string $summary,array $meta): void { $s=$db->prepare('INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:a,:e,\'channel\',:i,:s,:m)'); $s->execute(['a'=>$actor,'e'=>$event,'i'=>$id,'s'=>$summary,'m'=>json_encode($meta,JSON_THROW_ON_ERROR)]); }
    private static function flash(string $t,string $m): void { $_SESSION['channel_admin_flash']=['type'=>$t,'message'=>$m]; }
    private static function redirect(string $u): never { header('Location: '.$u,true,303); exit; }
    private static function forbidden(): never { http_response_code(403); echo 'Restricted'; exit; }

    private static function page(string $route,string $basePath,array $user,array $channels,array $productions,?array $edit): never
    {
        $url=static fn(string $p):string=>($basePath?:'').$p; $esc=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8'); $flash=$_SESSION['channel_admin_flash']??null; unset($_SESSION['channel_admin_flash']); $editing=$route==='/admin/channels/edit';
        header('Content-Type: text/html; charset=utf-8'); ?>
        <!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Community Operations · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/community-admin.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar($route,$basePath,$user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Operations',$editing?'Channel settings':'Community channels',$basePath); ?><div class="ca-page">
        <?php if($flash): ?><div class="ca-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif; ?>
        <?php if(!$editing): ?><section class="ca-hero"><div><small>AUDIENCE-AWARE COMMUNITY</small><h2>Build rooms around who should actually be there.</h2><p>Production rooms only work while their show is active. Adult-only, student, guardian, volunteer, and staff spaces can coexist without exposing them to the wrong accounts.</p></div><a class="button" href="<?= $url('/admin/channels/edit') ?>">Create channel</a></section><div class="ca-grid"><?php foreach($channels as $c): $r=self::decode($c['read_audiences_json']??null,'all_members'); ?><article class="ca-card<?= $c['archived_at']?' archived':'' ?>"><header><span>#</span><div><small><?= $esc($c['production_title']?:strtoupper($c['channel_type'])) ?></small><h3><?= $esc($c['name']) ?></h3></div></header><p><?= $esc($c['description']?:'No description') ?></p><div class="ca-policy"><span><b>Audience</b><?= $esc(implode(', ',array_map(fn($a)=>self::AUDIENCES[$a]??$a,$r))) ?></span><span><b>Posts</b><?= (int)$c['post_count'] ?></span></div><footer><a href="<?= $url('/admin/channels/edit?id='.(int)$c['id']) ?>">Settings</a><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['channel_admin_csrf']) ?>"><input type="hidden" name="channel_id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="<?= $c['archived_at']?'restore':'archive' ?>"><button><?= $c['archived_at']?'Restore':'Archive' ?></button></form></footer></article><?php endforeach; ?></div>
        <?php else: $c=$edit??['id'=>0,'production_id'=>null,'name'=>'','channel_type'=>'discussion','description'=>'','read_scope'=>'all_members','post_scope'=>'staff','read_audiences_json'=>null,'post_audiences_json'=>null,'sort_order'=>100]; $read=self::decode($c['read_audiences_json']??null,(string)$c['read_scope']); $post=self::decode($c['post_audiences_json']??null,(string)$c['post_scope']); ?><section class="ca-edit-head"><div><small><?= $edit?'CHANNEL SETTINGS':'NEW CHANNEL' ?></small><h2><?= $edit?'# '.$esc($c['name']):'Create a community channel' ?></h2><p>Audience selections are unioned: choosing Adults + Volunteers means either audience may enter. Production-prefixed audiences only qualify while that production is active.</p></div><a href="<?= $url('/admin/channels') ?>">← All channels</a></section><form class="ca-form" method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['channel_admin_csrf']) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="channel_id" value="<?= (int)$c['id'] ?>"><label>Channel name<input name="name" maxlength="120" required value="<?= $esc($c['name']) ?>"></label><div class="ca-pair"><label>Type<input name="channel_type" maxlength="80" required value="<?= $esc($c['channel_type']) ?>"></label><label>Production<select name="production_id"><option value="">Organization-wide</option><?php foreach($productions as $p): ?><option value="<?= (int)$p['id'] ?>"<?= (int)$c['production_id']===(int)$p['id']?' selected':'' ?>><?= $esc($p['title']) ?><?= $p['is_active']?' · ACTIVE':'' ?></option><?php endforeach; ?></select></label></div><label>Description<textarea name="description" maxlength="255"><?= $esc((string)$c['description']) ?></textarea></label><div class="ca-pair"><fieldset><legend>Who can read</legend><?php foreach(self::AUDIENCES as $key=>$label): ?><label><input type="checkbox" name="read_audiences[]" value="<?= $esc($key) ?>"<?= in_array($key,$read,true)?' checked':'' ?>> <?= $esc($label) ?></label><?php endforeach; ?></fieldset><fieldset><legend>Who can post</legend><?php foreach(self::AUDIENCES as $key=>$label): ?><label><input type="checkbox" name="post_audiences[]" value="<?= $esc($key) ?>"<?= in_array($key,$post,true)?' checked':'' ?>> <?= $esc($label) ?></label><?php endforeach; ?></fieldset></div><label>Sort order<input type="number" name="sort_order" value="<?= (int)$c['sort_order'] ?>"></label><button class="button" type="submit">Save channel</button></form><?php endif; ?>
        </div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php exit;
    }
}
