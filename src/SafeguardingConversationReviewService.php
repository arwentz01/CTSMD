<?php

declare(strict_types=1);

require_once __DIR__.'/Auth.php';

/**
 * Read-only, reason-gated safeguarding oversight of conversations.
 * Review never adds the reviewer to the conversation or changes read receipts.
 */
final class SafeguardingConversationReviewService
{
    public static function assertReviewer(PDO $db,array $user):void
    {
        $id=(int)($user['id']??0);
        if($id<1||!Auth::check())throw new RuntimeException('Authentication required.');
        // No generic staff-role or legacy permission fallback for sensitive DM content.
        if(!in_array('safeguarding.manage',Auth::permissions($db,$id),true))
            throw new RuntimeException('Explicit safeguarding review permission is required.');
    }

    public static function search(PDO $db,?int $participantA,?int $participantB,string $nameA='',string $nameB='',int $page=1):array
    {
        $page=max(1,min($page,10000));
        $where=[];$params=[];
        foreach([['id'=>$participantA,'name'=>$nameA],['id'=>$participantB,'name'=>$nameB]] as $i=>$filter){
            $alias='p'.$i;$suffix=(string)$i;
            if(($filter['id']??0)>0){
                $where[]="EXISTS (SELECT 1 FROM conversation_participants $alias WHERE $alias.conversation_id=c.id AND $alias.user_id=:user$suffix)";
                $params['user'.$suffix]=(int)$filter['id'];
            } elseif(trim($filter['name'])!==''){
                $where[]="EXISTS (SELECT 1 FROM conversation_participants $alias JOIN users u$suffix ON u$suffix.id=$alias.user_id WHERE $alias.conversation_id=c.id AND (CONCAT(u$suffix.first_name,' ',u$suffix.last_name) LIKE :name$suffix OR u$suffix.email LIKE :email$suffix))";
                $value='%'.str_replace(['!','%','_'],['!!','!%','!_'],trim($filter['name'])).'%';
                $params['name'.$suffix]=$value;$params['email'.$suffix]=$value;
                $where[count($where)-1]=str_replace('LIKE :name'.$suffix,"LIKE :name$suffix ESCAPE '!'",str_replace('LIKE :email'.$suffix,"LIKE :email$suffix ESCAPE '!'",end($where)));
            }
        }
        $predicate=$where?'WHERE '.implode(' AND ',$where):'';
        $sql="SELECT c.id,c.subject,c.conversation_type,
              (SELECT MAX(m.created_at) FROM messages m WHERE m.conversation_id=c.id) last_message_at,
              (SELECT COUNT(*) FROM messages m WHERE m.conversation_id=c.id) message_count,
              (SELECT GROUP_CONCAT(CONCAT(u.first_name,' ',u.last_name) ORDER BY u.last_name,u.first_name SEPARATOR ', ') FROM conversation_participants cp JOIN users u ON u.id=cp.user_id WHERE cp.conversation_id=c.id) participant_names
              FROM conversations c $predicate
              ORDER BY last_message_at DESC,c.id DESC LIMIT 51 OFFSET ".(($page-1)*50);
        $stmt=$db->prepare($sql);$stmt->execute($params);
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['rows'=>array_slice($rows,0,50),'has_more'=>count($rows)>50,'page'=>$page];
    }

    public static function review(PDO $db,array $reviewer,int $conversationId,string $reason,?int $caseId=null,int $page=1):array
    {
        self::assertReviewer($db,$reviewer);
        $reason=trim($reason);
        if(mb_strlen($reason)<12||mb_strlen($reason)>1000)throw new RuntimeException('Provide a meaningful review reason (12–1000 characters).');
        if($conversationId<1)throw new RuntimeException('Select a conversation.');
        $page=max(1,min($page,10000));
        if($caseId!==null){
            $case=$db->prepare('SELECT id FROM safeguarding_cases WHERE id=:id LIMIT 1');
            $case->execute(['id'=>$caseId]);
            if(!$case->fetchColumn())throw new RuntimeException('The referenced safeguarding case does not exist.');
        }
        $stmt=$db->prepare('SELECT id,subject,conversation_type,created_at FROM conversations WHERE id=:id LIMIT 1');
        $stmt->execute(['id'=>$conversationId]);$conversation=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$conversation)throw new RuntimeException('Conversation not found.');
        $participants=$db->prepare("SELECT cp.user_id,cp.participant_role,cp.access_revoked_at,CONCAT(u.first_name,' ',u.last_name) name FROM conversation_participants cp JOIN users u ON u.id=cp.user_id WHERE cp.conversation_id=:id ORDER BY u.last_name,u.first_name");
        $participants->execute(['id'=>$conversationId]);$conversation['participants']=$participants->fetchAll(PDO::FETCH_ASSOC);

        // Record the review BEFORE returning content; a failed audit means no disclosure.
        $audit=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,'safeguarding.conversation_reviewed','conversation',:conversation,'Safeguarding reviewer accessed conversation content.',:metadata)");
        $audit->execute(['actor'=>(int)$reviewer['id'],'conversation'=>$conversationId,'metadata'=>json_encode(['reason'=>$reason,'case_id'=>$caseId,'page'=>$page],JSON_THROW_ON_ERROR)]);

        $messages=$db->prepare('SELECT m.id,m.sender_user_id,m.body,m.created_at,m.hidden_at,CONCAT(u.first_name,\' \',u.last_name) sender_name FROM messages m JOIN users u ON u.id=m.sender_user_id WHERE m.conversation_id=:id ORDER BY m.id DESC LIMIT 51 OFFSET '.(($page-1)*50));
        $messages->execute(['id'=>$conversationId]);$rows=$messages->fetchAll(PDO::FETCH_ASSOC);
        $conversation['messages']=array_reverse(array_slice($rows,0,50));
        $conversation['has_older']=count($rows)>50;
        $conversation['page']=$page;
        return $conversation;
    }
}
