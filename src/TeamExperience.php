<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/ProductionContext.php';

final class TeamExperience
{
    private const ROUTES = ['/admin/teams','/admin/teams/view','/admin/private-channel'];

    public static function handles(string $route): bool { return in_array($route,self::ROUTES,true); }

    public static function render(string $route,string $basePath): never
    {
        Auth::startSession();
        $db=Database::connect(dirname(__DIR__));
        $user=Auth::currentUser($db);
        if(!$user) self::redirect(($basePath?:'').'/login');
        if(!AccessPolicy::isStaff($user)) self::forbidden();
        $_SESSION['team_csrf']??=bin2hex(random_bytes(24));
        if($_SERVER['REQUEST_METHOD']==='POST') self::handlePost($db,$user,$route,$basePath);
        $team=null;
        if($route==='/admin/teams/view'){
            $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;
            $team=self::team($db,(int)$id);
        }
        self::page($route,$basePath,$db,$user,$team);
    }

    private static function handlePost(PDO $db,array $user,string $route,string $basePath): never
    {
        if(!hash_equals((string)($_SESSION['team_csrf']??''),(string)($_POST['csrf_token']??''))){ self::flash('error','Your session token expired.'); self::redirect($basePath.'/admin/teams'); }
        $action=(string)($_POST['action']??'');
        try{
            if($action==='create_team'){
                $id=self::createTeam($db,$user,$_POST); self::flash('success','Team created.'); self::redirect($basePath.'/admin/teams/view?id='.$id);
            }
            if($action==='save_members'){
                $id=filter_input(INPUT_POST,'team_id',FILTER_VALIDATE_INT)?:0; self::saveMembers($db,$user,(int)$id,(array)($_POST['user_ids']??[])); self::flash('success','Team membership updated.'); self::redirect($basePath.'/admin/teams/view?id='.(int)$id);
            }
            if($action==='toggle_team'){
                $id=filter_input(INPUT_POST,'team_id',FILTER_VALIDATE_INT)?:0; self::toggleTeam($db,$user,(int)$id); self::flash('success','Team availability updated.'); self::redirect($basePath.'/admin/teams');
            }
            if($action==='create_team_channel'){
                $id=filter_input(INPUT_POST,'team_id',FILTER_VALIDATE_INT)?:0; $channelId=self::createTeamChannel($db,$user,(int)$id,$_POST); self::flash('success','Team channel created.'); self::redirect($basePath.'/channels/view?id='.$channelId);
            }
            if($action==='create_private_channel'){
                $channelId=self::createPrivateChannel($db,$user,$_POST); self::flash('success','Private channel created.'); self::redirect($basePath.'/channels/view?id='.$channelId);
            }
            throw new RuntimeException('Choose a valid team operation.');
        }catch(RuntimeException $e){ self::flash('error',$e->getMessage()); self::redirect($basePath.($route==='/admin/private-channel'?'/admin/private-channel':'/admin/teams')); }
    }

    private static function createTeam(PDO $db,array $actor,array $input): int
    {
        $name=trim((string)($input['name']??'')); $description=trim((string)($input['description']??''));
        $scope=(string)($input['scope']??'organization'); $productionId=null;
        if($name===''||mb_strlen($name)>190) throw new RuntimeException('Enter a team name no longer than 190 characters.');
        if(mb_strlen($description)>500) throw new RuntimeException('Keep the team description under 500 characters.');
        if($scope==='production'){
            $selected=ProductionContext::selected($db,$actor); if(!$selected) throw new RuntimeException('Select an active production before creating a production team.'); $productionId=(int)$selected['id'];
        }elseif($scope!=='organization') throw new RuntimeException('Choose a valid team scope.');
        $stmt=$db->prepare('INSERT INTO teams (production_id,name,description,active,created_by_user_id) VALUES (:production,:name,:description,1,:actor)');
        $stmt->execute(['production'=>$productionId,'name'=>$name,'description'=>$description?:null,'actor'=>(int)$actor['id']]);
        $id=(int)$db->lastInsertId(); self::audit($db,(int)$actor['id'],'team.created','team',$id,'Created reusable team.',['production_id'=>$productionId,'name'=>$name]); return $id;
    }

