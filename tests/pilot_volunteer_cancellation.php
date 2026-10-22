<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/VolunteerExperience.php';

// In-memory PDO double tests transaction outcomes with no real accounts or records.
// SQL trigger and cross-process concurrency acceptance require migrated staging.
final class CancelStatement extends PDOStatement {
    private mixed $value=false;
    private int $affected=0;
    public function __construct(private CancelDatabase $db, private string $sql) {}
    public function execute(?array $params=null):bool {
        $params??=[];
        if(str_contains($this->sql,'SELECT starts_at FROM volunteer_shifts')){
            $this->value=$this->db->shiftStart;
        } elseif(str_contains($this->sql,'SELECT id,status FROM volunteer_shift_signups')){
            $this->value=($params['user']===$this->db->ownerId && $params['shift']===$this->db->shiftId)
                ? $this->db->signup : false;
        } elseif(str_contains($this->sql,"UPDATE volunteer_shift_signups SET status='cancelled'")){
            if($this->db->signup && $this->db->signup['id']===$params['id'] && $this->db->signup['status']==='signed_up'){
                $this->db->signup['status']='cancelled';
                $this->affected=1;
            }
        } else throw new RuntimeException('Unexpected SQL in cancellation test: '.$this->sql);
        return true;
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0):mixed {return $this->value;}
    public function fetchColumn(int $column=0):mixed{return $this->value;}
    public function rowCount():int{return $this->affected;}
}
final class CancelDatabase extends PDO {
    public mixed $shiftStart;
    public array|false $signup=['id'=>7,'status'=>'signed_up'];
    public int $shiftId=32, $ownerId=12;
    public int $commits=0,$rollbacks=0;
    private bool $tx=false;
    public function __construct(){$this->shiftStart=date('Y-m-d H:i:s',time()+3600);}
    public function prepare(string $query,array $options=[]):PDOStatement|false{return new CancelStatement($this,$query);}
    public function beginTransaction():bool{$this->tx=true;return true;}
    public function inTransaction():bool{return $this->tx;}
    public function commit():bool{$this->tx=false;$this->commits++;return true;}
    public function rollBack():bool{$this->tx=false;$this->rollbacks++;return true;}
}
function checkCancel(bool $shouldPass,CancelDatabase $db,int $user=12,int $shift=32):void {
    $before=$db->signup;
    $thrown=false;
    try{VolunteerExperience::cancelSignup($db,$user,$shift);}catch(RuntimeException){$thrown=true;}
    if($thrown===$shouldPass)throw new RuntimeException('Unexpected cancellation result.');
    if(!$shouldPass && $db->signup!==$before)throw new RuntimeException('Rejected cancellation modified signup.');
    if($shouldPass && $db->signup['status']!=='cancelled')throw new RuntimeException('Accepted cancellation did not update status.');
    if($db->inTransaction())throw new RuntimeException('Transaction left open.');
    if($shouldPass && $db->commits!==1)throw new RuntimeException('Successful transaction not committed.');
    if(!$shouldPass && $db->rollbacks!==1)throw new RuntimeException('Rejected transaction not rolled back.');
}
$checks=0;
$db=new CancelDatabase();checkCancel(true,$db);$checks++;
$db=new CancelDatabase();$db->shiftStart=date('Y-m-d H:i:s',time()-20);checkCancel(false,$db);$checks++;
$db=new CancelDatabase();$db->signup['status']='checked_in';checkCancel(false,$db);$checks++;
$db=new CancelDatabase();$db->signup['status']='completed';checkCancel(false,$db);$checks++;
$db=new CancelDatabase();checkCancel(false,$db,999);$checks++;
$db=new CancelDatabase();$db->signup=false;checkCancel(false,$db);$checks++;
$db=new CancelDatabase();$db->shiftStart=false;checkCancel(false,$db);$checks++;
$db=new CancelDatabase();checkCancel(false,$db,12,-1);$checks++;
echo "PASS: {$checks} isolated volunteer cancellation and rollback checks (stubbed PDO; staging required).\n";
