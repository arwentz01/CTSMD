<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ProductionNotificationAccess.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/CastingOfferService.php';
require_once __DIR__ . '/ProductionChecklistAssignmentService.php';

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
        $castingOffers = CastingOfferService::pendingForUser($db,(int)$user['id']);
        $todoItems = ProductionChecklistAssignmentService::actionItems($db,(int)$user['id']);
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
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Notifications · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/home-experience.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/persisted-notifications.css?v=20261026-casting-offer') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/notifications',$basePath,$user); ?><main class="unified-main"><?php AppNavigation::renderHeader('Home','Notifications',$basePath,$subnav); ?><div class="home-page notification-page">
        <?php if($flash): ?><div class="notification-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif; ?>
        <section class="home-hero compact"><div><span>NOTIFICATION CENTER</span><h2>What needs action, and what actually changed.</h2><p>Find updates, reminders, and tasks that need your attention.</p></div><?php if($unread): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['notification_csrf']) ?>"><input type="hidden" name="action" value="mark_all_read"><button class="button" type="submit">Mark all read</button></form><?php endif; ?></section>
        <div class="notification-summary"><article><strong><?= $unread ?></strong><span>unread updates</span></article><article><strong><?= count($openForms) ?></strong><span>open forms</span></article></div>
        <div class="notification-columns"><section class="home-card" id="casting-offers"><header class="home-section-head"><div><span>ACTION REQUIRED</span><h3>Needs you</h3></div><b><?= count($openForms)+count($castingOffers)+count($todoItems) ?></b></header><?php foreach($todoItems as $task):?><article class="notification-row"><i aria-hidden="true">✓</i><div class="notification-form-copy"><b><?= $esc((string)$task['title']) ?></b><span><?= $esc((string)$task['production_title']) ?> · <?= (int)$task['is_own']===1?'Assigned to you':'For '.$esc((string)$task['student_first'].' '.(string)$task['student_last']) ?><?= (int)$task['required']===1?' · Required':' · Optional' ?><?= $task['due_at']?' · Due '.$esc(date('M j',strtotime((string)$task['due_at']))):'' ?></span><?php if($task['notes']):?><p><?= $esc((string)$task['notes']) ?></p><?php endif;?><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['notification_csrf']) ?>"><input type="hidden" name="action" value="complete_production_todo"><input type="hidden" name="assignment_id" value="<?= (int)$task['assignment_id'] ?>"><button type="submit">Mark completed</button></form></div></article><?php endforeach;?><?php foreach($castingOffers as $offer):?>
        <article class="notification-row notification-casting-offer" id="casting-offer-<?= (int)$offer['id'] ?>">
          <div class="casting-offer-content">
            <div class="casting-offer-eyebrow"><span aria-hidden="true">✦</span> YOUR CASTING OFFER</div>
            <h3 class="casting-offer-production"><?= $esc((string)$offer['production_title']) ?></h3>
            <div class="casting-offer-role-label">YOU'VE BEEN OFFERED THE ROLE OF</div>
            <p class="casting-offer-role"><?= $esc((string)$offer['role_snapshot']) ?></p>
            <p class="casting-offer-student">For <?= $esc((string)$offer['first_name'].' '.(string)$offer['last_name']) ?></p>
            <p class="casting-offer-instruction">Please review your offer and let the production team know whether you accept or decline.</p>
            <form method="post" class="cast-offer-response" action="<?= $url('/notifications') ?>">
              <input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['notification_csrf']) ?>">
              <input type="hidden" name="action" value="respond_cast_offer">
              <input type="hidden" name="offer_id" value="<?= (int)$offer['id'] ?>">
              <div class="casting-offer-actions">
                <button class="casting-offer-accept" type="submit" name="decision" value="accepted">Accept offer</button>
                <button class="casting-offer-decline-toggle" type="button" aria-expanded="false" aria-controls="casting-offer-decline-<?= (int)$offer['id'] ?>">Decline offer</button>
              </div>
              <div class="casting-offer-decline-panel" id="casting-offer-decline-<?= (int)$offer['id'] ?>" hidden>
                <label for="casting-offer-note-<?= (int)$offer['id'] ?>">Please share a reason for declining <span>(required)</span></label>
                <textarea id="casting-offer-note-<?= (int)$offer['id'] ?>" name="response_note" maxlength="2000" rows="3" placeholder="Let the production team know why you're declining…"></textarea>
                <div class="casting-offer-decline-actions">
                  <button class="casting-offer-confirm-decline" type="submit" name="decision" value="declined" disabled>Confirm decline</button>
                  <button class="casting-offer-cancel" type="button">Cancel</button>
                </div>
              </div>
            </form>
          </div>
        </article>
        <?php endforeach;?><?php if($openForms): foreach($openForms as $form): ?><article class="notification-row <?= $form['status']==='missing'?'urgent':'' ?>"><i aria-hidden="true">!</i><div class="notification-form-copy"><b><?= $esc($form['title']) ?></b><span><?= $esc(ucwords(str_replace('_',' ',$form['status']))) ?><?= $form['due_at'] ? ' · Due '.$esc(date('M j',strtotime($form['due_at']))) : '' ?></span></div><a class="notification-form-action" href="<?= $url('/forms/view?id='.(int)$form['id']) ?>">Open form →</a></article><?php endforeach; elseif(!$castingOffers && !$todoItems): ?><div class="home-empty"><b>Nothing needs action.</b><span>You are caught up on assigned forms.</span></div><?php endif; ?></section>
        <section class="home-card"><header class="home-section-head"><div><span>YOUR ACTIVITY</span><h3>Recent updates</h3></div><b><?= count($notifications) ?></b></header><?php if(!$notifications): ?><div class="home-empty"><b>No personal updates yet.</b><span>Published production changes and future account notices will appear here.</span></div><?php else: foreach($notifications as $notice): ?><article class="persisted-notification<?= empty($notice['read_at'])?' unread':'' ?>"><div class="persisted-marker"></div><div><small><?= empty($notice['read_at'])?'NEW · ':'' ?><?= $esc(date('M j · g:i A',strtotime($notice['created_at']))) ?></small><h3><?= $esc($notice['title']) ?></h3><p><?= nl2br($esc($notice['body'])) ?></p><footer><?php if($notice['source_type']==='casting_offer' && (int)$notice['source_id']>0): ?><a href="<?= $url('/notifications?offer='.(int)$notice['source_id'].'#casting-offer-'.(int)$notice['source_id']) ?>">Review casting offer →</a><?php elseif($notice['action_path']): ?><a href="<?= $url($notice['action_path']) ?>">Open related page →</a><?php endif; ?><?php if(empty($notice['read_at'])): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= $esc((string)$_SESSION['notification_csrf']) ?>"><input type="hidden" name="action" value="mark_read"><input type="hidden" name="notification_id" value="<?= (int)$notice['id'] ?>"><button type="submit">Mark read</button></form><?php endif; ?></footer></div></article><?php endforeach; endif; ?></section></div>
        </div></main></div><script>