    private static function saveMembers(PDO $db,array $actor,int $teamId,array $rawIds): void
    {
        $team=self::team($db,$teamId); if(!$team) throw new RuntimeException('That team no longer exists.');
        $ids=array_values(array_unique(array_filter(array_map('intval',$rawIds),static fn(int $id):bool=>$id>0)));
        if($team['production_id']!==null && $ids){
            $ph=implode(',',array_fill(0,count($ids),'?')); $stmt=$db->prepare("SELECT DISTINCT user_id FROM production_memberships WHERE production_id=? AND status='active' AND user_id IN ($ph)"); $stmt->execute(array_merge([(int)$team['production_id']],$ids)); $eligible=array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
            if(count($eligible)!==count($ids)) throw new RuntimeException('Production teams can only include active members of that production.');
        }
        self::validateStudentGroup($db,$ids);
        $db->beginTransaction();
        try{
            $db->prepare("UPDATE team_members SET status='inactive' WHERE team_id=:team")->execute(['team'=>$teamId]);
            $up=$db->prepare("INSERT INTO team_members (team_id,user_id,status,added_by_user_id) VALUES (:team,:user,'active',:actor) ON DUPLICATE KEY UPDATE status='active',added_by_user_id=VALUES(added_by_user_id),updated_at=CURRENT_TIMESTAMP");
            foreach($ids as $id) $up->execute(['team'=>$teamId,'user'=>$id,'actor'=>(int)$actor['id']]);
            self::audit($db,(int)$actor['id'],'team.members_updated','team',$teamId,'Updated team membership.',['user_ids'=>$ids]); $db->commit();
        }catch(Throwable $e){ if($db->inTransaction())$db->rollBack(); if($e instanceof RuntimeException)throw $e; throw new RuntimeException('Team membership could not be saved.'); }
    }

    private static function validateStudentGroup(PDO $db,array $ids): void
    {
        if(!$ids) return;
        $ph=implode(',',array_fill(0,count($ids),'?')); $stmt=$db->prepare("SELECT id,display_role FROM users WHERE active=1 AND id IN ($ph)"); $stmt->execute($ids); $rows=$stmt->fetchAll();
        $hasStudent=false; $hasStaff=false;
        foreach($rows as $row){ $role=(string)$row['display_role']; if(str_contains($role,'Student'))$hasStudent=true; if(str_contains($role,'Director')||str_contains($role,'Manager')||str_contains($role,'Staff')||str_contains($role,'Admin'))$hasStaff=true; }
        if($hasStudent && (count($rows)<3 || !$hasStaff)) throw new RuntimeException('Student-inclusive private groups must contain at least three active people and at least one staff member. Use safeguarded Messages for one-to-one communication.');
    }

    private static function toggleTeam(PDO $db,array $actor,int $teamId): void
    {
        $team=self::team($db,$teamId); if(!$team) throw new RuntimeException('That team no longer exists.'); $next=(int)$team['active']?0:1;
        $db->prepare('UPDATE teams SET active=:active WHERE id=:id')->execute(['active'=>$next,'id'=>$teamId]); self::audit($db,(int)$actor['id'],$next?'team.activated':'team.deactivated','team',$teamId,$next?'Activated team.':'Deactivated team.',[]);
    }

    private static function createTeamChannel(PDO $db,array $actor,int $teamId,array $input): int
    {
        $team=self::team($db,$teamId); if(!$team||(int)$team['active']!==1) throw new RuntimeException('Choose an active team.');
        $members=self::teamMemberIds($db,$teamId); self::validateStudentGroup($db,$members);
        $name=self::channelName($input); $description=trim((string)($input['description']??''));
        $db->beginTransaction();
        try{
            $stmt=$db->prepare("INSERT INTO channels (production_id,name,channel_type,description,read_scope,post_scope,read_audiences_json,post_audiences_json,access_mode,sort_order,created_by_user_id) VALUES (:production,:name,'team',:description,'staff','staff',JSON_ARRAY('staff'),JSON_ARRAY('staff'),'team',100,:actor)");
            $stmt->execute(['production'=>$team['production_id'],'name'=>$name,'description'=>$description?:$team['description'],'actor'=>(int)$actor['id']]); $channelId=(int)$db->lastInsertId();
            $db->prepare('INSERT INTO channel_teams (channel_id,team_id,can_read,can_post) VALUES (:channel,:team,1,1)')->execute(['channel'=>$channelId,'team'=>$teamId]);
            self::audit($db,(int)$actor['id'],'community.team_channel_created','channel',$channelId,'Created Team-backed Community channel.',['team_id'=>$teamId]); $db->commit(); return $channelId;
        }catch(Throwable $e){ if($db->inTransaction())$db->rollBack(); if($e instanceof RuntimeException)throw $e; throw new RuntimeException('The team channel could not be created.'); }
    }

