<?php

declare(strict_types=1);
require_once __DIR__.'/FormWriteGuard.php';

final class DynamicFormService
{
    public const FIELD_TYPES = ['short_text','long_text','single_choice','multiple_choice','date','acknowledgment','signature','resource_link','section_heading','email','phone','number'];

    public static function fields(PDO $db,int $formId,bool $activeOnly=true):array
    {
        if($formId<1)return [];
        $sql="SELECT id,form_id,field_key,label,help_text,field_type,required,options_json,condition_json,sort_order,active FROM form_fields WHERE form_id=:form".($activeOnly?" AND active=1":"")." ORDER BY active DESC,sort_order,id";
        $stmt=$db->prepare($sql);$stmt->execute(['form'=>$formId]);$rows=$stmt->fetchAll();
        foreach($rows as &$row){
            $decoded=json_decode((string)($row['options_json']??''),true);
            $condition=json_decode((string)($row['condition_json']??''),true);
            $row['condition']=is_array($condition)?$condition:null;
            if($row['field_type']==='resource_link'){
                $row['resource']=is_array($decoded)?$decoded:[];
                $row['options']=[];
                $linked=self::linkedResource($db,$row,null);
                $row['resource_title']=$linked['title']??'Linked resource unavailable';
                $row['resource_type']=$linked['resource_type']??null;
            }else{
                $row['options']=is_array($decoded)?$decoded:[];
                $row['resource']=[];
            }
        }unset($row);
        return $rows;
    }

