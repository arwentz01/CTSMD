<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/ProductionContext.php';
require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/StorageService.php';

final class AccountLibraryExperience
{
    private const ROUTES=['/files','/files/view','/resources','/resources/view'];
    public static function handles(string $route):bool{return in_array($route,self::ROUTES,true);}

    public static function render(string $route,string $basePath):never
    {
        Auth::startSession();$db=Database::connect(dirname(__DIR__));$user=Auth::currentUser($db);if(!$user)self::redirect($basePath.'/login');
        $productions=ProductionContext::activeProductions($db,$user);$allowed=array_map(static fn(array $p):int=>(int)$p['id'],$productions);$filter=filter_input(INPUT_GET,'production',FILTER_VALIDATE_INT)?:0;if($filter&&!in_array((int)$filter,$allowed,true))$filter=0;
        $fileMode=str_starts_with($route,'/files');$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;$item=null;
        if($id&&str_ends_with($route,'/view'))$item=$fileMode?self::file($db,$user,(int)$id,$allowed):self::resource($db,$user,(int)$id,$allowed);
        $items=$fileMode?self::files($db,$user,$allowed,(int)$filter):self::resources($db,$user,$allowed,(int)$filter);self::page($route,$basePath,$user,$productions,(int)$filter,$items,$item,$fileMode);
    }

    private static function files(PDO $db,array $user,array $ids,int $filter):array
    {
        if(!$ids)return [];$use=$filter?[$filter]:$ids;$ph=implode(',',array_fill(0,count($use),'?'));$stmt=$db->prepare("SELECT pf.*,p.title production_title,p.season,p.is_active production_active,v.id version_id,v.version_number,v.original_name,v.mime_type,v.byte_size,v.created_at version_created_at FROM production_files pf JOIN productions p ON p.id=pf.production_id LEFT JOIN stored_file_versions v ON v.id=(SELECT v2.id FROM stored_file_versions v2 WHERE v2.stored_file_id=pf.stored_file_id ORDER BY v2.version_number DESC LIMIT 1) WHERE pf.production_id IN ($ph) AND pf.status='active' AND p.is_active=1 ORDER BY pf.pinned DESC,p.title,pf.category,pf.title");$stmt->execute($use);return array_values(array_filter($stmt->fetchAll(),fn(array $r):bool=>self::canRead($db,$user,$r)));
    }

    private static function resources(PDO $db,array $user,array $ids,int $filter):array
    {
        if(!$ids)return [];$use=$filter?[$filter]:$ids;$ph=implode(',',array_fill(0,count($use),'?'));$stmt=$db->prepare("SELECT pr.*,p.title production_title,p.season,p.is_active production_active FROM production_resources pr JOIN productions p ON p.id=pr.production_id WHERE pr.production_id IN ($ph) AND pr.status='active' AND p.is_active=1 ORDER BY pr.pinned DESC,p.title,pr.category,pr.title");$stmt->execute($use);return array_values(array_filter($stmt->fetchAll(),fn(array $r):bool=>self::canRead($db,$user,$r)));
    }

    private static function file(PDO $db,array $user,int $id,array $allowed):?array
    {
        if(!$allowed)return null;$s=$db->prepare("SELECT pf.*,p.title production_title,p.season,p.is_active production_active,v.id version_id,v.version_number,v.original_name,v.mime_type,v.byte_size,v.sha256,v.created_at version_created_at FROM production_files pf JOIN productions p ON p.id=pf.production_id LEFT JOIN stored_file_versions v ON v.id=(SELECT v2.id FROM stored_file_versions v2 WHERE v2.stored_file_id=pf.stored_file_id ORDER BY v2.version_number DESC LIMIT 1) WHERE pf.id=:id AND pf.status='active' LIMIT 1");$s->execute(['id'=>$id]);$r=$s->fetch();if(!$r||!in_array((int)$r['production_id'],$allowed,true)||!self::canRead($db,$user,$r))return null;$r['versions']=StorageService::versions($db,(int)$r['stored_file_id']);return $r;
    }

    private static function resource(PDO $db,array $user,int $id,array $allowed):?array
    {
        if(!$allowed)return null;$s=$db->prepare("SELECT pr.*,p.title production_title,p.season,p.is_active production_active FROM production_resources pr JOIN productions p ON p.id=pr.production_id WHERE pr.id=:id AND pr.status='active' LIMIT 1");$s->execute(['id'=>$id]);$r=$s->fetch();if(!$r||!in_array((int)$r['production_id'],$allowed,true)||!self::canRead($db,$user,$r))return null;return $r;
    }

