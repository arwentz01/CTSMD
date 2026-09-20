<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/CommunicationReadStateService.php';
require_once __DIR__ . '/ProductionContext.php';

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
        $db = Database::connect(dirname(__DIR__));

        try {
            if ($route === '/api/mobile/login') self::login($db);
            $user = self::bearerUser($db);
            if (!$user) self::json(['error' => 'Authentication required.'], 401);

            match ($route) {
                '/api/mobile/me' => self::json(['user' => self::publicUser($user)]),
                '/api/mobile/messages' => self::messages($db, $user),
                '/api/mobile/message-thread' => self::messageThread($db, $user),
                '/api/mobile/message-send' => self::messageSend($db, $user),
                '/api/mobile/channels' => self::channels($db, $user),
                '/api/mobile/channel' => self::channel($db, $user),
                '/api/mobile/channel-post' => self::channelPost($db, $user),
                '/api/mobile/logout' => self::logout($db),
                default => self::json(['error' => 'Mobile API route not found.'], 404),
            };
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

        $stmt = $db->prepare("SELECT id,password_hash,active,account_status FROM users WHERE LOWER(email)=:email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        if (!$row || !(bool)$row['active'] || $row['account_status'] !== 'active' || empty($row['password_hash']) || !password_verify($password, (string)$row['password_hash'])) {
            self::json(['error' => 'Email or password was not recognized.'], 401);
        }

        $userId = (int)$row['id'];
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = (new DateTimeImmutable('+' . self::TOKEN_TTL_DAYS . ' days'))->format('Y-m-d H:i:s');
        $db->prepare("DELETE FROM auth_mobile_tokens WHERE user_id=:user AND (expires_at<CURRENT_TIMESTAMP OR revoked_at IS NOT NULL)")->execute(['user' => $userId]);
        $db->prepare("INSERT INTO auth_mobile_tokens (user_id,token_hash,expires_at,created_at,last_used_at) VALUES (:user,:hash,:expires,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")->execute(['user'=>$userId,'hash'=>$hash,'expires'=>$expires]);
        $db->prepare('UPDATE users SET last_login_at=CURRENT_TIMESTAMP WHERE id=:id')->execute(['id'=>$userId]);
        $user = self::loadUser($db, $userId);
        self::json(['token'=>$token,'expires_at'=>$expires,'user'=>self::publicUser($user)]);
    }

    private static function messages(PDO $db, array $user): never
    {
        self::requireMethod('GET');
        $stmt = $db->prepare("SELECT c.id,c.subject,c.conversation_type,MAX(m.created_at) latest_at,COUNT(DISTINCT m.id) message_count,COUNT(DISTINCT cp.user_id) participant_count,(SELECT COUNT(*) FROM messages um WHERE um.conversation_id=c.id AND um.hidden_at IS NULL AND um.id>mine.last_read_message_id AND um.sender_user_id<>mine.user_id) unread_count,(SELECT sm.body FROM messages sm WHERE sm.conversation_id=c.id AND sm.hidden_at IS NULL ORDER BY sm.id DESC LIMIT 1) preview FROM conversations c JOIN conversation_participants mine ON mine.conversation_id=c.id AND mine.user_id=:user JOIN conversation_participants cp ON cp.conversation_id=c.id LEFT JOIN messages m ON m.conversation_id=c.id AND m.hidden_at IS NULL GROUP BY c.id,c.subject,c.conversation_type,mine.last_read_message_id,mine.user_id ORDER BY latest_at DESC,c.id DESC");
        $stmt->execute(['user'=>(int)$user['id']]);
        self::json(['conversations'=>$stmt->fetchAll()]);
    }

    private static function messageThread(PDO $db, array $user): never
    {
        self::requireMethod('GET');
        $id = (int)($_GET['id'] ?? 0);
        if ($id < 1) throw new RuntimeException('Conversation id is required.');
        $access = $db->prepare("SELECT c.id,c.subject,c.conversation_type FROM conversations c JOIN conversation_participants cp ON cp.conversation_id=c.id AND cp.user_id=:user WHERE c.id=:id LIMIT 1");
        $access->execute(['user'=>(int)$user['id'],'id'=>$id]);
        $conversation = $access->fetch();
        if (!$conversation) self::json(['error'=>'Conversation not found.'],404);

        $p=$db->prepare("SELECT cp.user_id,cp.participant_role,cp.guardian_required,CONCAT(u.first_name,' ',u.last_name) name,u.display_role role,u.initials FROM conversation_participants cp JOIN users u ON u.id=cp.user_id WHERE cp.conversation_id=:id ORDER BY cp.id");
        $p->execute(['id'=>$id]);
        $m=$db->prepare("SELECT m.id,m.body,m.created_at,m.sender_user_id,CONCAT(u.first_name,' ',u.last_name) sender,u.display_role sender_role,u.initials FROM messages m JOIN users u ON u.id=m.sender_user_id WHERE m.conversation_id=:id AND m.hidden_at IS NULL ORDER BY m.created_at,m.id");
        $m->execute(['id'=>$id]);
        CommunicationReadStateService::markConversationRead($db,(int)$user['id'],$id);
        self::json(['conversation'=>$conversation,'participants'=>$p->fetchAll(),'messages'=>$m->fetchAll()]);
    }

    private static function messageSend(PDO $db, array $user): never
    {
        self::requireMethod('POST');
        $input=self::input();$id=(int)($input['conversation_id']??0);$body=trim((string)($input['body']??''));
        if($id<1||$body===''||mb_strlen($body)>4000)throw new RuntimeException('Choose a conversation and write a message up to 4,000 characters.');
        $membership=$db->prepare("SELECT c.conversation_type FROM conversations c JOIN conversation_participants cp ON cp.conversation_id=c.id AND cp.user_id=:user WHERE c.id=:id LIMIT 1");
        $membership->execute(['user'=>(int)$user['id'],'id'=>$id]);$row=$membership->fetch();if(!$row)self::json(['error'=>'Conversation not found.'],404);
        if((string)$row['conversation_type']==='safeguarded')self::assertSafeguarded($db,$id);
        $db->prepare("INSERT INTO messages (conversation_id,sender_user_id,body,created_at) VALUES (:conversation,:sender,:body,CURRENT_TIMESTAMP)")->execute(['conversation'=>$id,'sender'=>(int)$user['id'],'body'=>$body]);
        $messageId=(int)$db->lastInsertId();CommunicationReadStateService::markConversationThroughMessage($db,(int)$user['id'],$id,$messageId);
        self::json(['ok'=>true,'message_id'=>$messageId]);
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
        self::requireMethod('POST');$token=self::bearerToken();if($token){$db->prepare('UPDATE auth_mobile_tokens SET revoked_at=CURRENT_TIMESTAMP WHERE token_hash=:hash')->execute(['hash'=>hash('sha256',$token)]);}self::json(['ok'=>true]);
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

    private static function assertSafeguarded(PDO $db,int $conversationId): void
    {
        $s=$db->prepare("SELECT cp.user_id,cp.participant_role,u.active,u.account_status FROM conversation_participants cp JOIN users u ON u.id=cp.user_id WHERE cp.conversation_id=:id");$s->execute(['id'=>$conversationId]);$rows=$s->fetchAll();
        $students=[];$guardians=[];$staff=false;
        foreach($rows as $r){if(!(bool)$r['active']||$r['account_status']==='disabled')continue;$roles=Auth::roles($db,(int)$r['user_id']);if(in_array('student',$roles,true))$students[]=(int)$r['user_id'];if($r['participant_role']==='guardian')$guardians[]=(int)$r['user_id'];if(in_array('production_staff',$roles,true)||in_array('administrator',$roles,true))$staff=true;}
        if(count(array_unique($students))!==1||!$guardians||!$staff)throw new RuntimeException('Messaging is paused because this safeguarded conversation requires review.');
        $ph=implode(',',array_fill(0,count($guardians),'?'));$q=$db->prepare("SELECT 1 FROM family_relationships WHERE student_user_id=? AND guardian_user_id IN ($ph) AND status='active' LIMIT 1");$q->execute(array_merge([$students[0]],$guardians));if(!$q->fetchColumn())throw new RuntimeException('Messaging is paused because the guardian relationship requires review.');
    }

    private static function json(array $data,int $status=200): never{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;}
}
