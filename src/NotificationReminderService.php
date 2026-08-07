<?php

declare(strict_types=1);

require_once __DIR__.'/MailService.php';

final class NotificationReminderService
{
    public static function queueDue(PDO $db,string $appUrl):array
    {
        $counts=['forms'=>0,'volunteer_shifts'=>0,'credentials'=>0];
        $appUrl=rtrim($appUrl,'/');

        $forms=$db->query("SELECT fa.id,fa.user_id,fa.due_at,f.title,CONCAT(u.first_name,' ',u.last_name) name,u.email FROM form_assignments fa JOIN forms f ON f.id=fa.form_id JOIN users u ON u.id=fa.user_id AND u.active=1 WHERE u.email IS NOT NULL AND u.account_status='active' AND fa.status IN ('due_soon','missing') AND fa.due_at IS NOT NULL AND fa.due_at BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL 3 DAY)")->fetchAll();
        foreach($forms as $row){$date=date('l, F j',strtotime($row['due_at']));$body="Hi {$row['name']},\n\n{$row['title']} is due {$date}. Open CTSMD Connect to review and complete it:\n{$appUrl}/forms\n\n— CTSMD Connect";$id=MailService::queue($db,(int)$row['user_id'],(string)$row['email'],(string)$row['name'],'forms','Form due soon · '.$row['title'],$body,null,'form-due-'.$row['id'].'-'.date('Y-m-d'));if($id)$counts['forms']++;}

        $shifts=$db->query("SELECT vss.id,vss.user_id,vs.title,vs.starts_at,vs.location,CONCAT(u.first_name,' ',u.last_name) name,u.email FROM volunteer_shift_signups vss JOIN volunteer_shifts vs ON vs.id=vss.shift_id JOIN users u ON u.id=vss.user_id AND u.active=1 WHERE u.email IS NOT NULL AND u.account_status='active' AND vss.status IN ('signed_up','checked_in') AND vs.starts_at BETWEEN DATE_ADD(NOW(),INTERVAL 20 HOUR) AND DATE_ADD(NOW(),INTERVAL 28 HOUR)")->fetchAll();
        foreach($shifts as $row){$when=date('l, F j \a\t g:i A',strtotime($row['starts_at']));$body="Hi {$row['name']},\n\nReminder: you are signed up for {$row['title']} {$when} at {$row['location']}.\n\nReview your volunteer schedule:\n{$appUrl}/volunteer-shifts\n\n— CTSMD Connect";$id=MailService::queue($db,(int)$row['user_id'],(string)$row['email'],(string)$row['name'],'volunteer','Volunteer shift tomorrow · '.$row['title'],$body,null,'shift-reminder-'.$row['id']);if($id)$counts['volunteer_shifts']++;}

        $credentials=$db->query("SELECT vc.id,vc.user_id,vc.expires_at,vr.name requirement_name,CONCAT(u.first_name,' ',u.last_name) name,u.email FROM volunteer_credentials vc JOIN volunteer_requirements vr ON vr.id=vc.requirement_id JOIN users u ON u.id=vc.user_id AND u.active=1 WHERE u.email IS NOT NULL AND u.account_status='active' AND vc.status='approved' AND vc.expires_at IS NOT NULL AND DATE(vc.expires_at) IN (DATE(DATE_ADD(NOW(),INTERVAL 30 DAY)),DATE(DATE_ADD(NOW(),INTERVAL 7 DAY)))")->fetchAll();
        foreach($credentials as $row){$days=(int)round((strtotime($row['expires_at'])-time())/86400);$days=$days>15?30:7;$body="Hi {$row['name']},\n\nYour {$row['requirement_name']} credential expires in about {$days} days. Review your volunteer readiness in CTSMD Connect:\n{$appUrl}/volunteer-readiness\n\n— CTSMD Connect";$id=MailService::queue($db,(int)$row['user_id'],(string)$row['email'],(string)$row['name'],'volunteer','Credential expiring · '.$row['requirement_name'],$body,null,'credential-expiry-'.$row['id'].'-'.$days);if($id)$counts['credentials']++;}
        return $counts;
    }
}
