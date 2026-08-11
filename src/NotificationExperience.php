<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AppNavigation.php';

final class NotificationExperience
{
    public static function handles(string $route): bool
    {
        return $route === '/notifications';
    }

    public static function render(string $basePath): never
    {
        Auth::startSession();
        $db = Database::connect(dirname(__DIR__));
        $user = Auth::currentUser($db);
        if (!$user) self::redirect(($basePath ?: '') . '/login');
        $_SESSION['notification_csrf'] ??= bin2hex(random_bytes(24));

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::handlePost($db, (int)$user['id'], $basePath);
        }

        $notifications = self::notifications($db, (int)$user['id']);
        $openForms = self::openForms($db, (int)$user['id']);
        $unread = count(array_filter($notifications, static fn(array $row): bool => empty($row['read_at'])));
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $flash = $_SESSION['notification_flash'] ?? null;
        unset($_SESSION['notification_flash']);

        $subnav = [
            ['label'=>'Today','href'=>'/app','active'=>false],
            ['label'=>'My family','href'=>'/family-hub','active'=>false],
            ['label'=>'Forms','href'=>'/forms','active'=>false],
            ['label'=>'Notifications','href'=>'/notifications','active'=>true],
        ];

        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Notifications · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/home-experience.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/persisted-notifications.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/notifications',$basePath,$user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Home','Notifications',$basePath,$subnav); ?><div class="home-page notification-page">
        <?php if($flash): ?><div class="notification-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif; ?>
        <section class="home-hero compact"><div><span>NOTIFICATION CENTER</span><h2>What needs action, and what actually changed.</h2><p>Schedule updates published to you appear here as durable, user-specific notifications.</p></div><?php if($unread): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['notification_csrf']) ?>"><input type="hidden" name="action" value="mark_all_read"><button class="button" type="submit">Mark all read</button></form><?php endif; ?></section>
        <div class="notification-summary"><article><strong><?= $unread ?></strong><span>unread updates</span></article><article><strong><?= count($openForms) ?></strong><span>open forms</span></article></div>
        <div class="notification-columns"><section class="home-card"><header class="home-section-head"><div><span>ACTION REQUIRED</span><h3>Needs you</h3></div><b><?= count($openForms) ?></b></header><?php if($openForms): foreach($openForms as $form): ?><article class="notification-row <?= $form['status']==='missing'?'urgent':'' ?>"><i>!</i><div><b><?= $esc($form['title']) ?></b><span><?= $esc(ucwords(str_replace('_',' ',$form['status']))) ?><?= $form['due_at'] ? ' · Due '.$esc(date('M j',strtotime($form['due_at']))) : '' ?></span></div><a href="<?= $url('/forms') ?>">Review</a></article><?php endforeach; else: ?><div class="home-empty"><b>Nothing needs action.</b><span>You are caught up on assigned forms.</span></div><?php endif; ?></section>
        <section class="home-card"><header class="home-section-head"><div><span>PUBLISHED TO YOU</span><h3>Updates</h3></div><b><?= count($notifications) ?></b></header><?php if(!$notifications): ?><div class="home-empty"><b>No personal updates yet.</b><span>Published production changes and future account notices will appear here.</span></div><?php else: foreach($notifications as $notice): ?><article class="persisted-notification<?= empty($notice['read_at'])?' unread':'' ?>"><div class="persisted-marker"></div><div><small><?= empty($notice['read_at'])?'NEW · ':'' ?><?= $esc(date('M j · g:i A',strtotime($notice['created_at']))) ?></small><h3><?= $esc($notice['title']) ?></h3><p><?= nl2br($esc($notice['body'])) ?></p><footer><?php if($notice['action_path']): ?><a href="<?= $url($notice['action_path']) ?>">Open related page →</a><?php endif; ?><?php if(empty($notice['read_at'])): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['notification_csrf']) ?>"><input type="hidden" name="action" value="mark_read"><input type="hidden" name="notification_id" value="<?= (int)$notice['id'] ?>"><button type="submit">Mark read</button></form><?php endif; ?></footer></div></article><?php endforeach; endif; ?></section></div>
        </div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php
        exit;
    }

    private static function handlePost(PDO $db, int $userId, string $basePath): never
    {
        $token = (string)($_POST['csrf_token'] ?? '');
        if (!hash_equals((string)($_SESSION['notification_csrf'] ?? ''), $token)) {
            self::flash('error','Your session token expired. Please try again.');
            self::redirect($basePath.'/notifications');
        }
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'mark_read') {
            $id = filter_input(INPUT_POST,'notification_id',FILTER_VALIDATE_INT) ?: 0;
            $stmt = $db->prepare('UPDATE app_notifications SET read_at = COALESCE(read_at,CURRENT_TIMESTAMP) WHERE id = :id AND recipient_user_id = :user_id');
            $stmt->execute(['id'=>$id,'user_id'=>$userId]);
        } elseif ($action === 'mark_all_read') {
            $stmt = $db->prepare('UPDATE app_notifications SET read_at = CURRENT_TIMESTAMP WHERE recipient_user_id = :user_id AND read_at IS NULL');
            $stmt->execute(['user_id'=>$userId]);
        }
        self::redirect($basePath.'/notifications');
    }

    private static function notifications(PDO $db, int $userId): array
    {
        $stmt = $db->prepare('SELECT id,title,body,action_path,read_at,created_at FROM app_notifications WHERE recipient_user_id = :user_id ORDER BY created_at DESC,id DESC LIMIT 30');
        $stmt->execute(['user_id'=>$userId]);
        return $stmt->fetchAll();
    }

    private static function openForms(PDO $db, int $userId): array
    {
        $stmt = $db->prepare("SELECT f.title,fa.status,fa.due_at FROM form_assignments fa JOIN forms f ON f.id=fa.form_id WHERE fa.user_id=:user_id AND fa.status<>'completed' ORDER BY fa.due_at");
        $stmt->execute(['user_id'=>$userId]);
        return $stmt->fetchAll();
    }

    private static function flash(string $type,string $message): void
    {
        $_SESSION['notification_flash']=['type'=>$type,'message'=>$message];
    }

    private static function redirect(string $url): never
    {
        header('Location: '.$url,true,303);
        exit;
    }
}
