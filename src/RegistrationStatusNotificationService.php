<?php

declare(strict_types=1);

require_once __DIR__ . '/MailService.php';

final class RegistrationStatusNotificationService
{
    public static function queue(PDO $db,int $submissionId,string $previousStatus): void
    {
        $stmt=$db->prepare("SELECT rs.id,rs.participant_first_name,rs.participant_last_name,rs.registrant_email,rs.guardian_name,rs.guardian_email,rs.status,rs.reviewed_at,ro.title opportunity_title,ro.starts_at,ro.location FROM registration_submissions rs JOIN registration_opportunities ro ON ro.id=rs.opportunity_id WHERE rs.id=:id LIMIT 1");
        $stmt->execute(['id'=>$submissionId]);$row=$stmt->fetch();if(!$row||$row['status']===$previousStatus)return;
        $status=(string)$row['status'];$participant=trim($row['participant_first_name'].' '.$row['participant_last_name']);$when=$row['starts_at']?date('M j, Y · g:i A',strtotime((string)$row['starts_at'])):null;
        [$subject,$message]=self::copy($status,$participant,(string)$row['opportunity_title'],$when,(string)($row['location']??''));
        $stamp=preg_replace('/[^0-9]/','',(string)($row['reviewed_at']??date('Y-m-d H:i:s')));
        self::send($db,(string)$row['registrant_email'],$participant,$subject,$message,'registration-status:'.$submissionId.':'.$status.':'.$stamp.':registrant');
        $guardianEmail=mb_strtolower(trim((string)($row['guardian_email']??'')));$registrantEmail=mb_strtolower(trim((string)$row['registrant_email']));
        if($guardianEmail!==''&&$guardianEmail!==$registrantEmail){self::send($db,$guardianEmail,(string)($row['guardian_name']?:'Parent / Guardian'),$subject,$message,'registration-status:'.$submissionId.':'.$status.':'.$stamp.':guardian');}
    }

    private static function copy(string $status,string $participant,string $opportunity,?string $when,string $location):array
    {
        $detail='';if($when)$detail.="\n\nWhen: {$when}";if($location!=='')$detail.="\nWhere: {$location}";
        return match($status){
            'accepted'=>['CTSMD registration accepted · '.$opportunity,"The registration for {$participant} has been accepted for {$opportunity}.{$detail}\n\nIf this opportunity leads to CTSMD Connect production access, staff will add the appropriate person/household to the production separately.\n\n— Children’s Theatre of Southern Maryland"],
            'waitlisted'=>['CTSMD registration waitlist update · '.$opportunity,"The registration for {$participant} is currently on the waitlist for {$opportunity}.{$detail}\n\nCTSMD will contact you if the status changes.\n\n— Children’s Theatre of Southern Maryland"],
            'declined'=>['CTSMD registration update · '.$opportunity,"The registration for {$participant} was not selected for {$opportunity}.{$detail}\n\nThank you for your interest in Children’s Theatre of Southern Maryland.\n\n— Children’s Theatre of Southern Maryland"],
            'cancelled'=>['CTSMD registration cancelled · '.$opportunity,"The registration for {$participant} is now cancelled for {$opportunity}.{$detail}\n\n— Children’s Theatre of Southern Maryland"],
            default=>['CTSMD registration update · '.$opportunity,"The registration for {$participant} is now marked ".ucwords(str_replace('_',' ',$status))." for {$opportunity}.{$detail}\n\n— Children’s Theatre of Southern Maryland"],
        };
    }

    private static function send(PDO $db,string $email,string $name,string $subject,string $body,string $dedupe):void
    {
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))return;
        try{MailService::queue($db,null,$email,$name,'system',$subject,$body,null,$dedupe);}catch(Throwable){}
    }
}
