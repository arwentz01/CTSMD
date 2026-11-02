<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/src/SafeguardingConversationReviewService.php';

// SQL-boundary regression tests with deterministic stub results.
// Database-backed permission, navigation, and browser tests remain mandatory.
final class ReviewTestStatement extends PDOStatement
{
    public function __construct(private ReviewTestDb $db,private string $query){}
    public function execute(?array $params=null):bool
    {
        $this->db->queries[]=['sql'=>$this->query,'params'=>$params??[]];
        if($this->db->rejectAudit&&str_contains($this->query,'INSERT INTO audit_events'))throw new RuntimeException('Audit unavailable');
        return true;
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0):mixed
    {
        if(str_contains($this->query,'FROM conversations WHERE id=:id'))return ['id'=>91,'subject'=>'','conversation_type'=>'direct','created_at'=>'2026-10-26 12:00:00'];
        return false;
    }
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,...$args):array
    {
        if(str_contains($this->query,'SELECT m.id,m.sender_user_id'))return [['id'=>12,'sender_user_id'=>3,'sender_name'=>'Person','body'=>'Example','created_at'=>'2026-10-26 12:00:00','hidden_at'=>null]];
        return [];
    }
}
final class ReviewTestDb extends PDO
{
    public array $queries=[];
    public bool $rejectAudit=false;
    public function __construct(){}
    public function prepare(string $query,array $options=[]):PDOStatement|false{return new ReviewTestStatement($this,$query);}
}
$db=new ReviewTestDb();
$results=SafeguardingConversationReviewService::search($db,14,82,'','',1);
if($results['rows']!==[]||count($db->queries)!==1)throw new RuntimeException('Unexpected search query.');
$sql=$db->queries[0]['sql'];$parameters=$db->queries[0]['params'];
if(!str_contains($sql,'EXISTS (SELECT 1 FROM conversation_participants p0')||!str_contains($sql,'EXISTS (SELECT 1 FROM conversation_participants p1'))throw new RuntimeException('Both participant filters must apply.');
if($parameters!==['user0'=>14,'user1'=>82])throw new RuntimeException('User filters must be parameters.');
if(!str_contains($sql,'LIMIT 51 OFFSET 0'))throw new RuntimeException('Review search must be paginated.');

$db->queries=[];
SafeguardingConversationReviewService::search($db,null,null,'Smith','Jones',2);
if(!str_contains($db->queries[0]['sql'],"ESCAPE '!'")||!str_contains($db->queries[0]['sql'],'LIMIT 51 OFFSET 50'))throw new RuntimeException('Name search should be escaped and paginated.');
if($db->queries[0]['params']['name0']!=='%Smith%'||$db->queries[0]['params']['name1']!=='%Jones%')throw new RuntimeException('Both participant names should be included.');

$method=new ReflectionMethod(SafeguardingConversationReviewService::class,'review');
$source=(new ReflectionClass(SafeguardingConversationReviewService::class))->getFileName();
if(!$source||!is_file($source))throw new RuntimeException('Source unavailable.');
$code=file_get_contents($source);
if(strpos($code,'$audit->execute(')>strpos($code,'$messages->execute('))throw new RuntimeException('Content must never load before successful audit.');
if(!str_contains($code,"'safeguarding.manage'")||str_contains($code,'AccessPolicy::isStaff'))throw new RuntimeException('Review requires specific safeguarding permission.');
echo "PASS: safeguarding review participant filters, pagination and audit ordering checks.\n";
