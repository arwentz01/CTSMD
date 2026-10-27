<?php

declare(strict_types=1);

require_once __DIR__ . '/MailService.php';
require_once __DIR__ . '/AuditionIntakeBridge.php';
require_once __DIR__ . '/PersonEligibility.php';
require_once __DIR__ . '/Auth.php';

final class RegistrationService
{
    private const CONNECT_PUBLIC_TYPES = ['audition','event','interest'];

    public static function publicOpportunities(PDO $db): array
    {
        $stmt=$db->query("SELECT ro.*,p.title production_title,p.season FROM registration_opportunities ro LEFT JOIN productions p ON p.id=ro.production_id WHERE ro.status='published' AND ro.opportunity_type IN ('audition','event','interest') AND (ro.registration_opens_at IS NULL OR ro.registration_opens_at<=NOW()) AND (ro.registration_closes_at IS NULL OR ro.registration_closes_at>=NOW()) ORDER BY ro.starts_at IS NULL,ro.starts_at,ro.title");
        $rows=$stmt->fetchAll();
        foreach($rows as &$row){$row['registration_count']=self::activeCount($db,(int)$row['id']);$row['is_full']=$row['capacity']!==null && $row['registration_count'] >= (int)$row['capacity'];}
        unset($row);
        return $rows;
    }

    public static function opportunityBySlug(PDO $db,string $slug,bool $publicOnly=true): ?array
    {
        $slug=trim(strtolower($slug));if($slug==='')return null;
        $sql="SELECT ro.*,p.title production_title,p.season FROM registration_opportunities ro LEFT JOIN productions p ON p.id=ro.production_id WHERE ro.slug=:slug";
        if($publicOnly)$sql.=" AND ro.status='published' AND ro.opportunity_type IN ('audition','event','interest') AND (ro.registration_opens_at IS NULL OR ro.registration_opens_at<=NOW()) AND (ro.registration_closes_at IS NULL OR ro.registration_closes_at>=NOW())";
        $sql.=' LIMIT 1';$stmt=$db->prepare($sql);$stmt->execute(['slug'=>$slug]);$row=$stmt->fetch();if(!$row)return null;
        $row['registration_count']=self::activeCount($db,(int)$row['id']);$row['is_full']=$row['capacity']!==null && $row['registration_count'] >= (int)$row['capacity'];return $row;
    }

