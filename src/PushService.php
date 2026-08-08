<?php

declare(strict_types=1);

final class PushService
{
    public static function publicKey(): string{return trim((string)(getenv('PUSH_VAPID_PUBLIC_KEY')?:($_ENV['PUSH_VAPID_PUBLIC_KEY']??'')));}
    public static function configured(): bool{return self::publicKey()!==''&&self::privatePem()!=='';}

    public static function subscribe(PDO $db,int $userId,array $subscription,string $userAgent=''):void
    {
        $endpoint=trim((string)($subscription['endpoint']??''));$keys=$subscription['keys']??[];$p256dh=trim((string)($keys['p256dh']??''));$auth=trim((string)($keys['auth']??''));
        if($endpoint===''||$p256dh===''||$auth==='')throw new RuntimeException('The browser did not provide a complete push subscription.');
        if(!str_starts_with($endpoint,'https://'))throw new RuntimeException('Push subscriptions require HTTPS.');
        $platform=self::platform($userAgent);$label=match($platform){'ios'=>'iPhone / iPad','android'=>'Android',default=>'Web browser'};
        $s=$db->prepare("INSERT INTO push_subscriptions (user_id,endpoint,endpoint_hash,p256dh,auth_secret,user_agent,device_label,platform,status,last_seen_at) VALUES (:user,:endpoint,:hash,:p256dh,:auth,:ua,:label,:platform,'active',CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),endpoint=VALUES(endpoint),p256dh=VALUES(p256dh),auth_secret=VALUES(auth_secret),user_agent=VALUES(user_agent),device_label=VALUES(device_label),platform=VALUES(platform),status='active',last_seen_at=CURRENT_TIMESTAMP");
        $s->execute(['user'=>$userId,'endpoint'=>$endpoint,'hash'=>hash('sha256',$endpoint),'p256dh'=>$p256dh,'auth'=>$auth,'ua'=>mb_substr($userAgent,0,500),'label'=>$label,'platform'=>$platform]);
    }

    public static function unsubscribe(PDO $db,int $userId,string $endpoint):void{$s=$db->prepare("UPDATE push_subscriptions SET status='revoked' WHERE user_id=:user AND endpoint_hash=:hash");$s->execute(['user'=>$userId,'hash'=>hash('sha256',$endpoint)]);}

    public static function queue(PDO $db,int $userId,string $category,string $title,string $body,?string $actionPath=null,string $urgency='normal',?string $tag=null):int
    {
        $category=in_array($category,['schedule','forms','volunteer','community','messages','general'],true)?$category:'general';$urgency=in_array($urgency,['very-low','low','normal','high'],true)?$urgency:'normal';
        if(!self::pushAllowed($db,$userId,$category))return 0;
        $s=$db->prepare("INSERT INTO push_queue (user_id,category,title,body,action_path,tag,urgency) VALUES (:user,:category,:title,:body,:path,:tag,:urgency)");$s->execute(['user'=>$userId,'category'=>$category,'title'=>mb_substr($title,0,190),'body'=>mb_substr($body,0,1000),'path'=>$actionPath,'tag'=>$tag?mb_substr($tag,0,64):null,'urgency'=>$urgency]);return(int)$db->lastInsertId();
    }

