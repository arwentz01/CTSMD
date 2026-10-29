<?php
declare(strict_types=1);
require_once __DIR__.'/ProductionParticipantEligibility.php';

final class ProductionParticipantFunctionService
{
    public static function save(PDO $db,int $production,int $person,int $actor,string $type,string $title,bool $active):void
    {
        $title=trim($title);if(!in_array($type,['cast','crew','creative'],true)||$title===''||mb_strlen($title)>190)throw new RuntimeException('Choose a function and a title up to 190 characters.');
        $db->beginTransaction();
        try{
            ProductionLifecycleGuard::assertWritable($db,$production,true);ProductionParticipantEligibility::assertManager($db,$actor,$production);
            if($active)ProductionParticipantEligibility::assertEligible($db,$production,$person);
            $q=$db->prepare("SELECT pm.id,p.title,p.season FROM production_memberships pm JOIN productions p ON p.id=pm.production_id WHERE pm.production_id=? AND pm.user_id=? AND pm.status='active' AND pm.audience_type IN ('adult','student') FOR UPDATE");$q->execute([$production,$person]);$member=$q->fetch();if(!$member)throw new RuntimeException('Choose an active Adult or Student participant.');
            $db->prepare("INSERT INTO production_participant_functions(production_id,user_id,function_type,title,assigned_by_user_id,status) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status),assigned_by_user_id=VALUES(assigned_by_user_id),updated_at=NOW()")->execute([$production,$person,$type,$title,$actor,$active?'active':'inactive']);
            $kind=$type.'-'.substr(hash('sha256',$title),0,16);
            if($active)$db->prepare("INSERT INTO theatre_history_credits(user_id,production_id,source_membership_id,credit_kind,production_title,season_label,role_title,participation_track,verification_status,verified_by_user_id,verified_at,participation_started_at) VALUES(?,?,?,?,?,?,?,?,'verified',?,NOW(),NOW()) ON DUPLICATE KEY UPDATE participation_ended_at=NULL,updated_at=NOW()")->execute([$person,$production,(int)$member['id'],$kind,$member['title'],$member['season'],$title,$type,$actor]);
            else $db->prepare('UPDATE theatre_history_credits SET participation_ended_at=COALESCE(participation_ended_at,NOW()) WHERE production_id=? AND user_id=? AND credit_kind=?')->execute([$production,$person,$kind]);
            ProductionParticipantEligibility::audit($db,$actor,$person,'function_updated',['production_id'=>$production,'function'=>$type,'title'=>$title,'active'=>$active]);$db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
}
