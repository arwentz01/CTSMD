<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/ModerationService.php';

final class ModerationExperience
{
    private const ROUTES = ['/admin/moderation/terms','/admin/moderation/terms/edit','/admin/moderation/queue'];

    public static function handles(string $route): bool { return in_array($route,self::ROUTES,true); }

    public static function render(string $route,string $basePath): never
    {
        Auth::startSession();
        $db=Database::connect(dirname(__DIR__));
        $user=Auth::currentUser($db);
        if(!$user) self::redirect(($basePath?:'').'/login');
        if(!AccessPolicy::canModerateCommunity($user)) self::forbidden();
        $_SESSION['moderation_csrf']??=bin2hex(random_bytes(24));
        if($_SERVER['REQUEST_METHOD']==='POST') self::handlePost($db,$user,$route,$basePath);

        $edit=null;
        if($route==='/admin/moderation/terms/edit'){
            $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;
            if($id) $edit=self::term($db,(int)$id);
        }
        self::page($route,$basePath,$user,$db,$edit);
    }

    private static function handlePost(PDO $db,array $user,string $route,string $basePath): never
    {
        if(!hash_equals((string)($_SESSION['moderation_csrf']??''),(string)($_POST['csrf_token']??''))){self::flash('error','Your session token expired.');self::redirect($basePath.'/admin/moderation/terms');}
        $action=(string)($_POST['action']??'');
        try{
            if($action==='save_term'){
                $id=filter_input(INPUT_POST,'term_id',FILTER_VALIDATE_INT)?:0;
                $saved=self::saveTerm($db,$user,(int)$id,$_POST);
                self::flash('success',$id?'Moderation rule updated.':'Moderation rule created.');
                self::redirect($basePath.'/admin/moderation/terms/edit?id='.$saved);
            }
            if($action==='toggle_term'){
                $id=filter_input(INPUT_POST,'term_id',FILTER_VALIDATE_INT)?:0;
                self::toggleTerm($db,$user,(int)$id);
                self::flash('success','Moderation rule availability updated.');
                self::redirect($basePath.'/admin/moderation/terms');
            }
            if($action==='test_rule'){
                $term=trim((string)($_POST['term']??''));
                $mode=(string)($_POST['match_mode']??'normalized');
                $aliases=self::aliases((string)($_POST['aliases']??''));
                $sample=trim((string)($_POST['test_text']??''));
                $hit=$sample!==''&&$term!==''&&ModerationService::testRule($sample,$term,$mode,$aliases);
                $_SESSION['moderation_test']=['hit'=>$hit,'sample'=>$sample];
                self::redirect($basePath.'/admin/moderation/terms/edit'.(!empty($_POST['term_id'])?'?id='.(int)$_POST['term_id']:''));
            }
            if(in_array($action,['approve_post','reject_post'],true)){
                $postId=filter_input(INPUT_POST,'post_id',FILTER_VALIDATE_INT)?:0;
                self::reviewPost($db,$user,(int)$postId,$action==='approve_post');
                self::flash('success',$action==='approve_post'?'Post approved and published.':'Post rejected and kept private.');
                self::redirect($basePath.'/admin/moderation/queue');
            }
            throw new RuntimeException('Choose a valid moderation action.');
        }catch(RuntimeException $e){self::flash('error',$e->getMessage());}
        self::redirect($basePath.($route==='/admin/moderation/queue'?'/admin/moderation/queue':'/admin/moderation/terms'));
    }

