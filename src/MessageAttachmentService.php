<?php

declare(strict_types=1);

require_once __DIR__.'/StorageService.php';

final class MessageAttachmentService
{
    public static function attachUpload(PDO $db,string $projectRoot,int $messageId,int $actorId,array $upload):?array
    {
        $error=(int)($upload['error']??UPLOAD_ERR_NO_FILE);if($error===UPLOAD_ERR_NO_FILE)return null;$stored=StorageService::store($db,$projectRoot,$actorId,$upload);$s=$db->prepare('INSERT INTO message_attachments (message_id,stored_file_id,attached_by_user_id) VALUES (:message,:file,:actor)');$s->execute(['message'=>$messageId,'file'=>(int)$stored['stored_file_id'],'actor'=>$actorId]);return$stored;
    }

    public static function forMessages(PDO $db,array $messageIds):array
    {
        $messageIds=array_values(array_unique(array_map('intval',$messageIds)));if(!$messageIds)return [];$ph=implode(',',array_fill(0,count($messageIds),'?'));$s=$db->prepare("SELECT ma.id,ma.message_id,ma.stored_file_id,sfv.original_name,sfv.byte_size,sfv.extension,sfv.mime_type FROM message_attachments ma JOIN stored_files sf ON sf.id=ma.stored_file_id AND sf.status='active' JOIN stored_file_versions sfv ON sfv.id=(SELECT v.id FROM stored_file_versions v WHERE v.stored_file_id=ma.stored_file_id ORDER BY v.version_number DESC LIMIT 1) WHERE ma.message_id IN ($ph) ORDER BY ma.message_id,ma.sort_order,ma.id");$s->execute($messageIds);$out=[];foreach($s->fetchAll() as $r)$out[(int)$r['message_id']][]=$r;return$out;
    }

    public static function attachmentForParticipant(PDO $db,int $attachmentId,int $userId):?array
    {
        $s=$db->prepare("SELECT ma.id,ma.message_id,ma.stored_file_id,m.conversation_id,sfv.* FROM message_attachments ma JOIN messages m ON m.id=ma.message_id AND m.hidden_at IS NULL JOIN conversation_participants cp ON cp.conversation_id=m.conversation_id AND cp.user_id=:user JOIN stored_files sf ON sf.id=ma.stored_file_id AND sf.status='active' JOIN stored_file_versions sfv ON sfv.id=(SELECT v.id FROM stored_file_versions v WHERE v.stored_file_id=ma.stored_file_id ORDER BY v.version_number DESC LIMIT 1) WHERE ma.id=:id LIMIT 1");$s->execute(['user'=>$userId,'id'=>$attachmentId]);return$s->fetch()?:null;
    }
}
