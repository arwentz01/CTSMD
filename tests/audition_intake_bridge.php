<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/src/AuditionIntakeBridge.php';

// DB-independent smoke tests for intake handoff. These do not replace migrated MySQL tests.
final class AuditionStubStatement extends PDOStatement
{
    private int $affected=0;
    public function __construct(private string $sql,private array $scenario,private array &$writes){}
    public function execute(?array $params=null):bool
    {
        if(str_contains($this->sql,'INSERT IGNORE INTO production_casting_records')){
            $this->writes[]=$params??[];
            $this->affected=(int)($this->scenario['inserted']??1);
        } elseif(str_contains($this->sql,"UPDATE production_casting_records SET casting_status='withdrawn'")){
            $this->writes[]=$params??[];
            $this->affected=(int)($this->scenario['withdrawn']??1);
        } elseif(str_contains($this->sql,'INSERT INTO audit_events')){
            $this->affected=1;
        }
        return true;
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0):mixed
    {
        if(str_contains($this->sql,'SELECT cr.id,cr.production_id'))return $this->scenario['pending_record']??false;
        return str_contains($this->sql,'SELECT ro.production_id,rsl.participant_user_id') ? ($this->scenario['matched']??false) : false;
    }
    public function fetchColumn(int $column=0):mixed
    {
        if(str_contains($this->sql,'SELECT lifecycle_stage FROM productions'))return $this->scenario['stage']??'planning';
        return false;
    }
    public function rowCount():int{return $this->affected;}
}
final class AuditionStubPDO extends PDO
{
    public array $writes=[];
    public function __construct(public array $scenario){}
    public function prepare(string $query,array $options=[]):PDOStatement|false
    {
        return new AuditionStubStatement($query,$this->scenario,$this->writes);
    }
}
function verify(bool $result,string $message):void{if(!$result)throw new RuntimeException($message);}

$db=new AuditionStubPDO(['matched'=>['production_id'=>11,'participant_user_id'=>42],'stage'=>'planning']);
verify(AuditionIntakeBridge::syncAccepted($db,7,3),'Accepted linked audition was not transferred.');
verify(count($db->writes)===1,'Expected exactly one insertion.');
verify($db->writes[0]['student']===42,'The casting identity must be the student, not the guardian.');
verify($db->writes[0]['submission']===7,'The original audition submission must stay linked.');

$unmatched=new AuditionStubPDO(['matched'=>false]);
verify(!AuditionIntakeBridge::syncAccepted($unmatched,7,3),'Unlinked audition must stay pending.');
verify($unmatched->writes===[],'Unlinked audition must not create a casting row.');

$archived=new AuditionStubPDO(['matched'=>['production_id'=>11,'participant_user_id'=>42],'stage'=>'archived']);
verify(!AuditionIntakeBridge::syncAccepted($archived,7,3),'Archived production must remain read-only.');
verify($archived->writes===[],'No archived casting insert.');

$already=new AuditionStubPDO(['matched'=>['production_id'=>11,'participant_user_id'=>42],'stage'=>'planning','inserted'=>0]);
verify(!AuditionIntakeBridge::syncAccepted($already,7,3),'Duplicate casting insertion must be a no-op.');

$withdraw=new AuditionStubPDO(['pending_record'=>['id'=>19,'production_id'=>11],'stage'=>'planning']);
verify(AuditionIntakeBridge::withdrawPending($withdraw,7,3),'Cancellation should withdraw only under-review intake.');
verify(count($withdraw->writes)===1&&$withdraw->writes[0]['id']===19,'Expected one targeted casting withdrawal.');

$decided=new AuditionStubPDO(['pending_record'=>false]);
verify(!AuditionIntakeBridge::withdrawPending($decided,7,3),'Later casting decisions must not be withdrawn automatically.');
verify($decided->writes===[],'No casting update should occur for decided records.');

echo "PASS: audition bridge student-only, unmatched, lifecycle, duplicate and withdrawal smoke checks.\\n";