    private static function saveTerm(PDO $db,array $user,int $id,array $input): int
    {
        $term=trim((string)($input['term']??''));
        $category=trim((string)($input['category']??'profanity'));
        $action=(string)($input['rule_action']??'review');
        $mode=(string)($input['match_mode']??'normalized');
        $severity=(string)($input['severity']??'medium');
        $notes=trim((string)($input['notes']??''));
        $aliases=self::aliases((string)($input['aliases']??''));
        if($term===''||mb_strlen($term)>190)throw new RuntimeException('Enter a moderation term no longer than 190 characters.');
        if($category===''||mb_strlen($category)>80)throw new RuntimeException('Enter a category no longer than 80 characters.');
        if(!in_array($action,['block','review'],true))throw new RuntimeException('Choose Block or Hold for review.');
        if(!in_array($mode,['exact','normalized'],true))throw new RuntimeException('Choose a valid matching mode.');
        if(!in_array($severity,['low','medium','high','critical'],true))throw new RuntimeException('Choose a valid severity.');
        if(mb_strlen($notes)>500)throw new RuntimeException('Notes must be 500 characters or fewer.');
        $db->beginTransaction();
        try{
            if($id){
                if(!self::term($db,$id))throw new RuntimeException('That moderation rule no longer exists.');
                $stmt=$db->prepare('UPDATE moderation_terms SET term=:term,category=:category,action=:action,match_mode=:mode,severity=:severity,aliases_json=:aliases,notes=:notes,updated_by_user_id=:actor WHERE id=:id');
                $stmt->execute(['term'=>$term,'category'=>$category,'action'=>$action,'mode'=>$mode,'severity'=>$severity,'aliases'=>$aliases?json_encode($aliases,JSON_THROW_ON_ERROR):null,'notes'=>$notes?:null,'actor'=>(int)$user['id'],'id'=>$id]);
                self::audit($db,(int)$user['id'],'moderation.term_updated','moderation_term',$id,'Updated a Community moderation rule.',['category'=>$category,'action'=>$action,'match_mode'=>$mode,'severity'=>$severity]);
            }else{
                $stmt=$db->prepare('INSERT INTO moderation_terms (term,category,action,match_mode,severity,aliases_json,notes,active,created_by_user_id,updated_by_user_id) VALUES (:term,:category,:action,:mode,:severity,:aliases,:notes,1,:actor,:actor)');
                $stmt->execute(['term'=>$term,'category'=>$category,'action'=>$action,'mode'=>$mode,'severity'=>$severity,'aliases'=>$aliases?json_encode($aliases,JSON_THROW_ON_ERROR):null,'notes'=>$notes?:null,'actor'=>(int)$user['id']]);
                $id=(int)$db->lastInsertId();
                self::audit($db,(int)$user['id'],'moderation.term_created','moderation_term',$id,'Created a Community moderation rule.',['category'=>$category,'action'=>$action,'match_mode'=>$mode,'severity'=>$severity]);
            }
            $db->commit();
            return $id;
        }catch(Throwable $e){
            if($db->inTransaction())$db->rollBack();
            if($e instanceof RuntimeException)throw $e;
            if($e instanceof PDOException&&str_contains($e->getMessage(),'Duplicate'))throw new RuntimeException('That moderation term already exists.');
            throw new RuntimeException('The moderation rule could not be saved.');
        }
    }

    private static function toggleTerm(PDO $db,array $user,int $id): void
    {
        $term=self::term($db,$id);
        if(!$term)throw new RuntimeException('That moderation rule could not be found.');
        $active=(int)$term['active']===1?0:1;
        $stmt=$db->prepare('UPDATE moderation_terms SET active=:active,updated_by_user_id=:actor WHERE id=:id');
        $stmt->execute(['active'=>$active,'actor'=>(int)$user['id'],'id'=>$id]);
        self::audit($db,(int)$user['id'],$active?'moderation.term_activated':'moderation.term_deactivated','moderation_term',$id,$active?'Activated a Community moderation rule.':'Deactivated a Community moderation rule.',['active'=>(bool)$active]);
    }

    private static function reviewPost(PDO $db,array $user,int $postId,bool $approve): void
    {
        if($postId<1)throw new RuntimeException('That post could not be found.');
        $db->beginTransaction();
        try{
            $stmt=$db->prepare("SELECT cp.id,cp.moderation_status,cp.channel_id,cp.hidden_at,cp.deleted_at FROM channel_posts cp WHERE cp.id=:id FOR UPDATE");
            $stmt->execute(['id'=>$postId]);
            $post=$stmt->fetch();
            if(!$post||$post['moderation_status']!=='pending'||$post['hidden_at']!==null||$post['deleted_at']!==null)throw new RuntimeException('Only visible posts awaiting review can be moderated.');
            $status=$approve?'published':'rejected';
            $update=$db->prepare('UPDATE channel_posts SET moderation_status=:status,moderated_by_user_id=:actor,moderated_at=CURRENT_TIMESTAMP WHERE id=:id');
            $update->execute(['status'=>$status,'actor'=>(int)$user['id'],'id'=>$postId]);
            self::audit($db,(int)$user['id'],$approve?'moderation.post_approved':'moderation.post_rejected','channel_post',$postId,$approve?'Approved a held Community post.':'Rejected a held Community post.',['channel_id'=>(int)$post['channel_id'],'result'=>$status]);
            $db->commit();
        }catch(Throwable $e){
            if($db->inTransaction())$db->rollBack();
            if($e instanceof RuntimeException)throw $e;
            throw new RuntimeException('The moderation decision could not be saved.');
        }
    }