    public static function submit(PDO $db,array $opportunity,array $input,string $basePath): array
    {
        if(!in_array((string)($opportunity['opportunity_type']??''),self::CONNECT_PUBLIC_TYPES,true))throw new RuntimeException('This opportunity is not registered through CTSMD Connect.');
        $first=trim((string)($input['participant_first_name']??''));$last=trim((string)($input['participant_last_name']??''));$age=(string)($input['participant_age_group']??'');$email=strtolower(trim((string)($input['registrant_email']??'')));$phone=trim((string)($input['registrant_phone']??''));$guardianName=trim((string)($input['guardian_name']??''));$guardianEmail=strtolower(trim((string)($input['guardian_email']??'')));$guardianPhone=trim((string)($input['guardian_phone']??''));$notes=trim((string)($input['notes']??''));$allergies=trim((string)($input['allergy_information']??''));$accommodations=trim((string)($input['accommodation_information']??''));
        $declaredAge=null;$ageEvidence='guest_declared';$linkedDob=null;
        $linkedStudentId=0;$guardianId=Auth::userId()?:0;
        if($guardianId>0&&(int)($input['student_user_id']??0)>0){
            $linkedStudentId=(int)$input['student_user_id'];
            $child=$db->prepare("SELECT u.first_name,u.last_name,u.date_of_birth,g.email guardian_email,CONCAT(g.first_name,' ',g.last_name) guardian_name,g.contact_phone guardian_phone FROM family_relationships fr JOIN users u ON u.id=fr.student_user_id AND u.active=1 JOIN users g ON g.id=fr.guardian_user_id AND g.active=1 AND g.account_status='active' AND g.email_verified_at IS NOT NULL WHERE fr.guardian_user_id=:guardian AND fr.student_user_id=:student AND fr.status='active' AND fr.relationship_type IN ('parent','guardian') LIMIT 1");
            $child->execute(['guardian'=>$guardianId,'student'=>$linkedStudentId]);$linked=$child->fetch(PDO::FETCH_ASSOC);
            if(!$linked)throw new RuntimeException('The selected child is not linked to your verified guardian account.');
            $linkedDob=$linked['date_of_birth']?:null;
            $assessment=PersonEligibility::evaluate($opportunity,$linkedDob);
            if($assessment['status']==='ineligible')throw new RuntimeException('This child does not meet the age requirements for this audition.');
            if($assessment['status']==='needs_review')throw new RuntimeException('Add your child’s date of birth before registering for this audition.');
            $first=(string)$linked['first_name'];$last=(string)$linked['last_name'];
            $asOf=PersonEligibility::cutoff($opportunity)?:date('Y-m-d');
            if($linked['date_of_birth'])$age=PersonEligibility::group((string)$linked['date_of_birth'],$asOf);
            $guardianEmail=mb_strtolower((string)$linked['guardian_email']);$guardianName=(string)$linked['guardian_name'];$guardianPhone=(string)($linked['guardian_phone']?:$phone);
            $email=$guardianEmail;
            $ageEvidence='account_dob';
        }else{
            $declaredAge=PersonEligibility::declaredAge($input['participant_declared_age']??null);
            $age=$declaredAge<13?'under_13':($declaredAge<18?'13_17':'adult');
            $assessment=PersonEligibility::evaluateDeclared($opportunity,$declaredAge);
            if($assessment['status']==='ineligible')throw new RuntimeException('The selected age does not meet this signup’s age requirements.');
        }
        if($first===''||mb_strlen($first)>100||$last===''||mb_strlen($last)>100)throw new RuntimeException('Enter the participant’s first and last name.');
        if(!in_array($age,['under_13','13_17','adult'],true))throw new RuntimeException('Choose the participant age group.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid contact email address.');
        if(mb_strlen($phone)>40||mb_strlen($guardianPhone)>40)throw new RuntimeException('Keep phone numbers under 40 characters.');
        if(mb_strlen($notes)>2000)throw new RuntimeException('Keep notes under 2,000 characters.');
        if(mb_strlen($allergies)>2000||mb_strlen($accommodations)>2000)throw new RuntimeException('Limit allergy and accommodation responses to 2,000 characters each.');
        if($age!=='adult'){
            if($guardianName===''||mb_strlen($guardianName)>190)throw new RuntimeException('A parent or guardian name is required for participants under 18.');
            if(!filter_var($guardianEmail,FILTER_VALIDATE_EMAIL))throw new RuntimeException('A valid parent or guardian email is required for participants under 18.');
        }else{$guardianName=$guardianName!==''?$guardianName:null;$guardianEmail=$guardianEmail!==''&&filter_var($guardianEmail,FILTER_VALIDATE_EMAIL)?$guardianEmail:null;}
        $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
        $db->beginTransaction();
        try{
            $lock=$db->prepare("SELECT * FROM registration_opportunities WHERE id=:id AND status='published' AND opportunity_type IN ('audition','event','interest') AND (registration_opens_at IS NULL OR registration_opens_at<=NOW()) AND (registration_closes_at IS NULL OR registration_closes_at>=NOW()) FOR UPDATE");$lock->execute(['id'=>(int)$opportunity['id']]);$current=$lock->fetch();if(!$current)throw new RuntimeException('Registration for this opportunity is no longer open in CTSMD Connect.');
            // Recheck limits and date while the opportunity row is locked, preventing stale-form bypass.
            $assessment=$linkedStudentId>0?PersonEligibility::evaluate($current,$linkedDob):PersonEligibility::evaluateDeclared($current,$declaredAge);
            if($assessment['status']==='ineligible')throw new RuntimeException('Participant does not meet the current signup age requirements.');
            if($assessment['status']==='needs_review')throw new RuntimeException('A birth date is required to confirm eligibility for this Student account.');
            if($linkedStudentId>0&&$linkedDob)$age=PersonEligibility::group($linkedDob,PersonEligibility::cutoff($current)?:date('Y-m-d'));
            $count=self::activeCount($db,(int)$current['id']);$status=$current['capacity']!==null&&$count>=(int)$current['capacity']?'waitlisted':'submitted';
            $stmt=$db->prepare("INSERT INTO registration_submissions (opportunity_id,participant_first_name,participant_last_name,participant_age_group,participant_declared_age,age_evidence,registrant_email,registrant_phone,guardian_name,guardian_email,guardian_phone,notes,allergy_information,accommodation_information,status,manage_token_hash) VALUES (:opportunity,:first,:last,:age,:declared_age,:age_evidence,:email,:phone,:guardian_name,:guardian_email,:guardian_phone,:notes,:allergies,:accommodations,:status,:token)");
            $stmt->execute(['opportunity'=>(int)$current['id'],'first'=>$first,'last'=>$last,'age'=>$age,'declared_age'=>$declaredAge,'age_evidence'=>$ageEvidence,'email'=>$email,'phone'=>$phone!==''?$phone:null,'guardian_name'=>$guardianName,'guardian_email'=>$guardianEmail,'guardian_phone'=>$guardianPhone!==''?$guardianPhone:null,'notes'=>$notes!==''?$notes:null,'allergies'=>$allergies!==''?$allergies:null,'accommodations'=>$accommodations!==''?$accommodations:null,'status'=>$status,'token'=>$hash]);$id=(int)$db->lastInsertId();
            $audit=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (NULL,'registration.submitted','registration_submission',:id,:summary,:meta)");$audit->execute(['id'=>$id,'summary'=>'Received a public registration.','meta'=>json_encode(['opportunity_id'=>(int)$current['id'],'status'=>$status],JSON_THROW_ON_ERROR)]);
            if($linkedStudentId>0&&$current['opportunity_type']!=='audition'){
                $link=$db->prepare("INSERT INTO registration_submission_links (submission_id,participant_user_id,guardian_user_id,linked_by_user_id,link_method) VALUES (:submission,:student,:guardian,:actor,'existing')");
                $link->execute(['submission'=>$id,'student'=>$linkedStudentId,'guardian'=>$guardianId,'actor'=>$guardianId]);
            }
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof RuntimeException)throw $e;throw new RuntimeException('Registration could not be submitted. Please try again.');}
        $linked=false;
        if($linkedStudentId>0&&$current['opportunity_type']==='audition'){try{AuditionIntakeBridge::claimWithToken($db,$token,$guardianId,$linkedStudentId);$linked=true;}catch(Throwable $e){error_log('Audition account linkage after registration: '.$e->getMessage());}}
        if($linkedStudentId>0&&$current['opportunity_type']!=='audition')$linked=true;
        self::queueConfirmation($db,$current,$first,$last,$email,$status,$token,$basePath);
        if($guardianEmail && $guardianEmail!==$email)self::queueConfirmation($db,$current,$first,$last,$guardianEmail,$status,$token,$basePath);
        return ['id'=>$id,'status'=>$status,'token'=>$token,'linked'=>$linked];
    }

    public static function submissionForToken(PDO $db,string $token): ?array
    {
        if(!preg_match('/^[a-f0-9]{64}$/',$token))return null;$stmt=$db->prepare("SELECT rs.*,ro.title opportunity_title,ro.slug,ro.starts_at,ro.location,rsl.participant_user_id linked_student_id FROM registration_submissions rs JOIN registration_opportunities ro ON ro.id=rs.opportunity_id LEFT JOIN registration_submission_links rsl ON rsl.submission_id=rs.id WHERE rs.manage_token_hash=:hash LIMIT 1");$stmt->execute(['hash'=>hash('sha256',$token)]);return $stmt->fetch()?:null;
    }

    public static function cancelByToken(PDO $db,string $token): void
    {
        $submission=self::submissionForToken($db,$token);if(!$submission)throw new RuntimeException('That registration link is invalid.');if(in_array($submission['status'],['cancelled','declined'],true))return;
        $db->prepare("UPDATE registration_submissions SET status='cancelled',updated_at=CURRENT_TIMESTAMP WHERE id=:id")->execute(['id'=>(int)$submission['id']]);$audit=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (NULL,'registration.cancelled','registration_submission',:id,'Public registration cancelled by registrant.',NULL)");$audit->execute(['id'=>(int)$submission['id']]);AuditionIntakeBridge::withdrawPending($db,(int)$submission['id'],null);
    }

    public static function allOpportunities(PDO $db): array
    {
        return $db->query("SELECT ro.*,p.title production_title,(SELECT COUNT(*) FROM registration_submissions rs WHERE rs.opportunity_id=ro.id AND rs.status IN ('submitted','accepted')) active_registrations,(SELECT COUNT(*) FROM registration_submissions rs WHERE rs.opportunity_id=ro.id AND rs.status='waitlisted') waitlisted_registrations FROM registration_opportunities ro LEFT JOIN productions p ON p.id=ro.production_id ORDER BY FIELD(ro.status,'published','draft','closed','archived'),ro.starts_at IS NULL,ro.starts_at,ro.title")->fetchAll();
    }

    public static function submissions(PDO $db,int $opportunityId): array
    {
        $stmt=$db->prepare("SELECT rs.*,CONCAT(u.first_name,' ',u.last_name) reviewer FROM registration_submissions rs LEFT JOIN users u ON u.id=rs.reviewed_by_user_id WHERE rs.opportunity_id=:id ORDER BY rs.submitted_at DESC,rs.id DESC");$stmt->execute(['id'=>$opportunityId]);return $stmt->fetchAll();
    }

    private static function activeCount(PDO $db,int $opportunityId): int
    {
        $stmt=$db->prepare("SELECT COUNT(*) FROM registration_submissions WHERE opportunity_id=:id AND status IN ('submitted','accepted')");$stmt->execute(['id'=>$opportunityId]);return (int)$stmt->fetchColumn();
    }

    private static function queueConfirmation(PDO $db,array $opportunity,string $first,string $last,string $email,string $status,string $token,string $basePath): void
    {
        try{
            $manage=self::absoluteUrl('/register/manage?token='.$token,$basePath);$label=$status==='waitlisted'?'waitlist request':'registration';$subject='CTSMD '.$label.' received · '.$opportunity['title'];$text="Hi {$first},\n\nWe received the {$label} for {$first} {$last} for {$opportunity['title']}.";
            if(!empty($opportunity['confirmation_message']))$text.="\n\n".$opportunity['confirmation_message'];$text.="\n\nManage or cancel this registration:\n{$manage}\n\nCTSMD Connect";
            MailService::queue($db,null,$email,$first.' '.$last,'system',$subject,$text,null,'registration:'.(int)$opportunity['id'].':'.$email.':'.hash('sha256',$token));
        }catch(Throwable){}
    }

    private static function absoluteUrl(string $path,string $basePath): string
    {
        $configured=rtrim((string)(getenv('APP_URL')?:''),'/');if($configured!=='')return $configured.(str_starts_with($path,'/')?$path:'/'.$path);$scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';$host=(string)($_SERVER['HTTP_HOST']??'localhost');return $scheme.'://'.$host.($basePath?:'').(str_starts_with($path,'/')?$path:'/'.$path);
    }
}