    private static function createPrivateChannel(PDO $db,array $actor,array $input): int
    {
        $name=self::channelName($input); $description=trim((string)($input['description']??''));
        $ids=array_values(array_unique(array_filter(array_map('intval',(array)($input['user_ids']??[])),static fn(int $id):bool=>$id>0)));
        if(!in_array((int)$actor['id'],$ids,true)) $ids[]=(int)$actor['id'];
        if(count($ids)<2) throw new RuntimeException('Select at least one other person for a private channel.');
        self::validateStudentGroup($db,$ids);
        $productionId=null; if(isset($input['production_scope'])){ $selected=ProductionContext::selected($db,$actor); if(!$selected)throw new RuntimeException('Select an active production first.'); $productionId=(int)$selected['id']; }
        if($productionId!==null){ $ph=implode(',',array_fill(0,count($ids),'?')); $stmt=$db->prepare("SELECT DISTINCT user_id FROM production_memberships WHERE production_id=? AND status='active' AND user_id IN ($ph)"); $stmt->execute(array_merge([$productionId],$ids)); if(count($stmt->fetchAll(PDO::FETCH_COLUMN))!==count($ids)) throw new RuntimeException('A production-scoped private channel can only include active members of that production.'); }
        $db->beginTransaction();
        try{
            $stmt=$db->prepare("INSERT INTO channels (production_id,name,channel_type,description,read_scope,post_scope,read_audiences_json,post_audiences_json,access_mode,sort_order,created_by_user_id) VALUES (:production,:name,'private',:description,'staff','staff',JSON_ARRAY('staff'),JSON_ARRAY('staff'),'selected',100,:actor)");
            $stmt->execute(['production'=>$productionId,'name'=>$name,'description'=>$description?:null,'actor'=>(int)$actor['id']]); $channelId=(int)$db->lastInsertId();
            $link=$db->prepare("INSERT INTO channel_members (channel_id,user_id,can_read,can_post,status,added_by_user_id) VALUES (:channel,:user,1,1,'active',:actor)"); foreach($ids as $id)$link->execute(['channel'=>$channelId,'user'=>$id,'actor'=>(int)$actor['id']]);
            self::audit($db,(int)$actor['id'],'community.private_channel_created','channel',$channelId,'Created selected-members Community channel.',['user_ids'=>$ids,'production_id'=>$productionId]); $db->commit(); return $channelId;
        }catch(Throwable $e){ if($db->inTransaction())$db->rollBack(); if($e instanceof RuntimeException)throw $e; throw new RuntimeException('The private channel could not be created.'); }
    }

