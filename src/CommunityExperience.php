<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/ProductionContext.php';

final class CommunityExperience
{
    private const ROUTES = ['/channels', '/channels/view'];

    public static function handles(string $route): bool { return in_array($route, self::ROUTES, true); }

    public static function render(string $route, string $basePath): never
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $db = Database::connect(dirname(__DIR__));
        $user = self::currentUser($db);
        $_SESSION['community_csrf'] ??= bin2hex(random_bytes(24));
        if ($_SERVER['REQUEST_METHOD'] === 'POST') self::handlePost($db, $user, $basePath);

        $channels = self::channels($db, $user);
        $selected = null;
        if ($route === '/channels/view') {
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
            $selected = self::channel($db, $user, (int)$id);
        }
        self::page($route, $basePath, $user, $channels, $selected);
    }

    private static function handlePost(PDO $db, array $user, string $basePath): never
    {
        if (!hash_equals((string)($_SESSION['community_csrf'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
            self::flash('error', 'Your session token expired. Please try again.');
            self::redirect($basePath . '/channels');
        }
        $channelId = filter_input(INPUT_POST, 'channel_id', FILTER_VALIDATE_INT) ?: 0;
        $body = trim((string)($_POST['body'] ?? ''));
        try {
            self::createPost($db, $user, (int)$channelId, $body);
            self::flash('success', 'Post published to the channel.');
        } catch (RuntimeException $e) {
            self::flash('error', $e->getMessage());
        }
        self::redirect($basePath . '/channels/view?id=' . (int)$channelId);
    }

    private static function createPost(PDO $db, array $user, int $channelId, string $body): void
    {
        if ($channelId < 1) throw new RuntimeException('That channel could not be found.');
        if ($body === '' || mb_strlen($body) > 5000) throw new RuntimeException('Write a post up to 5,000 characters.');
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT c.id,c.production_id,c.name,c.read_scope,c.post_scope,c.read_audiences_json,c.post_audiences_json,c.access_mode,c.archived_at,p.is_active production_active FROM channels c LEFT JOIN productions p ON p.id=c.production_id WHERE c.id=:id FOR UPDATE");
            $stmt->execute(['id'=>$channelId]);
            $channel = $stmt->fetch();
            if (!$channel || $channel['archived_at'] !== null) throw new RuntimeException('That channel is no longer available.');
            if (!self::canRead($db,$user,$channel)) throw new RuntimeException('You do not have access to that channel.');
            if (!self::canPost($db,$user,$channel)) throw new RuntimeException('This channel is read-only for your account.');
            $insert = $db->prepare('INSERT INTO channel_posts (channel_id,author_user_id,body,pinned,reactions_json,created_at) VALUES (:channel,:author,:body,0,NULL,CURRENT_TIMESTAMP)');
            $insert->execute(['channel'=>$channelId,'author'=>(int)$user['id'],'body'=>$body]);
            $postId = (int)$db->lastInsertId();
            self::audit($db,(int)$user['id'],'community.post_created','channel_post',$postId,'Published a community channel post.',['channel_id'=>$channelId,'channel_name'=>$channel['name']]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof RuntimeException) throw $e;
            throw new RuntimeException('The post could not be published.');
        }
    }

    private static function channels(PDO $db, array $user): array
    {
        $rows = $db->query("SELECT c.id,c.production_id,c.name,c.channel_type,c.description,c.read_scope,c.post_scope,c.read_audiences_json,c.post_audiences_json,c.access_mode,p.title production_title,p.is_active production_active,COUNT(cp.id) post_count,MAX(cp.created_at) latest_at FROM channels c LEFT JOIN productions p ON p.id=c.production_id LEFT JOIN channel_posts cp ON cp.channel_id=c.id WHERE c.archived_at IS NULL GROUP BY c.id,c.production_id,c.name,c.channel_type,c.description,c.read_scope,c.post_scope,c.read_audiences_json,c.post_audiences_json,c.access_mode,p.title,p.is_active,c.sort_order ORDER BY c.sort_order,c.name")->fetchAll();
        $visible=[];
        foreach($rows as $row){ if(self::canRead($db,$user,$row)){ $row['can_post']=self::canPost($db,$user,$row); $visible[]=$row; } }
        return $visible;
    }

    private static function channel(PDO $db, array $user, int $id): ?array
    {
        if($id<1) return null;
        $stmt=$db->prepare("SELECT c.id,c.production_id,c.name,c.channel_type,c.description,c.read_scope,c.post_scope,c.read_audiences_json,c.post_audiences_json,c.access_mode,p.title production_title,p.is_active production_active FROM channels c LEFT JOIN productions p ON p.id=c.production_id WHERE c.id=:id AND c.archived_at IS NULL LIMIT 1");
        $stmt->execute(['id'=>$id]); $channel=$stmt->fetch();
        if(!$channel || !self::canRead($db,$user,$channel)) return null;
        $channel['can_post']=self::canPost($db,$user,$channel);
        $posts=$db->prepare("SELECT cp.id,cp.body,cp.pinned,cp.reactions_json,cp.created_at,CONCAT(u.first_name,' ',u.last_name) author,u.display_role author_role,u.initials FROM channel_posts cp JOIN users u ON u.id=cp.author_user_id WHERE cp.channel_id=:id ORDER BY cp.pinned DESC,cp.created_at DESC,cp.id DESC");
        $posts->execute(['id'=>$id]); $channel['posts']=$posts->fetchAll(); return $channel;
    }

    private static function canRead(PDO $db,array $user,array $channel): bool
    {
        if(AccessPolicy::isStaff($user)) return true;
        return self::access($db,$user,$channel,'read');
    }

    private static function canPost(PDO $db,array $user,array $channel): bool
    {
        if(AccessPolicy::isStaff($user)) return true;
        return self::access($db,$user,$channel,'post');
    }

    private static function access(PDO $db,array $user,array $channel,string $mode): bool
    {
        $accessMode=(string)($channel['access_mode']??'audience');
        $audienceOk=self::matchesAnyAudience($db,$user,$channel,self::audiences($channel,$mode));
        $selectedOk=self::selectedAccess($db,(int)$channel['id'],(int)$user['id'],$mode);
        $teamOk=self::teamAccess($db,(int)$channel['id'],(int)$user['id'],$mode,$channel);
        return match($accessMode){
            'selected' => $selectedOk,
            'team' => $teamOk,
            'hybrid' => $audienceOk || $selectedOk || $teamOk,
            default => $audienceOk,
        };
    }

    private static function selectedAccess(PDO $db,int $channelId,int $userId,string $mode): bool
    {
        $column=$mode==='post'?'can_post':'can_read';
        $stmt=$db->prepare("SELECT $column FROM channel_members WHERE channel_id=:channel AND user_id=:user AND status='active' LIMIT 1");
        $stmt->execute(['channel'=>$channelId,'user'=>$userId]);
        return (bool)$stmt->fetchColumn();
    }

    private static function teamAccess(PDO $db,int $channelId,int $userId,string $mode,array $channel): bool
    {
        if(!empty($channel['production_id']) && !(bool)($channel['production_active']??false)) return false;
        $column=$mode==='post'?'ct.can_post':'ct.can_read';
        $stmt=$db->prepare("SELECT 1 FROM channel_teams ct JOIN teams t ON t.id=ct.team_id AND t.active=1 JOIN team_members tm ON tm.team_id=t.id AND tm.status='active' WHERE ct.channel_id=:channel AND tm.user_id=:user AND $column=1 LIMIT 1");
        $stmt->execute(['channel'=>$channelId,'user'=>$userId]);
        return (bool)$stmt->fetchColumn();
    }

    private static function audiences(array $channel,string $mode): array
    {
        $json=(string)($channel[$mode.'_audiences_json'] ?? '');
        if($json!==''){
            $decoded=json_decode($json,true);
            if(is_array($decoded) && $decoded) return array_values(array_unique(array_map('strval',$decoded)));
        }
        $legacy=(string)($channel[$mode.'_scope'] ?? 'staff');
        return [$legacy];
    }

    private static function matchesAnyAudience(PDO $db,array $user,array $channel,array $audiences): bool
    {
        $userId=(int)$user['id'];
        $productionId=(int)($channel['production_id'] ?? 0);
        $productionActive=$productionId>0 && (bool)($channel['production_active'] ?? false);
        $isStudent=AccessPolicy::isStudent($user);
        $isAdult=!$isStudent;
        $audienceType=$productionActive ? ProductionContext::audienceType($db,$userId,$productionId) : null;
        $activeProductionMember=$productionActive && $audienceType!==null;

        foreach($audiences as $audience){
            $ok=match($audience){
                'all_members' => true,
                'adults' => $isAdult,
                'students' => $isStudent,
                'staff' => AccessPolicy::isStaff($user),
                'volunteers' => self::activeVolunteer($db,$userId),
                'production_members' => $activeProductionMember,
                'production_adults' => $activeProductionMember && $isAdult,
                'production_students' => $activeProductionMember && $audienceType==='student',
                'production_guardians' => $activeProductionMember && $audienceType==='guardian',
                'production_staff' => $activeProductionMember && $audienceType==='staff',
                default => false,
            };
            if($ok) return true;
        }
        return false;
    }

    private static function activeVolunteer(PDO $db,int $userId): bool
    {
        $stmt=$db->prepare('SELECT 1 FROM volunteer_profiles WHERE user_id=:id AND active=1 LIMIT 1');
        $stmt->execute(['id'=>$userId]); return (bool)$stmt->fetchColumn();
    }

    private static function accessLabel(array $channel): string
    {
        return match((string)($channel['access_mode']??'audience')){
            'selected'=>'Selected members',
            'team'=>'Team members',
            'hybrid'=>'Audience + selected members/teams',
            default=>self::audienceLabel(self::audiences($channel,'read')),
        };
    }

    private static function audienceLabel(array $audiences): string
    {
        $labels=['all_members'=>'All members','adults'=>'Adults only','students'=>'Students','staff'=>'Staff only','volunteers'=>'Volunteers','production_members'=>'Production members','production_adults'=>'Production adults','production_students'=>'Production students','production_guardians'=>'Production guardians','production_staff'=>'Production staff'];
        return implode(', ',array_map(static fn($a)=>$labels[$a]??$a,$audiences));
    }

    private static function currentUser(PDO $db): array
    {
        $row=$db->query("SELECT id,CONCAT(first_name,' ',last_name) name,display_role role,initials FROM users WHERE is_demo_current_user=1 AND active=1 LIMIT 1")->fetch();
        if(!$row) throw new RuntimeException('Demo user is missing. Re-import the local seed data.'); return $row;
    }
    private static function audit(PDO $db,int $actor,string $event,string $type,int $id,string $summary,array $meta): void
    {
        $stmt=$db->prepare('INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,:event,:type,:id,:summary,:meta)');
        $stmt->execute(['actor'=>$actor,'event'=>$event,'type'=>$type,'id'=>$id,'summary'=>$summary,'meta'=>json_encode($meta,JSON_THROW_ON_ERROR)]);
    }
    private static function flash(string $type,string $message): void { $_SESSION['community_flash']=['type'=>$type,'message'=>$message]; }
    private static function redirect(string $url): never { header('Location: '.$url,true,303); exit; }

    private static function page(string $route,string $basePath,array $user,array $channels,?array $selected): never
    {
        $url=static fn(string $p):string=>($basePath?:'').$p; $esc=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');
        $flash=$_SESSION['community_flash']??$_SESSION['team_flash']??null; unset($_SESSION['community_flash'],$_SESSION['team_flash']);
        $title=$route==='/channels'?'Community':($selected['name']??'Channel');
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $esc($title) ?> · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/communication-implementation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/community-permissions.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar($route,$basePath,$user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Community',$title,$basePath); ?><div class="comm-page community-page">
        <?php if($flash): ?><div class="comm-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif; ?>
        <?php if($route==='/channels'): ?><section class="comm-hero"><small>CTSMD COMMUNITY</small><h2>The right rooms for the right people.</h2><p>Audience channels, reusable Team rooms, and selected-member spaces all live here. Production spaces disappear automatically when their show becomes inactive.</p><?php if(AccessPolicy::isStaff($user)): ?><a class="button community-manage" href="<?= $url('/admin/channels') ?>">Manage channels</a><?php endif; ?></section><div class="comm-channel-grid"><?php foreach($channels as $c): ?><a class="comm-channel-card" href="<?= $url('/channels/view?id='.(int)$c['id']) ?>"><span>#</span><div><small><?= $esc($c['production_title']?:strtoupper($c['channel_type'])) ?></small><h3><?= $esc($c['name']) ?></h3><p><?= $esc($c['description']?:'Community updates and discussion.') ?></p><div class="community-policy"><em><?= $esc(self::accessLabel($c)) ?></em><em><?= $c['can_post']?'You can post':'Read only' ?></em></div><footer><b><?= (int)$c['post_count'] ?> posts</b><em><?= $c['latest_at']?$esc(date('M j · g:i A',strtotime($c['latest_at']))):'No posts yet' ?></em></footer></div></a><?php endforeach; ?><?php if(!$channels): ?><div class="comm-empty"><b>No Community channels available</b><p>Your account is not currently included in an active room.</p></div><?php endif; ?></div>
        <?php else: ?><?php if(!$selected): ?><section class="comm-empty"><b>Channel unavailable</b><p>This room may belong to an inactive production or an audience, Team, or selected-member group your account is not part of.</p><a class="button" href="<?= $url('/channels') ?>">Back to Community</a></section><?php else: ?><section class="comm-channel-head"><div><small><?= $esc($selected['production_title']?:strtoupper($selected['channel_type'])) ?></small><h2># <?= $esc($selected['name']) ?></h2><p><?= $esc($selected['description']?:'Community updates and discussion.') ?></p><div class="community-policy"><em>Access: <?= $esc(self::accessLabel($selected)) ?></em><em><?= $selected['can_post']?'Posting enabled':'Read only' ?></em></div></div><a href="<?= $url('/channels') ?>">All channels →</a></section><?php if($selected['can_post']): ?><form class="community-composer" method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['community_csrf']) ?>"><input type="hidden" name="channel_id" value="<?= (int)$selected['id'] ?>"><label><span>Post to #<?= $esc($selected['name']) ?></span><textarea name="body" rows="4" maxlength="5000" required></textarea></label><footer><small><?= $esc(self::accessLabel($selected)) ?></small><button class="button" type="submit">Publish post</button></footer></form><?php else: ?><div class="community-readonly"><b>Read-only for your account</b></div><?php endif; ?><section class="comm-feed"><?php foreach($selected['posts'] as $p): ?><article class="comm-post<?= $p['pinned']?' pinned':'' ?>"><header><i><?= $esc($p['initials']) ?></i><div><b><?= $esc($p['author']) ?></b><small><?= $esc($p['author_role']) ?> · <?= $esc(date('M j · g:i A',strtotime($p['created_at']))) ?></small></div></header><p><?= nl2br($esc($p['body'])) ?></p></article><?php endforeach; ?><?php if(!$selected['posts']): ?><div class="comm-empty"><b>No posts yet</b></div><?php endif; ?></section><?php endif; ?><?php endif; ?>
        </div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php exit;
    }
}
