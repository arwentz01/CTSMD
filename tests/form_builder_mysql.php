<?php
declare(strict_types=1);

/**
 * Isolated migrated MySQL integration checks.
 * Requires the RC1 disposable loopback schema. Every fixture is rolled back.
 */
require_once dirname(__DIR__).'/src/Database.php';
require_once dirname(__DIR__).'/src/DynamicFormService.php';

if(PHP_SAPI!=='cli'||getenv('APP_ENV')!=='rc1-test'||getenv('DB_HOST')!=='127.0.0.1'||!preg_match('/^ctsmd_rc1_[a-z0-9_]{1,40}$/',(string)getenv('DB_DATABASE'))){
    throw new RuntimeException('Refusing to run outside disposable RC1 test database.');
}
$db=Database::connect(dirname(__DIR__));
$checks=0;
$ok=static function(bool $assertion,string $description)use(&$checks):void{
    if(!$assertion)throw new RuntimeException($description);
    ++$checks;echo 'PASS '.$description."\n";
};
$denied=static function(callable $fn,string $description)use($ok):void{
    try{$fn();}catch(RuntimeException $e){$ok(true,$description);return;}
    throw new RuntimeException($description.' was permitted.');
};
$db->beginTransaction();
try{
    $columns=$db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='form_fields'")->fetchAll(PDO::FETCH_COLUMN);
    $ok(in_array('condition_json',$columns,true),'Conditional-question migration applied');
    $types=(string)$db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='form_fields' AND COLUMN_NAME='field_type'")->fetchColumn();
    foreach(['section_heading','email','phone','number'] as $type)$ok(str_contains($types,"'".$type."'"),'Field enum includes '.$type);

    $db->exec("INSERT INTO forms(title,form_type,instructions,completion_mode,review_required,active) VALUES ('Conditional integration fixture','acknowledgment','Test only','submission',0,0)");
    $formId=(int)$db->lastInsertId();

    $parent=DynamicFormService::saveField($db,$formId,0,[
        'label'=>'Does your child have allergies?', 'field_key'=>'allergies','field_type'=>'single_choice',
        'options_text'=>"Yes\nNo",'required'=>'1'
    ]);
    $child=DynamicFormService::saveField($db,$formId,0,[
        'label'=>'Describe the reaction','field_key'=>'reaction','field_type'=>'long_text',
        'required'=>'1','condition_source_id'=>(string)$parent,'condition_value'=>'Yes'
    ]);
    DynamicFormService::saveField($db,$formId,0,['label'=>'Contact details','field_key'=>'contact_heading','field_type'=>'section_heading']);
    DynamicFormService::saveField($db,$formId,0,['label'=>'Email','field_key'=>'email','field_type'=>'email','required'=>'1']);
    DynamicFormService::saveField($db,$formId,0,['label'=>'Phone','field_key'=>'phone','field_type'=>'phone','required'=>'1']);
    DynamicFormService::saveField($db,$formId,0,['label'=>'Quantity','field_key'=>'quantity','field_type'=>'number','required'=>'1']);

    $fields=DynamicFormService::fields($db,$formId);
    $ok(count($fields)===6,'All six mixed-type fields persisted');
    $childField=array_values(array_filter($fields,static fn(array $f):bool=>$f['field_key']==='reaction'))[0]??null;
    $ok($childField!==null&&($childField['condition']['source_key']??null)==='allergies','Conditional configuration round-trips through MySQL');
    $ok(count(array_filter($fields,static fn(array $f):bool=>$f['field_type']==='section_heading'))===1,'Section heading round-trips through MySQL');

    $base=['email'=>'parent@example.org','phone'=>'301-555-0112','quantity'=>'2.5'];
    $no=DynamicFormService::validateAnswers($fields,['field'=>$base+['allergies'=>'No','reaction'=>'Do not retain this']]);
    $ok(!isset($no['reaction'])&&count($no)===4,'Hidden question ignored in validated answers');
    $denied(static fn()=>DynamicFormService::validateAnswers($fields,['field'=>$base+['allergies'=>'Yes']]),'Visible required follow-up cannot be omitted');
    $yes=DynamicFormService::validateAnswers($fields,['field'=>$base+['allergies'=>'Yes','reaction'=>'Anaphylaxis']]);
    $ok(($yes['reaction']['answer']??null)==='Anaphylaxis','Visible follow-up validated');

    $denied(static fn()=>DynamicFormService::moveField($db,$formId,$child,'up'),'Dependent question cannot move before its controller');
    $denied(static fn()=>DynamicFormService::toggleField($db,$formId,$parent),'Controller cannot deactivate with dependents');
    $denied(static fn()=>DynamicFormService::saveField($db,$formId,$parent,['label'=>'Edited controller','field_key'=>'allergies','field_type'=>'single_choice','options_text'=>"Yes\nNo"]),'Controller cannot be edited while dependents exist');

    $db->prepare("INSERT INTO users(first_name,last_name,email,initials,display_role,active,account_status,organization_membership_status) VALUES('Form','Fixture',?,'FF','member',1,'active','approved')")->execute(['form-'.bin2hex(random_bytes(7)).'@example.invalid']);
    $person=(int)$db->lastInsertId();
    $db->prepare("INSERT INTO form_assignments(form_id,user_id,subject_user_id,status) VALUES(:form,:user,:subject,'missing')")->execute(['form'=>$formId,'user'=>$person,'subject'=>$person]);
    $denied(static fn()=>DynamicFormService::saveField($db,$formId,0,['label'=>'Late question','field_key'=>'late','field_type'=>'short_text']),'Assigned form definition locked');

    echo 'PASS: '.$checks." migrated Form Builder/MySQL checks. All fixtures rolled back.\n";
}finally{
    if($db->inTransaction())$db->rollBack();
}
