<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/AppNavigation.php';
require_once __DIR__.'/ProductionContext.php';
require_once __DIR__.'/RehearsalVideoService.php';

final class RehearsalVideoExperience
{
    public static function handles(string $route):bool{return in_array($route,['/videos','/videos/play','/production/rehearsal-library','/production/rehearsal-video'],true);}
    public static function render(string $route,string $basePath):never
    {
        Auth::startSession();$db=Database::connect(dirname(__DIR__));
        $user=Auth::currentUser($db);
        if(!$user){header('Location: '.($basePath?:'').'/login',true,303);exit;}
        $production=ProductionContext::selected($db,$user);
        $_SESSION['rehearsal_video_csrf']??=bin2hex(random_bytes(24));
        if(in_array($route,['/videos/play','/production/rehearsal-video'],true))self::play($db,$user,$production);
        if($_SERVER['REQUEST_METHOD']==='POST')self::post($db,$user,$production,$basePath);
        self::page($db,$user,$production,$basePath);
    }

    private static function play(PDO $db,array $user,?array $production):never
    {
        header('Cache-Control: private, no-store');
        header('X-Robots-Tag: noindex, nofollow');
        $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;
        $video=RehearsalVideoService::record($db,(int)$id);
        if(!$video||($video['audience_scope']!=='platform'&&(!$production||(int)$production['id']!==(int)$video['production_id']))){http_response_code(404);exit;}
        if(!RehearsalVideoService::canView($db,$user,$video)&&!(RehearsalVideoService::canManage($user)&&$video['status']==='active')){http_response_code(404);exit;}
        RehearsalVideoService::stream($video);
    }

    private static function post(PDO $db,array $user,?array $production,string $basePath):never
    {
        if(!hash_equals((string)($_SESSION['rehearsal_video_csrf']??''),(string)($_POST['csrf_token']??''))){
            self::flash('error','Session expired. Reload and try again.');
            self::redirect($basePath.'/videos');
        }
        if(!RehearsalVideoService::canManage($user)){
            http_response_code(403);exit('Production staff access required.');
        }
        try{
            $action=(string)($_POST['action']??'');
            if($action==='upload'){
                RehearsalVideoService::upload($db,$user,$production,$_POST,$_FILES['video']??[]);
                self::flash('success','Video uploaded and published for the selected audience.');
            }elseif($action==='archive'||$action==='restore'){
                RehearsalVideoService::archive($db,$user,(int)($_POST['video_production_id']??0),(int)($_POST['video_id']??0),$action==='restore');
                self::flash('success',$action==='archive'?'Video archived and hidden from students. The file remains on disk.':'Video restored to the library.');
            }elseif($action==='delete'){
                if((string)($_POST['confirm_delete']??'')!=='DELETE')throw new RuntimeException('Confirm permanent deletion.');
                RehearsalVideoService::delete($db,$user,(int)($_POST['video_production_id']??0),(int)($_POST['video_id']??0));
                self::flash('success','Video file and library record permanently deleted.');
            }else throw new RuntimeException('Unknown video action.');
        }catch(Throwable $e){
            self::flash('error',$e instanceof RuntimeException?$e->getMessage():'Video action failed. Check server upload settings or storage permissions.');
        }
        self::redirect($basePath.'/videos');
    }

