<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/src/PersonEligibility.php';

function check(bool $condition,string $reason):void{if(!$condition)throw new RuntimeException($reason);}
$show=['minimum_age'=>10,'maximum_age'=>15,'age_eligibility_date'=>'2026-11-01','starts_at'=>'2026-12-01 09:00:00'];
check(PersonEligibility::ageOn('2011-11-01','2026-11-01')===15,'Birthday on cutoff should count.');
check(PersonEligibility::ageOn('2011-11-02','2026-11-01')===14,'Birthday after cutoff must not count.');
check(PersonEligibility::evaluate($show,'2011-11-01')['status']==='eligible','Upper bound should be inclusive.');
check(PersonEligibility::evaluate($show,'2016-11-01')['status']==='eligible','Lower bound should be inclusive.');
check(PersonEligibility::evaluate($show,'2016-11-02')['status']==='ineligible','Below lower bound must be rejected.');
check(PersonEligibility::evaluate($show,'2010-10-31')['status']==='ineligible','Above upper bound must be rejected.');
check(PersonEligibility::evaluate($show,null)['status']==='needs_review','Unknown DOB needs staff review.');
check(PersonEligibility::evaluate(['minimum_age'=>null,'maximum_age'=>null],null)['status']==='unrestricted','Unrestricted auditions accept missing DOB.');
check(PersonEligibility::cutoff(['starts_at'=>'2026-12-01 12:00:00'])==='2026-12-01','Default cutoff uses audition start date.');
check(PersonEligibility::group('2013-11-02','2026-11-01')==='under_13','Under-13 group boundary.');
check(PersonEligibility::group('2013-11-01','2026-11-01')==='13_17','13th birthday boundary.');
check(PersonEligibility::group('2008-11-01','2026-11-01')==='adult','18th birthday boundary.');
foreach(['2026-02-30','1899-01-01','2099-01-01','not a date'] as $invalid){
    try{PersonEligibility::birthDate($invalid);throw new RuntimeException('Accepted invalid birth date: '.$invalid);}
    catch(RuntimeException $e){if(str_contains($e->getMessage(),'Accepted invalid'))throw $e;}
}
$contact=['contact_phone'=>'301-555-0100','address_line1'=>'123 Main St','address_city'=>'Clinton','address_state'=>'MD','address_postal_code'=>'20735'];
check(PersonEligibility::contact($contact)['contact_phone']==='301-555-0100','Contact fields retained.');
try{PersonEligibility::contact(['contact_phone'=>'']);throw new RuntimeException('Accepted missing phone.');}catch(RuntimeException $e){if($e->getMessage()==='Accepted missing phone.')throw $e;}
echo "PASS: birthday boundaries, guest unknown DOB, age cutoff and contact validation.\n";
