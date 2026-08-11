<?php

declare(strict_types=1);

final class VolunteerLifecycleGuard
{
    public static function assertActionAllowed(PDO $db,string $route,array $input):void
    {
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')return;
        $action=(string)($input['action']??'');
        if($route==='/volunteer/shift'&&$action==='signup'){
            $shiftId=(int)($input['shift_id']??0);self::assertShiftOpen($db,$shiftId,'That volunteer shift has already started and can no longer accept new signups.');
        }
        if($route==='/volunteer/approvals'&&$action==='request'){
            $shiftId=(int)($input['shift_id']??0);self::assertShiftOpen($db,$shiftId,'That volunteer shift has already started and can no longer accept approval requests.');
        }
        if($route==='/admin/volunteer-approvals'&&$action==='approve'){
            $requestId=(int)($input['request_id']??0);if($requestId<1)throw new RuntimeException('That approval request could not be found.');$s=$db->prepare('SELECT vs.starts_at FROM volunteer_shift_approval_requests r JOIN volunteer_shifts vs ON vs.id=r.shift_id WHERE r.id=:request LIMIT 1');$s->execute(['request'=>$requestId]);$starts=$s->fetchColumn();if($starts===false)throw new RuntimeException('That approval request could not be found.');if(strtotime((string)$starts)<=time())throw new RuntimeException('That volunteer shift has already started and this request can no longer be approved. Decline it or update the roster directly if historical correction is needed.');
        }
    }

    private static function assertShiftOpen(PDO $db,int $shiftId,string $message):void
    {
        if($shiftId<1)throw new RuntimeException('That volunteer shift could not be found.');$s=$db->prepare('SELECT starts_at FROM volunteer_shifts WHERE id=:shift LIMIT 1');$s->execute(['shift'=>$shiftId]);$starts=$s->fetchColumn();if($starts===false)throw new RuntimeException('That volunteer shift could not be found.');if(strtotime((string)$starts)<=time())throw new RuntimeException($message);
    }
}
