<?php

declare(strict_types=1);

require_once __DIR__.'/ScheduleAudience.php';

final class CastingService
{
    public static function board(PDO $db,int $productionId):array
    {
        $records=$db->prepare("SELECT cr.*,CONCAT(u.first_name,' ',u.last_name) person_name,u.display_role account_role,rs.participant_age_group,ro.title opportunity_title,pm.status membership_status FROM production_casting_records cr JOIN users u ON u.id=cr.user_id LEFT JOIN registration_submissions rs ON rs.id=cr.registration_submission_id LEFT JOIN registration_opportunities ro ON ro.id=rs.opportunity_id LEFT JOIN production_memberships pm ON pm.id=cr.production_membership_id WHERE cr.production_id=:production ORDER BY FIELD(cr.casting_status,'offered','cast','callback','under_review','not_cast','withdrawn'),u.last_name,u.first_name");
        $records->execute(['production'=>$productionId]);
        $rows=$records->fetchAll();
        foreach($rows as &$row)$row['groups']=self::groupsForMembership($db,(int)($row['production_membership_id']??0));unset($row);
        return $rows;
    }

    public static function intakeCandidates(PDO $db,int $productionId):array
    {
        $s=$db->prepare("SELECT rs.id submission_id,rsl.participant_user_id user_id,CONCAT(u.first_name,' ',u.last_name) person_name,rs.participant_age_group,rs.status registration_status,ro.title opportunity_title,rs.submitted_at FROM registration_submissions rs JOIN registration_opportunities ro ON ro.id=rs.opportunity_id AND ro.production_id=:production AND ro.opportunity_type='audition' JOIN registration_submission_links rsl ON rsl.submission_id=rs.id JOIN users u ON u.id=rsl.participant_user_id AND u.active=1 LEFT JOIN production_casting_records cr ON cr.production_id=:production2 AND cr.user_id=rsl.participant_user_id WHERE rs.status NOT IN ('declined','cancelled') AND cr.id IS NULL ORDER BY rs.submitted_at,u.last_name,u.first_name");
        $s->execute(['production'=>$productionId,'production2'=>$productionId]);return $s->fetchAll();
    }

    public static function peopleCandidates(PDO $db,int $productionId):array
    {
        $s=$db->prepare("SELECT u.id,CONCAT(u.first_name,' ',u.last_name) name,u.display_role role FROM users u LEFT JOIN production_casting_records cr ON cr.production_id=:production AND cr.user_id=u.id WHERE u.active=1 AND cr.id IS NULL ORDER BY u.last_name,u.first_name");
        $s->execute(['production'=>$productionId]);return $s->fetchAll();
    }

    public static function groups(PDO $db,int $productionId):array{return ScheduleAudience::groups($db,$productionId,true);}

