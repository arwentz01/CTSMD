<?php

declare(strict_types=1);

require_once __DIR__.'/StorageService.php';

final class CommunityAttachmentService
{
    public static function attachUpload(PDO $db,string $projectRoot,int $postId,int $actorId,array $upload):?array
    {
        $error=(int)($upload['error']??UPLOAD_ERR_NO_FILE);if($error===UPLOAD_ERR_NO_FILE)return null;
        $stored=StorageService::store($db,$projectRoot,$actorId,$upload);
        $s=$db->prepare('INSERT INTO channel_post_attachments (post_id,stored_file_id,attached_by_user_id) VALUES (:post,:file,:actor)');$s->execute(['post'=>$postId,'file'=>(int)$stored['stored_file_id'],'actor'=>$actorId]);
        return $stored;
    }

    public static function forPosts(PDO $db,array $postIds):array
    {
        $postIds=array_values(array_unique(array_map('intval',$postIds)));if(!$postIds)return [];$ph=implode(',',array_fill(0,count($postIds),'?'));
        $s=$db->prepare("SELECT cpa.id,cpa.post_id,cpa.stored_file_id,sfv.original_name,sfv.byte_size,sfv.extension,sfv.mime_type FROM channel_post_attachments cpa JOIN stored_files sf ON sf.id=cpa.stored_file_id AND sf.status='active' JOIN stored_file_versions sfv ON sfv.id=(SELECT v.id FROM stored_file_versions v WHERE v.stored_file_id=cpa.stored_file_id ORDER BY v.version_number DESC LIMIT 1) WHERE cpa.post_id IN ($ph) ORDER BY cpa.post_id,cpa.sort_order,cpa.id");$s->execute($postIds);$out=[];foreach($s->fetchAll() as $row)$out[(int)$row['post_id']][]=$row;return$out;
    }

    public static function attachment(PDO $db,int $attachmentId):?array
    {
        $s=$db->prepare("SELECT cpa.id,cpa.post_id,cpa.stored_file_id,cp.channel_id,cp.moderation_status,sfv.* FROM channel_post_attachments cpa JOIN channel_posts cp ON cp.id=cpa.post_id JOIN stored_files sf ON sf.id=cpa.stored_file_id AND sf.status='active' JOIN stored_file_versions sfv ON sfv.id=(SELECT v.id FROM stored_file_versions v WHERE v.stored_file_id=cpa.stored_file_id ORDER BY v.version_number DESC LIMIT 1) WHERE cpa.id=:id LIMIT 1");$s->execute(['id'=>$attachmentId]);return$s->fetch()?:null;
    }
}
