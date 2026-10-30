<?php
declare(strict_types=1);
require_once __DIR__.'/ProductionParticipantFunctionService.php';
require_once __DIR__.'/PerformerQuery.php';

/** Current responsibilities, keyed by the one existing account ID. No write-side sync. */
final class ProductionRosterService
{
    public static function people(PDO $db,int $production):array
    {
        $people=[];
        $add=static function(array $row,string $filter,string $description)use(&$people):void{
            $id=(int)$row['user_id'];$people[$id]??=['id'=>$id,'name'=>$row['name'],'initials'=>$row['initials'],'filters'=>[],'involvement'=>[],'roles'=>[],'permissions'=>[],'children'=>[],'team'=>[],'is_student'=>false];
            $people[$id]['filters'][$filter]=true;$people[$id]['involvement'][$description]=true;
        };
        $q=$db->prepare("SELECT pm.user_id,u.initials,CONCAT(u.first_name,' ',u.last_name) name,pm.audience_type,COALESCE(NULLIF(cr.role_title,''),pm.participation_role,'Cast') title FROM production_memberships pm JOIN users u ON u.id=pm.user_id AND u.active=1 AND u.account_status<>'disabled' LEFT JOIN production_casting_records cr ON cr.production_id=pm.production_id AND cr.user_id=pm.user_id WHERE pm.production_id=? AND pm.status='active' AND pm.audience_type IN ('student','adult') AND ".PerformerQuery::membership('pm','u')." AND (cr.id IS NULL OR cr.casting_status NOT IN ('withdrawn','not_cast'))");$q->execute([$production]);$cast=$q->fetchAll();
        foreach($cast as $r){$add($r,'cast','Cast: '.$r['title']);if($r['audience_type']==='student')$people[(int)$r['user_id']]['is_student']=true;}
        foreach(ProductionParticipantFunctionService::assignments($db,$production) as $r){if(!(int)$r['account_active']||$r['account_status']==='disabled')continue;$r['user_id']=(int)$r['user_id'];$r['initials']=mb_strtoupper(mb_substr($r['name'],0,1));$add($r,'team',ProductionParticipantFunctionService::CATEGORIES[$r['function_type']].': '.$r['title']);$people[$r['user_id']]['team'][]=$r;}
        $childIds=array_map('intval',array_column(array_filter($cast,static fn(array $r):bool=>$r['audience_type']==='student'),'user_id'));
        if($childIds){$q=$db->prepare("SELECT fr.guardian_user_id user_id,fr.student_user_id,CONCAT(child.first_name,' ',child.last_name) child_name,fr.relationship_type,u.initials,CONCAT(u.first_name,' ',u.last_name) name FROM family_relationships fr JOIN users u ON u.id=fr.guardian_user_id AND u.active=1 AND u.account_status<>'disabled' JOIN users child ON child.id=fr.student_user_id WHERE fr.status='active' AND fr.relationship_type IN ('parent','guardian') AND fr.student_user_id IN (".implode(',',array_fill(0,count($childIds),'?')).")");$q->execute($childIds);foreach($q->fetchAll() as $r){$add($r,'guardians',ucfirst($r['relationship_type']).' of '.$r['child_name']);$people[(int)$r['user_id']]['children'][(int)$r['student_user_id']]=$r['child_name'];}}
        $q=$db->prepare("SELECT pm.user_id,u.initials,CONCAT(u.first_name,' ',u.last_name) name FROM production_memberships pm JOIN users u ON u.id=pm.user_id AND u.active=1 AND u.account_status<>'disabled' WHERE pm.production_id=? AND pm.status='active' AND pm.audience_type='staff' AND EXISTS(SELECT 1 FROM auth_user_roles ur JOIN auth_roles r ON r.id=ur.role_id AND r.active=1 AND r.code IN ('administrator','production_staff') WHERE ur.user_id=u.id)");$q->execute([$production]);foreach($q->fetchAll() as $r)$add($r,'staff','Production staff access');
        if(!$people)return [];
        $ids=array_keys($people);$ph=implode(',',array_fill(0,count($ids),'?'));
        $q=$db->prepare("SELECT ur.user_id,r.name,p.code permission_code FROM auth_user_roles ur JOIN auth_roles r ON r.id=ur.role_id AND r.active=1 LEFT JOIN auth_role_permissions rp ON rp.role_id=r.id LEFT JOIN auth_permissions p ON p.id=rp.permission_id WHERE ur.user_id IN ($ph) ORDER BY r.name,p.code");$q->execute($ids);
        foreach($q->fetchAll() as $r){$people[(int)$r['user_id']]['roles'][$r['name']]=true;if($r['permission_code'])$people[(int)$r['user_id']]['permissions'][$r['permission_code']]=true;}
        foreach($people as &$person){$person['roles']=array_keys($person['roles']);$person['permissions']=array_keys($person['permissions']);$person['involvement']=array_keys($person['involvement']);}unset($person);
        uasort($people,static fn(array $a,array $b):int=>strnatcasecmp($a['name'],$b['name'])?:($a['id']<=>$b['id']));return array_values($people);
    }
    public static function counts(array $people):array
    {
        $counts=['all'=>count($people),'cast'=>0,'team'=>0,'guardians'=>0,'staff'=>0,'assignments'=>0];foreach($people as $person){foreach(['cast','team','guardians','staff'] as $key)if(isset($person['filters'][$key]))++$counts[$key];$counts['assignments']+=count($person['team']);}return $counts;
    }
}
