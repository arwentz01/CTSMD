<?php
declare(strict_types=1);
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/AccessPolicy.php';
require_once __DIR__.'/PersonEligibility.php';
require_once __DIR__.'/MailService.php';

/**
 * General membership invitations are for adults only.
 * Neither staff privileges nor production membership are issued here.
 */
final class AdultMembershipInvitationService
{
    public static function issue(PDO $db,array $actor,string $email,string $basePath):int
    {
        if(!AccessPolicy::canManageAccounts($actor))throw new RuntimeException('Account management permission is required.');
        $email=mb_strtolower(trim($email));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($email)>254)throw new RuntimeException('Enter a valid email address.');
        $existing=$db->prepare("SELECT u.id,u.active,u.account_status,u.password_hash,
            EXISTS(SELECT 1 FROM auth_invitations i WHERE i.user_id=u.id AND i.purpose='general_membership') was_adult_invited
            FROM users u WHERE LOWER(u.email)=:email LIMIT 1");
        $existing->execute(['email'=>$email]);
        $prior=$existing->fetch(PDO::FETCH_ASSOC)?:null;
        $revoked=$prior && !(int)$prior['active'] && $prior['account_status']==='disabled'
            && empty($prior['password_hash']) && (int)$prior['was_adult_invited']===1;
        if($prior&&!$revoked)throw new RuntimeException('An account already uses that email. Review the existing account instead of sending a duplicate invitation.');
        $db->beginTransaction();
        try{
            if($revoked){
                $id=(int)$prior['id'];
                if(Auth::roles($db,$id)!==['member'])throw new RuntimeException('The revoked account has additional roles and cannot be reinvited automatically.');
                $db->prepare("UPDATE users SET account_status='invited',active=1,organization_membership_status='pending' WHERE id=:id AND active=0 AND account_status='disabled'")->execute(['id'=>$id]);
            }else{
                // Names and contact details are completed by the recipient, not guessed by staff.
                $insert=$db->prepare("INSERT INTO users (first_name,last_name,email,password_hash,account_status,organization_membership_status,initials,display_role,is_demo_current_user,active) VALUES ('Invited','Member',:email,NULL,'invited','pending','IM','Member',0,1)");
                $insert->execute(['email'=>$email]);
                $id=(int)$db->lastInsertId();
                $role=$db->prepare("INSERT INTO auth_user_roles (user_id,role_id,assigned_by_user_id) SELECT :id,id,:actor FROM auth_roles WHERE code='member' AND active=1 LIMIT 1");
                $role->execute(['id'=>$id,'actor'=>(int)$actor['id']]);
                if($role->rowCount()!==1)throw new RuntimeException('General Membership role is not configured.');
            }
            $token=Auth::createInvitation($db,$id,(int)$actor['id'],168,'general_membership');
            $link=self::absoluteUrl($basePath.'/activate?token='.$token);
            $message="Hello,\n\nYou've been invited by CTSMD to join CTSMD Connect as a general member. Use the secure link below to complete your profile, confirm your email address, and create a password:\n".$link."\n\nOnce you finish, your General Membership becomes active. Production assignments and additional staff permissions are granted separately by CTSMD.\n\nThis invitation expires in seven days. If you weren't expecting it, simply ignore this email.\n\nChildren's Theatre of Southern Maryland";
            MailService::queue($db,$id,$email,null,'account_security','Complete your CTSMD Connect invitation',$message,null,'adult-general-invite-'.hash('sha256',$token));
            $audit=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,'account.general_member_invited','user',:id,'Invited an adult to join with General Membership.',NULL)");
            $audit->execute(['actor'=>(int)$actor['id'],'id'=>$id]);
            $db->commit();
            return $id;
        }catch(Throwable $error){
            if($db->inTransaction())$db->rollBack();
            throw $error;
        }
    }

    public static function accept(PDO $db,string $token,array $input):int
    {
        $inv=Auth::invitation($db,$token);
        if(!$inv||$inv['purpose']!=='general_membership'||$inv['account_status']!=='invited')throw new RuntimeException('This invitation is invalid, expired, or already used.');
        $first=trim((string)($input['first_name']??''));
        $last=trim((string)($input['last_name']??''));
        if($first===''||$last===''||mb_strlen($first)>100||mb_strlen($last)>100)throw new RuntimeException('Enter your first and last name.');
        $dob=PersonEligibility::birthDate((string)($input['date_of_birth']??''));
        $today=(new DateTimeImmutable('today'))->format('Y-m-d');
        if(PersonEligibility::ageOn($dob,$today)<18)throw new RuntimeException('This invitation is for adults. A parent or legal guardian must create a student profile.');
        $phone=trim((string)($input['contact_phone']??''));
        $digits=preg_replace('/\D+/','',$phone);
        if(strlen($digits)!==10)throw new RuntimeException('Enter a 10-digit contact phone number.');
        $phone='('.substr($digits,0,3).') '.substr($digits,3,3).'-'.substr($digits,6);
        $password=(string)($input['password']??'');
        if(strlen($password)<Auth::PASSWORD_MIN_LENGTH)throw new RuntimeException('Use a password with at least '.Auth::PASSWORD_MIN_LENGTH.' characters.');
        if(!hash_equals($password,(string)($input['password_confirm']??'')))throw new RuntimeException('Passwords do not match.');
        $db->beginTransaction();
        try{
            $q=$db->prepare("SELECT i.id,u.id user_id FROM auth_invitations i
              JOIN users u ON u.id=i.user_id AND u.active=1 AND u.account_status='invited'
              WHERE i.token_hash=:token AND i.purpose='general_membership' AND i.accepted_at IS NULL AND i.expires_at>NOW()
              LIMIT 1 FOR UPDATE");
            $q->execute(['token'=>hash('sha256',$token)]);
            $valid=$q->fetch(PDO::FETCH_ASSOC);
            if(!$valid)throw new RuntimeException('This invitation is invalid, expired, or already used.');
            $id=(int)$valid['user_id'];
            $roles=Auth::roles($db,$id);
            if(in_array('student',$roles,true)||in_array('administrator',$roles,true)||in_array('production_staff',$roles,true)||count($roles)!==1||$roles[0]!=='member')throw new RuntimeException('This invitation has an unexpected access configuration. Contact CTSMD.');
            $nameInitials=mb_strtoupper(mb_substr($first,0,1).mb_substr($last,0,1));
            $update=$db->prepare("UPDATE users SET first_name=:first,last_name=:last,initials=:initials,date_of_birth=:dob,contact_phone=:phone,
                password_hash=:hash,password_changed_at=CURRENT_TIMESTAMP,email_verified_at=CURRENT_TIMESTAMP,account_status='active',
                organization_membership_status='approved',organization_membership_reviewed_at=CURRENT_TIMESTAMP
                WHERE id=:id AND account_status='invited' AND active=1");
            $update->execute(['first'=>$first,'last'=>$last,'initials'=>$nameInitials,'dob'=>$dob,'phone'=>$phone,'hash'=>password_hash($password,PASSWORD_DEFAULT),'id'=>$id]);
            if($update->rowCount()!==1)throw new RuntimeException('This invitation could not be activated.');
            $done=$db->prepare('UPDATE auth_invitations SET accepted_at=CURRENT_TIMESTAMP WHERE id=:id AND accepted_at IS NULL');
            $done->execute(['id'=>(int)$valid['id']]);
            if($done->rowCount()!==1)throw new RuntimeException('This invitation was already accepted.');
            $audit=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,'account.general_member_activated','user',:id,'Accepted an adult general membership invitation.',NULL)");
            $audit->execute(['actor'=>$id,'id'=>$id]);
            $db->commit();
            return $id;
        }catch(Throwable $error){
            if($db->inTransaction())$db->rollBack();
            throw $error;
        }
    }

    public static function resend(PDO $db,array $actor,int $userId,string $basePath):void
    {
        if(!AccessPolicy::canManageAccounts($actor))throw new RuntimeException('Account management permission is required.');
        $q=$db->prepare("SELECT u.email,u.id FROM users u JOIN auth_invitations i ON i.user_id=u.id AND i.purpose='general_membership'
          WHERE u.id=:id AND u.active=1 AND u.account_status='invited' AND i.accepted_at IS NULL
          ORDER BY i.id DESC LIMIT 1");
        $q->execute(['id'=>$userId]);
        $recipient=$q->fetch(PDO::FETCH_ASSOC);
        if(!$recipient)throw new RuntimeException('An unaccepted adult General Membership invitation was not found.');
        $db->beginTransaction();
        try{
            $token=Auth::createInvitation($db,$userId,(int)$actor['id'],168,'general_membership');
            $link=self::absoluteUrl($basePath.'/activate?token='.$token);
            $message="Hello,\n\nCTSMD sent you a new secure invitation to join CTSMD Connect. Complete your profile and create a password using this link:\n".$link."\n\nWhen you accept, your General Membership becomes active. Staff roles and production assignments are managed separately.\n\nThe link expires in seven days. If you weren't expecting this message, you can ignore it.\n\nChildren's Theatre of Southern Maryland";
            MailService::queue($db,$userId,(string)$recipient['email'],null,'account_security','Complete your CTSMD Connect invitation',$message,null,'adult-general-invite-'.hash('sha256',$token));
            $db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,'account.general_member_invitation_resent','user',:id,'Resent adult General Membership invitation.',NULL)")->execute(['actor'=>(int)$actor['id'],'id'=>$userId]);
            $db->commit();
        }catch(Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
    }

    public static function pending(PDO $db):array
    {
        return $db->query("SELECT i.user_id,u.email,i.created_at,i.expires_at,
            CASE WHEN i.expires_at<=NOW() THEN 'expired' ELSE 'pending' END status
            FROM auth_invitations i JOIN users u ON u.id=i.user_id AND u.active=1 AND u.account_status='invited'
            WHERE i.purpose='general_membership' AND i.accepted_at IS NULL
              AND i.id=(SELECT MAX(j.id) FROM auth_invitations j WHERE j.user_id=i.user_id AND j.purpose='general_membership')
            ORDER BY i.created_at DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function revoke(PDO $db,array $actor,int $userId):void
    {
        if(!AccessPolicy::canManageAccounts($actor))throw new RuntimeException('Account management permission is required.');
        $q=$db->prepare("SELECT u.id FROM users u WHERE u.id=:id AND u.account_status='invited' AND u.active=1
            AND EXISTS(SELECT 1 FROM auth_invitations i WHERE i.user_id=u.id AND i.purpose='general_membership' AND i.accepted_at IS NULL) LIMIT 1");
        $q->execute(['id'=>$userId]);
        if(!$q->fetchColumn())throw new RuntimeException('Only pending invitations can be revoked.');
        $db->beginTransaction();
        try{
            $db->prepare("UPDATE auth_invitations SET accepted_at=CURRENT_TIMESTAMP WHERE user_id=:id AND purpose='general_membership' AND accepted_at IS NULL")->execute(['id'=>$userId]);
            $db->prepare("UPDATE users SET account_status='disabled',active=0 WHERE id=:id AND account_status='invited'")->execute(['id'=>$userId]);
            $db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,'account.general_member_invitation_revoked','user',:id,'Revoked an unused general member invitation.',NULL)")->execute(['actor'=>(int)$actor['id'],'id'=>$userId]);
            $db->commit();
        }catch(Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
    }

    private static function absoluteUrl(string $path):string
    {
        $app=rtrim((string)(getenv('APP_URL')?:''),'/');
        if($app!=='')return $app.$path;
        $scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
        return $scheme.'://'.($_SERVER['HTTP_HOST']??'localhost').$path;
    }
}