    private static function channelName(array $input): string { $name=trim((string)($input['name']??'')); if($name===''||mb_strlen($name)>120)throw new RuntimeException('Enter a channel name no longer than 120 characters.'); return $name; }
    private static function team(PDO $db,int $id): ?array { if($id<1)return null; $s=$db->prepare('SELECT t.*,p.title production_title FROM teams t LEFT JOIN productions p ON p.id=t.production_id WHERE t.id=:id LIMIT 1'); $s->execute(['id'=>$id]); $r=$s->fetch(); if(!$r)return null; $r['member_ids']=self::teamMemberIds($db,$id); return $r; }
    private static function teamMemberIds(PDO $db,int $id): array { $s=$db->prepare("SELECT user_id FROM team_members WHERE team_id=:id AND status='active' ORDER BY user_id"); $s->execute(['id'=>$id]); return array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN)); }
    private static function teams(PDO $db): array { return $db->query("SELECT t.id,t.name,t.description,t.active,t.production_id,p.title production_title,COUNT(CASE WHEN tm.status='active' THEN 1 END) member_count,COUNT(DISTINCT ct.channel_id) channel_count FROM teams t LEFT JOIN productions p ON p.id=t.production_id LEFT JOIN team_members tm ON tm.team_id=t.id LEFT JOIN channel_teams ct ON ct.team_id=t.id GROUP BY t.id,t.name,t.description,t.active,t.production_id,p.title ORDER BY t.active DESC,p.title IS NULL DESC,p.title,t.name")->fetchAll(); }
    private static function people(PDO $db): array { return $db->query("SELECT id,CONCAT(first_name,' ',last_name) name,display_role role FROM users WHERE active=1 ORDER BY last_name,first_name")->fetchAll(); }
    private static function audit(PDO $db,int $actor,string $event,string $type,int $id,string $summary,array $meta): void { $s=$db->prepare('INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:a,:e,:t,:i,:s,:m)'); $s->execute(['a'=>$actor,'e'=>$event,'t'=>$type,'i'=>$id,'s'=>$summary,'m'=>json_encode($meta,JSON_THROW_ON_ERROR)]); }
    private static function flash(string $t,string $m): void { $_SESSION['team_flash']=['type'=>$t,'message'=>$m]; }
    private static function redirect(string $u): never { header('Location: '.$u,true,303); exit; }
    private static function forbidden(): never { http_response_code(403); echo 'Restricted'; exit; }

    private static function page(string $route,string $basePath,PDO $db,array $user,?array $team): never
    {
        $url=static fn(string $p):string=>($basePath?:'').$p; $esc=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8'); $flash=$_SESSION['team_flash']??null; unset($_SESSION['team_flash']); $people=self::people($db); $teams=self::teams($db); $selected=ProductionContext::selected($db,$user);
        header('Content-Type: text/html; charset=utf-8'); ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Teams · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/team-operations.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar($route,$basePath,$user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Operations',$route==='/admin/private-channel'?'Private channel':($team?$team['name']:'Teams'),$basePath,[['label'=>'Teams','href'=>'/admin/teams','active'=>$route!=='/admin/private-channel'],['label'=>'Private channel','href'=>'/admin/private-channel','active'=>$route==='/admin/private-channel']]); ?><div class="team-page"><?php if($flash):?><div class="team-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif;?>
        <?php if($route==='/admin/teams'):?><section class="team-hero"><div><small>REUSABLE GROUPS</small><h2>Build the group once. Reuse it everywhere.</h2><p>Teams are membership objects, not channels. A costume crew, production leadership team, or event committee can later power multiple Community spaces and other workflows.</p></div></section><div class="team-grid"><?php foreach($teams as $t):?><a class="team-card<?= !$t['active']?' inactive':'' ?>" href="<?= $url('/admin/teams/view?id='.(int)$t['id']) ?>"><small><?= $esc($t['production_title']?:'Organization-wide') ?></small><h3><?= $esc($t['name']) ?></h3><p><?= $esc($t['description']?:'Reusable CTSMD team') ?></p><footer><span><?= (int)$t['member_count'] ?> members</span><span><?= (int)$t['channel_count'] ?> channels</span></footer></a><?php endforeach;?></div><form class="team-form" method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['team_csrf']) ?>"><input type="hidden" name="action" value="create_team"><h3>Create team</h3><label>Name<input name="name" maxlength="190" required></label><label>Description<textarea name="description" maxlength="500"></textarea></label><label>Scope<select name="scope"><option value="organization">Organization-wide</option><option value="production">Working production<?= $selected?' · '.$esc($selected['title']):'' ?></option></select></label><button class="button">Create team</button></form>
        <?php elseif($route==='/admin/teams/view'):?><?php if(!$team):?><section class="team-empty">Team not found.</section><?php else:?><section class="team-head"><div><small><?= $esc($team['production_title']?:'ORGANIZATION-WIDE') ?></small><h2><?= $esc($team['name']) ?></h2><p><?= $esc($team['description']?:'Reusable CTSMD team') ?></p></div><span><?= $team['active']?'Active':'Inactive' ?></span></section><div class="team-two"><form class="team-form" method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['team_csrf']) ?>"><input type="hidden" name="action" value="save_members"><input type="hidden" name="team_id" value="<?= (int)$team['id'] ?>"><h3>Members</h3><div class="team-people"><?php foreach($people as $p):?><label><input type="checkbox" name="user_ids[]" value="<?= (int)$p['id'] ?>"<?= in_array((int)$p['id'],$team['member_ids'],true)?' checked':'' ?>><span><b><?= $esc($p['name']) ?></b><small><?= $esc($p['role']) ?></small></span></label><?php endforeach;?></div><button class="button">Save membership</button></form><aside><form class="team-form" method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['team_csrf']) ?>"><input type="hidden" name="action" value="create_team_channel"><input type="hidden" name="team_id" value="<?= (int)$team['id'] ?>"><h3>Create Team channel</h3><label>Channel name<input name="name" maxlength="120" required placeholder="costume-planning"></label><label>Description<textarea name="description" maxlength="255"></textarea></label><button class="button">Create channel</button></form><form method="post" class="team-toggle"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['team_csrf']) ?>"><input type="hidden" name="action" value="toggle_team"><input type="hidden" name="team_id" value="<?= (int)$team['id'] ?>"><button><?= $team['active']?'Deactivate team':'Reactivate team' ?></button></form></aside></div><?php endif;?>
        <?php else:?><section class="team-hero"><div><small>SELECTED MEMBERS</small><h2>Create a one-off private Community room.</h2><p>Use this for temporary committees, surprise planning, or a hand-picked working group. It is not a replacement for safeguarded one-to-one Messages.</p></div></section><form class="team-form private" method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['team_csrf']) ?>"><input type="hidden" name="action" value="create_private_channel"><label>Channel name<input name="name" maxlength="120" required></label><label>Description<textarea name="description" maxlength="255"></textarea></label><label class="team-scope"><input type="checkbox" name="production_scope" value="1"><span>Limit this room to the working production<?= $selected?' · '.$esc($selected['title']):'' ?></span></label><fieldset><legend>Select people</legend><div class="team-people"><?php foreach($people as $p):?><label><input type="checkbox" name="user_ids[]" value="<?= (int)$p['id'] ?>"><span><b><?= $esc($p['name']) ?></b><small><?= $esc($p['role']) ?></small></span></label><?php endforeach;?></div></fieldset><button class="button">Create private channel</button></form><?php endif;?></div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php exit;
    }
}
