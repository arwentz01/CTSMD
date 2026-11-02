<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/WorkspaceUI.php';
final class UXStageStatement extends PDOStatement {
    public function __construct(private mixed $stage) {}
    public function execute(?array $params=null):bool{return true;}
    public function fetchColumn(int $column=0):mixed{return $this->stage;}
}
final class UXStagePDO extends PDO {
    public function __construct(private mixed $stage) {}
    public function prepare(string $query,array $options=[]):PDOStatement|false{return new UXStageStatement($this->stage);}
}
foreach(['upcoming','planning','in_production','performing'] as $stage){
    if(WorkspaceUI::productionReadOnly(new UXStagePDO($stage),11))throw new RuntimeException('Writable production should remain editable.');
}
foreach(['completed','archived','unsupported',null,false] as $stage){
    if(!WorkspaceUI::productionReadOnly(new UXStagePDO($stage),11))throw new RuntimeException('Closed or unknown production must render read-only.');
}
if(WorkspaceUI::productionReadOnly(new UXStagePDO(false),null))throw new RuntimeException('Organization forms are not a closed production.');
foreach([
    [['severity'=>'blocker','count'=>2],'Blocked'],
    [['severity'=>'warning','count'=>2],'Warning'],
    [['severity'=>'blocker','count'=>0],'Ready'],
] as [$finding,$expected]){
    if(WorkspaceUI::readinessLabel($finding)!==$expected)throw new RuntimeException('Finding labels must describe existing severity/count.');
}
echo "PASS: operational presentation lifecycle locks and readiness labels (not write authorization).\n";
