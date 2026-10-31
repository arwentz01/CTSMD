<?php
declare(strict_types=1);
require_once __DIR__.'/CommerceStore.php';
require_once __DIR__.'/ProductionLifecycleGuard.php';
/** Every new reservation, tender and fulfillment checks the actual performance row. */
final class CommercePerformancePolicy
{
    public static function timezone(string $name):DateTimeZone
    {
        if(!in_array($name,DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC),true)&&$name!=='UTC')throw new RuntimeException('Choose a valid IANA venue timezone, such as America/New_York.');return new DateTimeZone($name);
    }
    public static function venueTimezone(PDO $db,int $org,int $layout):string
    {
        $q=$db->prepare('SELECT v.timezone_name FROM commerce_layouts l JOIN commerce_spaces s ON s.id=l.space_id AND s.organization_id=l.organization_id JOIN commerce_venues v ON v.id=s.venue_id AND v.organization_id=s.organization_id WHERE l.organization_id=? AND l.id=?'.($db->inTransaction()?' FOR UPDATE':''));$q->execute([$org,$layout]);$value=$q->fetch();if(!$value)throw new RuntimeException('Choose a layout owned by this organization.');$name=$value['timezone_name']?:((string)(getenv('COMMERCE_VENUE_TIMEZONE')?:getenv('APP_TIMEZONE')?:'America/New_York'));self::timezone($name);return$name;
    }
    /** Enumerate possible instants rather than accepting PHP's silent DST normalization. */
    public static function resolve(string $local,string $zone,string $choice=''):array
    {
        $tz=self::timezone($zone);$utc=new DateTimeZone('UTC');$wall=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$local,$utc);if(!$wall||$wall->format('Y-m-d\TH:i')!==$local)throw new RuntimeException('Enter an exact valid date and time.');
        $stamp=$wall->getTimestamp();$offsets=[];foreach($tz->getTransitions($stamp-172800,$stamp+172800)?:[] as $transition)$offsets[(int)$transition['offset']]=true;if(!$offsets)$offsets[$tz->getOffset($wall)]=true;$candidates=[];
        foreach(array_keys($offsets) as $offset){$instant=(new DateTimeImmutable('@'.($stamp-$offset)))->setTimezone($tz);if($instant->format('Y-m-d\TH:i')===$local)$candidates[]=['local'=>$instant->format('Y-m-d H:i:s'),'utc'=>$instant->setTimezone($utc)->format('Y-m-d H:i:s'),'timezone'=>$zone,'offset'=>$instant->format('P'),'offset_minutes'=>(int)($offset/60)];}
        usort($candidates,fn($a,$b)=>strcmp($a['utc'],$b['utc']));if(!$candidates)throw new RuntimeException($local.' does not exist in '.$zone.' because clocks move forward. Choose another time.');
        if(count($candidates)>1&&$choice==='')throw new RuntimeException($local.' occurs twice in '.$zone.'. Choose the first or second occurrence, or an explicit UTC offset.');
        if($choice==='earlier')return$candidates[0];if($choice==='later')return$candidates[count($candidates)-1];
        if($choice!==''){foreach($candidates as $candidate)if($candidate['offset']===$choice)return$candidate;throw new RuntimeException('The chosen UTC offset does not match '.$local.' in '.$zone.'.');}return$candidates[0];
    }
    public static function problem(PDO $db,int $org,array $performance,bool $requireOpen=true):?string
    {
        if((int)$performance['organization_id']!==$org)return'Performance unavailable for this organization.';
        if($performance['schedule_status']!=='scheduled')return'This performance is '.$performance['schedule_status'].' and cannot accept new sales.';
        if(!$performance['starts_at_utc']||!$performance['timezone_name'])return'This historical performance needs a reviewed venue timezone before new sales.';
        if($performance['starts_at_utc']<=gmdate('Y-m-d H:i:s'))return'This performance has already started and cannot accept new sales.';
        if($requireOpen&&$performance['sales_status']!=='open')return'Sales are closed for this performance.';
        $q=$db->prepare('SELECT announcement_published_at,lifecycle_stage FROM productions WHERE id=?'.($db->inTransaction()?' FOR UPDATE':''));$q->execute([$performance['production_id']]);$p=$q->fetch();if(!$p||!$p['announcement_published_at']||!in_array($p['lifecycle_stage'],['upcoming','planning','in_production','performing'],true))return'This production is unpublished or no longer saleable.';
        $q=$db->prepare('SELECT 1 FROM commerce_showtime_slots WHERE organization_id=? AND performance_id=? AND starts_at_utc=?');$q->execute([$org,$performance['id'],$performance['starts_at_utc']]);if(!$q->fetchColumn())return'This performance has no confirmed venue/showtime record.';return null;
    }
    public static function demand(PDO $db,int $org,array $performance,bool $requireOpen=true):void
    {
        ProductionLifecycleGuard::assertWritable($db,(int)$performance['production_id'],true);if($problem=self::problem($db,$org,$performance,$requireOpen))throw new RuntimeException($problem);
    }
    public static function label(array $p):string
    {
        return (string)$p['starts_at'].(!empty($p['timezone_name'])?' · '.$p['timezone_name'].' (UTC'.self::offset((int)$p['utc_offset_minutes']).')':' · timezone review required');
    }
    private static function offset(int $minutes):string{return($minutes<0?'-':'+').sprintf('%02d:%02d',intdiv(abs($minutes),60),abs($minutes)%60);}
}
