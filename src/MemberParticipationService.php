<?php
declare(strict_types=1);
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/PerformerQuery.php';
require_once __DIR__.'/ProductionContext.php';

/** Personal read model. Administrative visibility and credits never become access grants. */
final class MemberParticipationService
{
    public static function subjects(PDO $db,int $viewer):array
    {
        $q=$db->prepare("SELECT id,CONCAT(first_name,' ',last_name) name,initials,display_role role,account_status,organization_membership_status FROM users WHERE id=? AND active=1 AND account_status<>'disabled'");$q->execute([$viewer]);$self=$q->fetch();if(!$self)return [];
        $self['relationship']='Self';$self['roles']=Auth::roles($db,$viewer);$self['permissions']=Auth::permissions($db,$viewer);$subjects=[$viewer=>$self];
        $q=$db->prepare("SELECT u.id,CONCAT(u.first_name,' ',u.last_name) name,u.initials,u.display_role role,u.account_status,u.organization_membership_status,fr.relationship_type relationship FROM family_relationships fr JOIN users u ON u.id=fr.student_user_id AND u.active=1 AND u.account_status<>'disabled' WHERE fr.guardian_user_id=? AND fr.status='active' AND fr.relationship_type IN ('parent','guardian') AND ".PerformerQuery::role('u','student')." ORDER BY u.last_name,u.first_name,u.id");$q->execute([$viewer]);
        foreach($q->fetchAll() as $child){$id=(int)$child['id'];$child['roles']=Auth::roles($db,$id);$child['permissions']=Auth::permissions($db,$id);$subjects[$id]=$child;}return $subjects;
    }
    public static function directProductionIds(PDO $db,int $person):array
    {
        $q=$db->prepare("SELECT DISTINCT pm.production_id FROM production_memberships pm JOIN users u ON u.id=pm.user_id AND u.active=1 AND u.account_status<>'disabled' JOIN productions p ON p.id=pm.production_id AND p.is_active=1 AND p.lifecycle_stage IN ('upcoming','planning','in_production','performing') WHERE pm.user_id=? AND pm.status='active' AND pm.audience_type IN ('student','adult','staff') AND ".PerformerQuery::membership('pm','u')." AND (pm.audience_type<>'staff' OR EXISTS(SELECT 1 FROM auth_user_roles ur JOIN auth_roles r ON r.id=ur.role_id AND r.active=1 AND r.code IN ('administrator','production_staff') WHERE ur.user_id=u.id))");$q->execute([$person]);return array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
    }
    public static function productions(PDO $db,array $viewer,array $subjects,?int $personFilter=null):array
    {
        if(!$subjects)return [];$viewerId=(int)$viewer['id'];$people=array_map('intval',array_keys($subjects));$ph=implode(',',array_fill(0,count($people),'?'));$out=[];
        $add=static function(array $row,string $context,bool $current,bool $access=false)use(&$out,$personFilter):void{
            if($personFilter!==null&&(int)$row['user_id']!==$personFilter)return;
            $id=(int)$row['production_id'];$out[$id]??=['id'=>$id,'title'=>$row['production_title'],'season'=>$row['season'],'lifecycle_stage'=>$row['lifecycle_stage'],'contexts'=>[],'subjects'=>[],'active_context'=>false,'can_schedule'=>false,'artwork'=>false];
            $out[$id]['contexts'][$context]=true;$out[$id]['subjects'][(int)$row['user_id']]=true;$out[$id]['active_context']=$out[$id]['active_context']||$current;$out[$id]['can_schedule']=$out[$id]['can_schedule']||$access;
        };
        $q=$db->prepare("SELECT pm.user_id,pm.audience_type,pm.participation_role,pm.status,(".PerformerQuery::membership('pm','u')." AND (pm.audience_type<>'staff' OR EXISTS(SELECT 1 FROM auth_user_roles ur JOIN auth_roles r ON r.id=ur.role_id AND r.active=1 AND r.code IN ('administrator','production_staff') WHERE ur.user_id=u.id))) role_valid,p.id production_id,p.title production_title,p.season,p.lifecycle_stage,p.is_active FROM production_memberships pm JOIN productions p ON p.id=pm.production_id JOIN users u ON u.id=pm.user_id WHERE pm.user_id IN ($ph) AND pm.audience_type IN ('student','adult','staff') AND (pm.user_id=? OR pm.audience_type='student') ORDER BY p.title,pm.id");$q->execute([...$people,$viewerId]);
        foreach($q->fetchAll() as $row){$person=(int)$row['user_id'];$current=$row['status']==='active'&&(bool)$row['role_valid']&&in_array($row['lifecycle_stage'],['upcoming','planning','in_production','performing'],true);$name=$subjects[$person]['name'];$label=$person===$viewerId?'You':$name;$context=$label.' · '.match($row['audience_type']){'student'=>'Student cast','adult'=>'Adult Performer',default=>'Production Staff'};if($row['participation_role'])$context.=': '.$row['participation_role'];if(!$current)$context.=' (previous participation)';$add($row,$context,$current,$current&&(bool)$row['is_active']);}
        $q=$db->prepare("SELECT f.user_id,f.title function_title,f.status,p.id production_id,p.title production_title,p.season,p.lifecycle_stage,p.is_active FROM production_participant_functions f JOIN productions p ON p.id=f.production_id WHERE f.user_id=? AND f.function_type<>'cast' ORDER BY f.display_order,f.id");$q->execute([$viewerId]);foreach($q->fetchAll() as $row)$add($row,'You · '.$row['function_title'].($row['status']==='inactive'?' (previous credit)':''),$row['status']==='active'&&$row['is_active']&&!in_array($row['lifecycle_stage'],['completed','archived'],true));
        $q=$db->prepare("SELECT DISTINCT c.user_id,p.id production_id,p.title production_title,p.season,p.lifecycle_stage,p.is_active FROM theatre_history_credits c JOIN productions p ON p.id=c.production_id WHERE c.user_id IN ($ph) AND c.verification_status='verified'");$q->execute($people);foreach($q->fetchAll() as $row){$id=(int)$row['production_id'];if(!isset($out[$id]['subjects'][(int)$row['user_id']]))$add($row,((int)$row['user_id']===$viewerId?'Your':$subjects[(int)$row['user_id']]['name'].'’s').' theatre credits',false);}
        $q=$db->prepare("SELECT DISTINCT s.user_id,s.status signup_status,p.id production_id,p.title production_title,p.season,p.lifecycle_stage,p.is_active FROM volunteer_shift_signups s JOIN volunteer_shifts v ON v.id=s.shift_id JOIN productions p ON p.id=v.production_id WHERE s.user_id=? AND s.status IN ('signed_up','checked_in','waitlisted','completed')");$q->execute([$viewerId]);foreach($q->fetchAll() as $row)$add($row,'You · Volunteer',in_array($row['signup_status'],['signed_up','checked_in','waitlisted'],true)&&$row['is_active']&&!in_array($row['lifecycle_stage'],['completed','archived'],true));
        foreach($out as &$p){$p['contexts']=array_keys($p['contexts']);$p['subjects']=array_keys($p['subjects']);$p['bucket']=$p['active_context']?(in_array($p['lifecycle_stage'],['upcoming','planning'],true)?'Upcoming':'Current'):'Past';
            if($p['can_schedule']){$q=$db->prepare("SELECT 1 FROM production_artwork WHERE production_id=? AND role IN ('banner','poster') LIMIT 1");$q->execute([$p['id']]);$p['artwork']=(bool)$q->fetchColumn();}
        }unset($p);uasort($out,static fn(array $a,array $b):int=>array_search($a['bucket'],['Current','Upcoming','Past'],true)<=>array_search($b['bucket'],['Current','Upcoming','Past'],true)?:strnatcasecmp($a['title'],$b['title']));return array_values($out);
    }
}
