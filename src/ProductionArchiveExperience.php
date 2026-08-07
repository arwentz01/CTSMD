<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/ProductionArchiveService.php';
require_once __DIR__ . '/StorageService.php';

final class ProductionArchiveExperience
{
    private const ROUTES=['/archive','/archive/production','/archive/community','/archive/file'];

    public static function handles(string $route):bool{return in_array($route,self::ROUTES,true);}

    public static function render(string $route,string $basePath):never
    {
        Auth::startSession();$db=Database::connect(dirname(__DIR__));$user=Auth::currentUser($db);
        if(!$user){header('Location: '.($basePath?:'').'/login',true,303);exit;}

        if($route==='/archive/file'){
            $fileId=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;$resolved=ProductionArchiveService::archiveFile($db,$user,(int)$fileId);
            if(!$resolved){http_response_code(404);exit('Historical file not found or not available to this account.');}
            StorageService::stream(dirname(__DIR__),$resolved['version'],true);
        }

        if($route==='/archive/community'){
            $productionId=filter_input(INPUT_GET,'production',FILTER_VALIDATE_INT)?:0;$channelId=filter_input(INPUT_GET,'channel',FILTER_VALIDATE_INT)?:0;
            $channel=ProductionArchiveService::channelDetail($db,$user,(int)$productionId,(int)$channelId);
            if(!$channel)self::notFound($basePath,$user,'That historical Community channel is not available to this account.');
            self::communityPage($basePath,$user,$channel); 
        }

        if($route==='/archive/production'){
            $productionId=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;$detail=ProductionArchiveService::detail($db,$user,(int)$productionId);
            if(!$detail)self::notFound($basePath,$user,'That archived production is not available to this account.');
            self::productionPage($basePath,$user,$detail);
        }

        self::indexPage($basePath,$user,ProductionArchiveService::productionsForViewer($db,$user));
    }

    private static function indexPage(string $basePath,array $user,array $productions):never
    {
        $u=static fn(string $p):string=>($basePath?:'').$p;$e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');
        header('Content-Type:text/html; charset=utf-8');?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Theatre archive · CTSMD Connect</title><link rel="stylesheet" href="<?=$u('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/production-archive.css')?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/archive',$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Theatre','Archive',$basePath);?><div class="pa-page"><section class="pa-hero"><div><small>PAST PRODUCTIONS</small><h2>The curtain closes. The history stays.</h2><p>Archived productions leave active schedules and Community, but the production record remains available to the people who were part of it.</p></div><div class="pa-seal">READ ONLY</div></section><?php if(!$productions):?><section class="pa-empty"><h3>No archived productions yet.</h3><p>When a production you belong to is archived, its historical record will appear here.</p></section><?php else:?><section class="pa-grid"><?php foreach($productions as $p):?><a class="pa-production-card" href="<?=$u('/archive/production?id='.(int)$p['id'])?>"><small><?=$e(strtoupper((string)($p['season']?:'CTSMD')))?> · ARCHIVED</small><h3><?=$e((string)$p['title'])?></h3><p><?=(int)$p['student_count']?> cast member<?=((int)$p['student_count']===1)?'':'s'?> · <?=(int)$p['schedule_count']?> schedule item<?=((int)$p['schedule_count']===1)?'':'s'?></p><?php if($p['deactivated_at']):?><span>Closed <?=date('M j, Y',strtotime((string)$p['deactivated_at']))?></span><?php endif;?><b>Open history →</b></a><?php endforeach;?></section><?php endif;?></div></main></div><script src="<?=$u('/assets/js/unified-navigation.js')?>"></script></body></html><?php exit;
    }

