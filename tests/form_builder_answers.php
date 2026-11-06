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

// Informational sections do not submit answers or require a response.
$section=[['id'=>3,'field_key'=>'about','field_type'=>'section_heading','required'=>false,'label'=>'Contact details','options'=>[]]];
if(DynamicFormService::validateAnswers($section,['field'=>[]])!==[])throw new RuntimeException('Section heading must not save an answer.');
$checks++;

$contacts=[
 ['id'=>4,'field_key'=>'email','field_type'=>'email','required'=>true,'label'=>'Email','options'=>[]],
 ['id'=>5,'field_key'=>'phone','field_type'=>'phone','required'=>true,'label'=>'Phone','options'=>[]],
 ['id'=>6,'field_key'=>'quantity','field_type'=>'number','required'=>true,'label'=>'Quantity','options'=>[]]
];
$validContacts=DynamicFormService::validateAnswers($contacts,['field'=>['email'=>'person@example.org','phone'=>'(301) 555-0102','quantity'=>'12.5']]);
if($validContacts['email']['answer']!=='person@example.org'||$validContacts['quantity']['answer']!=='12.5')throw new RuntimeException('Valid contact and numeric answers not preserved.');
$checks++;
foreach([
 ['email'=>'bad address','phone'=>'(301) 555-0102','quantity'=>'12'],
 ['email'=>'person@example.org','phone'=>'hello','quantity'=>'12'],
 ['email'=>'person@example.org','phone'=>'(301) 555-0102','quantity'=>'twelve'],
 ['email'=>['person@example.org'],'phone'=>'3015550102','quantity'=>'12']
] as $invalid){
    try{DynamicFormService::validateAnswers($contacts,['field'=>$invalid]);throw new LogicException('Invalid contact/number value accepted.');}
    catch(RuntimeException $e){$checks++;}
}

// Conditional questions are only required when the controlling answer matches.
$conditional=[
 ['id'=>10,'field_key'=>'allergies','field_type'=>'single_choice','required'=>true,'label'=>'Any allergies?','options'=>['Yes','No'],'condition'=>null],
 ['id'=>11,'field_key'=>'allergy_details','field_type'=>'long_text','required'=>true,'label'=>'Allergy details','options'=>[],'condition'=>['source_key'=>'allergies','equals'=>'Yes']]
];
$no=DynamicFormService::validateAnswers($conditional,['field'=>['allergies'=>'No','allergy_details'=>'Should not be saved']]);
if(isset($no['allergy_details']))throw new RuntimeException('Hidden conditional answer was saved.');
$checks++;
try{DynamicFormService::validateAnswers($conditional,['field'=>['allergies'=>'Yes']]);throw new LogicException('Visible required conditional question was skipped.');}
catch(RuntimeException $e){$checks++;}
$yes=DynamicFormService::validateAnswers($conditional,['field'=>['allergies'=>'Yes','allergy_details'=>'Peanuts']]);
if(($yes['allergy_details']['answer']??null)!=='Peanuts')throw new RuntimeException('Visible conditional answer not saved.');
$checks++;
echo 'PASS: '.$checks." structured form answer validation checks.\n";
