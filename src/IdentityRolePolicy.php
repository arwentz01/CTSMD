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
        $people=$db->prepare('SELECT id,active FROM users WHERE id IN (:guardian,:student)');$people->execute(['guardian'=>$guardianId,'student'=>$studentId]);$rows=$people->fetchAll();if(count($rows)!==2)throw new RuntimeException('One of those people could not be found.');foreach($rows as $row)if(!(bool)$row['active'])throw new RuntimeException('Family relationships can only use active people.');
        if(!self::isStudent($db,$studentId))throw new RuntimeException('The linked child must currently have the Student role.');
        if(self::isStudent($db,$guardianId))throw new RuntimeException('A Student cannot be assigned as the guardian in this relationship.');
    }

    public static function assertProductionAudience(PDO $db,int $userId,string $audienceType):void
    {
        if($userId<1||!in_array($audienceType,['student','guardian','staff'],true))throw new RuntimeException('Choose a valid person and production role.');
        $person=$db->prepare('SELECT active FROM users WHERE id=:id LIMIT 1');$person->execute(['id'=>$userId]);if(!(bool)$person->fetchColumn())throw new RuntimeException('That person is not an active CTSMD user.');
        if($audienceType==='student'&&!self::isStudent($db,$userId))throw new RuntimeException('Only an account with the Student role can be added as a production student.');
        if($audienceType==='staff'&&!self::isStaff($db,$userId))throw new RuntimeException('Only Production Staff or an Administrator can be added as production staff.');
        if($audienceType==='guardian'&&self::isStudent($db,$userId))throw new RuntimeException('A Student cannot be added as a production guardian.');
    }
}
