<?php

declare(strict_types=1);

require_once __DIR__.'/StudentProfileService.php';
require_once __DIR__.'/TheatreHistoryService.php';

final class ActingResumeService
{
    public static function build(PDO $db,array $viewer,int $studentId):array
    {
        if(!StudentProfileService::canEdit($db,$viewer,$studentId))throw new RuntimeException('That acting résumé is not available to this account.');$profile=StudentProfileService::profile($db,$studentId);if(!$profile)throw new RuntimeException('Student profile not found.');$verified=TheatreHistoryService::creditsForSubject($db,$studentId);$external=self::externalCredits($db,$studentId);return['profile'=>$profile,'verified'=>$verified,'external'=>$external];
    }

    public static function externalCredits(PDO $db,int $studentId):array{$s=$db->prepare("SELECT * FROM external_theatre_credits WHERE user_id=:user AND status='active' ORDER BY COALESCE(season_label,'') DESC,production_title,id DESC");$s->execute(['user'=>$studentId]);return$s->fetchAll();}

    public static function saveExternal(PDO $db,array $viewer,int $studentId,array $input):int
    {
        if(!StudentProfileService::canEdit($db,$viewer,$studentId))throw new RuntimeException('That Student record is not available to this account.');$title=trim((string)($input['production_title']??''));$role=trim((string)($input['role_title']??''));$org=trim((string)($input['organization_name']??''));$season=trim((string)($input['season_label']??''));$type=(string)($input['credit_type']??'performance');$notes=trim((string)($input['notes']??''));if($title===''||mb_strlen($title)>190||$org===''||mb_strlen($org)>190)throw new RuntimeException('Enter the production/training title and organization.');if(mb_strlen($role)>190||mb_strlen($season)>100||mb_strlen($notes)>500)throw new RuntimeException('One or more external credit fields are too long.');if(!in_array($type,['performance','crew','training'],true))throw new RuntimeException('Choose a valid credit type.');$s=$db->prepare("INSERT INTO external_theatre_credits (user_id,production_title,role_title,organization_name,season_label,credit_type,notes,status,created_by_user_id) VALUES (:user,:title,:role,:org,:season,:type,:notes,'active',:actor)");$s->execute(['user'=>$studentId,'title'=>$title,'role'=>$role!==''?$role:null,'org'=>$org,'season'=>$season!==''?$season:null,'type'=>$type,'notes'=>$notes!==''?$notes:null,'actor'=>(int)$viewer['id']]);$id=(int)$db->lastInsertId();self::audit($db,(int)$viewer['id'],'theatre.external_credit_added',$id,$studentId,'Added self-reported external theatre credit.');return$id;
    }

    public static function archiveExternal(PDO $db,array $viewer,int $studentId,int $creditId):void
    {
        if(!StudentProfileService::canEdit($db,$viewer,$studentId))throw new RuntimeException('That Student record is not available to this account.');$s=$db->prepare("UPDATE external_theatre_credits SET status='archived',updated_at=CURRENT_TIMESTAMP WHERE id=:id AND user_id=:user AND status='active'");$s->execute(['id'=>$creditId,'user'=>$studentId]);if($s->rowCount()<1)throw new RuntimeException('That external credit could not be found.');self::audit($db,(int)$viewer['id'],'theatre.external_credit_archived',$creditId,$studentId,'Archived self-reported external theatre credit.');
    }

    private static function audit(PDO $db,int $actor,string $event,int $id,int $student,string $summary):void{$s=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,:event,'external_theatre_credit',:id,:summary,:meta)");$s->execute(['actor'=>$actor,'event'=>$event,'id'=>$id,'summary'=>$summary,'meta'=>json_encode(['student_user_id'=>$student],JSON_THROW_ON_ERROR)]);}
}
