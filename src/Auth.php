<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class Auth
{
    public const SESSION_USER_ID = 'auth_user_id';

    public static function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
            session_start();
        }
    }

    public static function userId(): ?int{self::startSession();$id=(int)($_SESSION[self::SESSION_USER_ID]??0);return $id>0?$id:null;}
    public static function check(): bool{return self::userId()!==null;}

    public static function currentUser(PDO $db): ?array
    {
        $userId=self::userId();if(!$userId)return null;$local=!empty($_SESSION['auth_local_identity'])&&self::localIdentitySwitchEnabled();
        $sql="SELECT id,first_name,last_name,email,initials,display_role AS role,active,account_status,organization_membership_status,organization_membership_reviewed_at,last_login_at FROM users WHERE id=:id AND active=1".($local?'':" AND account_status='active'")." LIMIT 1";
        $stmt=$db->prepare($sql);$stmt->execute(['id'=>$userId]);$user=$stmt->fetch();if(!$user){self::logout();return null;}
        $user['name']=trim((string)$user['first_name'].' '.(string)$user['last_name']);$user['roles']=self::roles($db,$userId);$user['permissions']=self::permissions($db,$userId);return $user;
    }

    public static function login(PDO $db,string $email,string $password): array
    {
        $email=mb_strtolower(trim($email));if($email===''||$password==='')throw new RuntimeException('Enter your email and password.');$stmt=$db->prepare("SELECT id,password_hash,active,account_status FROM users WHERE LOWER(email)=:email LIMIT 1");$stmt->execute(['email'=>$email]);$row=$stmt->fetch();if(!$row||!(bool)$row['active']||$row['account_status']!=='active'||empty($row['password_hash'])||!password_verify($password,(string)$row['password_hash']))throw new RuntimeException('Email or password was not recognized.');self::establishSession($db,(int)$row['id']);$user=self::currentUser($db);if(!$user)throw new RuntimeException('This account is unavailable.');return $user;
    }

    public static function establishSession(PDO $db,int $userId): void
    {
        $stmt=$db->prepare("SELECT id FROM users WHERE id=:id AND active=1 AND account_status='active' LIMIT 1");$stmt->execute(['id'=>$userId]);if(!$stmt->fetchColumn())throw new RuntimeException('This account is unavailable.');self::startSession();session_regenerate_id(true);$_SESSION[self::SESSION_USER_ID]=$userId;$_SESSION['auth_authenticated_at']=time();unset($_SESSION['auth_local_identity']);$db->prepare('UPDATE users SET last_login_at=CURRENT_TIMESTAMP WHERE id=:id')->execute(['id'=>$userId]);
    }

    public static function loginAsLocalUser(PDO $db,int $userId): void
    {
        if(!self::localIdentitySwitchEnabled())throw new RuntimeException('Local identity switching is disabled.');$stmt=$db->prepare('SELECT id FROM users WHERE id=:id AND active=1 LIMIT 1');$stmt->execute(['id'=>$userId]);if(!$stmt->fetchColumn())throw new RuntimeException('That local test identity is unavailable.');self::startSession();session_regenerate_id(true);$_SESSION[self::SESSION_USER_ID]=$userId;$_SESSION['auth_local_identity']=true;
    }

    public static function logout(): void{self::startSession();unset($_SESSION[self::SESSION_USER_ID],$_SESSION['auth_authenticated_at'],$_SESSION['auth_local_identity']);session_regenerate_id(true);}
    public static function hasPermission(array $user,string $permission): bool{return in_array($permission,(array)($user['permissions']??[]),true);}
    public static function hasRole(array $user,string $role): bool{return in_array($role,(array)($user['roles']??[]),true);}
    public static function isApprovedMember(array $user): bool{return ($user['organization_membership_status']??null)==='approved'||self::hasRole($user,'production_staff')||self::hasRole($user,'administrator');}

    public static function roles(PDO $db,int $userId): array{$s=$db->prepare("SELECT r.code FROM auth_user_roles ur JOIN auth_roles r ON r.id=ur.role_id WHERE ur.user_id=:user AND r.active=1 ORDER BY r.code");$s->execute(['user'=>$userId]);return array_values($s->fetchAll(PDO::FETCH_COLUMN));}
    public static function permissions(PDO $db,int $userId): array{$s=$db->prepare("SELECT DISTINCT p.code FROM auth_user_roles ur JOIN auth_roles r ON r.id=ur.role_id AND r.active=1 JOIN auth_role_permissions rp ON rp.role_id=r.id JOIN auth_permissions p ON p.id=rp.permission_id WHERE ur.user_id=:user ORDER BY p.code");$s->execute(['user'=>$userId]);return array_values($s->fetchAll(PDO::FETCH_COLUMN));}

    public static function createInvitation(PDO $db,int $userId,?int $creatorId=null,int $hours=168): string
    {
        $stmt=$db->prepare('SELECT id,email,active,account_status FROM users WHERE id=:id LIMIT 1');$stmt->execute(['id'=>$userId]);$user=$stmt->fetch();if(!$user||!(bool)$user['active']||$user['account_status']==='disabled'||empty($user['email']))throw new RuntimeException('That person needs an available account with an email address before invitation.');$token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);$expires=(new DateTimeImmutable('+'.max(1,$hours).' hours'))->format('Y-m-d H:i:s');$db->prepare('UPDATE auth_invitations SET accepted_at=COALESCE(accepted_at,CURRENT_TIMESTAMP) WHERE user_id=:user AND accepted_at IS NULL')->execute(['user'=>$userId]);$db->prepare('INSERT INTO auth_invitations (user_id,token_hash,expires_at,created_by_user_id) VALUES (:user,:hash,:expires,:creator)')->execute(['user'=>$userId,'hash'=>$hash,'expires'=>$expires,'creator'=>$creatorId]);$db->prepare("UPDATE users SET account_status='invited' WHERE id=:id AND password_hash IS NULL")->execute(['id'=>$userId]);return $token;
    }

    public static function invitation(PDO $db,string $token): ?array
    {
        if(!preg_match('/^[a-f0-9]{64}$/',$token))return null;$hash=hash('sha256',$token);$s=$db->prepare("SELECT i.id,i.user_id,i.expires_at,u.first_name,u.last_name,u.email FROM auth_invitations i JOIN users u ON u.id=i.user_id AND u.active=1 WHERE i.token_hash=:hash AND i.accepted_at IS NULL AND i.expires_at>NOW() AND u.account_status IN ('invited','active') LIMIT 1");$s->execute(['hash'=>$hash]);return $s->fetch()?:null;
    }

    public static function acceptInvitation(PDO $db,string $token,string $password): int
    {
        self::assertPassword($password);$inv=self::invitation($db,$token);if(!$inv)throw new RuntimeException('That invitation is invalid or has expired.');$hash=password_hash($password,PASSWORD_DEFAULT);$db->beginTransaction();try{$db->prepare("UPDATE users SET password_hash=:password,account_status='active',password_changed_at=CURRENT_TIMESTAMP WHERE id=:id AND active=1 AND account_status IN ('invited','active')")->execute(['password'=>$hash,'id'=>(int)$inv['user_id']]);$db->prepare('UPDATE auth_invitations SET accepted_at=CURRENT_TIMESTAMP WHERE id=:id')->execute(['id'=>(int)$inv['id']]);$db->commit();return (int)$inv['user_id'];}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }

    public static function createPasswordReset(PDO $db,string $email): ?string
    {
        $s=$db->prepare("SELECT id FROM users WHERE LOWER(email)=:email AND active=1 AND account_status='active' LIMIT 1");$s->execute(['email'=>mb_strtolower(trim($email))]);$id=(int)($s->fetchColumn()?:0);if(!$id)return null;$token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);$db->prepare('UPDATE auth_password_resets SET used_at=COALESCE(used_at,CURRENT_TIMESTAMP) WHERE user_id=:user AND used_at IS NULL')->execute(['user'=>$id]);$db->prepare("INSERT INTO auth_password_resets (user_id,token_hash,expires_at) VALUES (:user,:hash,DATE_ADD(NOW(),INTERVAL 2 HOUR))")->execute(['user'=>$id,'hash'=>$hash]);return $token;
    }

    public static function resetPassword(PDO $db,string $token,string $password): void
    {
        self::assertPassword($password);if(!preg_match('/^[a-f0-9]{64}$/',$token))throw new RuntimeException('That reset link is invalid or expired.');$hash=hash('sha256',$token);$db->beginTransaction();try{$s=$db->prepare("SELECT pr.id,pr.user_id FROM auth_password_resets pr JOIN users u ON u.id=pr.user_id AND u.active=1 AND u.account_status='active' WHERE pr.token_hash=:hash AND pr.used_at IS NULL AND pr.expires_at>NOW() FOR UPDATE");$s->execute(['hash'=>$hash]);$row=$s->fetch();if(!$row)throw new RuntimeException('That reset link is invalid or expired.');$db->prepare("UPDATE users SET password_hash=:password,password_changed_at=CURRENT_TIMESTAMP WHERE id=:user AND active=1 AND account_status='active'")->execute(['password'=>password_hash($password,PASSWORD_DEFAULT),'user'=>(int)$row['user_id']]);$db->prepare('UPDATE auth_password_resets SET used_at=CURRENT_TIMESTAMP WHERE id=:id')->execute(['id'=>(int)$row['id']]);$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }

    public static function localIdentitySwitchEnabled(): bool
    {
        $environment=strtolower((string)(getenv('APP_ENV')?:($_ENV['APP_ENV']??'production')));
        return $environment==='local';
    }

    private static function assertPassword(string $password): void{if(strlen($password)<12)throw new RuntimeException('Use a password with at least 12 characters.');}
}