    private static function productionPage(string $basePath,array $user,array $detail):never
    {
        $u=static fn(string $p):string=>($basePath?:'').$p;$e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');$p=$detail['production'];$cast=$detail['cast'];$students=array_values(array_filter($detail['roster'],static fn(array $r):bool=>$r['audience_type']==='student'));$staff=array_values(array_filter($detail['roster'],static fn(array $r):bool=>$r['audience_type']==='staff'));
        header('Content-Type:text/html; charset=utf-8');?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=$e((string)$p['title'])?> archive · CTSMD Connect</title><link rel="stylesheet" href="<?=$u('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/production-archive.css')?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/archive',$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Archive',(string)$p['title'],$basePath,[['label'=>'All productions','href'=>'/archive','active'=>false],['label'=>'Production history','href'=>'/archive/production?id='.(int)$p['id'],'active'=>true]]);?><div class="pa-page"><section class="pa-production-hero"><div><small><?=$e(strtoupper((string)($p['season']?:'CTSMD')))?> · ARCHIVED PRODUCTION</small><h2><?=$e((string)$p['title'])?></h2><p>This is a read-only historical record. It does not reactivate production access, schedules, or Community.</p></div><div><b><?=count($students)?></b><span>cast</span><b><?=count($detail['schedule'])?></b><span>calls/events</span></div></section>

        <?php if($cast&&$cast['cast']):?><section class="pa-panel"><header><div><small>FINAL RELEASE</small><h3><?=$e((string)($cast['headline']?:'Published cast'))?></h3></div><?php if($cast['published_at']):?><span>Published <?=date('M j, Y',strtotime((string)$cast['published_at']))?></span><?php endif;?></header><?php if($cast['member_note']):?><p class="pa-note"><?=$e((string)$cast['member_note'])?></p><?php endif;?><div class="pa-cast-grid"><?php foreach($cast['cast'] as $row):?><article><b><?=$e((string)($row['name']??''))?></b><span><?=$e((string)($row['role']??''))?></span><?php if(!empty($row['participation_track'])):?><small><?=$e((string)$row['participation_track'])?></small><?php endif;?></article><?php endforeach;?></div></section><?php endif;?>

        <div class="pa-two"><section class="pa-panel"><header><div><small>PRODUCTION COMPANY</small><h3>Cast & production staff</h3></div></header><?php if(!$students&&!$staff):?><p class="pa-muted">No preserved roster records.</p><?php endif;?><?php foreach($students as $row):?><div class="pa-person"><span><b><?=$e((string)$row['name'])?></b><small><?=$e((string)$row['participation_role'])?></small></span><em>Cast</em></div><?php endforeach;?><?php foreach($staff as $row):?><div class="pa-person"><span><b><?=$e((string)$row['name'])?></b><small><?=$e((string)$row['participation_role'])?></small></span><em>Staff</em></div><?php endforeach;?></section>
        <section class="pa-panel"><header><div><small>PLAYBILL</small><h3><?=$detail['playbill']?$e((string)($detail['playbill']['display_title']?:$p['title'])):'No preserved Playbill'?></h3></div></header><?php if($detail['playbill']):?><?php if($detail['playbill']['subtitle']):?><p class="pa-note"><?=$e((string)$detail['playbill']['subtitle'])?></p><?php endif;?><?php if($detail['playbill']['cover_note']):?><p><?=$e((string)$detail['playbill']['cover_note'])?></p><?php endif;?><?php foreach($detail['playbill']['sections'] as $section):?><details><summary><?=$e((string)$section['heading'])?></summary><div class="pa-rich"><?=nl2br($e((string)$section['body']))?></div></details><?php endforeach;?><?php else:?><p class="pa-muted">No Playbill record was preserved for this production.</p><?php endif;?></section></div>

        <section class="pa-panel"><header><div><small>RUN OF SHOW</small><h3>Historical schedule</h3></div><span><?=count($detail['schedule'])?> items</span></header><?php if(!$detail['schedule']):?><p class="pa-muted">No schedule history.</p><?php else:?><div class="pa-timeline"><?php foreach($detail['schedule'] as $item):?><article><time><?=date('M j, Y',strtotime((string)$item['starts_at']))?><b><?=date('g:i A',strtotime((string)$item['starts_at']))?></b></time><div><h4><?=$e((string)$item['title'])?></h4><p><?=$e((string)$item['location'])?> · <?=$e(ucwords(str_replace('_',' ',(string)$item['item_type'])))?></p></div></article><?php endforeach;?></div><?php endif;?></section>

        <div class="pa-two"><section class="pa-panel"><header><div><small>FILES</small><h3>Historical files</h3></div><span><?=count($detail['files'])?></span></header><?php if(!$detail['files']):?><p class="pa-muted">No historical files available to this account.</p><?php endif;?><?php foreach($detail['files'] as $file):?><a class="pa-resource" href="<?=$u('/archive/file?id='.(int)$file['id'])?>"><span><b><?=$e((string)$file['title'])?></b><small><?=$e((string)$file['category'])?> · <?=StorageService::humanSize((int)($file['byte_size']??0))?></small></span><em>Download</em></a><?php endforeach;?></section>
        <section class="pa-panel"><header><div><small>RESOURCES</small><h3>Links & notes</h3></div><span><?=count($detail['resources'])?></span></header><?php if(!$detail['resources']):?><p class="pa-muted">No historical resources available to this account.</p><?php endif;?><?php foreach($detail['resources'] as $resource):?><article class="pa-resource static"><span><b><?=$e((string)$resource['title'])?></b><small><?=$e((string)$resource['category'])?></small><?php if($resource['description']):?><p><?=$e((string)$resource['description'])?></p><?php endif;?><?php if($resource['resource_type']==='note'&&$resource['body']):?><p><?=$e((string)$resource['body'])?></p><?php endif;?></span><?php if($resource['resource_type']==='link'&&$resource['resource_url']):?><a href="<?=$e((string)$resource['resource_url'])?>" target="_blank" rel="noopener">Open ↗</a><?php endif;?></article><?php endforeach;?></section></div>

        <div class="pa-two"><section class="pa-panel"><header><div><small>COMMUNITY ARCHIVE</small><h3>Read-only channels</h3></div><span><?=count($detail['channels'])?></span></header><?php if(!$detail['channels']):?><p class="pa-muted">No historical channels are available to this account.</p><?php endif;?><?php foreach($detail['channels'] as $channel):?><a class="pa-channel" href="<?=$u('/archive/community?production='.(int)$p['id'].'&channel='.(int)$channel['id'])?>"><span><b># <?=$e((string)$channel['name'])?></b><small><?=(int)$channel['post_count']?> published post<?=((int)$channel['post_count']===1)?'':'s'?></small></span><em>Read →</em></a><?php endforeach;?></section>
        <section class="pa-panel"><header><div><small>PUBLISHED UPDATES</small><h3>Production notices</h3></div><span><?=count($detail['notices'])?></span></header><?php if(!$detail['notices']):?><p class="pa-muted">No published production updates.</p><?php endif;?><?php foreach($detail['notices'] as $notice):?><article class="pa-notice"><small><?=$notice['published_at']?date('M j, Y',strtotime((string)$notice['published_at'])):''?><?=$notice['schedule_title']?' · '.$e((string)$notice['schedule_title']):''?></small><h4><?=$e((string)$notice['subject'])?></h4><p><?=$e((string)$notice['body'])?></p></article><?php endforeach;?></section></div>
        </div></main></div><script src="<?=$u('/assets/js/unified-navigation.js')?>"></script></body></html><?php exit;
    }