    public static function add(PDO $db,int $productionId,int $actorId,int $userId,?int $submissionId=null):int
    {
        if($productionId<1||$userId<1)throw new RuntimeException('Choose a valid person.');
        $db->beginTransaction();
        try{
            $p=$db->prepare('SELECT id,active FROM users WHERE id=:id FOR UPDATE');$p->execute(['id'=>$userId]);$person=$p->fetch();if(!$person||(int)$person['active']!==1)throw new RuntimeException('That person is not an active CTSMD person.');
            if($submissionId){$r=$db->prepare("SELECT rsl.participant_user_id,ro.production_id,ro.opportunity_type FROM registration_submission_links rsl JOIN registration_submissions rs ON rs.id=rsl.submission_id JOIN registration_opportunities ro ON ro.id=rs.opportunity_id WHERE rsl.submission_id=:submission LIMIT 1");$r->execute(['submission'=>$submissionId]);$link=$r->fetch();if(!$link||(int)$link['participant_user_id']!==$userId||(int)$link['production_id']!==$productionId||$link['opportunity_type']!=='audition')throw new RuntimeException('That audition intake is not linked to this person and Working Production.');}
            $i=$db->prepare("INSERT INTO production_casting_records (production_id,user_id,registration_submission_id,casting_status,created_by_user_id) VALUES (:production,:user,:submission,'under_review',:actor) ON DUPLICATE KEY UPDATE registration_submission_id=COALESCE(VALUES(registration_submission_id),registration_submission_id),updated_at=CURRENT_TIMESTAMP");
            $i->execute(['production'=>$productionId,'user'=>$userId,'submission'=>$submissionId,'actor'=>$actorId]);
            $id=(int)$db->lastInsertId();if($id<1){$q=$db->prepare('SELECT id FROM production_casting_records WHERE production_id=:production AND user_id=:user');$q->execute(['production'=>$productionId,'user'=>$userId]);$id=(int)$q->fetchColumn();}
            self::audit($db,$actorId,'casting.person_added',$id,'Added person to production casting board.',['production_id'=>$productionId,'user_id'=>$userId,'registration_submission_id'=>$submissionId]);$db->commit();return $id;
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof RuntimeException)throw $e;throw new RuntimeException('The person could not be added to casting.');}
    }

    public static function update(PDO $db,int $productionId,int $actorId,int $recordId,array $input):void
    {
        $status=(string)($input['casting_status']??'under_review');$role=trim((string)($input['role_title']??''));$track=trim((string)($input['participation_track']??''));$notes=trim((string)($input['staff_notes']??''));
        if(!in_array($status,['under_review','callback','offered','cast','not_cast','withdrawn'],true))throw new RuntimeException('Choose a valid casting status.');
        if(mb_strlen($role)>190||mb_strlen($track)>100||mb_strlen($notes)>2000)throw new RuntimeException('One or more casting fields are too long.');
        $db->beginTransaction();try{
            $s=$db->prepare('SELECT * FROM production_casting_records WHERE id=:id AND production_id=:production FOR UPDATE');$s->execute(['id'=>$recordId,'production'=>$productionId]);$before=$s->fetch();if(!$before)throw new RuntimeException('That casting record no longer exists in this production.');
            $decision=in_array($status,['offered','cast','not_cast','withdrawn'],true);$u=$db->prepare('UPDATE production_casting_records SET casting_status=:status,role_title=:role,participation_track=:track,staff_notes=:notes,decided_by_user_id=:actor,decided_at='.($decision?'CURRENT_TIMESTAMP':'decided_at').' WHERE id=:id');$u->execute(['status'=>$status,'role'=>$role!==''?$role:null,'track'=>$track!==''?$track:null,'notes'=>$notes!==''?$notes:null,'actor'=>$actorId,'id'=>$recordId]);
            self::audit($db,$actorId,'casting.decision_updated',$recordId,'Updated casting decision.',['production_id'=>$productionId,'before_status'=>$before['casting_status'],'after_status'=>$status,'role_title'=>$role?:null,'participation_track'=>$track?:null]);$db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof RuntimeException)throw $e;throw new RuntimeException('The casting decision could not be saved.');}
    }

    public static function finalizeRoster(PDO $db,int $productionId,int $actorId,int $recordId,array $groupIds):void
    {
        $groupIds=ScheduleAudience::validateGroupIds($db,$productionId,$groupIds);
        $db->beginTransaction();try{
            $s=$db->prepare("SELECT cr.*,u.display_role,CONCAT(u.first_name,' ',u.last_name) person_name FROM production_casting_records cr JOIN users u ON u.id=cr.user_id WHERE cr.id=:id AND cr.production_id=:production FOR UPDATE");$s->execute(['id'=>$recordId,'production'=>$productionId]);$record=$s->fetch();if(!$record)throw new RuntimeException('That casting record no longer exists.');if($record['casting_status']!=='cast')throw new RuntimeException('Mark the casting decision Cast before finalizing production access.');
            $role=trim((string)($record['role_title']??''));if($role==='')throw new RuntimeException('Enter a character or participation role before finalizing the roster.');
            $student=self::isStudent($db,(int)$record['user_id']);$audience=$student?'student':'staff';
            if(!$student && !self::isStaff($db,(int)$record['user_id']))$audience='student';
            if($audience==='student'&&!$student)throw new RuntimeException('Only Student or staff identities can be finalized through Casting. Add adult non-staff participants through Production Roster.');
            $guardianIds=[];
            if($student){$g=$db->prepare("SELECT fr.guardian_user_id,fr.relationship_type FROM family_relationships fr JOIN users u ON u.id=fr.guardian_user_id AND u.active=1 WHERE fr.student_user_id=:student AND fr.status='active' ORDER BY fr.is_primary DESC,fr.id");$g->execute(['student'=>(int)$record['user_id']]);$guardians=$g->fetchAll();if(!$guardians)throw new RuntimeException('This student has no active guardian relationship. Add the guardian in People before finalizing casting.');foreach($guardians as $guardian){$guardianIds[]=(int)$guardian['guardian_user_id'];self::upsertMembership($db,$productionId,(int)$guardian['guardian_user_id'],'guardian',ucfirst((string)$guardian['relationship_type']));}}
            $membershipId=self::upsertMembership($db,$productionId,(int)$record['user_id'],$audience,$role);
            $db->prepare("UPDATE production_group_members SET status='inactive',updated_at=CURRENT_TIMESTAMP WHERE production_membership_id=:membership")->execute(['membership'=>$membershipId]);
            if($groupIds){$link=$db->prepare("INSERT INTO production_group_members (group_id,production_membership_id,status,added_by_user_id) VALUES (:group_id,:membership,'active',:actor) ON DUPLICATE KEY UPDATE status='active',added_by_user_id=VALUES(added_by_user_id),updated_at=CURRENT_TIMESTAMP");foreach($groupIds as $groupId)$link->execute(['group_id'=>$groupId,'membership'=>$membershipId,'actor'=>$actorId]);}
            $db->prepare('UPDATE production_casting_records SET production_membership_id=:membership,rostered_by_user_id=:actor,rostered_at=CURRENT_TIMESTAMP WHERE id=:id')->execute(['membership'=>$membershipId,'actor'=>$actorId,'id'=>$recordId]);
            self::audit($db,$actorId,'casting.finalized_to_roster',$recordId,'Finalized cast member to production roster.',['production_id'=>$productionId,'user_id'=>(int)$record['user_id'],'membership_id'=>$membershipId,'role_title'=>$role,'group_ids'=>$groupIds,'auto_guardian_user_ids'=>$guardianIds]);$db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof RuntimeException)throw $e;throw new RuntimeException('Casting could not be finalized to the production roster.');}
    }

    private static function upsertMembership(PDO $db,int $productionId,int $userId,string $audience,string $role):int
    {
        $s=$db->prepare("INSERT INTO production_memberships (production_id,user_id,audience_type,participation_role,status) VALUES (:production,:user,:audience,:role,'active') ON DUPLICATE KEY UPDATE participation_role=VALUES(participation_role),status='active',updated_at=CURRENT_TIMESTAMP");$s->execute(['production'=>$productionId,'user'=>$userId,'audience'=>$audience,'role'=>$role]);
        $q=$db->prepare('SELECT id FROM production_memberships WHERE production_id=:production AND user_id=:user AND audience_type=:audience LIMIT 1');$q->execute(['production'=>$productionId,'user'=>$userId,'audience'=>$audience]);$id=(int)$q->fetchColumn();if($id<1)throw new RuntimeException('The production membership could not be resolved.');return $id;
    }

    private static function isStudent(PDO $db,int $userId):bool{$s=$db->prepare("SELECT 1 FROM auth_user_roles ur JOIN auth_roles r ON r.id=ur.role_id WHERE ur.user_id=:user AND r.slug='student' LIMIT 1");$s->execute(['user'=>$userId]);return (bool)$s->fetchColumn();}
    private static function isStaff(PDO $db,int $userId):bool{$s=$db->prepare("SELECT 1 FROM auth_user_roles ur JOIN auth_roles r ON r.id=ur.role_id WHERE ur.user_id=:user AND r.slug IN ('production_staff','administrator') LIMIT 1");$s->execute(['user'=>$userId]);return (bool)$s->fetchColumn();}
    private static function groupsForMembership(PDO $db,int $membershipId):array{if($membershipId<1)return [];$s=$db->prepare("SELECT pg.id,pg.name FROM production_group_members pgm JOIN production_groups pg ON pg.id=pgm.group_id WHERE pgm.production_membership_id=:membership AND pgm.status='active' AND pg.active=1 ORDER BY pg.sort_order,pg.name");$s->execute(['membership'=>$membershipId]);return $s->fetchAll();}
    private static function audit(PDO $db,int $actor,string $event,int $id,string $summary,array $meta):void{$s=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,:event,'casting_record',:id,:summary,:meta)");$s->execute(['actor'=>$actor,'event'=>$event,'id'=>$id,'summary'=>$summary,'meta'=>json_encode($meta,JSON_THROW_ON_ERROR)]);}
}
