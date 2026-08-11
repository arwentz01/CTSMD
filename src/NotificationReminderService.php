<?php

declare(strict_types=1);

require_once __DIR__.'/MailService.php';

final class NotificationReminderService
{
    public static function queueDue(PDO $db,string $appUrl):array
    {
        $counts=['forms'=>0,'volunteer_shifts'=>0,'credentials'=>0];
        $appUrl=rtrim($appUrl,'/');

        $forms=$db->query("SELECT fa.id,fa.user_id,COALESCE(fa.subject_user_id,fa.user_id) subject_user_id,fa.due_at,f.title,CONCAT(subject.first_name,' ',subject.last_name) subject_name,EXISTS(SELECT 1 FROM auth_user_roles ur JOIN auth_roles r ON r.id=ur.role_id AND r.code='student' AND r.active=1 WHERE ur.user_id=COALESCE(fa.subject_user_id,fa.user_id)) subject_is_student FROM form_assignments fa JOIN forms f ON f.id=fa.form_id AND f.active=1 JOIN users subject ON subject.id=COALESCE(fa.subject_user_id,fa.user_id) AND subject.active=1 AND subject.account_status<>'disabled' WHERE fa.status IN ('due_soon','missing') AND fa.due_at IS NOT NULL AND fa.due_at BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL 3 DAY)")->fetchAll();
        foreach($forms as $row){
            $date=date('l, F j',strtotime((string)$row['due_at']));
            if((bool)$row['subject_is_student']){
                $guardians=$db->prepare("SELECT DISTINCT u.id,CONCAT(u.first_name,' ',u.last_name) name,u.email FROM family_relationships fr JOIN users u ON u.id=fr.guardian_user_id AND u.active=1 AND u.account_status='active' WHERE fr.student_user_id=:student AND fr.status='active' AND u.email IS NOT NULL ORDER BY u.id");
                $guardians->execute(['student'=>(int)$row['subject_user_id']]);
                foreach($guardians->fetchAll() as $recipient){
                    $body="Hi {$recipient['name']},\n\n{$row['title']} for {$row['subject_name']} is due {$date}. Open CTSMD Connect to review and complete it:\n{$appUrl}/forms\n\n— CTSMD Connect";
                    $id=MailService::queue($db,(int)$recipient['id'],(string)$recipient['email'],(string)$recipient['name'],'forms','Form due soon · '.$row['title'],$body,null,'form-due-'.$row['id'].'-'.$recipient['id'].'-'.date('Y-m-d'));
                    if($id)$counts['forms']++;
                }
                continue;
            }
            $recipientStmt=$db->prepare("SELECT id,CONCAT(first_name,' ',last_name) name,email FROM users WHERE id=:user AND active=1 AND account_status='active' AND email IS NOT NULL LIMIT 1");
            $recipientStmt->execute(['user'=>(int)$row['user_id']]);$recipient=$recipientStmt->fetch();if(!$recipient)continue;
            $body="Hi {$recipient['name']},\n\n{$row['title']} is due {$date}. Open CTSMD Connect to review and complete it:\n{$appUrl}/forms\n\n— CTSMD Connect";
            $id=MailService::queue($db,(int)$recipient['id'],(string)$recipient['email'],(string)$recipient['name'],'forms','Form due soon · '.$row['title'],$body,null,'form-due-'.$row['id'].'-'.$recipient['id'].'-'.date('Y-m-d'));if($id)$counts['forms']++;
        }

        $shifts=$db->query("SELECT vss.id,vss.user_id,vss.shift_id,vs.title,vs.starts_at,vs.location,CONCAT(u.first_name,' ',u.last_name) name,u.email FROM volunteer_shift_signups vss JOIN volunteer_shifts vs ON vs.id=vss.shift_id JOIN users u ON u.id=vss.user_id AND u.active=1 WHERE u.email IS NOT NULL AND u.account_status='active' AND vss.status IN ('signed_up','checked_in') AND vs.starts_at BETWEEN DATE_ADD(NOW(),INTERVAL 20 HOUR) AND DATE_ADD(NOW(),INTERVAL 28 HOUR)")->fetchAll();
        foreach($shifts as $row){
            $when=date('l, F j \\a\\t g:i A',strtotime((string)$row['starts_at']));
            $missing=self::missingVolunteerRequirements($db,(int)$row['user_id'],(int)$row['shift_id']);
            if($missing){
                $needed=implode(', ',$missing);
                $body="Hi {$row['name']},\n\nYou are scheduled for {$row['title']} {$when} at {$row['location']}, but your volunteer eligibility needs attention before check-in. Current requirement(s) needed: {$needed}.\n\nReview your volunteer readiness and contact CTSMD if you need help resolving it:\n{$appUrl}/volunteer-readiness\n\n— CTSMD Connect";
                $id=MailService::queue($db,(int)$row['user_id'],(string)$row['email'],(string)$row['name'],'volunteer','Volunteer requirement needed · '.$row['title'],$body,null,'shift-eligibility-'.$row['id'].'-'.date('Y-m-d'));
            }else{
                $body="Hi {$row['name']},\n\nReminder: you are signed up for {$row['title']} {$when} at {$row['location']}.\n\nReview your volunteer schedule:\n{$appUrl}/volunteer-shifts\n\n— CTSMD Connect";
                $id=MailService::queue($db,(int)$row['user_id'],(string)$row['email'],(string)$row['name'],'volunteer','Volunteer shift tomorrow · '.$row['title'],$body,null,'shift-reminder-'.$row['id']);
            }
            if($id)$counts['volunteer_shifts']++;
        }

        $credentials=$db->query("SELECT vc.id,vc.user_id,vc.expires_at,vr.name requirement_name,CONCAT(u.first_name,' ',u.last_name) name,u.email FROM volunteer_credentials vc JOIN volunteer_requirements vr ON vr.id=vc.requirement_id JOIN users u ON u.id=vc.user_id AND u.active=1 WHERE u.email IS NOT NULL AND u.account_status='active' AND vc.status='approved' AND vc.expires_at IS NOT NULL AND DATE(vc.expires_at) IN (DATE(DATE_ADD(NOW(),INTERVAL 30 DAY)),DATE(DATE_ADD(NOW(),INTERVAL 7 DAY)))")->fetchAll();
        foreach($credentials as $row){$days=(int)round((strtotime($row['expires_at'])-time())/86400);$days=$days>15?30:7;$body="Hi {$row['name']},\n\nYour {$row['requirement_name']} credential expires in about {$days} days. Review your volunteer readiness in CTSMD Connect:\n{$appUrl}/volunteer-readiness\n\n— CTSMD Connect";$id=MailService::queue($db,(int)$row['user_id'],(string)$row['email'],(string)$row['name'],'volunteer','Credential expiring · '.$row['requirement_name'],$body,null,'credential-expiry-'.$row['id'].'-'.$days);if($id)$counts['credentials']++;}
        return $counts;
    }

    private static function missingVolunteerRequirements(PDO $db,int $userId,int $shiftId):array
    {
        $stmt=$db->prepare("SELECT vr.name FROM volunteer_shift_requirements vsr JOIN volunteer_requirements vr ON vr.id=vsr.requirement_id LEFT JOIN volunteer_credentials vc ON vc.requirement_id=vr.id AND vc.user_id=:user WHERE vsr.shift_id=:shift AND (vc.id IS NULL OR vc.status<>'approved' OR (vc.expires_at IS NOT NULL AND vc.expires_at<NOW())) ORDER BY vr.name");
        $stmt->execute(['user'=>$userId,'shift'=>$shiftId]);
        return array_values($stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}
