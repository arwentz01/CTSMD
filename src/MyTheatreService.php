<?php
declare(strict_types=1);
require_once __DIR__.'/MemberParticipationService.php';
require_once __DIR__.'/MemberScheduleService.php';
require_once __DIR__.'/FamilyFormService.php';
require_once __DIR__.'/CastingOfferService.php';
require_once __DIR__.'/ProductionChecklistAssignmentService.php';
require_once __DIR__.'/ProductionNotificationAccess.php';
require_once __DIR__.'/ProductionArchiveService.php';
require_once __DIR__.'/VolunteerCoverageService.php';
require_once __DIR__.'/IdentityRolePolicy.php';
require_once __DIR__.'/WorkspaceUI.php';

/** Existing policies supply every action; no membership, account or workflow writes. */
final class MyTheatreService
{
    public static function empty():array{return ['subjects'=>[],'productions'=>[],'events'=>[],'actions'=>[],'updates'=>[],'commitments'=>[],'forms'=>[],'context'=>'household','person'=>null,'history_subjects'=>[]];}
    public static function build(PDO $db,array $viewer,string $context='household',?int $production=null,?DateTimeImmutable $now=null):array
    {
        $now??=new DateTimeImmutable('now');$id=(int)$viewer['id'];$subjects=MemberParticipationService::subjects($db,$id);
        if(!$subjects)return self::empty();
        $person=match($context){'household'=>null,'self'=>$id,default=>ctype_digit($context)?(int)$context:-1};
        if($person!==null&&!isset($subjects[$person]))throw new RuntimeException('This participant context is unavailable.');
        $productions=MemberParticipationService::productions($db,$viewer,$subjects,$person);
        if($production!==null){$productions=array_values(array_filter($productions,static fn(array $p):bool=>$p['id']===$production));if(!$productions)throw new RuntimeException('This production is unavailable in your personal context.');}
        $events=MemberScheduleService::events($db,$subjects,$now,$now->modify('+45 days'),$person,$production);
        foreach($productions as &$p){$p['next_event']=null;foreach($events as $event)if((int)$event['production_id']===$p['id']&&$event['status']!=='cancelled'){$p['next_event']=$event;break;}$p['archive_allowed']=$p['lifecycle_stage']==='archived'&&ProductionArchiveService::canViewProduction($db,$viewer,$p['id']);}unset($p);
        $actions=[];$forms=[];
        $add=static function(string $key,array $action)use(&$actions):void{$actions[$key]=$action;};
        $matches=static fn(int $who,?int $p):bool=>($person===null||$person===$who)&&($production===null||$production===$p);
        foreach(CastingOfferService::pendingForUser($db,$id) as $offer){$who=(int)$offer['student_user_id'];$pid=(int)$offer['production_id'];if(!isset($subjects[$who])||!$matches($who,$pid))continue;
            $add('offer-'.$offer['id'],['kind'=>'Casting offer','title'=>$offer['role_snapshot'],'person'=>$subjects[$who]['name'],'production'=>$offer['production_title'],'due_at'=>null,'priority'=>0,'href'=>'/notifications?offer='.(int)$offer['id'].'#casting-offer-'.(int)$offer['id'],'action'=>IdentityRolePolicy::isStudent($db,$id)?'Review with guardian':'Review offer']);
        }
        foreach(FamilyFormService::assignmentsForViewer($db,$id) as $form){$who=(int)$form['subject_user_id'];$pid=$form['production_id']===null?null:(int)$form['production_id'];if(!isset($subjects[$who])||!$matches($who,$pid))continue;$forms[]=$form;
            if(!in_array($form['status'],['missing','due_soon'],true)||in_array($form['submission_status'],['submitted','approved'],true))continue;
            if(WorkspaceUI::formReadOnly($db,(int)$form['form_id'])||WorkspaceUI::productionReadOnly($db,$pid))continue;
            // Student completion rules remain enforced in the existing form workflow.
            $add('form-'.$form['id'],['kind'=>'Form','title'=>$form['title'],'person'=>$form['subject_name'],'production'=>$form['production_title']?:'CTSMD','due_at'=>$form['due_at'],'priority'=>1,'href'=>'/forms/view?id='.(int)$form['id'],'action'=>'Review form']);
        }
        foreach(ProductionChecklistAssignmentService::actionItems($db,$id) as $task){$who=(int)$task['assigned_user_id'];$pid=(int)$task['production_id'];if(!isset($subjects[$who])||!$matches($who,$pid))continue;
            $add('task-'.$task['assignment_id'],['kind'=>'Personal task','title'=>$task['title'],'person'=>$subjects[$who]['name'],'production'=>$task['production_title'],'due_at'=>$task['due_at'],'priority'=>2,'href'=>'/notifications#casting-offers','action'=>'Review task']);
        }
        $commitments=[];
        if($person===null||$person===$id){
            $q=$db->prepare("SELECT s.id signup_id,s.status,v.id shift_id,v.production_id,v.title,v.starts_at,v.ends_at,v.location,p.title production_title FROM volunteer_shift_signups s JOIN volunteer_shifts v ON v.id=s.shift_id LEFT JOIN productions p ON p.id=v.production_id WHERE s.user_id=? AND s.status IN ('signed_up','checked_in','waitlisted') AND v.ends_at>=? AND (v.production_id IS NULL OR (p.is_active=1 AND p.lifecycle_stage IN ('upcoming','planning','in_production','performing'))) ORDER BY v.starts_at,v.id");$q->execute([$id,$now->format('Y-m-d H:i:s')]);
            $requirements=VolunteerCoverageService::missingRequirementsForShifts($db,$id,array_column($qRows=$q->fetchAll(),'shift_id'));
            foreach($qRows as $shift){if($production!==null&&(int)$shift['production_id']!==$production)continue;$shift['missing_requirements']=$requirements[(int)$shift['shift_id']]??[];$commitments[]=$shift;
                if($shift['status']!=='waitlisted'&&(!VolunteerCoverageService::isLiveVolunteer($db,$id)||$shift['missing_requirements']))$add('shift-'.$shift['signup_id'],['kind'=>'Volunteer readiness','title'=>$shift['title'],'person'=>$subjects[$id]['name'],'production'=>$shift['production_title']?:'CTSMD','due_at'=>$shift['starts_at'],'priority'=>3,'href'=>'/volunteer/shift?id='.(int)$shift['shift_id'],'action'=>'Review requirements']);
            }
        }
        $updates=[];
        if($person===null||$person===$id){$q=$db->prepare('SELECT id,source_type,source_id,title,body,action_path,read_at,created_at FROM app_notifications WHERE recipient_user_id=? ORDER BY created_at DESC,id DESC LIMIT 60');$q->execute([$id]);$seen=[];
            foreach(ProductionNotificationAccess::filter($db,$id,$q->fetchAll()) as $notice){if(!in_array($notice['source_type'],['schedule_change','casting_release'],true))continue;
                if($notice['source_type']==='schedule_change'){$n=$db->prepare("SELECT schedule_item_id FROM schedule_change_notices WHERE id=? AND status='published'");$n->execute([(int)$notice['source_id']]);$item=(int)$n->fetchColumn();if(!$item||!MemberScheduleService::item($db,$subjects,$item,$person))continue;}
                else{$allowed=false;foreach($productions as $p)if($p['id']===(int)$notice['source_id']&&$p['can_schedule'])$allowed=true;if(!$allowed)continue;}
                $key=$notice['source_type'].'-'.($notice['source_type']==='schedule_change'?$item:$notice['source_id']);if(isset($seen[$key]))continue;$seen[$key]=true;$notice['href']='/notifications#notification-'.(int)$notice['id'];$updates[]=$notice;if(count($updates)===4)break;}
        }
        uasort($actions,static fn(array $a,array $b):int=>$a['priority']<=>$b['priority']?:strcmp($a['due_at']??'9999',$b['due_at']??'9999')?:strnatcasecmp($a['title'],$b['title']));
        $history=[];foreach($subjects as $who=>$subject){if($person!==null&&$person!==$who)continue;if(IdentityRolePolicy::hasPerformerHistory($db,$who))$history[$who]=$subject['name'];}
        foreach($productions as &$p){$p['history_subject']=null;foreach($p['subjects'] as $who)if(isset($history[$who])){$p['history_subject']=$who;break;}}unset($p);
        return ['subjects'=>$subjects,'productions'=>$productions,'events'=>$events,'actions'=>array_values($actions),'updates'=>$updates,'commitments'=>$commitments,'forms'=>$forms,'context'=>$context,'person'=>$person,'history_subjects'=>$history];
    }
}
