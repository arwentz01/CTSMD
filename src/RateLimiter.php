<?php

declare(strict_types=1);

final class RateLimitExceeded extends RuntimeException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct('Too many attempts. Please wait and try again.');
    }
}

final class RateLimiter
{
    public static function enforce(PDO $db,string $action,string $subject,int $limit,int $windowSeconds,int $blockSeconds):void
    {
        $action=substr(preg_replace('/[^a-z0-9_.-]/i','-',strtolower($action))??'unknown',0,64);
        $hash=self::subjectHash($subject);
        $db->beginTransaction();
        try{
            $select=$db->prepare('SELECT attempts,window_started_at,blocked_until FROM auth_rate_limits WHERE action_key=:action AND subject_hash=:hash FOR UPDATE');
            $select->execute(['action'=>$action,'hash'=>$hash]);$row=$select->fetch();$now=new DateTimeImmutable();
            if(!$row){
                $db->prepare('INSERT INTO auth_rate_limits (action_key,subject_hash,window_started_at,attempts,last_attempt_at) VALUES (:action,:hash,CURRENT_TIMESTAMP,1,CURRENT_TIMESTAMP)')->execute(['action'=>$action,'hash'=>$hash]);
                $db->commit();return;
            }
            $blocked=!empty($row['blocked_until'])?new DateTimeImmutable((string)$row['blocked_until']):null;
            if($blocked&&$blocked>$now){$db->commit();throw new RateLimitExceeded(max(1,$blocked->getTimestamp()-$now->getTimestamp()));}
            $started=new DateTimeImmutable((string)$row['window_started_at']);$attempts=(int)$row['attempts'];
            if($started->getTimestamp()<=($now->getTimestamp()-$windowSeconds)){$attempts=1;$started=$now;}else{$attempts++;}
            $blockedUntil=$attempts>$limit?$now->modify('+'.$blockSeconds.' seconds'):null;
            $update=$db->prepare('UPDATE auth_rate_limits SET attempts=:attempts,window_started_at=:started,blocked_until=:blocked,last_attempt_at=CURRENT_TIMESTAMP WHERE action_key=:action AND subject_hash=:hash');
            $update->execute(['attempts'=>$attempts,'started'=>$started->format('Y-m-d H:i:s'),'blocked'=>$blockedUntil?->format('Y-m-d H:i:s'),'action'=>$action,'hash'=>$hash]);
            $db->commit();
            if($blockedUntil)throw new RateLimitExceeded($blockSeconds);
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }

    public static function clear(PDO $db,string $action,string $subject):void
    {
        $s=$db->prepare('DELETE FROM auth_rate_limits WHERE action_key=:action AND subject_hash=:hash');
        $s->execute(['action'=>substr(strtolower($action),0,64),'hash'=>self::subjectHash($subject)]);
    }

    public static function requestSubject(string $identifier=''):string
    {
        $ip=trim((string)($_SERVER['REMOTE_ADDR']??'unknown'));
        return $ip.'|'.mb_strtolower(trim($identifier));
    }

    private static function subjectHash(string $subject):string
    {
        $secret=(string)(getenv('APP_KEY')?:($_ENV['APP_KEY']??'ctsmd-rate-limit'));
        return hash_hmac('sha256',$subject,$secret);
    }
}
