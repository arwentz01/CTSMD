<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/src/PersonEligibility.php';

function expectation(bool $passed,string $name):void
{
    if(!$passed)throw new RuntimeException('Failed: '.$name);
}

$signup=['minimum_age'=>12,'maximum_age'=>17,'age_eligibility_date'=>'2026-11-15','starts_at'=>'2026-12-01 18:00:00'];
expectation(PersonEligibility::declaredAge('12')===12,'parse guest age');
expectation(PersonEligibility::evaluateDeclared($signup,12)['status']==='self_reported','minimum is inclusive');
expectation(PersonEligibility::evaluateDeclared($signup,11)['status']==='ineligible','below minimum');
expectation(PersonEligibility::evaluateDeclared($signup,17)['status']==='self_reported','maximum inclusive');
expectation(PersonEligibility::evaluateDeclared($signup,18)['status']==='ineligible','above maximum');
expectation(PersonEligibility::evaluate($signup,'2014-11-15')['status']==='eligible','account DOB exact birthday');
expectation(PersonEligibility::evaluate($signup,'2014-11-16')['status']==='ineligible','account DOB day too young');
expectation(PersonEligibility::evaluate($signup,'2008-11-15')['status']==='ineligible','account DOB over maximum');
expectation(PersonEligibility::evaluate($signup,null)['status']==='needs_review','linked account missing birthdate');
expectation(PersonEligibility::evaluateDeclared(['minimum_age'=>0,'maximum_age'=>null],0)['status']==='self_reported','zero min');
foreach(['','-1','121','abc','13.5'] as $bad){
    try{PersonEligibility::declaredAge($bad);throw new LogicException('Invalid guest age accepted: '.$bad);}
    catch(RuntimeException $expected){}
}
echo "PASS: exact guest self-report and account DOB eligibility boundaries.\n";