    public static function saveField(PDO $db,int $formId,int $fieldId,array $input):int
    {
        FormWriteGuard::definitionEditable($db,$formId);
        $label=trim((string)($input['label']??''));$type=(string)($input['field_type']??'short_text');$help=trim((string)($input['help_text']??''));$required=isset($input['required'])?1:0;$sort=(int)($input['sort_order']??0);
        if($label===''||mb_strlen($label)>255)throw new RuntimeException('Enter a field label no longer than 255 characters.');
        if(!in_array($type,self::FIELD_TYPES,true))throw new RuntimeException('Choose a valid field type.');
        if(mb_strlen($help)>1000)throw new RuntimeException('Field help text must be 1,000 characters or fewer.');
        if($fieldId===0&&$sort===0){
            $last=$db->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM form_fields WHERE form_id=:form');
            $last->execute(['form'=>$formId]);$sort=(int)$last->fetchColumn();
        }
        $options=[];
        $json=null;
        if($type==='section_heading')$required=0;
        if(in_array($type,['single_choice','multiple_choice'],true)){
            $raw=preg_split('/\R/',trim((string)($input['options_text']??'')))?:[];
            foreach($raw as $option){$option=trim($option);if($option!==''&&!in_array($option,$options,true))$options[]=$option;}
            if(count($options)<2)throw new RuntimeException('Choice fields need at least two options.');
            if(count($options)>50)throw new RuntimeException('Choice fields support up to 50 options.');
            $json=json_encode($options,JSON_THROW_ON_ERROR);
        }elseif($type==='resource_link'){
            $ref=trim((string)($input['resource_ref']??''));
            if(!preg_match('/^(organization|production):(\d+)$/',$ref,$m))throw new RuntimeException('Choose a CTSMD resource to link.');
            $scope=$m[1];$resourceId=(int)$m[2];
            $form=self::formScope($db,$formId);
            if(!$form)throw new RuntimeException('That form no longer exists.');
            if($scope==='organization'){
                $s=$db->prepare("SELECT id FROM organization_resources WHERE id=:id AND status='active' LIMIT 1");$s->execute(['id'=>$resourceId]);if(!$s->fetchColumn())throw new RuntimeException('That organization resource is no longer active.');
            }else{
                if(empty($form['production_id']))throw new RuntimeException('Organization-wide forms can only link organization resources.');
                $s=$db->prepare("SELECT id FROM production_resources WHERE id=:id AND production_id=:production AND status='active' LIMIT 1");$s->execute(['id'=>$resourceId,'production'=>(int)$form['production_id']]);if(!$s->fetchColumn())throw new RuntimeException('That production resource is not available to this form.');
            }
            $required=isset($input['required'])?1:0;
            $json=json_encode(['scope'=>$scope,'resource_id'=>$resourceId,'require_open'=>(bool)$required],JSON_THROW_ON_ERROR);
        }
        $conditionJson=null;
        $sourceId=(int)($input['condition_source_id']??0);
        $match=(string)($input['condition_value']??'');
        if($sourceId>0){
            if(in_array($type,['resource_link','section_heading'],true))throw new RuntimeException('Conditions only apply to questions.');
            $stmt=$db->prepare('SELECT id,field_key,field_type,options_json,active,condition_json FROM form_fields WHERE id=:id AND form_id=:form LIMIT 1');
            $stmt->execute(['id'=>$sourceId,'form'=>$formId]);$source=$stmt->fetch();
            if(!$source||(int)$source['active']!==1||$source['field_type']!=='single_choice'||$source['condition_json']!==null)throw new RuntimeException('Choose an unconditional single-choice question.');
            $previous=$db->prepare('SELECT id FROM form_fields WHERE form_id=:form AND active=1 ORDER BY sort_order,id');
            $previous->execute(['form'=>$formId]);$order=array_map('intval',$previous->fetchAll(PDO::FETCH_COLUMN));
            $sourcePosition=array_search($sourceId,$order,true);
            $targetPosition=$fieldId>0?array_search($fieldId,$order,true):count($order);
            if($sourcePosition===false||$targetPosition===false||$sourcePosition>=$targetPosition)throw new RuntimeException('The controlling question must appear earlier in the form.');
            $choices=json_decode((string)$source['options_json'],true);
            if(!is_array($choices)||!in_array($match,$choices,true))throw new RuntimeException('Choose one of the controlling question’s options.');
            $conditionJson=json_encode(['source_key'=>(string)$source['field_key'],'equals'=>$match],JSON_THROW_ON_ERROR);
        }
        $key=trim((string)($input['field_key']??''));
        if($key==='')$key=self::slug($label);
        if(!preg_match('/^[a-z0-9_]{1,100}$/',$key))throw new RuntimeException('Field key may contain lowercase letters, numbers, and underscores only.');
        if($fieldId>0){
            $stmt=$db->prepare('SELECT field_key,field_type,sort_order FROM form_fields WHERE id=:id AND form_id=:form LIMIT 1');$stmt->execute(['id'=>$fieldId,'form'=>$formId]);$existing=$stmt->fetch();if(!$existing)throw new RuntimeException('That field no longer exists.');
            $sort=(int)$existing['sort_order']; // Reordering is performed only by moveField, which checks dependencies.
            if(self::hasDependents($db,$formId,(string)$existing['field_key']))throw new RuntimeException('This question controls other questions. Remove their conditions before editing it.');
            if($key!==(string)$existing['field_key']||$type!==(string)$existing['field_type']){
                $used=$db->prepare('SELECT 1 FROM form_submission_answers WHERE field_id=:id LIMIT 1');$used->execute(['id'=>$fieldId]);
                if($used->fetchColumn())throw new RuntimeException('This question already has submitted answers. Keep its question type and key, or add a new question instead.');
            }
            $update=$db->prepare('UPDATE form_fields SET field_key=:field_key,label=:label,help_text=:help,field_type=:type,required=:required,options_json=:options,condition_json=:condition,sort_order=:sort WHERE id=:id AND form_id=:form');
            $update->execute(['field_key'=>$key,'label'=>$label,'help'=>$help!==''?$help:null,'type'=>$type,'required'=>$required,'options'=>$json,'condition'=>$conditionJson,'sort'=>$sort,'id'=>$fieldId,'form'=>$formId]);
            return $fieldId;
        }
        $insert=$db->prepare('INSERT INTO form_fields (form_id,field_key,label,help_text,field_type,required,options_json,condition_json,sort_order,active) VALUES (:form,:field_key,:label,:help,:type,:required,:options,:condition,:sort,1)');
        $insert->execute(['form'=>$formId,'field_key'=>$key,'label'=>$label,'help'=>$help!==''?$help:null,'type'=>$type,'required'=>$required,'options'=>$json,'condition'=>$conditionJson,'sort'=>$sort]);
        return (int)$db->lastInsertId();
    }

    public static function availableResources(PDO $db,int $formId):array
    {
        $form=self::formScope($db,$formId);if(!$form)return [];$out=[];
        $rows=$db->query("SELECT id,title,category,resource_type FROM organization_resources WHERE status='active' ORDER BY category,title")->fetchAll();
        foreach($rows as $r)$out[]=['ref'=>'organization:'.(int)$r['id'],'scope'=>'organization','title'=>$r['title'],'category'=>$r['category'],'resource_type'=>$r['resource_type'],'context'=>'CTSMD'];
        if(!empty($form['production_id'])){
            $s=$db->prepare("SELECT pr.id,pr.title,pr.category,pr.resource_type,p.title production_title FROM production_resources pr JOIN productions p ON p.id=pr.production_id WHERE pr.production_id=:production AND pr.status='active' ORDER BY pr.category,pr.title");$s->execute(['production'=>(int)$form['production_id']]);
            foreach($s->fetchAll() as $r)$out[]=['ref'=>'production:'.(int)$r['id'],'scope'=>'production','title'=>$r['title'],'category'=>$r['category'],'resource_type'=>$r['resource_type'],'context'=>$r['production_title']];
        }
        return $out;
    }

