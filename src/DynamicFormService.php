<?php

declare(strict_types=1);

final class DynamicFormService
{
    public const FIELD_TYPES = ['short_text','long_text','single_choice','multiple_choice','date','acknowledgment','signature'];

    public static function fields(PDO $db,int $formId,bool $activeOnly=true):array
    {
        if($formId<1)return [];
        $sql="SELECT id,form_id,field_key,label,help_text,field_type,required,options_json,sort_order,active FROM form_fields WHERE form_id=:form".($activeOnly?" AND active=1":"")." ORDER BY active DESC,sort_order,id";
        $stmt=$db->prepare($sql);$stmt->execute(['form'=>$formId]);$rows=$stmt->fetchAll();
        foreach($rows as &$row){$decoded=json_decode((string)($row['options_json']??''),true);$row['options']=is_array($decoded)?$decoded:[];}unset($row);
        return $rows;
    }

    public static function saveField(PDO $db,int $formId,int $fieldId,array $input):int
    {
        $label=trim((string)($input['label']??''));$type=(string)($input['field_type']??'short_text');$help=trim((string)($input['help_text']??''));$required=isset($input['required'])?1:0;$sort=(int)($input['sort_order']??0);
        if($label===''||mb_strlen($label)>255)throw new RuntimeException('Enter a field label no longer than 255 characters.');
        if(!in_array($type,self::FIELD_TYPES,true))throw new RuntimeException('Choose a valid field type.');
        if(mb_strlen($help)>1000)throw new RuntimeException('Field help text must be 1,000 characters or fewer.');
        $options=[];
        if(in_array($type,['single_choice','multiple_choice'],true)){
            $raw=preg_split('/\R/',trim((string)($input['options_text']??'')))?:[];
            foreach($raw as $option){$option=trim($option);if($option!==''&&!in_array($option,$options,true))$options[]=$option;}
            if(count($options)<2)throw new RuntimeException('Choice fields need at least two options.');
            if(count($options)>50)throw new RuntimeException('Choice fields support up to 50 options.');
        }
        $key=trim((string)($input['field_key']??''));
        if($key==='')$key=self::slug($label);
        if(!preg_match('/^[a-z0-9_]{1,100}$/',$key))throw new RuntimeException('Field key may contain lowercase letters, numbers, and underscores only.');
        $json=$options?json_encode($options,JSON_THROW_ON_ERROR):null;
        if($fieldId>0){
            $stmt=$db->prepare('SELECT id FROM form_fields WHERE id=:id AND form_id=:form LIMIT 1');$stmt->execute(['id'=>$fieldId,'form'=>$formId]);if(!$stmt->fetchColumn())throw new RuntimeException('That field no longer exists.');
            $update=$db->prepare('UPDATE form_fields SET field_key=:field_key,label=:label,help_text=:help,field_type=:type,required=:required,options_json=:options,sort_order=:sort WHERE id=:id AND form_id=:form');
            $update->execute(['field_key'=>$key,'label'=>$label,'help'=>$help!==''?$help:null,'type'=>$type,'required'=>$required,'options'=>$json,'sort'=>$sort,'id'=>$fieldId,'form'=>$formId]);
            return $fieldId;
        }
        $insert=$db->prepare('INSERT INTO form_fields (form_id,field_key,label,help_text,field_type,required,options_json,sort_order,active) VALUES (:form,:field_key,:label,:help,:type,:required,:options,:sort,1)');
        $insert->execute(['form'=>$formId,'field_key'=>$key,'label'=>$label,'help'=>$help!==''?$help:null,'type'=>$type,'required'=>$required,'options'=>$json,'sort'=>$sort]);
        return (int)$db->lastInsertId();
    }

    public static function toggleField(PDO $db,int $formId,int $fieldId):void
    {
        $stmt=$db->prepare('SELECT active FROM form_fields WHERE id=:id AND form_id=:form FOR UPDATE');$stmt->execute(['id'=>$fieldId,'form'=>$formId]);$active=$stmt->fetchColumn();if($active===false)throw new RuntimeException('That field no longer exists.');
        $db->prepare('UPDATE form_fields SET active=:active WHERE id=:id')->execute(['active'=>(int)$active?0:1,'id'=>$fieldId]);
    }

    public static function bumpVersion(PDO $db,int $formId):int
    {
        $db->prepare('UPDATE forms SET definition_version=definition_version+1 WHERE id=:id')->execute(['id'=>$formId]);
        $stmt=$db->prepare('SELECT definition_version FROM forms WHERE id=:id');$stmt->execute(['id'=>$formId]);return (int)$stmt->fetchColumn();
    }

    public static function validateAnswers(array $fields,array $input):array
    {
        $answers=[];
        foreach($fields as $field){
            $key=(string)$field['field_key'];$type=(string)$field['field_type'];$required=(bool)$field['required'];$raw=$input['field'][$key]??null;$value=null;
            if($type==='multiple_choice'){
                $vals=is_array($raw)?array_values(array_unique(array_map('strval',$raw))):[];$allowed=array_map('strval',$field['options']??[]);$vals=array_values(array_filter($vals,static fn(string $v):bool=>in_array($v,$allowed,true)));
                if($required&&!$vals)throw new RuntimeException('Complete the required field: '.$field['label'].'.');$value=$vals;
            }elseif($type==='acknowledgment'){
                $value=$raw==='1';if($required&&!$value)throw new RuntimeException('Confirm the required acknowledgment: '.$field['label'].'.');
            }else{
                $value=trim((string)($raw??''));
                if($required&&$value==='')throw new RuntimeException('Complete the required field: '.$field['label'].'.');
                if(in_array($type,['short_text','signature'],true)&&mb_strlen($value)>500)throw new RuntimeException($field['label'].' is too long.');
                if($type==='long_text'&&mb_strlen($value)>10000)throw new RuntimeException($field['label'].' is too long.');
                if($type==='date'&&$value!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$value))throw new RuntimeException('Enter a valid date for '.$field['label'].'.');
                if($type==='single_choice'&&$value!==''&&!in_array($value,array_map('strval',$field['options']??[]),true))throw new RuntimeException('Choose a valid option for '.$field['label'].'.');
            }
            $answers[$key]=['field_id'=>(int)$field['id'],'label'=>(string)$field['label'],'type'=>$type,'answer'=>$value];
        }
        return $answers;
    }

    public static function snapshot(int $version,array $fields):array
    {
        return ['definition_version'=>$version,'fields'=>array_map(static fn(array $f):array=>['id'=>(int)$f['id'],'key'=>(string)$f['field_key'],'label'=>(string)$f['label'],'help_text'=>$f['help_text']??null,'type'=>(string)$f['field_type'],'required'=>(bool)$f['required'],'options'=>$f['options']??[],'sort_order'=>(int)$f['sort_order']],$fields)];
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

    private static function slug(string $value):string
    {
        $value=mb_strtolower($value);$value=preg_replace('/[^a-z0-9]+/','_',$value)??'';$value=trim($value,'_');return substr($value!==''?$value:'field',0,100);
    }
}
