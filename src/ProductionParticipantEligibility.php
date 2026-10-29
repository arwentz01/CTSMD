<?php
declare(strict_types=1);
require_once __DIR__.'/IdentityRolePolicy.php';
require_once __DIR__.'/PersonEligibility.php';
require_once __DIR__.'/ProductionLifecycleGuard.php';

/** Authoritative participation rules; never a grant of organizational authority. */
final class ProductionParticipantEligibility
{
    public static function evaluate(array $production,string $category,?string $dob,bool $exception=false):array
    {
        $result=static fn(string $status):array=>['status'=>$status,'eligible'=>in_array($status,['eligible','authorized_exception'],true)];
        if(($production['status']??'')==='archived'||!in_array($production['lifecycle_stage']??'',['upcoming','planning','in_production','performing'],true))return $result('production_closed');
        $allowed=$production['participant_category']??'students';
        if(!in_array($category,['student','adult'],true)||($allowed!=='mixed'&&$allowed!==($category==='adult'?'adults':'students')))return $result('wrong_participant_category');
        if($category==='adult')return $result('eligible');
        $min=$production['student_minimum_age']??null;$max=$production['student_maximum_age']??null;
        if($min===null&&$max===null)return $result('eligible');
        if($exception)return $result('authorized_exception');
        $date=$production['participant_eligibility_date']??null;
        if(!$dob||!$date)return $result('missing_eligibility_data');
        try{$age=PersonEligibility::ageOn($dob,$date);}catch(RuntimeException){return $result('missing_eligibility_data');}
        if($min!==null&&$age<(int)$min)return $result('below_minimum_age');
        if($max!==null&&$age>(int)$max)return $result('above_maximum_age');
        return $result('eligible');
    }

    public static function forUser(PDO $db,int $productionId,int $userId,bool $lock=false):array
    {
        if($lock&&!$db->inTransaction())throw new LogicException('Locked eligibility requires a transaction.');
        $suffix=$lock?' FOR UPDATE':'';
        $q=$db->prepare('SELECT * FROM productions WHERE id=?'.$suffix);$q->execute([$productionId]);$production=$q->fetch();
        $q=$db->prepare('SELECT active,account_status,date_of_birth,adult_participant FROM users WHERE id=?'.$suffix);$q->execute([$userId]);$person=$q->fetch();
        if(!$production||!$person||!(int)$person['active']||$person['account_status']==='disabled')return ['status'=>'unavailable','eligible'=>false];
        // Student always wins over an inconsistent adult flag. DOB never changes classification.
        $category=IdentityRolePolicy::isStudent($db,$userId)?'student':((bool)$person['adult_participant']?'adult':'unknown');
        $q=$db->prepare('SELECT id FROM production_age_exceptions WHERE production_id=? AND student_user_id=? AND revoked_at IS NULL'.$suffix);$q->execute([$productionId,$userId]);
        return self::evaluate($production,$category,$person['date_of_birth'],(bool)$q->fetchColumn())+['category'=>$category];
    }

    public static function assertEligible(PDO $db,int $productionId,int $userId):array
    {
        $result=self::forUser($db,$productionId,$userId,$db->inTransaction());
        if(!$result['eligible'])throw new RuntimeException('Participant eligibility: '.str_replace('_',' ',$result['status']).'.');
        return $result;
    }

    public static function assertManager(PDO $db,int $actorId,int $productionId):void
    {
        Auth::clearRequestCache($actorId);
        $q=$db->prepare("SELECT active,account_status FROM users WHERE id=? FOR UPDATE");$q->execute([$actorId]);$actor=$q->fetch();
        if(!$actor||!(int)$actor['active']||$actor['account_status']!=='active'||!in_array('production.manage',Auth::permissions($db,$actorId),true))throw new RuntimeException('Production management permission is required.');
        if(in_array('administrator',Auth::roles($db,$actorId),true))return;
        $q=$db->prepare("SELECT id FROM production_memberships WHERE production_id=? AND user_id=? AND audience_type='staff' AND status='active' FOR UPDATE");$q->execute([$productionId,$actorId]);
        if(!$q->fetchColumn())throw new RuntimeException('Current staff membership in this production is required.');
    }

    public static function saveSettings(PDO $db,int $productionId,int $actorId,array $input):void
    {
        $category=(string)($input['participant_category']??'');
        if(!in_array($category,['students','adults','mixed'],true))throw new RuntimeException('Choose who can participate.');
        $bounds=[];
        foreach(['student_minimum_age','student_maximum_age'] as $field){$value=trim((string)($input[$field]??''));if($value!==''&&(!ctype_digit($value)||(int)$value>120))throw new RuntimeException('Student age limits must be whole numbers from 0 to 120, or blank.');$bounds[$field]=$value===''?null:(int)$value;}
        if($bounds['student_minimum_age']!==null&&$bounds['student_maximum_age']!==null&&$bounds['student_minimum_age']>$bounds['student_maximum_age'])throw new RuntimeException('Minimum age cannot exceed maximum age.');
        $date=trim((string)($input['participant_eligibility_date']??''));$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if($date!==''&&(!$parsed||$parsed->format('Y-m-d')!==$date))throw new RuntimeException('Enter a valid eligibility date.');
        if(($bounds['student_minimum_age']!==null||$bounds['student_maximum_age']!==null)&&$date==='')throw new RuntimeException('Choose an eligibility date for Student age limits.');
        $db->beginTransaction();
        try{
            ProductionLifecycleGuard::assertWritable($db,$productionId,true);self::assertManager($db,$actorId,$productionId);
            $db->prepare('UPDATE productions SET participant_category=?,student_minimum_age=?,student_maximum_age=?,participant_eligibility_date=? WHERE id=?')->execute([$category,$bounds['student_minimum_age'],$bounds['student_maximum_age'],$date?:null,$productionId]);
            self::audit($db,$actorId,$productionId,'settings_updated',['production_id'=>$productionId,'category'=>$category,'bounds'=>$bounds,'date'=>$date]);$db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }

    public static function exception(PDO $db,int $productionId,int $studentId,int $actorId,string $reason,bool $revoke=false):void
    {
        $reason=trim($reason);if($reason===''||mb_strlen($reason)>1000)throw new RuntimeException('Enter a reason of up to 1,000 characters.');
        $db->beginTransaction();
        try{
            ProductionLifecycleGuard::assertWritable($db,$productionId,true);
            self::assertManager($db,$actorId,$productionId);
            $q=$db->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');$q->execute([$studentId]);
            if(!$q->fetchColumn()||!IdentityRolePolicy::isStudent($db,$studentId))throw new RuntimeException('Choose a Student account.');
            $db->prepare('UPDATE production_age_exceptions SET revoked_at=NOW(),revoked_by_user_id=? WHERE production_id=? AND student_user_id=? AND revoked_at IS NULL')->execute([$actorId,$productionId,$studentId]);
            if(!$revoke)$db->prepare('INSERT INTO production_age_exceptions(production_id,student_user_id,approved_by_user_id,reason) VALUES(?,?,?,?)')->execute([$productionId,$studentId,$actorId,$reason]);
            self::audit($db,$actorId,$studentId,$revoke?'age_exception_revoked':'age_exception_approved',['production_id'=>$productionId,'reason'=>$reason]);
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }

    public static function audit(PDO $db,int $actor,int $subject,string $event,array $metadata):void
    {
        $db->prepare("INSERT INTO audit_events(actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES(?,?,'user',?,'Updated participation eligibility.',?)")->execute([$actor,'participation.'.$event,$subject,json_encode($metadata,JSON_THROW_ON_ERROR)]);
    }
}
