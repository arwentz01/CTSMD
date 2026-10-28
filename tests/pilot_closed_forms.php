<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/FormWriteGuard.php';
final class FormGuardPDO extends PDO {
    public array $queries=[];
    public function __construct(private string $stage,private bool $exists=true){}
    public function prepare(string $query,array $options=[]):PDOStatement|false{
        $this->queries[]=$query;
        return new FormGuardStatement(str_contains($query,'lifecycle_stage')?$this->stage:($this->exists?7:false));
    }
}
final class FormGuardStatement extends PDOStatement {
    public function __construct(private mixed $value){}
    public function execute(?array $params=null):bool{return true;}
    public function fetchColumn(int $column=0):mixed{return $this->value;}
}
$checks=0;
foreach(['completed','archived','unknown-stage',''] as $stage){
    $db=new FormGuardPDO($stage);
    try{FormWriteGuard::form($db,42);throw new LogicException('Closed definition accepted.');}
    catch(RuntimeException $e){$checks++;}
    if(count($db->queries)!==3||!str_contains($db->queries[0],'FOR UPDATE')||!str_contains($db->queries[1],'FOR UPDATE'))throw new RuntimeException('Definition and production must be locked before lifecycle evaluation.');
    $checks++;
}
foreach(['upcoming','planning','in_production','performing'] as $stage){FormWriteGuard::form(new FormGuardPDO($stage),42);$checks++;}
$db=new FormGuardPDO('planning',false);
try{FormWriteGuard::form($db,42);throw new LogicException('Missing definition accepted.');}catch(RuntimeException $e){$checks++;}
foreach(['FamilyFormsExperience.php','FormManagementExperience.php','DynamicFormService.php','FamilyFormService.php','FormExperience.php'] as $file){
    if(!str_contains(file_get_contents(dirname(__DIR__).'/src/'.$file),'FormWriteGuard::'))throw new RuntimeException('Missing form boundary: '.$file);
    $checks++;
}
echo 'PASS: '.$checks." form lifecycle/locking and source-boundary checks (stubbed PDO, not concurrency proof).\n";
