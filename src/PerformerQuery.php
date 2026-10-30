<?php
declare(strict_types=1);

/** Shared SQL predicates. Role authorization, never ordinary Member or legacy flags. */
final class PerformerQuery
{
    public static function role(string $userAlias,string $code):string
    {
        if(!preg_match('/^[a-z][a-z0-9_]*$/i',$userAlias)||!in_array($code,['student','adult_performer'],true))throw new InvalidArgumentException('Invalid performer query.');
        return "EXISTS(SELECT 1 FROM auth_user_roles performer_ur JOIN auth_roles performer_r ON performer_r.id=performer_ur.role_id AND performer_r.active=1 AND performer_r.code='$code' WHERE performer_ur.user_id=$userAlias.id)";
    }
    public static function eligible(string $userAlias):string
    {
        return '('.self::role($userAlias,'student').' OR '.self::role($userAlias,'adult_performer').')';
    }
    public static function profile(string $userAlias):string
    {
        return '('.self::eligible($userAlias)." OR EXISTS(SELECT 1 FROM student_profiles performer_profile WHERE performer_profile.user_id=$userAlias.id) OR EXISTS(SELECT 1 FROM theatre_history_credits performer_credit WHERE performer_credit.user_id=$userAlias.id))";
    }
    public static function membership(string $membershipAlias,string $userAlias):string
    {
        if(!preg_match('/^[a-z][a-z0-9_]*$/i',$membershipAlias))throw new InvalidArgumentException('Invalid membership alias.');
        return "($membershipAlias.audience_type NOT IN ('student','adult') OR ($membershipAlias.audience_type='student' AND ".self::role($userAlias,'student').") OR ($membershipAlias.audience_type='adult' AND ".self::role($userAlias,'adult_performer').' AND NOT '.self::role($userAlias,'student').'))';
    }
}
