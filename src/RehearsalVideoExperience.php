<?php

declare(strict_types=1);
require_once __DIR__.'/MemberUI.php';

require_once __DIR__.'/WorkspaceUI.php';

require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/AppNavigation.php';
require_once __DIR__.'/ProductionContext.php';
require_once __DIR__.'/RehearsalVideoService.php';

final class RehearsalVideoExperience
{
    public static function handles(string $route):bool{return in_array($route,['/videos','/videos/view','/videos/play','/videos/archive-download','/videos/inventory','/production/rehearsal-library','/production/rehearsal-video'],true);}
    public static function render(string $route,string $basePath):never
    {
        try{
            self::renderAuthorized($route,$basePath);
        }catch(Throwable $error){
            // Log detailed failure only on the server; never expose SQL or paths
            // in the student/guardian browser. A short reference makes it findable.
            $reference=bin2hex(random_bytes(5));
            error_log('[CTSMD Video Library '.$reference.'] '.$error::class.': '.$error->getMessage().' at '.$error->getFile().':'.$error->getLine().' route='.$route);
            if(!headers_sent()){
                http_response_code(500);
                header('Content-Type: text/html; charset=utf-8');
                header('Cache-Control: no-store');
            }
            $ref=htmlspecialchars($reference,ENT_QUOTES,'UTF-8');
            echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Video Library temporarily unavailable</title></head><body style="font:16px system-ui,sans-serif;margin:8vh auto;max-width:540px;padding:20px"><h1>Video Library could not load</h1><p>Your account has not lost access. A server error prevented the library from opening.</p><p>Please give CTSMD support this error reference: <strong>'.$ref.'</strong></p><p><a href="/my-theatre">Back to My Theatre</a></p></body></html>';
            exit;
        }
    }

    private static function renderAuthorized(string $route,string $basePath):never
    {
        Auth::startSession();$db=Database::connect(dirname(__DIR__));
        $user=Auth::currentUser($db);
        if(!$user){header('Location: '.($basePath?:'').'/login',true,303);exit;}
        $production=RehearsalVideoService::canManage($user)?ProductionContext::selected($db,$user):null;
        $_SESSION['rehearsal_video_csrf']??=bin2hex(random_bytes(24));
        if(in_array($route,['/videos/play','/production/rehearsal-video'],true))self::play($db,$user,$production);
        if($route==='/videos/view')self::view($db,$user,$basePath);
        if($route==='/videos/archive-download')self::archiveDownload($db,$user,$production);
        if($route==='/videos/inventory')self::inventoryDownload($db,$user);
        if($_SERVER['REQUEST_METHOD']==='POST')self::post($db,$user,$production,$basePath);
        self::page($db,$user,$production,$basePath);
    }

    /** Shared authorization for a published video page and its stream. */
    public static function canOpenVideo(PDO $db,array $user,array $video):bool
    {
        if(($video['status']??'')!=='active')return false;
        return RehearsalVideoService::canManage($user)
            || RehearsalVideoService::canView($db,$user,$video);
    }

