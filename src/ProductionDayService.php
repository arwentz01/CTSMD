<?php

declare(strict_types=1);

require_once __DIR__ . '/ScheduleAudience.php';
require_once __DIR__ . '/AttendanceService.php';

final class ProductionDayService
{
    public static function build(PDO $db,int $productionId,string $date):array
    {
        $day=self::parseDate($date);
        $schedule=self::schedule($db,$productionId,$day);
        $expectedMarks=0;$marked=0;
        foreach($schedule as &$item){
            $item['groups']=ScheduleAudience::groupNamesForItem($db,(int)$item['id']);
            $roster=AttendanceService::roster($db,(int)$item['id']);
            $item['attendance_roster']=$roster;
            $item['attendance_counts']=AttendanceService::statusCounts($roster);
            $item['expected_count']=count($roster);
            $item['marked_count']=count(array_filter($roster,static fn(array $r):bool=>(string)($r['attendance_status']??'unmarked')!=='unmarked'));
            $expectedMarks+=$item['expected_count'];$marked+=$item['marked_count'];
        }unset($item);

        $volunteers=self::volunteerShifts($db,$productionId,$day);
        $openSlots=0;foreach($volunteers as $shift)$openSlots+=max(0,(int)$shift['required_slots']-(int)$shift['filled_slots']);
        $notices=self::notices($db,$productionId,$day);
        $draftNotices=count(array_filter($notices,static fn(array $n):bool=>$n['status']==='draft'));
        $checklist=self::checklist($db,$productionId,$day);
        $brief=self::brief($db,$productionId,$day);

        return [
            'date'=>$day,
            'brief'=>$brief,
            'schedule'=>$schedule,
            'volunteer_shifts'=>$volunteers,
            'notices'=>$notices,
            'checklist'=>$checklist,
            'summary'=>[
                'schedule_items'=>count($schedule),
                'expected_marks'=>$expectedMarks,
                'attendance_marked'=>$marked,
                'open_volunteer_slots'=>$openSlots,
                'draft_notices'=>$draftNotices,
                'checklist_open'=>count(array_filter($checklist,static fn(array $i):bool=>$i['status']!=='done')),
            ],
        ];
    }

    public static function saveBrief(PDO $db,int $productionId,int $actorId,string $date,array $input):void
    {
        $day=self::parseDate($date);
        $status=(string)($input['day_status']??'planning');
        $headline=trim((string)($input['headline']??''));
        $operations=trim((string)($input['operations_note']??''));
        $arrival=trim((string)($input['arrival_note']??''));
        if(!in_array($status,['planning','live','closed'],true))throw new RuntimeException('Choose a valid production-day status.');
        if(mb_strlen($headline)>190)throw new RuntimeException('Keep the day headline under 190 characters.');
        if(mb_strlen($operations)>5000||mb_strlen($arrival)>3000)throw new RuntimeException('Production-day notes are too long.');

        $db->beginTransaction();
        try{
            $select=$db->prepare('SELECT * FROM production_day_briefs WHERE production_id=:production AND service_date=:day FOR UPDATE');
            $select->execute(['production'=>$productionId,'day'=>$day]);$before=$select->fetch();
            $now=(new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
            $openedAt=$before['opened_at']??null;
            $closedAt=$before['closed_at']??null;
            if($status==='live'&&!$openedAt)$openedAt=$now;
            if($status==='closed'&&!$closedAt)$closedAt=$now;
            if($status!=='closed')$closedAt=null;

            if($before){
                $update=$db->prepare('UPDATE production_day_briefs SET day_status=:status,headline=:headline,operations_note=:operations,arrival_note=:arrival,updated_by_user_id=:actor,opened_at=:opened,closed_at=:closed WHERE id=:id');
                $update->execute(['status'=>$status,'headline'=>$headline?:null,'operations'=>$operations?:null,'arrival'=>$arrival?:null,'actor'=>$actorId,'opened'=>$openedAt,'closed'=>$closedAt,'id'=>(int)$before['id']]);
                $id=(int)$before['id'];
            }else{
                $insert=$db->prepare('INSERT INTO production_day_briefs (production_id,service_date,day_status,headline,operations_note,arrival_note,created_by_user_id,updated_by_user_id,opened_at,closed_at) VALUES (:production,:day,:status,:headline,:operations,:arrival,:actor,:actor2,:opened,:closed)');
                $insert->execute(['production'=>$productionId,'day'=>$day,'status'=>$status,'headline'=>$headline?:null,'operations'=>$operations?:null,'arrival'=>$arrival?:null,'actor'=>$actorId,'actor2'=>$actorId,'opened'=>$openedAt,'closed'=>$closedAt]);
                $id=(int)$db->lastInsertId();
            }
            self::audit($db,$actorId,'production_day.updated','production_day_brief',$id,'Updated production-day briefing.',['production_id'=>$productionId,'service_date'=>$day,'before_status'=>$before['day_status']??null,'after_status'=>$status]);
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof RuntimeException)throw $e;throw new RuntimeException('The production-day briefing could not be saved.');}
    }

