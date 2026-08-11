<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/AppNavigation.php';
require_once __DIR__.'/AccessPolicy.php';
require_once __DIR__.'/DynamicFormService.php';

final class DynamicFormExperience
{
    private const ROUTES=['/admin/forms/builder'];

    public static function handles(string $route):bool
    {
        return in_array($route,self::ROUTES,true);
    }

    public static function render(string $route,string $basePath):never
    {
        Auth::startSession();
        $db=Database::connect(dirname(__DIR__));
        $user=Auth::currentUser($db);
        if(!$user)self::redirect(($basePath?:'').'/login');
        if(!AccessPolicy::canManageForms($user))self::forbidden();
        $_SESSION['dynamic_forms_csrf']??=bin2hex(random_bytes(24));

        $formId=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:filter_input(INPUT_POST,'form_id',FILTER_VALIDATE_INT)?:0;
        $form=self::form($db,(int)$formId);
        if($_SERVER['REQUEST_METHOD']==='POST')self::handleBuilderPost($db,$user,(int)$formId,$basePath);
        self::builderPage($db,$basePath,$user,$form);
    }

    private static function handleBuilderPost(PDO $db,array $user,int $formId,string $basePath):never
    {
        self::csrf();
        $action=(string)($_POST['action']??'');
        try{
            if(!$formId||!self::form($db,$formId))throw new RuntimeException('Choose an existing form before editing its fields.');
            $db->beginTransaction();
            if($action==='save_field'){
                $fieldId=filter_input(INPUT_POST,'field_id',FILTER_VALIDATE_INT)?:0;
                $saved=DynamicFormService::saveField($db,$formId,(int)$fieldId,$_POST);
                $version=DynamicFormService::bumpVersion($db,$formId);
                self::audit($db,(int)$user['id'],$fieldId?'form.field_updated':'form.field_created','form_field',$saved,'Updated dynamic form definition.',['form_id'=>$formId,'definition_version'=>$version]);
            }elseif($action==='toggle_field'){
                $fieldId=filter_input(INPUT_POST,'field_id',FILTER_VALIDATE_INT)?:0;
                DynamicFormService::toggleField($db,$formId,(int)$fieldId);
                $version=DynamicFormService::bumpVersion($db,$formId);
                self::audit($db,(int)$user['id'],'form.field_toggled','form_field',(int)$fieldId,'Changed dynamic form field availability.',['form_id'=>$formId,'definition_version'=>$version]);
            }else throw new RuntimeException('Choose a valid form-builder action.');
            $db->commit();
            self::flash('success','Form fields updated.');
        }catch(Throwable $e){
            if($db->inTransaction())$db->rollBack();
            self::flash('error',$e instanceof RuntimeException?$e->getMessage():'The form field could not be saved.');
        }
        self::redirect($basePath.'/admin/forms/builder?id='.$formId);
    }

