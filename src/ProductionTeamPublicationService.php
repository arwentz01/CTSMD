<?php
declare(strict_types=1);
require_once __DIR__.'/ProductionParticipantFunctionService.php';
require_once __DIR__.'/PerformerQuery.php';

/** Name/title-only editorial snapshots. Eligibility is never publication. */
final class ProductionTeamPublicationService
{
    public static function candidates(PDO $db,int $production,int $playbill):array
    {
        $q=$db->prepare("SELECT f.id assignment_id,f.revision,f.user_id,COALESCE(p.display_name,CONCAT(u.first_name,' ',u.last_name)) name,f.title participation_role,f.function_type,".PerformerQuery::role('u','student')." requires_student_approval FROM production_participant_functions f JOIN users u ON u.id=f.user_id AND u.active=1 AND u.account_status<>'disabled' LEFT JOIN playbill_cast_profiles p ON p.user_id=u.id AND p.playbill_id=? AND p.visible=1 AND p.approval_status='approved' WHERE f.production_id=? AND f.status='active' AND f.function_type<>'cast' AND f.playbill_eligible=1 AND (NOT ".PerformerQuery::role('u','student')." OR p.id IS NOT NULL) ORDER BY f.display_order,u.last_name,u.first_name,f.id");$q->execute([$playbill,$production]);return $q->fetchAll();
    }
    public static function fingerprint(array $rows):string{return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));}
    public static function import(PDO $db,int $production,int $playbill,int $actor,string $expected):void
    {
        $db->beginTransaction();try{
            ProductionLifecycleGuard::assertWritable($db,$production,true);
            $q=$db->prepare('SELECT id,active,account_status FROM users WHERE id=? FOR UPDATE');$q->execute([$actor]);$user=$q->fetch();Auth::clearRequestCache($actor);
            if(!$user||!(int)$user['active']||$user['account_status']!=='active'||!in_array('playbill.manage',Auth::permissions($db,$actor),true))throw new RuntimeException('Current Playbill management permission is required.');
            if(!in_array('administrator',Auth::roles($db,$actor),true)){
                $q=$db->prepare("SELECT id FROM production_memberships WHERE production_id=? AND user_id=? AND audience_type='staff' AND status='active' FOR UPDATE");$q->execute([$production,$actor]);if(!$q->fetchColumn())throw new RuntimeException('Current production Staff access is required.');
            }
            $q=$db->prepare('SELECT status,editorial_status FROM playbills WHERE id=? AND production_id=? FOR UPDATE');$q->execute([$playbill,$production]);$pb=$q->fetch();
            if(!$pb||in_array($pb['status'],['current','archived'],true)||in_array($pb['editorial_status'],['published','archived'],true))throw new RuntimeException('Team credits can only be imported into an unpublished Playbill. Preserve published editions and review before republishing.');
            $rows=self::candidates($db,$production,$playbill);
            if(!hash_equals(self::fingerprint($rows),$expected))throw new RuntimeException('Team credits changed after your preview. Reload Playbill and review the names again.');
            $snapshot=array_map(static fn(array $r):array=>['user_id'=>(int)$r['user_id'],'requires_student_approval'=>(bool)$r['requires_student_approval'],'name'=>$r['name'],'participation_role'=>$r['participation_role']],$rows);
            $db->prepare("UPDATE playbill_sections SET active=0 WHERE playbill_id=? AND section_type='production_team_snapshot'")->execute([$playbill]);
            $db->prepare("INSERT INTO playbill_sections(playbill_id,section_type,heading,body,sort_order,active) VALUES(?,'production_team_snapshot','Production Team',?,15,1)")->execute([$playbill,json_encode($snapshot,JSON_THROW_ON_ERROR)]);
            $db->prepare("UPDATE playbills SET editorial_status='review' WHERE id=?")->execute([$playbill]);
            ProductionParticipantEligibility::audit($db,$actor,$actor,'team_credits_reviewed',['production_id'=>$production,'playbill_id'=>$playbill,'credits'=>$snapshot]);$db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
    public static function snapshot(PDO $db,int $playbill,bool $withIdentity=false):array
    {
        $q=$db->prepare("SELECT body FROM playbill_sections WHERE playbill_id=? AND section_type='production_team_snapshot' AND active=1 ORDER BY id DESC LIMIT 1");$q->execute([$playbill]);$raw=$q->fetchColumn();if($raw===false)return [];
        $rows=json_decode((string)$raw,true,flags:JSON_THROW_ON_ERROR);$visible=[];
        foreach($rows as $row){
            if(!empty($row['requires_student_approval'])){
                $q=$db->prepare("SELECT id FROM playbill_cast_profiles WHERE playbill_id=? AND user_id=? AND visible=1 AND approval_status='approved'");$q->execute([$playbill,(int)$row['user_id']]);if(!$q->fetchColumn())continue;
            }
            $credit=['name'=>(string)$row['name'],'participation_role'=>(string)$row['participation_role']];if($withIdentity)$credit['user_id']=(int)($row['user_id']??0);$visible[]=$credit;
        }return $visible;
    }
}
