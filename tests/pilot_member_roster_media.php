<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/ProductionContext.php';
require_once dirname(__DIR__).'/src/RehearsalVideoService.php';
require_once dirname(__DIR__).'/src/RehearsalVideoExperience.php';

function assertRoster(bool $value,string $label):void{
    if(!$value)throw new RuntimeException('Member roster visibility: '.$label);
    echo "PASS: ".$label."\n";
}
class RosterTestPDO extends PDO {
    public array $queries=[];
    public function __construct(){}
    public function prepare(string $query,array $options=[]):PDOStatement|false{
        $this->queries[]=$query;
        return new RosterStatement($query);
    }
}
class RosterStatement extends PDOStatement {
    public array $params=[];
    public function __construct(public string $query){}
    public function execute(?array $params=null):bool{$this->params=$params??[];return true;}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,...$args):array{
        if(str_contains($this->query,'FROM productions p JOIN (')&&str_contains($this->query,'SELECT DISTINCT p.id')){
            return [['id'=>17,'title'=>'Beauty and the Beast'],['id'=>23,'title'=>'Christmas Carol']];
        }
        if(str_contains($this->query,'FROM rehearsal_videos rv')){
            $production=(int)($this->params['production']??0);
            $all=[
                ['id'=>1,'status'=>'active','audience_scope'=>'platform','production_id'=>null,'group_id'=>null,'created_at'=>'2026-10-25 12:00:00'],
                ['id'=>2,'status'=>'active','audience_scope'=>'production','production_id'=>17,'group_id'=>null,'created_at'=>'2026-10-26 12:00:00'],
                ['id'=>3,'status'=>'active','audience_scope'=>'production','production_id'=>23,'group_id'=>null,'created_at'=>'2026-10-27 12:00:00'],
            ];
            return array_values(array_filter($all,static fn(array $v):bool=>
                $v['audience_scope']==='platform'||(int)$v['production_id']===$production));
        }
        return [];
    }
    public function fetchColumn(int $column=0):mixed{return true;}
}
$db=new RosterTestPDO();
$member=['id'=>431,'role'=>'student','roles'=>['student'],'permissions'=>[]];
$available=ProductionContext::activeProductions($db,$member);
assertRoster(count($available)===2,'student roster includes both current productions');
$rosterSql=$db->queries[0]??'';
assertRoster(str_contains($rosterSql,"family_relationships")&&str_contains($rosterSql,"fr.status='active'"),'guardian membership requires active family relationship');
assertRoster(str_contains($rosterSql,"pm.status='active'"),'guardian access follows current student roster');
assertRoster(str_contains($rosterSql,'UNION')&&str_contains($rosterSql,'eligible ON eligible.production_id=p.id'),'member roster uses noncorrelated MySQL-safe UNION');
assertRoster(!str_contains($rosterSql,"lifecycle_stage IN"),'member roster is not restricted to performing lifecycle stages');
assertRoster(!str_contains($rosterSql,'selected_production_id'),'member roster ignores staff selection');
$videos=RehearsalVideoService::visibleAcrossProductions($db,$member);
assertRoster(count($videos)===3,'student sees global videos and all rostered production videos');
$listing=implode("\n",$db->queries);
assertRoster(substr_count($listing,"WHERE (rv.production_id=:production OR rv.audience_scope='platform')")===3,'member video aggregation reuses known-safe production query for platform and both shows');
assertRoster(array_column($videos,'id')===[3,2,1],'aggregated video list is deduplicated and newest-first');
$guard=['id'=>432,'role'=>'guardian','roles'=>['guardian'],'permissions'=>[]];
ProductionContext::clearRequestCache();
assertRoster(count(ProductionContext::activeProductions($db,$guard))===2,'guardian also resolves combined roster access');

assertRoster(RehearsalVideoExperience::handles('/videos'),'video route remains registered');

$before=count($db->queries);
assertRoster(ProductionContext::isActiveMember($db,431,17),'active-member authorization reuses the audience role resolution');
$newQueries=array_slice($db->queries,$before);
assertRoster(!array_filter($newQueries,static fn(string $sql):bool=>str_contains($sql,'JOIN production_memberships cpm ON cpm.user_id=fr.student_user_id')
    &&str_contains($sql,'cpm.production_id=p.id')),'active-member read has no out-of-scope outer production join');
assertRoster(ProductionContext::userHasActiveProduction($db,431),'member production existence query prepares with the flat membership join');
$last=end($db->queries);
assertRoster(str_contains((string)$last,'eligible ON eligible.production_id=p.id'),'member existence uses an eligible-production derived table');