    private static function page(PDO $db,array $user,?array $production,string $basePath):never
    {
        $admin=RehearsalVideoService::canManage($user);$e=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
        $url=static fn(string $v):string=>($basePath?:'').$v;
        $videos=$production?RehearsalVideoService::visible($db,$user,(int)$production['id']):RehearsalVideoService::visible($db,$user,0);
        $groups=$production&&$admin?RehearsalVideoService::groups($db,(int)$production['id']):[];
        $usage=$production?RehearsalVideoService::usage($db,(int)$production['id']):['n'=>0,'bytes'=>0];
        $globalUsage=$admin?RehearsalVideoService::platformUsage($db):['n'=>0,'bytes'=>0];
        $flash=$_SESSION['rehearsal_video_flash']??null;unset($_SESSION['rehearsal_video_flash']);
        header('Cache-Control: private, no-store');header('X-Robots-Tag: noindex,nofollow');header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Video Library · CTSMD Connect</title><link rel="stylesheet" href="<?=$url('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$url('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$url('/assets/css/rehearsal-library.css')?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/videos',$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Production','Video Library',$basePath,AppNavigation::productionSubnav('/videos',$user));?><div class="rl-page">
        <?php if($flash):?><div class="rl-flash <?=$e($flash['type'])?>" role="status"><?=$e($flash['message'])?></div><?php endif;?>
        <header class="rl-hero"><div><small>CTSMD MEDIA</small><h1>Video Library</h1><p><?=$production?$e($production['title']):'Select a current production'?> · Videos for all CTSMD members and production-specific rehearsal resources.</p></div><?php if($admin):?><button class="button" type="button" id="rl-upload-toggle" aria-expanded="false" aria-controls="rl-upload-form">+ Add video</button><?php endif;?></header>
        <?php if(!$production&&!$admin&&!$videos):?><div class="rl-empty">No videos are currently available for this account.</div><?php else:?>
        <?php if($admin):?><div class="rl-usage"><span><b><?=count($videos)?> available entries</b></span><span><b><?=RehearsalVideoService::humanSize((int)$usage['bytes'])?></b> stored for selected production</span><span><b><?=RehearsalVideoService::humanSize((int)$globalUsage['bytes'])?></b> CTSMD-wide videos</span><small>Bluehost private storage: <code>storage/private/rehearsal-videos/<?=$production?(int)$production['id'].'-'.$e(preg_replace('/[^a-z0-9]+/i','-',strtolower($production['title']))):'ctsmd-platform'?>/</code>. Organization videos are in <code>ctsmd-platform/</code>. Archived videos still use disk space.</small></div>
        <section class="rl-upload" id="rl-upload-form" hidden><h2>Upload video</h2><p>Available to all production staff. Upload an MP4 (recommended), WebM or MOV. Bluehost PHP upload limits also apply.</p>
        <form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['rehearsal_video_csrf'])?>"><input type="hidden" name="action" value="upload">
        <div class="rl-fields"><label>Title<input name="title" maxlength="190" required placeholder="Belle — Full choreography"></label><label>Category<select name="category"><?php foreach(RehearsalVideoService::CATEGORIES as $value=>$name):?><option value="<?=$e($value)?>"><?=$e($name)?></option><?php endforeach;?></select></label>
        <label>Who can watch?<select name="audience_scope" id="rl-audience-scope" required><option value="platform">Everyone on CTSMD (authenticated members)</option><?php if($production):?><option value="production" selected>Everyone in <?=$e($production['title'])?> (cast, guardians and staff)</option><option value="group">Specific Production Group</option><?php endif;?></select></label><label id="rl-group-field" hidden>Production Group<select name="group_id" id="rl-group-id" disabled><option value="">Choose a group</option><?php foreach($groups as $group):?><option value="<?=(int)$group['id']?>"><?=$e($group['name'])?></option><?php endforeach;?></select></label><label>Video file<input type="file" name="video" accept="video/mp4,video/webm,video/quicktime,.mp4,.webm,.mov" required></label></div>
        <label>Description / practice instructions<textarea name="description" maxlength="2000" rows="3" placeholder="Practice counts 1–32 before Thursday."></textarea></label><button class="button" type="submit">Upload &amp; publish</button></form>
        </section><?php endif;?>
        <section class="rl-list"><div class="rl-section-head"><h2>Videos</h2><small><?=count($videos)?> shown</small></div>
        <?php if(!$videos):?><div class="rl-empty">No videos available for this audience yet.</div><?php endif;?>
        <?php foreach($videos as $video):?><article class="rl-card"><div class="rl-card-info"><small><?=$e(RehearsalVideoService::CATEGORIES[$video['category']]??$video['category'])?> · <?=$e($video['audience_scope']==='platform'?'All CTSMD members':($video['audience_scope']==='group'?($video['group_name']??'Production Group'):'Entire production'))?><?=$video['status']==='archived'?' · ARCHIVED':''?></small><h3><?=$e($video['title'])?></h3><?php if($video['description']):?><p><?=nl2br($e($video['description']))?></p><?php endif;?><span><?=RehearsalVideoService::humanSize((int)$video['byte_size'])?> · <?=$e(date('M j, Y',strtotime($video['created_at'])))?> · Uploaded by <?=$e($video['uploader']??'Production staff')?></span></div>
        <div class="rl-card-actions"><?php if($video['status']==='active'):?><video controls playsinline preload="none" controlsList="nodownload" poster="" src="<?=$url('/videos/play?id='.(int)$video['id'])?>" aria-label="<?=$e($video['title'])?>"></video><?php endif;?>
        <?php if($admin):?><div class="rl-manage"><?php if($video['status']==='active'):?><form method="post"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['rehearsal_video_csrf'])?>"><input type="hidden" name="action" value="archive"><input type="hidden" name="video_id" value="<?=(int)$video['id']?>"><input type="hidden" name="video_production_id" value="<?=(int)($video['production_id']??0)?>"><button type="submit">Archive</button></form><?php else:?><form method="post"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['rehearsal_video_csrf'])?>"><input type="hidden" name="action" value="restore"><input type="hidden" name="video_id" value="<?=(int)$video['id']?>"><input type="hidden" name="video_production_id" value="<?=(int)($video['production_id']??0)?>"><button type="submit">Restore</button></form><form method="post" onsubmit="return confirm('Permanently delete this archived video file from Bluehost? This cannot be undone.');"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['rehearsal_video_csrf'])?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="confirm_delete" value="DELETE"><input type="hidden" name="video_id" value="<?=(int)$video['id']?>"><input type="hidden" name="video_production_id" value="<?=(int)($video['production_id']??0)?>"><button type="submit" class="rl-danger">Delete from storage</button></form><?php endif;?></div><?php endif;?></div></article><?php endforeach;?></section>
        <?php endif;?></div></main></div><script src="<?=$url('/assets/js/unified-navigation.js')?>"></script><script>(function(){const b=document.getElementById('rl-upload-toggle'),p=document.getElementById('rl-upload-form');if(b&&p)b.addEventListener('click',()=>{p.hidden=!p.hidden;b.setAttribute('aria-expanded',String(!p.hidden));if(!p.hidden)p.querySelector('input[name=title]').focus();const scope=document.getElementById('rl-audience-scope'),group=document.getElementById('rl-group-field'),gid=document.getElementById('rl-group-id');if(scope&&group&&gid){const sync=()=>{group.hidden=scope.value!=='group';gid.disabled=scope.value!=='group';gid.required=scope.value==='group'};scope.addEventListener('change',sync);sync();}});})();</script></body></html><?php exit;
    }
    private static function flash(string $type,string $message):void{$_SESSION['rehearsal_video_flash']=['type'=>$type,'message'=>$message];}
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
}
