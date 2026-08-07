<?php

declare(strict_types=1);

require_once __DIR__ . '/ProductionContext.php';
require_once __DIR__ . '/ScheduleAudience.php';
require_once __DIR__ . '/AccessPolicy.php';

final class CalendarService
{
    public static function visibleEvents(PDO $db,array $user,?DateTimeImmutable $from=null,?DateTimeImmutable $to=null,?int $productionId=null):array
    {
        $productions=ProductionContext::activeProductions($db,$user);
        if(!$productions)return [];
        $allowedIds=array_map(static fn(array $p):int=>(int)$p['id'],$productions);
        if($productionId!==null){if(!in_array($productionId,$allowedIds,true))return [];$allowedIds=[$productionId];}
        $ph=implode(',',array_fill(0,count($allowedIds),'?'));
        $params=$allowedIds;
        $where=["si.production_id IN ($ph)"];
        if($from){$where[]='si.starts_at>=?';$params[]=$from->format('Y-m-d H:i:s');}
        if($to){$where[]='si.starts_at<?';$params[]=$to->format('Y-m-d H:i:s');}
        $sql="SELECT si.id,si.production_id,si.title,si.starts_at,si.ends_at,si.family_call_at,si.location,si.visibility,si.audience_mode,si.item_type,si.status,p.title production_title,p.season FROM schedule_items si JOIN productions p ON p.id=si.production_id WHERE ".implode(' AND ',$where)." ORDER BY si.starts_at,si.id";
        $stmt=$db->prepare($sql);$stmt->execute($params);$events=[];
        foreach($stmt->fetchAll() as $row){
            if(!ScheduleAudience::userCanViewItem($db,$user,$row))continue;
            $row['group_ids']=$row['audience_mode']==='groups'?ScheduleAudience::groupIdsForItem($db,(int)$row['id']):[];
            $row['group_names']=$row['audience_mode']==='groups'?ScheduleAudience::groupNamesForItem($db,(int)$row['id']):[];
            $events[]=$row;
        }
        return $events;
    }

    public static function conflicts(array $events):array
    {
        $out=[];$count=count($events);
        for($i=0;$i<$count;$i++){
            $aStart=strtotime($events[$i]['starts_at']);$aEnd=strtotime($events[$i]['ends_at']?:$events[$i]['starts_at'].' +1 hour');
            for($j=$i+1;$j<$count;$j++){
                $bStart=strtotime($events[$j]['starts_at']);if($bStart>=$aEnd)break;
                $bEnd=strtotime($events[$j]['ends_at']?:$events[$j]['starts_at'].' +1 hour');
                if($aStart<$bEnd&&$bStart<$aEnd){$out[(int)$events[$i]['id']]=true;$out[(int)$events[$j]['id']]=true;}
            }
        }
        return $out;
    }

    public static function subscriptionToken(PDO $db,int $userId):string
    {
        $stmt=$db->prepare('SELECT token FROM calendar_subscriptions WHERE user_id=:user AND active=1 LIMIT 1');$stmt->execute(['user'=>$userId]);$token=$stmt->fetchColumn();
        if($token!==false)return (string)$token;
        $token=bin2hex(random_bytes(32));$db->prepare('INSERT INTO calendar_subscriptions (user_id,token,active) VALUES (:user,:token,1) ON DUPLICATE KEY UPDATE token=VALUES(token),active=1,rotated_at=CURRENT_TIMESTAMP')->execute(['user'=>$userId,'token'=>$token]);return $token;
    }

    public static function rotateSubscriptionToken(PDO $db,int $userId):string
    {
        $token=bin2hex(random_bytes(32));$db->prepare('INSERT INTO calendar_subscriptions (user_id,token,active) VALUES (:user,:token,1) ON DUPLICATE KEY UPDATE token=VALUES(token),active=1,rotated_at=CURRENT_TIMESTAMP')->execute(['user'=>$userId,'token'=>$token]);return $token;
    }

    public static function userForToken(PDO $db,string $token):?array
    {
        if(!preg_match('/^[a-f0-9]{64}$/',$token))return null;
        $stmt=$db->prepare("SELECT u.id,CONCAT(u.first_name,' ',u.last_name) name,u.display_role role,u.initials FROM calendar_subscriptions cs JOIN users u ON u.id=cs.user_id AND u.active=1 WHERE cs.token=:token AND cs.active=1 LIMIT 1");$stmt->execute(['token'=>$token]);return $stmt->fetch()?:null;
    }

    public static function ics(array $events,string $calendarName):string
    {
        $esc=static function(string $value):string{return str_replace(["\\",";",",","\r","\n"],["\\\\","\\;","\\,","","\\n"],$value);};
        $lines=['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//CTSMD//Connect Calendar//EN','CALSCALE:GREGORIAN','METHOD:PUBLISH','X-WR-CALNAME:'.$esc($calendarName)];
        foreach($events as $e){$start=(new DateTimeImmutable($e['starts_at']))->setTimezone(new DateTimeZone('UTC'));$end=new DateTimeImmutable($e['ends_at']?:$e['starts_at'].' +1 hour');$end=$end->setTimezone(new DateTimeZone('UTC'));$description=$e['production_title'];if(!empty($e['group_names']))$description.=' · '.implode(' + ',$e['group_names']);if($e['status']==='cancelled')$description.=' · CANCELLED';$lines[]='BEGIN:VEVENT';$lines[]='UID:ctsmd-schedule-'.(int)$e['id'].'@connect';$lines[]='DTSTAMP:'.gmdate('Ymd\THis\Z');$lines[]='DTSTART:'.$start->format('Ymd\THis\Z');$lines[]='DTEND:'.$end->format('Ymd\THis\Z');$lines[]='SUMMARY:'.$esc(($e['status']==='cancelled'?'CANCELLED · ':'').$e['title']);$lines[]='LOCATION:'.$esc((string)$e['location']);$lines[]='DESCRIPTION:'.$esc($description);$lines[]='STATUS:'.($e['status']==='cancelled'?'CANCELLED':'CONFIRMED');$lines[]='END:VEVENT';}
        $lines[]='END:VCALENDAR';return implode("\r\n",$lines)."\r\n";
    }
}
