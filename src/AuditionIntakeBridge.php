<?php

declare(strict_types=1);

require_once __DIR__.'/ProductionLifecycleGuard.php';

/**
 * Bridges public audition intake to an authenticated household and the casting board.
 * A guardian's verified email is only used to identify their existing household;
 * student membership must already be active and unambiguous.
 */
final class AuditionIntakeBridge
{
    public static function eligibleChildren(PDO $db,int $guardianId,array $submission): array
    {
        if(($submission['status']??'')==='cancelled'||($submission['status']??'')==='declined')return [];
        $stmt=$db->prepare("SELECT DISTINCT u.id,CONCAT(u.first_name,' ',u.last_name) name FROM family_relationships fr JOIN users u ON u.id=fr.student_user_id AND u.active=1 AND u.account_status<>'disabled' JOIN auth_user_roles ur ON ur.user_id=u.id JOIN auth_roles ar ON ar.id=ur.role_id AND ar.code='student' AND ar.active=1 WHERE fr.guardian_user_id=:guardian AND fr.status='active' AND LOWER(u.first_name)=LOWER(:first) AND LOWER(u.last_name)=LOWER(:last) ORDER BY u.id");
        $stmt->execute(['guardian'=>$guardianId,'first'=>$submission['participant_first_name'],'last'=>$submission['participant_last_name']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function claimWithToken(PDO $db,string $token,int $guardianId,int $studentId): void
    {
        if(!preg_match('/^[a-f0-9]{64}$/',$token))throw new RuntimeException('This registration link is invalid.');
        $db->beginTransaction();
        try{
            $g=$db->prepare("SELECT email FROM users WHERE id=:id AND active=1 AND account_status='active' AND email_verified_at IS NOT NULL AND NOT EXISTS (SELECT 1 FROM auth_user_roles ur JOIN auth_roles ar ON ar.id=ur.role_id AND ar.code='student' AND ar.active=1 WHERE ur.user_id=:student)");
            $g->execute(['id'=>$guardianId,'student'=>$guardianId]);$email=$g->fetchColumn();
            if(!$email)throw new RuntimeException('Verify your guardian account before linking an audition.');
            $s=$db->prepare("SELECT rs.*,ro.opportunity_type FROM registration_submissions rs JOIN registration_opportunities ro ON ro.id=rs.opportunity_id WHERE rs.manage_token_hash=:hash FOR UPDATE");
            $s->execute(['hash'=>hash('sha256',$token)]);$submission=$s->fetch(PDO::FETCH_ASSOC);
            if(!$submission||$submission['opportunity_type']!=='audition'||$submission['participant_age_group']==='adult'||!in_array($submission['status'],['submitted','waitlisted','accepted'],true))throw new RuntimeException('This audition registration cannot be linked.');
            if(!hash_equals(mb_strtolower((string)$submission['guardian_email']),mb_strtolower((string)$email)))throw new RuntimeException('Sign in with the verified guardian email used on this audition.');
            $eligible=self::eligibleChildren($db,$guardianId,$submission);
            if(!in_array($studentId,array_map('intval',array_column($eligible,'id')),true))throw new RuntimeException('Select a matching child from your authorized household.');
            $check=$db->prepare("SELECT participant_user_id,guardian_user_id FROM registration_submission_links WHERE submission_id=:id");
            $check->execute(['id'=>(int)$submission['id']]);$existing=$check->fetch(PDO::FETCH_ASSOC);
            if($existing&&((int)$existing['participant_user_id']!==$studentId||(int)$existing['guardian_user_id']!==$guardianId))throw new RuntimeException('This audition is already linked. Contact CTSMD staff to review it.');
            if(!$existing){
                $insert=$db->prepare("INSERT INTO registration_submission_links (submission_id,participant_user_id,guardian_user_id,linked_by_user_id,link_method) VALUES (:submission,:student,:guardian,:actor,'existing')");
                $insert->execute(['submission'=>(int)$submission['id'],'student'=>$studentId,'guardian'=>$guardianId,'actor'=>$guardianId]);
            }
            self::syncAccepted($db,(int)$submission['id'],$guardianId);
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }

    public static function reconcileVerifiedGuardian(PDO $db, int $guardianId): void
    {
        $guardian = $db->prepare("SELECT email FROM users WHERE id=:id AND active=1 AND account_status='active' AND email_verified_at IS NOT NULL AND email IS NOT NULL AND NOT EXISTS (SELECT 1 FROM auth_user_roles ur JOIN auth_roles r ON r.id=ur.role_id AND r.code='student' AND r.active=1 WHERE ur.user_id=:id2)");
        $guardian->execute(['id'=>$guardianId,'id2'=>$guardianId]);
        $email = $guardian->fetchColumn();
        if (!$email) return;

        $registrations = $db->prepare("SELECT rs.id,rs.participant_first_name,rs.participant_last_name FROM registration_submissions rs JOIN registration_opportunities ro ON ro.id=rs.opportunity_id WHERE ro.opportunity_type='audition' AND rs.participant_age_group<>'adult' AND rs.status IN ('submitted','waitlisted','accepted') AND LOWER(rs.guardian_email)=LOWER(:email) AND NOT EXISTS (SELECT 1 FROM registration_submission_links l WHERE l.submission_id=rs.id)");
        $registrations->execute(['email'=>$email]);
        foreach ($registrations->fetchAll(PDO::FETCH_ASSOC) as $registration) {
            $children = $db->prepare("SELECT DISTINCT u.id FROM family_relationships fr JOIN users u ON u.id=fr.student_user_id AND u.active=1 AND u.account_status<>'disabled' JOIN auth_user_roles ur ON ur.user_id=u.id JOIN auth_roles r ON r.id=ur.role_id AND r.code='student' AND r.active=1 WHERE fr.guardian_user_id=:guardian AND fr.status='active' AND LOWER(u.first_name)=LOWER(:first) AND LOWER(u.last_name)=LOWER(:last) LIMIT 2");
            $children->execute(['guardian'=>$guardianId,'first'=>$registration['participant_first_name'],'last'=>$registration['participant_last_name']]);
            $ids=$children->fetchAll(PDO::FETCH_COLUMN);
            // Never guess which child is involved when multiple records match.
            if(count($ids)!==1)continue;
            $link=$db->prepare("INSERT IGNORE INTO registration_submission_links (submission_id,participant_user_id,guardian_user_id,linked_by_user_id,link_method) VALUES (:submission,:student,:guardian,NULL,'existing')");
            $link->execute(['submission'=>(int)$registration['id'],'student'=>(int)$ids[0],'guardian'=>$guardianId]);
            if($link->rowCount()===1)self::syncAccepted($db,(int)$registration['id'],null);
        }
    }

    public static function syncAccepted(PDO $db, int $submissionId, ?int $actorId): bool
    {
        $stmt=$db->prepare("SELECT ro.production_id,rsl.participant_user_id FROM registration_submissions rs JOIN registration_opportunities ro ON ro.id=rs.opportunity_id AND ro.opportunity_type='audition' JOIN registration_submission_links rsl ON rsl.submission_id=rs.id JOIN users guardian ON guardian.id=rsl.guardian_user_id AND guardian.active=1 AND guardian.account_status='active' AND guardian.email_verified_at IS NOT NULL JOIN users u ON u.id=rsl.participant_user_id AND u.active=1 AND u.account_status<>'disabled' JOIN auth_user_roles ur ON ur.user_id=u.id JOIN auth_roles ar ON ar.id=ur.role_id AND ar.code='student' AND ar.active=1 WHERE rs.id=:submission AND rs.status='accepted' AND rs.participant_age_group<>'adult' AND ro.production_id IS NOT NULL AND rsl.guardian_user_id IS NOT NULL AND EXISTS (SELECT 1 FROM family_relationships fr WHERE fr.guardian_user_id=rsl.guardian_user_id AND fr.student_user_id=rsl.participant_user_id AND fr.status='active') LIMIT 1");
        $stmt->execute(['submission'=>$submissionId]);
        $match=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$match)return false;
        // Never create new casting records for a read-only production lifecycle stage.
        try{ProductionLifecycleGuard::assertWritable($db,(int)$match['production_id']);}catch(RuntimeException $e){return false;}
        // An existing casting decision is never reset to Under Review.
        $insert=$db->prepare("INSERT IGNORE INTO production_casting_records (production_id,user_id,registration_submission_id,casting_status,created_by_user_id) VALUES (:production,:student,:submission,'under_review',:actor)");
        $insert->execute(['production'=>(int)$match['production_id'],'student'=>(int)$match['participant_user_id'],'submission'=>$submissionId,'actor'=>$actorId]);
        return $insert->rowCount()===1;
    }

    public static function syncLinkedForGuardian(PDO $db, int $guardianId): void
    {
        $stmt=$db->prepare("SELECT submission_id FROM registration_submission_links WHERE guardian_user_id=:guardian");
        $stmt->execute(['guardian'=>$guardianId]);
        foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $id)self::syncAccepted($db,(int)$id,null);
    }
}
