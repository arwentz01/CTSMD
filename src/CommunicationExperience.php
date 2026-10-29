<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__.'/WorkspaceUI.php';
require_once __DIR__ . '/CommunicationReadStateService.php';
require_once __DIR__ . '/MessageAttachmentService.php';
require_once __DIR__ . '/SafeguardedConversationPolicy.php';
require_once __DIR__ . '/StorageService.php';

final class CommunicationExperience
{
    private const ROUTES = ['/messages', '/messages/new', '/messages/thread', '/messages/attachment'];

    public static function handles(string $route): bool{return in_array($route,self::ROUTES,true);}

    public static function render(string $route,string $basePath): never
    {
        Auth::startSession();$db=Database::connect(dirname(__DIR__));$user=self::currentUser($db);$userId=(int)$user['id'];$_SESSION['communication_csrf']??=bin2hex(random_bytes(24));
        if($route==='/messages/attachment'){$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;$attachment=MessageAttachmentService::attachmentForParticipant($db,(int)$id,$userId);if(!$attachment){http_response_code(404);exit('Attachment unavailable.');}StorageService::stream(dirname(__DIR__),$attachment,true);}
        if($_SERVER['REQUEST_METHOD']==='POST')self::handlePost($db,$user,$basePath);
        $query=trim((string)($_GET['q']??''));if(mb_strlen($query)>100)$query=mb_substr($query,0,100);$conversations=self::conversations($db,$userId,$query);$recipients=$route==='/messages/new'?self::availableRecipients($db,$user):[];$selectedConversation=null;
        if($route==='/messages/thread'){$conversationId=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;$selectedConversation=self::conversationById($db,$userId,(int)$conversationId);}self::page($route,$basePath,$user,$conversations,$recipients,$selectedConversation,$query);
    }

    private static function handlePost(PDO $db,array $user,string $basePath): never
    {
        $token=(string)($_POST['csrf_token']??'');if(!hash_equals((string)($_SESSION['communication_csrf']??''),$token)){self::flash('error','Your session token expired. Please try again.');self::redirect($basePath.'/messages');}$action=(string)($_POST['action']??'');$userId=(int)$user['id'];$upload=$_FILES['attachment']??[];
        if($action==='create_conversation'){$recipientId=filter_input(INPUT_POST,'recipient_id',FILTER_VALIDATE_INT)?:0;$subject='';$body=trim((string)($_POST['body']??''));try{$conversationId=self::createConversation($db,$user,(int)$recipientId,$subject,$body,$upload);self::flash('success','Conversation created with the required safety participants included.');self::redirect($basePath.'/messages/thread?id='.$conversationId);}catch(RuntimeException $e){self::flash('error',$e->getMessage());self::redirect($basePath.'/messages/new');}}
        $conversationId=filter_input(INPUT_POST,'conversation_id',FILTER_VALIDATE_INT)?:0;if($action!=='send_message'||$conversationId<1){self::flash('error','That message action could not be completed.');self::redirect($basePath.'/messages');}$body=trim((string)($_POST['body']??''));try{$attached=self::sendMessage($db,$userId,(int)$conversationId,$body,$upload);self::flash('success',$attached?'Message and attachment sent to everyone in this conversation.':'Message sent to everyone in this conversation.');}catch(RuntimeException $e){self::flash('error',$e->getMessage());}self::redirect($basePath.'/messages/thread?id='.(int)$conversationId);
    }

    // Reuse the web experience's safeguarding rules for mobile conversation creation.
    public static function mobileRecipients(PDO $db,array $user):array
    {
        return array_values(array_map(static fn(array $row):array=>[
            'id'=>(int)$row['id'],'name'=>(string)$row['name'],
            'role'=>(string)$row['role'],'selectable'=>(bool)$row['selectable']
        ],self::availableRecipients($db,$user)));
    }

    public static function mobileCreateConversation(PDO $db,array $user,int $recipientId,string $subject,string $body):int
    {
        return self::createConversation($db,$user,$recipientId,$subject,$body,[]);
    }

