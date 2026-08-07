<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/AccessPolicy.php';
require_once __DIR__.'/AppNavigation.php';
require_once __DIR__.'/StorageService.php';

final class OrganizationResourceExperience
{
    private const ROUTES=['/admin/member-resources','/admin/member-resources/edit','/member-resources/download'];
    public static function handles(string $route):bool{return in_array($route,self::ROUTES,true);}

    public static function render(string $route,string $basePath):never
    {
        Auth::startSession();$db=Database::connect(dirname(__DIR__));$user=Auth::currentUser($db);if(!$user)self::redirect($basePath.'/login');
        if($route==='/member-resources/download')self::download($db,$user);
        if(!AccessPolicy::canManageResources($user))self::forbidden();
        $_SESSION['org_resource_csrf']??=bin2hex(random_bytes(24));
        if($_SERVER['REQUEST_METHOD']==='POST')self::handlePost($db,$user,$basePath);
        $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;$resource=$id?self::resource($db,(int)$id):null;$resources=self::resources($db);self::page($basePath,$user,$resource,$resources);
    }

    private static function handlePost(PDO $db,array $actor,string $basePath):never
    {
        if(!hash_equals((string)($_SESSION['org_resource_csrf']??''),(string)($_POST['csrf_token']??''))){self::flash('error','Your session expired.');self::redirect($basePath.'/admin/member-resources');}
        $action=(string)($_POST['action']??'');
        try{
            if($action==='save'){$id=self::save($db,$actor,$_POST,$_FILES['file_upload']??[]);self::flash('success','Organization member resource saved.');self::redirect($basePath.'/admin/member-resources/edit?id='.$id);}
            if($action==='toggle_archive'){self::toggleArchive($db,$actor,(int)($_POST['resource_id']??0));self::flash('success','Resource availability updated.');self::redirect($basePath.'/admin/member-resources');}
            throw new RuntimeException('Choose a valid resource action.');
        }catch(RuntimeException $e){self::flash('error',$e->getMessage());$id=(int)($_POST['resource_id']??0);self::redirect($basePath.($id?'/admin/member-resources/edit?id='.$id:'/admin/member-resources'));}
    }

    private static function save(PDO $db,array $actor,array $input,array $upload):int
    {
        $id=(int)($input['resource_id']??0);$title=trim((string)($input['title']??''));$category=trim((string)($input['category']??''));$description=trim((string)($input['description']??''));$type=(string)($input['resource_type']??'link');$url=trim((string)($input['resource_url']??''));$body=trim((string)($input['body']??''));$pinned=isset($input['pinned'])?1:0;
        if($title===''||mb_strlen($title)>190)throw new RuntimeException('Enter a title no longer than 190 characters.');if($category===''||mb_strlen($category)>100)throw new RuntimeException('Enter a category no longer than 100 characters.');if(mb_strlen($description)>500)throw new RuntimeException('Keep the description under 500 characters.');if(!in_array($type,['link','note','file'],true))throw new RuntimeException('Choose a valid resource type.');
        if($type==='link'){if($url===''||mb_strlen($url)>1000||!filter_var($url,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),['http','https'],true))throw new RuntimeException('Enter a valid http or https resource URL.');$body='';}
        elseif($type==='note'){if($body===''||mb_strlen($body)>10000)throw new RuntimeException('Enter resource notes up to 10,000 characters.');$url='';}
        else{$url='';$body='';}
        $stored=null;$db->beginTransaction();
        try{
            $before=null;
            if($id){$s=$db->prepare('SELECT * FROM organization_resources WHERE id=:id FOR UPDATE');$s->execute(['id'=>$id]);$before=$s->fetch();if(!$before)throw new RuntimeException('That organization resource no longer exists.');$storedFileId=$before['stored_file_id']?(int)$before['stored_file_id']:null;if($type==='file'){$has=(int)($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE;if(!$storedFileId&&!$has)throw new RuntimeException('Choose a file to upload.');if($has)$stored=StorageService::store($db,dirname(__DIR__),(int)$actor['id'],$upload,$storedFileId);}else{$storedFileId=null;}
                $db->prepare('UPDATE organization_resources SET stored_file_id=:stored,title=:title,category=:category,description=:description,resource_type=:type,resource_url=:url,body=:body,pinned=:pinned WHERE id=:id')->execute(['stored'=>$stored?$stored['stored_file_id']:$storedFileId,'title'=>$title,'category'=>$category,'description'=>$description?:null,'type'=>$type,'url'=>$url?:null,'body'=>$body?:null,'pinned'=>$pinned,'id'=>$id]);$event=$stored?'organization_resource.version_uploaded':'organization_resource.updated';
            }else{if($type==='file')$stored=StorageService::store($db,dirname(__DIR__),(int)$actor['id'],$upload,null);$db->prepare("INSERT INTO organization_resources (stored_file_id,created_by_user_id,title,category,description,resource_type,resource_url,body,pinned,status) VALUES (:stored,:creator,:title,:category,:description,:type,:url,:body,:pinned,'active')")->execute(['stored'=>$stored['stored_file_id']??null,'creator'=>(int)$actor['id'],'title'=>$title,'category'=>$category,'description'=>$description?:null,'type'=>$type,'url'=>$url?:null,'body'=>$body?:null,'pinned'=>$pinned]);$id=(int)$db->lastInsertId();$event='organization_resource.created';}
            self::audit($db,(int)$actor['id'],$event,$id,$event==='organization_resource.created'?'Created organization-wide member resource.':'Updated organization-wide member resource.',['resource_type'=>$type,'category'=>$category,'pinned'=>(bool)$pinned,'version_number'=>$stored['version_number']??null]);$db->commit();return $id;
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($stored)StorageService::deletePhysical(dirname(__DIR__),$stored);if($e instanceof RuntimeException)throw $e;throw new RuntimeException('The organization resource could not be saved.');}
    }