document.querySelectorAll('.cast-offer-response').forEach(function(form){
 const toggle=form.querySelector('.casting-offer-decline-toggle');
 const panel=form.querySelector('.casting-offer-decline-panel');
 const note=form.querySelector('textarea[name="response_note"]');
 const confirm=form.querySelector('.casting-offer-confirm-decline');
 const cancel=form.querySelector('.casting-offer-cancel');
 function hide(){panel.hidden=true;toggle.setAttribute('aria-expanded','false');note.required=false;note.value='';confirm.disabled=true;toggle.focus();}
 toggle.addEventListener('click',function(){panel.hidden=false;toggle.setAttribute('aria-expanded','true');note.required=true;note.focus();});
 note.addEventListener('input',function(){confirm.disabled=note.value.trim().length===0;});
 cancel.addEventListener('click',hide);
 form.querySelector('.casting-offer-accept').addEventListener('click',function(){note.required=false;});
});
</script><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php
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
        if ($action === 'complete_production_todo') {
            try {ProductionChecklistAssignmentService::complete($db,$userId,(int)($_POST['assignment_id']??0));self::flash('success','Task marked completed.');}
            catch(Throwable $e){self::flash('error',$e instanceof RuntimeException?$e->getMessage():'Unable to update task.');}
            self::redirect($basePath.'/notifications');
        } elseif ($action === 'respond_cast_offer') {
            try {CastingOfferService::respond($db,$userId,(int)($_POST['offer_id']??0),(string)($_POST['decision']??''),(string)($_POST['response_note']??''));self::flash('success','Your casting response was recorded.');}
            catch(Throwable $e){self::flash('error',$e instanceof RuntimeException?$e->getMessage():'Unable to record this response.');}
            self::redirect($basePath.'/notifications');
        } elseif ($action === 'mark_read') {
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
        $stmt = $db->prepare('SELECT id,source_type,source_id,title,body,action_path,read_at,created_at FROM app_notifications WHERE recipient_user_id = :user_id ORDER BY created_at DESC,id DESC LIMIT 100');
        $stmt->execute(['user_id'=>$userId]);
        return array_slice(ProductionNotificationAccess::filter($db,$userId,$stmt->fetchAll()),0,30);
    }

    private static function openForms(PDO $db, int $userId): array
    {
        $stmt = $db->prepare("SELECT fa.id,f.title,fa.status,fa.due_at FROM form_assignments fa JOIN forms f ON f.id=fa.form_id WHERE fa.user_id=:user_id AND fa.status<>'completed' ORDER BY fa.due_at");
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
