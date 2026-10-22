<?php
declare(strict_types=1);
/** Isolated push guard regression. Run: php tests/pilot_push_destination_guard.php */
require_once dirname(__DIR__).'/src/PushService.php';

final class PushGuardStatement extends PDOStatement
{
    private array $rows=[];private int $changed=0;
    public function __construct(private PushGuardDatabase $db,private string $sql){}
    public function execute(?array $params=null):bool
    {
        $this->rows=[];$this->changed=0;
        if(str_starts_with($this->sql,'SELECT * FROM push_queue')){
            $this->rows=[$this->db->queue];return true;
        }
        if(str_starts_with($this->sql,"UPDATE push_queue SET status='processing'")){
            $this->changed=1;return true;
        }
        if(str_starts_with($this->sql,'SELECT 1 FROM users')){
            $this->rows=[[1]];return true;
        }
        if(str_starts_with($this->sql,'SELECT * FROM notification_preferences')){
            $this->rows=[['push_enabled'=>1,'push_messages'=>1,'push_community'=>1]];return true;
        }
        if(str_starts_with($this->sql,"UPDATE push_queue SET status='suppressed'")){
            $this->db->suppressed=true;
            $this->db->suppressionReason=(string)($params['id']??'');
            $this->changed=1;return true;
        }
        throw new RuntimeException('Unexpected SQL in isolated push test: '.$this->sql);
    }
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return $this->rows;}
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0):mixed{return $this->rows[0]??false;}
    public function fetchColumn(int $column=0):mixed{return $this->rows[0][$column]??false;}
    public function rowCount():int{return $this->changed;}
}
final class PushGuardDatabase extends PDO
{
    public array $queue=[];
    public bool $suppressed=false;
    public string $suppressionReason='';
    public function __construct(){}
    public function exec(string $statement):int|false{
        if(str_contains($statement,"status='processing'"))return 0;
        throw new RuntimeException('Unexpected SQL in isolated push test: '.$statement);
    }
    public function prepare(string $query,array $options=[]):PDOStatement|false{
        return new PushGuardStatement($this,$query);
    }
}
putenv('PUSH_VAPID_PUBLIC_KEY=test');
putenv('PUSH_VAPID_PRIVATE_KEY_B64='.base64_encode('test'));
$paths=[
    ['messages','/messages/thread?id=not-an-id'],
    ['messages','https://evil.example/messages/thread?id=7'],
    ['community','/channels/view?id='],
    ['community','/channels/view?id=7&unsafe=1'],
];
foreach($paths as [$category,$path]){
    $db=new PushGuardDatabase();
    $db->queue=['id'=>9,'user_id'=>7,'category'=>$category,'action_path'=>$path,'attempt_count'=>0];
    $result=PushService::processQueue($db,1);
    if(!$db->suppressed||$result!==['sent'=>0,'failed'=>0]){
        throw new RuntimeException("Malformed {$category} destination was not suppressed.");
    }
}
echo "PASS: four malformed Message/Community push destinations were suppressed without delivery.\n";
