<?php

declare(strict_types=1);

require_once __DIR__ . '/MailService.php';

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
        $first=trim((string)($input['participant_first_name']??''));$last=trim((string)($input['participant_last_name']??''));$age=(string)($input['participant_age_group']??'');$email=strtolower(trim((string)($input['registrant_email']??'')));$phone=trim((string)($input['registrant_phone']??''));$guardianName=trim((string)($input['guardian_name']??''));$guardianEmail=strtolower(trim((string)($input['guardian_email']??'')));$guardianPhone=trim((string)($input['guardian_phone']??''));$notes=trim((string)($input['notes']??''));
        if($first===''||mb_strlen($first)>100||$last===''||mb_strlen($last)>100)throw new RuntimeException('Enter the participant’s first and last name.');
        if(!in_array($age,['under_13','13_17','adult'],true))throw new RuntimeException('Choose the participant age group.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid contact email address.');
        if(mb_strlen($phone)>40||mb_strlen($guardianPhone)>40)throw new RuntimeException('Keep phone numbers under 40 characters.');
        if(mb_strlen($notes)>2000)throw new RuntimeException('Keep notes under 2,000 characters.');
        if($age!=='adult'){
            if($guardianName===''||mb_strlen($guardianName)>190)throw new RuntimeException('A parent or guardian name is required for participants under 18.');
            if(!filter_var($guardianEmail,FILTER_VALIDATE_EMAIL))throw new RuntimeException('A valid parent or guardian email is required for participants under 18.');
        }else{$guardianName=$guardianName!==''?$guardianName:null;$guardianEmail=$guardianEmail!==''&&filter_var($guardianEmail,FILTER_VALIDATE_EMAIL)?$guardianEmail:null;}
        $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
        $db->beginTransaction();
        try{
            $lock=$db->prepare("SELECT * FROM registration_opportunities WHERE id=:id AND status='published' AND opportunity_type IN ('audition','event','interest') AND (registration_opens_at IS NULL OR registration_opens_at<=NOW()) AND (registration_closes_at IS NULL OR registration_closes_at>=NOW()) FOR UPDATE");$lock->execute(['id'=>(int)$opportunity['id']]);$current=$lock->fetch();if(!$current)throw new RuntimeException('Registration for this opportunity is no longer open in CTSMD Connect.');
            $count=self::activeCount($db,(int)$current['id']);$status=$current['capacity']!==null&&$count>=(int)$current['capacity']?'waitlisted':'submitted';
            $stmt=$db->prepare("INSERT INTO registration_submissions (opportunity_id,participant_first_name,participant_last_name,participant_age_group,registrant_email,registrant_phone,guardian_name,guardian_email,guardian_phone,notes,status,manage_token_hash) VALUES (:opportunity,:first,:last,:age,:email,:phone,:guardian_name,:guardian_email,:guardian_phone,:notes,:status,:token)");
            $stmt->execute(['opportunity'=>(int)$current['id'],'first'=>$first,'last'=>$last,'age'=>$age,'email'=>$email,'phone'=>$phone!==''?$phone:null,'guardian_name'=>$guardianName,'guardian_email'=>$guardianEmail,'guardian_phone'=>$guardianPhone!==''?$guardianPhone:null,'notes'=>$notes!==''?$notes:null,'status'=>$status,'token'=>$hash]);$id=(int)$db->lastInsertId();
            $audit=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (NULL,'registration.submitted','registration_submission',:id,:summary,:meta)");$audit->execute(['id'=>$id,'summary'=>'Received a public registration.','meta'=>json_encode(['opportunity_id'=>(int)$current['id'],'status'=>$status],JSON_THROW_ON_ERROR)]);
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof RuntimeException)throw $e;throw new RuntimeException('Registration could not be submitted. Please try again.');}
        self::queueConfirmation($db,$current,$first,$last,$email,$status,$token,$basePath);
        if($guardianEmail && $guardianEmail!==$email)self::queueConfirmation($db,$current,$first,$last,$guardianEmail,$status,$token,$basePath);
        return ['id'=>$id,'status'=>$status,'token'=>$token];
    }

    public static function submissionForToken(PDO $db,string $token): ?array
    {
        if(!preg_match('/^[a-f0-9]{64}$/',$token))return null;$stmt=$db->prepare("SELECT rs.*,ro.title opportunity_title,ro.slug,ro.starts_at,ro.location FROM registration_submissions rs JOIN registration_opportunities ro ON ro.id=rs.opportunity_id WHERE rs.manage_token_hash=:hash LIMIT 1");$stmt->execute(['hash'=>hash('sha256',$token)]);return $stmt->fetch()?:null;
    }

    public static function cancelByToken(PDO $db,string $token): void
    {
        $submission=self::submissionForToken($db,$token);if(!$submission)throw new RuntimeException('That registration link is invalid.');if(in_array($submission['status'],['cancelled','declined'],true))return;
        $db->prepare("UPDATE registration_submissions SET status='cancelled',updated_at=CURRENT_TIMESTAMP WHERE id=:id")->execute(['id'=>(int)$submission['id']]);$audit=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (NULL,'registration.cancelled','registration_submission',:id,'Public registration cancelled by registrant.',NULL)");$audit->execute(['id'=>(int)$submission['id']]);
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
