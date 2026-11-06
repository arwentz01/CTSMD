<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/DynamicFormService.php';

$fields=[
 ['id'=>1,'field_key'=>'date','field_type'=>'date','required'=>true,'label'=>'Event date','options'=>[]],
 ['id'=>2,'field_key'=>'days','field_type'=>'multiple_choice','required'=>true,'label'=>'Available days','options'=>['Monday','Friday']],
];
$checks=0;
$valid=DynamicFormService::validateAnswers($fields,['field'=>['date'=>'2026-11-06','days'=>['Monday','Friday']]]);
if($valid['date']['answer']!=='2026-11-06'||count($valid['days']['answer'])!==2)throw new RuntimeException('Valid form answers not preserved.');
$checks++;
$cases=[
 ['date'=>'2026-02-30','days'=>['Monday']],
 ['date'=>'2026-13-01','days'=>['Monday']],
 ['date'=>'2026-11-06','days'=>['Unknown']],
 ['date'=>'2026-11-06','days'=>['Monday','Unknown']],
];
foreach($cases as $values){
    try{DynamicFormService::validateAnswers($fields,['field'=>$values]);throw new LogicException('Invalid answer accepted.');}
    catch(RuntimeException $e){$checks++;}
}
try{DynamicFormService::validateAnswers($fields,['field'=>['date'=>'2026-11-06','days'=>'Monday']]);throw new LogicException('Scalar multiple choice accepted.');}
catch(RuntimeException $e){$checks++;}
echo 'PASS: '.$checks." structured form answer validation checks.\n";
