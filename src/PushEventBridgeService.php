<?php

declare(strict_types=1);

require_once __DIR__.'/PushService.php';
require_once __DIR__.'/CommunicationReadStateService.php';

final class PushEventBridgeService
{
    public static function queueNew(PDO $db):array
    {
        return [
            'messages'=>self::messages($db),
            'community'=>self::community($db),
            'notifications'=>self::appNotifications($db),
        ];
    }

    private static function messages(PDO $db):int
    {
        $cursor=self::cursor($db,'messages');
        $s=$db->prepare("SELECT m.id,m.conversation_id,m.sender_user_id,c.subject,CONCAT(u.first_name,' ',u.last_name) sender FROM messages m JOIN conversations c ON c.id=m.conversation_id JOIN users u ON u.id=m.sender_user_id WHERE m.id>:cursor AND m.hidden_at IS NULL ORDER BY m.id LIMIT 250");
        $s->execute(['cursor'=>$cursor]);$count=0;$last=$cursor;
        foreach($s->fetchAll() as $row){$last=max($last,(int)$row['id']);$p=$db->prepare('SELECT user_id FROM conversation_participants WHERE conversation_id=:conversation AND user_id<>:sender');$p->execute(['conversation'=>$row['conversation_id'],'sender'=>$row['sender_user_id']]);foreach($p->fetchAll(PDO::FETCH_COLUMN) as $recipient){$id=PushService::queue($db,(int)$recipient,'messages','New message from '.$row['sender'],(string)($row['subject']?:'CTSMD conversation'),'/messages/thread?id='.(int)$row['conversation_id'],'normal','msg-'.$row['conversation_id']);if($id)$count++;}}
        if($last>$cursor)self::advance($db,'messages',$last);return$count;
    }

    private static function community(PDO $db):int
    {
        $cursor=self::cursor($db,'community_posts');
        $s=$db->prepare("SELECT cp.id,cp.channel_id,cp.author_user_id,c.name channel_name,CONCAT(u.first_name,' ',u.last_name) author FROM channel_posts cp JOIN channels c ON c.id=cp.channel_id JOIN users u ON u.id=cp.author_user_id WHERE cp.id>:cursor AND cp.moderation_status='published' ORDER BY cp.id LIMIT 150");
        $s->execute(['cursor'=>$cursor]);$count=0;$last=$cursor;
        foreach($s->fetchAll() as $row){$last=max($last,(int)$row['id']);$users=$db->query("SELECT id,first_name,last_name,display_role AS role,organization_membership_status,active FROM users WHERE active=1")->fetchAll();foreach($users as $user){if((int)$user['id']===(int)$row['author_user_id'])continue;try{$allowed=CommunicationReadStateService::canAccessChannel($db,$user,(int)$row['channel_id']);}catch(Throwable){$allowed=false;}if(!$allowed)continue;$id=PushService::queue($db,(int)$user['id'],'community','# '.$row['channel_name'],'New Community post from '.$row['author'],'/channels/view?id='.(int)$row['channel_id'],'low','ch-'.$row['channel_id']);if($id)$count++;}}
        if($last>$cursor)self::advance($db,'community_posts',$last);return$count;
    }

    private static function appNotifications(PDO $db):int
    {
        $cursor=self::cursor($db,'app_notifications');
        $s=$db->prepare('SELECT id,recipient_user_id,title,body,action_path FROM app_notifications WHERE id>:cursor ORDER BY id LIMIT 250');$s->execute(['cursor'=>$cursor]);$count=0;$last=$cursor;
        foreach($s->fetchAll() as $row){$last=max($last,(int)$row['id']);$path=(string)($row['action_path']??'');$category=match(true){str_starts_with($path,'/calendar'),str_starts_with($path,'/schedule'),str_starts_with($path,'/production')=>'schedule',str_starts_with($path,'/forms')=>'forms',str_starts_with($path,'/volunteer')=>'volunteer',str_starts_with($path,'/channels')=>'community',str_starts_with($path,'/messages')=>'messages',default=>'general'};$id=PushService::queue($db,(int)$row['recipient_user_id'],$category,(string)$row['title'],mb_substr(trim(strip_tags((string)$row['body'])),0,240),$path?:'/notifications',$category==='schedule'?'high':'normal','notice-'.$row['id']);if($id)$count++;}
        if($last>$cursor)self::advance($db,'app_notifications',$last);return$count;
    }

    private static function cursor(PDO $db,string $key):int{$s=$db->prepare('SELECT last_id FROM push_event_cursors WHERE source_key=:key');$s->execute(['key'=>$key]);return(int)($s->fetchColumn()?:0);}
    private static function advance(PDO $db,string $key,int $id):void{$s=$db->prepare('INSERT INTO push_event_cursors (source_key,last_id) VALUES (:key,:id) ON DUPLICATE KEY UPDATE last_id=GREATEST(last_id,VALUES(last_id))');$s->execute(['key'=>$key,'id'=>$id]);}
}
