<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/ProductionContext.php';
require_once __DIR__ . '/ScheduleAudience.php';

final class ProductionGroupExperience
{
    private const ROUTES=['/production/groups','/production/groups/view'];
    private const TYPES=['cast'=>'Cast','crew'=>'Crew','leadership'=>'Leadership','dance'=>'Dance / movement','music'=>'Music / vocal','custom'=>'Custom'];

    public static function handles(string $route): bool { return in_array($route,self::ROUTES,true); }

    public static function render(string $route,string $basePath): never
    {
        Auth::startSession();
        $db=Database::connect(dirname(__DIR__));
        $user=Auth::currentUser($db);
        if(!$user) self::redirect(($basePath?:'').'/login');
        if(!AccessPolicy::canManageProduction($user)) self::forbidden($basePath,$user);
        $_SESSION['production_group_csrf']??=bin2hex(random_bytes(24));
        $production=ProductionContext::selected($db,$user);

        if($_SERVER['REQUEST_METHOD']==='POST') self::handlePost($db,$user,$production,$route,$basePath);

        $group=null;
        if($route==='/production/groups/view' && $production){
            $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;
            $group=self::group($db,(int)$production['id'],(int)$id);
        }
        self::page($route,$basePath,$db,$user,$production,$group);
    }

    private static function handlePost(PDO $db,array $user,?array $production,string $route,string $basePath): never
    {
        if(!hash_equals((string)($_SESSION['production_group_csrf']??''),(string)($_POST['csrf_token']??''))){
            self::flash('error','Your session token expired. Please try again.');
            self::redirect($basePath.'/production/groups');
        }
        if(!$production){ self::flash('error','Select an active production first.'); self::redirect($basePath.'/production'); }
        $selected=ProductionContext::selected($db,$user);
        if(!$selected || (int)$selected['id']!==(int)$production['id']){ self::flash('error','The working production changed before this group was saved.'); self::redirect($basePath.'/production/groups'); }

        $action=(string)($_POST['action']??'');
        try{
            if($action==='create'){
                $id=self::saveGroup($db,$user,(int)$production['id'],0,$_POST);
                self::flash('success','Production group created. Add members below.');
                self::redirect($basePath.'/production/groups/view?id='.$id);
            }
            $groupId=filter_input(INPUT_POST,'group_id',FILTER_VALIDATE_INT)?:0;
            if($action==='update'){
                self::saveGroup($db,$user,(int)$production['id'],(int)$groupId,$_POST);
                self::flash('success','Production group updated.');
                self::redirect($basePath.'/production/groups/view?id='.(int)$groupId);
            }
            if($action==='members'){
                self::saveMembers($db,$user,(int)$production['id'],(int)$groupId,(array)($_POST['membership_ids']??[]));
                self::flash('success','Group membership updated.');
                self::redirect($basePath.'/production/groups/view?id='.(int)$groupId);
            }
            if($action==='toggle'){
                self::toggle($db,$user,(int)$production['id'],(int)$groupId);
                self::flash('success','Group availability updated.');
                self::redirect($basePath.'/production/groups');
            }
            throw new RuntimeException('Choose a valid production-group action.');
        }catch(RuntimeException $e){
            self::flash('error',$e->getMessage());
            $fallback=$route==='/production/groups/view' && !empty($_POST['group_id'])?'/production/groups/view?id='.(int)$_POST['group_id']:'/production/groups';
            self::redirect($basePath.$fallback);
        }
    }

