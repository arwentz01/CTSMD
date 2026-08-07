<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';
require_once __DIR__.'/AppNavigation.php';
require_once __DIR__.'/AccessPolicy.php';
require_once __DIR__.'/ProductionContext.php';
require_once __DIR__.'/ScheduleAudience.php';
require_once __DIR__.'/CalendarService.php';

final class CalendarExperience
{
    private const ROUTES=['/calendar','/calendar/feed'];
    public static function handles(string $route):bool{return in_array($route,self::ROUTES,true);}

    public static function render(string $route,string $basePath):never
    {
        if($route==='/calendar/feed'){self::feed($basePath);}
        if(session_status()!==PHP_SESSION_ACTIVE)session_start();
        $db=Database::connect(dirname(__DIR__));$user=self::currentUser($db);$_SESSION['calendar_csrf']??=bin2hex(random_bytes(24));
        if($_SERVER['REQUEST_METHOD']==='POST')self::handlePost($db,$user,$basePath);
        self::page($db,$user,$basePath);
    }

    private static function feed(string $basePath):never
    {
        $db=Database::connect(dirname(__DIR__));$token=(string)($_GET['token']??'');$user=CalendarService::userForToken($db,$token);if(!$user){http_response_code(404);exit;}
        $events=CalendarService::visibleEvents($db,$user,(new DateTimeImmutable('-6 months')),(new DateTimeImmutable('+18 months')));
        header('Content-Type:text/calendar; charset=utf-8');header('Content-Disposition:inline; filename="ctsmd-connect.ics"');echo CalendarService::ics($events,'CTSMD Connect · '.$user['name']);exit;
    }

    private static function handlePost(PDO $db,array $user,string $basePath):never
    {
        if(!hash_equals((string)($_SESSION['calendar_csrf']??''),(string)($_POST['csrf_token']??''))){self::flash('error','Your session token expired.');self::redirect($basePath.'/calendar');}
        $action=(string)($_POST['action']??'');
        try{
            if($action==='rotate_feed'){CalendarService::rotateSubscriptionToken($db,(int)$user['id']);self::flash('success','Calendar subscription link rotated. Your previous link no longer works.');}
            elseif($action==='duplicate'){if(!AccessPolicy::canManageProduction($user))throw new RuntimeException('Only production staff can duplicate schedule items.');self::duplicate($db,$user,(int)($_POST['schedule_item_id']??0));self::flash('success','Schedule item duplicated into the same production.');}
            elseif($action==='cancel'){if(!AccessPolicy::canManageProduction($user))throw new RuntimeException('Only production staff can cancel schedule items.');self::cancel($db,$user,(int)($_POST['schedule_item_id']??0));self::flash('success','Schedule item cancelled and retained in calendar history.');}
            else throw new RuntimeException('That calendar action is unavailable.');
        }catch(RuntimeException $e){self::flash('error',$e->getMessage());}
        self::redirect($basePath.'/calendar'.self::returnQuery());
    }