    private static function communityPage(string $basePath,array $user,array $channel):never
    {
        $u=static fn(string $p):string=>($basePath?:'').$p;$e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');
        header('Content-Type:text/html; charset=utf-8');?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>#<?=$e((string)$channel['name'])?> archive · CTSMD Connect</title><link rel="stylesheet" href="<?=$u('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/production-archive.css')?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/archive',$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Community archive','#'.$channel['name'],$basePath,[['label'=>'Archive','href'=>'/archive','active'=>false],['label'=>$channel['production_title'],'href'=>'/archive/production?id='.(int)$channel['production_id'],'active'=>false],['label'=>'#'.$channel['name'],'href'=>'/archive/community?production='.(int)$channel['production_id'].'&channel='.(int)$channel['id'],'active'=>true]]);?><div class="pa-page"><section class="pa-channel-hero"><div><small><?=$e(strtoupper((string)$channel['production_title']))?> · READ ONLY</small><h2># <?=$e((string)$channel['name'])?></h2><p><?=$e((string)($channel['description']?:'Historical production Community channel.'))?></p></div><span>Posting disabled</span></section><section class="pa-community"><?php if(!$channel['posts']):?><div class="pa-empty"><h3>No published posts.</h3></div><?php endif;?><?php foreach($channel['posts'] as $post):?><article><div class="pa-avatar"><?=strtoupper(substr((string)$post['author_name'],0,1))?></div><div><header><b><?=$e((string)$post['author_name'])?></b><time><?=date('M j, Y · g:i A',strtotime((string)$post['created_at']))?></time></header><p><?=nl2br($e((string)$post['body']))?></p></div></article><?php endforeach;?></section><p class="pa-readonly-note">This archived channel is preserved as production history. New posts, replies, and reactions are disabled.</p></div></main></div><script src="<?=$u('/assets/js/unified-navigation.js')?>"></script></body></html><?php exit;
    }

    private static function notFound(string $basePath,array $user,string $message):never
    {
        $u=static fn(string $p):string=>($basePath?:'').$p;$e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');http_response_code(404);header('Content-Type:text/html; charset=utf-8');?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Archive unavailable · CTSMD Connect</title><link rel="stylesheet" href="<?=$u('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/production-archive.css')?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/archive',$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Theatre','Archive',$basePath);?><div class="pa-page"><section class="pa-empty"><h2>Archive unavailable</h2><p><?=$e($message)?></p><a class="button" href="<?=$u('/archive')?>">Back to archive</a></section></div></main></div></body></html><?php exit;
    }
}
