<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/ProductionListOrganizer.php';
$rows=[
 ['id'=>1,'title'=>'Oldest','lifecycle_stage'=>'archived','status'=>'archived','is_active'=>0,'performance_ends_at'=>'2022-05-01'],
 ['id'=>2,'title'=>'Soon','lifecycle_stage'=>'upcoming','status'=>'planning','is_active'=>0,'performance_starts_at'=>'2027-03-01'],
 ['id'=>3,'title'=>'Just closed','lifecycle_stage'=>'completed','status'=>'current','is_active'=>1,'performance_ends_at'=>'2026-09-15'],
 ['id'=>4,'title'=>'Performing','lifecycle_stage'=>'performing','status'=>'current','is_active'=>1,'performance_starts_at'=>'2026-10-01'],
 ['id'=>5,'title'=>'Later','lifecycle_stage'=>'planning','status'=>'planning','is_active'=>0,'performance_starts_at'=>'2027-12-01'],
 ['id'=>6,'title'=>'Archived recently','lifecycle_stage'=>'archived','status'=>'current','is_active'=>1,'performance_ends_at'=>'2026-10-10'],
 ['id'=>7,'title'=>'Second soon','lifecycle_stage'=>'upcoming','status'=>'planning','is_active'=>0,'performance_starts_at'=>'2027-04-01'],
];
$sections=ProductionListOrganizer::split($rows);
$check=static function(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);echo "PASS: ".$message."\n";};
$check(array_column($sections['current'],'id')===[4,2,7,5],'current/performing first then nearest-to-furthest upcoming');
$check(array_column($sections['past'],'id')===[6,3,1],'closed list newest-to-oldest regardless legacy active flag');
$check(count($rows)===count($sections['current'])+count($sections['past']),'all productions retained');
$source=file_get_contents(dirname(__DIR__).'/src/ProductionLifecycleExperience.php');
$check(str_contains((string)$source,'<details class="pl-past-productions">'),'Past Productions uses a collapsed native details element');
$check(str_contains((string)$source,"ProductionListOrganizer::split("),'staff directory uses lifecycle sorting');
