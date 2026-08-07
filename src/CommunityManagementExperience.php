<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/AccessPolicy.php';

final class CommunityManagementExperience
{
    private const ROUTES = ['/admin/channels', '/admin/channels/edit'];

    public static function handles(string $route): bool
    {
        return in_array($route, self::ROUTES, true);
    }

    public static function render(string $route, string $basePath): never
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $db = Database::connect(dirname(__DIR__));
        $user = self::currentUser($db);
        if (!AccessPolicy::isStaff($user)) self::forbidden($basePath, $user);
        $_SESSION['channel_admin_csrf'] ??= bin2hex(random_bytes(24));

        if ($_SERVER['REQUEST_METHOD'] === 'POST') self::handlePost($db, $user, $route, $basePath);

        $edit = null;
        if ($route === '/admin/channels/edit') {
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
            $edit = self::channel($db, (int)$id);
        }
        self::page($route, $basePath, $user, self::channels($db), self::productions($db), $edit);
    }

    private static function handlePost(PDO $db, array $user, string $route, string $basePath): never
    {
        $token = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals((string)($_SESSION['channel_admin_csrf'] ?? ''), $token)) {
            self::flash('error', 'Your session token expired. Please try again.');
            self::redirect($basePath . '/admin/channels');
        }
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'save') {
                $id = filter_input(INPUT_POST, 'channel_id', FILTER_VALIDATE_INT) ?: 0;
                $savedId = self::saveChannel($db, $user, (int)$id, $_POST);
                self::flash('success', $id > 0 ? 'Channel settings updated.' : 'Channel created.');
                self::redirect($basePath . '/admin/channels/edit?id=' . $savedId);
            }
            if ($action === 'archive') {
                $id = filter_input(INPUT_POST, 'channel_id', FILTER_VALIDATE_INT) ?: 0;
                self::setArchived($db, $user, (int)$id, true);
                self::flash('success', 'Channel archived. Existing posts were preserved.');
            } elseif ($action === 'restore') {
                $id = filter_input(INPUT_POST, 'channel_id', FILTER_VALIDATE_INT) ?: 0;
                self::setArchived($db, $user, (int)$id, false);
                self::flash('success', 'Channel restored.');
            } else {
                throw new RuntimeException('Choose a valid channel action.');
            }
        } catch (RuntimeException $e) {
            self::flash('error', $e->getMessage());
            if ($route === '/admin/channels/edit') {
                $id = filter_input(INPUT_POST, 'channel_id', FILTER_VALIDATE_INT) ?: 0;
                self::redirect($basePath . '/admin/channels/edit?id=' . (int)$id);
            }
        }
        self::redirect($basePath . '/admin/channels');
    }

    private static function saveChannel(PDO $db, array $user, int $channelId, array $input): int
    {
        $name = trim((string)($input['name'] ?? ''));
        $type = trim((string)($input['channel_type'] ?? 'discussion'));
        $description = trim((string)($input['description'] ?? ''));
        $readScope = (string)($input['read_scope'] ?? 'all_members');
        $postScope = (string)($input['post_scope'] ?? 'staff');
        $productionId = filter_var($input['production_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $sortOrder = filter_var($input['sort_order'] ?? 0, FILTER_VALIDATE_INT);
        $sortOrder = $sortOrder === false ? 0 : (int)$sortOrder;

        if ($name === '' || mb_strlen($name) > 120) throw new RuntimeException('Enter a channel name no longer than 120 characters.');
        if ($type === '' || mb_strlen($type) > 80) throw new RuntimeException('Enter a channel type no longer than 80 characters.');
        if (mb_strlen($description) > 255) throw new RuntimeException('Keep the description under 255 characters.');
        $scopes = ['all_members','production_members','staff'];
        if (!in_array($readScope, $scopes, true) || !in_array($postScope, $scopes, true)) throw new RuntimeException('Choose valid read and posting policies.');
        if (($readScope === 'production_members' || $postScope === 'production_members') && !$productionId) throw new RuntimeException('Production-member access requires a production.');

        $db->beginTransaction();
        try {
            if ($productionId) {
                $p = $db->prepare('SELECT id FROM productions WHERE id = :id LIMIT 1'); $p->execute(['id' => $productionId]);
                if (!$p->fetchColumn()) throw new RuntimeException('That production no longer exists.');
            }

            if ($channelId > 0) {
                $beforeStmt = $db->prepare('SELECT id, name, channel_type, description, production_id, read_scope, post_scope, sort_order, archived_at FROM channels WHERE id = :id FOR UPDATE');
                $beforeStmt->execute(['id' => $channelId]);
                $before = $beforeStmt->fetch();
                if (!$before) throw new RuntimeException('That channel no longer exists.');
                $save = $db->prepare('UPDATE channels SET name = :name, channel_type = :type, description = :description, production_id = :production_id, read_scope = :read_scope, post_scope = :post_scope, sort_order = :sort_order WHERE id = :id');
                $save->execute(['name'=>$name,'type'=>$type,'description'=>$description !== '' ? $description : null,'production_id'=>$productionId,'read_scope'=>$readScope,'post_scope'=>$postScope,'sort_order'=>$sortOrder,'id'=>$channelId]);
                self::audit($db, (int)$user['id'], 'community.channel_updated', 'channel', $channelId, 'Updated community channel settings.', ['before'=>$before,'after'=>['name'=>$name,'channel_type'=>$type,'description'=>$description !== '' ? $description : null,'production_id'=>$productionId,'read_scope'=>$readScope,'post_scope'=>$postScope,'sort_order'=>$sortOrder]]);
            } else {
                $save = $db->prepare('INSERT INTO channels (production_id, name, channel_type, description, read_scope, post_scope, sort_order, created_by_user_id) VALUES (:production_id, :name, :type, :description, :read_scope, :post_scope, :sort_order, :creator)');
                $save->execute(['production_id'=>$productionId,'name'=>$name,'type'=>$type,'description'=>$description !== '' ? $description : null,'read_scope'=>$readScope,'post_scope'=>$postScope,'sort_order'=>$sortOrder,'creator'=>(int)$user['id']]);
                $channelId = (int)$db->lastInsertId();
                self::audit($db, (int)$user['id'], 'community.channel_created', 'channel', $channelId, 'Created community channel.', ['name'=>$name,'production_id'=>$productionId,'read_scope'=>$readScope,'post_scope'=>$postScope]);
            }
            $db->commit();
            return $channelId;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof RuntimeException) throw $e;
            throw new RuntimeException('The channel could not be saved.');
        }
    }

    private static function setArchived(PDO $db, array $user, int $channelId, bool $archive): void
    {
        if ($channelId < 1) throw new RuntimeException('That channel could not be found.');
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('SELECT id, name, archived_at FROM channels WHERE id = :id FOR UPDATE'); $stmt->execute(['id'=>$channelId]); $channel = $stmt->fetch();
            if (!$channel) throw new RuntimeException('That channel no longer exists.');
            $update = $db->prepare('UPDATE channels SET archived_at = :archived_at WHERE id = :id');
            $update->execute(['archived_at'=>$archive ? date('Y-m-d H:i:s') : null,'id'=>$channelId]);
            self::audit($db, (int)$user['id'], $archive ? 'community.channel_archived' : 'community.channel_restored', 'channel', $channelId, ($archive ? 'Archived ' : 'Restored ') . 'community channel.', ['channel_name'=>$channel['name']]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof RuntimeException) throw $e;
            throw new RuntimeException('The channel status could not be changed.');
        }
    }

    private static function channels(PDO $db): array
    {
        return $db->query("SELECT c.id, c.name, c.channel_type, c.description, c.read_scope, c.post_scope, c.sort_order, c.archived_at, p.title AS production_title, COUNT(cp.id) AS post_count FROM channels c LEFT JOIN productions p ON p.id = c.production_id LEFT JOIN channel_posts cp ON cp.channel_id = c.id GROUP BY c.id, c.name, c.channel_type, c.description, c.read_scope, c.post_scope, c.sort_order, c.archived_at, p.title ORDER BY c.archived_at IS NOT NULL, c.sort_order, c.name")->fetchAll();
    }

    private static function channel(PDO $db, int $channelId): ?array
    {
        if ($channelId < 1) return null;
        $stmt = $db->prepare('SELECT id, production_id, name, channel_type, description, read_scope, post_scope, sort_order, archived_at FROM channels WHERE id = :id LIMIT 1'); $stmt->execute(['id'=>$channelId]);
        return $stmt->fetch() ?: null;
    }

    private static function productions(PDO $db): array
    {
        return $db->query("SELECT id, title, season, status FROM productions ORDER BY FIELD(status,'current','planning','archived'), title")->fetchAll();
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
        $stmt->execute(['actor'=>$actorId,'event_type'=>$eventType,'subject_type'=>$subjectType,'subject_id'=>$subjectId,'summary'=>$summary,'metadata'=>json_encode($metadata, JSON_THROW_ON_ERROR)]);
    }

    private static function flash(string $type, string $message): void { $_SESSION['channel_admin_flash'] = ['type'=>$type,'message'=>$message]; }
    private static function redirect(string $url): never { header('Location: ' . $url, true, 303); exit; }
    private static function scopeLabel(string $scope): string { return match($scope){'all_members'=>'All members','production_members'=>'Production members','staff'=>'Staff',default=>$scope}; }

    private static function page(string $route, string $basePath, array $user, array $channels, array $productions, ?array $edit): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $flash = $_SESSION['channel_admin_flash'] ?? null; unset($_SESSION['channel_admin_flash']);
        $editing = $route === '/admin/channels/edit';
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#a6192e"><title>Channel management · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/community-admin.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar($route, $basePath, $user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Operations', $editing ? 'Channel settings' : 'Community channels', $basePath); ?><div class="ca-page">
        <?php if ($flash): ?><div class="ca-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif; ?>
        <?php if (!$editing): ?>
        <section class="ca-hero"><div><small>COMMUNITY GOVERNANCE</small><h2>Build rooms with rules, not assumptions.</h2><p>Create organization-wide or production-specific channels and define who may read and who may publish. Archiving preserves history without leaving dead rooms in the member experience.</p></div><a class="button" href="<?= $url('/admin/channels/edit') ?>">Create channel</a></section>
        <div class="ca-grid"><?php foreach ($channels as $channel): ?><article class="ca-card<?= $channel['archived_at'] ? ' archived' : '' ?>"><header><span>#</span><div><small><?= $esc($channel['production_title'] ?: strtoupper($channel['channel_type'])) ?></small><h3><?= $esc($channel['name']) ?></h3></div></header><p><?= $esc($channel['description'] ?: 'No description') ?></p><div class="ca-policy"><span><b>Read</b><?= $esc(self::scopeLabel($channel['read_scope'])) ?></span><span><b>Post</b><?= $esc(self::scopeLabel($channel['post_scope'])) ?></span><span><b>Posts</b><?= (int)$channel['post_count'] ?></span></div><footer><a href="<?= $url('/admin/channels/edit?id=' . (int)$channel['id']) ?>">Settings</a><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['channel_admin_csrf']) ?>"><input type="hidden" name="channel_id" value="<?= (int)$channel['id'] ?>"><input type="hidden" name="action" value="<?= $channel['archived_at'] ? 'restore' : 'archive' ?>"><button type="submit"><?= $channel['archived_at'] ? 'Restore' : 'Archive' ?></button></form></footer></article><?php endforeach; ?></div>
        <?php else: ?>
        <?php $c = $edit ?? ['id'=>0,'production_id'=>null,'name'=>'','channel_type'=>'discussion','description'=>'','read_scope'=>'all_members','post_scope'=>'staff','sort_order'=>100,'archived_at'=>null]; ?>
        <section class="ca-edit-head"><div><small><?= $edit ? 'CHANNEL SETTINGS' : 'NEW CHANNEL' ?></small><h2><?= $edit ? '# ' . $esc($c['name']) : 'Create a community channel' ?></h2><p>Read and post policies are enforced again on the server when members open a channel or submit a post.</p></div><a href="<?= $url('/admin/channels') ?>">← All channels</a></section>
        <?php if ($editing && !$edit && isset($_GET['id'])): ?><div class="ca-empty"><b>Channel not found</b></div><?php else: ?><form class="ca-form" method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['channel_admin_csrf']) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="channel_id" value="<?= (int)$c['id'] ?>"><label>Channel name<input name="name" maxlength="120" required value="<?= $esc($c['name']) ?>" placeholder="Parent Questions"></label><div class="ca-pair"><label>Channel type<input name="channel_type" maxlength="80" required value="<?= $esc($c['channel_type']) ?>"></label><label>Sort order<input type="number" name="sort_order" value="<?= (int)$c['sort_order'] ?>"></label></div><label>Description<textarea name="description" rows="3" maxlength="255"><?= $esc((string)$c['description']) ?></textarea></label><label>Production<select name="production_id"><option value="">Organization-wide</option><?php foreach($productions as $p): ?><option value="<?= (int)$p['id'] ?>"<?= (int)($c['production_id'] ?? 0)===(int)$p['id']?' selected':'' ?>><?= $esc($p['title']) ?> — <?= $esc($p['status']) ?></option><?php endforeach; ?></select></label><div class="ca-pair"><label>Who can read?<select name="read_scope"><option value="all_members"<?= $c['read_scope']==='all_members'?' selected':'' ?>>All members</option><option value="production_members"<?= $c['read_scope']==='production_members'?' selected':'' ?>>Production members</option><option value="staff"<?= $c['read_scope']==='staff'?' selected':'' ?>>Staff only</option></select></label><label>Who can post?<select name="post_scope"><option value="staff"<?= $c['post_scope']==='staff'?' selected':'' ?>>Staff only</option><option value="production_members"<?= $c['post_scope']==='production_members'?' selected':'' ?>>Production members</option><option value="all_members"<?= $c['post_scope']==='all_members'?' selected':'' ?>>All members</option></select></label></div><div class="ca-note"><b>Production membership rule</b><span>If either policy is “Production members,” this channel must be attached to a production. CTSMD checks active membership at request time.</span></div><footer><a href="<?= $url('/admin/channels') ?>">Cancel</a><button class="button" type="submit"><?= $edit ? 'Save settings' : 'Create channel' ?></button></footer></form><?php endif; ?>
        <?php endif; ?>
        </div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php exit;
    }

    private static function forbidden(string $basePath, array $user): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path; http_response_code(403); header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Restricted · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/channels',$basePath,$user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Community','Restricted',$basePath); ?><div class="ca-page"><section class="ca-empty"><b>Staff only</b><p>Your account cannot manage community channel policies.</p></section></div></main></div></body></html><?php exit;
    }
}
