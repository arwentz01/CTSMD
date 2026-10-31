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
    public static function primaryKey():string{return (string)(getenv('COMMERCE_ORGANIZATION_KEY')?:'primary');}
    public static function can(PDO $db,array $user,int $org,string $permission):bool
    {
        if(!self::gate()||empty($user['id'])||!in_array($permission,self::ROLES['administrator'],true))return false;
        $q=$db->prepare("SELECT 1 FROM users WHERE id=? AND active=1 AND account_status='active'");$q->execute([(int)$user['id']]);if(!$q->fetchColumn())return false;
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
    public static function bootstrap(PDO $db,array $user):int
    {
        if(!self::gate()||!Auth::hasRole($user,'administrator'))throw new RuntimeException('Only an application administrator can initialize the local sandbox.');
        $q=$db->prepare('INSERT IGNORE INTO commerce_organizations(organization_key,name) VALUES(?,?)');$q->execute([self::primaryKey(),OrganizationBranding::fromEnvironment()['name']]);
        return(int)self::primary($db,$user)['id'];
    }
}
