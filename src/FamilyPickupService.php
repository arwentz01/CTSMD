<?php
declare(strict_types=1);
require_once __DIR__.'/StudentProfileService.php';
require_once __DIR__.'/AccessPolicy.php';

final class FamilyPickupService {
    public static function canManage(PDO $db,array $actor,int $student):bool {
        if($student<1 || !StudentProfileService::profile($db,$student))return false;
        if(AccessPolicy::canManagePeople($actor))return true;
        $s=$db->prepare("SELECT 1 FROM family_relationships WHERE guardian_user_id=:adult AND student_user_id=:student AND status='active' AND relationship_type IN ('parent','guardian') LIMIT 1");
        $s->execute(['adult'=>(int)$actor['id'],'student'=>$student]);return (bool)$s->fetchColumn();
    }
    public static function requireManager(PDO $db,array $actor,int $student):void {
        if(!self::canManage($db,$actor,$student))throw new RuntimeException('You cannot manage this student’s family or pickup list.');
    }
    public static function adults(PDO $db,int $student):array {
        $s=$db->prepare("SELECT fr.id,fr.guardian_user_id,fr.relationship_type,fr.is_primary,fr.status,CONCAT(u.first_name,' ',u.last_name) name,u.email FROM family_relationships fr JOIN users u ON u.id=fr.guardian_user_id WHERE fr.student_user_id=:student ORDER BY fr.status='active' DESC,fr.is_primary DESC,u.last_name");
        $s->execute(['student'=>$student]);return $s->fetchAll();
    }
    public static function pickup(PDO $db,int $student):array {
        $s=$db->prepare("SELECT * FROM student_pickup_people WHERE student_user_id=:student ORDER BY active DESC,name,id");
        $s->execute(['student'=>$student]);return $s->fetchAll();
    }
    public static function requests(PDO $db,int $student):array {
        $s=$db->prepare("SELECT * FROM student_relationship_requests WHERE student_user_id=:student ORDER BY created_at DESC,id DESC LIMIT 40");
        $s->execute(['student'=>$student]);return $s->fetchAll();
    }
    private static function audit(PDO $db,int $actor,int $student,string $event,array $metadata):void {
        $s=$db->prepare("INSERT INTO audit_events(actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES(:actor,:event,'student',:student,:summary,:metadata)");
        $s->execute(['actor'=>$actor,'event'=>$event,'student'=>$student,'summary'=>'Student family or pickup authorization updated.','metadata'=>json_encode($metadata,JSON_THROW_ON_ERROR)]);
    }
    public static function savePickup(PDO $db,array $actor,int $student,array $input):void {
        self::requireManager($db,$actor,$student);
        $id=(int)($input['pickup_id']??0);$name=trim((string)($input['name']??''));$relation=trim((string)($input['relationship_label']??''));$phone=trim((string)($input['phone']??''));$notes=trim((string)($input['notes']??''));
        if($name===''||$relation===''||mb_strlen($name)>190||mb_strlen($relation)>100||mb_strlen($phone)>60||mb_strlen($notes)>500)throw new RuntimeException('Enter a name and relationship; check field lengths.');
        $db->beginTransaction();try{
            if($id>0){$s=$db->prepare("UPDATE student_pickup_people SET name=:name,relationship_label=:relation,phone=:phone,notes=:notes,updated_by_user_id=:actor WHERE id=:id AND student_user_id=:student AND linked_guardian_user_id IS NULL");
                $s->execute(['name'=>$name,'relation'=>$relation,'phone'=>$phone?:null,'notes'=>$notes?:null,'actor'=>(int)$actor['id'],'id'=>$id,'student'=>$student]);if(!$s->rowCount())throw new RuntimeException('Pickup person not found or managed through an adult relationship.');
            }else{$s=$db->prepare("INSERT INTO student_pickup_people(student_user_id,name,relationship_label,phone,notes,updated_by_user_id) VALUES(:student,:name,:relation,:phone,:notes,:actor)");
                $s->execute(['student'=>$student,'name'=>$name,'relation'=>$relation,'phone'=>$phone?:null,'notes'=>$notes?:null,'actor'=>(int)$actor['id']]);$id=(int)$db->lastInsertId();}
            self::audit($db,(int)$actor['id'],$student,'family.pickup_saved',['pickup_id'=>$id,'name'=>$name,'relationship'=>$relation]);$db->commit();
        }catch(Throwable $e){$db->rollBack();throw $e;}
    }
    public static function togglePickup(PDO $db,array $actor,int $student,int $id,bool $active):void {
        self::requireManager($db,$actor,$student);
        $db->beginTransaction();try{
            $s=$db->prepare("UPDATE student_pickup_people SET active=:active,updated_by_user_id=:actor WHERE id=:id AND student_user_id=:student");
            $s->execute(['active'=>$active?1:0,'actor'=>(int)$actor['id'],'id'=>$id,'student'=>$student]);
            if(!$s->rowCount())throw new RuntimeException('Pickup authorization was not changed.');
            self::audit($db,(int)$actor['id'],$student,'family.pickup_status',['pickup_id'=>$id,'active'=>$active]);$db->commit();
        }catch(Throwable $e){$db->rollBack();throw $e;}
    }
    public static function requestAdult(PDO $db,array $actor,int $student,array $input):void {
        self::requireManager($db,$actor,$student);
        $email=strtolower(trim((string)($input['adult_email']??'')));$type=(string)($input['relationship_type']??'');$label=trim((string)($input['relationship_label']??''));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($email)>190||!in_array($type,['parent','guardian','caregiver'],true)||$label===''||mb_strlen($label)>100)throw new RuntimeException('Enter a valid adult email, relationship and access type.');
        $s=$db->prepare("SELECT id FROM users WHERE LOWER(email)=:email AND active=1 AND account_status<>'disabled' LIMIT 1");$s->execute(['email'=>$email]);$adultId=$s->fetchColumn()?:null;
        if($adultId){$check=$db->prepare("SELECT 1 FROM family_relationships WHERE guardian_user_id=:adult AND student_user_id=:student AND status='active'");$check->execute(['adult'=>$adultId,'student'=>$student]);if($check->fetchColumn())throw new RuntimeException('This adult is already connected to the child.');}
        $exists=$db->prepare("SELECT 1 FROM student_relationship_requests WHERE student_user_id=:student AND adult_email=:email AND status='pending' LIMIT 1");$exists->execute(['student'=>$student,'email'=>$email]);if($exists->fetchColumn())throw new RuntimeException('An approval request for this adult is already pending.');
        $db->beginTransaction();try{
            $s=$db->prepare("INSERT INTO student_relationship_requests(student_user_id,adult_email,adult_user_id,relationship_type,relationship_label,pickup_authorized,requested_by_user_id) VALUES(:student,:email,:adult,:type,:label,:pickup,:actor)");
            $s->execute(['student'=>$student,'email'=>$email,'adult'=>$adultId,'type'=>$type,'label'=>$label,'pickup'=>!empty($input['pickup_authorized'])?1:0,'actor'=>(int)$actor['id']]);
            self::audit($db,(int)$actor['id'],$student,'family.relationship_requested',['request_id'=>(int)$db->lastInsertId(),'email'=>$email,'type'=>$type]);$db->commit();
        }catch(Throwable $e){$db->rollBack();throw $e;}
    }
    public static function decide(PDO $db,array $actor,int $student,int $id,bool $approve):void {
        if(!AccessPolicy::canManagePeople($actor))throw new RuntimeException('CTSMD staff approval is required to activate new adult access.');
        self::requireManager($db,$actor,$student);
        $db->beginTransaction();try{
            $s=$db->prepare("SELECT * FROM student_relationship_requests WHERE id=:id AND student_user_id=:student FOR UPDATE");$s->execute(['id'=>$id,'student'=>$student]);$request=$s->fetch();
            if(!$request||$request['status']!=='pending')throw new RuntimeException('Relationship request is no longer pending.');
            if($approve){
                $s=$db->prepare("SELECT id FROM users WHERE LOWER(email)=:email AND active=1 AND account_status='active' LIMIT 1");$s->execute(['email'=>$request['adult_email']]);$adult=$s->fetchColumn();
                if(!$adult)throw new RuntimeException('The adult must first register and have an active CTSMD account.');
                $s=$db->prepare("INSERT INTO family_relationships(guardian_user_id,student_user_id,relationship_type,is_primary,status,created_by_user_id) VALUES(:adult,:student,:type,0,'active',:actor) ON DUPLICATE KEY UPDATE relationship_type=VALUES(relationship_type),status='active'");
                $s->execute(['adult'=>$adult,'student'=>$student,'type'=>$request['relationship_type'],'actor'=>(int)$actor['id']]);
                if((int)$request['pickup_authorized']===1){
                    $q=$db->prepare("SELECT 1 FROM student_pickup_people WHERE student_user_id=:student AND linked_guardian_user_id=:adult LIMIT 1");$q->execute(['student'=>$student,'adult'=>$adult]);
                    if(!$q->fetchColumn()){
                        $u=$db->prepare("SELECT CONCAT(first_name,' ',last_name) name FROM users WHERE id=:id");$u->execute(['id'=>$adult]);$name=(string)$u->fetchColumn();
                        $q=$db->prepare("INSERT INTO student_pickup_people(student_user_id,name,relationship_label,linked_guardian_user_id,updated_by_user_id) VALUES(:student,:name,:label,:adult,:actor)");
                        $q->execute(['student'=>$student,'name'=>$name,'label'=>$request['relationship_label'],'adult'=>$adult,'actor'=>(int)$actor['id']]);
                    }
                }
            }
            $s=$db->prepare("UPDATE student_relationship_requests SET status=:status,adult_user_id=COALESCE(:adult,adult_user_id),reviewed_by_user_id=:actor,reviewed_at=NOW() WHERE id=:id");
            $s->execute(['status'=>$approve?'approved':'rejected','adult'=>$adult??null,'actor'=>(int)$actor['id'],'id'=>$id]);
            self::audit($db,(int)$actor['id'],$student,'family.relationship_reviewed',['request_id'=>$id,'approved'=>$approve]);$db->commit();
        }catch(Throwable $e){$db->rollBack();throw $e;}
    }
}
