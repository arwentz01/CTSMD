<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/ProductionContext.php';
require_once __DIR__ . '/ScheduleAudience.php';
require_once __DIR__ . '/ModerationService.php';

final class ScheduleNoticeExperience
{
    private const ROUTES=['/production/notices','/production/notice'];
    public static function handles(string $route):bool{return in_array($route,self::ROUTES,true);}

    public static function render(string $route,string $basePath):never
    {
        Auth::startSession();
        $db=Database::connect(dirname(__DIR__));$user=Auth::currentUser($db);if(!$user)self::redirect(($basePath?:'').'/login');
        if(!AccessPolicy::canManageProduction($user))self::forbidden($basePath,$user);
        $_SESSION['schedule_notice_csrf']??=bin2hex(random_bytes(24));
        if($_SERVER['REQUEST_METHOD']==='POST')self::handlePost($db,$user,$basePath);
        $production=ProductionContext::selected($db,$user);$productionId=$production?(int)$production['id']:0;
        $notices=$production?self::notices($db,$productionId):[];$selected=null;$channels=[];$audience=[];$deliveries=[];
        if($route==='/production/notice'){
            $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;$selected=self::notice($db,(int)$id,$productionId);
            if($selected){$selected['group_names']=ScheduleAudience::groupNamesForItem($db,(int)$selected['schedule_item_id']);$channels=$selected['audience_mode']==='production'&&$selected['audience_scope']==='all'?self::channels($db,(int)$selected['production_id']):[];$audience=ScheduleAudience::audienceMembersForItem($db,(int)$selected['schedule_item_id']);$deliveries=self::deliveries($db,(int)$selected['id']);}
        }
        self::page($route,$basePath,$user,$production,$notices,$selected,$channels,$audience,$deliveries);
    }

    private static function handlePost(PDO $db,array $user,string $basePath):never
    {
        if(!hash_equals((string)($_SESSION['schedule_notice_csrf']??''),(string)($_POST['csrf_token']??''))){self::flash('error','Your session token expired. Please try again.');self::redirect($basePath.'/production/notices');}
        $noticeId=filter_input(INPUT_POST,'notice_id',FILTER_VALIDATE_INT)?:0;$action=(string)($_POST['action']??'');
        try{
            if($action==='publish'){
                $channelStatus=self::publish($db,$user,(int)$noticeId,$_POST);
                if($channelStatus==='pending')self::flash('success','Schedule update published. The Community channel copy is awaiting moderator review and is not visible yet.');
                elseif($channelStatus==='rejected')self::flash('success','Schedule update published. The Community channel copy was blocked by the moderation rules and was not shown.');
                else self::flash('success','Schedule update published to the selected CTSMD destinations.');
            }elseif($action==='cancel'){self::cancel($db,$user,(int)$noticeId);self::flash('success','Draft cancelled. No communication was sent.');}
            else throw new RuntimeException('That update action is not available.');
        }catch(RuntimeException $e){self::flash('error',$e->getMessage());}
        self::redirect($basePath.'/production/notice?id='.(int)$noticeId);
    }

