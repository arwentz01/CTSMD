<?php

declare(strict_types=1);

require_once __DIR__.'/AccessPolicy.php';
require_once __DIR__.'/StorageService.php';

final class StudentProfileService
{
    public static function subjectsForViewer(PDO $db,array $viewer):array
    {
        $viewerId=(int)$viewer['id'];$subjects=[];
        if(self::isStudent($db,$viewerId))$subjects[$viewerId]=self::subject($db,$viewerId,'Self');
        $s=$db->prepare("SELECT u.id,fr.relationship_type FROM family_relationships fr JOIN users u ON u.id=fr.student_user_id WHERE fr.guardian_user_id=:guardian AND fr.status='active' ORDER BY u.last_name,u.first_name");$s->execute(['guardian'=>$viewerId]);foreach($s->fetchAll() as $r)$subjects[(int)$r['id']]=self::subject($db,(int)$r['id'],ucfirst((string)$r['relationship_type']));
        if(AccessPolicy::canManagePeople($viewer)){$s=$db->query("SELECT DISTINCT u.id FROM users u JOIN auth_user_roles ur ON ur.user_id=u.id JOIN auth_roles ar ON ar.id=ur.role_id AND ar.code='student' WHERE u.active=1 ORDER BY u.last_name,u.first_name");foreach($s->fetchAll() as $r)$subjects[(int)$r['id']]=self::subject($db,(int)$r['id'],'Staff managed');}
        return array_values(array_filter($subjects));
    }

    public static function canEdit(PDO $db,array $viewer,int $studentId):bool
    {
        if($studentId<1||!self::isStudent($db,$studentId))return false;if((int)$viewer['id']===$studentId)return true;if(AccessPolicy::canManagePeople($viewer))return true;$s=$db->prepare("SELECT 1 FROM family_relationships WHERE guardian_user_id=:viewer AND student_user_id=:student AND status='active' LIMIT 1");$s->execute(['viewer'=>(int)$viewer['id'],'student'=>$studentId]);return(bool)$s->fetchColumn();
    }

    public static function profile(PDO $db,int $studentId):?array
    {
        $s=$db->prepare("SELECT u.id,u.first_name,u.last_name,CONCAT(u.first_name,' ',u.last_name) legal_name,sp.preferred_name,sp.short_bio,sp.special_skills,sp.headshot_stored_file_id FROM users u JOIN auth_user_roles ur ON ur.user_id=u.id JOIN auth_roles ar ON ar.id=ur.role_id AND ar.code='student' LEFT JOIN student_profiles sp ON sp.user_id=u.id WHERE u.id=:student LIMIT 1");$s->execute(['student'=>$studentId]);return$s->fetch()?:null;
    }

    public static function save(PDO $db,string $projectRoot,array $viewer,int $studentId,array $input,array $upload):void
    {
        if(!self::canEdit($db,$viewer,$studentId))throw new RuntimeException('That Student profile is not available to this account.');$preferred=trim((string)($input['preferred_name']??''));$bio=trim((string)($input['short_bio']??''));$skills=trim((string)($input['special_skills']??''));if(mb_strlen($preferred)>120)throw new RuntimeException('Preferred name must be 120 characters or fewer.');if(mb_strlen($bio)>1500||mb_strlen($skills)>1500)throw new RuntimeException('Bio and special skills are limited to 1,500 characters each.');
        $db->beginTransaction();try{$existing=self::profile($db,$studentId);$headshot=$existing['headshot_stored_file_id']??null;if((int)($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){$stored=StorageService::store($db,$projectRoot,(int)$viewer['id'],$upload);if(!str_starts_with((string)$stored['mime_type'],'image/'))throw new RuntimeException('Headshots must be JPG, PNG, or WebP images.');$headshot=(int)$stored['stored_file_id'];}$s=$db->prepare("INSERT INTO student_profiles (user_id,preferred_name,short_bio,special_skills,headshot_stored_file_id,updated_by_user_id) VALUES (:user,:preferred,:bio,:skills,:headshot,:actor) ON DUPLICATE KEY UPDATE preferred_name=VALUES(preferred_name),short_bio=VALUES(short_bio),special_skills=VALUES(special_skills),headshot_stored_file_id=VALUES(headshot_stored_file_id),updated_by_user_id=VALUES(updated_by_user_id),updated_at=CURRENT_TIMESTAMP");$s->execute(['user'=>$studentId,'preferred'=>$preferred!==''?$preferred:null,'bio'=>$bio!==''?$bio:null,'skills'=>$skills!==''?$skills:null,'headshot'=>$headshot,'actor'=>(int)$viewer['id']]);self::audit($db,(int)$viewer['id'],$studentId,'Updated Student theatre profile.',['preferred_name'=>$preferred!==''?$preferred:null,'headshot_changed'=>(bool)((int)($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)]);$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e instanceof RuntimeException?$e:new RuntimeException('The Student profile could not be saved.');}
    }

    public static function headshot(PDO $db,array $viewer,int $studentId):?array
    {
        if(!self::canView($db,$viewer,$studentId))return null;$p=self::profile($db,$studentId);if(!$p||empty($p['headshot_stored_file_id']))return null;return StorageService::currentVersion($db,(int)$p['headshot_stored_file_id']);
    }

    public static function canView(PDO $db,array $viewer,int $studentId):bool
    {
        if(self::canEdit($db,$viewer,$studentId))return true;$s=$db->prepare("SELECT 1 FROM production_memberships mine JOIN production_memberships student ON student.production_id=mine.production_id AND student.user_id=:student AND student.audience_type='student' WHERE mine.user_id=:viewer LIMIT 1");$s->execute(['student'=>$studentId,'viewer'=>(int)$viewer['id']]);return(bool)$s->fetchColumn();
    }

    private static function subject(PDO $db,int $id,string $relationship):?array{$p=self::profile($db,$id);if(!$p)return null;return['id'=>$id,'name'=>$p['preferred_name']?:$p['legal_name'],'relationship'=>$relationship];}
    private static function isStudent(PDO $db,int $id):bool{$s=$db->prepare("SELECT 1 FROM auth_user_roles ur JOIN auth_roles r ON r.id=ur.role_id WHERE ur.user_id=:user AND r.code='student' LIMIT 1");$s->execute(['user'=>$id]);return(bool)$s->fetchColumn();}
    private static function audit(PDO $db,int $actor,int $student,string $summary,array $meta):void{$s=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,'student.profile_updated','user',:student,:summary,:meta)");$s->execute(['actor'=>$actor,'student'=>$student,'summary'=>$summary,'meta'=>json_encode($meta,JSON_THROW_ON_ERROR)]);}
}
