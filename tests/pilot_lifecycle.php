<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/src/VolunteerLifecycleGuard.php';

// Exercise the web lifecycle guard without credentials or a live database.
// SQL/trigger integration still requires migrated staging.
final class PilotStatement extends PDOStatement
{
    public function __construct(private mixed $row) {}
    public function execute(?array $params = null): bool { return true; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return $this->row; }
    public function fetchColumn(int $column = 0): mixed { return $this->row; }
}
final class PilotDatabase extends PDO
{
    public function __construct(private mixed $shift) {}
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new PilotStatement(str_contains($query, 'volunteer_shift_approval_requests') ? 10 : $this->shift);
    }
}

$checks=0;
$future=['starts_at'=>date('Y-m-d H:i:s',time()+3600),'production_id'=>1,'is_active'=>1];
$inactive=$future;$inactive['is_active']=0;
$organization=$future;$organization['production_id']=null;$organization['is_active']=null;
$past=$future;$past['starts_at']=date('Y-m-d H:i:s',time()-3600);
$_SERVER['REQUEST_METHOD']='POST';
foreach ([['/volunteer/shift','signup'],['/volunteer/approvals','request'],['/admin/volunteer-approvals','approve'],['/admin/volunteer-approvals/review','approve']] as [$route,$action]) {
    foreach ([[$future,false],[$inactive,true],[$organization,false],[$past,true],[false,true]] as [$shift,$reject]) {
        $thrown=false;
        try { VolunteerLifecycleGuard::assertActionAllowed(new PilotDatabase($shift),$route,['action'=>$action,'shift_id'=>10,'request_id'=>20]); }
        catch (RuntimeException) { $thrown=true; }
        if($thrown!==$reject)throw new RuntimeException('Unexpected lifecycle result: '.$route.' '.$action);
        $checks++;
    }
}
// Historical/non-enrollment actions must remain possible for corrections.
VolunteerLifecycleGuard::assertActionAllowed(new PilotDatabase($inactive),'/volunteer/shift',['action'=>'cancel','shift_id'=>10]);
$checks++;
echo "PASS: {$checks} lifecycle checks (stubbed database; not SQL integration).\n";