    private static function toggleArchive(PDO $db,array $actor,int $id):void
    {
        if($id<1)throw new RuntimeException('That resource could not be found.');$s=$db->prepare('SELECT id,title,status FROM organization_resources WHERE id=:id LIMIT 1');$s->execute(['id'=>$id]);$r=$s->fetch();if(!$r)throw new RuntimeException('That resource no longer exists.');$next=$r['status']==='active'?'archived':'active';$db->prepare('UPDATE organization_resources SET status=:status WHERE id=:id')->execute(['status'=>$next,'id'=>$id]);self::audit($db,(int)$actor['id'],$next==='active'?'organization_resource.restored':'organization_resource.archived',$id,$next==='active'?'Restored organization member resource.':'Archived organization member resource.',['title'=>$r['title']]);
    }

    private static function download(PDO $db,array $user):never
    {
        if(!Auth::isApprovedMember($user)&&!AccessPolicy::canManageResources($user)){http_response_code(404);exit('File not found');}$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;$versionId=filter_input(INPUT_GET,'version',FILTER_VALIDATE_INT)?:null;$r=self::resource($db,(int)$id);if(!$r||$r['resource_type']!=='file'||($r['status']!=='active'&&!AccessPolicy::canManageResources($user))||!$r['stored_file_id']){http_response_code(404);exit('File not found');}$version=StorageService::version($db,(int)$r['stored_file_id'],$versionId?(int)$versionId:null);if(!$version){http_response_code(404);exit('File not found');}self::audit($db,(int)$user['id'],'organization_resource.downloaded',(int)$r['id'],'Downloaded organization member resource.',['version_id'=>(int)$version['id'],'version_number'=>(int)$version['version_number']]);StorageService::stream(dirname(__DIR__),$version,true);
    }

    public static function active(PDO $db):array
    {
        return $db->query("SELECT r.*,CONCAT(u.first_name,' ',u.last_name) creator,v.id version_id,v.version_number,v.original_name,v.mime_type,v.byte_size,v.created_at version_created_at FROM organization_resources r LEFT JOIN users u ON u.id=r.created_by_user_id LEFT JOIN stored_file_versions v ON v.id=(SELECT v2.id FROM stored_file_versions v2 WHERE v2.stored_file_id=r.stored_file_id ORDER BY v2.version_number DESC LIMIT 1) WHERE r.status='active' ORDER BY r.pinned DESC,r.category,r.title")->fetchAll();
    }
    private static function resources(PDO $db):array{return $db->query("SELECT r.*,CONCAT(u.first_name,' ',u.last_name) creator,v.version_number,v.original_name,v.byte_size FROM organization_resources r LEFT JOIN users u ON u.id=r.created_by_user_id LEFT JOIN stored_file_versions v ON v.id=(SELECT v2.id FROM stored_file_versions v2 WHERE v2.stored_file_id=r.stored_file_id ORDER BY v2.version_number DESC LIMIT 1) ORDER BY r.status='active' DESC,r.pinned DESC,r.category,r.title")->fetchAll();}
    public static function resource(PDO $db,int $id):?array{if($id<1)return null;$s=$db->prepare("SELECT r.*,CONCAT(u.first_name,' ',u.last_name) creator,v.id version_id,v.version_number,v.original_name,v.mime_type,v.byte_size,v.sha256,v.created_at version_created_at FROM organization_resources r LEFT JOIN users u ON u.id=r.created_by_user_id LEFT JOIN stored_file_versions v ON v.id=(SELECT v2.id FROM stored_file_versions v2 WHERE v2.stored_file_id=r.stored_file_id ORDER BY v2.version_number DESC LIMIT 1) WHERE r.id=:id LIMIT 1");$s->execute(['id'=>$id]);$r=$s->fetch();if($r&&$r['stored_file_id'])$r['versions']=StorageService::versions($db,(int)$r['stored_file_id']);return $r?:null;}