    private static function saveGroup(PDO $db,array $actor,int $productionId,int $groupId,array $input): int
    {
        $name=trim((string)($input['name']??''));
        $type=(string)($input['group_type']??'cast');
        $description=trim((string)($input['description']??''));
        $sort=filter_var($input['sort_order']??100,FILTER_VALIDATE_INT); $sort=$sort===false?100:(int)$sort;
        if($name===''||mb_strlen($name)>190) throw new RuntimeException('Enter a group name no longer than 190 characters.');
        if(!isset(self::TYPES[$type])) throw new RuntimeException('Choose a valid group type.');
        if(mb_strlen($description)>500) throw new RuntimeException('Keep the group description under 500 characters.');

        $db->beginTransaction();
        try{
            if($groupId>0){
                $before=self::groupForUpdate($db,$productionId,$groupId);
                if(!$before) throw new RuntimeException('That group no longer exists in this production.');
                $stmt=$db->prepare('UPDATE production_groups SET name=:name,group_type=:type,description=:description,sort_order=:sort WHERE id=:id AND production_id=:production');
                $stmt->execute(['name'=>$name,'type'=>$type,'description'=>$description?:null,'sort'=>$sort,'id'=>$groupId,'production'=>$productionId]);
                self::audit($db,(int)$actor['id'],'production.group_updated','production_group',$groupId,'Updated production group.',['production_id'=>$productionId,'before'=>$before,'name'=>$name,'group_type'=>$type]);
            }else{
                $stmt=$db->prepare('INSERT INTO production_groups (production_id,name,group_type,description,active,sort_order,created_by_user_id) VALUES (:production,:name,:type,:description,1,:sort,:creator)');
                $stmt->execute(['production'=>$productionId,'name'=>$name,'type'=>$type,'description'=>$description?:null,'sort'=>$sort,'creator'=>(int)$actor['id']]);
                $groupId=(int)$db->lastInsertId();
                self::audit($db,(int)$actor['id'],'production.group_created','production_group',$groupId,'Created production group.',['production_id'=>$productionId,'name'=>$name,'group_type'=>$type]);
            }
            $db->commit(); return $groupId;
        }catch(PDOException $e){
            if($db->inTransaction())$db->rollBack();
            if((string)$e->getCode()==='23000') throw new RuntimeException('A production group with that name already exists in this show.');
            throw new RuntimeException('The production group could not be saved.');
        }catch(Throwable $e){
            if($db->inTransaction())$db->rollBack(); if($e instanceof RuntimeException)throw $e; throw new RuntimeException('The production group could not be saved.');
        }
    }

