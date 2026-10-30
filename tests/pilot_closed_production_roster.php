<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/CastingRosterService.php';
require_once dirname(__DIR__).'/src/ProductionParticipantFunctionService.php';

final class ClosedRosterPDO extends PDO {
    public int $writes=0;
    public int $rollbacks=0;
    private bool $tx=false;
    public function __construct(private string $stage){}
    public function beginTransaction():bool{$this->tx=true;return true;}
    public function inTransaction():bool{return $this->tx;}
    public function rollBack():bool{$this->tx=false;$this->rollbacks++;return true;}
    public function prepare(string $query,array $options=[]):PDOStatement|false{
        if(!str_contains($query,'SELECT lifecycle_stage FROM productions WHERE id=:id LIMIT 1'))$this->writes++;
        return new ClosedRosterStatement($this->stage);
    }
}
final class ClosedRosterStatement extends PDOStatement {
    public function __construct(private string $stage){}
    public function execute(?array $params=null):bool{return true;}
    public function fetchColumn(int $column=0):mixed{return $this->stage;}
}
function assertClosedRoster(bool $pass,string $label):void{
    if(!$pass)throw new RuntimeException('Lifecycle roster: '.$label);
    echo 'PASS: '.$label."\n";
}
foreach(['completed','archived','unknown-stage'] as $stage){
    foreach(['saveTeam','removeTeam','removeCast'] as $action){
        $db=new ClosedRosterPDO($stage);

        $actor=['id'=>1,'roles'=>['administrator'],'permissions'=>['production.manage']];
        try{
            if($action==='saveTeam')ProductionParticipantFunctionService::saveAssignment($db,7,1,['user_id'=>42,'function_type'=>'creative','function_code'=>'director']);
            elseif($action==='removeTeam')ProductionParticipantFunctionService::remove($db,7,1,5,1);
            else CastingRosterService::remove($db,$actor,7,5);
            throw new RuntimeException('Closed roster unexpectedly accepted '.$action);
        }catch(RuntimeException $e){
            assertClosedRoster(str_contains(strtolower($e->getMessage()),'production')&&!str_contains($e->getMessage(),'unexpectedly accepted'),
                $stage.' '.$action.' rejected');
        }
        assertClosedRoster($db->writes===0,$stage.' '.$action.' had no downstream queries');
        assertClosedRoster($db->rollbacks===1&&!$db->inTransaction(),$stage.' '.$action.' rolled back');
    }
}
