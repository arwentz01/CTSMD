<?php

declare(strict_types=1);

require_once __DIR__.'/Auth.php';

final class IdentityRolePolicy
{
    public static function isStudent(PDO $db,int $userId):bool
    {
        return $userId>0 && in_array('student',Auth::roles($db,$userId),true);
    }

    public static function isStaff(PDO $db,int $userId):bool
    {
        if($userId<1)return false;$roles=Auth::roles($db,$userId);return in_array('production_staff',$roles,true)||in_array('administrator',$roles,true);
    }

    public static function assertFamilyPair(PDO $db,int $guardianId,int $studentId):void
    {
        if($guardianId<1||$studentId<1||$guardianId===$studentId)throw new RuntimeException('Choose a valid guardian and student.');
        $people=$db->prepare('SELECT id,active,account_status FROM users WHERE id IN (:guardian,:student)');$people->execute(['guardian'=>$guardianId,'student'=>$studentId]);$rows=$people->fetchAll();if(count($rows)!==2)throw new RuntimeException('One of those people could not be found.');foreach($rows as $row)if(!(bool)$row['active']||$row['account_status']==='disabled')throw new RuntimeException('Family relationships can only use available people. Restore a disabled account before creating a live relationship.');
        if(!self::isStudent($db,$studentId))throw new RuntimeException('The linked child must currently have the Student role.');
        if(self::isStudent($db,$guardianId))throw new RuntimeException('A Student cannot be assigned as the guardian in this relationship.');
    }

    public static function assertProductionAudience(PDO $db,int $userId,string $audienceType):void
    {
        if($userId<1||!in_array($audienceType,['student','guardian','staff'],true))throw new RuntimeException('Choose a valid person and production role.');
        $person=$db->prepare('SELECT active,account_status FROM users WHERE id=:id LIMIT 1');$person->execute(['id'=>$userId]);$row=$person->fetch();if(!$row||!(bool)$row['active']||$row['account_status']==='disabled')throw new RuntimeException('That person is not available for an active production roster.');
        if($audienceType==='student'&&!self::isStudent($db,$userId))throw new RuntimeException('Only an account with the Student role can be added as a production student.');
        if($audienceType==='staff'&&!self::isStaff($db,$userId))throw new RuntimeException('Only Production Staff or an Administrator can be added as production staff.');
        if($audienceType==='guardian'&&self::isStudent($db,$userId))throw new RuntimeException('A Student cannot be added as a production guardian.');
    }

    public static function assertRoleSelection(PDO $db,int $userId,array $roleIds):void
    {
        if($userId<1)throw new RuntimeException('Choose a valid account.');
        $ids=array_values(array_unique(array_filter(array_map('intval',$roleIds),static fn(int $id):bool=>$id>0)));
        $selected=[];
        if($ids){$ph=implode(',',array_fill(0,count($ids),'?'));$s=$db->prepare("SELECT code FROM auth_roles WHERE active=1 AND id IN ($ph)");$s->execute($ids);$selected=array_values($s->fetchAll(PDO::FETCH_COLUMN));}

        $child=$db->prepare("SELECT 1 FROM family_relationships WHERE student_user_id=:user AND status='active' LIMIT 1");$child->execute(['user'=>$userId]);
        $productionStudent=$db->prepare("SELECT 1 FROM production_memberships WHERE user_id=:user AND audience_type='student' AND status='active' LIMIT 1");$productionStudent->execute(['user'=>$userId]);
        if(($child->fetchColumn()||$productionStudent->fetchColumn())&&!in_array('student',$selected,true))throw new RuntimeException('This account is structurally linked as a Student. Remove active family/production Student relationships before removing the Student role.');

        $guardian=$db->prepare("SELECT 1 FROM family_relationships WHERE guardian_user_id=:user AND status='active' LIMIT 1");$guardian->execute(['user'=>$userId]);
        $productionGuardian=$db->prepare("SELECT 1 FROM production_memberships WHERE user_id=:user AND audience_type='guardian' AND status='active' LIMIT 1");$productionGuardian->execute(['user'=>$userId]);
        if(($guardian->fetchColumn()||$productionGuardian->fetchColumn())&&in_array('student',$selected,true))throw new RuntimeException('An active parent, guardian, or caregiver cannot be assigned the Student role. Remove active family/production Guardian relationships first.');

        if(in_array('student',$selected,true)&&$ids){
            $ph=implode(',',array_fill(0,count($ids),'?'));
            $privileged=$db->prepare("SELECT 1 FROM auth_role_permissions rp JOIN auth_permissions p ON p.id=rp.permission_id WHERE rp.role_id IN ($ph) LIMIT 1");
            $privileged->execute($ids);
            if($privileged->fetchColumn())throw new RuntimeException('A Student identity cannot also hold staff, moderation, safeguarding, or administrative permissions. Remove privileged roles before saving the Student role.');
        }

        $productionStaff=$db->prepare("SELECT 1 FROM production_memberships WHERE user_id=:user AND audience_type='staff' AND status='active' LIMIT 1");$productionStaff->execute(['user'=>$userId]);
        if($productionStaff->fetchColumn()&&!in_array('production_staff',$selected,true)&&!in_array('administrator',$selected,true))throw new RuntimeException('This account is active production staff. Remove its production Staff memberships before removing staff authority.');
    }
}