    public static function toggleField(PDO $db,int $formId,int $fieldId):void
    {
        FormWriteGuard::definitionEditable($db,$formId);
        $stmt=$db->prepare('SELECT active FROM form_fields WHERE id=:id AND form_id=:form FOR UPDATE');$stmt->execute(['id'=>$fieldId,'form'=>$formId]);$active=$stmt->fetchColumn();if($active===false)throw new RuntimeException('That field no longer exists.');
        $source=$db->prepare('SELECT field_key FROM form_fields WHERE id=:id AND form_id=:form');
        $source->execute(['id'=>$fieldId,'form'=>$formId]);
        if(self::hasDependents($db,$formId,(string)$source->fetchColumn()))throw new RuntimeException('Remove the dependent question rules before deactivating this question.');
        $db->prepare('UPDATE form_fields SET active=:active WHERE id=:id')->execute(['active'=>(int)$active?0:1,'id'=>$fieldId]);
    }

    /**
     * Move a question one position within the builder's visible order.
     * Question IDs and keys stay unchanged, preserving saved answer references.
     * Caller owns the transaction and increments the definition version.
     */
    public static function moveField(PDO $db,int $formId,int $fieldId,string $direction):bool
    {
        FormWriteGuard::definitionEditable($db,$formId);
        if(!in_array($direction,['up','down'],true))throw new RuntimeException('Choose a direction to move the question.');
        $stmt=$db->prepare('SELECT id FROM form_fields WHERE form_id=:form ORDER BY active DESC,sort_order,id FOR UPDATE');
        $stmt->execute(['form'=>$formId]);
        $ids=array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
        $index=array_search($fieldId,$ids,true);
        if($index===false)throw new RuntimeException('That question no longer exists.');
        $other=$index+($direction==='up'?-1:1);
        if($other<0||$other>=count($ids))return false;
        // Do not move questions between active and inactive groups.
        $status=$db->prepare('SELECT active FROM form_fields WHERE id=:id');
        $status->execute(['id'=>$ids[$index]]);$current=(int)$status->fetchColumn();
        $status->execute(['id'=>$ids[$other]]);$neighbor=(int)$status->fetchColumn();
        if($current!==$neighbor)return false;
        [$ids[$index],$ids[$other]]=[$ids[$other],$ids[$index]];
        $q=$db->prepare('SELECT id,field_key,condition_json FROM form_fields WHERE form_id=:form AND active=1');
        $q->execute(['form'=>$formId]);
        $positions=array_flip($ids);$keys=[];
        $rows=$q->fetchAll();
        foreach($rows as $row)$keys[(string)$row['field_key']]=(int)$row['id'];
        foreach($rows as $row){
            $condition=json_decode((string)($row['condition_json']??''),true);
            if(!is_array($condition))continue;
            $sourceId=$keys[(string)($condition['source_key']??'')]??null;
            if($sourceId===null||$positions[$sourceId]>=$positions[(int)$row['id']])throw new RuntimeException('That move would put a question before the answer it depends on.');
        }
        $update=$db->prepare('UPDATE form_fields SET sort_order=:position WHERE id=:id AND form_id=:form');
        foreach($ids as $position=>$id)$update->execute(['position'=>$position+1,'id'=>$id,'form'=>$formId]);
        return true;
    }

    public static function bumpVersion(PDO $db,int $formId):int
    {
        FormWriteGuard::form($db,$formId);
        $db->prepare('UPDATE forms SET definition_version=definition_version+1 WHERE id=:id')->execute(['id'=>$formId]);
        $stmt=$db->prepare('SELECT definition_version FROM forms WHERE id=:id');$stmt->execute(['id'=>$formId]);return (int)$stmt->fetchColumn();
    }

    private static function hasDependents(PDO $db,int $formId,string $key):bool
    {
        $stmt=$db->prepare("SELECT 1 FROM form_fields WHERE form_id=:form AND JSON_UNQUOTE(JSON_EXTRACT(condition_json,'$.source_key'))=:source LIMIT 1");
        $stmt->execute(['form'=>$formId,'source'=>$key]);
        return (bool)$stmt->fetchColumn();
    }

    public static function isVisible(array $field,array $input):bool
    {
        $condition=$field['condition']??null;
        if(!is_array($condition))return true;
        $source=(string)($condition['source_key']??'');
        $expected=(string)($condition['equals']??'');
        $value=$input['field'][$source]??null;
        return $source!==''&&is_string($value)&&$value===$expected;
    }