    public static function processQueue(PDO $db,int $limit=25):array
    {
        if(!self::configured())throw new RuntimeException('Web Push is not configured. Set PUSH_VAPID_PUBLIC_KEY and PUSH_VAPID_PRIVATE_KEY_B64.');$sent=0;$failed=0;
        $q=$db->prepare("SELECT * FROM push_queue WHERE status='queued' AND available_at<=CURRENT_TIMESTAMP ORDER BY id LIMIT ".max(1,min($limit,100)));$q->execute();
        foreach($q->fetchAll() as $row){$db->prepare("UPDATE push_queue SET status='processing',claimed_at=CURRENT_TIMESTAMP,attempt_count=attempt_count+1 WHERE id=:id AND status='queued'")->execute(['id'=>$row['id']]);
            $subs=$db->prepare("SELECT * FROM push_subscriptions WHERE user_id=:user AND status='active'");$subs->execute(['user'=>$row['user_id']]);$subscriptions=$subs->fetchAll();if(!$subscriptions){$db->prepare("UPDATE push_queue SET status='suppressed',completed_at=CURRENT_TIMESTAMP,last_error='No active push-enabled device.' WHERE id=:id")->execute(['id'=>$row['id']]);continue;}
            $ok=0;$bad=0;foreach($subscriptions as $sub){try{$result=self::send($sub,$row);$status=(int)$result['status'];$expired=in_array($status,[404,410],true);$success=$status>=200&&$status<300;$log=$db->prepare("INSERT INTO push_delivery_log (queue_id,subscription_id,http_status,result,error_message) VALUES (:queue,:sub,:status,:result,:error)");$log->execute(['queue'=>$row['id'],'sub'=>$sub['id'],'status'=>$status?:null,'result'=>$success?'sent':($expired?'expired':'rejected'),'error'=>$success?null:mb_substr((string)$result['body'],0,1000)]);if($success){$ok++;$db->prepare("UPDATE push_subscriptions SET last_success_at=CURRENT_TIMESTAMP,failure_count=0 WHERE id=:id")->execute(['id'=>$sub['id']]);}else{$bad++;$db->prepare("UPDATE push_subscriptions SET status=:status,last_failure_at=CURRENT_TIMESTAMP,failure_count=failure_count+1 WHERE id=:id")->execute(['status'=>$expired?'expired':'active','id'=>$sub['id']]);}}catch(Throwable $e){$bad++;$db->prepare("INSERT INTO push_delivery_log (queue_id,subscription_id,result,error_message) VALUES (:queue,:sub,'error',:error)")->execute(['queue'=>$row['id'],'sub'=>$sub['id'],'error'=>mb_substr($e->getMessage(),0,1000)]);}}
            $status=$ok>0?($bad>0?'partial':'sent'):'failed';$db->prepare("UPDATE push_queue SET status=:status,completed_at=CURRENT_TIMESTAMP,last_error=:error WHERE id=:id")->execute(['status'=>$status,'error'=>$bad&&$ok===0?'All device deliveries failed.':null,'id'=>$row['id']]);$sent+=$ok;$failed+=$bad;
        }return['sent'=>$sent,'failed'=>$failed];
    }

    private static function pushAllowed(PDO $db,int $userId,string $category):bool
    {
        $s=$db->prepare('SELECT * FROM notification_preferences WHERE user_id=:user LIMIT 1');$s->execute(['user'=>$userId]);$p=$s->fetch();if(!$p)return true;if(!(bool)($p['push_enabled']??1))return false;$key=match($category){'schedule'=>'push_schedule','forms'=>'push_forms','volunteer'=>'push_volunteer','community'=>'push_community','messages'=>'push_messages',default=>null};return$key===null||(bool)($p[$key]??1);
    }

