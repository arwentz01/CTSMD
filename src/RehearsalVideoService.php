<?php

declare(strict_types=1);

require_once __DIR__.'/AccessPolicy.php';
require_once __DIR__.'/ProductionContext.php';
require_once __DIR__.'/ProductionLifecycleGuard.php';

final class RehearsalVideoService
{
    public const CATEGORIES=['choreography'=>'Choreography','blocking'=>'Blocking','vocals'=>'Vocal practice','music'=>'Music','other'=>'Other'];
    private const MIMES=['video/mp4'=>'mp4','video/webm'=>'webm','video/quicktime'=>'mov'];

    public static function canManage(array $user):bool
    {
        return AccessPolicy::canManageProduction($user);
    }

    public static function groups(PDO $db,int $productionId):array
    {
        $s=$db->prepare("SELECT id,name,group_type FROM production_groups WHERE production_id=:production AND active=1 ORDER BY sort_order,name");
        $s->execute(['production'=>$productionId]);return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function listings(PDO $db,int $productionId,bool $includeArchived=false):array
    {
        $s=$db->prepare("SELECT rv.*,pg.name group_name,CONCAT(u.first_name,' ',u.last_name) uploader FROM rehearsal_videos rv LEFT JOIN production_groups pg ON pg.id=rv.group_id LEFT JOIN users u ON u.id=rv.uploaded_by_user_id WHERE (rv.production_id=:production OR rv.audience_scope='platform')".($includeArchived?"":" AND rv.status='active'")." ORDER BY rv.created_at DESC,rv.id DESC");
        $s->execute(['production'=>$productionId]);return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function record(PDO $db,int $id):?array
    {
        $s=$db->prepare("SELECT rv.*,p.is_active production_active,p.lifecycle_stage FROM rehearsal_videos rv LEFT JOIN productions p ON p.id=rv.production_id WHERE rv.id=:id LIMIT 1");
        $s->execute(['id'=>$id]);return $s->fetch(PDO::FETCH_ASSOC)?:null;
    }

    public static function canView(PDO $db,array $user,array $video):bool
    {
        if($video['status']!=='active')return false;
        if(($video['audience_scope']??'production')==='platform')return true;
        $productionId=(int)$video['production_id'];$id=(int)$user['id'];
        if(($video['audience_scope']??'production')==='group'&&empty($video['group_id']))return false;
        if(!empty($video['group_id'])){
            $v=$db->prepare('SELECT 1 FROM production_groups WHERE id=:id AND production_id=:production AND active=1');
            $v->execute(['id'=>(int)$video['group_id'],'production'=>$productionId]);
            if(!$v->fetchColumn())return false;
            if(!ProductionContext::isActiveMember($db,$id,$productionId))return false;
            $s=$db->prepare("SELECT 1 FROM production_group_members pgm JOIN production_memberships pm ON pm.id=pgm.production_membership_id AND pm.status='active'
                WHERE pgm.group_id=:group_id AND pgm.status='active' AND pm.production_id=:production AND pm.user_id=:viewer LIMIT 1");
            $s->execute(['group_id'=>(int)$video['group_id'],'production'=>$productionId,'viewer'=>$id]);
            if($s->fetchColumn())return true;
            $g=$db->prepare("SELECT 1 FROM family_relationships fr
                JOIN users child ON child.id=fr.student_user_id AND child.active=1 AND child.account_status<>'disabled'
                JOIN production_memberships child_pm ON child_pm.user_id=child.id AND child_pm.production_id=:production AND child_pm.status='active'
                JOIN production_group_members pgm ON pgm.production_membership_id=child_pm.id AND pgm.group_id=:group_id AND pgm.status='active'
                WHERE fr.guardian_user_id=:viewer AND fr.status='active' AND fr.relationship_type IN ('parent','guardian') LIMIT 1");
            $g->execute(['production'=>$productionId,'group_id'=>(int)$video['group_id'],'viewer'=>$id]);
            return (bool)$g->fetchColumn();
        }
        return ProductionContext::isActiveMember($db,$id,$productionId);
    }

    public static function visible(PDO $db,array $user,int $productionId):array
    {
        $all=self::listings($db,$productionId,self::canManage($user));
        return array_values(array_filter($all,static fn(array $video):bool=>
            ($video['status']==='active'&&self::canView($db,$user,$video))||
            (self::canManage($user)&&($video['audience_scope']==='platform'||(int)$video['production_id']===$productionId))
        ));
    }

    public static function upload(PDO $db,array $actor,?array $production,array $post,array $file):int
    {
        if(!self::canManage($actor))throw new RuntimeException('Production staff access is required.');
        $scope=(string)($post['audience_scope']??'production');
        if(!in_array($scope,['platform','production','group'],true))throw new RuntimeException('Choose a valid video audience.');
        $productionId=$scope==='platform'?null:($production?(int)$production['id']:null);
        if($scope!=='platform'){
            if(!$productionId)throw new RuntimeException('Select a production before uploading a production video.');
            ProductionLifecycleGuard::assertWritable($db,$productionId);
        }
        $title=trim((string)($post['title']??''));$desc=trim((string)($post['description']??''));
        $category=(string)($post['category']??'choreography');
        $groupId=(int)($post['group_id']??0);
        if($title===''||mb_strlen($title)>190||mb_strlen($desc)>2000)throw new RuntimeException('Enter a title (up to 190 characters) and a short description.');
        if(!isset(self::CATEGORIES[$category]))throw new RuntimeException('Choose a valid video category.');
        if($scope==='group'&&$groupId<1)throw new RuntimeException('Select the Production Group for this video.');
        if($scope!=='group')$groupId=0;
        if($groupId>0){
            $g=$db->prepare('SELECT 1 FROM production_groups WHERE id=:id AND production_id <=> :production AND active=1 LIMIT 1');
            $g->execute(['id'=>$groupId,'production'=>$productionId]);
            if(!$g->fetchColumn())throw new RuntimeException('The selected Production Group is no longer available.');
        }
        $error=(int)($file['error']??UPLOAD_ERR_NO_FILE);
        if($error!==UPLOAD_ERR_OK)throw new RuntimeException($error===UPLOAD_ERR_INI_SIZE||$error===UPLOAD_ERR_FORM_SIZE?'The video exceeds the server upload limit. Check Bluehost PHP upload_max_filesize and post_max_size.':'Choose a video to upload and try again.');
        $tmp=(string)($file['tmp_name']??'');$size=(int)($file['size']??0);
        $maxMb=max(1,min(1024,(int)(getenv('CTSMD_VIDEO_MAX_MB')?:200)));
        if($tmp===''||!is_uploaded_file($tmp)||$size<1||$size>$maxMb*1048576)throw new RuntimeException('Video upload must be between 1 byte and '.$maxMb.' MB.');
        $mime=(string)(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $extension=self::MIMES[$mime]??null;
        if(!$extension)throw new RuntimeException('Use an MP4, WebM or MOV video. MP4 (H.264) is recommended for playback.');
        $source=(string)($file['name']??'video.'.$extension);
        $name=preg_replace('/[^a-z0-9._-]+/i','-',basename($source))?:'video.'.$extension;
        $safeTitle=self::slug($title);
        $folder=$scope==='platform'?'ctsmd-platform':$productionId.'-'.self::slug((string)$production['title']);
        $key='rehearsal-videos/'.$folder.'/'.date('Y').'/'.date('Ymd-His').'-'.$safeTitle.'-'.bin2hex(random_bytes(6)).'.'.$extension;
        $absolute=self::absolute($key);
        $directory=dirname($absolute);
        if(!is_dir($directory)&&!mkdir($directory,0750,true)&&!is_dir($directory))throw new RuntimeException('Cannot create private rehearsal video directory.');
        self::assertPrivateStorage();
        if(!move_uploaded_file($tmp,$absolute))throw new RuntimeException('The video could not be stored on Bluehost.');
        @chmod($absolute,0640);
        try{
            $db->beginTransaction();
            $s=$db->prepare("INSERT INTO rehearsal_videos (production_id,uploaded_by_user_id,title,category,description,audience_scope,group_id,original_filename,storage_key,mime_type,byte_size,status) VALUES (:production,:actor,:title,:category,:description,:scope,:group_id,:name,:storage,:mime,:size,'active')");
            $s->execute(['production'=>$productionId,'actor'=>(int)$actor['id'],'title'=>$title,'category'=>$category,'description'=>$desc!==''?$desc:null,'scope'=>$scope,'group_id'=>$groupId?:null,'name'=>mb_substr($name,0,255),'storage'=>$key,'mime'=>$mime,'size'=>$size]);
            $id=(int)$db->lastInsertId();
            self::audit($db,(int)$actor['id'],'rehearsal.video_uploaded',$id,$productionId);
            $db->commit();return $id;
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();@unlink($absolute);throw $e;}
    }

    public static function archive(PDO $db,array $actor,int $productionId,int $id,bool $restore=false):void
    {
        if(!self::canManage($actor))throw new RuntimeException('Production staff access required.');
        // Cleanup actions remain allowed after the production has finished.

        $s=$db->prepare("UPDATE rehearsal_videos SET status=:status,archived_at=".($restore?'NULL':'CURRENT_TIMESTAMP')." WHERE id=:id AND production_id <=> :production");
        $s->execute(['status'=>$restore?'active':'archived','id'=>$id,'production'=>$productionId?:null]);
        if($s->rowCount()<1)throw new RuntimeException('Video is unavailable or already in that state.');
        self::audit($db,(int)$actor['id'],$restore?'rehearsal.video_restored':'rehearsal.video_archived',$id,$productionId);
    }

    public static function delete(PDO $db,array $actor,int $productionId,int $id):void
    {
        if(!self::canManage($actor))throw new RuntimeException('Production staff access required.');
        // Cleanup actions remain allowed after the production has finished.

        $record=self::record($db,$id);
        if(!$record||($record['production_id']===null?0:(int)$record['production_id'])!==$productionId||$record['status']!=='archived')throw new RuntimeException('Archive the video before permanently deleting it.');
        $path=self::absolute($record['storage_key']);
        if(is_file($path)&&!unlink($path))throw new RuntimeException('Unable to remove the video from disk. Check permissions.');
        $db->prepare("DELETE FROM rehearsal_videos WHERE id=:id AND production_id <=> :production AND status='archived'")->execute(['id'=>$id,'production'=>$productionId?:null]);
        self::audit($db,(int)$actor['id'],'rehearsal.video_deleted',$id,$productionId);
    }

    public static function usage(PDO $db,int $productionId):array
    {
        $s=$db->prepare("SELECT COUNT(*) n,COALESCE(SUM(byte_size),0) bytes FROM rehearsal_videos WHERE production_id=:production");
        $s->execute(['production'=>$productionId]);return $s->fetch(PDO::FETCH_ASSOC)?:['n'=>0,'bytes'=>0];
    }

    public static function platformUsage(PDO $db):array
    {
        $s=$db->query("SELECT COUNT(*) n,COALESCE(SUM(byte_size),0) bytes FROM rehearsal_videos WHERE audience_scope='platform'");
        return $s->fetch(PDO::FETCH_ASSOC)?:['n'=>0,'bytes'=>0];
    }

    public static function absolute(string $key):string
    {
        if(!preg_match('~^rehearsal-videos/(?:[0-9]+-[a-z0-9-]+|ctsmd-platform)/[0-9]{4}/[a-z0-9.-]+\\.(mp4|webm|mov)$~D',$key))throw new RuntimeException('Invalid private video path.');
        return dirname(__DIR__).'/storage/private/'.$key;
    }

    public static function assertPrivateStorage():void
    {
        $base=dirname(__DIR__).'/storage/private';
        if(!is_file($base.'/.htaccess'))throw new RuntimeException('Private storage access guard is missing. Upload blocked.');
    }

    public static function stream(array $video):never
    {
        self::assertPrivateStorage();
        $path=self::absolute((string)$video['storage_key']);
        if(!is_file($path)||!is_readable($path)){http_response_code(404);exit('Video not available');}
        $size=filesize($path);
        if($size===false||$size<1){http_response_code(404);exit('Video not available');}
        // A browser can navigate CTSMD during playback; do not hold its session lock
        // while streaming hundreds of MB through a shared-host PHP worker.
        if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
        $start=0;$end=$size-1;$status=200;
        header('Content-Type: '.$video['mime_type']);
        header('Accept-Ranges: bytes');
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="rehearsal-video.'.pathinfo($path,PATHINFO_EXTENSION).'"');
        $range=(string)($_SERVER['HTTP_RANGE']??'');
        if($range!==''){
            if(!preg_match('/^bytes=(\\d*)-(\\d*)$/D',$range,$m)||($m[1]===''&&$m[2]==='')){header('Content-Range: bytes */'.$size);http_response_code(416);exit;}
            if($m[1]===''){$length=(int)$m[2];if($length<1){header('Content-Range: bytes */'.$size);http_response_code(416);exit;}$start=max(0,$size-$length);}
            else{$start=(int)$m[1];if($m[2]!=='')$end=min($end,(int)$m[2]);}
            if($start>$end||$start >= $size){header('Content-Range: bytes */'.$size);http_response_code(416);exit;}
            $status=206;header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);
        }
        http_response_code($status);header('Content-Length: '.($end-$start+1));
        if($_SERVER['REQUEST_METHOD']==='HEAD')exit;
        @set_time_limit(0);
        $h=fopen($path,'rb');if(!$h){http_response_code(404);exit;}
        fseek($h,$start);$remain=$end-$start+1;
        while($remain>0&&!feof($h)&&!connection_aborted()){
            $chunk=fread($h,min(262144,$remain));if($chunk===false||$chunk==='')break;
            echo $chunk;$remain-=strlen($chunk);flush();
        }
        fclose($h);exit;
    }

    public static function humanSize(int $bytes):string{return number_format($bytes/1048576,1).' MB';}
    private static function slug(string $value):string
    {
        $v=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$value)??'','-'));
        return mb_substr($v!==''?$v:'production',0,54);
    }
    private static function audit(PDO $db,int $user,string $event,int $id,?int $production):void
    {
        $s=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:user,:event,'rehearsal_video',:id,'Rehearsal video library action.',:meta)");
        $s->execute(['user'=>$user,'event'=>$event,'id'=>$id,'meta'=>json_encode(['production_id'=>$production],JSON_THROW_ON_ERROR)]);
    }
}
