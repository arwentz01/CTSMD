<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/src/CommunityExperience.php';

final class CommunitySearchStatement extends PDOStatement
{
    public array $params=[];
    public function __construct(private CommunitySearchPDO $database){}
    public function execute(?array $params=null):bool{$this->params=$params??[];$this->database->executed=$this->params;return true;}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,...$args):array{return [];}
}
final class CommunitySearchPDO extends PDO
{
    public string $sql='';
    public array $executed=[];
    public function __construct(){}
    public function prepare(string $query,array $options=[]):PDOStatement|false{$this->sql=$query;return new CommunitySearchStatement($this);}
}
$method=new ReflectionMethod(CommunityExperience::class,'searchPosts');
$db=new CommunitySearchPDO();
$search=static fn(array $channels,string $q):array=>$method->invoke(null,$db,$channels,$q);
$visible=[['id'=>10],['id'=>25],['id'=>10]];
if($search([],'casting')!==[]||$db->sql!=='')throw new RuntimeException('Search must not query when no channel is readable.');
if($search($visible,'x')!==[]||$db->sql!=='')throw new RuntimeException('Short search query should not hit the database.');
$search($visible,'role%_!'); 
if(!str_contains($db->sql,'cp.channel_id IN (?,?)'))throw new RuntimeException('Only unique authorized channel IDs should be searched.');
if(!str_contains($db->sql,"cp.moderation_status='published'")||!str_contains($db->sql,'cp.hidden_at IS NULL')||!str_contains($db->sql,'cp.deleted_at IS NULL')||!str_contains($db->sql,'c.archived_at IS NULL'))throw new RuntimeException('Unpublished, hidden, deleted or archived content may not appear.');
if($db->executed!==[10,25,'%role!%!_!!%'])throw new RuntimeException('Expected authorized IDs and escaped literal phrase.');
if(!str_contains($db->sql,'LIMIT 50'))throw new RuntimeException('Search results should be bounded.');
echo "PASS: permission-scoped Community search SQL safety checks.\n";
