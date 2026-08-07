<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/AppNavigation.php';
require_once __DIR__.'/StudentProfileService.php';
require_once __DIR__.'/StorageService.php';

final class StudentProfileExperience
{
    private const ROUTES=['/student-profile','/student-profile/headshot'];
    public static function handles(string $route):bool{return in_array($route,self::ROUTES,true);}

    public static function render(string $route,string $basePath):never
    {
        Auth::startSession();$db=Database::connect(dirname(__DIR__));$viewer=Auth::currentUser($db);if(!$viewer)self::redirect($basePath.'/login');
        $studentId=filter_input(INPUT_GET,'student',FILTER_VALIDATE_INT)?:filter_input(INPUT_POST,'student_id',FILTER_VALIDATE_INT)?:0;$subjects=StudentProfileService::subjectsForViewer($db,$viewer);if($studentId<1&&$subjects)$studentId=(int)$subjects[0]['id'];
        if($route==='/student-profile/headshot'){$version=StudentProfileService::headshot($db,$viewer,(int)$studentId);if(!$version){http_response_code(404);exit('Headshot unavailable.');}StorageService::stream(dirname(__DIR__),$version,false);}
        if($studentId<1||!StudentProfileService::canEdit($db,$viewer,(int)$studentId)){http_response_code(404);exit('Student profile unavailable.');}
        $_SESSION['student_profile_csrf']??=bin2hex(random_bytes(24));if($_SERVER['REQUEST_METHOD']==='POST')self::save($db,$basePath,$viewer,(int)$studentId);
        $profile=StudentProfileService::profile($db,(int)$studentId);self::page($basePath,$viewer,$subjects,$profile);
    }

    private static function save(PDO $db,string $basePath,array $viewer,int $studentId):never
    {
        if(!hash_equals((string)($_SESSION['student_profile_csrf']??''),(string)($_POST['csrf_token']??''))){self::flash('error','Your session token expired. Please try again.');self::redirect($basePath.'/student-profile?student='.$studentId);}try{StudentProfileService::save($db,dirname(__DIR__),$viewer,$studentId,$_POST,$_FILES['headshot']??[]);self::flash('success','Theatre profile updated.');}catch(RuntimeException $e){self::flash('error',$e->getMessage());}self::redirect($basePath.'/student-profile?student='.$studentId);
    }

    private static function page(string $basePath,array $viewer,array $subjects,array $profile):never
    {
        $u=static fn(string $p):string=>($basePath?:'').$p;$e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');$flash=self::takeFlash();$name=$profile['preferred_name']?:$profile['legal_name'];
        header('Content-Type:text/html; charset=utf-8');?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=$e((string)$name)?> · Theatre Profile</title><link rel="stylesheet" href="<?=$u('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/student-profile.css')?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/student-profile',$basePath,$viewer);?><main class="unified-main"><?php AppNavigation::renderHeader('Your CTSMD story','Theatre Profile',$basePath,[['label'=>'Profile','href'=>'/student-profile?student='.(int)$profile['id'],'active'=>true],['label'=>'Theatre History','href'=>'/theatre-history?student='.(int)$profile['id'],'active'=>false]]);?><div class="sp-page"><?php if($flash):?><div class="sp-flash <?=$e($flash['type'])?>"><?=$e($flash['message'])?></div><?php endif;?><?php if(count($subjects)>1):?><nav class="sp-subjects"><?php foreach($subjects as $s):?><a class="<?=((int)$s['id']===(int)$profile['id'])?'active':''?>" href="<?=$u('/student-profile?student='.(int)$s['id'])?>"><?=$e((string)$s['name'])?><small><?=$e((string)$s['relationship'])?></small></a><?php endforeach;?></nav><?php endif;?><section class="sp-hero"><div class="sp-photo"><?php if($profile['headshot_stored_file_id']):?><img src="<?=$u('/student-profile/headshot?student='.(int)$profile['id'])?>" alt="Headshot for <?=$e((string)$name)?>"><?php else:?><span><?=strtoupper(substr((string)$name,0,1))?></span><?php endif;?></div><div><small>STUDENT THEATRE PROFILE</small><h2><?=$e((string)$name)?></h2><p><?=$profile['short_bio']?$e((string)$profile['short_bio']):'Add a short theatre bio to help build future Playbills and résumés.'?></p></div></section><div class="sp-grid"><section class="sp-card"><small>PROFILE</small><h3>About the performer</h3><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['student_profile_csrf'])?>"><input type="hidden" name="student_id" value="<?=(int)$profile['id']?>"><label>Preferred / stage name<input name="preferred_name" maxlength="120" value="<?=$e((string)($profile['preferred_name']??''))?>" placeholder="Optional"></label><label>Short bio<textarea name="short_bio" rows="6" maxlength="1500" placeholder="A short theatre-appropriate biography."><?=$e((string)($profile['short_bio']??''))?></textarea></label><label>Special skills<textarea name="special_skills" rows="4" maxlength="1500" placeholder="Dance styles, instruments, dialects, gymnastics, stage combat training…"><?=$e((string)($profile['special_skills']??''))?></textarea></label><label>Headshot <small>JPG, PNG, or WebP</small><input type="file" name="headshot" accept="image/jpeg,image/png,image/webp"></label><button class="button" type="submit">Save theatre profile</button></form></section><aside class="sp-card"><small>CTSMD IDENTITY</small><h3><?=$e((string)$profile['legal_name'])?></h3><p>Preferred name, bio, skills, and headshot support theatre-facing experiences. The legal/account name remains preserved separately for administrative records.</p><a href="<?=$u('/theatre-history?student='.(int)$profile['id'])?>">View verified credits →</a></aside></div></div></main></div><script src="<?=$u('/assets/js/unified-navigation.js')?>"></script></body></html><?php exit;
    }

    private static function flash(string $type,string $message):void{$_SESSION['student_profile_flash']=['type'=>$type,'message'=>$message];}
    private static function takeFlash():?array{$f=$_SESSION['student_profile_flash']??null;unset($_SESSION['student_profile_flash']);return is_array($f)?$f:null;}
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
}