    private static function createConversation(PDO $db,array $initiator,int $recipientId,string $subject,string $body,array $upload): int
    {
        if($recipientId<1||$recipientId===(int)$initiator['id'])throw new RuntimeException('Choose another available CTSMD member.');
        if(mb_strlen($subject)>190)throw new RuntimeException('Group name is too long.');
        if($body===''||mb_strlen($body)>4000)throw new RuntimeException('Add an opening message up to 4,000 characters.');
        $recipientStmt=$db->prepare("SELECT id,CONCAT(first_name,' ',last_name) name,display_role role,active,account_status FROM users WHERE id=:id LIMIT 1");
        $recipientStmt->execute(['id'=>$recipientId]);$recipient=$recipientStmt->fetch();
        if(!$recipient||!(bool)$recipient['active']||$recipient['account_status']!=='active')throw new RuntimeException('That recipient is not available.');
        $recipient['roles']=Auth::roles($db,$recipientId);$subject=trim($subject)!==''?$subject:(string)$recipient['name'];
        $initiatorStudent=self::isStudentUser($initiator);$recipientStudent=self::isStudentUser($recipient);
        if($initiatorStudent&&$recipientStudent)throw new RuntimeException('Student-to-student direct messaging is not available.');
        $type='direct';$participants=[];
        if($initiatorStudent||$recipientStudent){
            $student=$initiatorStudent?$initiator:$recipient;$adult=$initiatorStudent?$recipient:$initiator;
            if(!SafeguardedConversationPolicy::canContactStudent($db,(int)$adult['id'],(int)$student['id']))throw new RuntimeException('Student conversations require current CTSMD staff or an approved Adult performer in a shared active production.');
            $guardians=SafeguardedConversationPolicy::eligibleGuardians($db,(int)$student['id']);
            if(!$guardians)throw new RuntimeException('This student does not have an available guardian relationship. Messaging cannot begin until staff repairs that relationship.');
            $type='safeguarded';
            $participants=[
                ['user_id'=>(int)$adult['id'],'participant_role'=>'adult','guardian_required'=>0],
                ['user_id'=>(int)$student['id'],'participant_role'=>'student','guardian_required'=>0],
            ];
            foreach($guardians as $guardian){
                $guardianId=(int)$guardian['id'];
                if($guardianId===(int)$adult['id']||$guardianId===(int)$student['id'])continue;
                $participants[]=['user_id'=>$guardianId,'participant_role'=>'guardian','guardian_required'=>1];
            }
            if(count($participants)<3)throw new RuntimeException('A separate available guardian is required before this student conversation can begin.');
        }else{
            $participants=[
                ['user_id'=>(int)$initiator['id'],'participant_role'=>'adult','guardian_required'=>0],
                ['user_id'=>(int)$recipient['id'],'participant_role'=>'adult','guardian_required'=>0],
            ];
        }

        $db->beginTransaction();
        try{
            $conversationId=0;
            if($type==='safeguarded'){
                $guardianIds=array_values(array_map(static fn(array $row):int=>(int)$row['user_id'],array_filter($participants,static fn(array $row):bool=>$row['participant_role']==='guardian')));
                $studentId=(int)array_values(array_filter($participants,static fn(array $row):bool=>$row['participant_role']==='student'))[0]['user_id'];
                $staffId=(int)array_values(array_filter($participants,static fn(array $row):bool=>$row['participant_role']==='adult'))[0]['user_id'];
                $conversationId=SafeguardedConversationPolicy::findReusableConversation($db,$studentId,$staffId,$guardianIds)??0;
            }
            if($conversationId<1){
                $insertConversation=$db->prepare('INSERT INTO conversations (subject,conversation_type,created_at) VALUES (:subject,:type,CURRENT_TIMESTAMP)');
                $insertConversation->execute(['subject'=>$subject,'type'=>$type]);$conversationId=(int)$db->lastInsertId();
                $insertParticipant=$db->prepare('INSERT INTO conversation_participants (conversation_id,user_id,participant_role,guardian_required) VALUES (:conversation,:user,:role,:guardian)');
                $seen=[];
                foreach($participants as $participant){
                    if(isset($seen[$participant['user_id']]))continue;$seen[$participant['user_id']]=true;
                    $insertParticipant->execute(['conversation'=>$conversationId,'user'=>$participant['user_id'],'role'=>$participant['participant_role'],'guardian'=>$participant['guardian_required']]);
                }
            }
            $insertMessage=$db->prepare('INSERT INTO messages (conversation_id,sender_user_id,body,created_at) VALUES (:conversation,:sender,:body,CURRENT_TIMESTAMP)');
            $insertMessage->execute(['conversation'=>$conversationId,'sender'=>(int)$initiator['id'],'body'=>$body]);$messageId=(int)$db->lastInsertId();
            MessageAttachmentService::attachUpload($db,dirname(__DIR__),$messageId,(int)$initiator['id'],$upload);
            SafeguardedConversationPolicy::enforceForSend($db,$conversationId,(int)$initiator['id']);
            $db->commit();return$conversationId;
        }catch(Throwable $e){
            if($db->inTransaction())$db->rollBack();
            throw $e instanceof RuntimeException?$e:new RuntimeException('We could not create that conversation. Please try again.');
        }
    }