    private static function terms(PDO $db): array
    {
        return $db->query("SELECT mt.*,CONCAT(c.first_name,' ',c.last_name) creator,CONCAT(u.first_name,' ',u.last_name) updater FROM moderation_terms mt LEFT JOIN users c ON c.id=mt.created_by_user_id LEFT JOIN users u ON u.id=mt.updated_by_user_id ORDER BY mt.category,mt.active DESC,FIELD(mt.severity,'critical','high','medium','low'),mt.term")->fetchAll();
    }

    private static function term(PDO $db,int $id): ?array
    {
        if($id<1)return null;
        $s=$db->prepare('SELECT * FROM moderation_terms WHERE id=:id LIMIT 1');
        $s->execute(['id'=>$id]);
        return $s->fetch()?:null;
    }

    private static function queue(PDO $db): array
    {
        return $db->query("SELECT cp.id,cp.body,cp.moderation_status,cp.moderation_reason,cp.created_at,cp.moderated_at,c.name channel_name,p.title production_title,CONCAT(a.first_name,' ',a.last_name) author,a.display_role author_role,mt.term matched_term,mt.category,mt.action rule_action,mt.severity,CONCAT(m.first_name,' ',m.last_name) moderator FROM channel_posts cp JOIN channels c ON c.id=cp.channel_id LEFT JOIN productions p ON p.id=c.production_id JOIN users a ON a.id=cp.author_user_id LEFT JOIN moderation_terms mt ON mt.id=cp.moderation_term_id LEFT JOIN users m ON m.id=cp.moderated_by_user_id WHERE cp.moderation_status IN ('pending','rejected') AND cp.hidden_at IS NULL AND cp.deleted_at IS NULL ORDER BY cp.moderation_status='pending' DESC,cp.created_at DESC LIMIT 150")->fetchAll();
    }

    private static function aliases(string $value): array
    {
        $parts=preg_split('/[\r\n,]+/',$value)?:[];
        $out=[];
        foreach($parts as $part){$part=trim($part);if($part!=='')$out[]=$part;}
        return array_values(array_unique($out));
    }

    private static function audit(PDO $db,int $actor,string $event,string $type,int $id,string $summary,array $meta): void
    {
        $s=$db->prepare('INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:a,:e,:t,:i,:s,:m)');
        $s->execute(['a'=>$actor,'e'=>$event,'t'=>$type,'i'=>$id,'s'=>$summary,'m'=>json_encode($meta,JSON_THROW_ON_ERROR)]);
    }

    private static function flash(string $type,string $message): void { $_SESSION['moderation_flash']=['type'=>$type,'message'=>$message]; }
    private static function redirect(string $url): never { header('Location: '.$url,true,303);exit; }

    private static function categoryLabel(string $category): string
    {
        return ucwords(str_replace(['_','-'],' ',$category));
    }

    private static function termRow(array $term,string $basePath,string $csrf,callable $esc): void
    {
        $url=static fn(string $p):string=>($basePath?:'').$p;
        $severity=(string)$term['severity'];
        ?>
        <div class="mod-table-row<?= !$term['active']?' inactive':'' ?>">
            <div class="mod-table-term"><b><?= $esc((string)$term['term']) ?></b><?php if(!empty($term['notes'])):?><small><?= $esc((string)$term['notes']) ?></small><?php endif;?></div>
            <div><span class="mod-mobile-label">Severity</span><span class="mod-severity <?= $esc($severity) ?>"><?= $esc(ucfirst($severity)) ?></span></div>
            <div><span class="mod-mobile-label">Action</span><?= $term['action']==='block'?'Block immediately':'Hold for review' ?></div>
            <div><span class="mod-mobile-label">Matching</span><?= $term['match_mode']==='normalized'?'Normalized':'Exact' ?></div>
            <div><span class="mod-status <?= $term['active']?'active':'inactive' ?>"><?= $term['active']?'Active':'Inactive' ?></span></div>
            <div class="mod-table-actions"><a href="<?= $url('/admin/moderation/terms/edit?id='.(int)$term['id']) ?>">Edit</a><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc($csrf) ?>"><input type="hidden" name="action" value="toggle_term"><input type="hidden" name="term_id" value="<?= (int)$term['id'] ?>"><button><?= $term['active']?'Deactivate':'Activate' ?></button></form></div>
        </div>
        <?php
    }