    private static function canRead(PDO $db,array $user,array $item):bool
    {
        if(!(bool)($item['production_active']??false))return false;$aud=json_decode((string)$item['audiences_json'],true);if(!is_array($aud)||!$aud)return false;$s=$db->prepare("SELECT audience_type FROM production_memberships WHERE production_id=:production AND user_id=:user AND status='active'");$s->execute(['production'=>(int)$item['production_id'],'user'=>(int)$user['id']]);$types=array_values(array_unique($s->fetchAll(PDO::FETCH_COLUMN)));if(!$types)return false;if(in_array('production_all',$aud,true))return true;if(in_array('production_students',$aud,true)&&in_array('student',$types,true))return true;if(in_array('production_guardians',$aud,true)&&in_array('guardian',$types,true))return true;if(in_array('production_staff',$aud,true)&&in_array('staff',$types,true))return true;if(in_array('production_adults',$aud,true)&&!AccessPolicy::isStudent($user))return true;return false;
    }

    private static function page(string $route,string $basePath,array $user,array $productions,int $filter,array $items,?array $item,bool $files):never
    {
        $u=static fn(string $p):string=>($basePath?:'').$p;$e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');$detail=str_ends_with($route,'/view');$title=$detail?($item['title']??($files?'File':'Resource')):($files?'Files':'Resources');$sub=[['label'=>'Resources','href'=>'/resources','active'=>!$files],['label'=>'Files','href'=>'/files','active'=>$files]];header('Content-Type:text/html; charset=utf-8');?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=$e($title)?> · CTSMD Connect</title><link rel="stylesheet" href="<?=$u('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/account-library.css')?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar($route,$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Your theatre',$title,$basePath,$sub);?><div class="account-library">
        <?php if($detail):?><?php if(!$item):?><section class="library-empty"><h2><?=$files?'File':'Resource'?> unavailable.</h2><p>It may belong to an inactive production or an audience your account is not currently part of.</p><a href="<?=$u($files?'/files':'/resources')?>">Back to library</a></section><?php else:?><section class="library-detail"><a class="library-back" href="<?=$u($files?'/files':'/resources')?>">← All <?=$files?'files':'resources'?></a><small><?=$e((string)$item['production_title'])?> · <?=$e((string)$item['category'])?></small><h2><?=$e((string)$item['title'])?></h2><?php if($item['description']):?><p><?=$e((string)$item['description'])?></p><?php endif;?><?php if($files):?><div class="library-file-meta"><b><?=$e((string)$item['original_name'])?></b><span>Version <?=(int)$item['version_number']?> · <?=StorageService::humanBytes((int)$item['byte_size'])?></span></div><a class="button" href="<?=$u('/files/download?id='.(int)$item['id'])?>">Download current version</a><?php if(!empty($item['versions'])):?><h3>Version history</h3><div class="library-versions"><?php foreach($item['versions'] as $v):?><a href="<?=$u('/files/download?id='.(int)$item['id'].'&version='.(int)$v['id'])?>"><b>Version <?=(int)$v['version_number']?></b><span><?=$e((string)$v['original_name'])?> · <?=StorageService::humanBytes((int)$v['byte_size'])?></span></a><?php endforeach;?></div><?php endif;?><?php else:?><?php if(($item['resource_type']??'')==='link'):?><a class="button" href="<?=$e((string)$item['resource_url'])?>" target="_blank" rel="noopener noreferrer">Open resource ↗</a><?php else:?><div class="library-note"><?=nl2br($e((string)$item['body']))?></div><?php endif;?><?php endif;?></section><?php endif;?>
        <?php else:?><section class="library-hero"><div><small>ACCOUNT-WIDE LIBRARY</small><h2><?=$files?'Files from all your active productions.':'Resources from all your active productions.'?></h2><p>Production labels and filters organize what you can access; you never need to switch shows to discover it.</p></div><?php if(AccessPolicy::canManageResources($user)):?><a href="<?=$u($files?'/admin/files':'/admin/resources')?>">Manage working production →</a><?php endif;?></section><?php if(count($productions)>1):?><nav class="library-filters"><a class="<?=$filter===0?'active':''?>" href="<?=$u($files?'/files':'/resources')?>">All</a><?php foreach($productions as $p):?><a class="<?=$filter===(int)$p['id']?'active':''?>" href="<?=$u(($files?'/files':'/resources').'?production='.(int)$p['id'])?>"><?=$e((string)$p['title'])?></a><?php endforeach;?></nav><?php endif;?><div class="library-grid"><?php if(!$items):?><section class="library-empty"><b>No <?=$files?'files':'resources'?> are available to your account right now.</b><p>Items appear automatically when an active production publishes something for your audience.</p></section><?php endif;?><?php foreach($items as $r):?><a class="library-card" href="<?=$u(($files?'/files/view':'/resources/view').'?id='.(int)$r['id'])?>"><small><?=$e((string)$r['production_title'])?> · <?=$e((string)$r['category'])?></small><h3><?=$e((string)$r['title'])?></h3><p><?=$e((string)($r['description']?:($files?$r['original_name']:'Open resource')))?></p><?php if($files):?><span>Version <?=(int)$r['version_number']?> · <?=StorageService::humanBytes((int)$r['byte_size'])?></span><?php else:?><span><?=ucfirst((string)$r['resource_type'])?></span><?php endif;?></a><?php endforeach;?></div><?php endif;?></div></main></div><script src="<?=$u('/assets/js/unified-navigation.js')?>"></script></body></html><?php exit;
    }
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
}
