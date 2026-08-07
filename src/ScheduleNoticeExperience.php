<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/ProductionContext.php';

final class ScheduleNoticeExperience
{
    private const ROUTES = ['/production/notices', '/production/notice'];

    public static function handles(string $route): bool
    {
        return in_array($route, self::ROUTES, true);
    }

    public static function render(string $route, string $basePath): never
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $db = Database::connect(dirname(__DIR__));
        $user = self::currentUser($db);
        if (!AccessPolicy::canManageProduction($user)) {
            self::forbidden($basePath, $user);
        }

        $_SESSION['schedule_notice_csrf'] ??= bin2hex(random_bytes(24));

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::handlePost($db, $user, $basePath);
        }

        $production = ProductionContext::selected($db, $user);
        $productionId = $production ? (int)$production['id'] : 0;
        $notices = $production ? self::notices($db, $productionId) : [];
        $selected = null;
        $channels = [];
        $audience = [];
        $deliveries = [];

        if ($route === '/production/notice') {
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
            $selected = self::notice($db, (int)$id, $productionId);
            if ($selected) {
                $channels = self::channels($db, (int)$selected['production_id']);
                $audience = self::audienceMembers($db, (int)$selected['production_id'], (string)$selected['audience_scope']);
                $deliveries = self::deliveries($db, (int)$selected['id']);
            }
        }

        self::page($route, $basePath, $user, $production, $notices, $selected, $channels, $audience, $deliveries);
    }

    private static function handlePost(PDO $db, array $user, string $basePath): never
    {
        $token = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals((string)($_SESSION['schedule_notice_csrf'] ?? ''), $token)) {
            self::flash('error', 'Your session token expired. Please try again.');
            self::redirect($basePath . '/production/notices');
        }

        $noticeId = filter_input(INPUT_POST, 'notice_id', FILTER_VALIDATE_INT) ?: 0;
        $action = (string)($_POST['action'] ?? '');

        try {
            if ($action === 'publish') {
                self::publish($db, $user, (int)$noticeId, $_POST);
                self::flash('success', 'Schedule update published to the selected CTSMD destinations.');
            } elseif ($action === 'cancel') {
                self::cancel($db, $user, (int)$noticeId);
                self::flash('success', 'Draft cancelled. No communication was sent.');
            } else {
                throw new RuntimeException('That update action is not available.');
            }
        } catch (RuntimeException $e) {
            self::flash('error', $e->getMessage());
        }

        self::redirect($basePath . '/production/notice?id=' . (int)$noticeId);
    }

    private static function publish(PDO $db, array $user, int $noticeId, array $input): void
    {
        if ($noticeId < 1) {
            throw new RuntimeException('That communication draft could not be found.');
        }

        $selectedProduction = ProductionContext::selected($db, $user);
        if (!$selectedProduction) {
            throw new RuntimeException('Select an active production before publishing its updates.');
        }

        $subject = trim((string)($input['subject'] ?? ''));
        $body = trim((string)($input['body'] ?? ''));
        $sendInApp = isset($input['send_in_app']);
        $sendChannel = isset($input['send_channel']);
        $channelId = filter_var($input['channel_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;

        if ($subject === '' || mb_strlen($subject) > 190) {
            throw new RuntimeException('Enter a subject no longer than 190 characters.');
        }
        if ($body === '' || mb_strlen($body) > 6000) {
            throw new RuntimeException('Enter update text no longer than 6,000 characters.');
        }
        if (!$sendInApp && !$sendChannel) {
            throw new RuntimeException('Choose at least one publishing destination.');
        }
        if ($sendChannel && $channelId < 1) {
            throw new RuntimeException('Choose a Community channel for this update.');
        }

        $db->beginTransaction();
        try {
            $noticeStmt = $db->prepare("SELECT id, schedule_item_id, production_id, audience_scope, status FROM schedule_change_notices WHERE id = :id FOR UPDATE");
            $noticeStmt->execute(['id' => $noticeId]);
            $notice = $noticeStmt->fetch();
            if (!$notice) {
                throw new RuntimeException('That communication draft no longer exists.');
            }
            if ((int)$notice['production_id'] !== (int)$selectedProduction['id']) {
                throw new RuntimeException('That update belongs to a different production workspace.');
            }
            if ($notice['status'] !== 'draft') {
                throw new RuntimeException('Only draft updates can be published.');
            }

            $audience = self::audienceMembers($db, (int)$notice['production_id'], (string)$notice['audience_scope']);

            $channelPostId = null;
            if ($sendChannel) {
                $channelStmt = $db->prepare('SELECT id FROM channels WHERE id = :id AND production_id = :production_id AND archived_at IS NULL');
                $channelStmt->execute(['id' => $channelId, 'production_id' => (int)$notice['production_id']]);
                if (!$channelStmt->fetchColumn()) {
                    throw new RuntimeException('That Community channel is unavailable for this production.');
                }

                $post = $db->prepare('INSERT INTO channel_posts (channel_id, author_user_id, body, pinned, reactions_json, created_at) VALUES (:channel_id, :author, :body, 0, NULL, CURRENT_TIMESTAMP)');
                $post->execute(['channel_id' => $channelId, 'author' => (int)$user['id'], 'body' => $subject . "\n\n" . $body]);
                $channelPostId = (int)$db->lastInsertId();

                $delivery = $db->prepare("INSERT INTO schedule_notice_deliveries (notice_id, destination_type, destination_id, recipient_count, created_by_user_id) VALUES (:notice, 'channel', :destination, :count, :actor)");
                $delivery->execute(['notice' => $noticeId, 'destination' => $channelId, 'count' => count($audience), 'actor' => (int)$user['id']]);
            }

            $notificationCount = 0;
            if ($sendInApp) {
                $insertNotification = $db->prepare("INSERT INTO app_notifications (recipient_user_id, source_type, source_id, title, body, action_path, created_at) VALUES (:recipient, 'schedule_change', :source_id, :title, :body, :action_path, CURRENT_TIMESTAMP)");
                foreach ($audience as $member) {
                    $insertNotification->execute([
                        'recipient' => (int)$member['id'],
                        'source_id' => $noticeId,
                        'title' => $subject,
                        'body' => $body,
                        'action_path' => '/schedule',
                    ]);
                    $notificationCount++;
                }

                $delivery = $db->prepare("INSERT INTO schedule_notice_deliveries (notice_id, destination_type, destination_id, recipient_count, created_by_user_id) VALUES (:notice, 'in_app', NULL, :count, :actor)");
                $delivery->execute(['notice' => $noticeId, 'count' => $notificationCount, 'actor' => (int)$user['id']]);
            }

            $update = $db->prepare("UPDATE schedule_change_notices SET subject = :subject, body = :body, audience_count = :audience_count, status = 'published', published_at = CURRENT_TIMESTAMP WHERE id = :id");
            $update->execute(['subject' => $subject, 'body' => $body, 'audience_count' => count($audience), 'id' => $noticeId]);

            $audit = $db->prepare('INSERT INTO audit_events (actor_user_id, event_type, subject_type, subject_id, summary, metadata_json) VALUES (:actor, :event_type, :subject_type, :subject_id, :summary, :metadata)');
            $audit->execute([
                'actor' => (int)$user['id'],
                'event_type' => 'schedule.notice_published',
                'subject_type' => 'schedule_change_notice',
                'subject_id' => $noticeId,
                'summary' => 'Published schedule change communication.',
                'metadata' => json_encode([
                    'production_id' => (int)$notice['production_id'],
                    'destinations' => ['in_app' => $sendInApp, 'channel' => $sendChannel],
                    'channel_id' => $sendChannel ? $channelId : null,
                    'channel_post_id' => $channelPostId,
                    'recipient_count' => $notificationCount,
                    'audience_user_ids' => array_map(static fn(array $row): int => (int)$row['id'], $audience),
                ], JSON_THROW_ON_ERROR),
            ]);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('The schedule update could not be published.');
        }
    }

    private static function cancel(PDO $db, array $user, int $noticeId): void
    {
        $selectedProduction = ProductionContext::selected($db, $user);
        if (!$selectedProduction) {
            throw new RuntimeException('Select an active production before cancelling its update.');
        }

        $db->beginTransaction();
        try {
            $check = $db->prepare("SELECT production_id, status FROM schedule_change_notices WHERE id = :id FOR UPDATE");
            $check->execute(['id' => $noticeId]);
            $notice = $check->fetch();
            if (!$notice || $notice['status'] !== 'draft') {
                throw new RuntimeException('Only an active draft can be cancelled.');
            }
            if ((int)$notice['production_id'] !== (int)$selectedProduction['id']) {
                throw new RuntimeException('That update belongs to a different production workspace.');
            }

            $stmt = $db->prepare("UPDATE schedule_change_notices SET status = 'cancelled' WHERE id = :id AND status = 'draft'");
            $stmt->execute(['id' => $noticeId]);
            $audit = $db->prepare('INSERT INTO audit_events (actor_user_id, event_type, subject_type, subject_id, summary, metadata_json) VALUES (:actor, :event_type, :subject_type, :subject_id, :summary, :metadata)');
            $audit->execute([
                'actor' => (int)$user['id'],
                'event_type' => 'schedule.notice_cancelled',
                'subject_type' => 'schedule_change_notice',
                'subject_id' => $noticeId,
                'summary' => 'Cancelled schedule change communication draft.',
                'metadata' => json_encode(['production_id' => (int)$notice['production_id']], JSON_THROW_ON_ERROR),
            ]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            if ($e instanceof RuntimeException) throw $e;
            throw new RuntimeException('The draft could not be cancelled.');
        }
    }

    private static function audienceMembers(PDO $db, int $productionId, string $scope): array
    {
        $types = match ($scope) {
            'family' => ['student', 'guardian'],
            'staff' => ['staff'],
            default => ['student', 'guardian', 'staff'],
        };
        $placeholders = implode(',', array_fill(0, count($types), '?'));
        $stmt = $db->prepare("SELECT DISTINCT u.id, CONCAT(u.first_name, ' ', u.last_name) AS name, pm.audience_type, u.last_name AS sort_last_name, u.first_name AS sort_first_name FROM production_memberships pm JOIN users u ON u.id = pm.user_id WHERE pm.production_id = ? AND pm.status = 'active' AND u.active = 1 AND pm.audience_type IN ($placeholders) ORDER BY sort_last_name, sort_first_name");
        $stmt->execute(array_merge([$productionId], $types));
        return $stmt->fetchAll();
    }

    private static function currentUser(PDO $db): array
    {
        $row = $db->query("SELECT id, CONCAT(first_name, ' ', last_name) AS name, display_role AS role, initials FROM users WHERE is_demo_current_user = 1 AND active = 1 LIMIT 1")->fetch();
        if (!$row) throw new RuntimeException('Demo user is missing. Re-import the local seed data.');
        return $row;
    }

    private static function notices(PDO $db, int $productionId): array
    {
        $stmt = $db->prepare("SELECT scn.id, scn.subject, scn.body, scn.audience_scope, scn.audience_count, scn.status, scn.created_at, scn.published_at, si.title AS schedule_title, CONCAT(u.first_name, ' ', u.last_name) AS creator FROM schedule_change_notices scn JOIN schedule_items si ON si.id = scn.schedule_item_id LEFT JOIN users u ON u.id = scn.created_by_user_id WHERE scn.production_id = :production_id ORDER BY FIELD(scn.status,'draft','published','cancelled'), scn.created_at DESC, scn.id DESC");
        $stmt->execute(['production_id' => $productionId]);
        return $stmt->fetchAll();
    }

    private static function notice(PDO $db, int $id, int $productionId): ?array
    {
        if ($id < 1 || $productionId < 1) return null;
        $stmt = $db->prepare("SELECT scn.*, si.title AS schedule_title, si.starts_at, si.location, p.title AS production_title, CONCAT(u.first_name, ' ', u.last_name) AS creator FROM schedule_change_notices scn JOIN schedule_items si ON si.id = scn.schedule_item_id JOIN productions p ON p.id = scn.production_id LEFT JOIN users u ON u.id = scn.created_by_user_id WHERE scn.id = :id AND scn.production_id = :production_id LIMIT 1");
        $stmt->execute(['id' => $id, 'production_id' => $productionId]);
        return $stmt->fetch() ?: null;
    }

    private static function channels(PDO $db, int $productionId): array
    {
        $stmt = $db->prepare("SELECT id, name, description FROM channels WHERE production_id = :production_id AND archived_at IS NULL ORDER BY sort_order, name");
        $stmt->execute(['production_id' => $productionId]);
        return $stmt->fetchAll();
    }

    private static function deliveries(PDO $db, int $noticeId): array
    {
        $stmt = $db->prepare("SELECT snd.destination_type, snd.destination_id, snd.recipient_count, snd.created_at, c.name AS channel_name, CONCAT(u.first_name, ' ', u.last_name) AS creator FROM schedule_notice_deliveries snd LEFT JOIN channels c ON snd.destination_type = 'channel' AND c.id = snd.destination_id LEFT JOIN users u ON u.id = snd.created_by_user_id WHERE snd.notice_id = :notice_id ORDER BY snd.created_at");
        $stmt->execute(['notice_id' => $noticeId]);
        return $stmt->fetchAll();
    }

    private static function page(string $route, string $basePath, array $user, ?array $production, array $notices, ?array $selected, array $channels, array $audience, array $deliveries): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $flash = $_SESSION['schedule_notice_flash'] ?? $_SESSION['production_context_flash'] ?? null;
        unset($_SESSION['schedule_notice_flash'], $_SESSION['production_context_flash']);
        $title = $route === '/production/notices' ? 'Production updates' : ($selected['subject'] ?? 'Schedule update');
        $subnav = [
            ['label'=>'Overview','href'=>'/production','active'=>false],
            ['label'=>'Schedule','href'=>'/schedule','active'=>false],
            ['label'=>'Updates','href'=>'/production/notices','active'=>true],
            ['label'=>'Resources','href'=>'/resources','active'=>false],
            ['label'=>'Playbill','href'=>'/playbills','active'=>false],
        ];

        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $esc($title) ?> · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/schedule-notices.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar($route,$basePath,$user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Production',$title,$basePath,$subnav); ?><div class="notice-page">
        <?php if ($flash): ?><div class="notice-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif; ?>
        <?php if (!$production): ?><section class="notice-empty"><b>No active production selected</b></section>
        <?php elseif ($route === '/production/notices'): ?>
            <section class="notice-hero"><div><small><?= $esc(strtoupper($production['title'])) ?> · CHANGE COMMUNICATION</small><h2>Review before you broadcast.</h2><p>Only drafts belonging to the selected production workspace appear here.</p></div><a class="button" href="<?= $url('/schedule') ?>">Open schedule</a></section>
            <div class="notice-list"><?php if (!$notices): ?><div class="notice-empty"><b>No schedule updates yet</b><p>Edit a schedule item in this production to create the first communication draft.</p></div><?php endif; ?><?php foreach ($notices as $notice): ?><a href="<?= $url('/production/notice?id='.(int)$notice['id']) ?>" class="notice-row"><span class="notice-status <?= $esc($notice['status']) ?>"><?= $esc(strtoupper($notice['status'])) ?></span><div><small><?= $esc(strtoupper($notice['audience_scope'])) ?> · <?= (int)$notice['audience_count'] ?> PEOPLE</small><h3><?= $esc($notice['subject']) ?></h3><p><?= $esc($notice['schedule_title']) ?> · <?= $esc(date('M j · g:i A',strtotime($notice['created_at']))) ?></p></div><b>Review →</b></a><?php endforeach; ?></div>
        <?php else: ?>
            <?php if (!$selected): ?><section class="notice-empty"><b>Update not found in this production</b><p>It may belong to another active production. Switch the working production and try again.</p><a class="button" href="<?= $url('/production/notices') ?>">Back to updates</a></section><?php else: ?>
            <section class="notice-detail-head"><div><small><?= $esc(strtoupper($selected['status'])) ?> · <?= $esc(strtoupper($selected['audience_scope'])) ?></small><h2><?= $esc($selected['schedule_title']) ?></h2><p><?= $esc(date('l, M j · g:i A',strtotime($selected['starts_at']))) ?> · <?= $esc($selected['location']) ?></p></div><a href="<?= $url('/production/notices') ?>">← All updates</a></section>
            <div class="notice-layout"><section class="notice-card"><header><small>MESSAGE</small><h3><?= $selected['status']==='draft' ? 'Review & publish' : 'Published communication' ?></h3></header>
            <?php if ($selected['status']==='draft'): ?><form method="post" class="notice-form"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['schedule_notice_csrf']) ?>"><input type="hidden" name="notice_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="action" value="publish"><label>Subject<input name="subject" maxlength="190" value="<?= $esc($selected['subject']) ?>" required></label><label>Message<textarea name="body" rows="7" maxlength="6000" required><?= $esc($selected['body']) ?></textarea></label><fieldset><legend>Publish to</legend><label class="notice-check"><input type="checkbox" name="send_in_app" value="1" checked><span><b>In-app notifications</b><small>Creates one notification for each current audience member.</small></span></label><label class="notice-check"><input type="checkbox" name="send_channel" value="1"><span><b>Community channel</b><small>Publishes the same update as a channel post for this production.</small></span></label><label>Channel<select name="channel_id"><option value="">Choose production channel</option><?php foreach($channels as $channel): ?><option value="<?= (int)$channel['id'] ?>"><?= $esc($channel['name']) ?></option><?php endforeach; ?></select></label></fieldset><footer><button class="button" type="submit">Publish update</button></footer></form><form method="post" class="notice-cancel"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['schedule_notice_csrf']) ?>"><input type="hidden" name="notice_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="action" value="cancel"><button type="submit">Cancel draft</button></form>
            <?php else: ?><article class="notice-published-copy"><h3><?= $esc($selected['subject']) ?></h3><p><?= nl2br($esc($selected['body'])) ?></p><small><?= $selected['published_at'] ? 'Published '. $esc(date('M j · g:i A',strtotime($selected['published_at']))) : $esc(ucfirst($selected['status'])) ?></small></article><?php endif; ?></section>
            <aside class="notice-card"><header><small>AUDIENCE</small><h3><?= count($audience) ?> current recipients</h3></header><p class="notice-help">Audience is recalculated at publish time from this production's active memberships, not trusted from the older draft count.</p><div class="notice-people"><?php foreach($audience as $member): ?><span><i><?= $esc(strtoupper(substr($member['audience_type'],0,1))) ?></i><b><?= $esc($member['name']) ?></b><small><?= $esc(ucfirst($member['audience_type'])) ?></small></span><?php endforeach; ?></div><?php if($deliveries): ?><div class="notice-deliveries"><small>DELIVERED TO</small><?php foreach($deliveries as $delivery): ?><p><b><?= $delivery['destination_type']==='channel' ? '# '.$esc((string)$delivery['channel_name']) : 'In-app notifications' ?></b><span><?= (int)$delivery['recipient_count'] ?> recipients · <?= $esc(date('M j · g:i A',strtotime($delivery['created_at']))) ?></span></p><?php endforeach; ?></div><?php endif; ?></aside></div>
            <?php endif; ?>
        <?php endif; ?>
        </div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php
        exit;
    }

    private static function forbidden(string $basePath, array $user): never
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/production',$basePath,$user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Production','Restricted',$basePath); ?><div class="notice-page"><section class="notice-empty"><b>Staff only</b><p>Your role cannot publish production updates.</p></section></div></main></div></body></html><?php
        exit;
    }

    private static function flash(string $type, string $message): void
    {
        $_SESSION['schedule_notice_flash'] = ['type'=>$type,'message'=>$message];
    }

    private static function redirect(string $url): never
    {
        header('Location: '.$url,true,303);
        exit;
    }
}