    private static function send(array $sub,array $notification):array
    {
        $base=rtrim((string)(getenv('APP_BASE_PATH')?:($_ENV['APP_BASE_PATH']??'')),'/');$action=(string)($notification['action_path']?:'/app');if($action!==''&&str_starts_with($action,'/'))$action=$base.$action;
        $payload=json_encode(['title'=>$notification['title'],'body'=>$notification['body'],'url'=>$action,'tag'=>$notification['tag']?:('ctsmd-'.$notification['id']),'badgeCount'=>1],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $ua=self::b64d((string)$sub['p256dh']);$auth=self::b64d((string)$sub['auth_secret']);if(strlen($ua)!==65||strlen($auth)<16)throw new RuntimeException('Stored push keys are invalid.');
        $ephemeral=openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_EC,'curve_name'=>'prime256v1']);if(!$ephemeral)throw new RuntimeException('Could not generate push encryption key.');$details=openssl_pkey_get_details($ephemeral);$ec=$details['ec']??null;if(!$ec||!isset($ec['x'],$ec['y']))throw new RuntimeException('OpenSSL did not expose the ephemeral EC key.');$asPublic="\x04".$ec['x'].$ec['y'];
        $uaKey=openssl_pkey_get_public(self::ecPublicPem($ua));if(!$uaKey)throw new RuntimeException('Could not parse browser push key.');$shared=openssl_pkey_derive($uaKey,$ephemeral,32);if($shared===false)throw new RuntimeException('Could not derive push encryption secret.');
        $prkKey=hash_hmac('sha256',$shared,$auth,true);$ikm=hash_hmac('sha256',"WebPush: info\0".$ua.$asPublic."\x01",$prkKey,true);$salt=random_bytes(16);$prk=hash_hmac('sha256',$ikm,$salt,true);$cek=substr(hash_hmac('sha256',"Content-Encoding: aes128gcm\0\x01",$prk,true),0,16);$nonce=substr(hash_hmac('sha256',"Content-Encoding: nonce\0\x01",$prk,true),0,12);
        $plain=$payload."\x02";$tag='';$cipher=openssl_encrypt($plain,'aes-128-gcm',$cek,OPENSSL_RAW_DATA,$nonce,$tag,'',16);if($cipher===false)throw new RuntimeException('Could not encrypt push payload.');$rs=max(4096,strlen($plain)+17);$body=$salt.pack('N',$rs).chr(strlen($asPublic)).$asPublic.$cipher.$tag;
        $endpoint=(string)$sub['endpoint'];$scheme=(string)parse_url($endpoint,PHP_URL_SCHEME);$host=(string)parse_url($endpoint,PHP_URL_HOST);$port=parse_url($endpoint,PHP_URL_PORT);$origin=$scheme.'://'.$host.($port?':'.$port:'');$jwt=self::vapidJwt($origin);$headers=['Content-Type: application/octet-stream','Content-Encoding: aes128gcm','TTL: 86400','Urgency: '.($notification['urgency']?:'normal'),'Authorization: vapid t='.$jwt.', k='.self::publicKey(),'Content-Length: '.strlen($body)];
        $ch=curl_init($endpoint);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HEADER=>false]);$response=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);if($response===false)throw new RuntimeException('Push service request failed: '.$error);return['status'=>$status,'body'=>(string)$response];
    }

    private static function vapidJwt(string $audience):string
    {
        $header=self::b64e(json_encode(['typ'=>'JWT','alg'=>'ES256'],JSON_THROW_ON_ERROR));$claims=self::b64e(json_encode(['aud'=>$audience,'exp'=>time()+43200,'sub'=>(getenv('PUSH_VAPID_SUBJECT')?:'mailto:notifications@ctsmd.org')],JSON_THROW_ON_ERROR));$input=$header.'.'.$claims;$key=openssl_pkey_get_private(self::privatePem());if(!$key)throw new RuntimeException('VAPID private key is invalid.');$der='';if(!openssl_sign($input,$der,$key,OPENSSL_ALGO_SHA256))throw new RuntimeException('Could not sign VAPID token.');return$input.'.'.self::b64e(self::ecdsaDerToJose($der,64));
    }

    private static function privatePem():string{$raw=trim((string)(getenv('PUSH_VAPID_PRIVATE_KEY_B64')?:($_ENV['PUSH_VAPID_PRIVATE_KEY_B64']??'')));return$raw!==''?(base64_decode($raw,true)?:''):'';}
    private static function platform(string $ua):string{$u=strtolower($ua);if(str_contains($u,'iphone')||str_contains($u,'ipad'))return'ios';if(str_contains($u,'android'))return'android';return'web';}
    private static function b64e(string $v):string{return rtrim(strtr(base64_encode($v),'+/','-_'),'=');}
    private static function b64d(string $v):string{$v=strtr($v,'-_','+/');$v.=str_repeat('=',(4-strlen($v)%4)%4);return base64_decode($v,true)?:'';}
    private static function ecPublicPem(string $point):string{$alg=self::asn1Seq(self::asn1Oid("\x2A\x86\x48\xCE\x3D\x02\x01").self::asn1Oid("\x2A\x86\x48\xCE\x3D\x03\x01\x07"));$der=self::asn1Seq($alg."\x03".self::asn1Len(strlen($point)+1)."\x00".$point);return"-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der),64,"\n")."-----END PUBLIC KEY-----\n";}
    private static function asn1Seq(string $v):string{return"\x30".self::asn1Len(strlen($v)).$v;}private static function asn1Oid(string $v):string{return"\x06".self::asn1Len(strlen($v)).$v;}private static function asn1Len(int $n):string{if($n<128)return chr($n);$b=ltrim(pack('N',$n),"\x00");return chr(0x80|strlen($b)).$b;}
    private static function ecdsaDerToJose(string $der,int $len):string{$offset=0;if(ord($der[$offset++])!==0x30)throw new RuntimeException('Invalid ECDSA signature.');self::derLen($der,$offset);if(ord($der[$offset++])!==0x02)throw new RuntimeException('Invalid ECDSA signature.');$rLen=self::derLen($der,$offset);$r=substr($der,$offset,$rLen);$offset+=$rLen;if(ord($der[$offset++])!==0x02)throw new RuntimeException('Invalid ECDSA signature.');$sLen=self::derLen($der,$offset);$s=substr($der,$offset,$sLen);$half=intdiv($len,2);$r=str_pad(ltrim($r,"\x00"),$half,"\x00",STR_PAD_LEFT);$s=str_pad(ltrim($s,"\x00"),$half,"\x00",STR_PAD_LEFT);return substr($r,-$half).substr($s,-$half);}
    private static function derLen(string $der,int &$offset):int{$n=ord($der[$offset++]);if(($n&0x80)===0)return$n;$count=$n&0x7f;$v=0;for($i=0;$i<$count;$i++)$v=($v<<8)|ord($der[$offset++]);return$v;}
}
