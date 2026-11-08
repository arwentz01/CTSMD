<?php
declare(strict_types=1);

/**
 * Guardian-facing student activation status. Read-only except explicit guardian
 * requests handled in PlatformRegistrationService.
 */
final class StudentInvitationStatusService
{
    public static function forGuardian(PDO $db,int $guardianId):array
    {
        $sql="SELECT u.id child_id,u.first_name,u.last_name,u.email,u.account_status,
            (SELECT v.expires_at FROM auth_email_verifications v
             WHERE v.user_id=u.id ORDER BY v.id DESC LIMIT 1) invite_expires_at,
            (SELECT v.verified_at FROM auth_email_verifications v
             WHERE v.user_id=u.id ORDER BY v.id DESC LIMIT 1) invite_used_at
            FROM family_relationships fr
            JOIN users u ON u.id=fr.student_user_id
                AND u.active=1 AND u.account_status='managed'
            WHERE fr.guardian_user_id=:guardian AND fr.status='active'
              AND fr.relationship_type IN ('parent','guardian')
              AND EXISTS (
                SELECT 1 FROM auth_user_roles ur
                JOIN auth_roles ar ON ar.id=ur.role_id AND ar.active=1 AND ar.code='student'
                WHERE ur.user_id=u.id
              )
            ORDER BY u.first_name,u.last_name,u.id";
        $q=$db->prepare($sql);
        $q->execute(['guardian'=>$guardianId]);
        $result=[];
        $now=new DateTimeImmutable('now');
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $child){
            if(empty($child['email']))continue; // Managed-only child: no activation required.
            $expires=(string)($child['invite_expires_at']??'');
            $status=$expires===''?'not_sent':(new DateTimeImmutable($expires)<=$now?'expired':'pending');
            $result[]=[
                'child_id'=>(int)$child['child_id'],
                'child_name'=>trim($child['first_name'].' '.$child['last_name']),
                'status'=>$status,
                'expires_at'=>$expires?:null
            ];
        }
        return $result;
    }
}
