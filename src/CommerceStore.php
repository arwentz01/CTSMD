<?php
declare(strict_types=1);
require_once __DIR__.'/CommerceAccess.php';
final class CommerceStore
{
    public static function row(PDO $db,string $table,int $org,int $id,bool $lock=false):array
    {
        if(!in_array($table,['venues','spaces','layouts','performances','orders','payment_attempts','tickets'],true))throw new LogicException('Unknown Commerce store.');
        $q=$db->prepare('SELECT * FROM commerce_'.$table.' WHERE organization_id=? AND id=?'.($lock?' FOR UPDATE':''));$q->execute([$org,$id]);
        return$q->fetch()?:throw new RuntimeException('Commerce record unavailable for this organization.');
    }
    public static function audit(PDO $db,int $org,int $actor,string $action,string $type,int $id,array $details=[]):void
    {$db->prepare('INSERT INTO commerce_audit(organization_id,actor_user_id,action,entity_type,entity_id,detail_json) VALUES(?,?,?,?,?,?)')->execute([$org,$actor,$action,$type,$id,json_encode($details,JSON_THROW_ON_ERROR)]);}
    public static function tx(PDO $db,callable $work):mixed
    {
        $db->beginTransaction();try{$value=$work();$db->commit();return$value;}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
    public static function organization(PDO $db,int $org,bool $ticketing=false):array
    {
        $q=$db->prepare('SELECT * FROM commerce_organizations WHERE id=?');$q->execute([$org]);$r=$q->fetch();if(!$r||($ticketing&&!$r['ticketing_enabled']))throw new RuntimeException('Ticketing is disabled for this organization.');return$r;
    }
    public static function configure(PDO $db,array $user,int $org,array $input):void
    {
        CommerceAccess::demand($db,$user,$org,'configure');$seconds=(int)($input['hold_seconds']??600);if($seconds<60||$seconds>1800)throw new RuntimeException('Choose a hold duration between 60 and 1800 seconds.');
        self::tx($db,function()use($db,$user,$org,$input,$seconds){$db->prepare('UPDATE commerce_organizations SET ticketing_enabled=?,merchandise_enabled=?,education_enabled=?,hold_seconds=? WHERE id=?')->execute([!empty($input['ticketing_enabled'])?1:0,!empty($input['merchandise_enabled'])?1:0,!empty($input['education_enabled'])?1:0,$seconds,$org]);self::audit($db,$org,(int)$user['id'],'commerce.configured','organization',$org,['hold_seconds'=>$seconds]);});
    }
    public static function grant(PDO $db,array $user,int $org,int $recipient,string $role,bool $enabled):void
    {
        CommerceAccess::demand($db,$user,$org,'configure');if(!isset(CommerceAccess::ROLES[$role]))throw new RuntimeException('Choose a Commerce role.');
        $q=$db->prepare("SELECT 1 FROM users u WHERE u.id=? AND u.active=1 AND u.account_status='active' AND NOT EXISTS(SELECT 1 FROM auth_user_roles ur JOIN auth_roles r ON r.id=ur.role_id WHERE ur.user_id=u.id AND r.code='student')");$q->execute([$recipient]);if(!$q->fetchColumn())throw new RuntimeException('Choose an active adult testing or staff account.');
        self::tx($db,function()use($db,$org,$user,$recipient,$role,$enabled){$q=$enabled?$db->prepare('INSERT IGNORE INTO commerce_grants(organization_id,user_id,role_code) VALUES(?,?,?)'):$db->prepare('DELETE FROM commerce_grants WHERE organization_id=? AND user_id=? AND role_code=?');$q->execute([$org,$recipient,$role]);self::audit($db,$org,(int)$user['id'],'commerce.grant_changed','user',$recipient,['role'=>$role,'enabled'=>$enabled]);});
    }
}
