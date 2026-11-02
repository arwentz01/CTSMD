<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/NotificationExperience.php';

final class UXActionStatement extends PDOStatement
{
    public function __construct(private array $rows, private UXActionPDO $db) {}
    public function execute(?array $params=null): bool { $this->db->params=$params??[]; return true; }
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,...$args): array { return $this->rows; }
}
final class UXActionPDO extends PDO
{
    public array $params=[];
    public string $assignmentSql='';
    public string $productionSql='';
    public function __construct() {}
    public function prepare(string $query,array $options=[]): PDOStatement|false
    {
        $this->assignmentSql=$query;
        return new UXActionStatement([
            ['id'=>1,'status'=>'missing','production_id'=>11,'submission_status'=>null],
            ['id'=>2,'status'=>'due_soon','production_id'=>11,'submission_status'=>null],
            ['id'=>3,'status'=>'completed','production_id'=>11,'submission_status'=>'approved'],
            ['id'=>4,'status'=>'missing','production_id'=>12,'submission_status'=>null],
            ['id'=>5,'status'=>'requires_review','production_id'=>null,'submission_status'=>'submitted'],
            ['id'=>6,'status'=>'missing','production_id'=>99,'submission_status'=>null],
            ['id'=>7,'status'=>'missing','production_id'=>null,'submission_status'=>null],
            ['id'=>8,'status'=>'missing','production_id'=>11,'submission_status'=>'submitted'],
            ['id'=>9,'status'=>'due_soon','production_id'=>11,'submission_status'=>'rejected'],
        ],$this);
    }
    public function query(string $query,?int $fetchMode=null,...$fetchModeArgs): PDOStatement|false
    {
        $this->productionSql=$query;
        return new UXActionStatement([11],$this);
    }
}
$db=new UXActionPDO();
$method=new ReflectionMethod(NotificationExperience::class,'openForms');
$rows=$method->invoke(null,$db,7);
if(array_column($rows,'id')!==[1,2,7,9])throw new RuntimeException('Only actionable current-production or organization-wide assignments belong in actions; pending staff review is informational.');
if($db->params!==['viewer'=>7,'guardian'=>7])throw new RuntimeException('Reuse the existing viewer-scoped form service.');
foreach(["fr.status='active'","fr.relationship_type IN ('parent','guardian')","fa.user_id=:viewer"] as $guard){
    if(!str_contains($db->assignmentSql,$guard))throw new RuntimeException('Existing guardian/own-assignment query boundary missing.');
}
if(!str_contains($db->productionSql,"is_active=1")||!str_contains($db->productionSql,"'upcoming','planning','in_production','performing'"))throw new RuntimeException('Closed productions may not return as actionable work.');
echo "PASS: Action Center delegates viewer authorization and excludes completed/closed/unknown-production work.\n";