    private static function duplicate(PDO $db,array $user,int $id):void
    {
        $selected=ProductionContext::selected($db,$user);if(!$selected)throw new RuntimeException('Select a production first.');
        $db->beginTransaction();try{$s=$db->prepare('SELECT * FROM schedule_items WHERE id=:id AND production_id=:production FOR UPDATE');$s->execute(['id'=>$id,'production'=>(int)$selected['id']]);$item=$s->fetch();if(!$item)throw new RuntimeException('That schedule item is not in the working production.');$start=(new DateTimeImmutable($item['starts_at']))->modify('+7 days');$end=$item['ends_at']?(new DateTimeImmutable($item['ends_at']))->modify('+7 days'):null;$call=$item['family_call_at']?(new DateTimeImmutable($item['family_call_at']))->modify('+7 days'):null;$i=$db->prepare("INSERT INTO schedule_items (production_id,title,starts_at,ends_at,family_call_at,location,visibility,item_type,audience_mode,status,duplicate_of_id) VALUES (:production,:title,:start,:end,:call,:location,:visibility,:type,:audience,'active',:original)");$i->execute(['production'=>(int)$selected['id'],'title'=>$item['title'],'start'=>$start->format('Y-m-d H:i:s'),'end'=>$end?->format('Y-m-d H:i:s'),'call'=>$call?->format('Y-m-d H:i:s'),'location'=>$item['location'],'visibility'=>$item['visibility'],'type'=>$item['item_type'],'audience'=>$item['audience_mode'],'original'=>$id]);$newId=(int)$db->lastInsertId();foreach(ScheduleAudience::groupIdsForItem($db,$id) as $groupId)$db->prepare('INSERT INTO schedule_item_groups (schedule_item_id,group_id) VALUES (:item,:group)')->execute(['item'=>$newId,'group'=>$groupId]);self::audit($db,(int)$user['id'],'schedule.duplicated',$newId,'Duplicated schedule item one week forward.',['source_id'=>$id,'production_id'=>(int)$selected['id']]);$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof RuntimeException)throw $e;throw new RuntimeException('The schedule item could not be duplicated.');}
    }

    private static function cancel(PDO $db,array $user,int $id):void
    {
        $selected=ProductionContext::selected($db,$user);if(!$selected)throw new RuntimeException('Select a production first.');
        $db->beginTransaction();try{$s=$db->prepare("SELECT id,title,status,visibility FROM schedule_items WHERE id=:id AND production_id=:production FOR UPDATE");$s->execute(['id'=>$id,'production'=>(int)$selected['id']]);$item=$s->fetch();if(!$item)throw new RuntimeException('That schedule item is not in the working production.');if($item['status']==='cancelled'){ $db->commit();return; }$db->prepare("UPDATE schedule_items SET status='cancelled',cancelled_at=CURRENT_TIMESTAMP,cancelled_by_user_id=:actor WHERE id=:id")->execute(['actor'=>(int)$user['id'],'id'=>$id]);$audience=ScheduleAudience::audienceMembersForItem($db,$id);$n=$db->prepare("INSERT INTO schedule_change_notices (schedule_item_id,production_id,created_by_user_id,audience_scope,audience_count,subject,body,status) VALUES (:item,:production,:actor,:scope,:count,:subject,:body,'draft')");$n->execute(['item'=>$id,'production'=>(int)$selected['id'],'actor'=>(int)$user['id'],'scope'=>$item['visibility'],'count'=>count($audience),'subject'=>'Cancelled · '.$item['title'],'body'=>$item['title'].' has been cancelled. Please review CTSMD Connect for the latest production schedule.']);self::audit($db,(int)$user['id'],'schedule.cancelled',$id,'Cancelled schedule item and created communication draft.',['production_id'=>(int)$selected['id'],'notice_id'=>(int)$db->lastInsertId()]);$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof RuntimeException)throw $e;throw new RuntimeException('The schedule item could not be cancelled.');}
    }

    private static function page(PDO $db,array $user,string $basePath):never
    {
        $url=static fn(string $p):string=>($basePath?:'').$p;$e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');$view=in_array(($_GET['view']??'month'),['month','week','agenda'],true)?(string)$_GET['view']:'month';$date=self::date((string)($_GET['date']??''));$productionFilter=filter_input(INPUT_GET,'production',FILTER_VALIDATE_INT)?:0;$productions=ProductionContext::activeProductions($db,$user);$filter=$productionFilter>0?$productionFilter:null;
        [$from,$to]=self::range($view,$date);$events=CalendarService::visibleEvents($db,$user,$from,$to,$filter);$conflicts=CalendarService::conflicts($events);$token=CalendarService::subscriptionToken($db,(int)$user['id']);$feed=$url('/calendar/feed?token='.$token);$staff=AccessPolicy::canManageProduction($user);$flash=$_SESSION['calendar_flash']??null;unset($_SESSION['calendar_flash']);$prev=self::shiftDate($view,$date,-1);$next=self::shiftDate($view,$date,1);
        header('Content-Type:text/html; charset=utf-8');?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Calendar · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/calendar.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/calendar',$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Theatre','Calendar',$basePath);?><div class="cal-page"><?php if($flash):?><div class="cal-flash <?= $e($flash['type']) ?>"><?= $e($flash['message']) ?></div><?php endif;?><section class="cal-hero"><div><small>YOUR CTSMD CALENDAR</small><h2>One view across every active show.</h2><p>Calls remain filtered by the productions, groups and family relationships you already have access to.</p></div><div class="cal-view-tabs"><?php foreach(['month'=>'Month','week'=>'Week','agenda'=>'Agenda'] as $v=>$label):?><a class="<?= $view===$v?'active':'' ?>" href="<?= $url('/calendar?view='.$v.'&date='.$date->format('Y-m-d').($filter?'&production='.$filter:'')) ?>"><?= $label ?></a><?php endforeach;?></div></section><section class="cal-tools"><div class="cal-period"><a href="<?= $url('/calendar?view='.$view.'&date='.$prev->format('Y-m-d').($filter?'&production='.$filter:'')) ?>">‹</a><h3><?= $e(self::periodLabel($view,$date)) ?></h3><a href="<?= $url('/calendar?view='.$view.'&date='.$next->format('Y-m-d').($filter?'&production='.$filter:'')) ?>">›</a></div><form method="get"><input type="hidden" name="view" value="<?= $e($view) ?>"><input type="hidden" name="date" value="<?= $e($date->format('Y-m-d')) ?>"><select name="production" onchange="this.form.submit()"><option value="">All active productions</option><?php foreach($productions as $p):?><option value="<?= (int)$p['id'] ?>"<?= $filter===(int)$p['id']?' selected':'' ?>><?= $e($p['title']) ?></option><?php endforeach;?></select></form></section>
