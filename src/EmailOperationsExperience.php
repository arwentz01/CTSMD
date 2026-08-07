<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/AccessPolicy.php';
require_once __DIR__.'/AppNavigation.php';
require_once __DIR__.'/MailService.php';
require_once __DIR__.'/NotificationReminderService.php';

final class EmailOperationsExperience
{
    public static function handles(string $route):bool{return $route==='/admin/email';}
    public static function render(string $basePath):never
    {
        Auth::startSession();$db=Database::connect(dirname(__DIR__));$user=Auth::currentUser($db);if(!$user||!AccessPolicy::canManageAccounts($user))self::forbidden();$_SESSION['email_ops_csrf']??=bin2hex(random_bytes(24));
        if($_SERVER['REQUEST_METHOD']==='POST')self::post($db,$user,$basePath);
        $counts=[];foreach(['queued','sending','sent','failed'] as $status){$s=$db->prepare('SELECT COUNT(*) FROM email_queue WHERE status=:status');$s->execute(['status'=>$status]);$counts[$status]=(int)$s->fetchColumn();}
        $recent=$db->query("SELECT eq.id,eq.recipient_email,eq.recipient_name,eq.category,eq.subject,eq.status,eq.attempts,eq.created_at,eq.sent_at,eq.last_error,edl.transport,edl.outcome delivery_outcome FROM email_queue eq LEFT JOIN email_delivery_log edl ON edl.id=(SELECT x.id FROM email_delivery_log x WHERE x.email_queue_id=eq.id ORDER BY x.id DESC LIMIT 1) ORDER BY eq.id DESC LIMIT 80")->fetchAll();
        $flash=$_SESSION['email_ops_flash']??null;unset($_SESSION['email_ops_flash']);$url=static fn(string $p):string=>($basePath?:'').$p;$e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');$driver=strtolower((string)(getenv('MAIL_DRIVER')?:'log'));
        header('Content-Type:text/html; charset=utf-8');?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Email Operations · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/email-operations.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/admin/email',$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Administration','Email Operations',$basePath);?><div class="emailops-page"><?php if($flash):?><div class="emailops-flash <?= $e($flash['type']) ?>"><?= $e($flash['message']) ?></div><?php endif;?><section class="emailops-hero"><div><small>MAIL TRANSPORT · <?= $e(strtoupper($driver)) ?></small><h2>Know what is waiting, what sent, and what failed.</h2><p>Local development defaults to the log transport. Production can use SMTP or PHP mail without changing notification workflows.</p></div><div class="emailops-actions"><form method="post"><input type="hidden" name="csrf_token" value="<?= $e((string)$_SESSION['email_ops_csrf']) ?>"><button name="action" value="queue_reminders">Queue due reminders</button><button name="action" value="process">Process 25 now</button></form></div></section><section class="emailops-stats"><?php foreach($counts as $label=>$count):?><article><small><?= $e(strtoupper($label)) ?></small><b><?= $count ?></b></article><?php endforeach;?></section><section class="emailops-list"><header><h3>Recent outbound email</h3></header><?php if(!$recent):?><div class="emailops-empty">No outbound email has been queued yet.</div><?php endif;?><?php foreach($recent as $row):?><article><div><small><?= $e(strtoupper($row['category'])) ?> · #<?= (int)$row['id'] ?></small><b><?= $e($row['subject']) ?></b><span><?= $e(($row['recipient_name']?$row['recipient_name'].' · ':'').$row['recipient_email']) ?></span></div><div class="emailops-meta"><strong class="<?= $e($row['status']) ?>"><?= $e(strtoupper($row['status'])) ?></strong><small><?= $e($row['transport']?:'Not attempted') ?><?= $row['attempts']?' · '.$e((string)$row['attempts']).' attempt'.((int)$row['attempts']===1?'':'s'):'' ?></small><?php if($row['last_error']):?><em><?= $e($row['last_error']) ?></em><?php endif;?></div></article><?php endforeach;?></section></div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php exit;
    }
    private static function post(PDO $db,array $user,string $basePath):never
    {
        if(!hash_equals((string)($_SESSION['email_ops_csrf']??''),(string)($_POST['csrf_token']??''))){self::flash('error','Your session token expired.');self::redirect(($basePath?:'').'/admin/email');}
        try{$action=(string)($_POST['action']??'');if($action==='process'){$r=MailService::process($db,dirname(__DIR__),25);self::flash('success',"Processed {$r['processed']} email(s): {$r['sent']} sent, {$r['failed']} failed.");}elseif($action==='queue_reminders'){$r=NotificationReminderService::queueDue($db,(string)(getenv('APP_URL')?:'http://localhost/CTSMD'));self::flash('success','Reminder scan complete: '.array_sum($r).' email(s) queued.');}else throw new RuntimeException('That email action is unavailable.');}catch(Throwable $e){self::flash('error',$e->getMessage());}self::redirect(($basePath?:'').'/admin/email');
    }
    private static function flash(string $type,string $message):void{$_SESSION['email_ops_flash']=['type'=>$type,'message'=>$message];}
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
    private static function forbidden():never{http_response_code(403);exit('Restricted');}
}
