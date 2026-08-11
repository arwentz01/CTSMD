<?php

declare(strict_types=1);

final class MailService
{
    public static function queue(PDO $db, ?int $userId, string $email, ?string $name, string $category, string $subject, string $textBody, ?string $htmlBody = null, ?string $dedupeKey = null, ?DateTimeImmutable $availableAt = null): int
    {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('A valid recipient email is required.');
        if ($subject === '' || mb_strlen($subject) > 255) throw new RuntimeException('Email subject is invalid.');
        if ($textBody === '') throw new RuntimeException('Email body is required.');
        $allowed = ['account_security','schedule','forms','volunteer','community','digest','system'];
        if (!in_array($category, $allowed, true)) $category = 'system';
        if ($userId && !self::preferenceAllows($db, $userId, $category)) return 0;
        $stmt = $db->prepare("INSERT INTO email_queue (user_id,recipient_email,recipient_name,category,subject,text_body,html_body,status,available_at,dedupe_key) VALUES (:user,:email,:name,:category,:subject,:text,:html,'queued',:available,:dedupe)");
        try {
            $stmt->execute([
                'user'=>$userId,'email'=>$email,'name'=>$name ?: null,'category'=>$category,'subject'=>$subject,
                'text'=>$textBody,'html'=>$htmlBody,'available'=>($availableAt ?: new DateTimeImmutable())->format('Y-m-d H:i:s'),'dedupe'=>$dedupeKey ?: null,
            ]);
            return (int)$db->lastInsertId();
        } catch (PDOException $e) {
            if ($dedupeKey && str_contains($e->getMessage(),'Duplicate')) return 0;
            throw $e;
        }
    }

    public static function preferenceAllows(PDO $db, int $userId, string $category): bool
    {
        if ($category === 'account_security') return true;
        $stmt=$db->prepare('SELECT * FROM notification_preferences WHERE user_id=:user LIMIT 1');$stmt->execute(['user'=>$userId]);$p=$stmt->fetch();
        if (!$p) return true;
        if (!(bool)$p['email_enabled']) return false;
        return match($category){
            'schedule'=>(bool)$p['email_schedule'],
            'forms'=>(bool)$p['email_forms'],
            'volunteer'=>(bool)$p['email_volunteer'],
            'community'=>(bool)$p['email_community'],
            default=>true,
        };
    }

    public static function process(PDO $db, string $projectRoot, int $limit = 25): array
    {
        $limit=max(1,min(100,$limit));$processed=0;$sent=0;$failed=0;
        $db->exec("UPDATE email_queue SET status='queued',available_at=NOW(),last_error='Recovered after interrupted delivery attempt.' WHERE status='sending' AND last_attempt_at<DATE_SUB(NOW(),INTERVAL 10 MINUTE)");
        for($i=0;$i<$limit;$i++){
            $db->beginTransaction();
            try{
                $row=$db->query("SELECT * FROM email_queue WHERE status='queued' AND available_at<=NOW() ORDER BY id ASC LIMIT 1 FOR UPDATE")->fetch();
                if(!$row){$db->commit();break;}
                $db->prepare("UPDATE email_queue SET status='sending',attempts=attempts+1,last_attempt_at=CURRENT_TIMESTAMP WHERE id=:id")->execute(['id'=>(int)$row['id']]);
                $db->commit();
            }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
            $processed++;
            if(!empty($row['user_id']) && (string)$row['category']!=='account_security'){
                $account=$db->prepare("SELECT 1 FROM users WHERE id=:user AND active=1 AND account_status='active' LIMIT 1");$account->execute(['user'=>(int)$row['user_id']]);
                if(!$account->fetchColumn()){
                    $db->prepare("UPDATE email_queue SET status='suppressed',last_error='Recipient account is unavailable.' WHERE id=:id")->execute(['id'=>(int)$row['id']]);
                    self::log($db,(int)$row['id'],self::driver(),'suppressed','Recipient account is unavailable.');
                    continue;
                }
            }
            try{
                self::deliver($projectRoot,$row);
                $db->prepare("UPDATE email_queue SET status='sent',sent_at=CURRENT_TIMESTAMP,last_error=NULL WHERE id=:id")->execute(['id'=>(int)$row['id']]);
                self::log($db,(int)$row['id'],self::driver(),'sent',null);$sent++;
            }catch(Throwable $e){
                $attempts=(int)$row['attempts']+1;$status=$attempts>=3?'failed':'queued';$delay=min(60,$attempts*5);$available=(new DateTimeImmutable('+'.$delay.' minutes'))->format('Y-m-d H:i:s');
                $db->prepare("UPDATE email_queue SET status=:status,available_at=:available,last_error=:error WHERE id=:id")->execute(['status'=>$status,'available'=>$available,'error'=>mb_substr($e->getMessage(),0,1000),'id'=>(int)$row['id']]);
                self::log($db,(int)$row['id'],self::driver(),'failed',$e->getMessage());$failed++;
            }
        }
        return ['processed'=>$processed,'sent'=>$sent,'failed'=>$failed];
    }

    private static function deliver(string $projectRoot,array $row):void
    {
        $driver=self::driver();
        if($driver==='log'){self::deliverLog($projectRoot,$row);return;}
        if($driver==='mail'){self::deliverMail($row);return;}
        if($driver==='smtp'){self::deliverSmtp($row);return;}
        throw new RuntimeException('Unsupported MAIL_DRIVER.');
    }

