<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/ProductionParticipantEligibility.php';
$checks=0;
$assert=static function(bool $ok,string $label)use(&$checks):void{if(!$ok)throw new RuntimeException($label);$checks++;};
$base=['lifecycle_stage'=>'planning','status'=>'planning','participant_category'=>'mixed','student_minimum_age'=>8,'student_maximum_age'=>17,'participant_eligibility_date'=>'2026-10-28'];
$cases=[['2018-10-28','eligible'],['2018-10-29','below_minimum_age'],['2008-10-29','eligible'],['2008-10-28','above_maximum_age'],[null,'missing_eligibility_data']];
foreach($cases as [$dob,$expected])$assert(ProductionParticipantEligibility::evaluate($base,'student',$dob)['status']===$expected,'Birthday boundary '.$expected);
$assert(ProductionParticipantEligibility::evaluate($base,'adult',null)['eligible'],'Adult independent of Student age bounds');
$assert(ProductionParticipantEligibility::evaluate($base,'student',null,true)['status']==='authorized_exception','Approved age exception');
$assert(!ProductionParticipantEligibility::evaluate(array_replace($base,['participant_category'=>'adults']),'student','2015-01-01',true)['eligible'],'Exception cannot waive category');
$assert(!ProductionParticipantEligibility::evaluate(array_replace($base,['participant_category'=>'students']),'adult','1980-01-01')['eligible'],'Student-only category');
$assert(!ProductionParticipantEligibility::evaluate($base,'unknown','1980-01-01')['eligible'],'No DOB-based classification');
foreach(['completed','archived','invalid'] as $stage)$assert(!ProductionParticipantEligibility::evaluate(array_replace($base,['lifecycle_stage'=>$stage]),'adult',null)['eligible'],'Closed or invalid production');
$assert(PersonEligibility::ageOn('2012-02-29','2025-02-28')===12,'Leap birthday not completed February 28');
$assert(PersonEligibility::ageOn('2012-02-29','2025-03-01')===13,'Leap birthday completed March 1');
$assert(PersonEligibility::ageOn('2012-02-29','2024-02-29')===12,'Leap year birthday');
foreach([[null,null],[8,null],[null,17]] as [$min,$max])$assert(ProductionParticipantEligibility::evaluate(array_replace($base,['student_minimum_age'=>$min,'student_maximum_age'=>$max]),'student','2015-01-01')['eligible'],'Optional bounds');
echo "PASS $checks pure eligibility checks (not database acceptance).\n";
