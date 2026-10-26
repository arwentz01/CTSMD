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
        }
        return true;
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0):mixed
    {
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

echo "PASS: audition bridge student-only, unmatched, lifecycle and duplicate smoke checks.\n";