    private static function builderPage(PDO $db,string $basePath,array $user,?array $form):never
    {
        $url=static fn(string $p):string=>($basePath?:'').$p;
        $e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');
        $flash=self::takeFlash();
        $fields=$form?DynamicFormService::fields($db,(int)$form['id'],false):[];
        $editId=filter_input(INPUT_GET,'field',FILTER_VALIDATE_INT)?:0;
        $editing=null;
        foreach($fields as $field)if((int)$field['id']===(int)$editId)$editing=$field;
        $subnav=[
            ['label'=>'Review queue','href'=>'/admin/forms','active'=>false],
            ['label'=>'Manage forms','href'=>'/admin/forms/manage','active'=>false],
            ['label'=>'Form builder','href'=>'/admin/forms/build','active'=>true],
            ['label'=>'Group assignment','href'=>'/admin/forms/group-assign','active'=>false],
        ];
        header('Content-Type:text/html; charset=utf-8');?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Form builder · CTSMD Connect</title><link rel="stylesheet" href="<?=$url('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$url('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$url('/assets/css/dynamic-forms.css')?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/admin/forms/builder',$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Forms','Dynamic form builder',$basePath,$subnav);?><div class="df-page"><?php if($flash):?><div class="df-flash <?=$e($flash['type'])?>"><?=$e($flash['message'])?></div><?php endif;?><?php if(!$form):?><section class="df-empty"><h2>Choose a form to build.</h2><p>Open the Form Builder index and select an existing form.</p><a class="button" href="<?=$url('/admin/forms/build')?>">Form Builder</a></section><?php else:?><section class="df-hero"><div><small>DEFINITION VERSION <?=(int)$form['definition_version']?></small><h2><?=$e((string)$form['title'])?></h2><p>Add structured questions without changing the existing assignment/review workflow.</p></div><a href="<?=$url('/admin/forms/manage/edit?id='.(int)$form['id'])?>">Form settings →</a></section><div class="df-layout"><section class="df-fields"><header><h3>Fields</h3><span><?=count(array_filter($fields,static fn($f)=>(bool)$f['active']))?> active</span></header><?php if(!$fields):?><div class="df-empty compact"><b>No structured fields yet.</b></div><?php endif;?><?php foreach($fields as $f):?><article class="df-field<?=!$f['active']?' inactive':''?>"><div><small><?=$e(str_replace('_',' ',strtoupper((string)$f['field_type'])))?> · <?=$f['required']?'REQUIRED':'OPTIONAL'?></small><h4><?=$e((string)$f['label'])?></h4><p><?=$e((string)($f['help_text']??''))?></p><?php if($f['options']):?><em><?=$e(implode(' · ',$f['options']))?></em><?php endif;?></div><footer><a href="<?=$url('/admin/forms/builder?id='.(int)$form['id'].'&field='.(int)$f['id'])?>">Edit</a><form method="post"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['dynamic_forms_csrf'])?>"><input type="hidden" name="form_id" value="<?=(int)$form['id']?>"><input type="hidden" name="field_id" value="<?=(int)$f['id']?>"><button name="action" value="toggle_field" type="submit"><?=$f['active']?'Deactivate':'Reactivate'?></button></form></footer></article><?php endforeach;?></section><form class="df-editor" method="post"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['dynamic_forms_csrf'])?>"><input type="hidden" name="form_id" value="<?=(int)$form['id']?>"><input type="hidden" name="field_id" value="<?=(int)($editing['id']??0)?>"><input type="hidden" name="action" value="save_field"><small><?=$editing?'EDIT FIELD':'ADD FIELD'?></small><label>Question / label<input name="label" maxlength="255" required value="<?=$e((string)($editing['label']??''))?>"></label><label>Field type<select name="field_type"><?php foreach(DynamicFormService::FIELD_TYPES as $type):?><option value="<?=$e($type)?>"<?=($editing['field_type']??'')===$type?' selected':''?>><?=$e(ucwords(str_replace('_',' ',$type)))?></option><?php endforeach;?></select></label><label>Help text<textarea name="help_text" rows="3" maxlength="1000"><?=$e((string)($editing['help_text']??''))?></textarea></label><label>Choice options <small>one per line; used by choice fields</small><textarea name="options_text" rows="5"><?=$e($editing&&$editing['options']?implode("\n",$editing['options']):'')?></textarea></label><div class="df-pair"><label>Field key<input name="field_key" maxlength="100" value="<?=$e((string)($editing['field_key']??''))?>" placeholder="auto-generated"></label><label>Order<input type="number" name="sort_order" value="<?=(int)($editing['sort_order']??0)?>"></label></div><label class="df-check"><input type="checkbox" name="required" value="1"<?=!empty($editing['required'])?' checked':''?>><span>Required field</span></label><button class="button" type="submit"><?=$editing?'Save field':'Add field'?></button></form></div><?php endif;?></div></main></div><script src="<?=$url('/assets/js/unified-navigation.js')?>"></script></body></html><?php exit;
    }

    private static function form(PDO $db,int $id):?array
    {
        if($id<1)return null;
        $s=$db->prepare('SELECT id,production_id,title,form_type,instructions,completion_mode,review_required,definition_version,active FROM forms WHERE id=:id LIMIT 1');
        $s->execute(['id'=>$id]);
        return $s->fetch()?:null;
    }

    private static function csrf():void
    {
        if(!hash_equals((string)($_SESSION['dynamic_forms_csrf']??''),(string)($_POST['csrf_token']??'')))throw new RuntimeException('Your session token expired. Please try again.');
    }

    private static function audit(PDO $db,int $actor,string $event,string $type,int $id,string $summary,array $meta):void
    {
        $s=$db->prepare('INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,:event,:type,:id,:summary,:meta)');
        $s->execute(['actor'=>$actor,'event'=>$event,'type'=>$type,'id'=>$id,'summary'=>$summary,'meta'=>json_encode($meta,JSON_THROW_ON_ERROR)]);
    }

    private static function flash(string $type,string $message):void{$_SESSION['dynamic_forms_flash']=['type'=>$type,'message'=>$message];}
    private static function takeFlash():?array{$f=$_SESSION['dynamic_forms_flash']??null;unset($_SESSION['dynamic_forms_flash']);return is_array($f)?$f:null;}
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
    private static function forbidden():never{http_response_code(403);exit('Forms management permission is required.');}
}
