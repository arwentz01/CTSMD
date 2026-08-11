<?php

declare(strict_types=1);

require_once __DIR__.'/MailService.php';
require_once __DIR__.'/ProductionContext.php';

final class CastingCommunicationService
{
    public static function publication(PDO $db,int $productionId):array
    {
        $s=$db->prepare("SELECT pcp.*,CONCAT(u.first_name,' ',u.last_name) publisher_name FROM production_cast_publications pcp LEFT JOIN users u ON u.id=pcp.published_by_user_id WHERE pcp.production_id=:production LIMIT 1");
        $s->execute(['production'=>$productionId]);
        return $s->fetch() ?: ['production_id'=>$productionId,'status'=>'draft','headline'=>null,'member_note'=>null,'cast_snapshot_json'=>null,'published_at'=>null,'publisher_name'=>null];
    }

    public static function sendResult(PDO $db,int $productionId,int $actorId,int $recordId,string $baseUrl):int
    {
        $db->beginTransaction();
        try{
            $s=$db->prepare("SELECT cr.*,p.title production_title,CONCAT(u.first_name,' ',u.last_name) student_name,u.email student_email,u.active student_active,u.account_status student_account_status,pm.status membership_status,EXISTS(SELECT 1 FROM auth_user_roles ur JOIN auth_roles ar ON ar.id=ur.role_id AND ar.active=1 AND ar.code='student' WHERE ur.user_id=cr.user_id) student_role_active FROM production_casting_records cr JOIN productions p ON p.id=cr.production_id JOIN users u ON u.id=cr.user_id LEFT JOIN production_memberships pm ON pm.id=cr.production_membership_id WHERE cr.id=:id AND cr.production_id=:production FOR UPDATE");
            $s->execute(['id'=>$recordId,'production'=>$productionId]);$r=$s->fetch();
            if(!$r)throw new RuntimeException('That casting record could not be found.');
            if(!(bool)$r['student_active']||$r['student_account_status']==='disabled'||!(bool)$r['student_role_active'])throw new RuntimeException('That Student identity is no longer available for live casting communication.');
            if(!in_array((string)$r['casting_status'],['offered','cast','not_cast'],true))throw new RuntimeException('Only Offered, Cast, or Not cast decisions can be communicated.');
            if(in_array((string)$r['casting_status'],['offered','cast'],true)&&trim((string)($r['role_title']??''))==='')throw new RuntimeException('Add the offered/cast role before sending the result.');
            if(!empty($r['production_membership_id'])&&in_array((string)$r['casting_status'],['offered','cast'],true)&&$r['membership_status']!=='active')throw new RuntimeException('That finalized production membership is no longer active. Restore the roster membership or update the casting decision before communicating it.');

            $g=$db->prepare("SELECT u.id,CONCAT(u.first_name,' ',u.last_name) name,u.email FROM family_relationships fr JOIN users u ON u.id=fr.guardian_user_id AND u.active=1 AND u.account_status<>'disabled' WHERE fr.student_user_id=:student AND fr.status='active' ORDER BY fr.is_primary DESC,fr.id");
            $g->execute(['student'=>(int)$r['user_id']]);
            $guardians=$g->fetchAll();
            if(!$guardians)throw new RuntimeException('This Student has no available active guardian relationship. Restore guardian context before communicating a casting result.');

            $recipients=[];
            if(filter_var((string)$r['student_email'],FILTER_VALIDATE_EMAIL))$recipients[(string)$r['student_email']]=['user_id'=>(int)$r['user_id'],'name'=>(string)$r['student_name']];
            $guardianRecipients=0;
            foreach($guardians as $guardian){
                if(!filter_var((string)$guardian['email'],FILTER_VALIDATE_EMAIL))continue;
                $recipients[(string)$guardian['email']]=['user_id'=>(int)$guardian['id'],'name'=>(string)$guardian['name']];
                $guardianRecipients++;
            }
            if($guardianRecipients<1)throw new RuntimeException('This Student has guardian context, but no available guardian email address can receive the casting result.');

            $status=(string)$r['casting_status'];$production=(string)$r['production_title'];$student=(string)$r['student_name'];$role=trim((string)($r['role_title']??''));
            $subject=$status==='not_cast'?'Casting update · '.$production:($status==='offered'?'Casting offer · '.$production:'Casting result · '.$production);
            $body=$status==='not_cast'
                ?"CTSMD has an update regarding {$student}'s casting for {$production}.\n\nAt this time, {$student} has not been cast in this production. Thank you for the time, preparation, and courage it takes to audition.\n\nCTSMD Connect"
                :($status==='offered'
                    ?"CTSMD has a casting offer for {$student} in {$production}.\n\nRole: {$role}\n\nPlease sign in to CTSMD Connect for production information and follow-up from the production team.\n\nCTSMD Connect"
                    :"CTSMD has finalized a casting result for {$student} in {$production}.\n\nRole: {$role}\n\nPlease sign in to CTSMD Connect for production information.\n\nCTSMD Connect");
            $queued=0;$guardianQueued=0;$guardianIds=array_map(static fn(array $guardian):int=>(int)$guardian['id'],$guardians);$nonce=date('YmdHis');
            foreach($recipients as $email=>$recipient){$id=MailService::queue($db,$recipient['user_id'],$email,$recipient['name'],'system',$subject,$body,null,'casting-result-'.$recordId.'-'.$status.'-'.$email.'-'.$nonce);if($id>0){$queued++;if(in_array((int)$recipient['user_id'],$guardianIds,true))$guardianQueued++;}}
            if($guardianQueued<1)throw new RuntimeException('Current email preferences prevented every guardian copy from being queued. No casting result was marked as communicated.');
            $db->prepare('UPDATE production_casting_records SET result_communicated_at=CURRENT_TIMESTAMP,result_communicated_by_user_id=:actor WHERE id=:id')->execute(['actor'=>$actorId,'id'=>$recordId]);
            self::audit($db,$actorId,'casting.result_communicated',$recordId,'Queued casting result communication.',['production_id'=>$productionId,'casting_status'=>$status,'recipient_count'=>count($recipients),'queue_count'=>$queued,'guardian_queue_count'=>$guardianQueued]);
            $db->commit();return $queued;
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof RuntimeException)throw $e;throw new RuntimeException('The casting result could not be queued.');}
    }

    public static function savePublication(PDO $db,int $productionId,int $actorId,string $headline,string $note,bool $publish):void
    {
        $headline=trim($headline);$note=trim($note);
        if(mb_strlen($headline)>190||mb_strlen($note)>2000)throw new RuntimeException('The cast publication text is too long.');
        if(!$publish){$existing=self::publication($db,$productionId);if(($existing['status']??'draft')==='published')throw new RuntimeException('The cast is already published. Private casting edits remain private; use Publish cast again when you are ready to release an updated snapshot.');}
        $snapshot=null;$count=self::publishableCount($db,$productionId);
        if($publish){if($count<1)throw new RuntimeException('Finalize at least one available cast member to the roster before publishing the cast.');$snapshot=json_encode(self::currentPublishableCast($db,$productionId),JSON_THROW_ON_ERROR);}
        $status=$publish?'published':'draft';
        $s=$db->prepare("INSERT INTO production_cast_publications (production_id,status,headline,member_note,cast_snapshot_json,published_by_user_id,published_at) VALUES (:production,:status,:headline,:note,:snapshot,:actor,".($publish?'CURRENT_TIMESTAMP':'NULL').") ON DUPLICATE KEY UPDATE status=VALUES(status),headline=VALUES(headline),member_note=VALUES(member_note),cast_snapshot_json=".($publish?'VALUES(cast_snapshot_json)':'cast_snapshot_json').",published_by_user_id=".($publish?'VALUES(published_by_user_id)':'published_by_user_id').",published_at=".($publish?'CURRENT_TIMESTAMP':'NULL').",updated_at=CURRENT_TIMESTAMP");
        $s->execute(['production'=>$productionId,'status'=>$status,'headline'=>$headline!==''?$headline:null,'note'=>$note!==''?$note:null,'snapshot'=>$snapshot,'actor'=>$actorId]);
        self::audit($db,$actorId,$publish?'casting.cast_published':'casting.cast_publication_saved',$productionId,$publish?'Published production cast snapshot to members.':'Saved cast publication draft.',['headline'=>$headline?:null,'publishable_count'=>$count]);
    }

    public static function publishedCastsForUser(PDO $db,int $userId):array
    {
        $s=$db->prepare("SELECT p.id production_id,p.title,p.season,pcp.headline,pcp.member_note,pcp.cast_snapshot_json,pcp.published_at FROM production_cast_publications pcp JOIN productions p ON p.id=pcp.production_id AND p.is_active=1 WHERE pcp.status='published' AND EXISTS (SELECT 1 FROM production_memberships pm WHERE pm.production_id=p.id AND pm.user_id=:user AND pm.status='active') ORDER BY pcp.published_at DESC,p.title");
        $s->execute(['user'=>$userId]);$out=[];
        foreach($s->fetchAll() as $production){
            if(!ProductionContext::isActiveMember($db,$userId,(int)$production['production_id']))continue;
            $raw=$production['cast_snapshot_json']??null;
            $decoded=$raw!==null&&trim((string)$raw)!==''?json_decode((string)$raw,true):null;
            $production['cast']=is_array($decoded)?$decoded:self::currentPublishableCast($db,(int)$production['production_id']);
            unset($production['cast_snapshot_json']);$out[]=$production;
        }
        return $out;
    }

    private static function currentPublishableCast(PDO $db,int $productionId):array{$s=$db->prepare("SELECT CONCAT(u.first_name,' ',u.last_name) name,pm.participation_role role,cr.participation_track FROM production_casting_records cr JOIN users u ON u.id=cr.user_id AND u.active=1 AND u.account_status<>'disabled' JOIN production_memberships pm ON pm.id=cr.production_membership_id AND pm.status='active' WHERE cr.production_id=:production AND cr.casting_status='cast' AND cr.rostered_at IS NOT NULL ORDER BY COALESCE(cr.participation_track,''),COALESCE(pm.participation_role,''),u.last_name,u.first_name");$s->execute(['production'=>$productionId]);return$s->fetchAll();}
    private static function publishableCount(PDO $db,int $productionId):int{$s=$db->prepare("SELECT COUNT(*) FROM production_casting_records cr JOIN users u ON u.id=cr.user_id AND u.active=1 AND u.account_status<>'disabled' JOIN production_memberships pm ON pm.id=cr.production_membership_id AND pm.status='active' WHERE cr.production_id=:production AND cr.casting_status='cast' AND cr.rostered_at IS NOT NULL");$s->execute(['production'=>$productionId]);return(int)$s->fetchColumn();}
    private static function audit(PDO $db,int $actor,string $event,int $id,string $summary,array $meta):void{$s=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,:event,'casting',:id,:summary,:meta)");$s->execute(['actor'=>$actor,'event'=>$event,'id'=>$id,'summary'=>$summary,'meta'=>json_encode($meta,JSON_THROW_ON_ERROR)]);}
}
