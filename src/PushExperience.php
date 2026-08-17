<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/AppNavigation.php';
require_once __DIR__.'/PushService.php';
require_once __DIR__.'/PushSchemaGuard.php';

final class PushExperience
{
    private const ROUTES=['/push-settings','/push/subscribe','/push/unsubscribe','/push/test'];
    public static function handles(string $route):bool{return in_array($route,self::ROUTES,true);}

    public static function render(string $route,string $basePath):never
    {
        Auth::startSession();
        $db=Database::connect(dirname(__DIR__));
        $user=Auth::currentUser($db);
        if(!$user){header('Location: '.($basePath?:'').'/login',true,303);exit;}
        PushSchemaGuard::requireCurrent($db);
        $_SESSION['push_csrf']??=bin2hex(random_bytes(24));
        if($route==='/push/subscribe')self::subscribe($db,$user);
        if($route==='/push/unsubscribe')self::unsubscribe($db,$user);
        if($route==='/push/test')self::test($db,$user,$basePath);
        self::page($db,$user,$basePath);
    }

    private static function subscribe(PDO $db,array $user):never
    {
        self::jsonCsrf();
        $data=json_decode((string)file_get_contents('php://input'),true);
        if(!is_array($data))self::json(['ok'=>false,'message'=>'Invalid subscription payload.'],400);
        try{PushService::subscribe($db,(int)$user['id'],$data,(string)($_SERVER['HTTP_USER_AGENT']??''));self::json(['ok'=>true]);}
        catch(Throwable $e){self::json(['ok'=>false,'message'=>$e->getMessage()],422);}
    }

    private static function unsubscribe(PDO $db,array $user):never
    {
        self::jsonCsrf();
        $data=json_decode((string)file_get_contents('php://input'),true);
        $endpoint=trim((string)($data['endpoint']??''));
        if($endpoint!=='')PushService::unsubscribe($db,(int)$user['id'],$endpoint);
        self::json(['ok'=>true]);
    }

    private static function test(PDO $db,array $user,string $basePath):never
    {
        if($_SERVER['REQUEST_METHOD']==='POST'&&hash_equals((string)($_SESSION['push_csrf']??''),(string)($_POST['csrf_token']??''))){
            try{
                $stmt=$db->prepare("INSERT INTO app_notifications (recipient_user_id,source_type,source_id,title,body,action_path) VALUES (:user,'push_test',NULL,'CTSMD Connect','Push notifications are ready on this device.','/notifications')");
                $stmt->execute(['user'=>(int)$user['id']]);
                $_SESSION['push_flash']=['success','Test notification created. Run the push queue processor to deliver it to this device.'];
            }catch(Throwable $e){$_SESSION['push_flash']=['error',$e->getMessage()];}
        }
        header('Location: '.($basePath?:'').'/push-settings',true,303);exit;
    }

    private static function page(PDO $db,array $user,string $basePath):never
    {
        $url=static fn(string $p):string=>($basePath?:'').$p;
        $e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');
        $s=$db->prepare("SELECT id,device_label,platform,status,last_success_at,created_at FROM push_subscriptions WHERE user_id=:user ORDER BY updated_at DESC");$s->execute(['user'=>(int)$user['id']]);$devices=$s->fetchAll();
        $flash=$_SESSION['push_flash']??null;unset($_SESSION['push_flash']);
        header('Content-Type:text/html; charset=utf-8');
        ?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>Mobile notifications · CTSMD Connect</title><link rel="stylesheet" href="<?=$url('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$url('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$url('/assets/css/push-settings.css')?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/notification-preferences',$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Your account','Mobile notifications',$basePath);?><div class="push-page" data-push-page data-public-key="<?=$e(PushService::publicKey())?>" data-csrf="<?=$e((string)$_SESSION['push_csrf'])?>" data-base="<?=$e($basePath)?>"><?php if($flash):?><div class="push-flash <?=$e($flash[0])?>"><?=$e($flash[1])?></div><?php endif;?><section class="push-hero"><small>IOS · IPADOS · ANDROID</small><h2>CTSMD updates on your phone.</h2><p>Install Connect, then enable notifications for schedule changes, messages, forms, Community and volunteer updates.</p></section><section class="push-device-card"><div><span class="push-device-icon">◉</span><div><small>THIS DEVICE</small><h3 data-push-status>Checking notification support…</h3><p data-push-detail>Connect only asks for notification permission after you choose Enable.</p></div></div><button class="button" type="button" data-push-enable>Enable notifications</button><button class="button" type="button" data-push-local-test hidden>Show browser test</button><button type="button" class="push-disable" data-push-disable hidden>Disable on this device</button></section><section class="push-diagnostics"><header><div><small>DIAGNOSTICS</small><h3>Notification diagnostics</h3></div><button type="button" data-push-diagnostics>Refresh diagnostics</button></header><div class="push-diagnostic-grid"><article><span>Browser permission</span><b data-diag-permission>Checking…</b></article><article><span>Service worker</span><b data-diag-worker>Checking…</b></article><article><span>Push subscription</span><b data-diag-subscription>Checking…</b></article><article><span>Push service</span><b data-diag-endpoint>Checking…</b></article></div><div class="push-diagnostic-actions"><button class="button" type="button" data-push-page-test>Direct page notification</button><button class="button" type="button" data-push-worker-test>Service-worker notification</button></div><p data-diag-result>Use these tests to separate browser/OS presentation from remote Web Push delivery.</p></section><section class="push-install"><h3>Install Connect</h3><div><article><b>iPhone / iPad</b><p>In Safari choose Share → Add to Home Screen, launch Connect from the new icon, then enable notifications here.</p></article><article><b>Android</b><p>Open this Mobile Notifications screen once in Chrome, then choose Install app / Add to Home screen. Launch Connect from the installed icon and enable notifications here.</p></article></div></section><section class="push-devices"><header><h3>Your notification devices</h3><a href="<?=$url('/notification-preferences')?>">Preferences</a></header><?php if(!$devices):?><p class="push-empty">No devices registered yet.</p><?php else:foreach($devices as $device):?><article><span><?=$device['platform']==='ios'?'◉':($device['platform']==='android'?'◆':'◇')?></span><div><b><?=$e((string)$device['device_label'])?></b><small><?=ucfirst($e((string)$device['status']))?> · Added <?=date('M j, Y',strtotime((string)$device['created_at']))?></small></div><?php if($device['last_success_at']):?><em>Last push <?=date('M j',strtotime((string)$device['last_success_at']))?></em><?php endif;?></article><?php endforeach;endif;?></section><form class="push-test" method="post" action="<?=$url('/push/test')?>"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['push_csrf'])?>"><button type="submit">Queue a test notification</button></form></div></main></div><script src="<?=$url('/assets/js/unified-navigation.js')?>"></script><script src="<?=$url('/assets/js/push-client.js')?>"></script></body></html><?php exit;
    }

    private static function jsonCsrf():void{$token=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');if(!hash_equals((string)($_SESSION['push_csrf']??''),$token))self::json(['ok'=>false,'message'=>'Session expired. Refresh and try again.'],419);}
    private static function json(array $data,int $status=200):never{http_response_code($status);header('Content-Type:application/json');echo json_encode($data);exit;}
}
