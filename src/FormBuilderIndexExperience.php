<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/AppNavigation.php';
require_once __DIR__.'/AccessPolicy.php';

final class FormBuilderIndexExperience
{
    public static function handles(string $route):bool{return $route==='/admin/forms/build';}
    public static function render(string $basePath):never
    {
        Auth::startSession();$db=Database::connect(dirname(__DIR__));$user=Auth::currentUser($db);if(!$user){header('Location: '.($basePath?:'').'/login',true,303);exit;}if(!AccessPolicy::canManageForms($user)){http_response_code(403);echo 'Restricted';exit;}
        $forms=$db->query("SELECT f.id,f.title,f.form_type,f.definition_version,f.active,p.title production_title,COUNT(ff.id) field_count FROM forms f LEFT JOIN productions p ON p.id=f.production_id LEFT JOIN form_fields ff ON ff.form_id=f.id AND ff.active=1 GROUP BY f.id,f.title,f.form_type,f.definition_version,f.active,p.title ORDER BY f.active DESC,p.title,f.title")->fetchAll();
        $url=static fn(string $p):string=>($basePath?:'').$p;$e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');header('Content-Type:text/html; charset=utf-8');?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Form builder · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/dynamic-forms.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/admin/forms/build',$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Forms','Form builder',$basePath);?><div class="df-page"><section class="df-hero"><div><small>STRUCTURED FORMS</small><h2>Choose a form to build.</h2><p>Add questions to any existing form. Forms without structured fields continue using the standard completion experience.</p></div><a href="<?= $url('/admin/forms/manage') ?>">Forms Operations →</a></section><div class="df-form-grid"><?php foreach($forms as $form):?><a href="<?= $url('/admin/forms/builder?id='.(int)$form['id']) ?>" class="df-form-card<?= !$form['active']?' inactive':'' ?>"><small><?= $e(strtoupper($form['production_title']?:'Organization')) ?></small><h3><?= $e($form['title']) ?></h3><p><?= $e(ucwords(str_replace('_',' ',$form['form_type']))) ?></p><footer><span><?= (int)$form['field_count'] ?> active fields</span><span>v<?= (int)$form['definition_version'] ?></span></footer></a><?php endforeach;?><?php if(!$forms):?><section class="df-empty"><h2>No forms yet.</h2><a class="button" href="<?= $url('/admin/forms/manage') ?>">Create a form</a></section><?php endif;?></div></div></main></div></body></html><?php exit;
    }
}
