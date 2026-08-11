<?php

declare(strict_types=1);

require_once __DIR__ . '/ScheduleAudience.php';
require_once __DIR__ . '/AccessPolicy.php';

final class AttendanceService
{
    public static function scheduleItem(PDO $db,int $itemId,int $productionId):?array
    {
        if($itemId<1||$productionId<1)return null;
        $stmt=$db->prepare("SELECT si.id,si.production_id,si.title,si.starts_at,si.ends_at,si.family_call_at,si.location,si.visibility,si.item_type,si.audience_mode,si.status,p.title production_title FROM schedule_items si JOIN productions p ON p.id=si.production_id WHERE si.id=:id AND si.production_id=:production AND si.status='active' LIMIT 1");
        $stmt->execute(['id'=>$itemId,'production'=>$productionId]);
        return $stmt->fetch()?:null;
    }

    public static function expectedMembers(PDO $db,int $scheduleItemId):array
    {
        $audience=ScheduleAudience::audienceMembersForItem($db,$scheduleItemId);
        $expected=[];$seen=[];
        foreach($audience as $member){
            if(($member['audience_type']??'')==='guardian')continue;
            $id=(int)$member['id'];if(isset($seen[$id]))continue;$seen[$id]=true;$expected[]=$member;
        }
        return $expected;
    }

    public static function roster(PDO $db,int $scheduleItemId):array
    {
        $expected=self::expectedMembers($db,$scheduleItemId);
        if(!$expected)return [];
        $ids=array_map(static fn(array $m):int=>(int)$m['id'],$expected);
        $ph=implode(',',array_fill(0,count($ids),'?'));
        $stmt=$db->prepare("SELECT ar.user_id,ar.status,ar.staff_note,ar.marked_at,CONCAT(marker.first_name,' ',marker.last_name) marked_by FROM attendance_records ar LEFT JOIN users marker ON marker.id=ar.marked_by_user_id WHERE ar.schedule_item_id=? AND ar.user_id IN ($ph)");
        $stmt->execute(array_merge([$scheduleItemId],$ids));
        $records=[];foreach($stmt->fetchAll() as $row)$records[(int)$row['user_id']]=$row;
        $reports=self::activeReportsByStudent($db,$scheduleItemId);
        foreach($expected as &$member){$id=(int)$member['id'];$record=$records[$id]??null;$member['attendance_status']=$record['status']??'unmarked';$member['staff_note']=$record['staff_note']??null;$member['marked_at']=$record['marked_at']??null;$member['marked_by']=$record['marked_by']??null;$member['absence_report']=$reports[$id]??null;}unset($member);
        return $expected;
    }

    public static function saveRoster(PDO $db,int $scheduleItemId,int $actorUserId,array $statuses,array $notes):void
    {
        $active=$db->prepare("SELECT 1 FROM schedule_items WHERE id=:id AND status='active' LIMIT 1");$active->execute(['id'=>$scheduleItemId]);if(!$active->fetchColumn())throw new RuntimeException('Attendance cannot be changed for a cancelled schedule item.');
        $expected=self::expectedMembers($db,$scheduleItemId);
        $allowed=[];foreach($expected as $member)$allowed[(int)$member['id']]=true;
        $validStatuses=['unmarked','present','absent','late','excused','left_early'];
        $upsert=$db->prepare("INSERT INTO attendance_records (schedule_item_id,user_id,status,staff_note,marked_by_user_id,marked_at) VALUES (:item,:user,:status,:note,:actor,CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE status=VALUES(status),staff_note=VALUES(staff_note),marked_by_user_id=VALUES(marked_by_user_id),marked_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP");
        foreach($statuses as $userId=>$status){$userId=(int)$userId;$status=(string)$status;if(!isset($allowed[$userId])||!in_array($status,$validStatuses,true))continue;$note=trim((string)($notes[$userId]??''));if(mb_strlen($note)>1000)throw new RuntimeException('Attendance notes must be 1,000 characters or fewer.');$upsert->execute(['item'=>$scheduleItemId,'user'=>$userId,'status'=>$status,'note'=>$note!==''?$note:null,'actor'=>$actorUserId]);}
    }

