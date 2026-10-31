<?php
declare(strict_types=1);
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/OrganizationBranding.php';
/** Commerce ownership does not claim tenant isolation for the rest of Connect. */
final class CommerceAccess
{
    public const ROLES=[
        'administrator'=>['configure','venues','inventory','sell','checkin','reverse','refund','report','test','complimentary','discount'],
        'box_office'=>['venues','inventory','sell','checkin','reverse','refund','report','test','complimentary','discount'],
        'seller'=>['sell','test'], 'door'=>['checkin'], 'reporter'=>['report'], 'tester'=>['test'], 'storefront_tester'=>['test'],
    ];
    public static function local():bool
    {
        if(!in_array(getenv('APP_ENV'),['local','rc1-test'],true))return false;
        if(PHP_SAPI==='cli')return true;
        $host=strtolower((string)(parse_url('http://'.($_SERVER['HTTP_HOST']??'localhost'),PHP_URL_HOST)?:''));
        return in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)&&in_array($host,['localhost','127.0.0.1','[::1]'],true);
    }
    public static function gate():bool
    {
        // Hosted preview is administrator-only below; this never enables public sales.
        return self::local()?getenv('COMMERCE_SANDBOX_ENABLED')==='1':getenv('COMMERCE_HOSTED_SANDBOX_ENABLED')!=='0';
    }
    private static function hostedAdministrator(PDO $db,array $user):bool
    {
        if(!Auth::hasRole($user,'administrator'))return false;
        $q=$db->prepare("SELECT 1 FROM users u WHERE u.id=? AND u.active=1 AND u.account_status='active' AND NOT EXISTS(SELECT 1 FROM auth_user_roles ur JOIN auth_roles r ON r.id=ur.role_id WHERE ur.user_id=u.id AND r.code='student')");$q->execute([(int)($user['id']??0)]);return(bool)$q->fetchColumn();
    }
    public static function navigation(PDO $db,array $user):bool
    {
        if(!self::local()&&self::hostedAdministrator($db,$user))return true;
        if(!self::gate())return false;
        try{$owner=self::primary($db,$user);if(!$owner)return self::canBootstrap($db,$user);foreach(self::ROLES['administrator'] as $p){if(!self::local()&&$p==='test')continue;if(self::can($db,$user,(int)$owner['id'],$p))return true;}}catch(PDOException $e){return false;}return false;
    }
    public static function schemaReady(PDO $db):bool
    {
        $tables=['commerce_organizations','commerce_grants','commerce_venues','commerce_spaces','commerce_layouts','commerce_performances','commerce_orders','commerce_inventory','commerce_order_lines','commerce_payment_attempts','commerce_payment_events','commerce_tickets','commerce_audit','commerce_simulated_results','commerce_box_office_sales','commerce_storefront_performances','commerce_order_links','commerce_showtime_slots','commerce_schedule_batches','commerce_allocation_batches'];
        $q=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('.implode(',',array_fill(0,count($tables),'?')).')');$q->execute($tables);if((int)$q->fetchColumn()!==count($tables))return false;
        $columns=$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='commerce_performances' AND COLUMN_NAME='seating_mode') OR (TABLE_NAME='commerce_orders' AND COLUMN_NAME='tax_basis_points_snapshot') OR (TABLE_NAME='commerce_performances' AND COLUMN_NAME IN ('starts_at_utc','timezone_name','utc_offset_minutes','schedule_status')) OR (TABLE_NAME='commerce_venues' AND COLUMN_NAME='timezone_name'))");if((int)$columns->fetchColumn()!==7)return false;
        $names=['commerce_layout_immutable_update','commerce_layout_immutable_delete','commerce_performance_layout_immutable'];$q=$db->prepare('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME IN (?,?,?)');$q->execute($names);return(int)$q->fetchColumn()===count($names);
    }
    public static function primaryKey():string{return (string)(getenv('COMMERCE_ORGANIZATION_KEY')?:'primary');}
    private static function activeAdult(PDO $db,array $user):bool
    {
        $q=$db->prepare("SELECT 1 FROM users u WHERE u.id=? AND u.active=1 AND u.account_status='active' AND u.date_of_birth IS NOT NULL AND u.date_of_birth<=DATE_SUB(CURDATE(),INTERVAL 18 YEAR) AND NOT EXISTS(SELECT 1 FROM auth_user_roles ur JOIN auth_roles r ON r.id=ur.role_id WHERE ur.user_id=u.id AND r.code='student')");$q->execute([(int)($user['id']??0)]);return(bool)$q->fetchColumn();
    }
    public static function can(PDO $db,array $user,int $org,string $permission):bool
    {
        if(!self::gate()||empty($user['id'])||!in_array($permission,self::ROLES['administrator'],true))return false;
        if(self::local()?!self::activeAdult($db,$user):(!self::hostedAdministrator($db,$user)&&!self::activeAdult($db,$user)))return false;
        $q=$db->prepare('SELECT organization_key FROM commerce_organizations WHERE id=?');$q->execute([$org]);$key=$q->fetchColumn();if(!$key)return false;
        // Existing app administrators bootstrap only this installation's configured owner.
        if($key===self::primaryKey()&&Auth::hasRole($user,'administrator'))return true;
        $q=$db->prepare('SELECT role_code FROM commerce_grants WHERE organization_id=? AND user_id=?');$q->execute([$org,(int)$user['id']]);
        foreach($q->fetchAll(PDO::FETCH_COLUMN) as $role){if(!self::local()&&$role==='tester')continue;if(in_array($permission,self::ROLES[$role]??[],true))return true;}
        return false;
    }
    public static function demand(PDO $db,array $user,int $org,string $permission):void
    {if(!self::can($db,$user,$org,$permission))throw new RuntimeException('Commerce access for this organization is required.');}
    public static function primary(PDO $db,array $user):?array
    {
        if(!self::gate())return null;
        $q=$db->prepare('SELECT * FROM commerce_organizations WHERE organization_key=?');$q->execute([self::primaryKey()]);return$q->fetch()?:null;
    }
    public static function canBootstrap(PDO $db,array $user):bool{return self::gate()&&Auth::hasRole($user,'administrator')&&(self::local()?self::activeAdult($db,$user):self::hostedAdministrator($db,$user));}
    public static function bootstrap(PDO $db,array $user):int
    {
        if(!self::canBootstrap($db,$user))throw new RuntimeException('An eligible active application administrator is required to initialize the sandbox.');
        $q=$db->prepare('INSERT IGNORE INTO commerce_organizations(organization_key,name) VALUES(?,?)');$q->execute([self::primaryKey(),OrganizationBranding::fromEnvironment()['name']]);
        return(int)self::primary($db,$user)['id'];
    }
}