    private static function saveMembers(PDO $db,array $actor,int $productionId,int $groupId,array $membershipIds): void
    {
        $group=self::group($db,$productionId,$groupId);
        if(!$group) throw new RuntimeException('That group no longer exists in this production.');
        $ids=array_values(array_unique(array_filter(array_map('intval',$membershipIds),static fn(int $id):bool=>$id>0)));
        if($ids){
            $ph=implode(',',array_fill(0,count($ids),'?'));
            $stmt=$db->prepare("SELECT id FROM production_memberships WHERE production_id=? AND status='active' AND id IN ($ph)");
            $stmt->execute(array_merge([$productionId],$ids));
            $valid=array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN)); sort($valid); $expected=$ids; sort($expected);
            if($valid!==$expected) throw new RuntimeException('One or more selected people are no longer active in this production.');
        }
        $db->beginTransaction();
        try{
            $db->prepare("UPDATE production_group_members pgm JOIN production_memberships pm ON pm.id=pgm.production_membership_id SET pgm.status='inactive',pgm.updated_at=CURRENT_TIMESTAMP WHERE pgm.group_id=:group_id AND pm.production_id=:production")->execute(['group_id'=>$groupId,'production'=>$productionId]);
            $upsert=$db->prepare("INSERT INTO production_group_members (group_id,production_membership_id,status,added_by_user_id) VALUES (:group_id,:membership,'active',:actor) ON DUPLICATE KEY UPDATE status='active',added_by_user_id=VALUES(added_by_user_id),updated_at=CURRENT_TIMESTAMP");
            foreach($ids as $membershipId) $upsert->execute(['group_id'=>$groupId,'membership'=>$membershipId,'actor'=>(int)$actor['id']]);
            self::audit($db,(int)$actor['id'],'production.group_members_updated','production_group',$groupId,'Updated production group membership.',['production_id'=>$productionId,'production_membership_ids'=>$ids]);
            $db->commit();
        }catch(Throwable $e){ if($db->inTransaction())$db->rollBack(); if($e instanceof RuntimeException)throw $e; throw new RuntimeException('Group membership could not be updated.'); }
    }

    private static function toggle(PDO $db,array $actor,int $productionId,int $groupId): void
    {
        $db->beginTransaction();
        try{
            $group=self::groupForUpdate($db,$productionId,$groupId); if(!$group)throw new RuntimeException('That group no longer exists.');
            $next=(int)$group['active']?0:1;
            $db->prepare('UPDATE production_groups SET active=:active WHERE id=:id AND production_id=:production')->execute(['active'=>$next,'id'=>$groupId,'production'=>$productionId]);
            self::audit($db,(int)$actor['id'],$next?'production.group_activated':'production.group_deactivated','production_group',$groupId,$next?'Activated production group.':'Deactivated production group.',['production_id'=>$productionId,'name'=>$group['name']]);
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof RuntimeException)throw $e;throw new RuntimeException('The group availability could not be changed.');}
    }

    private static function group(PDO $db,int $productionId,int $groupId): ?array
    {
        if($groupId<1)return null;
        $stmt=$db->prepare("SELECT pg.*,COUNT(DISTINCT CASE WHEN pgm.status='active' AND pm.status='active' THEN pgm.production_membership_id END) member_count FROM production_groups pg LEFT JOIN production_group_members pgm ON pgm.group_id=pg.id LEFT JOIN production_memberships pm ON pm.id=pgm.production_membership_id WHERE pg.id=:id AND pg.production_id=:production GROUP BY pg.id LIMIT 1");
        $stmt->execute(['id'=>$groupId,'production'=>$productionId]); return $stmt->fetch()?:null;
    }
    private static function groupForUpdate(PDO $db,int $productionId,int $groupId): ?array
    {
        $stmt=$db->prepare('SELECT id,production_id,name,group_type,description,active,sort_order FROM production_groups WHERE id=:id AND production_id=:production FOR UPDATE');
        $stmt->execute(['id'=>$groupId,'production'=>$productionId]); return $stmt->fetch()?:null;
    }
    private static function roster(PDO $db,int $productionId): array
    {
        $stmt=$db->prepare("SELECT pm.id membership_id,pm.user_id,pm.audience_type,pm.participation_role,CONCAT(u.first_name,' ',u.last_name) name,u.initials FROM production_memberships pm JOIN users u ON u.id=pm.user_id AND u.active=1 WHERE pm.production_id=:production AND pm.status='active' ORDER BY FIELD(pm.audience_type,'student','staff','guardian'),u.last_name,u.first_name");
        $stmt->execute(['production'=>$productionId]); return $stmt->fetchAll();
    }
    private static function selectedMembershipIds(PDO $db,int $groupId): array
    {
        $stmt=$db->prepare("SELECT pgm.production_membership_id FROM production_group_members pgm JOIN production_memberships pm ON pm.id=pgm.production_membership_id AND pm.status='active' WHERE pgm.group_id=:group_id AND pgm.status='active'");
        $stmt->execute(['group_id'=>$groupId]); return array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
    }
    private static function audit(PDO $db,int $actor,string $event,string $type,int $id,string $summary,array $meta): void
    {
        $stmt=$db->prepare('INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,:event,:type,:id,:summary,:meta)');
        $stmt->execute(['actor'=>$actor,'event'=>$event,'type'=>$type,'id'=>$id,'summary'=>$summary,'meta'=>json_encode($meta,JSON_THROW_ON_ERROR)]);
    }
    private static function flash(string $type,string $message):void{$_SESSION['production_group_flash']=['type'=>$type,'message'=>$message];}
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}

    private static function page(string $route,string $basePath,PDO $db,array $user,?array $production,?array $group):never
    {
        $url=static fn(string $p):string=>($basePath?:'').$p; $esc=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');
        $flash=$_SESSION['production_group_flash']??$_SESSION['production_context_flash']??null; unset($_SESSION['production_group_flash'],$_SESSION['production_context_flash']);
        $editing=$route==='/production/groups/view';
        $groups=$production?ScheduleAudience::groups($db,(int)$production['id'],false):[];
        $roster=$production?self::roster($db,(int)$production['id']):[];
        $selected=$group?self::selectedMembershipIds($db,(int)$group['id']):[];
        header('Content-Type: text/html; charset=utf-8');?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Production Groups · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/production-groups.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar($route,$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Production','Groups & calls',$basePath,[['label'=>'Overview','href'=>'/production','active'=>false],['label'=>'Schedule','href'=>'/schedule','active'=>false],['label'=>'Groups','href'=>'/production/groups','active'=>true],['label'=>'Resources','href'=>'/resources','active'=>false],['label'=>'Playbill','href'=>'/playbills','active'=>false]]);?><div class="pg-page">
<?php if($flash):?><div class="pg-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif;?>
<?php if(!$production):?><section class="pg-empty"><small>NO ACTIVE PRODUCTION</small><h2>Select a production workspace first.</h2><p>Production Groups belong to one show and never spill into another active production.</p><a class="button" href="<?= $url('/production') ?>">Choose production</a></section>
<?php elseif(!$editing):?>
<section class="pg-hero"><div><small><?= $esc(strtoupper($production['title'])) ?></small><h2>Who needs to be called?</h2><p>Build operational groups once, then target rehearsals, calls and future attendance to the right people without rebuilding lists every time.</p></div><span><?= count(array_filter($groups,fn($g)=>(bool)$g['active'])) ?> active groups</span></section>
<div class="pg-layout"><section class="pg-panel"><header><div><small>PRODUCTION GROUPS</small><h3>Cast, crew & working groups</h3></div></header><div class="pg-grid"><?php foreach($groups as $g):?><article class="pg-card<?= $g['active']?'':' inactive'?>"><div><small><?= $esc(self::TYPES[$g['group_type']]??ucfirst($g['group_type'])) ?></small><h3><?= $esc($g['name']) ?></h3><p><?= $esc($g['description']?:'No description yet.') ?></p></div><footer><span><b><?= (int)$g['member_count'] ?></b> members</span><a href="<?= $url('/production/groups/view?id='.(int)$g['id']) ?>">Manage →</a><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_group_csrf']) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="group_id" value="<?= (int)$g['id'] ?>"><button><?= $g['active']?'Deactivate':'Reactivate' ?></button></form></footer></article><?php endforeach;?><?php if(!$groups):?><div class="pg-empty compact"><b>No production groups yet</b><p>Create Full Cast, Ensemble, Tech Crew, Principals, or whatever reflects how this show actually works.</p></div><?php endif;?></div></section>
<aside class="pg-panel"><header><div><small>NEW GROUP</small><h3>Create a call group</h3></div></header><form class="pg-form" method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_group_csrf']) ?>"><input type="hidden" name="action" value="create"><label>Name<input name="name" maxlength="190" required placeholder="Full Cast"></label><label>Type<select name="group_type"><?php foreach(self::TYPES as $key=>$label):?><option value="<?= $esc($key) ?>"><?= $esc($label) ?></option><?php endforeach;?></select></label><label>Description<textarea name="description" maxlength="500" rows="4" placeholder="Who belongs here and when this group is typically called."></textarea></label><label>Sort order<input type="number" name="sort_order" value="100"></label><button class="button" type="submit">Create group</button></form><div class="pg-note"><b>Guardians inherit student calls.</b><p>You usually do not need to add a parent to Ensemble just so they can see their child's Ensemble rehearsal.</p></div></aside></div>
<?php else:?><?php if(!$group):?><section class="pg-empty"><h2>Group not found</h2><p>It may belong to another production workspace.</p><a class="button" href="<?= $url('/production/groups') ?>">Back to groups</a></section><?php else:?>
<section class="pg-edit-head"><div><small><?= $esc(strtoupper($production['title'])) ?></small><h2><?= $esc($group['name']) ?></h2><p><?= (int)$group['member_count'] ?> active members · <?= $esc(self::TYPES[$group['group_type']]??ucfirst($group['group_type'])) ?></p></div><a href="<?= $url('/production/groups') ?>">← All groups</a></section>
<div class="pg-layout"><section class="pg-panel"><header><div><small>MEMBERSHIP</small><h3>Who belongs in this group?</h3></div></header><form method="post" class="pg-member-form"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_group_csrf']) ?>"><input type="hidden" name="action" value="members"><input type="hidden" name="group_id" value="<?= (int)$group['id'] ?>"><div class="pg-roster"><?php foreach($roster as $member):?><label><input type="checkbox" name="membership_ids[]" value="<?= (int)$member['membership_id'] ?>"<?= in_array((int)$member['membership_id'],$selected,true)?' checked':'' ?>><i><?= $esc($member['initials']) ?></i><span><b><?= $esc($member['name']) ?></b><small><?= $esc(ucfirst($member['audience_type'])) ?> · <?= $esc($member['participation_role']?:'No production role') ?></small></span></label><?php endforeach;?></div><footer><small>Students' active guardians receive family-facing group calls automatically.</small><button class="button" type="submit">Save members</button></footer></form></section>
<aside class="pg-panel"><header><div><small>GROUP SETTINGS</small><h3>Identity & ordering</h3></div></header><form class="pg-form" method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['production_group_csrf']) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="group_id" value="<?= (int)$group['id'] ?>"><label>Name<input name="name" maxlength="190" required value="<?= $esc($group['name']) ?>"></label><label>Type<select name="group_type"><?php foreach(self::TYPES as $key=>$label):?><option value="<?= $esc($key) ?>"<?= $group['group_type']===$key?' selected':'' ?>><?= $esc($label) ?></option><?php endforeach;?></select></label><label>Description<textarea name="description" maxlength="500" rows="4"><?= $esc((string)$group['description']) ?></textarea></label><label>Sort order<input type="number" name="sort_order" value="<?= (int)$group['sort_order'] ?>"></label><button class="button" type="submit">Save settings</button></form></aside></div>
<?php endif;?><?php endif;?>
</div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php exit;
    }

    private static function forbidden(string $basePath,array $user):never
    {
        http_response_code(403);$url=static fn(string $p):string=>($basePath?:'').$p;?><!doctype html><html><head><meta charset="utf-8"><title>Restricted · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"></head><body><main style="padding:40px"><h1>Staff only</h1><p>Production Groups are managed by production staff.</p></main></body></html><?php exit;
    }
}
