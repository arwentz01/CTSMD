<?php
declare(strict_types=1);
require_once __DIR__.'/ProductionParticipantEligibility.php';

final class AdultParticipantService
{
    /** Explicit administrator classification; self-edited DOB is never authorization. */
    public static function classify(PDO $db,int $actorId,int $userId,bool $adult,string $reason):void
    {
        if(trim($reason)===''||mb_strlen($reason)>1000)throw new RuntimeException('A classification review reason is required (up to 1,000 characters).');
        $db->beginTransaction();
        try{
            $q=$db->prepare('SELECT active,account_status FROM users WHERE id=? FOR UPDATE');$q->execute([$actorId]);$actor=$q->fetch();
            if(!$actor||!(int)$actor['active']||$actor['account_status']!=='active'||!in_array('accounts.manage',Auth::permissions($db,$actorId),true))throw new RuntimeException('Account management permission is required.');
            $q=$db->prepare('SELECT active,account_status,date_of_birth,adult_participant FROM users WHERE id=? FOR UPDATE');$q->execute([$userId]);$person=$q->fetch();
            if(!$person||!(int)$person['active']||$person['account_status']==='disabled')throw new RuntimeException('Account unavailable.');
            if(IdentityRolePolicy::isStudent($db,$userId))throw new RuntimeException('Student transition requires a separate safeguarding and account review; changing DOB or roles is insufficient.');
            if($adult&&(!$person['date_of_birth']||PersonEligibility::ageOn($person['date_of_birth'],date('Y-m-d'))<18))throw new RuntimeException('Review a date of birth showing age 18 or older before approving Adult participation.');
            $db->prepare('UPDATE users SET adult_participant=? WHERE id=?')->execute([(int)$adult,$userId]);
            ProductionParticipantEligibility::audit($db,$actorId,$userId,'adult_classification',['before'=>(bool)$person['adult_participant'],'after'=>$adult,'reason'=>trim($reason)]);
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
}
