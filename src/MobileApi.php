<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/CommunicationReadStateService.php';
require_once __DIR__ . '/SafeguardedConversationPolicy.php';
require_once __DIR__ . '/ProductionContext.php';
require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/CalendarService.php';
require_once __DIR__ . '/VolunteerCoverageService.php';
require_once __DIR__ . '/PushService.php';
require_once __DIR__ . '/CommunicationExperience.php';
require_once __DIR__ . '/FormExperience.php';
require_once __DIR__ . '/VolunteerExperience.php';
require_once __DIR__ . '/VolunteerApprovalExperience.php';

final class MobileApi
{
    private const TOKEN_TTL_DAYS = 30;

    public static function handles(string $route): bool
    {
        return str_starts_with($route, '/api/mobile/');
    }

    public static function render(string $route): never
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        try {
            $db = Database::connect(dirname(__DIR__));
            if ($route === '/api/mobile/login') self::login($db);
            $user = self::bearerUser($db);
            if (!$user) self::json(['error' => 'Authentication required.'], 401);

            match ($route) {
                '/api/mobile/me' => self::json(['user' => self::publicUser($user)]),
                '/api/mobile/theatre' => self::theatre($db, $user),
                '/api/mobile/event' => self::eventDetail($db, $user),
                '/api/mobile/daily-tasks' => self::dailyTasks($db, $user),
                '/api/mobile/volunteer-signup' => self::volunteerSignup($db, $user),
                '/api/mobile/volunteer-cancel' => self::volunteerCancel($db, $user),
                '/api/mobile/volunteer-approval' => self::volunteerApproval($db, $user),
                '/api/mobile/form-detail' => self::formDetail($db, $user),
                '/api/mobile/form-submit' => self::formSubmit($db, $user),
                '/api/mobile/notifications' => self::notifications($db, $user),
                '/api/mobile/notification-read' => self::notificationRead($db, $user),
                '/api/mobile/messages' => self::messages($db, $user),
                '/api/mobile/message-thread' => self::messageThread($db, $user),
                '/api/mobile/message-send' => self::messageSend($db, $user),
                '/api/mobile/message-recipients' => self::messageRecipients($db, $user),
                '/api/mobile/message-create' => self::messageCreate($db, $user),
                '/api/mobile/channels' => self::channels($db, $user),
                '/api/mobile/channel' => self::channel($db, $user),
                '/api/mobile/channel-post' => self::channelPost($db, $user),
                '/api/mobile/push-subscription' => self::pushSubscription($db, $user),
                '/api/mobile/logout' => self::logout($db),
                default => self::json(['error' => 'Mobile API route not found.'], 404),
            };
        } catch (PDOException $e) {
            error_log('Mobile API database error: ' . $e->getMessage());
            self::json(['error' => 'The CTSMD service could not complete that request.'], 500);
        } catch (RateLimitExceeded $e) {
            header('Retry-After: '.$e->retryAfter);self::json(['error'=>$e->getMessage(),'retry_after'=>$e->retryAfter],429);
        } catch (RuntimeException $e) {
            self::json(['error' => $e->getMessage()], 400);
        } catch (Throwable $e) {
            error_log('Mobile API error: ' . $e->getMessage());
            self::json(['error' => 'The CTSMD service could not complete that request.'], 500);
        }
    }

    private static function login(PDO $db): never
    {
        self::requireMethod('POST');
        $input = self::input();
        $email = mb_strtolower(trim((string)($input['email'] ?? '')));
        $password = (string)($input['password'] ?? '');
        if ($email === '' || $password === '') throw new RuntimeException('Enter your email and password.');
        $rateSubject=RateLimiter::requestSubject($email);RateLimiter::enforce($db,'mobile-login',$rateSubject,8,900,900);

        $stmt = $db->prepare("SELECT id,password_hash,active,account_status FROM users WHERE LOWER(email)=:email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        if (!$row || !(bool)$row['active'] || $row['account_status'] !== 'active' || empty($row['password_hash']) || !password_verify($password, (string)$row['password_hash'])) {
            self::json(['error' => 'Email or password was not recognized.'], 401);
        }

        $userId = (int)$row['id'];
        RateLimiter::clear($db,'mobile-login',$rateSubject);
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = (new DateTimeImmutable('+' . self::TOKEN_TTL_DAYS . ' days'))->format('Y-m-d H:i:s');
        $db->prepare("DELETE FROM auth_mobile_tokens WHERE user_id=:user AND (expires_at<CURRENT_TIMESTAMP OR revoked_at IS NOT NULL)")->execute(['user' => $userId]);
        $db->prepare("INSERT INTO auth_mobile_tokens (user_id,token_hash,expires_at,created_at,last_used_at) VALUES (:user,:hash,:expires,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")->execute(['user'=>$userId,'hash'=>$hash,'expires'=>$expires]);
        $db->prepare('UPDATE users SET last_login_at=CURRENT_TIMESTAMP WHERE id=:id')->execute(['id'=>$userId]);
        $user = self::loadUser($db, $userId);
        self::json(['token'=>$token,'expires_at'=>$expires,'user'=>self::publicUser($user)]);
    }

    private static function theatre(PDO $db, array $user): never
    {
        self::requireMethod('GET');
        // Reuse the web platform's audience filters. Never expose an unrestricted
        // schedule or rely on filtering events in the mobile client.
        $productions=ProductionContext::activeProductions($db,$user);
        $events=CalendarService::visibleEvents(
            $db,$user,new DateTimeImmutable('-1 day'),new DateTimeImmutable('+90 days')
        );
        self::json(['productions'=>$productions,'events'=>array_slice($events,0,100)]);
    }

    private static function eventDetail(PDO $db,array $user):never
    {
        self::requireMethod('GET');
        $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
        if(!$id||$id<1)self::json(['error'=>'An event ID is required.'],400);
        // Narrow the existing audience-filtered calendar query to this event's date.
        // A known event ID alone never gives access to a restricted production event.
        $s=$db->prepare("SELECT starts_at FROM schedule_items WHERE id=:id LIMIT 1");
        $s->execute(['id'=>$id]);$start=$s->fetchColumn();
        if(!$start)self::json(['error'=>'Event not found.'],404);
        $day=new DateTimeImmutable((string)$start);
        $events=CalendarService::visibleEvents($db,$user,$day->modify('-1 day'),$day->modify('+1 day'));
        foreach($events as $event){
            if((int)$event['id']===$id)self::json(['event'=>$event]);
        }
        self::json(['error'=>'Event not found.'],404);
    }

    private static function volunteerApproval(PDO $db,array $user):never
    {
        self::requireMethod('POST');
        $input=self::input();
        $action=(string)($input['action']??'');
        if($action==='request'){
            $id=filter_var($input['shift_id']??null,FILTER_VALIDATE_INT);
            if(!$id||$id<1)self::json(['error'=>'A valid volunteer shift is required.'],400);
            $note=$input['note']??'';
            if(!is_string($note))self::json(['error'=>'Invalid request note.'],400);
            VolunteerApprovalExperience::requestApproval($db,$user,(int)$id,trim($note));
        }elseif($action==='withdraw'){
            $id=filter_var($input['request_id']??null,FILTER_VALIDATE_INT);
            if(!$id||$id<1)self::json(['error'=>'A valid approval request is required.'],400);
            VolunteerApprovalExperience::withdrawRequest($db,$user,(int)$id);
        }else self::json(['error'=>'Unsupported approval action.'],400);
        self::json(['ok'=>true]);
    }

    private static function volunteerCancel(PDO $db,array $user):never
    {
        self::requireMethod('POST');
        $input=self::input();
        $shift=filter_var($input['shift_id']??null,FILTER_VALIDATE_INT);
        if(!$shift||$shift<1)self::json(['error'=>'A valid volunteer shift is required.'],400);
        // Shared web cancellation guard rejects started shifts, checked-in
        // attendance, completed service and other users' commitments.
        VolunteerExperience::cancelSignup($db,(int)$user['id'],(int)$shift);
        self::json(['ok'=>true]);
    }

    private static function volunteerSignup(PDO $db,array $user):never
    {
        self::requireMethod('POST');
        $input=self::input();
        $shift=filter_var($input['shift_id']??null,FILTER_VALIDATE_INT);
        if(!$shift||$shift<1)self::json(['error'=>'A valid volunteer shift is required.'],400);
        // Reuse the web signup transaction: requirements, active volunteer status,
        // approval gate, shift start, live capacity and duplicate signup checks.
        VolunteerExperience::signup($db,(int)$user['id'],(int)$shift);
        self::json(['ok'=>true]);
    }

    private static function formSubmit(PDO $db,array $user):never
    {
        self::requireMethod('POST');
        $input=self::input();
        $id=filter_var($input['assignment_id']??null,FILTER_VALIDATE_INT);
        if(!$id||$id<1)self::json(['error'=>'A valid form assignment is required.'],400);
        // The web and mobile entry points use the exact same transaction,
        // assignee check, completion rules, review workflow and audit trail.
        $payload=[];
        if(($input['acknowledgment']??false)===true)$payload['acknowledgment']='1';
        $payload['typed_signature']=is_string($input['typed_signature']??null)?$input['typed_signature']:'';
        $payload['response_text']=is_string($input['response_text']??null)?$input['response_text']:'';
        FormExperience::submit($db,$user,(int)$id,$payload);
        self::json(['ok'=>true]);
    }

    private static function formDetail(PDO $db, array $user): never
    {
        self::requireMethod('GET');
        $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
        if(!$id||$id<1) self::json(['error'=>'A form assignment ID is required.'],400);
        // Match the web Forms page: only the authenticated assignee can read
        // an active assignment. Do not expose arbitrary form definitions.
        $s=$db->prepare("SELECT fa.id,fa.status,fa.due_at,fa.completed_at,f.title,f.form_type,f.instructions,f.completion_mode,f.review_required,fs.status AS submission_status,fs.submitted_at,fs.reviewed_at,fs.reviewer_note FROM form_assignments fa JOIN forms f ON f.id=fa.form_id AND f.active=1 LEFT JOIN form_submissions fs ON fs.assignment_id=fa.id WHERE fa.id=:id AND fa.user_id=:user LIMIT 1");
        $s->execute(['id'=>$id,'user'=>(int)$user['id']]);
        $assignment=$s->fetch(PDO::FETCH_ASSOC);
        if(!$assignment) self::json(['error'=>'This form is not available to your account.'],404);
        self::json(['form'=>$assignment]);
    }

    private static function dailyTasks(PDO $db, array $user): never
    {
        self::requireMethod('GET');
        $id=(int)$user['id'];
        // Same assignee + active form boundary used by the member Forms page.
        $forms=$db->prepare("SELECT fa.id,fa.status,fa.due_at,f.title,fs.status submission_status FROM form_assignments fa JOIN forms f ON f.id=fa.form_id AND f.active=1 LEFT JOIN form_submissions fs ON fs.assignment_id=fa.id WHERE fa.user_id=:user ORDER BY fa.due_at,fa.id LIMIT 100");
        $forms->execute(['user'=>$id]);
        $live=VolunteerCoverageService::isLiveVolunteer($db,$id);
        $rows=[];
        if($live){
        $shifts=$db->prepare("SELECT vs.id,vs.title,vs.category,vs.starts_at,vs.ends_at,vs.location,vs.required_slots,vs.approval_required,mine.status my_status,req.id my_request_id,req.status my_request_status FROM volunteer_shifts vs LEFT JOIN volunteer_shift_signups mine ON mine.shift_id=vs.id AND mine.user_id=:mine LEFT JOIN volunteer_shift_approval_requests req ON req.shift_id=vs.id AND req.user_id=:request WHERE vs.starts_at>NOW() AND (vs.production_id IS NULL OR EXISTS(SELECT 1 FROM productions p WHERE p.id=vs.production_id AND p.is_active=1)) ORDER BY vs.starts_at,vs.id LIMIT 100");
        $shifts->execute(['mine'=>$id,'request'=>$id]);
        $rows=$shifts->fetchAll();
        $missingMap=VolunteerCoverageService::missingRequirementsForShifts($db,$id,array_column($rows,'id'));
        foreach($rows as &$row){
            $row['missing_requirements']=$missingMap[(int)$row['id']]??[];
            $row['eligible']=$live && !$row['missing_requirements'];
            $row['open_slots']=max(0,(int)$row['required_slots']-VolunteerCoverageService::eligibleLiveSignupCount($db,(int)$row['id']));
        }
        unset($row);
        }
        self::json(['forms'=>$forms->fetchAll(),'shifts'=>$rows,'volunteer_enabled'=>$live]);
    }

    private static function notifications(PDO $db,array $user):never
    {
        self::requireMethod('GET');
        $s=$db->prepare("SELECT id,title,body,action_path,read_at,created_at FROM app_notifications WHERE recipient_user_id=:user ORDER BY created_at DESC,id DESC LIMIT 75");
        $s->execute(['user'=>(int)$user['id']]);
        $items=$s->fetchAll();
        // Navigation is a hint only: target pages still enforce their own authorization.
        // Never let stored notification paths navigate outside the CTSMD site.
        foreach($items as &$item){
            $path=(string)($item['action_path']??'');
            if(!preg_match('~^/(?!/)[a-zA-Z0-9/_?=&%-]*$~D',$path))$item['action_path']=null;
        }
        unset($item);
        $count=$db->prepare("SELECT COUNT(*) FROM app_notifications WHERE recipient_user_id=:user AND read_at IS NULL");
        $count->execute(['user'=>(int)$user['id']]);
        self::json(['notifications'=>$items,'unread_count'=>(int)$count->fetchColumn()]);
    }

    private static function notificationRead(PDO $db,array $user):never
    {
        self::requireMethod('POST');
        $input=self::input();$id=(int)($input['notification_id']??0);
        if($id<1)throw new RuntimeException('A notification is required.');
        $s=$db->prepare("UPDATE app_notifications SET read_at=COALESCE(read_at,CURRENT_TIMESTAMP) WHERE id=:id AND recipient_user_id=:user");
        $s->execute(['id'=>$id,'user'=>(int)$user['id']]);
        self::json(['ok'=>true]);
    }

    private static function messages(PDO $db, array $user): never
    {
        self::requireMethod('GET');
        $stmt = $db->prepare("SELECT c.id,c.subject,c.conversation_type,MAX(m.created_at) latest_at,COUNT(DISTINCT m.id) message_count,COUNT(DISTINCT cp.user_id) participant_count,(SELECT COUNT(*) FROM messages um WHERE um.conversation_id=c.id AND um.hidden_at IS NULL AND um.id>mine.last_read_message_id AND um.sender_user_id<>mine.user_id AND (mine.history_through_message_id IS NULL OR um.id<=mine.history_through_message_id)) unread_count,(SELECT sm.body FROM messages sm WHERE sm.conversation_id=c.id AND sm.hidden_at IS NULL AND (mine.history_through_message_id IS NULL OR sm.id<=mine.history_through_message_id) ORDER BY sm.id DESC LIMIT 1) preview FROM conversations c JOIN conversation_participants mine ON mine.conversation_id=c.id AND mine.user_id=:user JOIN conversation_participants cp ON cp.conversation_id=c.id LEFT JOIN messages m ON m.conversation_id=c.id AND m.hidden_at IS NULL AND (mine.history_through_message_id IS NULL OR m.id<=mine.history_through_message_id) GROUP BY c.id,c.subject,c.conversation_type,mine.last_read_message_id,mine.user_id,mine.history_through_message_id ORDER BY latest_at DESC,c.id DESC");
        $stmt->execute(['user'=>(int)$user['id']]);
        $rows=$stmt->fetchAll();$names=$db->prepare("SELECT cp.user_id,cp.participant_role,CONCAT(u.first_name,' ',u.last_name) name FROM conversation_participants cp JOIN users u ON u.id=cp.user_id WHERE cp.conversation_id=:id");foreach($rows as &$row){$names->execute(['id'=>$row['id']]);$people=$names->fetchAll();$visible=array_values(array_map(static fn(array $p):string=>(string)$p['name'],array_filter($people,static fn(array $p):bool=>(int)$p['user_id']!==(int)$user['id']&&$p['participant_role']!=='guardian')));$row['display_name']=$row['conversation_type']==='group'?($row['subject']?:'Group conversation'):(implode(', ',$visible)?:$row['subject']);}unset($row);self::json(['conversations'=>$rows]);
    }

    private static function messageThread(PDO $db, array $user): never
    {
        self::requireMethod('GET');
        $id = (int)($_GET['id'] ?? 0);
        if ($id < 1) throw new RuntimeException('Conversation id is required.');
        $access = $db->prepare("SELECT c.id,c.subject,c.conversation_type,cp.history_through_message_id,cp.access_revoked_at FROM conversations c JOIN conversation_participants cp ON cp.conversation_id=c.id AND cp.user_id=:user WHERE c.id=:id LIMIT 1");
        $access->execute(['user'=>(int)$user['id'],'id'=>$id]);
        $conversation = $access->fetch();
        if (!$conversation) self::json(['error'=>'Conversation not found.'],404);

        $p=$db->prepare("SELECT cp.user_id,cp.participant_role,cp.guardian_required,cp.last_read_message_id,CONCAT(u.first_name,' ',u.last_name) name,u.display_role role,u.initials FROM conversation_participants cp JOIN users u ON u.id=cp.user_id WHERE cp.conversation_id=:id ORDER BY cp.user_id");
        $p->execute(['id'=>$id]);
        $m=$db->prepare("SELECT m.id,m.body,m.created_at,m.sender_user_id,CONCAT(u.first_name,' ',u.last_name) sender,u.display_role sender_role,u.initials FROM messages m JOIN users u ON u.id=m.sender_user_id WHERE m.conversation_id=:id AND m.hidden_at IS NULL AND (:cutoff IS NULL OR m.id<=:cutoff2) ORDER BY m.created_at,m.id");
        $m->execute(['id'=>$id,'cutoff'=>$conversation['history_through_message_id'],'cutoff2'=>$conversation['history_through_message_id']]);
        CommunicationReadStateService::markConversationRead($db,(int)$user['id'],$id);
        $people=$p->fetchAll();$visible=array_values(array_map(static fn(array $person):string=>(string)$person['name'],array_filter($people,static fn(array $person):bool=>(int)$person['user_id']!==(int)$user['id']&&$person['participant_role']!=='guardian')));$conversation['display_name']=$conversation['conversation_type']==='group'?($conversation['subject']?:'Group conversation'):(implode(', ',$visible)?:$conversation['subject']);self::json(['conversation'=>$conversation,'participants'=>$people,'messages'=>$m->fetchAll()]);
    }

    private static function messageRecipients(PDO $db,array $user):never
    {
        self::requireMethod('GET');
        self::json(['recipients'=>CommunicationExperience::mobileRecipients($db,$user)]);
    }

    private static function messageCreate(PDO $db,array $user):never
    {
        self::requireMethod('POST');
        $input=self::input();
        $recipientId=(int)($input['recipient_id']??0);
        $subject=''; // Direct messages are identified by participants, not a user-entered subject.
        $body=trim((string)($input['body']??''));
        $id=CommunicationExperience::mobileCreateConversation($db,$user,$recipientId,$subject,$body);
        self::json(['ok'=>true,'conversation_id'=>$id]);
    }

    private static function messageSend(PDO $db, array $user): never
    {
        self::requireMethod('POST');
        $input=self::input();$id=(int)($input['conversation_id']??0);$body=trim((string)($input['body']??''));
        if($id<1||$body===''||mb_strlen($body)>4000)throw new RuntimeException('Choose a conversation and write a message up to 4,000 characters.');
        $db->beginTransaction();
        try{
            SafeguardedConversationPolicy::enforceForSend($db,$id,(int)$user['id']);
            $insert=$db->prepare("INSERT INTO messages (conversation_id,sender_user_id,body,created_at) VALUES (:conversation,:sender,:body,CURRENT_TIMESTAMP)");
            $insert->execute(['conversation'=>$id,'sender'=>(int)$user['id'],'body'=>$body]);
            $messageId=(int)$db->lastInsertId();
            CommunicationReadStateService::markConversationThroughMessage($db,(int)$user['id'],$id,$messageId);
            $db->commit();
            self::json(['ok'=>true,'message_id'=>$messageId]);
        }catch(Throwable $e){
            if($db->inTransaction())$db->rollBack();
            throw $e instanceof RuntimeException?$e:new RuntimeException('We could not send that message. Please try again.');
        }
    }

    private static function channels(PDO $db,array $user): never
    {
        self::requireMethod('GET');
        $rows=$db->query("SELECT c.id,c.name,c.description,c.channel_type,c.production_id,p.title production_title FROM channels c LEFT JOIN productions p ON p.id=c.production_id WHERE c.archived_at IS NULL ORDER BY COALESCE(p.title,''),c.name")->fetchAll();
        $unread=CommunicationReadStateService::communityUnread($db,$user);$visible=[];
        foreach($rows as $row){if(!CommunicationReadStateService::canAccessChannel($db,$user,(int)$row['id']))continue;$row['unread_count']=(int)($unread['channels'][(int)$row['id']]??0);$visible[]=$row;}
        self::json(['channels'=>$visible]);
    }

    private static function channel(PDO $db,array $user): never
    {
        self::requireMethod('GET');$id=(int)($_GET['id']??0);
        if($id<1||!CommunicationReadStateService::canAccessChannel($db,$user,$id))self::json(['error'=>'Channel not found.'],404);
        $c=$db->prepare("SELECT c.id,c.name,c.description,c.channel_type,c.production_id,c.post_scope,p.title production_title FROM channels c LEFT JOIN productions p ON p.id=c.production_id WHERE c.id=:id AND c.archived_at IS NULL LIMIT 1");$c->execute(['id'=>$id]);$channel=$c->fetch();
        $p=$db->prepare("SELECT cp.id,cp.body,cp.created_at,cp.author_user_id,cp.pinned,CONCAT(u.first_name,' ',u.last_name) author,u.display_role author_role,u.initials FROM channel_posts cp JOIN users u ON u.id=cp.author_user_id WHERE cp.channel_id=:id AND cp.moderation_status='published' AND cp.hidden_at IS NULL AND cp.deleted_at IS NULL ORDER BY cp.pinned DESC,cp.created_at,cp.id");$p->execute(['id'=>$id]);
        CommunicationReadStateService::markChannelRead($db,(int)$user['id'],$id);
        self::json(['channel'=>$channel,'posts'=>$p->fetchAll()]);
    }

    private static function channelPost(PDO $db,array $user): never
    {
        self::requireMethod('POST');$input=self::input();$id=(int)($input['channel_id']??0);$body=trim((string)($input['body']??''));
        if($id<1||$body===''||mb_strlen($body)>5000)throw new RuntimeException('Choose a channel and write a post up to 5,000 characters.');
        if(!CommunicationReadStateService::canAccessChannel($db,$user,$id))self::json(['error'=>'Channel not found.'],404);
        // Mobile v1 deliberately restricts posting to staff. The existing web Community path remains authoritative for nuanced audience/moderation rules.
        if(!AccessPolicy::isStaff($user))self::json(['error'=>'Posting from the mobile app is not enabled for this account yet.'],403);
        $db->prepare("INSERT INTO channel_posts (channel_id,author_user_id,body,moderation_status,created_at) VALUES (:channel,:author,:body,'published',CURRENT_TIMESTAMP)")->execute(['channel'=>$id,'author'=>(int)$user['id'],'body'=>$body]);
        self::json(['ok'=>true,'post_id'=>(int)$db->lastInsertId()]);
    }

    private static function logout(PDO $db): never
    {
        self::requireMethod('POST');
        $token=self::bearerToken();
        $input=self::input();
        $identity=trim((string)($input['push_identity']??''));
        if($token){
            // Only remove the calling device's registered push identity; other devices remain signed in.
            if($identity!==''&&strlen($identity)<=2048){
                $user=self::bearerUser($db);
                if($user){
                    $db->prepare('DELETE FROM mobile_push_subscriptions WHERE user_id=:user AND identity=:identity')->execute(['user'=>(int)$user['id'],'identity'=>$identity]);
                    if(str_starts_with($identity,'https://'))PushService::unsubscribe($db,(int)$user['id'],$identity);
                }
            }
            $db->prepare('UPDATE auth_mobile_tokens SET revoked_at=CURRENT_TIMESTAMP WHERE token_hash=:hash')->execute(['hash'=>hash('sha256',$token)]);
        }
        self::json(['ok'=>true]);
    }

    private static function pushSubscription(PDO $db,array $user): never
    {
        $userId=(int)$user['id'];
        $method=$_SERVER['REQUEST_METHOD']??'GET';
        if($method==='POST'){
            $input=self::input();
            $sub=$input['subscription']??null;
            if(!is_array($sub))throw new RuntimeException('Push subscription is required.');
            $identity=trim((string)($sub['endpoint']??$sub['fcmToken']??$sub['apnsToken']??''));
            if($identity==='')throw new RuntimeException('Push subscription identity is missing.');
            $previous=trim((string)($input['previous_identity']??''));
            // A standards-based browser Web Push subscription has endpoint + encryption keys.
            // Native APNs/FCM tokens are NOT Web Push endpoints and need a distinct sender.
            $isWeb=isset($sub['endpoint']);
            if($isWeb){
                PushService::subscribe($db,$userId,$sub,(string)($_SERVER['HTTP_USER_AGENT']??''));
            }elseif(!isset($sub['fcmToken'])&&!isset($sub['apnsToken'])){
                throw new RuntimeException('Unsupported push subscription type.');
            }
            if($previous!==''&&$previous!==$identity){
                $db->prepare("DELETE FROM mobile_push_subscriptions WHERE user_id=:user AND identity=:identity")->execute(['user'=>$userId,'identity'=>$previous]);
                if(str_starts_with($previous,'https://'))PushService::unsubscribe($db,$userId,$previous);
            }
            $json=json_encode($sub,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
            $stmt=$db->prepare("INSERT INTO mobile_push_subscriptions (user_id,identity,subscription_json,created_at,updated_at) VALUES (:user,:identity,:json,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),subscription_json=VALUES(subscription_json),updated_at=CURRENT_TIMESTAMP");
            $stmt->execute(['user'=>$userId,'identity'=>$identity,'json'=>$json]);
            self::json(['ok'=>true,'delivery_type'=>$isWeb?'web_push':'native_pending']);
        }
        if($method==='DELETE'){
            $identity=trim((string)($_GET['identity']??''));
            if($identity!==''){
                $db->prepare("DELETE FROM mobile_push_subscriptions WHERE user_id=:user AND identity=:identity")->execute(['user'=>$userId,'identity'=>$identity]);
                if(str_starts_with($identity,'https://'))PushService::unsubscribe($db,$userId,$identity);
            }else{
                $stmt=$db->prepare("SELECT identity FROM mobile_push_subscriptions WHERE user_id=:user");
                $stmt->execute(['user'=>$userId]);
                foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $old){
                    if(str_starts_with((string)$old,'https://'))PushService::unsubscribe($db,$userId,(string)$old);
                }
                $db->prepare("DELETE FROM mobile_push_subscriptions WHERE user_id=:user")->execute(['user'=>$userId]);
            }
            self::json(['ok'=>true]);
        }
        self::json(['error'=>'Method not allowed.'],405);
    }

    private static function bearerUser(PDO $db): ?array
    {
        $token=self::bearerToken();if(!$token||!preg_match('/^[a-f0-9]{64}$/',$token))return null;
        $stmt=$db->prepare("SELECT t.user_id FROM auth_mobile_tokens t JOIN users u ON u.id=t.user_id AND u.active=1 AND u.account_status='active' WHERE t.token_hash=:hash AND t.revoked_at IS NULL AND t.expires_at>CURRENT_TIMESTAMP LIMIT 1");$stmt->execute(['hash'=>hash('sha256',$token)]);$id=(int)($stmt->fetchColumn()?:0);if($id<1)return null;
        $db->prepare('UPDATE auth_mobile_tokens SET last_used_at=CURRENT_TIMESTAMP WHERE token_hash=:hash')->execute(['hash'=>hash('sha256',$token)]);
        return self::loadUser($db,$id);
    }

    private static function loadUser(PDO $db,int $id): array
    {
        $s=$db->prepare("SELECT id,first_name,last_name,email,initials,display_role role,organization_membership_status FROM users WHERE id=:id AND active=1 AND account_status='active' LIMIT 1");$s->execute(['id'=>$id]);$u=$s->fetch();if(!$u)throw new RuntimeException('This account is unavailable.');$u['name']=trim($u['first_name'].' '.$u['last_name']);$u['roles']=Auth::roles($db,$id);$u['permissions']=Auth::permissions($db,$id);return $u;
    }

    private static function publicUser(array $u): array{return ['id'=>(int)$u['id'],'name'=>$u['name'],'first_name'=>$u['first_name'],'last_name'=>$u['last_name'],'email'=>$u['email'],'initials'=>$u['initials'],'role'=>$u['role'],'roles'=>$u['roles']];}
    private static function bearerToken(): ?string{$h=(string)($_SERVER['HTTP_AUTHORIZATION']??'');return preg_match('/^Bearer\s+(.+)$/i',$h,$m)?trim($m[1]):null;}
    private static function input(): array{$raw=file_get_contents('php://input')?:'';$data=json_decode($raw,true);return is_array($data)?$data:$_POST;}
    private static function requireMethod(string $method): void{if(($_SERVER['REQUEST_METHOD']??'GET')!==$method)self::json(['error'=>'Method not allowed.'],405);}

    private static function json(array $data,int $status=200): never{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;}
}