    private static function brief(PDO $db,int $productionId,string $day):?array
    {
        $s=$db->prepare("SELECT b.*,CONCAT(c.first_name,' ',c.last_name) creator,CONCAT(u.first_name,' ',u.last_name) updater FROM production_day_briefs b LEFT JOIN users c ON c.id=b.created_by_user_id LEFT JOIN users u ON u.id=b.updated_by_user_id WHERE b.production_id=:production AND b.service_date=:day LIMIT 1");
        $s->execute(['production'=>$productionId,'day'=>$day]);return $s->fetch()?:null;
    }

    private static function schedule(PDO $db,int $productionId,string $day):array
    {
        $s=$db->prepare("SELECT si.id,si.title,si.starts_at,si.ends_at,si.family_call_at,si.location,si.visibility,si.item_type,si.audience_mode,si.status FROM schedule_items si WHERE si.production_id=:production AND si.status='active' AND DATE(si.starts_at)=:day ORDER BY si.starts_at,si.id");
        $s->execute(['production'=>$productionId,'day'=>$day]);return $s->fetchAll();
    }

    private static function volunteerShifts(PDO $db,int $productionId,string $day):array
    {
        $s=$db->prepare("SELECT vs.id,vs.title,vs.category,vs.starts_at,vs.ends_at,vs.location,vs.required_slots,vs.approval_required,COALESCE(SUM(CASE WHEN vss.status IN ('signed_up','checked_in','completed') THEN 1 ELSE 0 END),0) filled_slots,COALESCE(SUM(CASE WHEN vss.status='checked_in' THEN 1 ELSE 0 END),0) checked_in,COALESCE(SUM(CASE WHEN vss.status='waitlisted' THEN 1 ELSE 0 END),0) waitlisted FROM volunteer_shifts vs LEFT JOIN volunteer_shift_signups vss ON vss.shift_id=vs.id WHERE vs.production_id=:production AND DATE(vs.starts_at)=:day GROUP BY vs.id,vs.title,vs.category,vs.starts_at,vs.ends_at,vs.location,vs.required_slots,vs.approval_required ORDER BY vs.starts_at,vs.id");
        $s->execute(['production'=>$productionId,'day'=>$day]);return $s->fetchAll();
    }

    private static function notices(PDO $db,int $productionId,string $day):array
    {
        $s=$db->prepare("SELECT scn.id,scn.schedule_item_id,scn.subject,scn.audience_scope,scn.audience_count,scn.status,scn.created_at,scn.published_at,si.title schedule_title FROM schedule_change_notices scn JOIN schedule_items si ON si.id=scn.schedule_item_id WHERE scn.production_id=:production AND DATE(si.starts_at)=:day AND scn.status<>'cancelled' ORDER BY FIELD(scn.status,'draft','published'),scn.created_at DESC");
        $s->execute(['production'=>$productionId,'day'=>$day]);return $s->fetchAll();
    }

    private static function checklist(PDO $db,int $productionId,string $day):array
    {
        $end=$day.' 23:59:59';
        $s=$db->prepare("SELECT pci.id,pci.title,pci.category,pci.status,pci.due_at,pci.notes,pci.completed_at,CONCAT(u.first_name,' ',u.last_name) owner_name FROM production_checklist_items pci LEFT JOIN users u ON u.id=pci.assigned_to_user_id WHERE pci.production_id=:production AND (pci.status<>'done' AND (pci.due_at IS NULL OR pci.due_at<=:end) OR pci.status='done' AND DATE(pci.completed_at)=:day) ORDER BY pci.status='done',pci.due_at IS NULL,pci.due_at,pci.sort_order,pci.id LIMIT 20");
        $s->execute(['production'=>$productionId,'end'=>$end,'day'=>$day]);return $s->fetchAll();
    }

    private static function parseDate(string $date):string
    {
        $date=trim($date);$d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);if(!$d||$d->format('Y-m-d')!==$date)throw new RuntimeException('Choose a valid production date.');return $date;
    }

    private static function audit(PDO $db,int $actor,string $event,string $subjectType,int $subjectId,string $summary,array $meta):void
    {
        $s=$db->prepare('INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,:event,:type,:id,:summary,:meta)');
        $s->execute(['actor'=>$actor,'event'=>$event,'type'=>$subjectType,'id'=>$subjectId,'summary'=>$summary,'meta'=>json_encode($meta,JSON_THROW_ON_ERROR)]);
    }
}
