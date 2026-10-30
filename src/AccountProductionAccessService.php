<?php
declare(strict_types=1);
require_once __DIR__.'/ProductionParticipantEligibility.php';
require_once __DIR__.'/ProductionContext.php';

/** Scoped Staff membership is edited with account authorization, never a team credit. */
final class AccountProductionAccessService
{
    public static function scopes(PDO $db,int $person):array
    {
        $q=$db->prepare("SELECT p.id,p.title,p.lifecycle_stage,COALESCE(pm.status,'inactive') status FROM productions p LEFT JOIN production_memberships pm ON pm.production_id=p.id AND pm.user_id=? AND pm.audience_type='staff' WHERE p.is_active=1 OR pm.id IS NOT NULL ORDER BY p.title,p.id");$q->execute([$person]);return $q->fetchAll();
    }
    public static function set(PDO $db,int $actor,int $person,int $production,bool $active):void
    {
        $db->beginTransaction();try{
            ProductionLifecycleGuard::assertWritable($db,$production,true);
            $q=$db->prepare('SELECT id,active,account_status FROM users WHERE id IN (?,?) ORDER BY id FOR UPDATE');$q->execute([$actor,$person]);$users=$q->fetchAll();$actorLive=false;$subjectLive=false;
            foreach($users as $u){if((int)$u['id']===$actor)$actorLive=(int)$u['active']&&$u['account_status']==='active';if((int)$u['id']===$person)$subjectLive=(int)$u['active']&&$u['account_status']==='active';}
            Auth::clearRequestCache($actor);Auth::clearRequestCache($person);
            if(!$actorLive||!in_array('accounts.manage',Auth::permissions($db,$actor),true))throw new RuntimeException('Current account management permission is required.');
            if($active&&(!$subjectLive||!IdentityRolePolicy::isStaff($db,$person)||IdentityRolePolicy::isStudent($db,$person)))throw new RuntimeException('Assign Production Staff or Administrator in Authorization roles before granting production Staff access.');
            $q=$db->prepare("SELECT id,status FROM production_memberships WHERE production_id=? AND user_id=? AND audience_type='staff' FOR UPDATE");$q->execute([$production,$person]);$before=$q->fetch();
            if($active)$db->prepare("INSERT INTO production_memberships(production_id,user_id,audience_type,participation_role,status) VALUES(?,?,'staff','Production Staff','active') ON DUPLICATE KEY UPDATE status='active',updated_at=NOW()")->execute([$production,$person]);
            elseif($before)$db->prepare("UPDATE production_memberships SET status='inactive',updated_at=NOW() WHERE id=?")->execute([$before['id']]);
            $db->prepare("INSERT INTO audit_events(actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES(?,'account.production_staff_access_updated','user',?,'Updated scoped production Staff authorization.',?)")->execute([$actor,$person,json_encode(['production_id'=>$production,'before'=>$before,'active'=>$active],JSON_THROW_ON_ERROR)]);
            $db->commit();ProductionContext::clearRequestCache();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
}
