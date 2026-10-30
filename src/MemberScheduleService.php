<?php
declare(strict_types=1);
require_once __DIR__.'/CalendarService.php';
require_once __DIR__.'/MemberParticipationService.php';

final class MemberScheduleService
{
    public static function item(PDO $db,array $subjects,int $id,?int $person=null):?array
    {
        $q=$db->prepare("SELECT si.*,p.title production_title FROM schedule_items si JOIN productions p ON p.id=si.production_id AND p.is_active=1 AND p.lifecycle_stage IN ('upcoming','planning','in_production','performing') WHERE si.id=?");$q->execute([$id]);$item=$q->fetch();if(!$item)return null;$names=[];$ids=[];
        foreach($subjects as $who=>$subject){if($person!==null&&$who!==$person)continue;if(!in_array((int)$item['production_id'],MemberParticipationService::directProductionIds($db,$who),true))continue;if(ScheduleAudience::userCanViewItem($db,$subject,$item,true)){$names[]=$subject['name'];$ids[]=$who;}}
        if(!$names)return null;$item['participants']=$names;$item['participant_ids']=$ids;$item['group_names']=$item['audience_mode']==='groups'?ScheduleAudience::groupNamesForItem($db,$id):[];$item['target_label']=$item['audience_mode']==='groups'?'Group call · '.implode(', ',$item['group_names']):'Production-wide event';return $item;
    }
    public static function events(PDO $db,array $subjects,DateTimeImmutable $from,DateTimeImmutable $to,?int $person=null,?int $production=null):array
    {
        $events=[];
        foreach($subjects as $id=>$subject){if($person!==null&&$id!==$person)continue;
            foreach(CalendarService::visibleEvents($db,$subject,$from,$to,$production,true) as $event){$eid=(int)$event['id'];$events[$eid]??=$event+['participants'=>[],'participant_ids'=>[]];$events[$eid]['participants'][$id]=$subject['name'];$events[$eid]['participant_ids'][$id]=true;}
        }
        foreach($events as &$event){$event['participants']=array_values($event['participants']);$event['participant_ids']=array_keys($event['participant_ids']);$event['target_label']=$event['audience_mode']==='groups'?'Group call · '.implode(', ',$event['group_names']):'Production-wide event';}unset($event);
        uasort($events,static fn(array $a,array $b):int=>strcmp($a['starts_at'],$b['starts_at'])?:((int)$a['id']<=>(int)$b['id']));return array_values($events);
    }
}
