<?php
declare(strict_types=1);
require_once __DIR__.'/ProductionLifecycleGuard.php';

final class CastingOfferService {
    public static function issue(PDO $db,int $productionId,int $recordId,int $actorId):int {
        ProductionLifecycleGuard::assertWritable($db,$productionId);
        $ownsTransaction=!$db->inTransaction();
        if($ownsTransaction)$db->beginTransaction();
        try {
            $s=$db->prepare("SELECT cr.*,p.title production_title,u.active student_active,u.account_status student_status FROM production_casting_records cr JOIN productions p ON p.id=cr.production_id JOIN users u ON u.id=cr.user_id WHERE cr.id=:id AND cr.production_id=:production FOR UPDATE");
            $s->execute(['id'=>$recordId,'production'=>$productionId]);$r=$s->fetch();
            if(!$r || $r['casting_status']!=='offered' || !(int)$r['student_active'] || $r['student_status']==='disabled')throw new RuntimeException('A current Offered student is required.');
            $role=trim((string)($r['role_title']??''));
            require_once __DIR__.'/CastingRoleService.php';
            $assigned=CastingRoleService::assigned($db,$productionId,$recordId);
            if($assigned)$role=implode(' / ',array_column($assigned,'title'));
            if($role==='')throw new RuntimeException('Assign the offered role before sending.');
            $s=$db->prepare("SELECT fr.guardian_user_id FROM family_relationships fr JOIN users u ON u.id=fr.guardian_user_id AND u.active=1 AND u.account_status='active' WHERE fr.student_user_id=:student AND fr.status='active'");
            $s->execute(['student'=>(int)$r['user_id']]);$guardians=array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));
            if(!$guardians)throw new RuntimeException('An active guardian is required before sending a student casting offer.');
            $s=$db->prepare("SELECT id,status FROM production_cast_offers WHERE casting_record_id=:record FOR UPDATE");
            $s->execute(['record'=>$recordId]);$existing=$s->fetch();
            if($existing && $existing['status']==='pending'){if($ownsTransaction)$db->commit();return (int)$existing['id'];}
            if($existing && $existing['status']==='accepted')throw new RuntimeException('This offer was accepted. Update the casting decision before issuing another.');
            if($existing){
                $s=$db->prepare("UPDATE production_cast_offers SET status='pending',role_snapshot=:role,student_user_id=:student,offered_by_user_id=:actor,responded_by_user_id=NULL,response_note=NULL,offered_at=NOW(),responded_at=NULL WHERE id=:id");
                $s->execute(['role'=>$role,'student'=>(int)$r['user_id'],'actor'=>$actorId,'id'=>(int)$existing['id']]);$offerId=(int)$existing['id'];
            }else{
                $s=$db->prepare("INSERT INTO production_cast_offers (production_id,casting_record_id,student_user_id,role_snapshot,offered_by_user_id) VALUES (:production,:record,:student,:role,:actor)");
                $s->execute(['production'=>$productionId,'record'=>$recordId,'student'=>(int)$r['user_id'],'role'=>$role,'actor'=>$actorId]);$offerId=(int)$db->lastInsertId();
            }
            $recipients=array_values(array_unique(array_merge([(int)$r['user_id']],$guardians)));
            $notify=$db->prepare("INSERT INTO app_notifications (recipient_user_id,source_type,source_id,title,body,action_path,created_at) VALUES (:recipient,'casting_offer',:id,:title,:body,'/notifications#casting-offers',NOW())");
            foreach($recipients as $recipient){$notify->execute(['recipient'=>$recipient,'id'=>$offerId,'title'=>'Casting offer needs a response','body'=>'A role has been offered for '.(string)$r['production_title'].'. Sign in to review and accept or decline.']);}
            $audit=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,'casting.offer_issued','casting_record',:record,'Sent a casting offer for family response.',:meta)");
            $audit->execute(['actor'=>$actorId,'record'=>$recordId,'meta'=>json_encode(['production_id'=>$productionId,'offer_id'=>$offerId,'recipient_count'=>count($recipients)],JSON_THROW_ON_ERROR)]);
            if($ownsTransaction)$db->commit();return $offerId;
        }catch(Throwable $e){if($ownsTransaction&&$db->inTransaction())$db->rollBack();throw $e;}
    }
    public static function pendingForUser(PDO $db,int $userId):array {
        $s=$db->prepare("SELECT o.id,o.role_snapshot,p.title production_title,u.first_name,u.last_name FROM production_cast_offers o JOIN productions p ON p.id=o.production_id AND p.is_active=1 JOIN production_casting_records cr ON cr.id=o.casting_record_id AND cr.casting_status='offered' JOIN users u ON u.id=o.student_user_id AND u.active=1 AND u.account_status<>'disabled' WHERE o.status='pending' AND (o.student_user_id=:student OR EXISTS(SELECT 1 FROM family_relationships fr WHERE fr.student_user_id=o.student_user_id AND fr.guardian_user_id=:guardian AND fr.status='active')) ORDER BY o.offered_at DESC");
        $s->execute(['student'=>$userId,'guardian'=>$userId]);return $s->fetchAll();
    }
    public static function respond(PDO $db,int $userId,int $offerId,string $decision,string $note):void {
        if(!in_array($decision,['accepted','declined'],true))throw new RuntimeException('Choose Accept or Decline.');
        $note=trim($note);if(mb_strlen($note)>2000)throw new RuntimeException('Limit your response note to 2,000 characters.');
        if($decision==='declined' && $note==='')throw new RuntimeException('Please add a short note explaining the decline.');
        $db->beginTransaction();
        try{
            $s=$db->prepare("SELECT o.*,cr.casting_status,p.is_active production_active FROM production_cast_offers o JOIN production_casting_records cr ON cr.id=o.casting_record_id JOIN productions p ON p.id=o.production_id WHERE o.id=:id FOR UPDATE");
            $s->execute(['id'=>$offerId]);$o=$s->fetch();
            if(!$o||$o['status']!=='pending'||$o['casting_status']!=='offered'||!(int)$o['production_active'])throw new RuntimeException('This casting offer is no longer available.');
            $s=$db->prepare("SELECT active,account_status FROM users WHERE id=:id");$s->execute(['id'=>$userId]);$actor=$s->fetch();
            if(!$actor||!(int)$actor['active']||$actor['account_status']!=='active')throw new RuntimeException('Your account cannot respond to this offer.');
            $s=$db->prepare("SELECT active,account_status FROM users WHERE id=:id");$s->execute(['id'=>(int)$o['student_user_id']]);$student=$s->fetch();
            if(!$student||!(int)$student['active']||$student['account_status']==='disabled')throw new RuntimeException('The student is unavailable.');
            if($userId!==(int)$o['student_user_id']){
                $s=$db->prepare("SELECT 1 FROM family_relationships WHERE guardian_user_id=:guardian AND student_user_id=:student AND status='active' LIMIT 1");
                $s->execute(['guardian'=>$userId,'student'=>(int)$o['student_user_id']]);if(!$s->fetchColumn())throw new RuntimeException('You are not an active guardian for this student.');
            }else{
                $s=$db->prepare("SELECT 1 FROM family_relationships fr JOIN users u ON u.id=fr.guardian_user_id AND u.active=1 AND u.account_status='active' WHERE fr.student_user_id=:student AND fr.status='active' LIMIT 1");
                $s->execute(['student'=>(int)$o['student_user_id']]);if(!$s->fetchColumn())throw new RuntimeException('An active guardian is required to respond.');
            }
            ProductionLifecycleGuard::assertWritable($db,(int)$o['production_id']);
            $status=$decision==='accepted'?'cast':'withdrawn';
            $s=$db->prepare("UPDATE production_cast_offers SET status=:decision,response_note=:note,responded_by_user_id=:actor,responded_at=NOW() WHERE id=:id AND status='pending'");
            $s->execute(['decision'=>$decision,'note'=>$note?:null,'actor'=>$userId,'id'=>$offerId]);if($s->rowCount()!==1)throw new RuntimeException('This offer has already been answered.');
            $s=$db->prepare("UPDATE production_casting_records SET casting_status=:status,decided_at=NOW(),decided_by_user_id=:actor WHERE id=:id AND casting_status='offered'");
            $s->execute(['status'=>$status,'actor'=>$userId,'id'=>(int)$o['casting_record_id']]);if($s->rowCount()!==1)throw new RuntimeException('The casting decision changed. Contact production staff.');
            $staff=$db->prepare("SELECT DISTINCT pm.user_id FROM production_memberships pm JOIN users u ON u.id=pm.user_id AND u.active=1 AND u.account_status='active' WHERE pm.production_id=:production AND pm.audience_type='staff' AND pm.status='active'");
            $staff->execute(['production'=>(int)$o['production_id']]);
            $notify=$db->prepare("INSERT INTO app_notifications (recipient_user_id,source_type,source_id,title,body,action_path,created_at) VALUES (:user,'casting_offer_response',:source,:title,:body,'/production/casting',NOW())");
            foreach($staff->fetchAll(PDO::FETCH_COLUMN) as $staffId){$notify->execute(['user'=>(int)$staffId,'source'=>$offerId,'title'=>'Casting offer '.($decision==='accepted'?'accepted':'declined'),'body'=>$decision==='declined'?'A student or guardian declined an offered role. Review the response note in casting.':'A student or guardian accepted an offered role.']);}
            $audit=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,:event,'casting_record',:record,:summary,:meta)");
            $audit->execute(['actor'=>$userId,'event'=>'casting.offer_'.$decision,'record'=>(int)$o['casting_record_id'],'summary'=>'A family member responded to a casting offer.','meta'=>json_encode(['offer_id'=>$offerId,'production_id'=>(int)$o['production_id'],'response_note'=>$note],JSON_THROW_ON_ERROR)]);
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
}