    private static function page(string $basePath,array $user,?array $resource,array $resources):never
    {
        $u=static fn(string $p):string=>($basePath?:'').$p;$e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');$flash=$_SESSION['org_resource_flash']??null;unset($_SESSION['org_resource_flash']);$editing=$resource!==null||isset($_GET['id']);$r=$resource?:['id'=>0,'title'=>'','category'=>'','description'=>'','resource_type'=>'link','resource_url'=>'','body'=>'','pinned'=>0,'status'=>'active','stored_file_id'=>null,'original_name'=>null,'version_number'=>null];$title=$editing?($r['id']?$r['title']:'New member resource'):'Member Resource Operations';header('Content-Type:text/html; charset=utf-8');?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=$e((string)$title)?> · CTSMD Connect</title><link rel="stylesheet" href="<?=$u('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/organization-resources.css')?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/admin/member-resources',$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Operations',$title,$basePath);?><div class="orgres-page"><?php if($flash):?><div class="orgres-flash <?=$e($flash['type'])?>"><?=$e($flash['message'])?></div><?php endif;?><?php if(!$editing):?><section class="orgres-hero"><div><small>APPROVED MEMBER LIBRARY</small><h2>Resources that belong to CTSMD, not a single show.</h2><p>Publish handbooks, policies, facility information, general volunteer guidance, links and downloadable files for approved CTSMD members.</p></div><a href="<?=$u('/admin/member-resources/edit')?>">+ New resource</a></section><div class="orgres-list"><?php if(!$resources):?><div class="orgres-empty">No organization-wide member resources yet.</div><?php endif;?><?php foreach($resources as $item):?><article><div><small><?=$e(strtoupper($item['status'].' · '.$item['resource_type'].' · '.$item['category']))?></small><h3><?=$e($item['title'])?></h3><p><?=$e((string)($item['description']?:($item['original_name']?:'Approved member resource')))?></p></div><div><a href="<?=$u('/admin/member-resources/edit?id='.(int)$item['id'])?>">Edit</a></div></article><?php endforeach;?></div><?php else:?><section class="orgres-head"><div><small><?=$r['id']?'EDIT MEMBER RESOURCE':'NEW MEMBER RESOURCE'?></small><h2><?=$e((string)$title)?></h2></div><a href="<?=$u('/admin/member-resources')?>">← All member resources</a></section><form class="orgres-form" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['org_resource_csrf'])?>"><input type="hidden" name="resource_id" value="<?=(int)$r['id']?>"><input type="hidden" name="action" value="save"><div class="pair"><label>Title<input name="title" maxlength="190" value="<?=$e((string)$r['title'])?>" required></label><label>Category<input name="category" maxlength="100" value="<?=$e((string)$r['category'])?>" placeholder="Policies, Facility, Volunteers…" required></label></div><label>Description<textarea name="description" maxlength="500" rows="3"><?=$e((string)$r['description'])?></textarea></label><label>Type<select name="resource_type"><option value="link"<?=$r['resource_type']==='link'?' selected':''?>>Link</option><option value="note"<?=$r['resource_type']==='note'?' selected':''?>>Text / note</option><option value="file"<?=$r['resource_type']==='file'?' selected':''?>>Downloadable file</option></select></label><label>Link URL<input type="url" name="resource_url" value="<?=$e((string)$r['resource_url'])?>" placeholder="https://..."></label><label>Text / note<textarea name="body" rows="10"><?=$e((string)$r['body'])?></textarea></label><label>File<input type="file" name="file_upload"><small><?php if($r['resource_type']==='file'&&$r['original_name']):?>Current: <?=$e((string)$r['original_name'])?> · version <?=(int)$r['version_number']?>. Uploading another file creates a new immutable version.<?php else:?>PDF, Office, text/CSV, JPG, PNG or WebP.<?php endif;?></small></label><label class="check"><input type="checkbox" name="pinned" value="1"<?=$r['pinned']?' checked':''?>> Pin this resource near the top</label><button type="submit">Save member resource</button></form><?php if($r['id']):?><form class="orgres-archive" method="post"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['org_resource_csrf'])?>"><input type="hidden" name="resource_id" value="<?=(int)$r['id']?>"><button name="action" value="toggle_archive" type="submit"><?=$r['status']==='active'?'Archive resource':'Restore resource'?></button></form><?php endif;?><?php endif;?></div></main></div><script src="<?=$u('/assets/js/unified-navigation.js')?>"></script></body></html><?php exit;
    }

    private static function audit(PDO $db,int $actor,string $event,int $id,string $summary,array $meta):void{$s=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,:event,'organization_resource',:id,:summary,:meta)");$s->execute(['actor'=>$actor,'event'=>$event,'id'=>$id,'summary'=>$summary,'meta'=>$meta?json_encode($meta,JSON_THROW_ON_ERROR):null]);}
    private static function flash(string $type,string $message):void{$_SESSION['org_resource_flash']=['type'=>$type,'message'=>$message];}
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
    private static function forbidden():never{http_response_code(403);exit('Restricted');}
}
