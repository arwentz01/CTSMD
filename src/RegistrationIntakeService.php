<?php

declare(strict_types=1);

require_once __DIR__ . '/Auth.php';

final class RegistrationIntakeService
{
    public static function review(PDO $db,int $submissionId): ?array
    {
        if($submissionId<1)return null;
        $stmt=$db->prepare("SELECT rs.*,ro.title opportunity_title,ro.production_id,ro.opportunity_type,p.title production_title,rsl.participant_user_id,rsl.guardian_user_id,rsl.link_method,rsl.linked_at,CONCAT(pu.first_name,' ',pu.last_name) linked_participant_name,CONCAT(gu.first_name,' ',gu.last_name) linked_guardian_name FROM registration_submissions rs JOIN registration_opportunities ro ON ro.id=rs.opportunity_id LEFT JOIN productions p ON p.id=ro.production_id LEFT JOIN registration_submission_links rsl ON rsl.submission_id=rs.id LEFT JOIN users pu ON pu.id=rsl.participant_user_id LEFT JOIN users gu ON gu.id=rsl.guardian_user_id WHERE rs.id=:id LIMIT 1");
        $stmt->execute(['id'=>$submissionId]);$row=$stmt->fetch();if(!$row)return null;
        $row['participant_candidates']=self::participantCandidates($db,$row);
        $row['guardian_candidates']=$row['participant_age_group']==='adult'?[]:self::guardianCandidates($db,$row);
        return $row;
    }

