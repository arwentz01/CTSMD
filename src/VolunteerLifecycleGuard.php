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
        if(in_array($route,['/admin/volunteer-approvals','/admin/volunteer-approvals/review'],true)&&$action==='approve'){
            $requestId=(int)($input['request_id']??0);
            $s=$db->prepare('SELECT shift_id FROM volunteer_shift_approval_requests WHERE id=:request LIMIT 1');
            $s->execute(['request'=>$requestId]);
            $shiftId=(int)($s->fetchColumn()?:0);
            self::assertShiftOpen($db,$shiftId,'That volunteer shift has already started and this request can no longer be approved.');
        }
    }

    private static function assertShiftOpen(PDO $db,int $shiftId,string $message):void
    {
        if($shiftId<1)throw new RuntimeException('That volunteer shift could not be found.');
        $s=$db->prepare('SELECT vs.starts_at,vs.production_id,p.is_active FROM volunteer_shifts vs LEFT JOIN productions p ON p.id=vs.production_id WHERE vs.id=:shift LIMIT 1');
        $s->execute(['shift'=>$shiftId]);
        $shift=$s->fetch();
        if(!$shift)throw new RuntimeException('That volunteer shift could not be found.');
        if($shift['production_id']!==null&&!(bool)$shift['is_active'])throw new RuntimeException('That production is inactive and cannot accept volunteer signups or approvals.');
        if(strtotime((string)$shift['starts_at'])<=time())throw new RuntimeException($message);
    }
}