    private static function page(string $route,string $basePath,array $user,PDO $db,?array $edit): never
    {
        $url=static fn(string $p):string=>($basePath?:'').$p;
        $esc=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');
        $flash=$_SESSION['moderation_flash']??null;unset($_SESSION['moderation_flash']);
        $test=$_SESSION['moderation_test']??null;unset($_SESSION['moderation_test']);
        $editing=$route==='/admin/moderation/terms/edit';
        $queue=$route==='/admin/moderation/queue';
        $title=$editing?($edit?'Edit moderation rule':'New moderation rule'):($queue?'Moderation queue':'Moderation terms');
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $esc($title) ?> · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/moderation.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar($route,$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Operations',$title,$basePath,[['label'=>'Term library','href'=>'/admin/moderation/terms','active'=>!$queue],['label'=>'Review queue','href'=>'/admin/moderation/queue','active'=>$queue]]);?><div class="mod-page">
        <?php if($flash):?><div class="mod-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif;?>
        <?php if($queue):
            $items=self::queue($db);$pending=array_values(array_filter($items,fn($p)=>$p['moderation_status']==='pending'));
        ?>
            <section class="mod-hero"><div><small>EXCEPTION-BASED MODERATION</small><h2><?= count($pending) ?> post<?= count($pending)===1?'':'s' ?> waiting for review.</h2><p>Clean posts publish immediately. Only rule matches appear here. Approving publishes the original text unchanged; rejecting keeps it private.</p></div></section>
            <div class="mod-queue"><?php foreach($items as $item):?><article class="mod-review <?= $esc($item['moderation_status']) ?>"><header><div><small><?= $esc(strtoupper($item['moderation_status'])) ?> · <?= $esc(strtoupper((string)$item['severity'])) ?></small><h3>#<?= $esc($item['channel_name']) ?><?= $item['production_title']?' · '.$esc($item['production_title']):'' ?></h3><p><?= $esc($item['author']) ?> · <?= $esc($item['author_role']) ?> · <?= $esc(date('M j · g:i A',strtotime($item['created_at']))) ?></p></div><span><?= $esc((string)$item['category']) ?></span></header><blockquote><?= nl2br($esc($item['body'])) ?></blockquote><div class="mod-match"><b>Matched rule</b><code><?= $esc((string)$item['matched_term']) ?></code><span><?= $esc((string)$item['moderation_reason']) ?></span></div><?php if($item['moderation_status']==='pending'):?><footer><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['moderation_csrf']) ?>"><input type="hidden" name="post_id" value="<?= (int)$item['id'] ?>"><button name="action" value="reject_post" class="mod-danger">Reject</button><button name="action" value="approve_post" class="button">Approve & publish</button></form></footer><?php else:?><footer class="mod-decided">Automatically blocked or rejected<?= $item['moderator']?' by '.$esc($item['moderator']):'' ?><?= $item['moderated_at']?' · '.$esc(date('M j · g:i A',strtotime($item['moderated_at']))):'' ?></footer><?php endif;?></article><?php endforeach;?><?php if(!$items):?><div class="mod-empty"><b>Nothing in moderation.</b><p>No Community posts have been held or blocked.</p></div><?php endif;?></div>
        <?php elseif($editing):
            $t=$edit??['id'=>0,'term'=>'','category'=>'profanity','action'=>'review','match_mode'=>'normalized','severity'=>'medium','aliases_json'=>null,'notes'=>'','active'=>1];
            $aliases=$t['aliases_json']?json_decode((string)$t['aliases_json'],true):[];
        ?>
            <section class="mod-edit-head"><div><small><?= $edit?'RULE SETTINGS':'NEW RULE' ?></small><h2><?= $edit?$esc($t['term']):'Add a moderation rule' ?></h2><p>Normalized matching catches common substitutions, punctuation and spacing without unrestricted typo-distance matching.</p></div><a href="<?= $url('/admin/moderation/terms') ?>">← Term library</a></section>
            <div class="mod-edit-grid"><form class="mod-form" method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['moderation_csrf']) ?>"><input type="hidden" name="action" value="save_term"><input type="hidden" name="term_id" value="<?= (int)$t['id'] ?>"><label>Canonical term<input name="term" maxlength="190" required value="<?= $esc($t['term']) ?>"></label><div class="mod-pair"><label>Category<input name="category" maxlength="80" required value="<?= $esc($t['category']) ?>"></label><label>Severity<select name="severity"><?php foreach(['low','medium','high','critical'] as $v):?><option value="<?= $v ?>"<?= $t['severity']===$v?' selected':'' ?>><?= ucfirst($v) ?></option><?php endforeach;?></select></label></div><div class="mod-pair"><label>When matched<select name="rule_action"><option value="review"<?= $t['action']==='review'?' selected':'' ?>>Hold for review</option><option value="block"<?= $t['action']==='block'?' selected':'' ?>>Block immediately</option></select></label><label>Matching<select name="match_mode"><option value="normalized"<?= $t['match_mode']==='normalized'?' selected':'' ?>>Normalized / fuzzy</option><option value="exact"<?= $t['match_mode']==='exact'?' selected':'' ?>>Exact</option></select></label></div><label>Aliases <span>comma or one per line</span><textarea name="aliases" rows="4"><?= $esc(is_array($aliases)?implode("\n",$aliases):'') ?></textarea></label><label>Admin notes<textarea name="notes" rows="3" maxlength="500"><?= $esc((string)$t['notes']) ?></textarea></label><button class="button" type="submit">Save moderation rule</button></form><aside class="mod-tester"><small>TEST BEFORE SAVING</small><h3>Would this text flag?</h3><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['moderation_csrf']) ?>"><input type="hidden" name="action" value="test_rule"><input type="hidden" name="term_id" value="<?= (int)$t['id'] ?>"><input type="hidden" name="term" value="<?= $esc($t['term']) ?>"><input type="hidden" name="match_mode" value="<?= $esc($t['match_mode']) ?>"><input type="hidden" name="aliases" value="<?= $esc(is_array($aliases)?implode(',',$aliases):'') ?>"><textarea name="test_text" rows="5" placeholder="Enter sample text..."><?= $test?$esc((string)$test['sample']):'' ?></textarea><button type="submit">Test current saved values</button></form><?php if($test):?><div class="mod-test-result <?= $test['hit']?'hit':'clear' ?>"><b><?= $test['hit']?'FLAGGED':'CLEAR' ?></b><span><?= $test['hit']?'This sample matches the current rule.':'This sample does not match the current rule.' ?></span></div><?php endif;?></aside></div>
        <?php else:
            $terms=self::terms($db);
            $pending=(int)$db->query("SELECT COUNT(*) FROM channel_posts WHERE moderation_status='pending' AND hidden_at IS NULL AND deleted_at IS NULL")->fetchColumn();
            $groups=[];foreach($terms as $term)$groups[(string)$term['category']][]=$term;
        ?>
            <section class="mod-list-head"><div><small>COMMUNITY SAFETY</small><h2>Moderation terms</h2><p>Rules are grouped by type so staff can scan what CTSMD intercepts and how each match is handled.</p></div><div class="mod-hero-actions"><a href="<?= $url('/admin/moderation/queue') ?>">Review queue<?= $pending?' · '.$pending:'' ?></a><a class="button" href="<?= $url('/admin/moderation/terms/edit') ?>">Add rule</a></div></section>
            <?php if($groups):?><section class="mod-table" aria-label="Moderation term library"><div class="mod-table-columns"><span>Term</span><span>Severity</span><span>Action</span><span>Matching</span><span>Status</span><span>Actions</span></div><?php foreach($groups as $category=>$categoryTerms):$activeCount=count(array_filter($categoryTerms,static fn(array $t):bool=>(bool)$t['active']));?><div class="mod-table-group"><header><div><b><?= $esc(self::categoryLabel($category)) ?></b><small><?= count($categoryTerms) ?> rule<?= count($categoryTerms)===1?'':'s' ?> · <?= $activeCount ?> active</small></div></header><?php foreach($categoryTerms as $term)self::termRow($term,$basePath,(string)$_SESSION['moderation_csrf'],$esc);?></div><?php endforeach;?></section><?php else:?><div class="mod-empty"><b>No moderation rules yet.</b></div><?php endif;?>
        <?php endif;?>
        </div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php exit;
    }

    private static function forbidden(): never { http_response_code(403);echo 'Restricted';exit; }
}