    public static function validateAnswers(array $fields,array $input):array
    {
        $answers=[];
        foreach($fields as $field){
            $key=(string)$field['field_key'];$type=(string)$field['field_type'];$required=(bool)$field['required'];
            if(in_array($type,['resource_link','section_heading'],true))continue;
            if(!self::isVisible($field,$input))continue;
            $raw=$input['field'][$key]??null;$value=null;
            if($type==='multiple_choice'){
                if($raw!==null&&!is_array($raw))throw new RuntimeException('Choose valid options for '.$field['label'].'.');
                $vals=is_array($raw)?array_values(array_unique(array_map('strval',$raw))):[];$allowed=array_map('strval',$field['options']??[]);
                foreach($vals as $selected)if(!in_array($selected,$allowed,true))throw new RuntimeException('Choose valid options for '.$field['label'].'.');
                if($required&&!$vals)throw new RuntimeException('Complete the required field: '.$field['label'].'.');$value=$vals;
            }elseif($type==='acknowledgment'){
                $value=$raw==='1';if($required&&!$value)throw new RuntimeException('Confirm the required acknowledgment: '.$field['label'].'.');
            }else{
                if(is_array($raw))throw new RuntimeException('Enter a valid answer for '.$field['label'].'.');
                $value=trim((string)($raw??''));
                if($required&&$value==='')throw new RuntimeException('Complete the required field: '.$field['label'].'.');
                if(in_array($type,['short_text','signature'],true)&&mb_strlen($value)>500)throw new RuntimeException($field['label'].' is too long.');
                if($type==='long_text'&&mb_strlen($value)>10000)throw new RuntimeException($field['label'].' is too long.');
                if($type==='date'&&$value!==''){
                    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
                    if(!$date||$date->format('Y-m-d')!==$value)throw new RuntimeException('Enter a valid date for '.$field['label'].'.');
                }
                if($type==='single_choice'&&$value!==''&&!in_array($value,array_map('strval',$field['options']??[]),true))throw new RuntimeException('Choose a valid option for '.$field['label'].'.');
                if($type==='email'&&$value!==''&&(mb_strlen($value)>254||!filter_var($value,FILTER_VALIDATE_EMAIL)))throw new RuntimeException('Enter a valid email address for '.$field['label'].'.');
                if($type==='phone'&&$value!==''){
                    $digits=preg_replace('/\D/','',$value);
                    if(mb_strlen($value)>40||!preg_match('/^\+?[0-9().\-\s]+$/D',$value)||strlen((string)$digits)<7||strlen((string)$digits)>15)throw new RuntimeException('Enter a valid phone number for '.$field['label'].'.');
                }
                if($type==='number'&&$value!==''&&(strlen($value)>50||!preg_match('/^-?[0-9]+(?:\.[0-9]+)?$/D',$value)))throw new RuntimeException('Enter a valid number for '.$field['label'].'.');
            }
            $answers[$key]=['field_id'=>(int)$field['id'],'label'=>(string)$field['label'],'type'=>$type,'answer'=>$value];
        }
        return $answers;
    }

    public static function snapshot(int $version,array $fields):array
    {
        return ['definition_version'=>$version,'fields'=>array_map(static fn(array $f):array=>['id'=>(int)$f['id'],'key'=>(string)$f['field_key'],'label'=>(string)$f['label'],'help_text'=>$f['help_text']??null,'type'=>(string)$f['field_type'],'required'=>(bool)$f['required'],'condition'=>$f['condition']??null,'options'=>$f['field_type']==='resource_link'?($f['resource']??[]):($f['options']??[]),'sort_order'=>(int)$f['sort_order']],$fields)];
    }

    public static function writeAnswers(PDO $db,int $submissionId,array $answers):void
    {
        $db->prepare('DELETE FROM form_submission_answers WHERE submission_id=:submission')->execute(['submission'=>$submissionId]);
        $insert=$db->prepare('INSERT INTO form_submission_answers (submission_id,field_id,field_key,field_label,field_type,answer_json) VALUES (:submission,:field_id,:field_key,:label,:type,:answer)');
        foreach($answers as $key=>$answer){$insert->execute(['submission'=>$submissionId,'field_id'=>$answer['field_id'],'field_key'=>$key,'label'=>$answer['label'],'type'=>$answer['type'],'answer'=>json_encode($answer['answer'],JSON_THROW_ON_ERROR)]);}
    }