    private static function sendMessage(PDO $db,int $userId,int $conversationId,string $body,array $upload):bool
    {
        if($body===''||mb_strlen($body)>4000)throw new RuntimeException($body===''?'Write a message before sending.':'Messages are limited to 4,000 characters.');
        $db->beginTransaction();
        try{
            SafeguardedConversationPolicy::enforceForSend($db,$conversationId,$userId);
            $insert=$db->prepare('INSERT INTO messages (conversation_id,sender_user_id,body,created_at) VALUES (:conversation,:sender,:body,CURRENT_TIMESTAMP)');
            $insert->execute(['conversation'=>$conversationId,'sender'=>$userId,'body'=>$body]);$messageId=(int)$db->lastInsertId();
            $attached=MessageAttachmentService::attachUpload($db,dirname(__DIR__),$messageId,$userId,$upload);
            $db->commit();return(bool)$attached;
        }catch(Throwable $e){
            if($db->inTransaction())$db->rollBack();
            throw $e instanceof RuntimeException?$e:new RuntimeException('We could not send that message. Please try again.');
        }
    }

    private static function currentUser(PDO $db):array{$user=Auth::currentUser($db);if(!$user)throw new RuntimeException('Sign in to use Messages.');return$user;}
    private static function isStudentUser(array $user):bool{return in_array('student',(array)($user['roles']??[]),true);}
    private static function isStaffUser(array $user):bool{return in_array('production_staff',(array)($user['roles']??[]),true)||in_array('administrator',(array)($user['roles']??[]),true);}
    private static function availableRecipients(PDO $db,array $user):array{$s=$db->prepare("SELECT id,CONCAT(first_name,' ',last_name) name,display_role role,initials FROM users WHERE active=1 AND account_status<>'disabled' AND id<>:user ORDER BY last_name,first_name");$s->execute(['user'=>(int)$user['id']]);$rows=$s->fetchAll();$userIsStudent=self::isStudentUser($user);foreach($rows as &$r){$r['roles']=Auth::roles($db,(int)$r['id']);$r['student']=self::isStudentUser($r);$r['selectable']=$userIsStudent?self::isStaffUser($r):(!$r['student']||self::isStaffUser($user));}unset($r);return$rows;}
    private static function conversations(PDO $db,int $userId,string $query=''):array
    {
        $search='';$params=['user_id'=>$userId];if($query!==''){$search=" AND (c.subject LIKE :query OR EXISTS (SELECT 1 FROM messages sm WHERE sm.conversation_id=c.id AND sm.hidden_at IS NULL AND (mine.history_through_message_id IS NULL OR sm.id<=mine.history_through_message_id) AND sm.body LIKE :query2))";$params['query']='%'.$query.'%';$params['query2']='%'.$query.'%';}
        $s=$db->prepare("SELECT c.id,c.subject,c.conversation_type,MAX(m.created_at) latest_at,COUNT(DISTINCT m.id) message_count,COUNT(DISTINCT cp.user_id) participant_count,(SELECT COUNT(*) FROM messages um WHERE um.conversation_id=c.id AND um.hidden_at IS NULL AND um.id>mine.last_read_message_id AND um.sender_user_id<>mine.user_id AND (mine.history_through_message_id IS NULL OR um.id<=mine.history_through_message_id)) unread_count FROM conversations c JOIN conversation_participants mine ON mine.conversation_id=c.id AND mine.user_id=:user_id JOIN conversation_participants cp ON cp.conversation_id=c.id LEFT JOIN messages m ON m.conversation_id=c.id AND m.hidden_at IS NULL AND (mine.history_through_message_id IS NULL OR m.id<=mine.history_through_message_id) WHERE 1=1 $search GROUP BY c.id,c.subject,c.conversation_type,mine.last_read_message_id,mine.user_id,mine.history_through_message_id ORDER BY latest_at DESC,c.id DESC");$s->execute($params);$rows=$s->fetchAll();$names=$db->prepare("SELECT cp.user_id,cp.participant_role,CONCAT(u.first_name,' ',u.last_name) name FROM conversation_participants cp JOIN users u ON u.id=cp.user_id WHERE cp.conversation_id=:id ORDER BY cp.user_id");foreach($rows as &$row){$names->execute(['id'=>$row['id']]);$row['participants']=$names->fetchAll();}unset($row);return $rows;
    }

