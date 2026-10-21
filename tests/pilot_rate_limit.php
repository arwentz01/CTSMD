<?php

declare(strict_types=1);
require_once dirname(__DIR__).'/src/RateLimiter.php';

final class RateLimitStatement extends PDOStatement
{
    private array|false $result=false;
    public function __construct(private RateLimitDatabase $db,private string $sql){}
    public function execute(?array $params=null):bool
    {
        $params??=[];$key=($params['action']??'').'|'.($params['hash']??'');
        if(str_starts_with($this->sql,'SELECT attempts')){$this->result=$this->db->rows[$key]??false;return true;}
        if(str_starts_with($this->sql,'INSERT INTO')){$this->db->rows[$key]=['attempts'=>1,'window_started_at'=>date('Y-m-d H:i:s'),'blocked_until'=>null];return true;}
        if(str_starts_with($this->sql,'UPDATE auth_rate_limits')){$this->db->rows[$key]=['attempts'=>$params['attempts'],'window_started_at'=>$params['started'],'blocked_until'=>$params['blocked']];return true;}
        if(str_starts_with($this->sql,'DELETE FROM')){unset($this->db->rows[$key]);return true;}
        throw new RuntimeException('Unexpected rate-limit SQL.');
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0):mixed{return$this->result;}
}

final class RateLimitDatabase extends PDO
{
    public array $rows=[];private bool $transaction=false;
    public function __construct(){}
    public function beginTransaction():bool{$this->transaction=true;return true;}
    public function commit():bool{$this->transaction=false;return true;}
    public function rollBack():bool{$this->transaction=false;return true;}
    public function inTransaction():bool{return$this->transaction;}
    public function prepare(string $query,array $options=[]):PDOStatement|false{return new RateLimitStatement($this,$query);}
}

putenv('APP_KEY=rate-limit-test-key');
$db=new RateLimitDatabase();$subject='127.0.0.1|test@example.test';
RateLimiter::enforce($db,'web-login',$subject,2,900,120);
RateLimiter::enforce($db,'web-login',$subject,2,900,120);
$blocked=false;try{RateLimiter::enforce($db,'web-login',$subject,2,900,120);}catch(RateLimitExceeded $e){$blocked=$e->retryAfter===120;}
if(!$blocked)throw new RuntimeException('Rate limit did not block at the configured threshold.');
RateLimiter::clear($db,'web-login',$subject);
RateLimiter::enforce($db,'web-login',$subject,2,900,120);
echo "PASS: durable authentication rate-limit threshold and reset behavior.\n";