    private static function view(PDO $db,array $user,string $basePath):never
    {
        $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;
        $video=RehearsalVideoService::record($db,(int)$id);
        if(!$video||!self::canOpenVideo($db,$user,$video)){
            http_response_code(404);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Video unavailable · CTSMD</title><main style="max-width:40rem;margin:5rem auto;font:16px system-ui;padding:1rem"><h1>Video unavailable</h1><p>This video is archived or your account does not have access to it.</p><a href="/videos">Back to Video Library</a></main></html>';
            exit;
        }
        $escape=static fn(mixed $s):string=>htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
        $url=static fn(string $s):string=>($basePath?:'').$s;
        $videoSrc=$url('/videos/play?id='.(int)$video['id']);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: private, no-store');
        header('X-Robots-Tag: noindex,nofollow');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=$escape($video['title'])?> · CTSMD Videos</title><link rel="stylesheet" href="<?=$url('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$url('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$url('/assets/css/rehearsal-library.css')?>"><?php WorkspaceUI::operationsStyles($basePath); ?><?php MemberUI::styles($basePath); ?></head><body class="app-body ux-operations ux-workspace<?=MemberUI::bodyClass()?>"><div class="unified-shell"><?php AppNavigation::renderSidebar('/videos',$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Your Theatre','Watch video',$basePath); MemberUI::wayfinding($basePath);?><div class="rl-page ops-page"><p><a href="<?=$url('/videos')?>">← All videos</a></p><section class="rl-card ops-panel rl-video-detail"><div class="rl-card-info"><small><?=$escape($video['audience_scope']==='platform'?'CTSMD members':($video['audience_scope']==='group'?'Production group':'Production members'))?></small><h1><?=$escape($video['title'])?></h1><?php if(!empty($video['description'])):?><p><?=nl2br($escape($video['description']))?></p><?php endif;?></div><div class="rl-card-actions"><video controls playsinline preload="metadata" src="<?=$escape($videoSrc)?>" aria-label="<?=$escape($video['title'])?>"></video><div class="rl-playback-tools" role="group" aria-label="Practice playback controls"><button type="button" data-video-rewind>↶ 10s</button><label>Speed <select data-video-speed><option value="0.5">0.5×</option><option value="0.75">0.75×</option><option value="1" selected>1×</option><option value="1.25">1.25×</option><option value="1.5">1.5×</option></select></label><button type="button" data-loop-start>Loop start</button><button type="button" data-loop-end>Loop end</button><button type="button" data-loop-clear hidden>Clear loop</button></div></div></section></div></main></div><script src="<?=$url('/assets/js/video-library-player.js')?>"></script></body></html><?php exit;
    }

    private static function play(PDO $db,array $user,?array $production):never
    {
        header('Cache-Control: private, no-store');
        header('X-Robots-Tag: noindex, nofollow');
        $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;
        $video=RehearsalVideoService::record($db,(int)$id);
        if(!$video){http_response_code(404);exit;}
        if(!self::canOpenVideo($db,$user,$video)){http_response_code(404);exit;}
        RehearsalVideoService::stream($video);
    }

    private static function archiveDownload(PDO $db,array $user,?array $production):never
    {
        if(!in_array((string)($_SERVER['REQUEST_METHOD']??'GET'),['GET','HEAD'],true)||!RehearsalVideoService::canManage($user)){
            http_response_code(403);exit('Production staff access required.');
        }
        $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;
        $video=RehearsalVideoService::record($db,(int)$id);
        if(!$video||($video['audience_scope']!=='platform'&&(!$production||(int)$production['id']!==(int)$video['production_id']))){
            http_response_code(404);exit('Video unavailable.');
        }
        // Archived originals are intentionally downloadable for backup before removal.
        header('X-Robots-Tag: noindex,nofollow');
        RehearsalVideoService::stream($video,true);
    }

    private static function inventoryDownload(PDO $db,array $user):never
    {
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'||!RehearsalVideoService::canManage($user)){
            http_response_code(403);exit('Production staff access required.');
        }
        $rows=RehearsalVideoService::inventory($db);
        if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="ctsmd-video-inventory-'.date('Ymd').'.csv"');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        $stream=fopen('php://output','wb');
        fputcsv($stream,['Video ID','Production ID','Production','Title','Category','Audience','Group','Status','Original filename','Storage key (relative to storage/private)','MIME','Bytes','Uploaded','Archived']);
        foreach($rows as $v){
            // Protect spreadsheet users from formulas embedded in user-entered titles.
            $safe=static function(mixed $val):string{
                $s=(string)($val??'');
                return preg_match('/^[\\s\\x00-\\x1f]*[=+@-]/u',$s)?"'".$s:$s;
            };
            fputcsv($stream,[
                $v['id'],$v['production_id'],$safe($v['production_title']),$safe($v['title']),$safe($v['category']),
                $safe($v['audience_scope']),$safe($v['group_name']),$safe($v['status']),$safe($v['original_filename']),
                $safe($v['storage_key']),$safe($v['mime_type']),$v['byte_size'],$v['created_at'],$v['archived_at']
            ]);
        }
        fclose($stream);exit;
    }

