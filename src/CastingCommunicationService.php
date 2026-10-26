<?php

declare(strict_types=1);

require_once __DIR__.'/ProductionLifecycleGuard.php';

require_once __DIR__.'/MailService.php';
require_once __DIR__.'/ProductionContext.php';
require_once __DIR__.'/PushService.php';
require_once __DIR__.'/ManualParticipantService.php';

require_once __DIR__.'/CastingRoleService.php';
require_once __DIR__.'/CastingOfferService.php';

final class CastingCommunicationService
{
    public static function publication(PDO $db,int $productionId,string $type='cast'):array
    {
        self::assertPublicationType($type);
        $s=$db->prepare("SELECT pcp.*,CONCAT(u.first_name,' ',u.last_name) publisher_name FROM production_cast_publications pcp LEFT JOIN users u ON u.id=pcp.published_by_user_id WHERE pcp.production_id=:production AND pcp.publication_type=:type LIMIT 1");
        $s->execute(['production'=>$productionId,'type'=>$type]);
        return $s->fetch() ?: ['production_id'=>$productionId,'publication_type'=>$type,'status'=>'draft','headline'=>null,'member_note'=>null,'cast_snapshot_json'=>null,'published_at'=>null,'publisher_name'=>null];
    }

    public static function sendResult(PDO $db,int $productionId,int $actorId,int $recordId,string $baseUrl):int
    {
        ProductionLifecycleGuard::assertWritable($db,$productionId);
        $db->beginTransaction();
        try{
            $s=$db->prepare("SELECT cr.*,p.title production_title,CONCAT(u.first_name,' ',u.last_name) student_name,u.email student_email,u.active student_active,u.account_status student_account_status,pm.status membership_status,EXISTS(SELECT 1 FROM auth_user_roles ur JOIN auth_roles ar ON ar.id=ur.role_id AND ar.active=1 AND ar.code='student' WHERE ur.user_id=cr.user_id) student_role_active FROM production_casting_records cr JOIN productions p ON p.id=cr.production_id JOIN users u ON u.id=cr.user_id LEFT JOIN production_memberships pm ON pm.id=cr.production_membership_id WHERE cr.id=:id AND cr.production_id=:production FOR UPDATE");
            $s->execute(['id'=>$recordId,'production'=>$productionId]);$r=$s->fetch();
            if(!$r)throw new RuntimeException('That casting record could not be found.');
            if(!(bool)$r['student_active']||$r['student_account_status']==='disabled'||!(bool)$r['student_role_active'])throw new RuntimeException('That Student identity is no longer available for live casting communication.');
            if(!in_array((string)$r['casting_status'],['offered','cast','not_cast'],true))throw new RuntimeException('Only Offered, Cast, or Not cast decisions can be communicated.');
            // Multi-select roles are authoritative; role_title is only a legacy display
            // projection and may be empty for records written by older editors.
            $role=trim((string)($r['role_title']??''));
            if(in_array((string)$r['casting_status'],['offered','cast'],true)){
                $assignedRoles=CastingRoleService::assigned($db,$productionId,$recordId);
                if($assignedRoles){
                    $role=implode(' / ',array_column($assignedRoles,'title'));
                }
                if($role==='')throw new RuntimeException('Add at least one character/role under Casting Decisions, save it, then send the result.');
            }
            if(!empty($r['production_membership_id'])&&in_array((string)$r['casting_status'],['offered','cast'],true)&&$r['membership_status']!=='active')throw new RuntimeException('That finalized production membership is no longer active. Restore the roster membership or update the casting decision before communicating it.');

            $g=$db->prepare("SELECT u.id,CONCAT(u.first_name,' ',u.last_name) name,u.email FROM family_relationships fr JOIN users u ON u.id=fr.guardian_user_id AND u.active=1 AND u.account_status<>'disabled' WHERE fr.student_user_id=:student AND fr.status='active' AND fr.relationship_type IN ('parent','guardian') ORDER BY fr.is_primary DESC,fr.id");
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

            $status=(string)$r['casting_status'];$production=(string)$r['production_title'];$student=(string)$r['student_name'];
            $subject=$status==='not_cast'?'Casting update · '.$production:($status==='offered'?'Casting offer · '.$production:'Casting result · '.$production);
            $body=$status==='not_cast'
                ?"CTSMD has an update regarding {$student}'s casting for {$production}.\n\nAt this time, {$student} has not been cast in this production. Thank you for the time, preparation, and courage it takes to audition.\n\nCTSMD Connect"
                :($status==='offered'
                    ?"CTSMD has a casting offer for {$student} in {$production}.\n\nRole: {$role}\n\nPlease sign in to CTSMD Connect for production information and follow-up from the production team.\n\nCTSMD Connect"
                    :"CTSMD has finalized a casting result for {$student} in {$production}.\n\nRole: {$role}\n\nPlease sign in to CTSMD Connect for production information.\n\nCTSMD Connect");
            $queued=0;$guardianQueued=0;$guardianIds=array_map(static fn(array $guardian):int=>(int)$guardian['id'],$guardians);$nonce=date('YmdHis');
            foreach($recipients as $email=>$recipient){$id=MailService::queue($db,$recipient['user_id'],$email,$recipient['name'],'system',$subject,$body,null,'casting-result-'.$recordId.'-'.$status.'-'.$email.'-'.$nonce);if($id>0){$queued++;if(in_array((int)$recipient['user_id'],$guardianIds,true))$guardianQueued++;}}
            if($guardianQueued<1)throw new RuntimeException('Current email preferences prevented every guardian copy from being queued. No casting result was marked as communicated.');
            // An Offered result is one staff action: send the email and create the
            // actionable student/guardian offer in the same database transaction.
            // Reuse any pending offer rather than notifying the family twice.
            if($status==='offered')CastingOfferService::issue($db,$productionId,$recordId,$actorId);
            $db->prepare('UPDATE production_casting_records SET result_communicated_at=CURRENT_TIMESTAMP,result_communicated_by_user_id=:actor WHERE id=:id')->execute(['actor'=>$actorId,'id'=>$recordId]);
            self::audit($db,$actorId,'casting.result_communicated',$recordId,'Queued casting result communication.',['production_id'=>$productionId,'casting_status'=>$status,'recipient_count'=>count($recipients),'queue_count'=>$queued,'guardian_queue_count'=>$guardianQueued]);
            $db->commit();return $queued;
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof RuntimeException)throw $e;throw new RuntimeException('The casting result could not be queued.');}
    }

    public static function savePublication(PDO $db,int $productionId,int $actorId,string $type,string $headline,string $note,bool $publish):void
    {
        ProductionLifecycleGuard::assertWritable($db,$productionId);
        self::assertPublicationType($type);
        $headline=trim($headline);$note=trim($note);
        $label=$type==='callback'?'callback':'cast';
        if(mb_strlen($headline)>190||mb_strlen($note)>2000)throw new RuntimeException('The '.$label.' publication text is too long.');
        if(!$publish){$existing=self::publication($db,$productionId,$type);if(($existing['status']??'draft')==='published')throw new RuntimeException('The '.$label.' list is already published. Private casting edits remain private; publish again when you are ready to release an updated snapshot.');}
        $snapshot=null;$count=self::publishableCount($db,$productionId,$type);
        if($publish){
            if($count<1)throw new RuntimeException($type==='callback'?'Mark at least one available student Callback before publishing callbacks.':'Finalize at least one available cast member to the roster before publishing the cast.');
            $snapshot=json_encode($type==='callback'?self::currentPublishableCallbacks($db,$productionId):self::currentPublishableCast($db,$productionId),JSON_THROW_ON_ERROR);
        }
        $status=$publish?'published':'draft';
        $s=$db->prepare("INSERT INTO production_cast_publications (production_id,publication_type,status,headline,member_note,cast_snapshot_json,published_by_user_id,published_at) VALUES (:production,:type,:status,:headline,:note,:snapshot,:actor,".($publish?'CURRENT_TIMESTAMP':'NULL').") ON DUPLICATE KEY UPDATE status=VALUES(status),headline=VALUES(headline),member_note=VALUES(member_note),cast_snapshot_json=".($publish?'VALUES(cast_snapshot_json)':'cast_snapshot_json').",published_by_user_id=".($publish?'VALUES(published_by_user_id)':'published_by_user_id').",published_at=".($publish?'CURRENT_TIMESTAMP':'NULL').",updated_at=CURRENT_TIMESTAMP");
        $s->execute(['production'=>$productionId,'type'=>$type,'status'=>$status,'headline'=>$headline!==''?$headline:null,'note'=>$note!==''?$note:null,'snapshot'=>$snapshot,'actor'=>$actorId]);
        self::audit($db,$actorId,$publish?'casting.'.$type.'_published':'casting.'.$type.'_publication_saved',$productionId,$publish?'Published production '.$label.' snapshot to members.':'Saved '.$label.' publication draft.',['headline'=>$headline?:null,'publishable_count'=>$count,'publication_type'=>$type]);
        if($publish && ProductionContext::isLiveProductionStage($db,$productionId)){
            // The notification contains no child names or roles. Members view the
            // published snapshot only after authenticating and passing production access.
            $audience=$db->prepare("SELECT DISTINCT pm.user_id FROM production_memberships pm JOIN users u ON u.id=pm.user_id AND u.active=1 AND u.account_status='active'
                WHERE pm.production_id=:production AND pm.status='active' AND (
                  pm.audience_type<>'guardian' OR EXISTS (
                    SELECT 1 FROM family_relationships fr
                    JOIN production_memberships spm ON spm.user_id=fr.student_user_id
                      AND spm.production_id=:guardian_production AND spm.audience_type='student' AND spm.status='active'
                    JOIN users student ON student.id=fr.student_user_id AND student.active=1 AND student.account_status='active'
                    WHERE fr.guardian_user_id=pm.user_id AND fr.status='active' AND fr.relationship_type IN ('parent','guardian')
                  )
                )");
            $audience->execute(['production'=>$productionId,'guardian_production'=>$productionId]);
            $notice=$db->prepare("INSERT INTO app_notifications (recipient_user_id,source_type,source_id,title,body,action_path,created_at)
                VALUES (:recipient,'casting_release',:production,:title,:body,'/production',CURRENT_TIMESTAMP)");
            $noticeTitle=$type==='callback'?'Callbacks published':'Cast list published';
            foreach($audience->fetchAll(PDO::FETCH_COLUMN) as $recipientId){
                if(!ProductionContext::isActiveMember($db,(int)$recipientId,$productionId))continue;
                $notice->execute(['recipient'=>(int)$recipientId,'production'=>$productionId,'title'=>$noticeTitle,'body'=>'A new '.$label.' announcement is available in your CTSMD production.']);
                PushService::queue($db,(int)$recipientId,'general',$noticeTitle,'A production announcement is available.','/production','normal','casting-'.$type.'-'.$productionId,'casting-release-'.$type.'-'.$productionId.'-'.hash('sha256',(string)$snapshot),$productionId);
            }
        }

    }

    public static function unpublishPublication(PDO $db,int $productionId,int $actorId,string $type):void
    {
        ProductionLifecycleGuard::assertWritable($db,$productionId);
        self::assertPublicationType($type);
        $current=self::publication($db,$productionId,$type);
        if(($current['status']??'draft')!=='published')throw new RuntimeException('That '.($type==='callback'?'callback':'cast').' list is not currently published.');
        $s=$db->prepare("UPDATE production_cast_publications SET status='draft',published_at=NULL,updated_at=CURRENT_TIMESTAMP WHERE production_id=:production AND publication_type=:type");
        $s->execute(['production'=>$productionId,'type'=>$type]);
        self::audit($db,$actorId,'casting.'.$type.'_unpublished',$productionId,'Unpublished production '.($type==='callback'?'callback':'cast').' announcement.',['publication_type'=>$type]);
    }

    public static function publicSettings(PDO $db,int $productionId):array
    {
        $s=$db->prepare("SELECT casting_public_enabled,casting_public_slug FROM productions WHERE id=:production LIMIT 1");
        $s->execute(['production'=>$productionId]);$row=$s->fetch();
        return ['enabled'=>(bool)($row['casting_public_enabled']??false),'slug'=>(string)($row['casting_public_slug']??'')];
    }

    public static function setPublicPage(PDO $db,int $productionId,int $actorId,bool $enabled):array
    {
        ProductionLifecycleGuard::assertWritable($db,$productionId);
        $db->beginTransaction();
        try{
            $s=$db->prepare("SELECT casting_public_enabled,casting_public_slug,title FROM productions WHERE id=:production FOR UPDATE");
            $s->execute(['production'=>$productionId]);$production=$s->fetch();
            if(!$production)throw new RuntimeException('That production could not be found.');
            $slug=trim((string)($production['casting_public_slug']??''));
            if($enabled&&$slug===''){
                for($i=0;$i<5;$i++){
                    $candidate=bin2hex(random_bytes(8));
                    $check=$db->prepare("SELECT 1 FROM productions WHERE casting_public_slug=:slug LIMIT 1");
                    $check->execute(['slug'=>$candidate]);
                    if(!$check->fetchColumn()){$slug=$candidate;break;}
                }
                if($slug==='')throw new RuntimeException('A public casting link could not be generated.');
            }
            $u=$db->prepare("UPDATE productions SET casting_public_enabled=:enabled,casting_public_slug=:slug WHERE id=:production");
            $u->execute(['enabled'=>$enabled?1:0,'slug'=>$slug!==''?$slug:null,'production'=>$productionId]);
            self::audit($db,$actorId,$enabled?'casting.public_page_enabled':'casting.public_page_disabled',$productionId,$enabled?'Enabled public casting announcements page.':'Disabled public casting announcements page.',['public_slug'=>$slug?:null]);
            $db->commit();
            return ['enabled'=>$enabled,'slug'=>$slug];
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof RuntimeException)throw $e;throw new RuntimeException('The public casting page setting could not be changed.');}
    }

    public static function publicProductionBySlug(PDO $db,string $slug):?array
    {
        $slug=trim($slug);
        if($slug===''||!preg_match('/^[a-f0-9]{16}$/',$slug))return null;
        $s=$db->prepare("SELECT id,title,season FROM productions WHERE casting_public_enabled=1 AND casting_public_slug=:slug AND is_active=1 LIMIT 1");
        $s->execute(['slug'=>$slug]);$row=$s->fetch();
        return $row?:null;
    }

    public static function publishedForProduction(PDO $db,int $productionId,string $type):?array
    {
        self::assertPublicationType($type);
        $s=$db->prepare("SELECT p.id production_id,p.title,p.season,pcp.publication_type,pcp.headline,pcp.member_note,pcp.cast_snapshot_json,pcp.published_at FROM production_cast_publications pcp JOIN productions p ON p.id=pcp.production_id AND p.is_active=1 WHERE pcp.production_id=:production AND pcp.publication_type=:type AND pcp.status='published' LIMIT 1");
        $s->execute(['production'=>$productionId,'type'=>$type]);$production=$s->fetch();
        if(!$production)return null;
        $raw=$production['cast_snapshot_json']??null;
        $decoded=$raw!==null&&trim((string)$raw)!==''?json_decode((string)$raw,true):null;
        // Fail closed: publication is the reviewed snapshot, never the mutable casting board.
        $production['cast']=is_array($decoded)?$decoded:[];
        unset($production['cast_snapshot_json']);
        return $production;
    }

    public static function publishedCastForProduction(PDO $db,int $productionId):?array
    {
        return self::publishedForProduction($db,$productionId,'cast');
    }

    public static function publishedCallbacksForProduction(PDO $db,int $productionId):?array
    {
        return self::publishedForProduction($db,$productionId,'callback');
    }

    public static function publishedCastsForUser(PDO $db,int $userId):array
    {
        $s=$db->prepare("SELECT DISTINCT p.id FROM production_cast_publications pcp JOIN productions p ON p.id=pcp.production_id AND p.is_active=1 JOIN production_memberships pm ON pm.production_id=p.id AND pm.user_id=:user AND pm.status='active' WHERE pcp.status='published' ORDER BY p.id");
        $s->execute(['user'=>$userId]);$out=[];
        foreach($s->fetchAll(PDO::FETCH_COLUMN) as $productionId){
            if(!ProductionContext::isActiveMember($db,$userId,(int)$productionId))continue;
            $publication=self::publishedCastForProduction($db,(int)$productionId);
            if($publication)$out[]=$publication;
        }
        return $out;
    }

    public static function refreshPublishedAfterRosterRemoval(PDO $db,int $productionId):void {
        if(!$db->inTransaction())throw new LogicException('An active transaction is required.');
        $s=$db->prepare("SELECT publication_type FROM production_cast_publications WHERE production_id=:id AND status='published' FOR UPDATE");
        $s->execute(['id'=>$productionId]);
        foreach($s->fetchAll(PDO::FETCH_COLUMN) as $type){
            $rows=$type==='callback'?self::currentPublishableCallbacks($db,$productionId):self::currentPublishableCast($db,$productionId);
            $sql="UPDATE production_cast_publications SET status=:status,cast_snapshot_json=:snapshot,updated_at=NOW() WHERE production_id=:id AND publication_type=:type";
            $update=$db->prepare($sql);
            $update->execute(['status'=>$rows?'published':'draft','snapshot'=>json_encode($rows,JSON_THROW_ON_ERROR),'id'=>$productionId,'type'=>$type]);
        }
    }
    private static function currentPublishableCast(PDO $db,int $productionId):array{$s=$db->prepare("SELECT CONCAT(u.first_name,' ',u.last_name) name,COALESCE(NULLIF(cr.role_title,''),pm.participation_role) role,cr.participation_track,'registered' identity_type FROM production_casting_records cr JOIN users u ON u.id=cr.user_id AND u.active=1 AND u.account_status<>'disabled' JOIN production_memberships pm ON pm.id=cr.production_membership_id AND pm.status='active' WHERE cr.production_id=:production AND cr.casting_status='cast' AND cr.rostered_at IS NOT NULL ORDER BY COALESCE(cr.participation_track,''),COALESCE(pm.participation_role,''),u.last_name,u.first_name");$s->execute(['production'=>$productionId]);return array_merge($s->fetchAll(),ManualParticipantService::publicCastingRows($db,$productionId,'cast'));}
    private static function currentPublishableCallbacks(PDO $db,int $productionId):array{$s=$db->prepare("SELECT CONCAT(u.first_name,' ',u.last_name) name,cr.role_title role,cr.participation_track,'registered' identity_type FROM production_casting_records cr JOIN users u ON u.id=cr.user_id AND u.active=1 AND u.account_status<>'disabled' WHERE cr.production_id=:production AND cr.casting_status='callback' ORDER BY COALESCE(cr.participation_track,''),u.last_name,u.first_name");$s->execute(['production'=>$productionId]);return array_merge($s->fetchAll(),ManualParticipantService::publicCastingRows($db,$productionId,'callback'));}
    private static function publishableCount(PDO $db,int $productionId,string $type):int{return count($type==='callback'?self::currentPublishableCallbacks($db,$productionId):self::currentPublishableCast($db,$productionId));}
    private static function assertPublicationType(string $type):void{if(!in_array($type,['callback','cast'],true))throw new RuntimeException('Choose a valid casting publication type.');}
    private static function audit(PDO $db,int $actor,string $event,int $id,string $summary,array $meta):void{$s=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,:event,'casting',:id,:summary,:meta)");$s->execute(['actor'=>$actor,'event'=>$event,'id'=>$id,'summary'=>$summary,'meta'=>json_encode($meta,JSON_THROW_ON_ERROR)]);}
}
