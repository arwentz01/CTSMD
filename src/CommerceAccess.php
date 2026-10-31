<?php
declare(strict_types=1);
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/OrganizationBranding.php';
/** Commerce ownership does not claim tenant isolation for the rest of Connect. */
final class CommerceAccess
{
    public const ROLES=[
        'administrator'=>['configure','venues','inventory','sell','checkin','reverse','refund','report','test'],
        'box_office'=>['venues','inventory','sell','checkin','reverse','refund','report','test'],
        'seller'=>['sell','test'], 'door'=>['checkin'], 'reporter'=>['report'], 'tester'=>['test'],
    ];
    public static function gate():bool
    {
        return getenv('COMMERCE_SANDBOX_ENABLED')==='1' && in_array(getenv('APP_ENV'),['local','rc1-test'],true);
    }
    public static function navigation(PDO $db,array $user):bool
    {
        if(!self::gate())return false;
        try{$owner=self::primary($db,$user);if(!$owner)return self::canBootstrap($db,$user);foreach(self::ROLES['administrator'] as $p)if(self::can($db,$user,(int)$owner['id'],$p))return true;}catch(PDOException $e){return false;}return false;
    }
    public static function primaryKey():string{return (string)(getenv('COMMERCE_ORGANIZATION_KEY')?:'primary');}
    private static function activeAdult(PDO $db,array $user):bool
    {
        $q=$db->prepare("SELECT 1 FROM users u WHERE u.id=? AND u.active=1 AND u.account_status='active' AND u.date_of_birth IS NOT NULL AND u.date_of_birth<=DATE_SUB(CURDATE(),INTERVAL 18 YEAR) AND NOT EXISTS(SELECT 1 FROM auth_user_roles ur JOIN auth_roles r ON r.id=ur.role_id WHERE ur.user_id=u.id AND r.code='student')");$q->execute([(int)($user['id']??0)]);return(bool)$q->fetchColumn();
    }
    public static function can(PDO $db,array $user,int $org,string $permission):bool
    {
        if(!self::gate()||empty($user['id'])||!in_array($permission,self::ROLES['administrator'],true))return false;
        if(!self::activeAdult($db,$user))return false;
        $q=$db->prepare('SELECT organization_key FROM commerce_organizations WHERE id=?');$q->execute([$org]);$key=$q->fetchColumn();if(!$key)return false;
        // Existing app administrators bootstrap only this installation's configured owner.
        if($key===self::primaryKey()&&Auth::hasRole($user,'administrator'))return true;
        $q=$db->prepare('SELECT role_code FROM commerce_grants WHERE organization_id=? AND user_id=?');$q->execute([$org,(int)$user['id']]);
        foreach($q->fetchAll(PDO::FETCH_COLUMN) as $role)if(in_array($permission,self::ROLES[$role]??[],true))return true;
        return false;
    }
    public static function demand(PDO $db,array $user,int $org,string $permission):void
    {if(!self::can($db,$user,$org,$permission))throw new RuntimeException('Commerce access for this organization is required.');}
    public static function primary(PDO $db,array $user):?array
    {
        if(!self::gate())return null;
        $q=$db->prepare('SELECT * FROM commerce_organizations WHERE organization_key=?');$q->execute([self::primaryKey()]);return$q->fetch()?:null;
    }
    public static function canBootstrap(PDO $db,array $user):bool{return self::gate()&&self::activeAdult($db,$user)&&Auth::hasRole($user,'administrator');}
    public static function bootstrap(PDO $db,array $user):int
    {
        if(!self::canBootstrap($db,$user))throw new RuntimeException('An active adult application administrator with a recorded birthdate is required to initialize the local sandbox.');
        $q=$db->prepare('INSERT IGNORE INTO commerce_organizations(organization_key,name) VALUES(?,?)');$q->execute([self::primaryKey(),OrganizationBranding::fromEnvironment()['name']]);
        return(int)self::primary($db,$user)['id'];
    }
}
