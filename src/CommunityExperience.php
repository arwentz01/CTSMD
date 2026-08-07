<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/AccessPolicy.php';

final class CommunityExperience
{
    private const ROUTES = ['/channels', '/channels/view'];

    public static function handles(string $route): bool
    {
        return in_array($route, self::ROUTES, true);
    }

    public static function render(string $route, string $basePath): never
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $db = Database::connect(dirname(__DIR__));
        $user = self::currentUser($db);
        $_SESSION['community_csrf'] ??= bin2hex(random_bytes(24));

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::handlePost($db, $user, $basePath);
        }

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
        $token = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals((string)($_SESSION['community_csrf'] ?? ''), $token)) {
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
            $stmt = $db->prepare('SELECT id, production_id, name, read_scope, post_scope, archived_at FROM channels WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $channelId]);
            $channel = $stmt->fetch();
            if (!$channel || $channel['archived_at'] !== null) throw new RuntimeException('That channel is no longer available.');
            if (!self::canRead($db, $user, $channel)) throw new RuntimeException('You do not have access to that channel.');
            if (!self::canPost($db, $user, $channel)) throw new RuntimeException('This channel is read-only for your account.');

            $insert = $db->prepare('INSERT INTO channel_posts (channel_id, author_user_id, body, pinned, reactions_json, created_at) VALUES (:channel_id, :author, :body, 0, NULL, CURRENT_TIMESTAMP)');
            $insert->execute(['channel_id' => $channelId, 'author' => (int)$user['id'], 'body' => $body]);
            $postId = (int)$db->lastInsertId();

            self::audit($db, (int)$user['id'], 'community.post_created', 'channel_post', $postId, 'Published a community channel post.', [
                'channel_id' => $channelId,
                'channel_name' => $channel['name'],
            ]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof RuntimeException) throw $e;
            throw new RuntimeException('The post could not be published.');
        }
    }

    private static function channels(PDO $db, array $user): array
    {
        $rows = $db->query("SELECT c.id, c.production_id, c.name, c.channel_type, c.description, c.read_scope, c.post_scope, p.title AS production_title, COUNT(cp.id) AS post_count, MAX(cp.created_at) AS latest_at FROM channels c LEFT JOIN productions p ON p.id = c.production_id LEFT JOIN channel_posts cp ON cp.channel_id = c.id WHERE c.archived_at IS NULL GROUP BY c.id, c.production_id, c.name, c.channel_type, c.description, c.read_scope, c.post_scope, p.title, c.sort_order ORDER BY c.sort_order, c.name")->fetchAll();
        $visible = [];
        foreach ($rows as $row) {
            if (self::canRead($db, $user, $row)) {
                $row['can_post'] = self::canPost($db, $user, $row);
                $visible[] = $row;
            }
        }
        return $visible;
    }

    private static function channel(PDO $db, array $user, int $channelId): ?array
    {
        if ($channelId < 1) return null;
        $stmt = $db->prepare("SELECT c.id, c.production_id, c.name, c.channel_type, c.description, c.read_scope, c.post_scope, p.title AS production_title FROM channels c LEFT JOIN productions p ON p.id = c.production_id WHERE c.id = :id AND c.archived_at IS NULL LIMIT 1");
        $stmt->execute(['id' => $channelId]);
        $channel = $stmt->fetch();
        if (!$channel || !self::canRead($db, $user, $channel)) return null;
        $channel['can_post'] = self::canPost($db, $user, $channel);
        $posts = $db->prepare("SELECT cp.id, cp.body, cp.pinned, cp.reactions_json, cp.created_at, CONCAT(u.first_name, ' ', u.last_name) AS author, u.display_role AS author_role, u.initials FROM channel_posts cp JOIN users u ON u.id = cp.author_user_id WHERE cp.channel_id = :channel_id ORDER BY cp.pinned DESC, cp.created_at DESC, cp.id DESC");
        $posts->execute(['channel_id' => $channelId]);
        $channel['posts'] = $posts->fetchAll();
        return $channel;
    }

    private static function canRead(PDO $db, array $user, array $channel): bool
    {
        if (AccessPolicy::isStaff($user)) return true;
        return match ((string)$channel['read_scope']) {
            'all_members' => true,
            'production_members' => self::productionMember($db, (int)$user['id'], (int)($channel['production_id'] ?? 0)),
            'staff' => false,
            default => false,
        };
    }

    private static function canPost(PDO $db, array $user, array $channel): bool
    {
        if (AccessPolicy::isStaff($user)) return true;
        return match ((string)$channel['post_scope']) {
            'all_members' => true,
            'production_members' => self::productionMember($db, (int)$user['id'], (int)($channel['production_id'] ?? 0)),
            'staff' => false,
            default => false,
        };
    }

    private static function productionMember(PDO $db, int $userId, int $productionId): bool
    {
        if ($productionId < 1) return false;
        $stmt = $db->prepare("SELECT 1 FROM production_memberships WHERE production_id = :production_id AND user_id = :user_id AND status = 'active' LIMIT 1");
        $stmt->execute(['production_id' => $productionId, 'user_id' => $userId]);
        return (bool)$stmt->fetchColumn();
    }

    private static function currentUser(PDO $db): array
    {
        $row = $db->query("SELECT id, CONCAT(first_name, ' ', last_name) AS name, display_role AS role, initials FROM users WHERE is_demo_current_user = 1 AND active = 1 LIMIT 1")->fetch();
        if (!$row) throw new RuntimeException('Demo user is missing. Re-import the local seed data.');
        return $row;
    }

    private static function audit(PDO $db, int $actorId, string $eventType, string $subjectType, int $subjectId, string $summary, array $metadata): void
    {
        $stmt = $db->prepare('INSERT INTO audit_events (actor_user_id, event_type, subject_type, subject_id, summary, metadata_json) VALUES (:actor, :event_type, :subject_type, :subject_id, :summary, :metadata)');
        $stmt->execute(['actor' => $actorId, 'event_type' => $eventType, 'subject_type' => $subjectType, 'subject_id' => $subjectId, 'summary' => $summary, 'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR)]);
    }

    private static function flash(string $type, string $message): void { $_SESSION['community_flash'] = ['type' => $type, 'message' => $message]; }
    private static function redirect(string $url): never { header('Location: ' . $url, true, 303); exit; }

    private static function scopeLabel(string $scope): string
    {
        return match ($scope) { 'all_members' => 'All members', 'production_members' => 'Production members', 'staff' => 'Staff', default => $scope };
    }

    private static function page(string $route, string $basePath, array $user, array $channels, ?array $selected): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $flash = $_SESSION['community_flash'] ?? null; unset($_SESSION['community_flash']);
        $title = $route === '/channels' ? 'Community' : ($selected['name'] ?? 'Channel');
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#a6192e"><title><?= $esc($title) ?> · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/communication-implementation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/community-permissions.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar($route, $basePath, $user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Community', $title, $basePath); ?><div class="comm-page community-page">
        <?php if ($flash): ?><div class="comm-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif; ?>
        <?php if ($route === '/channels'): ?>
            <section class="comm-hero"><small>CTSMD COMMUNITY</small><h2>Useful updates, in the right room.</h2><p>You only see channels available to your account. Each channel also carries an explicit posting policy, so discussion spaces can stay collaborative while announcement channels remain controlled.</p><?php if (AccessPolicy::isStaff($user)): ?><a class="button community-manage" href="<?= $url('/admin/channels') ?>">Manage channels</a><?php endif; ?></section>
            <div class="comm-channel-grid"><?php if (!$channels): ?><div class="comm-empty"><b>No channels available</b><p>There are no active channels your account can currently access.</p></div><?php endif; ?><?php foreach ($channels as $channel): ?><a class="comm-channel-card" href="<?= $url('/channels/view?id=' . (int)$channel['id']) ?>"><span>#</span><div><small><?= $esc($channel['production_title'] ?: strtoupper($channel['channel_type'])) ?></small><h3><?= $esc($channel['name']) ?></h3><p><?= $esc($channel['description'] ?: 'Community updates and discussion.') ?></p><div class="community-policy"><em>Read: <?= $esc(self::scopeLabel($channel['read_scope'])) ?></em><em><?= $channel['can_post'] ? 'You can post' : 'Read only' ?></em></div><footer><b><?= (int)$channel['post_count'] ?> posts</b><em><?= $channel['latest_at'] ? $esc(date('M j · g:i A', strtotime($channel['latest_at']))) : 'No posts yet' ?></em></footer></div></a><?php endforeach; ?></div>
        <?php else: ?>
            <?php if (!$selected): ?><section class="comm-empty"><b>Channel unavailable</b><p>This channel may be archived or outside your current access.</p><a class="button" href="<?= $url('/channels') ?>">Back to Community</a></section><?php else: ?>
            <section class="comm-channel-head"><div><small><?= $esc($selected['production_title'] ?: strtoupper($selected['channel_type'])) ?></small><h2># <?= $esc($selected['name']) ?></h2><p><?= $esc($selected['description'] ?: 'Community updates and discussion.') ?></p><div class="community-policy"><em>Read: <?= $esc(self::scopeLabel($selected['read_scope'])) ?></em><em>Post: <?= $esc(self::scopeLabel($selected['post_scope'])) ?></em></div></div><a href="<?= $url('/channels') ?>">All channels →</a></section>
            <?php if ($selected['can_post']): ?><form class="community-composer" method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['community_csrf']) ?>"><input type="hidden" name="channel_id" value="<?= (int)$selected['id'] ?>"><label><span>Post to #<?= $esc($selected['name']) ?></span><textarea name="body" rows="4" maxlength="5000" required placeholder="Share an update with this channel…"></textarea></label><footer><small>Visible to <?= $esc(strtolower(self::scopeLabel($selected['read_scope']))) ?>.</small><button class="button" type="submit">Publish post</button></footer></form><?php else: ?><div class="community-readonly"><b>Read-only for your account</b><span>Posting is limited to <?= $esc(strtolower(self::scopeLabel($selected['post_scope']))) ?>.</span></div><?php endif; ?>
            <section class="comm-feed"><?php if (!$selected['posts']): ?><div class="comm-empty"><b>No posts yet</b><p><?= $selected['can_post'] ? 'Start the conversation above.' : 'This channel does not have any posts yet.' ?></p></div><?php endif; ?><?php foreach ($selected['posts'] as $post): $reactions = json_decode((string)($post['reactions_json'] ?? '{}'), true) ?: []; ?><article class="comm-post<?= $post['pinned'] ? ' pinned' : '' ?>"><header><i><?= $esc($post['initials']) ?></i><div><b><?= $esc($post['author']) ?></b><small><?= $esc($post['author_role']) ?> · <?= $esc(date('M j · g:i A', strtotime($post['created_at']))) ?></small></div><?php if ($post['pinned']): ?><span>PINNED</span><?php endif; ?></header><p><?= nl2br($esc($post['body'])) ?></p><?php if ($reactions): ?><footer><?php foreach ($reactions as $reaction => $count): ?><span><?= $esc(str_replace('_', ' ', $reaction)) ?> <?= (int)$count ?></span><?php endforeach; ?></footer><?php endif; ?></article><?php endforeach; ?></section>
            <?php endif; ?>
        <?php endif; ?>
        </div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php exit;
    }
}
