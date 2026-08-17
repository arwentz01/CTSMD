<?php

declare(strict_types=1);

require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/MailService.php';

final class PlatformRegistrationService
{
    public static function registerAdult(PDO $db,array $input,string $basePath): void
    {
        $first=trim((string)($input['first_name']??''));
        $last=trim((string)($input['last_name']??''));
        $email=mb_strtolower(trim((string)($input['email']??'')));
        $password=(string)($input['password']??'');
        $confirm=(string)($input['password_confirm']??'');
        $relationship=(string)($input['relationship_type']??'parent');

        if($first===''||mb_strlen($first)>100||$last===''||mb_strlen($last)>100)throw new RuntimeException('Enter your first and last name.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email address.');
        if(!hash_equals($password,$confirm))throw new RuntimeException('Passwords do not match.');
        if(strlen($password)<Auth::PASSWORD_MIN_LENGTH)throw new RuntimeException('Use a password with at least '.Auth::PASSWORD_MIN_LENGTH.' characters.');
        if(!in_array($relationship,['parent','guardian','caregiver'],true))throw new RuntimeException('Choose your household relationship.');

        $existing=$db->prepare('SELECT id,account_status FROM users WHERE LOWER(email)=:email LIMIT 1');
        $existing->execute(['email'=>$email]);
        $row=$existing->fetch();
        if($row){
            if($row['account_status']==='pending_verification')self::queueVerification($db,(int)$row['id'],$basePath);
            return;
        }

        $initials=mb_strtoupper(mb_substr($first,0,1).mb_substr($last,0,1));
        $db->beginTransaction();
        try{
            $insert=$db->prepare("INSERT INTO users (first_name,last_name,email,password_hash,account_status,email_verified_at,self_registered_at,initials,display_role,is_demo_current_user,active) VALUES (:first,:last,:email,:password,'pending_verification',NULL,CURRENT_TIMESTAMP,:initials,'Parent / Guardian',0,1)");
            $insert->execute(['first'=>$first,'last'=>$last,'email'=>$email,'password'=>password_hash($password,PASSWORD_DEFAULT),'initials'=>$initials]);
            $userId=(int)$db->lastInsertId();
            $role=$db->prepare("INSERT IGNORE INTO auth_user_roles (user_id,role_id) SELECT :user,id FROM auth_roles WHERE code='member' AND active=1 LIMIT 1");
            $role->execute(['user'=>$userId]);
            $audit=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (NULL,'account.self_registered','user',:id,'Created a public CTSMD Connect registration.',:meta)");
            $audit->execute(['id'=>$userId,'meta'=>json_encode(['relationship_type'=>$relationship],JSON_THROW_ON_ERROR)]);
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof RuntimeException)throw $e;throw new RuntimeException('We could not create that account. Please try again.');}

