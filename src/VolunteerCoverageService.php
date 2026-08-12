<?php

declare(strict_types=1);

final class VolunteerCoverageService
{
    public static function eligibleLiveSignupCount(PDO $db,int $shiftId):int
    {
        if($shiftId<1)return 0;
        $stmt=$db->prepare("SELECT COUNT(*)
            FROM volunteer_shift_signups vss
            JOIN users u ON u.id=vss.user_id AND u.active=1 AND u.account_status='active'
            JOIN volunteer_profiles vp ON vp.user_id=vss.user_id AND vp.active=1
            WHERE vss.shift_id=:shift
              AND vss.status IN ('signed_up','checked_in')
              AND NOT EXISTS (
                  SELECT 1
                  FROM volunteer_shift_requirements vsr
                  WHERE vsr.shift_id=vss.shift_id
                    AND NOT EXISTS (
                        SELECT 1
                        FROM volunteer_credentials vc
                        WHERE vc.requirement_id=vsr.requirement_id
                          AND vc.user_id=vss.user_id
                          AND vc.status='approved'
                          AND (vc.expires_at IS NULL OR vc.expires_at>=NOW())
                    )
              )");
        $stmt->execute(['shift'=>$shiftId]);
        return (int)$stmt->fetchColumn();
    }

    public static function eligibleLiveSignupCountForUpdate(PDO $db,int $shiftId):int
    {
        if($shiftId<1)return 0;
        $lock=$db->prepare('SELECT id FROM volunteer_shifts WHERE id=:shift FOR UPDATE');
        $lock->execute(['shift'=>$shiftId]);
        if(!$lock->fetchColumn())return 0;
        return self::eligibleLiveSignupCount($db,$shiftId);
    }

    public static function openSlots(PDO $db,int $shiftId):int
    {
        if($shiftId<1)return 0;
        $stmt=$db->prepare('SELECT required_slots FROM volunteer_shifts WHERE id=:shift LIMIT 1');
        $stmt->execute(['shift'=>$shiftId]);
        $required=$stmt->fetchColumn();
        if($required===false)return 0;
        return max((int)$required-self::eligibleLiveSignupCount($db,$shiftId),0);
    }

    public static function missingRequirements(PDO $db,int $userId,int $shiftId):array
    {
        if($userId<1||$shiftId<1)return [];
        $stmt=$db->prepare("SELECT vr.name
            FROM volunteer_shift_requirements vsr
            JOIN volunteer_requirements vr ON vr.id=vsr.requirement_id
            LEFT JOIN volunteer_credentials vc
              ON vc.requirement_id=vr.id
             AND vc.user_id=:user
            WHERE vsr.shift_id=:shift
              AND (
                  vc.id IS NULL
                  OR vc.status<>'approved'
                  OR (vc.expires_at IS NOT NULL AND vc.expires_at<NOW())
              )
            ORDER BY vr.id");
        $stmt->execute(['user'=>$userId,'shift'=>$shiftId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function isLiveVolunteer(PDO $db,int $userId):bool
    {
        if($userId<1)return false;
        $stmt=$db->prepare("SELECT 1
            FROM users u
            JOIN volunteer_profiles vp ON vp.user_id=u.id AND vp.active=1
            WHERE u.id=:user
              AND u.active=1
              AND u.account_status='active'
            LIMIT 1");
        $stmt->execute(['user'=>$userId]);
        return (bool)$stmt->fetchColumn();
    }

    public static function signupFillsLiveSlot(PDO $db,int $signupId):bool
    {
        if($signupId<1)return false;
        $stmt=$db->prepare("SELECT 1
            FROM volunteer_shift_signups vss
            JOIN users u ON u.id=vss.user_id AND u.active=1 AND u.account_status='active'
            JOIN volunteer_profiles vp ON vp.user_id=vss.user_id AND vp.active=1
            WHERE vss.id=:signup
              AND vss.status IN ('signed_up','checked_in')
              AND NOT EXISTS (
                  SELECT 1
                  FROM volunteer_shift_requirements vsr
                  WHERE vsr.shift_id=vss.shift_id
                    AND NOT EXISTS (
                        SELECT 1
                        FROM volunteer_credentials vc
                        WHERE vc.requirement_id=vsr.requirement_id
                          AND vc.user_id=vss.user_id
                          AND vc.status='approved'
                          AND (vc.expires_at IS NULL OR vc.expires_at>=NOW())
                    )
              )
            LIMIT 1");
        $stmt->execute(['signup'=>$signupId]);
        return (bool)$stmt->fetchColumn();
    }
}
