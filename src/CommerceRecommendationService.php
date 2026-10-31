<?php
declare(strict_types=1);
require_once __DIR__.'/CommerceInventoryService.php';
/** Conservative geometric suggestion, not a hold or a promise about sightlines. */
final class CommerceRecommendationService
{
    public static function suggest(PDO $db,array $u,int $org,int $performance,int $count,string $zone=''):array
    {
        CommerceAccess::demand($db,$u,$org,'test');CommerceStore::organization($db,$org,true);if($count<1||$count>12)throw new RuntimeException('Choose between 1 and 12 neighboring seats.');
        return CommerceStore::tx($db,function()use($db,$u,$org,$performance,$count,$zone){$p=CommerceStore::row($db,'performances',$org,$performance,true);$prices=json_decode($p['prices_json'],true);if($zone!==''&&!array_key_exists($zone,$prices))throw new RuntimeException('Choose an existing pricing zone.');ProductionLifecycleGuard::assertWritable($db,(int)$p['production_id'],true);if(CommercePerformancePolicy::problem($db,$org,$p))return[];CommerceInventoryService::expireLocked($db,$org,$performance,(int)$u['id']);$l=CommerceStore::row($db,'layouts',$org,(int)$p['layout_id']);return self::choose(CommerceInventoryService::inventory($db,$org,$performance),json_decode($l['document_json'],true),$count,$zone);});
    }
    private static function choose(array $inventory,array $document,int $count,string $zone):array
    {
        $rows=[];foreach($inventory as $item){$seat=json_decode($item['seat_json'],true);$rows[$seat['row']][]=$item+['seat'=>$seat];}$sectionOrder=array_column($document['sections'],'order','key');$rowOrder=array_column($document['rows'],'order','key');$obstacles=array_values(array_filter($document['items'],fn($i)=>in_array($i['kind'],['aisle','space'],true)));$best=null;$bestScore=null;
        foreach($rows as $rowKey=>$row){if(count($row)<$count)continue;$xs=array_column(array_column($row,'seat'),'x');$ys=array_column(array_column($row,'seat'),'y');$axis=max($xs)-min($xs)>=max($ys)-min($ys)?'x':'y';usort($row,fn($a,$b)=>[$a['seat'][$axis],$a['seat'][$axis==='x'?'y':'x'],$a['seat_key']]<=>[$b['seat'][$axis],$b['seat'][$axis==='x'?'y':'x'],$b['seat_key']]);$distances=[];for($i=1;$i<count($row);$i++){$d=hypot($row[$i]['seat']['x']-$row[$i-1]['seat']['x'],$row[$i]['seat']['y']-$row[$i-1]['seat']['y']);if($d>.01)$distances[]=$d;}sort($distances);$normal=$distances?$distances[intdiv(count($distances),2)]:40;$limit=max(48,min(120,$normal*1.6));$connected=[];
            for($i=1;$i<count($row);$i++){$a=$row[$i-1]['seat'];$b=$row[$i]['seat'];$distance=hypot($b['x']-$a['x'],$b['y']-$a['y']);$connected[$i]=$distance>.01&&$distance<=$limit;foreach($obstacles as $obstacle)if(self::crosses($a['x']+20,$a['y']+20,$b['x']+20,$b['y']+20,$obstacle)){$connected[$i]=false;break;}}
            for($start=0;$start<=count($row)-$count;$start++){$window=array_slice($row,$start,$count);$valid=true;$candidateZone=$window[0]['seat']['zone'];foreach($window as $offset=>$item)if(!in_array($item['state'],['available','released'],true)||$item['seat']['zone']!==$candidateZone||($zone!==''&&$candidateZone!==$zone)||($offset>0&&!$connected[$start+$offset])){$valid=false;break;}if(!$valid)continue;$seat=$window[0]['seat'];$score=[($sectionOrder[$seat['section']]??0),($rowOrder[$rowKey]??0),abs($start+($count-1)/2-(count($row)-1)/2),$window[0]['seat_key']];if($bestScore===null||$score<$bestScore){$bestScore=$score;$best=array_column($window,'seat_key');}}
        }
        return$best??[];
    }
    private static function crosses(float $ax,float $ay,float $bx,float $by,array $box):bool
    {
        $left=(float)$box['x'];$top=(float)$box['y'];$right=$left+(float)($box['width']??100);$bottom=$top+(float)($box['height']??40);$dx=$bx-$ax;$dy=$by-$ay;$low=0.0;$high=1.0;
        foreach([[-$dx,$ax-$left],[$dx,$right-$ax],[-$dy,$ay-$top],[$dy,$bottom-$ay]] as [$p,$q]){if(abs($p)<.000001){if($q<0)return false;continue;}$r=$q/$p;if($p<0)$low=max($low,$r);else$high=min($high,$r);if($low>$high)return false;}return true;
    }
}