    public static function linkExisting(PDO $db,array $actor,int $submissionId,int $participantUserId,?int $guardianUserId): void
    {
        $submission=self::review($db,$submissionId);if(!$submission)throw new RuntimeException('That registration no longer exists.');
        if($submission['participant_user_id'])throw new RuntimeException('This registration is already linked to CTSMD records.');
        if(!in_array($submission['status'],['accepted','submitted','waitlisted'],true))throw new RuntimeException('Only an active registration can be linked to CTSMD records.');
        $participant=self::user($db,$participantUserId);if(!$participant)throw new RuntimeException('Choose an available participant record.');
        $minor=$submission['participant_age_group']!=='adult';
        if($minor){
            if(!$guardianUserId)throw new RuntimeException('Choose the guardian record for a minor participant.');
            $guardian=self::user($db,$guardianUserId);if(!$guardian)throw new RuntimeException('Choose an available guardian record.');
            self::assertStudent($db,$participantUserId);
            self::assertAdultGuardian($db,$guardianUserId);
            if($guardianUserId===$participantUserId)throw new RuntimeException('A Student cannot be their own guardian.');
        }else{$guardianUserId=null;}

        $db->beginTransaction();
        try{
            if($minor){self::ensureRelationship($db,$guardianUserId,$participantUserId,(int)$actor['id']);}
            self::saveLink($db,$submissionId,$participantUserId,$guardianUserId,(int)$actor['id'],'existing');
            self::audit($db,(int)$actor['id'],'registration.records_linked',$submissionId,'Linked registration to existing CTSMD people.',['participant_user_id'=>$participantUserId,'guardian_user_id'=>$guardianUserId]);
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof RuntimeException)throw $e;throw new RuntimeException('CTSMD records could not be linked.');}
    }

    public static function createFromRegistration(PDO $db,array $actor,int $submissionId): array
    {
        $submission=self::review($db,$submissionId);if(!$submission)throw new RuntimeException('That registration no longer exists.');
        if($submission['participant_user_id'])throw new RuntimeException('This registration is already linked to CTSMD records.');
        if(!in_array($submission['status'],['accepted','submitted','waitlisted'],true))throw new RuntimeException('Only an active registration can be converted to CTSMD records.');
        $minor=$submission['participant_age_group']!=='adult';
        $participantId=null;$guardianId=null;$createdParticipant=false;$createdGuardian=false;

        $db->beginTransaction();
        try{
            if($minor){
                $guardianId=self::userIdByEmail($db,(string)$submission['guardian_email']);
                if(!$guardianId){
                    [$gFirst,$gLast]=self::splitName((string)$submission['guardian_name']);
                    $guardianId=self::createAdultPerson($db,$gFirst,$gLast,(string)$submission['guardian_email'],(int)$actor['id']);$createdGuardian=true;
                }else self::assertAdultGuardian($db,$guardianId);
                $participantId=self::childCandidateForGuardian($db,$guardianId,(string)$submission['participant_first_name'],(string)$submission['participant_last_name']);
                if(!$participantId){$participantId=self::createManagedStudent($db,(string)$submission['participant_first_name'],(string)$submission['participant_last_name'],(int)$actor['id']);$createdParticipant=true;}
                self::ensureRelationship($db,$guardianId,$participantId,(int)$actor['id']);
            }else{
                $participantId=self::userIdByEmail($db,(string)$submission['registrant_email']);
                if(!$participantId){$participantId=self::createAdultPerson($db,(string)$submission['participant_first_name'],(string)$submission['participant_last_name'],(string)$submission['registrant_email'],(int)$actor['id']);$createdParticipant=true;}else{self::assertAvailableAdult($db,$participantId);}
            }
            $method=($createdParticipant||$createdGuardian)?(($createdParticipant&&($createdGuardian||!$minor))?'created':'mixed'):'existing';
            self::saveLink($db,$submissionId,$participantId,$guardianId,(int)$actor['id'],$method);
            self::audit($db,(int)$actor['id'],'registration.records_created_or_linked',$submissionId,'Converted registration into reviewed CTSMD people/household records.',['participant_user_id'=>$participantId,'guardian_user_id'=>$guardianId,'created_participant'=>$createdParticipant,'created_guardian'=>$createdGuardian]);
            $db->commit();return ['participant_user_id'=>$participantId,'guardian_user_id'=>$guardianId,'created_participant'=>$createdParticipant,'created_guardian'=>$createdGuardian];
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof RuntimeException)throw $e;throw new RuntimeException('CTSMD household records could not be created.');}
    }

    private static function participantCandidates(PDO $db,array $submission): array
    {
        if($submission['participant_age_group']==='adult'){
            $stmt=$db->prepare("SELECT id,CONCAT(first_name,' ',last_name) name,email,display_role,account_status FROM users WHERE active=1 AND account_status<>'disabled' AND LOWER(email)=LOWER(:email) ORDER BY id LIMIT 10");$stmt->execute(['email'=>$submission['registrant_email']]);return $stmt->fetchAll();
        }
        $stmt=$db->prepare("SELECT u.id,CONCAT(u.first_name,' ',u.last_name) name,u.email,u.display_role,u.account_status,CONCAT(g.first_name,' ',g.last_name) guardian_name,g.email guardian_email FROM users u LEFT JOIN family_relationships fr ON fr.student_user_id=u.id AND fr.status='active' LEFT JOIN users g ON g.id=fr.guardian_user_id AND g.active=1 AND g.account_status<>'disabled' WHERE u.active=1 AND u.account_status<>'disabled' AND LOWER(u.first_name)=LOWER(:first) AND LOWER(u.last_name)=LOWER(:last) ORDER BY u.id LIMIT 20");$stmt->execute(['first'=>$submission['participant_first_name'],'last'=>$submission['participant_last_name']]);return $stmt->fetchAll();
    }

    private static function guardianCandidates(PDO $db,array $submission): array
    {
        $stmt=$db->prepare("SELECT u.id,CONCAT(u.first_name,' ',u.last_name) name,u.email,u.display_role,u.account_status FROM users u WHERE u.active=1 AND u.account_status<>'disabled' AND u.email IS NOT NULL AND LOWER(u.email)=LOWER(:email) AND NOT EXISTS (SELECT 1 FROM auth_user_roles ur JOIN auth_roles r ON r.id=ur.role_id AND r.active=1 AND r.code='student' WHERE ur.user_id=u.id) ORDER BY u.id LIMIT 10");$stmt->execute(['email'=>$submission['guardian_email']]);return $stmt->fetchAll();
    }

    private static function createAdultPerson(PDO $db,string $first,string $last,string $email,int $actorId): int
    {
        $email=mb_strtolower(trim($email));if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('A valid adult email is required before creating a CTSMD person record.');
        $initials=self::initials($first,$last);$stmt=$db->prepare("INSERT INTO users (first_name,last_name,email,password_hash,account_status,organization_membership_status,initials,display_role,is_demo_current_user,active) VALUES (:first,:last,:email,NULL,'invited','pending',:initials,'Member / Guardian',0,1)");
        try{$stmt->execute(['first'=>$first,'last'=>$last,'email'=>$email,'initials'=>$initials]);}catch(PDOException $e){if((string)$e->getCode()==='23000'){ $id=self::userIdByEmail($db,$email);if($id){self::assertAdultGuardian($db,$id);return $id;}}throw $e;}
        $id=(int)$db->lastInsertId();$db->prepare("INSERT IGNORE INTO auth_user_roles (user_id,role_id,assigned_by_user_id) SELECT :user,id,:actor FROM auth_roles WHERE code='member' AND active=1 LIMIT 1")->execute(['user'=>$id,'actor'=>$actorId]);return $id;
    }

    private static function createManagedStudent(PDO $db,string $first,string $last,int $actorId): int
    {
        $stmt=$db->prepare("INSERT INTO users (first_name,last_name,email,password_hash,account_status,organization_membership_status,initials,display_role,is_demo_current_user,active) VALUES (:first,:last,NULL,NULL,'managed','pending',:initials,'Student',0,1)");$stmt->execute(['first'=>$first,'last'=>$last,'initials'=>self::initials($first,$last)]);$id=(int)$db->lastInsertId();$db->prepare("INSERT IGNORE INTO auth_user_roles (user_id,role_id,assigned_by_user_id) SELECT :user,id,:actor FROM auth_roles WHERE code='student' AND active=1 LIMIT 1")->execute(['user'=>$id,'actor'=>$actorId]);return $id;
    }

    private static function ensureRelationship(PDO $db,int $guardianId,int $studentId,int $actorId): void
    {
        if($guardianId===$studentId)throw new RuntimeException('A Student cannot be their own guardian.');self::assertAdultGuardian($db,$guardianId);self::assertStudent($db,$studentId);
        $stmt=$db->prepare("INSERT INTO family_relationships (guardian_user_id,student_user_id,relationship_type,is_primary,status,created_by_user_id) VALUES (:guardian,:student,'guardian',1,'active',:actor) ON DUPLICATE KEY UPDATE status='active',updated_at=CURRENT_TIMESTAMP");$stmt->execute(['guardian'=>$guardianId,'student'=>$studentId,'actor'=>$actorId]);
    }

    private static function assertStudent(PDO $db,int $userId): void
    {
        $stmt=$db->prepare("SELECT 1 FROM auth_user_roles ur JOIN auth_roles r ON r.id=ur.role_id AND r.active=1 AND r.code='student' JOIN users u ON u.id=ur.user_id AND u.active=1 AND u.account_status<>'disabled' WHERE ur.user_id=:user LIMIT 1");$stmt->execute(['user'=>$userId]);if(!$stmt->fetchColumn())throw new RuntimeException('The selected participant is not an available Student profile.');
    }

    private static function assertAdultGuardian(PDO $db,int $userId): void
    {
        $stmt=$db->prepare("SELECT 1 FROM users u WHERE u.id=:user AND u.active=1 AND u.account_status<>'disabled' AND NOT EXISTS (SELECT 1 FROM auth_user_roles ur JOIN auth_roles r ON r.id=ur.role_id AND r.active=1 AND r.code='student' WHERE ur.user_id=u.id) LIMIT 1");$stmt->execute(['user'=>$userId]);if(!$stmt->fetchColumn())throw new RuntimeException('The selected guardian must be an available adult CTSMD person.');
    }

    private static function assertAvailableAdult(PDO $db,int $userId): void
    {
        $stmt=$db->prepare("SELECT 1 FROM users u WHERE u.id=:user AND u.active=1 AND u.account_status<>'disabled' AND NOT EXISTS (SELECT 1 FROM auth_user_roles ur JOIN auth_roles r ON r.id=ur.role_id AND r.active=1 AND r.code='student' WHERE ur.user_id=u.id) LIMIT 1");$stmt->execute(['user'=>$userId]);if(!$stmt->fetchColumn())throw new RuntimeException('That adult account is unavailable. Restore it in Account & Access before linking this registration.');
    }

    private static function childCandidateForGuardian(PDO $db,int $guardianId,string $first,string $last): ?int
    {
        $stmt=$db->prepare("SELECT u.id FROM family_relationships fr JOIN users u ON u.id=fr.student_user_id AND u.active=1 AND u.account_status<>'disabled' JOIN auth_user_roles ur ON ur.user_id=u.id JOIN auth_roles r ON r.id=ur.role_id AND r.active=1 AND r.code='student' WHERE fr.guardian_user_id=:guardian AND fr.status='active' AND LOWER(u.first_name)=LOWER(:first) AND LOWER(u.last_name)=LOWER(:last) LIMIT 1");$stmt->execute(['guardian'=>$guardianId,'first'=>$first,'last'=>$last]);$id=$stmt->fetchColumn();return $id!==false?(int)$id:null;
    }

    private static function userIdByEmail(PDO $db,string $email): ?int{$email=mb_strtolower(trim($email));if($email==='')return null;$stmt=$db->prepare('SELECT id FROM users WHERE active=1 AND LOWER(email)=:email LIMIT 1');$stmt->execute(['email'=>$email]);$id=$stmt->fetchColumn();return $id!==false?(int)$id:null;}
    private static function user(PDO $db,int $id):?array{$stmt=$db->prepare("SELECT id,first_name,last_name,email,display_role,account_status FROM users WHERE id=:id AND active=1 AND account_status<>'disabled' LIMIT 1");$stmt->execute(['id'=>$id]);return $stmt->fetch()?:null;}
    private static function saveLink(PDO $db,int $submissionId,int $participantId,?int $guardianId,int $actorId,string $method):void{$stmt=$db->prepare('INSERT INTO registration_submission_links (submission_id,participant_user_id,guardian_user_id,linked_by_user_id,link_method) VALUES (:submission,:participant,:guardian,:actor,:method)');$stmt->execute(['submission'=>$submissionId,'participant'=>$participantId,'guardian'=>$guardianId,'actor'=>$actorId,'method'=>$method]);}
    private static function audit(PDO $db,int $actor,string $event,int $submissionId,string $summary,array $meta):void{$stmt=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,:event,'registration_submission',:id,:summary,:meta)");$stmt->execute(['actor'=>$actor,'event'=>$event,'id'=>$submissionId,'summary'=>$summary,'meta'=>json_encode($meta,JSON_THROW_ON_ERROR)]);}
    private static function splitName(string $name):array{$parts=preg_split('/\s+/',trim($name))?:[];$first=array_shift($parts)?:'Guardian';$last=$parts?implode(' ',$parts):'Household';return [$first,$last];}
    private static function initials(string $first,string $last):string{return mb_strtoupper(mb_substr(trim($first),0,1).mb_substr(trim($last),0,1));}
}