    private static function publish(PDO $db,array $user,int $noticeId,array $input):?string
    {
        if($noticeId<1)throw new RuntimeException('That communication draft could not be found.');
        $selectedProduction=ProductionContext::selected($db,$user);if(!$selectedProduction)throw new RuntimeException('Select an active production before publishing its updates.');
        $subject=trim((string)($input['subject']??''));$body=trim((string)($input['body']??''));$sendInApp=isset($input['send_in_app']);$sendChannel=isset($input['send_channel']);$channelId=filter_var($input['channel_id']??null,FILTER_VALIDATE_INT)?:0;
        if($subject===''||mb_strlen($subject)>190)throw new RuntimeException('Enter a subject no longer than 190 characters.');
        if($body===''||mb_strlen($body)>6000)throw new RuntimeException('Enter update text no longer than 6,000 characters.');
        if(!$sendInApp&&!$sendChannel)throw new RuntimeException('Choose at least one publishing destination.');
        if($sendChannel&&$channelId<1)throw new RuntimeException('Choose a Community channel for this update.');

        $db->beginTransaction();
        try{
            $noticeStmt=$db->prepare("SELECT scn.id,scn.schedule_item_id,scn.production_id,scn.audience_scope,scn.status,si.audience_mode FROM schedule_change_notices scn JOIN schedule_items si ON si.id=scn.schedule_item_id WHERE scn.id=:id FOR UPDATE");$noticeStmt->execute(['id'=>$noticeId]);$notice=$noticeStmt->fetch();
            if(!$notice)throw new RuntimeException('That communication draft no longer exists.');
            if((int)$notice['production_id']!==(int)$selectedProduction['id'])throw new RuntimeException('That update belongs to a different production workspace.');
            if($notice['status']!=='draft')throw new RuntimeException('Only draft updates can be published.');
            if($sendChannel&&($notice['audience_mode']!=='production'||$notice['audience_scope']!=='all'))throw new RuntimeException('Targeted schedule updates publish as targeted in-app notifications only. Community channel copies are limited to whole-production updates so family, staff, or group calls cannot be broadcast more broadly by mistake.');
            $audience=ScheduleAudience::audienceMembersForItem($db,(int)$notice['schedule_item_id']);
            if(!$audience)throw new RuntimeException('This schedule item currently resolves to an empty audience. Review its Production Groups before publishing.');

            $channelPostId=null;$channelModerationStatus=null;
            if($sendChannel){
                $channelStmt=$db->prepare("SELECT id FROM channels WHERE id=:id AND production_id=:production AND archived_at IS NULL AND COALESCE(access_mode,'audience')='audience'");$channelStmt->execute(['id'=>$channelId,'production'=>(int)$notice['production_id']]);if(!$channelStmt->fetchColumn())throw new RuntimeException('Choose a standard audience-based Community channel for this whole-production update. Private, Team, or hybrid rooms are not automatic broadcast destinations.');
                $postBody=$subject."\n\n".$body;
                $decision=ModerationService::evaluate($db,$postBody);$channelModerationStatus=(string)($decision['status']??'');
                if(!in_array($channelModerationStatus,['published','pending','rejected'],true))throw new RuntimeException('We could not verify the Community channel copy right now. Try publishing in-app only or try again.');
                $term=is_array($decision['term']??null)?$decision['term']:null;
                $post=$db->prepare('INSERT INTO channel_posts (channel_id,author_user_id,body,moderation_status,moderation_term_id,moderation_reason,pinned,reactions_json,created_at) VALUES (:channel,:author,:body,:status,:term_id,:reason,0,NULL,CURRENT_TIMESTAMP)');
                $post->execute(['channel'=>$channelId,'author'=>(int)$user['id'],'body'=>$postBody,'status'=>$channelModerationStatus,'term_id'=>$term?(int)$term['id']:null,'reason'=>$decision['reason']??null]);$channelPostId=(int)$db->lastInsertId();
                if($channelModerationStatus==='published'){
                    $delivery=$db->prepare("INSERT INTO schedule_notice_deliveries (notice_id,destination_type,destination_id,recipient_count,created_by_user_id) VALUES (:notice,'channel',:destination,:count,:actor)");$delivery->execute(['notice'=>$noticeId,'destination'=>$channelId,'count'=>count($audience),'actor'=>(int)$user['id']]);
                }
            }
            $notificationCount=0;
            if($sendInApp){
                $insert=$db->prepare("INSERT INTO app_notifications (recipient_user_id,source_type,source_id,title,body,action_path,created_at) VALUES (:recipient,'schedule_change',:source,:title,:body,'/schedule',CURRENT_TIMESTAMP)");
                foreach($audience as $member){$insert->execute(['recipient'=>(int)$member['id'],'source'=>$noticeId,'title'=>$subject,'body'=>$body]);$notificationCount++;}
                $delivery=$db->prepare("INSERT INTO schedule_notice_deliveries (notice_id,destination_type,destination_id,recipient_count,created_by_user_id) VALUES (:notice,'in_app',NULL,:count,:actor)");$delivery->execute(['notice'=>$noticeId,'count'=>$notificationCount,'actor'=>(int)$user['id']]);
            }
            $db->prepare("UPDATE schedule_change_notices SET subject=:subject,body=:body,audience_count=:count,status='published',published_at=CURRENT_TIMESTAMP WHERE id=:id")->execute(['subject'=>$subject,'body'=>$body,'count'=>count($audience),'id'=>$noticeId]);
            self::audit($db,(int)$user['id'],'schedule.notice_published',$noticeId,'Published schedule change communication.',['production_id'=>(int)$notice['production_id'],'schedule_item_id'=>(int)$notice['schedule_item_id'],'audience_mode'=>$notice['audience_mode'],'group_ids'=>ScheduleAudience::groupIdsForItem($db,(int)$notice['schedule_item_id']),'destinations'=>['in_app'=>$sendInApp,'channel'=>$sendChannel],'channel_id'=>$sendChannel?$channelId:null,'channel_post_id'=>$channelPostId,'channel_moderation_status'=>$channelModerationStatus,'recipient_count'=>count($audience),'audience_user_ids'=>array_map(static fn(array $r):int=>(int)$r['id'],$audience)]);
            $db->commit();return $channelModerationStatus;
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof RuntimeException)throw $e;throw new RuntimeException('The schedule update could not be published.');}
    }

    private static function cancel(PDO $db,array $user,int $noticeId):void
    {
        $selected=ProductionContext::selected($db,$user);if(!$selected)throw new RuntimeException('Select an active production before cancelling its update.');
        $db->beginTransaction();try{$check=$db->prepare('SELECT production_id,status FROM schedule_change_notices WHERE id=:id FOR UPDATE');$check->execute(['id'=>$noticeId]);$notice=$check->fetch();if(!$notice||$notice['status']!=='draft')throw new RuntimeException('Only an active draft can be cancelled.');if((int)$notice['production_id']!==(int)$selected['id'])throw new RuntimeException('That update belongs to a different production workspace.');$db->prepare("UPDATE schedule_change_notices SET status='cancelled' WHERE id=:id")->execute(['id'=>$noticeId]);self::audit($db,(int)$user['id'],'schedule.notice_cancelled',$noticeId,'Cancelled schedule change communication draft.',['production_id'=>(int)$notice['production_id']]);$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof RuntimeException)throw $e;throw new RuntimeException('The draft could not be cancelled.');}
    }

    private static function notices(PDO $db,int $productionId):array{$s=$db->prepare("SELECT scn.id,scn.subject,scn.body,scn.audience_scope,scn.audience_count,scn.status,scn.created_at,scn.published_at,scn.schedule_item_id,si.title schedule_title,si.audience_mode,CONCAT(u.first_name,' ',u.last_name) creator FROM schedule_change_notices scn JOIN schedule_items si ON si.id=scn.schedule_item_id LEFT JOIN users u ON u.id=scn.created_by_user_id WHERE scn.production_id=:production ORDER BY FIELD(scn.status,'draft','published','cancelled'),scn.created_at DESC,scn.id DESC");$s->execute(['production'=>$productionId]);$rows=$s->fetchAll();foreach($rows as &$row)$row['group_names']=$row['audience_mode']==='groups'?ScheduleAudience::groupNamesForItem($db,(int)$row['schedule_item_id']):[];unset($row);return $rows;}
    private static function notice(PDO $db,int $id,int $productionId):?array{if($id<1||$productionId<1)return null;$s=$db->prepare("SELECT scn.*,si.title schedule_title,si.starts_at,si.location,si.audience_mode,p.title production_title,CONCAT(u.first_name,' ',u.last_name) creator FROM schedule_change_notices scn JOIN schedule_items si ON si.id=scn.schedule_item_id JOIN productions p ON p.id=scn.production_id LEFT JOIN users u ON u.id=scn.created_by_user_id WHERE scn.id=:id AND scn.production_id=:production LIMIT 1");$s->execute(['id'=>$id,'production'=>$productionId]);return $s->fetch()?:null;}
    private static function channels(PDO $db,int $productionId):array{$s=$db->prepare("SELECT id,name,description FROM channels WHERE production_id=:production AND archived_at IS NULL AND COALESCE(access_mode,'audience')='audience' ORDER BY sort_order,name");$s->execute(['production'=>$productionId]);return $s->fetchAll();}
    private static function deliveries(PDO $db,int $noticeId):array{$s=$db->prepare("SELECT snd.destination_type,snd.destination_id,snd.recipient_count,snd.created_at,c.name channel_name,CONCAT(u.first_name,' ',u.last_name) creator FROM schedule_notice_deliveries snd LEFT JOIN channels c ON snd.destination_type='channel' AND c.id=snd.destination_id LEFT JOIN users u ON u.id=snd.created_by_user_id WHERE snd.notice_id=:notice ORDER BY snd.created_at");$s->execute(['notice'=>$noticeId]);return $s->fetchAll();}
    private static function audit(PDO $db,int $actor,string $event,int $id,string $summary,array $meta):void{$s=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,:event,'schedule_change_notice',:id,:summary,:meta)");$s->execute(['actor'=>$actor,'event'=>$event,'id'=>$id,'summary'=>$summary,'meta'=>json_encode($meta,JSON_THROW_ON_ERROR)]);}

    private static function page(string $route,string $basePath,array $user,?array $production,array $notices,?array $selected,array $channels,array $audience,array $deliveries):never
    {
        $url=static fn(string $p):string=>($basePath?:'').$p;$esc=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');$flash=$_SESSION['schedule_notice_flash']??$_SESSION['production_context_flash']??null;unset($_SESSION['schedule_notice_flash'],$_SESSION['production_context_flash']);$title=$route==='/production/notices'?'Production updates':($selected['subject']??'Schedule update');$subnav=[['label'=>'Overview','href'=>'/production','active'=>false],['label'=>'Schedule','href'=>'/schedule','active'=>false],['label'=>'Groups','href'=>'/production/groups','active'=>false],['label'=>'Updates','href'=>'/production/notices','active'=>true],['label'=>'Resources','href'=>'/resources','active'=>false],['label'=>'Playbill','href'=>'/playbills','active'=>false]];
        header('Content-Type: text/html; charset=utf-8');?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $esc($title) ?> · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/schedule-notices.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar($route,$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Production',$title,$basePath,$subnav);?><div class="notice-page">
<?php if($flash):?><div class="notice-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif;?>
<?php if(!$production):?><section class="notice-empty"><b>No active production selected</b></section>
<?php elseif($route==='/production/notices'):?><section class="notice-hero"><div><small><?= $esc(strtoupper($production['title'])) ?> · CHANGE COMMUNICATION</small><h2>Review before you broadcast.</h2><p>Recipient counts follow the schedule item's current production/group targeting.</p></div><a class="button" href="<?= $url('/schedule') ?>">Open schedule</a></section><div class="notice-list"><?php if(!$notices):?><div class="notice-empty"><b>No schedule updates yet</b></div><?php endif;?><?php foreach($notices as $n):$target=$n['audience_mode']==='groups'&&$n['group_names']?implode(' + ',$n['group_names']):'Whole production';?><a href="<?= $url('/production/notice?id='.(int)$n['id']) ?>" class="notice-row"><span class="notice-status <?= $esc($n['status']) ?>"><?= $esc(strtoupper($n['status'])) ?></span><div><small><?= $esc(strtoupper($target)) ?> · <?= (int)$n['audience_count'] ?> PEOPLE</small><h3><?= $esc($n['subject']) ?></h3><p><?= $esc($n['schedule_title']) ?> · <?= $esc(date('M j · g:i A',strtotime($n['created_at']))) ?></p></div><b>Review →</b></a><?php endforeach;?></div>
<?php else:?><?php if(!$selected):?><section class="notice-empty"><b>Update not found in this production</b><a class="button" href="<?= $url('/production/notices') ?>">Back to updates</a></section><?php else:$target=$selected['audience_mode']==='groups'&&$selected['group_names']?implode(' + ',$selected['group_names']):'Whole production';?><section class="notice-detail-head"><div><small><?= $esc(strtoupper($selected['status'])) ?> · <?= $esc(strtoupper($target)) ?></small><h2><?= $esc($selected['schedule_title']) ?></h2><p><?= $esc(date('l, M j · g:i A',strtotime($selected['starts_at']))) ?> · <?= $esc($selected['location']) ?></p></div><a href="<?= $url('/production/notices') ?>">← All updates</a></section><div class="notice-layout"><section class="notice-card"><header><small>MESSAGE</small><h3><?= $selected['status']==='draft'?'Review & publish':'Published communication' ?></h3></header>
<?php if($selected['status']==='draft'):?><form method="post" class="notice-form"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['schedule_notice_csrf']) ?>"><input type="hidden" name="notice_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="action" value="publish"><label>Subject<input name="subject" maxlength="190" value="<?= $esc($selected['subject']) ?>" required></label><label>Message<textarea name="body" rows="7" maxlength="6000" required><?= $esc($selected['body']) ?></textarea></label><fieldset><legend>Publish to</legend><label class="notice-check"><input type="checkbox" name="send_in_app" value="1" checked><span><b>Targeted in-app notifications</b><small><?= count($audience) ?> recipients resolved from <?= $esc($target) ?>.</small></span></label><?php if($selected['audience_mode']==='production'&&$selected['audience_scope']==='all'):?><label class="notice-check"><input type="checkbox" name="send_channel" value="1"><span><b>Community channel</b><small>Optional copy for a whole-production update.</small></span></label><label>Channel<select name="channel_id"><option value="">Choose standard production channel</option><?php foreach($channels as $channel):?><option value="<?= (int)$channel['id'] ?>"><?= $esc($channel['name']) ?></option><?php endforeach;?></select></label><?php else:?><div class="notice-help"><b>Targeted audience protection:</b> Family-only, staff-only, and Production Group calls publish here as targeted in-app notifications only. Post separately to an intentionally chosen Community room if a channel message is also appropriate.</div><?php endif;?></fieldset><footer><button class="button" type="submit">Publish update</button></footer></form><form method="post" class="notice-cancel"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['schedule_notice_csrf']) ?>"><input type="hidden" name="notice_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="action" value="cancel"><button type="submit">Cancel draft</button></form>
<?php else:?><article class="notice-published-copy"><h3><?= $esc($selected['subject']) ?></h3><p><?= nl2br($esc($selected['body'])) ?></p></article><?php endif;?></section><aside class="notice-card"><header><small>AUDIENCE</small><h3><?= count($audience) ?> current recipients</h3></header><p class="notice-help">Recipients are recalculated from active production memberships and Production Groups at publish time.</p><div class="notice-people"><?php foreach($audience as $member):?><span><i><?= $esc(strtoupper(substr($member['audience_type'],0,1))) ?></i><b><?= $esc($member['name']) ?></b><small><?= $esc(ucfirst($member['audience_type'])) ?></small></span><?php endforeach;?></div><?php if($deliveries):?><div class="notice-deliveries"><small>DELIVERED TO</small><?php foreach($deliveries as $d):?><p><b><?= $d['destination_type']==='channel'?'# '.$esc((string)$d['channel_name']):'In-app notifications' ?></b><span><?= (int)$d['recipient_count'] ?> recipients · <?= $esc(date('M j · g:i A',strtotime($d['created_at']))) ?></span></p><?php endforeach;?></div><?php endif;?></aside></div><?php endif;?><?php endif;?>
</div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php exit;
    }

    private static function forbidden(string $basePath,array $user):never{http_response_code(403);echo 'Restricted';exit;}
    private static function flash(string $type,string $message):void{$_SESSION['schedule_notice_flash']=['type'=>$type,'message'=>$message];}
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
}
