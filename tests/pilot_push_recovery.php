<?php

declare(strict_types=1);
require_once dirname(__DIR__).'/src/PushService.php';

final class PushRecoveryStatement extends PDOStatement
{
    private array $rows=[];private int $affected=0;
    public function __construct(private PushRecoveryDatabase $db,private string $sql){}
    public function execute(?array $params=null):bool
    {
        if(str_starts_with($this->sql,'SELECT * FROM push_queue')){$this->rows=[['id'=>9,'user_id'=>7,'category'=>'general','attempt_count'=>0,'action_path'=>'/app']];return true;}
        if(str_starts_with($this->sql,"UPDATE push_queue SET status='processing'")){$this->affected=1;return true;}
        if(str_starts_with($this->sql,'SELECT 1 FROM users')){$this->rows=[[1]];return true;}
        if(str_starts_with($this->sql,'SELECT * FROM notification_preferences')){$this->rows=[['push_enabled'=>0]];return true;}
        if(str_contains($this->sql,"status='suppressed'")){$this->db->suppressed=true;$this->affected=1;return true;}
        throw new RuntimeException('Unexpected push recovery SQL: '.$this->sql);
    }
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return$this->rows;}
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0):mixed{return$this->rows[0]??false;}
    public function fetchColumn(int $column=0):mixed{return$this->rows[0][$column]??false;}
    public function rowCount():int{return$this->affected;}
}

final class PushRecoveryDatabase extends PDO
{
    public bool $reclaimed=false;public bool $suppressed=false;
    public function __construct(){}
    public function exec(string $statement):int|false{if(str_contains($statement,"status='processing'")){$this->reclaimed=true;return 1;}throw new RuntimeException('Unexpected push recovery exec.');}
    public function prepare(string $query,array $options=[]):PDOStatement|false{return new PushRecoveryStatement($this,$query);}
}

putenv('PUSH_VAPID_PUBLIC_KEY=test');putenv('PUSH_VAPID_PRIVATE_KEY_B64='.base64_encode('test'));
$db=new PushRecoveryDatabase();$result=PushService::processQueue($db,1);
if(!$db->reclaimed||!$db->suppressed||$result!==['sent'=>0,'failed'=>0])throw new RuntimeException('Push recovery or delivery-time preference enforcement failed.');
echo "PASS: stale push claims recover and disabled preferences suppress delivery.\n";
