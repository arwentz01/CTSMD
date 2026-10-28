<?php
declare(strict_types=1);

/**
 * Staff production directory: current/upcoming first; closed shows are
 * collapsible and ordered newest-to-oldest. Never infer closure solely from
 * legacy is_active (it can remain true after lifecycle completion).
 */
final class ProductionListOrganizer
{
    public static function split(array $productions):array
    {
        $current=[];$past=[];
        foreach($productions as $p){
            $stage=(string)($p['lifecycle_stage']??'');
            $closed=in_array($stage,['completed','archived'],true)
                || (string)($p['status']??'')==='archived';
            if($closed)$past[]=$p;else $current[]=$p;
        }
        usort($current,static function(array $a,array $b):int{
            $stageRank=static fn(array $p):int=>in_array((string)($p['lifecycle_stage']??''),['in_production','performing'],true)?0:1;
            $rank=$stageRank($a)<=>$stageRank($b);
            if($rank!==0)return $rank;
            $date=static function(array $p):int{
                foreach(['performance_starts_at','audition_starts_at','performance_ends_at','audition_ends_at','created_at'] as $key){
                    if(!empty($p[$key]))return strtotime((string)$p[$key])?:PHP_INT_MAX;
                }
                return PHP_INT_MAX;
            };
            return ($date($a)<=>$date($b))?:((int)$b['id']<=>(int)$a['id']);
        });
        usort($past,static function(array $a,array $b):int{
            $date=static function(array $p):int{
                foreach(['performance_ends_at','performance_starts_at','deactivated_at','created_at'] as $key){
                    if(!empty($p[$key]))return strtotime((string)$p[$key])?:0;
                }
                return 0;
            };
            return ($date($b)<=>$date($a))?:((int)$b['id']<=>(int)$a['id']);
        });
        return ['current'=>$current,'past'=>$past];
    }
}