    private static function deliverLog(string $projectRoot,array $row):void
    {
        $path=$projectRoot.'/storage/logs';if(!is_dir($path)&&!mkdir($path,0770,true)&&!is_dir($path))throw new RuntimeException('Could not prepare mail log directory.');
        $record="\n--- CTSMD EMAIL ".date('c')." ---\nTo: {$row['recipient_name']} <{$row['recipient_email']}>\nSubject: {$row['subject']}\nCategory: {$row['category']}\n\n{$row['text_body']}\n";
        if(file_put_contents($path.'/mail.log',$record,FILE_APPEND|LOCK_EX)===false)throw new RuntimeException('Could not write mail log.');
    }

    private static function deliverMail(array $row):void
    {
        $from=self::env('MAIL_FROM_ADDRESS','no-reply@localhost');$name=self::env('MAIL_FROM_NAME','CTSMD Connect');
        $headers=['MIME-Version: 1.0','Content-Type: text/plain; charset=UTF-8','From: '.$name.' <'.$from.'>'];
        if(!mail((string)$row['recipient_email'],(string)$row['subject'],(string)$row['text_body'],implode("\r\n",$headers)))throw new RuntimeException('PHP mail() delivery failed.');
    }

    private static function deliverSmtp(array $row):void
    {
        $host=self::env('MAIL_HOST','');$port=(int)self::env('MAIL_PORT','587');$encryption=strtolower(self::env('MAIL_ENCRYPTION','tls'));
        $user=self::env('MAIL_USERNAME','');$pass=self::env('MAIL_PASSWORD','');$from=self::env('MAIL_FROM_ADDRESS','');$fromName=self::env('MAIL_FROM_NAME','CTSMD Connect');
        if($host===''||$from==='')throw new RuntimeException('SMTP configuration is incomplete.');
        $target=($encryption==='ssl'?'ssl://':'').$host.':'.$port;$socket=@stream_socket_client($target,$errno,$errstr,20,STREAM_CLIENT_CONNECT);
        if(!$socket)throw new RuntimeException('SMTP connection failed: '.$errstr);stream_set_timeout($socket,20);
        $read=static function($s):string{$out='';while(($line=fgets($s,515))!==false){$out.=$line;if(strlen($line)<4||$line[3]===' ')break;}return $out;};
        $cmd=static function($s,$read,string $command,array $ok):void{fwrite($s,$command."\r\n");$response=$read($s);$code=(int)substr($response,0,3);if(!in_array($code,$ok,true))throw new RuntimeException('SMTP error '.$code.': '.trim($response));};
        $greeting=$read($socket);if((int)substr($greeting,0,3)!==220)throw new RuntimeException('SMTP greeting failed.');
        $cmd($socket,$read,'EHLO '.($_SERVER['SERVER_NAME']??'localhost'),[250]);
        if($encryption==='tls'){$cmd($socket,$read,'STARTTLS',[220]);if(!stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('SMTP TLS negotiation failed.');$cmd($socket,$read,'EHLO '.($_SERVER['SERVER_NAME']??'localhost'),[250]);}
        if($user!==''){$cmd($socket,$read,'AUTH LOGIN',[334]);$cmd($socket,$read,base64_encode($user),[334]);$cmd($socket,$read,base64_encode($pass),[235]);}
        $cmd($socket,$read,'MAIL FROM:<'.$from.'>',[250]);$cmd($socket,$read,'RCPT TO:<'.$row['recipient_email'].'>',[250,251]);$cmd($socket,$read,'DATA',[354]);
        $boundary='=_ctsmd_'.bin2hex(random_bytes(8));$toName=trim((string)$row['recipient_name']);$to=($toName!==''?$toName.' ':'').'<'.$row['recipient_email'].'>';
        $headers=['From: '.$fromName.' <'.$from.'>','To: '.$to,'Subject: '.$row['subject'],'MIME-Version: 1.0'];
        if(!empty($row['html_body'])){$headers[]='Content-Type: multipart/alternative; boundary="'.$boundary.'"';$body="--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$row['text_body']}\r\n--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$row['html_body']}\r\n--$boundary--";}else{$headers[]='Content-Type: text/plain; charset=UTF-8';$body=(string)$row['text_body'];}
        $data=implode("\r\n",$headers)."\r\n\r\n".$body;$data=preg_replace('/(?m)^\./','..',$data)."\r\n.";
        $cmd($socket,$read,$data,[250]);@fwrite($socket,"QUIT\r\n");fclose($socket);
    }

    private static function driver():string{return strtolower(self::env('MAIL_DRIVER','log'));}
    private static function env(string $key,string $default=''):string{$v=getenv($key);return ($v===false||$v==='')?$default:(string)$v;}
    private static function log(PDO $db,int $id,string $transport,string $outcome,?string $detail):void{$s=$db->prepare('INSERT INTO email_delivery_log (email_queue_id,transport,outcome,detail) VALUES (:id,:transport,:outcome,:detail)');$s->execute(['id'=>$id,'transport'=>$transport,'outcome'=>$outcome,'detail'=>$detail?mb_substr($detail,0,1000):null]);}
}