    public static function answers(PDO $db,int $submissionId):array
    {
        if($submissionId<1)return [];$stmt=$db->prepare('SELECT field_key,field_label,field_type,answer_json FROM form_submission_answers WHERE submission_id=:submission ORDER BY id');$stmt->execute(['submission'=>$submissionId]);$rows=$stmt->fetchAll();foreach($rows as &$row)$row['answer']=json_decode((string)$row['answer_json'],true);unset($row);return $rows;
    }

    public static function linkedResource(PDO $db,array $field,?int $assignmentProductionId):?array
    {
        if(($field['field_type']??'')!=='resource_link')return null;$cfg=$field['resource']??json_decode((string)($field['options_json']??''),true);if(!is_array($cfg))return null;$id=(int)($cfg['resource_id']??0);$scope=(string)($cfg['scope']??'');if($id<1)return null;
        if($scope==='organization'){
            $s=$db->prepare("SELECT id,title,category,description,resource_type,resource_url,body,stored_file_id,'organization' resource_scope FROM organization_resources WHERE id=:id AND status='active' LIMIT 1");$s->execute(['id'=>$id]);return$s->fetch()?:null;
        }
        if($scope==='production'){
            $sql="SELECT id,production_id,title,category,description,resource_type,resource_url,body,NULL stored_file_id,'production' resource_scope FROM production_resources WHERE id=:id AND status='active'".($assignmentProductionId!==null?' AND production_id=:production':'').' LIMIT 1';$s=$db->prepare($sql);$args=['id'=>$id];if($assignmentProductionId!==null)$args['production']=$assignmentProductionId;$s->execute($args);return$s->fetch()?:null;
        }
        return null;
    }

    public static function resourceWasOpened(PDO $db,int $assignmentId,int $fieldId,int $userId):bool
    {
        $f=$db->prepare("SELECT options_json FROM form_fields WHERE id=:id AND field_type='resource_link' LIMIT 1");$f->execute(['id'=>$fieldId]);$cfg=json_decode((string)($f->fetchColumn()?:''),true);if(!is_array($cfg))return false;$resourceId=(int)($cfg['resource_id']??0);$scope=(string)($cfg['scope']??'');if($resourceId<1||!in_array($scope,['organization','production'],true))return false;
        $s=$db->prepare("SELECT 1 FROM audit_events WHERE actor_user_id=:user AND event_type='form.resource_opened' AND subject_type='form_assignment' AND subject_id=:assignment AND CAST(JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.field_id')) AS UNSIGNED)=:field AND CAST(JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.resource_id')) AS UNSIGNED)=:resource AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.resource_scope'))=:scope LIMIT 1");$s->execute(['user'=>$userId,'assignment'=>$assignmentId,'field'=>$fieldId,'resource'=>$resourceId,'scope'=>$scope]);return(bool)$s->fetchColumn();
    }

    public static function recordResourceOpen(PDO $db,int $assignmentId,array $field,int $userId,array $resource):void
    {
        if(self::resourceWasOpened($db,$assignmentId,(int)$field['id'],$userId))return;
        $meta=['field_id'=>(int)$field['id'],'form_id'=>(int)$field['form_id'],'resource_scope'=>(string)$resource['resource_scope'],'resource_id'=>(int)$resource['id'],'resource_title'=>(string)$resource['title']];
        $s=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,'form.resource_opened','form_assignment',:assignment,:summary,:meta)");$s->execute(['actor'=>$userId,'assignment'=>$assignmentId,'summary'=>'Opened linked form resource: '.$resource['title'].'.','meta'=>json_encode($meta,JSON_THROW_ON_ERROR)]);
    }

    public static function assertRequiredResourcesOpened(PDO $db,array $fields,int $assignmentId,int $userId):void
    {
        foreach($fields as $field){
            if(($field['field_type']??'')!=='resource_link'||empty($field['required']))continue;
            if(!self::linkedResource($db,$field,null))throw new RuntimeException('A required linked resource is unavailable: '.$field['label'].'. Contact CTSMD staff before submitting.');
            if(!self::resourceWasOpened($db,$assignmentId,(int)$field['id'],$userId))throw new RuntimeException('Open the required resource before submitting: '.$field['label'].'.');
        }
    }

    private static function formScope(PDO $db,int $formId):?array
    {
        $s=$db->prepare('SELECT id,production_id FROM forms WHERE id=:id LIMIT 1');$s->execute(['id'=>$formId]);return$s->fetch()?:null;
    }

    private static function slug(string $value):string
    {
        $value=mb_strtolower($value);$value=preg_replace('/[^a-z0-9]+/','_',$value)??'';$value=trim($value,'_');return substr($value!==''?$value:'field',0,100);
    }
}
