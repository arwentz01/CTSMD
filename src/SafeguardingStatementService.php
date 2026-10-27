<?php

declare(strict_types=1);

require_once __DIR__.'/AccessPolicy.php';
require_once __DIR__.'/MailService.php';

/**
 * External statement invitations are one-use capabilities. The public page
 * sees only the per-request shared snapshot, never the safeguarding case itself.
 */
final class SafeguardingStatementService
{
    public static function saveSharedDescription(PDO $db,array $actor,int $caseId,string $description):void
    {
        self::authorize($actor);
        $description=trim($description);
        if(mb_strlen($description)>2000)throw new RuntimeException('The recipient-facing description must be 2,000 characters or less.');
        $db->prepare('UPDATE safeguarding_cases SET shared_incident_description=:description WHERE id=:id')->execute(['description'=>$description!==''?$description:null,'id'=>$caseId]);
        self::audit($db,(int)$actor['id'],$caseId,'safeguarding.statement_summary_updated',['shared_description_changed'=>true]);
    }

    public static function invite(PDO $db,array $actor,int $caseId,array $input,string $basePath):int
    {
        self::authorize($actor);
        $name=trim((string)($input['recipient_name']??''));
        $email=mb_strtolower(trim((string)($input['recipient_email']??'')));
        $description=trim((string)($input['shared_description']??''));
        $publicTitle=trim((string)($input['shared_title']??''));
        if($publicTitle===''||mb_strlen($publicTitle)>190)throw new RuntimeException('Enter a recipient-facing case name up to 190 characters.');
        if($name===''||mb_strlen($name)>190)throw new RuntimeException('Enter the recipient’s name.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($email)>254)throw new RuntimeException('Enter a valid recipient email address.');
        if($description===''||mb_strlen($description)>2000)throw new RuntimeException('Write a brief, shareable incident description before sending.');
        $days=(int)($input['expires_in_days']??7);
        if(!in_array($days,[3,7,14],true))throw new RuntimeException('Choose a supported invitation expiry.');
        $case=$db->prepare("SELECT sc.id,sc.title,sc.case_code,sc.occurred_at,p.title production_title FROM safeguarding_cases sc LEFT JOIN productions p ON p.id=sc.production_id WHERE sc.id=:id AND sc.status NOT IN ('closed') LIMIT 1");
        $case->execute(['id'=>$caseId]);$row=$case->fetch(PDO::FETCH_ASSOC);
        if(!$row)throw new RuntimeException('That case is not available for statement requests.');
        $token=bin2hex(random_bytes(32));
        $hash=hash('sha256',$token);
        $expiry=(new DateTimeImmutable('+'.$days.' days'))->format('Y-m-d H:i:s');
        $link=self::absoluteUrl($basePath.'/incident-statement?token='.$token);
        $db->beginTransaction();
        try{
            $stmt=$db->prepare("INSERT INTO safeguarding_statement_requests (case_id,recipient_name,recipient_email,token_hash,shared_title,shared_description,shared_occurred_at,shared_production_title,expires_at,invited_by_user_id) VALUES (:case,:name,:email,:hash,:title,:description,:occurred,:production,:expiry,:actor)");
            $stmt->execute(['case'=>$caseId,'name'=>$name,'email'=>$email,'hash'=>$hash,'title'=>$publicTitle,'description'=>$description,'occurred'=>$row['occurred_at'],'production'=>$row['production_title'],'expiry'=>$expiry,'actor'=>(int)$actor['id']]);
            $id=(int)$db->lastInsertId();
            $body="CTSMD has requested your individual account of an incident.\n\nOpen your private statement form:\n".$link."\n\nThis link expires in ".$days." days. Please do not forward it. Your response will become read-only once submitted.\n\nIf you believe you received this request in error, contact CTSMD directly.\n\nChildren’s Theatre of Southern Maryland";
            // Avoid subject lines or email body revealing case names or confidential facts.
            MailService::queue($db,null,$email,$name,'system','CTSMD confidential statement request',$body,null,'safeguarding-statement-'.$id);
            self::audit($db,(int)$actor['id'],$caseId,'safeguarding.statement_invited',['statement_request_id'=>$id,'expires_in_days'=>$days]);
            $db->commit();
            return $id;
        }catch(Throwable $e){
            if($db->inTransaction())$db->rollBack();
            throw $e instanceof RuntimeException?$e:new RuntimeException('The statement request could not be queued.');
        }
    }

    public static function requests(PDO $db,array $actor,int $caseId):array
    {
        self::authorize($actor);
        $s=$db->prepare("SELECT id,recipient_name,recipient_email,shared_title,shared_description,shared_occurred_at,shared_production_title,status,invited_at,expires_at,submitted_at,statement_body,statement_witnesses,statement_followup,attested_name,accepted_attestation,revoked_at FROM safeguarding_statement_requests WHERE case_id=:id ORDER BY COALESCE(submitted_at,invited_at),id");
        $s->execute(['id'=>$caseId]);return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function revoke(PDO $db,array $actor,int $caseId,int $requestId):void
    {
        self::authorize($actor);
        $s=$db->prepare("UPDATE safeguarding_statement_requests SET status='revoked',revoked_at=CURRENT_TIMESTAMP,revoked_by_user_id=:actor WHERE id=:request AND case_id=:case AND status='pending'");
        $s->execute(['actor'=>(int)$actor['id'],'request'=>$requestId,'case'=>$caseId]);
        if($s->rowCount()!==1)throw new RuntimeException('Only pending statement requests can be revoked.');
        self::audit($db,(int)$actor['id'],$caseId,'safeguarding.statement_revoked',['statement_request_id'=>$requestId]);
    }

    public static function byToken(PDO $db,string $token):?array
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$token))return null;
        $s=$db->prepare('SELECT id,recipient_name,shared_title,shared_description,shared_occurred_at,shared_production_title,status,expires_at,submitted_at FROM safeguarding_statement_requests WHERE token_hash=:hash LIMIT 1');
        $s->execute(['hash'=>hash('sha256',$token)]);return $s->fetch(PDO::FETCH_ASSOC)?:null;
    }

    public static function submit(PDO $db,string $token,array $input):void
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new RuntimeException('This statement link is unavailable.');
        $account=trim((string)($input['statement_body']??''));
        $witnesses=trim((string)($input['statement_witnesses']??''));
        $followup=trim((string)($input['statement_followup']??''));
        $attestedName=trim((string)($input['attested_name']??''));
        if($account===''||mb_strlen($account)>12000)throw new RuntimeException('Provide your account of events (up to 12,000 characters).');
        if(mb_strlen($witnesses)>2000||mb_strlen($followup)>2000)throw new RuntimeException('Additional answers may be up to 2,000 characters each.');
        if($attestedName===''||mb_strlen($attestedName)>190||empty($input['attestation']))throw new RuntimeException('Enter your name and confirm this is your own account.');
        $db->beginTransaction();
        try{
            $s=$db->prepare('SELECT id,case_id,recipient_name,status,expires_at FROM safeguarding_statement_requests WHERE token_hash=:hash FOR UPDATE');
            $s->execute(['hash'=>hash('sha256',$token)]);$request=$s->fetch(PDO::FETCH_ASSOC);
            if(!$request||$request['status']!=='pending'||strtotime((string)$request['expires_at'])<=time())throw new RuntimeException('This statement link has expired or has already been used.');
            $u=$db->prepare("UPDATE safeguarding_statement_requests SET status='submitted',submitted_at=CURRENT_TIMESTAMP,statement_body=:body,statement_witnesses=:witnesses,statement_followup=:followup,attested_name=:name,accepted_attestation=1 WHERE id=:id AND status='pending'");
            $u->execute(['body'=>$account,'witnesses'=>$witnesses!==''?$witnesses:null,'followup'=>$followup!==''?$followup:null,'name'=>$attestedName,'id'=>$request['id']]);
            if($u->rowCount()!==1)throw new RuntimeException('This statement was already received.');
            // External respondent is not impersonated as an internal CTSMD user.
            $a=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (NULL,'safeguarding.statement_received','safeguarding_case',:case,'A requested account of an incident was submitted.',:metadata)");
            $a->execute(['case'=>$request['case_id'],'metadata'=>json_encode(['statement_request_id'=>(int)$request['id']],JSON_THROW_ON_ERROR)]);
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }

    private static function authorize(array $actor):void
    {
        if(!AccessPolicy::canManageSafeguarding($actor))throw new RuntimeException('Safeguarding case authority is required.');
    }

    private static function audit(PDO $db,int $actorId,int $caseId,string $event,array $data):void
    {
        $s=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,:event,'safeguarding_case',:case,'Safeguarding statement request event.',:metadata)");
        $s->execute(['actor'=>$actorId,'event'=>$event,'case'=>$caseId,'metadata'=>json_encode($data,JSON_THROW_ON_ERROR)]);
    }

    private static function absoluteUrl(string $path):string
    {
        $configured=rtrim((string)(getenv('APP_URL')?:''),'/');
        if($configured!=='')return $configured.$path;
        $scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
        return $scheme.'://'.(string)($_SERVER['HTTP_HOST']??'localhost').$path;
    }
}
