<?php

declare(strict_types=1);

/**
 * Viewer-only message visibility and conversation archiving.
 * No message or attachment is physically removed. Restricted safeguarding review
 * continues to use the original records.
 */
final class MessageVisibilityService
{
    public static function deleteMessage(PDO $db,int $actorId,int $messageId,string $scope):void
    {
        if($actorId<1||$messageId<1||!in_array($scope,['self','everyone'],true)){
            throw new RuntimeException('Choose a valid message and removal option.');
        }
        $db->beginTransaction();
        try{
            $s=$db->prepare("SELECT m.id,m.conversation_id,m.sender_user_id,m.hidden_at,m.recalled_at,cp.history_through_message_id
                FROM messages m JOIN conversation_participants cp ON cp.conversation_id=m.conversation_id AND cp.user_id=:actor
                WHERE m.id=:id LIMIT 1 FOR UPDATE");
            $s->execute(['actor'=>$actorId,'id'=>$messageId]);$message=$s->fetch();
            if(!$message||$message['hidden_at']!==null||$message['recalled_at']!==null
                ||($message['history_through_message_id']!==null&&$messageId>(int)$message['history_through_message_id'])){
                throw new RuntimeException('This message is no longer available.');
            }
            if($scope==='everyone'){
                if((int)$message['sender_user_id']!==$actorId)throw new RuntimeException('You can only recall messages you sent.');
                // Lock all participant read states, including guardians, before deciding whether
                // any recipient has already read the message.
                $read=$db->prepare("SELECT user_id,last_read_message_id FROM conversation_participants WHERE conversation_id=:id ORDER BY user_id FOR UPDATE");
                $read->execute(['id'=>(int)$message['conversation_id']]);
                foreach($read->fetchAll() as $participant){
                    if((int)$participant['user_id']!==$actorId&&(int)$participant['last_read_message_id'] >= $messageId){
                        throw new RuntimeException('Someone has already read this message. You can only delete it for yourself.');
                    }
                }
                $u=$db->prepare("UPDATE messages SET recalled_at=CURRENT_TIMESTAMP,recalled_by_user_id=:actor WHERE id=:id AND recalled_at IS NULL");
                $u->execute(['actor'=>$actorId,'id'=>$messageId]);
                $event='message.recalled';
            }else{
                $s=$db->prepare('INSERT IGNORE INTO message_personal_hides (message_id,user_id) VALUES (:message,:user)');
                $s->execute(['message'=>$messageId,'user'=>$actorId]);
                $event='message.hidden_for_self';
            }
            self::audit($db,$actorId,$event,'message',$messageId,
                $scope==='everyone'?'Message recalled from participant views.':'Message hidden from sender view.');
            $db->commit();
        }catch(Throwable $e){
            if($db->inTransaction())$db->rollBack();
            throw $e;
        }
    }

    public static function archive(PDO $db,int $actorId,int $conversationId,bool $archived):void
    {
        if($actorId<1||$conversationId<1)throw new RuntimeException('Choose a valid conversation.');
        $db->beginTransaction();
        try{
            $s=$db->prepare('SELECT conversation_id FROM conversation_participants WHERE conversation_id=:conversation AND user_id=:user FOR UPDATE');
            $s->execute(['conversation'=>$conversationId,'user'=>$actorId]);
            if(!$s->fetchColumn())throw new RuntimeException('Conversation not found.');
            $u=$db->prepare('UPDATE conversation_participants SET archived_at='.($archived?'CURRENT_TIMESTAMP':'NULL').' WHERE conversation_id=:conversation AND user_id=:user');
            $u->execute(['conversation'=>$conversationId,'user'=>$actorId]);
            self::audit($db,$actorId,$archived?'conversation.archived':'conversation.restored','conversation',$conversationId,
                $archived?'Conversation archived for participant.':'Conversation restored for participant.');
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }

    public static function restoreOnNewMessage(PDO $db,int $conversationId,int $senderId):void
    {
        // An incoming message reopens an archived thread for its recipients.
        $stmt=$db->prepare('UPDATE conversation_participants SET archived_at=NULL WHERE conversation_id=:conversation AND user_id<>:sender AND archived_at IS NOT NULL AND access_revoked_at IS NULL');
        $stmt->execute(['conversation'=>$conversationId,'sender'=>$senderId]);
    }

    public static function unreadRecallAllowed(PDO $db,int $conversationId,int $messageId,int $senderId):bool
    {
        $s=$db->prepare('SELECT COUNT(*) FROM conversation_participants WHERE conversation_id=:conversation AND user_id<>:sender AND last_read_message_id>=:message');
        $s->execute(['conversation'=>$conversationId,'sender'=>$senderId,'message'=>$messageId]);
        return (int)$s->fetchColumn()===0;
    }

    public static function visiblePredicate(string $messageAlias,string $viewerExpr):string
    {
        // Both ordinary read paths and search must exclude personal hides and recalls.
        return $messageAlias.".recalled_at IS NULL AND NOT EXISTS (SELECT 1 FROM message_personal_hides mph WHERE mph.message_id=".$messageAlias.".id AND mph.user_id=".$viewerExpr.")";
    }

    private static function audit(PDO $db,int $actor,string $event,string $type,int $id,string $summary):void
    {
        $s=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,:event,:type,:id,:summary,'{}')");
        $s->execute(['actor'=>$actor,'event'=>$event,'type'=>$type,'id'=>$id,'summary'=>$summary]);
    }
}