    private static function uploadAjax():bool
    {
        return (string)($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='XMLHttpRequest'
            && (string)($_POST['action']??'')==='upload';
    }

    private static function uploadResponse(string $type,string $message,string $basePath):never
    {
        if(self::uploadAjax()){
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: private, no-store');
            http_response_code($type==='success'?200:($type==='error'?422:400));
            echo json_encode(['ok'=>$type==='success','message'=>$message],JSON_THROW_ON_ERROR);
            exit;
        }
        self::flash($type,$message);
        self::redirect($basePath.'/videos');
    }

    private static function post(PDO $db,array $user,?array $production,string $basePath):never
    {
        if(!hash_equals((string)($_SESSION['rehearsal_video_csrf']??''),(string)($_POST['csrf_token']??''))){
            self::uploadResponse('error','Session expired. Reload and try again.',$basePath);
        }
        if(!RehearsalVideoService::canManage($user)){
            http_response_code(403);exit('Production staff access required.');
        }
        try{
            $action=(string)($_POST['action']??'');
            if($action==='upload'){
                RehearsalVideoService::upload($db,$user,$production,$_POST,$_FILES['video']??[]);
                self::uploadResponse('success','Video uploaded and published for the selected audience.',$basePath);
            }elseif($action==='archive'||$action==='restore'){
                RehearsalVideoService::archive($db,$user,(int)($_POST['video_production_id']??0),(int)($_POST['video_id']??0),$action==='restore');
                self::flash('success',$action==='archive'?'Video archived and hidden from students. The file remains on disk.':'Video restored to the library.');
            }elseif($action==='delete'){
                if((string)($_POST['confirm_delete']??'')!=='DELETE')throw new RuntimeException('Confirm permanent deletion.');
                RehearsalVideoService::delete($db,$user,(int)($_POST['video_production_id']??0),(int)($_POST['video_id']??0));
                self::flash('success','Video file and library record permanently deleted.');
            }else throw new RuntimeException('Unknown video action.');
        }catch(Throwable $e){
            self::uploadResponse('error',$e instanceof RuntimeException?$e->getMessage():'Video action failed. Check server upload settings or storage permissions.',$basePath);
        }
        self::redirect($basePath.'/videos');
    }

    private static function page(PDO $db,array $user,?array $production,string $basePath):never
    {
        $admin=RehearsalVideoService::canManage($user);$e=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
        $url=static fn(string $v):string=>($basePath?:'').$v;
        $videos=$admin?($production?RehearsalVideoService::visible($db,$user,(int)$production['id']):RehearsalVideoService::visible($db,$user,0)):RehearsalVideoService::visibleAcrossProductions($db,$user);
        $groups=$production&&$admin?RehearsalVideoService::groups($db,(int)$production['id']):[];
        $usage=$production?RehearsalVideoService::usage($db,(int)$production['id']):['n'=>0,'bytes'=>0];
        $globalUsage=$admin?RehearsalVideoService::platformUsage($db):['n'=>0,'bytes'=>0];
        $totalUsage=$admin?RehearsalVideoService::totalUsage($db):['n'=>0,'bytes'=>0];
        $hosting=$admin?RehearsalVideoService::hostingLimits():null;
        $usageRows=$admin?RehearsalVideoService::usageByProduction($db):[];
        $flash=$_SESSION['rehearsal_video_flash']??null;unset($_SESSION['rehearsal_video_flash']);
        header('Cache-Control: private, no-store');header('X-Robots-Tag: noindex,nofollow');header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Video Library · CTSMD Connect</title><link rel="stylesheet" href="<?=$url('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$url('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$url('/assets/css/rehearsal-library.css')?>"><?php WorkspaceUI::operationsStyles($basePath); ?><?php MemberUI::styles($basePath); ?></head><body class="app-body ux-operations ux-workspace<?=MemberUI::bodyClass()?>"><div class="unified-shell"><?php AppNavigation::renderSidebar('/videos',$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Production','Video Library',$basePath,AppNavigation::productionSubnav('/videos',$user)); MemberUI::wayfinding($basePath);?><div class="rl-page ops-page">
        <?php if($flash):?><div class="rl-flash <?=$e($flash['type'])?>" role="status"><?=$e($flash['message'])?></div><?php endif;?>
        <header class="rl-hero ops-heading"><div><small>CTSMD MEDIA</small><h1>Video Library</h1><p><?=$production?$e($production['title']):'Select a current production'?> · Videos for all CTSMD members and production-specific rehearsal resources.</p></div><?php if($admin):?><button class="button" type="button" id="rl-upload-toggle" aria-expanded="false" aria-controls="rl-upload-form">+ Add video</button><?php endif;?></header>
        <?php if(!$production&&!$admin&&!$videos):?><div class="rl-empty">No videos are currently available for this account.</div><?php else:?>
        <?php if($admin):?><div class="rl-usage"><span><b><?=count($videos)?> available entries</b></span><span><b><?=RehearsalVideoService::humanSize((int)$usage['bytes'])?></b> stored for selected production</span><span><b><?=RehearsalVideoService::humanSize((int)$globalUsage['bytes'])?></b> CTSMD-wide videos</span><span><b><?=RehearsalVideoService::humanSize((int)$totalUsage['bytes'])?></b> all videos stored</span><a class="rl-inventory-link" href="<?=$url('/videos/inventory')?>">Export inventory CSV</a><small>Archived videos still use storage. These totals cover the Video Library only.</small></div>
        <details class="rl-storage-breakdown"><summary>Video storage by production and archive status</summary><div class="rl-storage-table-wrap"><table><thead><tr><th>Production</th><th>Videos</th><th>Stored</th><th>Archived (still stored)</th></tr></thead><tbody><?php foreach($usageRows as $stat):?><tr><td><?=$e($stat['production_title'])?></td><td><?=(int)$stat['video_count']?></td><td><?=RehearsalVideoService::humanSize((int)$stat['stored_bytes'])?></td><td><?=(int)$stat['archived_count']?> · <?=RehearsalVideoService::humanSize((int)$stat['archived_bytes'])?></td></tr><?php endforeach;?></tbody></table></div></details>
        <section class="rl-upload ops-form" id="rl-upload-form" hidden><h2>Upload video</h2><p>Available to all production staff. Upload an MP4 (recommended), WebM or MOV. The current upload limits are shown below.</p>
        <?php if($hosting):?><div class="rl-hosting-limits" role="note">
          <strong>Current server upload allowance: <?=RehearsalVideoService::humanSize((int)$hosting['effective_bytes'])?> per video</strong>
          <small>Upload limit: <?=RehearsalVideoService::humanSize((int)$hosting['php_upload_bytes'])?> · Combined upload limit: <?=RehearsalVideoService::humanSize((int)$hosting['php_post_bytes'])?> · CTSMD limit: <?=RehearsalVideoService::humanSize((int)$hosting['app_bytes'])?>.</small>
          <small><?php if($hosting['volume_free_bytes']!==null):?>Available server storage: <?=RehearsalVideoService::humanSize((int)$hosting['volume_free_bytes'])?> free; this is <b>not</b> the total storage available to CTSMD.<?php else:?>Available storage could not be checked.<?php endif;?></small>
          <?php if($hosting['effective_bytes']<20*1048576):?><small class="rl-limit-warning">Large videos may exceed the current upload limit. Try a smaller file or contact CTSMD support.</small><?php endif;?>
        </div><?php endif;?>
        <form method="post" enctype="multipart/form-data" id="rl-video-upload" data-max-bytes="<?=(int)($hosting['effective_bytes']??0)?>"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['rehearsal_video_csrf'])?>"><input type="hidden" name="action" value="upload">
        <div class="rl-fields"><label>Title<input name="title" maxlength="190" required placeholder="Belle — Full choreography"></label><label>Category<select name="category"><?php foreach(RehearsalVideoService::CATEGORIES as $value=>$name):?><option value="<?=$e($value)?>"><?=$e($name)?></option><?php endforeach;?></select></label>
        <label>Who can watch?<select name="audience_scope" id="rl-audience-scope" required><option value="platform">Everyone on CTSMD (authenticated members)</option><?php if($production):?><option value="production" selected>Everyone in <?=$e($production['title'])?> (cast, guardians and staff)</option><option value="group">Specific Production Group</option><?php endif;?></select></label><label id="rl-group-field" hidden>Production Group<select name="group_id" id="rl-group-id" disabled><option value="">Choose a group</option><?php foreach($groups as $group):?><option value="<?=(int)$group['id']?>"><?=$e($group['name'])?></option><?php endforeach;?></select></label><label>Video file<input type="file" name="video" accept="video/mp4,video/webm,video/quicktime,.mp4,.webm,.mov" required></label></div>
        <label>Description / practice instructions<textarea name="description" maxlength="2000" rows="3" placeholder="Practice counts 1–32 before Thursday."></textarea></label><div class="rl-upload-status" id="rl-upload-status" hidden role="status" aria-live="polite" aria-atomic="true">
            <div class="rl-upload-status-head"><strong id="rl-upload-phase">Preparing upload…</strong><b id="rl-upload-percent">0%</b></div>
            <progress id="rl-upload-progress" max="100" value="0" aria-label="Video upload progress"></progress>
            <div class="rl-upload-detail"><span id="rl-upload-bytes">0 MB transferred</span><span id="rl-upload-speed"></span></div>
            <p id="rl-upload-message">Please keep this page open until the upload finishes.</p>
          </div>
          <div class="rl-upload-actions"><button class="button" type="submit" id="rl-upload-submit">Upload &amp; publish</button><button type="button" id="rl-upload-cancel" hidden>Cancel upload</button></div></form>
        </section><?php endif;?>
        <section class="rl-list"><div class="rl-section-head"><h2>Videos</h2><small><?=count($videos)?> shown</small></div>
        <?php if(!$videos):?><div class="rl-empty">No videos available for this audience yet.</div><?php endif;?>
        <?php foreach($videos as $video):?><article class="rl-card ops-panel"><div class="rl-card-info"><small><?=$e(RehearsalVideoService::CATEGORIES[$video['category']]??$video['category'])?> · <?=$e($video['audience_scope']==='platform'?'All CTSMD members':(($video['production_title']??'Production').' · '.($video['audience_scope']==='group'?($video['group_name']??'Production Group'):'Entire production')))?><?=$video['status']==='archived'?' · ARCHIVED':''?></small><h3><?=$e($video['title'])?></h3><?php if($video['description']):?><p><?=nl2br($e($video['description']))?></p><?php endif;?><span><?=RehearsalVideoService::humanSize((int)$video['byte_size'])?> · <?=$e(date('M j, Y',strtotime($video['created_at'])))?> · Uploaded by <?=$e($video['uploader']??'Production staff')?></span></div>
        <div class="rl-card-actions"><?php if($video['status']==='active'):?><div class="rl-share-actions"><a href="<?=$url('/videos/view?id='.(int)$video['id'])?>">Open video</a><?php if($admin):?><button type="button" data-copy-video-link data-video-id="<?=(int)$video['id']?>" data-video-title="<?=$e($video['title'])?>">Copy link for Community</button><?php endif;?></div><?php endif;?><?php if($video['status']==='active'):?><video controls playsinline preload="none" controlsList="nodownload" poster="" src="<?=$url('/videos/play?id='.(int)$video['id'])?>" aria-label="<?=$e($video['title'])?>"></video><div class="rl-playback-tools" role="group" aria-label="Practice playback controls"><button type="button" data-video-rewind aria-label="Rewind 10 seconds">↶ 10s</button><label>Speed <select data-video-speed aria-label="Playback speed"><option value="0.5">0.5×</option><option value="0.75">0.75×</option><option value="1" selected>1×</option><option value="1.25">1.25×</option><option value="1.5">1.5×</option></select></label><button type="button" data-loop-start>Loop start</button><button type="button" data-loop-end>Loop end</button><button type="button" data-loop-clear hidden>Clear loop</button></div><?php endif;?>
        <?php if($admin):?><div class="rl-manage"><a class="rl-download-original" href="<?=$url('/videos/archive-download?id='.(int)$video['id'])?>" aria-label="Download original video for archiving: <?=$e($video['title'])?>">Download original</a><?php if($video['status']==='active'):?><form method="post"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['rehearsal_video_csrf'])?>"><input type="hidden" name="action" value="archive"><input type="hidden" name="video_id" value="<?=(int)$video['id']?>"><input type="hidden" name="video_production_id" value="<?=(int)($video['production_id']??0)?>"><button type="submit">Archive</button></form><?php else:?><form method="post"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['rehearsal_video_csrf'])?>"><input type="hidden" name="action" value="restore"><input type="hidden" name="video_id" value="<?=(int)$video['id']?>"><input type="hidden" name="video_production_id" value="<?=(int)($video['production_id']??0)?>"><button type="submit">Restore</button></form><form method="post" onsubmit="return confirm('Permanently delete this archived video file? This cannot be undone.');"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['rehearsal_video_csrf'])?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="confirm_delete" value="DELETE"><input type="hidden" name="video_id" value="<?=(int)$video['id']?>"><input type="hidden" name="video_production_id" value="<?=(int)($video['production_id']??0)?>"><button type="submit" class="rl-danger">Delete from storage</button></form><?php endif;?></div><?php endif;?></div></article><?php endforeach;?></section>
        <?php endif;?></div></main></div><script src="<?=$url('/assets/js/unified-navigation.js')?>"></script><script>(function(){const b=document.getElementById('rl-upload-toggle'),p=document.getElementById('rl-upload-form');if(b&&p)b.addEventListener('click',()=>{p.hidden=!p.hidden;b.setAttribute('aria-expanded',String(!p.hidden));if(!p.hidden)p.querySelector('input[name=title]').focus();const scope=document.getElementById('rl-audience-scope'),group=document.getElementById('rl-group-field'),gid=document.getElementById('rl-group-id');if(scope&&group&&gid){const sync=()=>{group.hidden=scope.value!=='group';gid.disabled=scope.value!=='group';gid.required=scope.value==='group'};scope.addEventListener('change',sync);sync();}});})();</script><script src="<?=$url('/assets/js/video-library-player.js')?>"></script><script src="<?=$url('/assets/js/video-library-upload.js')?>"></script><script src="<?=$url('/assets/js/video-library-sharing.js')?>"></script></body></html><?php exit;
    }
    private static function flash(string $type,string $message):void{$_SESSION['rehearsal_video_flash']=['type'=>$type,'message'=>$message];}
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
}