        self::queueVerification($db,$userId,$basePath);
    }

    public static function verification(PDO $db,string $token): ?array
    {
        if(!preg_match('/^[a-f0-9]{64}$/',$token))return null;
        $stmt=$db->prepare("SELECT v.id,v.user_id,u.first_name,u.last_name,u.email FROM auth_email_verifications v JOIN users u ON u.id=v.user_id AND u.active=1 WHERE v.token_hash=:hash AND v.verified_at IS NULL AND v.expires_at>NOW() AND u.account_status='pending_verification' LIMIT 1");
        $stmt->execute(['hash'=>hash('sha256',$token)]);
        return $stmt->fetch()?:null;
    }

    public static function verify(PDO $db,string $token): int
    {
        $verification=self::verification($db,$token);if(!$verification)throw new RuntimeException('That verification link is invalid or has expired.');
        $db->beginTransaction();
        try{
            $db->prepare("UPDATE users SET account_status='active',email_verified_at=CURRENT_TIMESTAMP WHERE id=:id AND account_status='pending_verification'")->execute(['id'=>(int)$verification['user_id']]);
            $db->prepare('UPDATE auth_email_verifications SET verified_at=CURRENT_TIMESTAMP WHERE id=:id')->execute(['id'=>(int)$verification['id']]);
            $db->prepare("UPDATE auth_email_verifications SET verified_at=COALESCE(verified_at,CURRENT_TIMESTAMP) WHERE user_id=:user AND id<>:id AND verified_at IS NULL")->execute(['user'=>(int)$verification['user_id'],'id'=>(int)$verification['id']]);
            $audit=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,'account.email_verified','user',:id,'Verified a self-registered CTSMD Connect email address.',NULL)");
            $audit->execute(['actor'=>(int)$verification['user_id'],'id'=>(int)$verification['user_id']]);
            $db->commit();
            return (int)$verification['user_id'];
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }

    public static function addManagedChild(PDO $db,array $guardian,array $input): int
    {
        if(Auth::hasRole($guardian,'student'))throw new RuntimeException('Student accounts cannot create household profiles.');
        $first=trim((string)($input['first_name']??''));$last=trim((string)($input['last_name']??''));$relationship=(string)($input['relationship_type']??'parent');
        if($first===''||mb_strlen($first)>100||$last===''||mb_strlen($last)>100)throw new RuntimeException('Enter the child’s first and last name.');
        if(!in_array($relationship,['parent','guardian','caregiver'],true))throw new RuntimeException('Choose your relationship to this child.');
        $initials=mb_strtoupper(mb_substr($first,0,1).mb_substr($last,0,1));
        $db->beginTransaction();
        try{
            $insert=$db->prepare("INSERT INTO users (first_name,last_name,email,password_hash,account_status,initials,display_role,is_demo_current_user,active) VALUES (:first,:last,NULL,NULL,'managed',:initials,'Student',0,1)");
            $insert->execute(['first'=>$first,'last'=>$last,'initials'=>$initials]);$childId=(int)$db->lastInsertId();
            $role=$db->prepare("INSERT IGNORE INTO auth_user_roles (user_id,role_id,assigned_by_user_id) SELECT :user,id,:actor FROM auth_roles WHERE code='student' AND active=1 LIMIT 1");
            $role->execute(['user'=>$childId,'actor'=>(int)$guardian['id']]);
            $relation=$db->prepare("INSERT INTO family_relationships (guardian_user_id,student_user_id,relationship_type,is_primary,status,created_by_user_id) VALUES (:guardian,:student,:relationship,1,'active',:creator)");
            $relation->execute(['guardian'=>(int)$guardian['id'],'student'=>$childId,'relationship'=>$relationship,'creator'=>(int)$guardian['id']]);
            $db->prepare('UPDATE users SET onboarding_completed_at=COALESCE(onboarding_completed_at,CURRENT_TIMESTAMP) WHERE id=:id')->execute(['id'=>(int)$guardian['id']]);
            $audit=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,'family.child_created','user',:student,'Created a guardian-managed student profile.',:meta)");
            $audit->execute(['actor'=>(int)$guardian['id'],'student'=>$childId,'meta'=>json_encode(['relationship_type'=>$relationship],JSON_THROW_ON_ERROR)]);
            $db->commit();return $childId;
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw new RuntimeException('We could not add that child profile. Please try again.');}
    }

    public static function completeOnboarding(PDO $db,int $userId): void
    {
        $db->prepare('UPDATE users SET onboarding_completed_at=COALESCE(onboarding_completed_at,CURRENT_TIMESTAMP) WHERE id=:id')->execute(['id'=>$userId]);
    }

    private static function queueVerification(PDO $db,int $userId,string $basePath): void
    {
        $stmt=$db->prepare("SELECT id,first_name,last_name,email FROM users WHERE id=:id AND active=1 AND account_status='pending_verification' LIMIT 1");$stmt->execute(['id'=>$userId]);$user=$stmt->fetch();if(!$user)return;
        $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
        $db->prepare('UPDATE auth_email_verifications SET verified_at=COALESCE(verified_at,CURRENT_TIMESTAMP) WHERE user_id=:user AND verified_at IS NULL')->execute(['user'=>$userId]);
        $db->prepare("INSERT INTO auth_email_verifications (user_id,token_hash,expires_at) VALUES (:user,:hash,DATE_ADD(NOW(),INTERVAL 24 HOUR))")->execute(['user'=>$userId,'hash'=>$hash]);
        $link=self::absoluteUrl('/verify-email?token='.$token,$basePath);$name=trim($user['first_name'].' '.$user['last_name']);
        $body="Hi {$user['first_name']},\n\nWelcome to CTSMD Connect. Verify your email within 24 hours to finish creating your account:\n{$link}\n\nAfter verification, you can set up your household and add your child profiles. Production access is assigned separately by CTSMD staff.\n\n— Children’s Theatre of Southern Maryland";
        MailService::queue($db,$userId,(string)$user['email'],$name,'account_security','Verify your CTSMD Connect account',$body,null,'platform-verification-'.$hash);
        if(Auth::localIdentitySwitchEnabled())$_SESSION['platform_local_verification_link']=$link;
    }

    private static function absoluteUrl(string $path,string $basePath): string
    {
        $configured=rtrim((string)(getenv('APP_URL')?:''),'/');if($configured!==''){if($basePath!==''&&str_ends_with(strtolower($configured),strtolower($basePath)))return $configured.$path;return $configured.($basePath?:'').$path;}$scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';$host=(string)($_SERVER['HTTP_HOST']??'localhost');return $scheme.'://'.$host.($basePath?:'').$path;
    }
}
