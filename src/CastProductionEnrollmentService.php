<?php
declare(strict_types=1);
require_once __DIR__.'/IdentityRolePolicy.php';
require_once __DIR__.'/TheatreHistoryService.php';
require_once __DIR__.'/ProductionParticipantEligibility.php';

/**
 * Enroll an accepted cast student and their currently active parent/guardian
 * relationships. Must be invoked inside the caller's transaction.
 * Never enroll pickup-only contacts or unapproved relationship requests.
 */
final class CastProductionEnrollmentService {
    public static function enroll(PDO $db,int $productionId,int $studentId,int $actorId,string $role):array {
        if(!$db->inTransaction())throw new LogicException('Cast enrollment requires an existing transaction.');
        $eligibility=ProductionParticipantEligibility::assertEligible($db,$productionId,$studentId);
        $type=$eligibility['category'];
        IdentityRolePolicy::assertProductionAudience($db,$studentId,$type);
        $role=trim($role);
        if($role==='')$role='Cast';
        $role=mb_substr($role,0,120);
        self::membership($db,$productionId,$studentId,$type,$role);
        $q=$db->prepare('SELECT id FROM production_memberships WHERE production_id=? AND user_id=? AND audience_type=?');$q->execute([$productionId,$studentId,$type]);$membershipId=(int)$q->fetchColumn();
        $db->prepare('UPDATE production_casting_records SET production_membership_id=?,rostered_by_user_id=?,rostered_at=NOW() WHERE production_id=? AND user_id=?')->execute([$membershipId,$actorId,$productionId,$studentId]);
        $db->prepare("INSERT IGNORE INTO production_participant_functions(production_id,user_id,function_type,title,assigned_by_user_id) VALUES(?,?,'cast',?,?)")->execute([$productionId,$studentId,$role,$actorId]);
        if($type==='adult'){
            TheatreHistoryService::syncStudentCredit($db,$productionId,$studentId,$actorId);
            ProductionParticipantEligibility::audit($db,$actorId,$studentId,'adult_enrolled',['production_id'=>$productionId,'membership_id'=>$membershipId]);
            return [];
        }
        $q=$db->prepare("SELECT DISTINCT fr.guardian_user_id,fr.relationship_type FROM family_relationships fr JOIN users u ON u.id=fr.guardian_user_id AND u.active=1 AND u.account_status<>'disabled' WHERE fr.student_user_id=:student AND fr.status='active' AND fr.relationship_type IN ('parent','guardian') ORDER BY fr.guardian_user_id");
        $q->execute(['student'=>$studentId]);$guardianIds=[];
        foreach($q->fetchAll() as $adult){
            $id=(int)$adult['guardian_user_id'];
            IdentityRolePolicy::assertProductionAudience($db,$id,'guardian');
            self::membership($db,$productionId,$id,'guardian',ucfirst((string)$adult['relationship_type']));
            $guardianIds[]=$id;
        }
        if(!$guardianIds)throw new RuntimeException('An active parent or guardian must be linked before casting acceptance can complete.');
        TheatreHistoryService::syncStudentCredit($db,$productionId,$studentId,$actorId);
        $audit=$db->prepare("INSERT INTO audit_events(actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES(:actor,'casting.acceptance_enrollment','production',:production,'Automatically enrolled accepted student and active family guardians.',:metadata)");
        $audit->execute(['actor'=>$actorId,'production'=>$productionId,'metadata'=>json_encode(['student_user_id'=>$studentId,'guardian_user_ids'=>$guardianIds,'role'=>$role],JSON_THROW_ON_ERROR)]);
        return $guardianIds;
    }
    private static function membership(PDO $db,int $productionId,int $userId,string $type,string $role):void {
        // Do not clobber an already-active production membership role (e.g. staff).
        $s=$db->prepare("SELECT id,audience_type,status FROM production_memberships WHERE production_id=:production AND user_id=:user AND audience_type=:type FOR UPDATE");
        $s->execute(['production'=>$productionId,'user'=>$userId,'type'=>$type]);$old=$s->fetch();
        if($old&&$old['status']==='active'){
            if($type==='student'&&$old['audience_type']!=='student')throw new RuntimeException('The student has an incompatible production membership. Contact production staff.');
            return;
        }
        $s=$db->prepare("INSERT INTO production_memberships(production_id,user_id,audience_type,participation_role,status) VALUES(:production,:user,:type,:role,'active') ON DUPLICATE KEY UPDATE audience_type=VALUES(audience_type),participation_role=VALUES(participation_role),status='active',updated_at=CURRENT_TIMESTAMP");
        $s->execute(['production'=>$productionId,'user'=>$userId,'type'=>$type,'role'=>$role]);
    }
}
