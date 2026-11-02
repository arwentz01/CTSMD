<?php

declare(strict_types=1);

final class VolunteerAutomationService
{
    public static function syncShiftHours(PDO $db,int $signupId,int $actorUserId):void
    {
        $stmt=$db->prepare("SELECT s.id,s.user_id,s.status,vs.id shift_id,vs.production_id,vs.starts_at,vs.ends_at,vs.title FROM volunteer_shift_signups s JOIN volunteer_shifts vs ON vs.id=s.shift_id WHERE s.id=:id LIMIT 1");
        $stmt->execute(['id'=>$signupId]);$row=$stmt->fetch();if(!$row)return;
        if($row['status']!=='completed'){
            $db->prepare("UPDATE volunteer_hour_entries SET status='void',verified_by_user_id=:actor,updated_at=CURRENT_TIMESTAMP WHERE shift_id=:shift AND user_id=:user")->execute(['actor'=>$actorUserId,'shift'=>(int)$row['shift_id'],'user'=>(int)$row['user_id']]);
            return;
        }
        $start=new DateTimeImmutable((string)$row['starts_at']);$end=new DateTimeImmutable((string)$row['ends_at']);$minutes=max(1,(int)round(($end->getTimestamp()-$start->getTimestamp())/60));
        $sql="INSERT INTO volunteer_hour_entries (user_id,production_id,shift_id,minutes,source_type,status,note,verified_by_user_id,served_at) VALUES (:user,:production,:shift,:minutes,'shift','verified',:note,:actor,:served_at) ON DUPLICATE KEY UPDATE minutes=VALUES(minutes),production_id=VALUES(production_id),status='verified',note=VALUES(note),verified_by_user_id=VALUES(verified_by_user_id),served_at=VALUES(served_at),updated_at=CURRENT_TIMESTAMP";
        $db->prepare($sql)->execute(['user'=>(int)$row['user_id'],'production'=>$row['production_id']!==null?(int)$row['production_id']:null,'shift'=>(int)$row['shift_id'],'minutes'=>$minutes,'note'=>'Verified from completed shift: '.$row['title'],'actor'=>$actorUserId,'served_at'=>(string)$row['ends_at']]);
    }

    public static function completeTraining(PDO $db,int $moduleId,int $userId,int $actorUserId,?string $note=null):void
    {
        $stmt=$db->prepare("SELECT id,requirement_id,validity_days,active FROM volunteer_training_modules WHERE id=:id LIMIT 1");$stmt->execute(['id'=>$moduleId]);$module=$stmt->fetch();if(!$module||(int)$module['active']!==1)throw new RuntimeException('That training module is unavailable.');
        $db->prepare("INSERT INTO volunteer_training_completions (module_id,user_id,status,completed_at,verified_by_user_id,note) VALUES (:module,:user,'completed',CURRENT_TIMESTAMP,:actor,:note) ON DUPLICATE KEY UPDATE status='completed',completed_at=CURRENT_TIMESTAMP,verified_by_user_id=VALUES(verified_by_user_id),note=VALUES(note),updated_at=CURRENT_TIMESTAMP")->execute(['module'=>$moduleId,'user'=>$userId,'actor'=>$actorUserId,'note'=>$note]);
        if($module['requirement_id']!==null)self::approveCredential($db,$userId,(int)$module['requirement_id'],$actorUserId,$module['validity_days']!==null?(int)$module['validity_days']:null);
    }

    public static function applyApprovedForm(PDO $db,int $submissionId,int $actorUserId):array
    {
        $stmt=$db->prepare("SELECT fs.form_id,COALESCE(fs.submitted_for_user_id,fs.submitted_by_user_id) credential_user_id FROM form_submissions fs WHERE fs.id=:id LIMIT 1");$stmt->execute(['id'=>$submissionId]);$submission=$stmt->fetch();if(!$submission)return [];
        $map=$db->prepare("SELECT requirement_id,validity_days FROM form_requirement_mappings WHERE form_id=:form AND active=1");$map->execute(['form'=>(int)$submission['form_id']]);$applied=[];
        foreach($map->fetchAll() as $row){self::approveCredential($db,(int)$submission['credential_user_id'],(int)$row['requirement_id'],$actorUserId,$row['validity_days']!==null?(int)$row['validity_days']:null);$applied[]=(int)$row['requirement_id'];}
        return $applied;
    }

    public static function approveCredential(PDO $db,int $userId,int $requirementId,int $actorUserId,?int $validityDays=null):void
    {
        $expiresAt=$validityDays!==null?(new DateTimeImmutable('now'))->modify('+'.$validityDays.' days')->format('Y-m-d H:i:s'):null;
        $sql="INSERT INTO volunteer_credentials (user_id,requirement_id,status,completed_at,expires_at,verified_by_user_id) VALUES (:user,:requirement,'approved',CURRENT_TIMESTAMP,:expires,:actor) ON DUPLICATE KEY UPDATE status='approved',completed_at=CURRENT_TIMESTAMP,expires_at=VALUES(expires_at),verified_by_user_id=VALUES(verified_by_user_id)";
        $db->prepare($sql)->execute(['user'=>$userId,'requirement'=>$requirementId,'expires'=>$expiresAt,'actor'=>$actorUserId]);
    }

    public static function totalMinutes(PDO $db,int $userId):int
    {
        $stmt=$db->prepare("SELECT COALESCE(SUM(minutes),0) FROM volunteer_hour_entries WHERE user_id=:user AND status='verified'");$stmt->execute(['user'=>$userId]);return (int)$stmt->fetchColumn();
    }
}
