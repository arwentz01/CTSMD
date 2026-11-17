<?php
declare(strict_types=1);

require_once __DIR__.'/StorageService.php';
require_once __DIR__.'/CommunicationReadStateService.php';

/**
 * Private, user-owned account photos. Never generate publicly reachable image URLs.
 * Existing StorageService owns uploaded originals; generated square thumbnails share
 * its private storage root and are served only after viewer authorization.
 */
final class MemberProfilePhotoService
{
    private const MAX_BYTES = 6291456;
    private const MAX_PIXELS = 24000000;
    private const MIME = ['image/jpeg','image/png','image/webp'];

    public static function validateImage(string $path,int $size):array
    {
        if($size<1||$size>self::MAX_BYTES)throw new RuntimeException('Choose an image smaller than 6 MB.');
        $info=@getimagesize($path);
        if(!$info||!in_array((string)($info['mime']??''),self::MIME,true))throw new RuntimeException('Choose a valid JPEG, PNG or WebP image.');
        $width=(int)$info[0];$height=(int)$info[1];
        if($width<64||$height<64||$width>7500||$height>7500||$width*$height>self::MAX_PIXELS){
            throw new RuntimeException('Use an image at least 64 pixels wide and high, and no larger than 24 megapixels.');
        }
        if(!extension_loaded('gd'))throw new RuntimeException('Image processing is currently unavailable.');
        return ['mime'=>(string)$info['mime'],'width'=>$width,'height'=>$height];
    }

