<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/AppNavigation.php';

final class NotificationPreferenceExperience
{
    public static function handles(string $route):bool{return $route==='/notification-preferences';}

    public static function render(string $basePath):never
    {
        Auth::startSession();$db=Database::connect(dirname(__DIR__));$user=Auth::currentUser($db);if(!$user){header('Location: '.($basePath?:'').'/login',true,303);exit;}
        $_SESSION['notification_preferences_csrf']??=bin2hex(random_bytes(24));
        if($_SERVER['REQUEST_METHOD']==='POST')self::save($db,$user,$basePath);
        $prefs=self::prefs($db,(int)$user['id']);$flash=$_SESSION['notification_preferences_flash']??null;unset($_SESSION['notification_preferences_flash']);
        $url=static fn(string $p):string=>($basePath?:'').$p;$e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');
        header('Content-Type:text/html; charset=utf-8');?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Notification preferences · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/notification-preferences.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/notification-preferences',$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Your account','Notification preferences',$basePath);?><div class="np-page"><?php if($flash):?><div class="np-flash <?= $e($flash['type']) ?>"><?= $e($flash['message']) ?></div><?php endif;?><section class="np-hero"><small>EMAIL DELIVERY</small><h2>Choose what deserves your inbox.</h2><p>Account-security messages such as invitations and password resets always send when needed. Everything else can be tailored here.</p></section><form class="np-card" method="post"><input type="hidden" name="csrf_token" value="<?= $e((string)$_SESSION['notification_preferences_csrf']) ?>"><label class="np-master"><input type="checkbox" name="email_enabled"<?= $prefs['email_enabled']?' checked':'' ?>><span><b>Email notifications</b><small>Master switch for non-security email.</small></span></label><div class="np-grid"><?php foreach(['email_schedule'=>'Schedule & production updates','email_forms'=>'Forms & due dates','email_volunteer'=>'Volunteer shifts, training & credentials','email_community'=>'Community announcements'] as $key=>$label):?><label><input type="checkbox" name="<?= $key ?>"<?= $prefs[$key]?' checked':'' ?>><span><b><?= $e($label) ?></b></span></label><?php endforeach;?></div><label class="np-select"><span><b>Delivery style</b><small>Immediate is the current default. Daily digest is stored now and will be used by digest-capable notification types as they are added.</small></span><select name="digest_mode"><option value="immediate"<?= $prefs['digest_mode']==='immediate'?' selected':'' ?>>Immediate</option><option value="daily"<?= $prefs['digest_mode']==='daily'?' selected':'' ?>>Daily digest</option></select></label><button type="submit">Save preferences</button></form></div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php exit;
    }

    private static function save(PDO $db,array $user,string $basePath):never
    {
        if(!hash_equals((string)($_SESSION['notification_preferences_csrf']??''),(string)($_POST['csrf_token']??''))){self::flash('error','Your session token expired.');self::redirect(($basePath?:'').'/notification-preferences');}
        $digest=in_array((string)($_POST['digest_mode']??'immediate'),['immediate','daily'],true)?(string)$_POST['digest_mode']:'immediate';
        $stmt=$db->prepare("INSERT INTO notification_preferences (user_id,email_enabled,email_schedule,email_forms,email_volunteer,email_community,email_account_security,digest_mode) VALUES (:user,:enabled,:schedule,:forms,:volunteer,:community,1,:digest) ON DUPLICATE KEY UPDATE email_enabled=VALUES(email_enabled),email_schedule=VALUES(email_schedule),email_forms=VALUES(email_forms),email_volunteer=VALUES(email_volunteer),email_community=VALUES(email_community),email_account_security=1,digest_mode=VALUES(digest_mode)");
        $stmt->execute(['user'=>(int)$user['id'],'enabled'=>isset($_POST['email_enabled'])?1:0,'schedule'=>isset($_POST['email_schedule'])?1:0,'forms'=>isset($_POST['email_forms'])?1:0,'volunteer'=>isset($_POST['email_volunteer'])?1:0,'community'=>isset($_POST['email_community'])?1:0,'digest'=>$digest]);
        self::flash('success','Notification preferences saved.');self::redirect(($basePath?:'').'/notification-preferences');
    }
    private static function prefs(PDO $db,int $userId):array{$s=$db->prepare('SELECT * FROM notification_preferences WHERE user_id=:user LIMIT 1');$s->execute(['user'=>$userId]);return $s->fetch()?:['email_enabled'=>1,'email_schedule'=>1,'email_forms'=>1,'email_volunteer'=>1,'email_community'=>1,'digest_mode'=>'immediate'];}
    private static function flash(string $type,string $message):void{$_SESSION['notification_preferences_flash']=['type'=>$type,'message'=>$message];}
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
}