    private static function conversationById(PDO $db,int $userId,int $conversationId):?array
    {
        if($conversationId<1)return null;$s=$db->prepare("SELECT c.id,c.subject,c.conversation_type,mine.history_through_message_id,mine.access_revoked_at FROM conversations c JOIN conversation_participants mine ON mine.conversation_id=c.id AND mine.user_id=:user WHERE c.id=:id LIMIT 1");$s->execute(['user'=>$userId,'id'=>$conversationId]);$conversation=$s->fetch();if(!$conversation)return null;$p=$db->prepare("SELECT cp.user_id,cp.participant_role,cp.guardian_required,CASE WHEN u.active=1 AND u.account_status<>'disabled' THEN 1 ELSE 0 END active,CONCAT(u.first_name,' ',u.last_name) name,u.display_role role,u.initials FROM conversation_participants cp JOIN users u ON u.id=cp.user_id WHERE cp.conversation_id=:conversation ORDER BY FIELD(cp.participant_role,'student','guardian','adult'),u.last_name,u.first_name");$p->execute(['conversation'=>$conversationId]);$conversation['participants']=$p->fetchAll();$m=$db->prepare("SELECT m.id,m.body,m.created_at,m.sender_user_id,CONCAT(u.first_name,' ',u.last_name) sender,u.display_role sender_role,u.initials FROM messages m JOIN users u ON u.id=m.sender_user_id WHERE m.conversation_id=:conversation AND m.hidden_at IS NULL AND (:cutoff IS NULL OR m.id<=:cutoff2) ORDER BY m.created_at,m.id");$m->execute(['conversation'=>$conversationId,'cutoff'=>$conversation['history_through_message_id'],'cutoff2'=>$conversation['history_through_message_id']]);$conversation['messages']=$m->fetchAll();$attachments=MessageAttachmentService::forMessages($db,array_map(static fn(array $row):int=>(int)$row['id'],$conversation['messages']));foreach($conversation['messages'] as &$message)$message['attachments']=$attachments[(int)$message['id']]??[];unset($message);try{SafeguardedConversationPolicy::enforceForSend($db,$conversationId,$userId);$conversation['send_allowed']=true;$conversation['safety_issue']=null;}catch(RuntimeException $e){$conversation['send_allowed']=false;$conversation['safety_issue']=$e->getMessage();}return$conversation;
    }