<?php if($view==='month'):self::month($events,$conflicts,$date,$url,$e,$staff);else:self::agenda($events,$conflicts,$url,$e,$staff,$view);?>
<section class="cal-subscribe"><div><small>CALENDAR SUBSCRIPTION</small><h3>Subscribe from Apple, Google or Outlook.</h3><p>This private feed updates as your visible CTSMD schedule changes. Rotate it if the link is ever shared accidentally.</p><code><?= $e($feed) ?></code></div><form method="post"><input type="hidden" name="csrf_token" value="<?= $e((string)$_SESSION['calendar_csrf']) ?>"><input type="hidden" name="action" value="rotate_feed"><button type="submit">Rotate private link</button></form></section></div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php exit;
    }

    private static function month(array $events,array $conflicts,DateTimeImmutable $date,Closure $url,Closure $e,bool $staff):void{$first=$date->modify('first day of this month');$grid=$first->modify('monday this week');$end=$date->modify('last day of this month')->modify('sunday this week');$by=[];foreach($events as $ev)$by[substr($ev['starts_at'],0,10)][]=$ev;echo '<div class="cal-weekdays">';foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d)echo '<b>'.$d.'</b>';echo '</div><div class="cal-month">';for($d=$grid;$d<=$end;$d=$d->modify('+1 day')){$key=$d->format('Y-m-d');echo '<section class="cal-day'.($d->format('m')!==$date->format('m')?' muted':'').'"><span>'.$d->format('j').'</span>';foreach($by[$key]??[] as $ev)self::event($ev,$conflicts,$url,$e,$staff,true);echo '</section>';}echo '</div>';}
    private static function agenda(array $events,array $conflicts,Closure $url,Closure $e,bool $staff,string $view):void{echo '<div class="cal-agenda">';if(!$events)echo '<section class="cal-empty"><b>No visible events in this '.$e($view).'.</b></section>';foreach($events as $ev)self::event($ev,$conflicts,$url,$e,$staff,false);echo '</div>';}
    private static function event(array $ev,array $conflicts,Closure $url,Closure $e,bool $staff,bool $compact):void{$cancel=$ev['status']==='cancelled';$target=$ev['group_names']?implode(' + ',$ev['group_names']):'Whole production';echo '<article class="cal-event'.($compact?' compact':'').($cancel?' cancelled':'').(isset($conflicts[(int)$ev['id']])?' conflict':'').'"><div><small>'.$e(strtoupper($ev['production_title'])).' · '.$e(strtoupper($target)).'</small><h4>'.$e($ev['title']).'</h4><p>'.$e(date('D M j · g:i A',strtotime($ev['starts_at']))).' · '.$e($ev['location']).'</p>';if(isset($conflicts[(int)$ev['id']]))echo '<em>Schedule conflict</em>';if($cancel)echo '<em>Cancelled</em>';echo '</div>';if($staff&&!$compact&& !$cancel){echo '<footer><a href="'.$url('/production/edit?id='.(int)$ev['id']).'">Edit</a><form method="post"><input type="hidden" name="csrf_token" value="'.htmlspecialchars((string)$_SESSION['calendar_csrf'],ENT_QUOTES,'UTF-8').'"><input type="hidden" name="schedule_item_id" value="'.(int)$ev['id'].'"><button name="action" value="duplicate">Duplicate +7 days</button><button name="action" value="cancel">Cancel event</button></form></footer>';}echo '</article>';}
    private static function range(string $view,DateTimeImmutable $date):array{return match($view){'week'=>[$date->modify('monday this week')->setTime(0,0),$date->modify('monday next week')->setTime(0,0)],'agenda'=>[$date->setTime(0,0),$date->modify('+90 days')->setTime(0,0)],default=>[$date->modify('first day of this month')->modify('monday this week')->setTime(0,0),$date->modify('last day of this month')->modify('monday next week')->setTime(0,0)]};}
    private static function shiftDate(string $view,DateTimeImmutable $date,int $dir):DateTimeImmutable{return $date->modify(($dir>0?'+':'-').($view==='month'?'1 month':($view==='week'?'1 week':'90 days')));}
    private static function periodLabel(string $view,DateTimeImmutable $date):string{return match($view){'week'=>$date->modify('monday this week')->format('M j').' – '.$date->modify('sunday this week')->format('M j, Y'),'agenda'=>'Next 90 days from '.$date->format('M j, Y'),default=>$date->format('F Y')};}
    private static function date(string $raw):DateTimeImmutable{try{return $raw!==''?new DateTimeImmutable($raw):new DateTimeImmutable('today');}catch(Throwable){return new DateTimeImmutable('today');}}
    private static function returnQuery():string{$view=(string)($_POST['return_view']??'');return $view!==''?'?view='.rawurlencode($view):'';}
    private static function currentUser(PDO $db):array{$r=$db->query("SELECT id,CONCAT(first_name,' ',last_name) name,display_role role,initials FROM users WHERE is_demo_current_user=1 AND active=1 LIMIT 1")->fetch();if(!$r)throw new RuntimeException('Demo user is missing.');return $r;}
    private static function audit(PDO $db,int $actor,string $event,int $id,string $summary,array $meta):void{$s=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,:event,'schedule_item',:id,:summary,:meta)");$s->execute(['actor'=>$actor,'event'=>$event,'id'=>$id,'summary'=>$summary,'meta'=>json_encode($meta,JSON_THROW_ON_ERROR)]);}
    private static function flash(string $type,string $message):void{$_SESSION['calendar_flash']=['type'=>$type,'message'=>$message];}
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
}