    public static function save(PDO $db,string $projectRoot,int $actorId,array $upload):void
    {
        if((int)($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Choose a profile photo.');
        $path=(string)($upload['tmp_name']??'');
        if(!is_uploaded_file($path))throw new RuntimeException('Profile photo upload could not be verified.');
        $details=self::validateImage($path,(int)($upload['size']??0));
        $source=match($details['mime']){
            'image/jpeg'=>@imagecreatefromjpeg($path),
            'image/png'=>@imagecreatefrompng($path),
            'image/webp'=>@imagecreatefromwebp($path),
        };
        if(!$source)throw new RuntimeException('That image could not be processed.');
        try{
            // JPEG EXIF orientation is applied on the server as well as by modern browsers.
            if($details['mime']==='image/jpeg'&&function_exists('exif_read_data')){
                $exif=@exif_read_data($path);
                $orientation=(int)($exif['Orientation']??1);
                if(in_array($orientation,[2,4,5,7],true)&&function_exists('imageflip')){
                    imageflip($source, in_array($orientation,[2,5],true)?IMG_FLIP_HORIZONTAL:IMG_FLIP_VERTICAL);
                }
                if(in_array($orientation,[3,4],true)){$rotated=imagerotate($source,180,0);if($rotated){imagedestroy($source);$source=$rotated;}}
                elseif(in_array($orientation,[5,6],true)){$rotated=imagerotate($source,270,0);if($rotated){imagedestroy($source);$source=$rotated;}}
                elseif(in_array($orientation,[7,8],true)){$rotated=imagerotate($source,90,0);if($rotated){imagedestroy($source);$source=$rotated;}}
            }
            $width=imagesx($source);$height=imagesy($source);$side=min($width,$height);
            $sourceX=(int)floor(($width-$side)/2);$sourceY=(int)floor(($height-$side)/2);
            $token=bin2hex(random_bytes(18));$base='profile-thumbnails/'.date('Y/m').'/'.$token;
            $root=self::privateRoot($projectRoot);
            $keys=[];$created=[];
            foreach(['large'=>512,'medium'=>96,'small'=>40] as $name=>$pixels){
                $canvas=imagecreatetruecolor($pixels,$pixels);
                if(!$canvas)throw new RuntimeException('Unable to prepare the profile image.');
                $white=imagecolorallocate($canvas,255,255,255);imagefill($canvas,0,0,$white);
                imagecopyresampled($canvas,$source,0,0,$sourceX,$sourceY,$pixels,$pixels,$side,$side);
                $key=$base.'-'.$name.'.jpg';$absolute=$root.'/'.$key;
                if(!is_dir(dirname($absolute))&&!mkdir(dirname($absolute),0770,true)&&!is_dir(dirname($absolute))){
                    imagedestroy($canvas);throw new RuntimeException('Unable to prepare secure profile image storage.');
                }
                if(!imagejpeg($canvas,$absolute,85)){imagedestroy($canvas);throw new RuntimeException('Unable to save the profile image.');}
                imagedestroy($canvas);@chmod($absolute,0660);$keys[$name]=$key;$created[]=$absolute;
            }
            try{
                // Keep uploaded originals in existing secure storage, never under public assets.
                $stored=StorageService::store($db,$projectRoot,$actorId,$upload);
                $old=self::row($db,$actorId);
                $stmt=$db->prepare('INSERT INTO member_profile_photos (user_id,stored_file_id,avatar_large_key,avatar_medium_key,avatar_small_key) VALUES (:user,:file,:large,:medium,:small) ON DUPLICATE KEY UPDATE stored_file_id=VALUES(stored_file_id),avatar_large_key=VALUES(avatar_large_key),avatar_medium_key=VALUES(avatar_medium_key),avatar_small_key=VALUES(avatar_small_key),updated_at=CURRENT_TIMESTAMP');
                $stmt->execute(['user'=>$actorId,'file'=>(int)$stored['stored_file_id'],'large'=>$keys['large'],'medium'=>$keys['medium'],'small'=>$keys['small']]);
                // Remove stale renditions only once the new mapping has been saved.
                if($old){foreach(['avatar_large_key','avatar_medium_key','avatar_small_key'] as $field)self::unlinkKey($root,(string)$old[$field]);}
            }catch(Throwable $e){foreach($created as $absolute)@unlink($absolute);throw $e;}
        }finally{imagedestroy($source);}
    }

    public static function row(PDO $db,int $userId):?array
    {
        if($userId<1)return null;
        $stmt=$db->prepare('SELECT * FROM member_profile_photos WHERE user_id=:user LIMIT 1');
        $stmt->execute(['user'=>$userId]);return $stmt->fetch()?:null;
    }

    public static function canView(PDO $db,array $viewer,int $subjectId):bool
    {
        $viewerId=(int)$viewer['id'];
        if($subjectId<1)return false;
        if($viewerId===$subjectId)return true;
        $active=$db->prepare("SELECT 1 FROM users WHERE id=:user AND active=1 AND account_status='active' LIMIT 1");
        $active->execute(['user'=>$subjectId]);if(!$active->fetchColumn())return false;
        // Any existing conversation shared by these members is sufficient,
        // including safeguarded conversations with guardian participants.
        $same=$db->prepare('SELECT 1 FROM conversation_participants mine JOIN conversation_participants theirs ON theirs.conversation_id=mine.conversation_id AND theirs.user_id=:subject AND theirs.access_revoked_at IS NULL WHERE mine.user_id=:viewer AND mine.access_revoked_at IS NULL LIMIT 1');
        $same->execute(['subject'=>$subjectId,'viewer'=>$viewerId]);if($same->fetchColumn())return true;
        // Guardians can see their own linked student's photo and vice versa.
        $family=$db->prepare("SELECT 1 FROM family_relationships WHERE status='active' AND relationship_type IN ('parent','guardian') AND ((guardian_user_id=:viewer AND student_user_id=:subject) OR (guardian_user_id=:subject2 AND student_user_id=:viewer2)) LIMIT 1");
        $family->execute(['viewer'=>$viewerId,'subject'=>$subjectId,'subject2'=>$subjectId,'viewer2'=>$viewerId]);
        if($family->fetchColumn())return true;
        // Community access is checked per channel rather than treating a
        // published author as globally visible to every logged-in member.
        $posts=$db->prepare("SELECT DISTINCT channel_id FROM channel_posts WHERE author_user_id=:subject AND moderation_status='published' AND hidden_at IS NULL AND deleted_at IS NULL");
        $posts->execute(['subject'=>$subjectId]);
        foreach($posts->fetchAll(PDO::FETCH_COLUMN) as $channelId){
            if(CommunicationReadStateService::canAccessChannel($db,$viewer,(int)$channelId))return true;
        }
        return false;
    }

    public static function stream(PDO $db,string $projectRoot,array $viewer,int $subjectId,string $size='small'):never
    {
        if(!in_array($size,['small','medium','large'],true)||!self::canView($db,$viewer,$subjectId)){http_response_code(404);exit('Profile photo unavailable.');}
        $photo=self::row($db,$subjectId);if(!$photo){http_response_code(404);exit('Profile photo unavailable.');}
        $root=self::privateRoot($projectRoot);
        $key=(string)$photo['avatar_'.$size.'_key'];
        // Mapping values are controlled by the generator. Never accept paths from a request.
        if(!preg_match('~^profile-thumbnails/\\d{4}/\\d{2}/[a-f0-9]{36}-(small|medium|large)\\.jpg$~',$key)){
            http_response_code(404);exit('Profile photo unavailable.');
        }
        $path=$root.'/'.$key;
        if(!is_file($path)||!is_readable($path)){http_response_code(404);exit('Profile photo unavailable.');}
        header('Content-Type: image/jpeg');header('Content-Length: '.filesize($path));
        header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');
        header('Content-Disposition: inline; filename="profile-photo.jpg"');
        readfile($path);exit;
    }

    private static function privateRoot(string $projectRoot):string
    {
        $configured=trim((string)(getenv('STORAGE_PATH')?:''));
        if($configured==='')return $projectRoot.'/storage/private';
        if(!str_starts_with($configured,'/')&&!preg_match('/^[A-Za-z]:[\\\\\\/]/',$configured)){
            return $projectRoot.'/'.trim($configured,'/\\\\');
        }
        return rtrim($configured,'/\\\\');
    }

    private static function unlinkKey(string $root,string $key):void
    {
        if(preg_match('~^profile-thumbnails/\\d{4}/\\d{2}/[a-f0-9]{36}-(small|medium|large)\\.jpg$~',$key))@unlink($root.'/'.$key);
    }
}