    public static function reportableStudents(PDO $db,array $user,int $scheduleItemId):array
    {
        $active=$db->prepare("SELECT 1 FROM schedule_items WHERE id=:id AND status='active' LIMIT 1");$active->execute(['id'=>$scheduleItemId]);if(!$active->fetchColumn())return [];
        $expected=self::expectedMembers($db,$scheduleItemId);$expectedStudents=[];foreach($expected as $member)if(($member['audience_type']??'')==='student')$expectedStudents[(int)$member['id']]=$member;
        if(!$expectedStudents)return [];
        if(AccessPolicy::isStudent($user))return isset($expectedStudents[(int)$user['id']])?[$expectedStudents[(int)$user['id']]]:[];
        $stmt=$db->prepare("SELECT fr.student_user_id FROM family_relationships fr JOIN users student ON student.id=fr.student_user_id AND student.active=1 AND student.account_status<>'disabled' WHERE fr.guardian_user_id=:guardian AND fr.status='active'");$stmt->execute(['guardian'=>(int)$user['id']]);$ids=array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));$out=[];foreach($ids as $id)if(isset($expectedStudents[$id]))$out[]=$expectedStudents[$id];return $out;
    }

    public static function submitAbsenceReport(PDO $db,int $scheduleItemId,int $studentUserId,int $reporterUserId,string $reason):int
    {
        $active=$db->prepare("SELECT 1 FROM schedule_items WHERE id=:id AND status='active' LIMIT 1");$active->execute(['id'=>$scheduleItemId]);if(!$active->fetchColumn())throw new RuntimeException('Absence reports cannot be submitted for a cancelled schedule item.');
        $reason=trim($reason);if($reason===''||mb_strlen($reason)>1500)throw new RuntimeException('Enter a brief absence reason up to 1,500 characters.');
        $stmt=$db->prepare("INSERT INTO attendance_absence_reports (schedule_item_id,student_user_id,reported_by_user_id,reason,status,submitted_at) VALUES (:item,:student,:reporter,:reason,'submitted',CURRENT_TIMESTAMP)");$stmt->execute(['item'=>$scheduleItemId,'student'=>$studentUserId,'reporter'=>$reporterUserId,'reason'=>$reason]);return (int)$db->lastInsertId();
    }

    public static function acknowledgeReport(PDO $db,int $reportId,int $actorUserId):void
    {
        $stmt=$db->prepare("SELECT aar.id,aar.schedule_item_id,aar.student_user_id,aar.status FROM attendance_absence_reports aar JOIN schedule_items si ON si.id=aar.schedule_item_id AND si.status='active' WHERE aar.id=:id FOR UPDATE");$stmt->execute(['id'=>$reportId]);$report=$stmt->fetch();if(!$report||$report['status']!=='submitted')throw new RuntimeException('That absence report is no longer awaiting review on an active schedule item.');
        $expected=self::expectedMembers($db,(int)$report['schedule_item_id']);$expectedIds=array_map(static fn(array $m):int=>(int)$m['id'],$expected);if(!in_array((int)$report['student_user_id'],$expectedIds,true))throw new RuntimeException('That student is no longer expected for this schedule item. Review the schedule audience before acknowledging the report.');
        $db->prepare("UPDATE attendance_absence_reports SET status='acknowledged',reviewed_by_user_id=:actor,reviewed_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=:id")->execute(['actor'=>$actorUserId,'id'=>$reportId]);
        $db->prepare("INSERT INTO attendance_records (schedule_item_id,user_id,status,marked_by_user_id,marked_at) VALUES (:item,:user,'excused',:actor,CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE status='excused',marked_by_user_id=VALUES(marked_by_user_id),marked_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP")->execute(['item'=>(int)$report['schedule_item_id'],'user'=>(int)$report['student_user_id'],'actor'=>$actorUserId]);
    }

    public static function statusCounts(array $roster):array
    {
        $counts=['unmarked'=>0,'present'=>0,'absent'=>0,'late'=>0,'excused'=>0,'left_early'=>0];foreach($roster as $member){$status=(string)($member['attendance_status']??'unmarked');if(isset($counts[$status]))$counts[$status]++;}return $counts;
    }

    private static function activeReportsByStudent(PDO $db,int $scheduleItemId):array
    {
        $stmt=$db->prepare("SELECT aar.id,aar.student_user_id,aar.reason,aar.status,aar.submitted_at,CONCAT(u.first_name,' ',u.last_name) reporter FROM attendance_absence_reports aar JOIN users u ON u.id=aar.reported_by_user_id WHERE aar.schedule_item_id=:item AND aar.status IN ('submitted','acknowledged') ORDER BY aar.submitted_at DESC,aar.id DESC");$stmt->execute(['item'=>$scheduleItemId]);$out=[];foreach($stmt->fetchAll() as $row){$id=(int)$row['student_user_id'];if(!isset($out[$id]))$out[$id]=$row;}return $out;
    }
}