    private static function conversationDisplayName(array $conversation,int $viewerId):string
    {
        if(($conversation['conversation_type']??'')==='group')return (string)($conversation['subject']?:'Group conversation');
        $people=$conversation['participants']??[];
        $names=array_values(array_map(static fn(array $p):string=>(string)$p['name'],array_filter($people,static fn(array $p):bool=>(int)$p['user_id']!==$viewerId&&($p['participant_role']??'')!=='guardian')));
        if($names)return implode(', ',$names);
        return (string)($conversation['display_name']??$conversation['subject']??'Conversation');
    }

    private static function flash(string $type,string $message):void{$_SESSION['communication_flash']=['type'=>$type,'message'=>$message];}
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}

    private static function page(string $route,string $basePath,array $user,array $conversations,array $recipients,?array $selectedConversation,string $query):never
    {
        $url=static fn(string $path):string=>($basePath?:'').$path;$esc=static fn(string $value):string=>htmlspecialchars($value,ENT_QUOTES,'UTF-8');$flash=$_SESSION['communication_flash']??null;unset($_SESSION['communication_flash']);$title=match($route){'/messages'=>'Messages','/messages/new'=>'New conversation',default=>self::conversationDisplayName($selectedConversation??[],(int)$user['id'])};$section='Messages';header('Content-Type:text/html; charset=utf-8');?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?=$esc($title)?> · CTSMD Connect</title><link rel="stylesheet" href="<?=$url('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$url('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$url('/assets/css/communication-implementation.css')?>"><link rel="stylesheet" href="<?=$url('/assets/css/message-attachments.css')?>"><link rel="stylesheet" href="<?=$url('/assets/css/mobile-messaging-stream.css')?>"><?php WorkspaceUI::styles($basePath); ?></head><body class="app-body ux-workspace ux-messages"><a class="ux-skip" href="#ux-main">Skip to main content</a><div class="unified-shell"><?php AppNavigation::renderSidebar($route,$basePath,$user);?><main class="unified-main" id="ux-main" tabindex="-1"><?php AppNavigation::renderHeader($section,$title,$basePath);?><div class="comm-page"><div class="comm-safety-banner"><div><b>CTSMD messaging is monitored for safeguarding</b><span>Authorized safeguarding reviewers may inspect conversations when investigating reports or safety concerns. Reviews are logged.</span></div></div><?php if($flash):?><div class="comm-flash <?=$esc($flash['type'])?>"><?=$esc($flash['message'])?></div><?php endif;?>
        <?php if($route==='/messages'):?><section class="comm-message-intro"><div><small>YOUR CONVERSATIONS</small><h2>Messages</h2><p>Direct conversations stay with your account across productions.</p></div><a class="button" href="<?=$url('/messages/new')?>">New conversation</a></section><form class="msg-search" method="get" action="<?=$url('/messages')?>"><label class="ux-visually-hidden" for="message-search">Search conversations</label><input id="message-search" type="search" name="q" maxlength="100" value="<?=$esc($query)?>" placeholder="Search messages"><button type="submit">Search</button><?php if($query!==''):?><a href="<?=$url('/messages')?>">Clear</a><?php endif;?></form><div class="comm-conversation-list"><?php if(!$conversations):?><div class="comm-empty"><b><?=$query!==''?'No matching conversations':'No conversations yet'?></b><p><?=$query!==''?'Try a different search term.':'Start one with another CTSMD member.'?></p></div><?php endif;?><?php foreach($conversations as $conversation):$unread=(int)($conversation['unread_count']??0);?><a class="comm-conversation<?=$unread>0?' unread':''?>" href="<?=$url('/messages/thread?id='.(int)$conversation['id'])?>"><div class="comm-conversation-icon"><?=$conversation['conversation_type']==='safeguarded'?'●':'✉'?></div><div><small><?=$conversation['conversation_type']==='safeguarded'?'Safeguarded':'Direct'?></small><h3><?=$esc(self::conversationDisplayName($conversation,(int)$user['id']))?></h3><p><?=(int)$conversation['participant_count']?> participants · <?=(int)$conversation['message_count']?> messages</p></div><div class="comm-conversation-meta"><span><?=$conversation['latest_at']?$esc(date('M j · g:i A',strtotime($conversation['latest_at']))):'No messages'?></span><?php if($unread>0):?><strong class="comm-unread"><?=$unread?> new</strong><?php else:?><b>Open →</b><?php endif;?></div></a><?php endforeach;?></div>
        <?php elseif($route==='/messages/new'):?><section class="comm-channel-head"><div><small>NEW CONVERSATION</small><h2>Start a message</h2><p>Safeguarding is applied automatically when a student is selected.</p></div><a href="<?=$url('/messages')?>">Cancel →</a></section><form class="comm-new-form" method="post" enctype="multipart/form-data" action="<?=$url('/messages/new')?>"><input type="hidden" name="csrf_token" value="<?=$esc((string)$_SESSION['communication_csrf'])?>"><input type="hidden" name="action" value="create_conversation"><label>Recipient<select name="recipient_id" required><option value="">Choose a member</option><?php foreach($recipients as $recipient):?><option value="<?=(int)$recipient['id']?>"<?=!$recipient['selectable']?' disabled':''?>><?=$esc($recipient['name'])?> — <?=$esc($recipient['role'])?><?=$recipient['student']&&!$recipient['selectable']?' (staff contact required)':''?></option><?php endforeach;?></select></label><label>Opening message<textarea name="body" rows="5" maxlength="4000" required></textarea></label><div class="comm-compose-actions"><label class="comm-attach-compact" title="Add attachment">+<input type="file" name="attachment" aria-label="Attach a file to your message" data-file-input></label><span class="comm-file-name" role="status" data-file-name></span><button class="button" type="submit">Create conversation</button></div></form>
        <?php elseif($route==='/messages/thread'):?><?php if(!$selectedConversation):?><section class="comm-empty"><b>Conversation not found</b><p>You may no longer have access to this conversation.</p><a class="button" href="<?=$url('/messages')?>">Back to Messages</a></section><?php else:?><section class="comm-thread-head"><div><small><?=$selectedConversation['conversation_type']==='safeguarded'?'Safeguarded':''?></small><h2><?=$esc(self::conversationDisplayName($selectedConversation,(int)$user['id']))?></h2><div class="comm-thread-people"><div class="comm-avatar-stack" aria-label="Conversation participants"><?php foreach($selectedConversation['participants'] as $participant):?><i class="<?=$esc((string)$participant['participant_role'])?>" title="<?=$esc($participant['name'])?> · <?=$esc(ucfirst($participant['participant_role']))?>"><?=$esc($participant['initials'])?></i><?php endforeach;?></div><button class="comm-people-toggle" type="button" data-people-toggle aria-controls="ux-conversation-participants" aria-expanded="false">Who can see this</button></div></div><a href="<?=$url('/messages')?>">All messages →</a></section><?php if($selectedConversation['conversation_type']==='safeguarded'):?><?php if(in_array((int)$user['id'],array_map(static fn(array $p):int=>((string)($p['participant_role']??'')==='guardian'?(int)$p['user_id']:0),$selectedConversation['participants']),true)):?><p class="comm-guardian-notifications">Guardian alerts: <a href="<?=$url('/notification-preferences')?>">Manage safeguarded conversation notifications</a></p><?php endif;?><div class="comm-safety-banner"><div><b><?=$selectedConversation['send_allowed']?'Guardian relationship current':'Safeguarding review required'?></b><span><?=$selectedConversation['send_allowed']?'The required guardian relationship and CTSMD staff authority are current.':'The conversation remains available as history, but sending is paused until the safeguarding relationship is repaired.'?></span></div><strong><?=$selectedConversation['send_allowed']?'PROTECTED':'PAUSED'?></strong></div><?php endif;?><aside class="comm-participants collapsed" data-participants id="ux-conversation-participants"><div class="comm-participants-list"><?php foreach($selectedConversation['participants'] as $participant):?><div><i><?=$esc($participant['initials'])?></i><span><b><?=$esc($participant['name'])?></b><small><?=$esc(ucfirst($participant['participant_role']))?> · <?=$esc($participant['role'])?></small></span></div><?php endforeach;?></div></aside><div class="comm-thread-layout"><section class="comm-thread"><?php $lastDay=null;foreach($selectedConversation['messages'] as $message):$mine=(int)$message['sender_user_id']===(int)$user['id'];$dayKey=date('Y-m-d',strtotime($message['created_at']));if($dayKey!==$lastDay):$today=date('Y-m-d');$yesterday=date('Y-m-d',strtotime('-1 day'));$dayLabel=$dayKey===$today?'Today':($dayKey===$yesterday?'Yesterday':date('M j, Y',strtotime($message['created_at'])));?><div class="comm-day-separator"><span><?=$esc($dayLabel)?></span></div><?php $lastDay=$dayKey;endif;?><div class="comm-message-row<?=$mine?' mine':''?>"><?php if(!$mine):?><i class="comm-message-avatar" title="<?=$esc($message['sender'])?>"><?=$esc($message['initials'])?></i><?php endif;?><article class="comm-bubble<?=$mine?' mine':''?>"><header><i><?=$esc($message['initials'])?></i><span><b><?=$esc($message['sender'])?></b><small><?=$esc($message['sender_role'])?></small></span></header><p><?=nl2br($esc($message['body']))?></p><?php if($message['attachments']):?><div class="msg-attachments"><?php foreach($message['attachments'] as $attachment):?><a href="<?=$url('/messages/attachment?id='.(int)$attachment['id'])?>"><span>▣</span><div><b><?=$esc((string)$attachment['original_name'])?></b><small><?=StorageService::humanSize((int)$attachment['byte_size'])?> · <?=$esc(strtoupper((string)$attachment['extension']))?></small></div></a><?php endforeach;?></div><?php endif;?><time><?=$esc(date('g:i A',strtotime($message['created_at'])))?></time></article></div><?php endforeach;?></section></div><section class="comm-composer"><?php if($selectedConversation['send_allowed']):?><form method="post" enctype="multipart/form-data" action="<?=$url('/messages/thread?id='.(int)$selectedConversation['id'])?>"><input type="hidden" name="csrf_token" value="<?=$esc((string)$_SESSION['communication_csrf'])?>"><input type="hidden" name="action" value="send_message"><input type="hidden" name="conversation_id" value="<?=(int)$selectedConversation['id']?>"><label class="ux-visually-hidden" for="message-body">Reply to this conversation</label><textarea id="message-body" name="body" rows="1" maxlength="4000" required placeholder="Message" data-auto-grow></textarea><div class="comm-compose-actions"><label class="comm-attach-compact" title="Add attachment">+<input type="file" name="attachment" aria-label="Attach a file to your message" data-file-input></label><span class="comm-file-name" role="status" data-file-name></span><button class="button" type="submit">Send</button></div></form><?php else:?><div class="comm-send-blocked"><b>Sending is paused</b><p><?=$esc((string)$selectedConversation['safety_issue'])?></p></div><?php endif;?></section><?php endif;?>
        <?php endif;?></div></main></div><script src="<?=$url('/assets/js/unified-navigation.js')?>"></script><script>document.querySelectorAll('[data-people-toggle]').forEach(function(btn){btn.addEventListener('click',function(){var box=document.querySelector('[data-participants]');if(!box)return;var collapsed=box.classList.toggle('collapsed');btn.setAttribute('aria-expanded',collapsed?'false':'true');btn.textContent=collapsed?'Who can see this':'Hide participants';});});document.querySelectorAll('[data-file-input]').forEach(function(input){input.addEventListener('change',function(){var form=input.closest('form');var label=form?form.querySelector('[data-file-name]'):null;if(label)label.textContent=input.files&&input.files[0]?input.files[0].name:'';});});document.querySelectorAll('[data-auto-grow]').forEach(function(area){var grow=function(){area.style.height='40px';area.style.height=Math.min(area.scrollHeight,116)+'px';};area.addEventListener('input',grow);grow();});</script></body></html><?php exit;
    }
}
