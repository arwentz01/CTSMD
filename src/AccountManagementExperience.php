<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/AccessPolicy.php';
require_once __DIR__.'/AppNavigation.php';
require_once __DIR__.'/MailService.php';

final class AccountManagementExperience
{
    private const ROUTES=['/admin/accounts','/admin/accounts/view'];
    public static function handles(string $route):bool{return in_array($route,self::ROUTES,true);}

    public static function render(string $route,string $basePath):never
    {
        Auth::startSession();$db=Database::connect(dirname(__DIR__));$user=Auth::currentUser($db);if(!$user||!AccessPolicy::canManageAccounts($user))self::forbidden();$_SESSION['accounts_csrf']??=bin2hex(random_bytes(24));
        if($_SERVER['REQUEST_METHOD']==='POST')self::handlePost($db,$user,$basePath,$route);
        $account=null;if($route==='/admin/accounts/view'){$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;$account=self::account($db,(int)$id);}
        self::page($db,$user,$basePath,$route,$account);
    }

    private static function handlePost(PDO $db,array $actor,string $basePath,string $route):never
    {
        if(!hash_equals((string)($_SESSION['accounts_csrf']??''),(string)($_POST['csrf_token']??''))){self::flash('error','Your session token expired.');self::redirect($basePath.'/admin/accounts');}
        $id=filter_input(INPUT_POST,'user_id',FILTER_VALIDATE_INT)?:0;$action=(string)($_POST['action']??'');
        try{
            if($id<1)throw new RuntimeException('Choose a valid account.');
            if($action==='invite'){
                $account=self::account($db,$id);if(!$account||empty($account['email']))throw new RuntimeException('That account needs an email address before invitation.');
                $token=Auth::createInvitation($db,$id,(int)$actor['id']);$link=self::absoluteUrl($basePath.'/activate?token='.$token);$_SESSION['accounts_invite_link']=$link;
                $text="Hi {$account['name']},\n\nYou have been invited to CTSMD Connect. Activate your account and choose a password using this private link:\n{$link}\n\nThis link expires in 7 days. If you were not expecting this invitation, you can ignore this message.\n\n— Children’s Theatre of Southern Maryland";
                MailService::queue($db,$id,(string)$account['email'],(string)$account['name'],'account_security','Your CTSMD Connect invitation',$text,null,'account-invite-'.$id.'-'.hash('sha256',$token));
                self::audit($db,(int)$actor['id'],'account.invited',$id,'Issued account invitation.');self::flash('success','Invitation created and queued for email delivery.');
            }elseif($action==='save_roles'){
                self::saveRoles($db,$actor,(int)$id,(array)($_POST['role_ids']??[]));self::flash('success','Account roles updated.');
            }elseif($action==='toggle_status'){
                self::toggleStatus($db,$actor,(int)$id);self::flash('success','Account availability updated.');
            }else throw new RuntimeException('Choose a valid account action.');
        }catch(RuntimeException $e){self::flash('error',$e->getMessage());}
        self::redirect($basePath.'/admin/accounts/view?id='.(int)$id);
    }

    private static function saveRoles(PDO $db,array $actor,int $userId,array $roleIds):void
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$roleIds),static fn(int $id):bool=>$id>0)));$roles=self::roles($db);$allowed=array_column($roles,'id');foreach($ids as $id)if(!in_array($id,$allowed,true))throw new RuntimeException('One or more roles are invalid.');
        $adminRoleId=0;foreach($roles as $role)if($role['code']==='administrator')$adminRoleId=(int)$role['id'];if($userId===(int)$actor['id']&&!in_array($adminRoleId,$ids,true))throw new RuntimeException('You cannot remove your own Administrator role.');
        $db->beginTransaction();try{$before=Auth::roles($db,$userId);$db->prepare('DELETE FROM auth_user_roles WHERE user_id=:user')->execute(['user'=>$userId]);$insert=$db->prepare('INSERT INTO auth_user_roles (user_id,role_id,assigned_by_user_id) VALUES (:user,:role,:actor)');foreach($ids as $roleId)$insert->execute(['user'=>$userId,'role'=>$roleId,'actor'=>(int)$actor['id']]);self::audit($db,(int)$actor['id'],'account.roles_updated',$userId,'Updated account roles.',['before'=>$before,'after'=>Auth::roles($db,$userId)]);$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($e instanceof RuntimeException)throw $e;throw new RuntimeException('Roles could not be updated.');}
    }

    private static function toggleStatus(PDO $db,array $actor,int $userId):void
    {
        if($userId===(int)$actor['id'])throw new RuntimeException('You cannot disable your own account.');$s=$db->prepare('SELECT account_status,password_hash FROM users WHERE id=:id LIMIT 1');$s->execute(['id'=>$userId]);$row=$s->fetch();if(!$row)throw new RuntimeException('That account no longer exists.');
        if($row['account_status']==='disabled'){
            $managed=$db->prepare("SELECT 1 FROM family_relationships fr JOIN auth_user_roles ur ON ur.user_id=fr.student_user_id JOIN auth_roles r ON r.id=ur.role_id AND r.code='student' WHERE fr.student_user_id=:user AND fr.status='active' LIMIT 1");$managed->execute(['user'=>$userId]);$next=$managed->fetchColumn()?'managed':(empty($row['password_hash'])?'invited':'active');
        }else{$next='disabled';}
        $db->prepare('UPDATE users SET account_status=:status WHERE id=:id')->execute(['status'=>$next,'id'=>$userId]);self::audit($db,(int)$actor['id'],$next==='disabled'?'account.disabled':'account.enabled',$userId,'Changed account availability.',['status'=>$next]);
    }

    private static function accounts(PDO $db):array{return $db->query("SELECT u.id,CONCAT(u.first_name,' ',u.last_name) name,u.email,u.initials,u.display_role,u.account_status,u.last_login_at,GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ', ') role_names FROM users u LEFT JOIN auth_user_roles ur ON ur.user_id=u.id LEFT JOIN auth_roles r ON r.id=ur.role_id WHERE u.active=1 GROUP BY u.id,u.first_name,u.last_name,u.email,u.initials,u.display_role,u.account_status,u.last_login_at ORDER BY u.last_name,u.first_name")->fetchAll();}
    private static function account(PDO $db,int $id):?array{if($id<1)return null;$s=$db->prepare("SELECT id,CONCAT(first_name,' ',last_name) name,email,initials,display_role,account_status,last_login_at,password_changed_at FROM users WHERE id=:id AND active=1 LIMIT 1");$s->execute(['id'=>$id]);$a=$s->fetch();if(!$a)return null;$a['roles']=Auth::roles($db,$id);return $a;}
    private static function roles(PDO $db):array{return $db->query("SELECT id,code,name,description FROM auth_roles WHERE active=1 ORDER BY FIELD(code,'administrator','production_staff','moderator','safeguarding','volunteer','member','student'),name")->fetchAll();}

    private static function page(PDO $db,array $user,string $basePath,string $route,?array $account):never
    {
        $url=static fn(string $p):string=>($basePath?:'').$p;$e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');$flash=$_SESSION['accounts_flash']??null;$invite=$_SESSION['accounts_invite_link']??null;unset($_SESSION['accounts_flash'],$_SESSION['accounts_invite_link']);$roles=self::roles($db);$accounts=self::accounts($db);
        header('Content-Type:text/html; charset=utf-8');?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Account & Access · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/account-access.css') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar($route,$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Administration',$account?$account['name']:'Account & Access',$basePath);?><div class="acct-page"><?php if($flash):?><div class="acct-flash <?= $e($flash['type']) ?>"><?= $e($flash['message']) ?></div><?php endif;?><?php if($invite):?><section class="acct-invite"><small>ACTIVATION LINK · ALSO QUEUED FOR EMAIL</small><code><?= $e($invite) ?></code><p>The link is shown here for administrative recovery/testing. Normal delivery now uses the CTSMD email queue.</p></section><?php endif;?>
        <?php if($route==='/admin/accounts'):?><section class="acct-hero"><small>AUTHENTICATION + RBAC</small><h2>Accounts are people. Roles decide authority.</h2><p>Production membership controls show participation; account roles control what someone is allowed to administer.</p></section><div class="acct-list"><?php foreach($accounts as $a):?><a href="<?= $url('/admin/accounts/view?id='.(int)$a['id']) ?>"><span class="acct-avatar"><?= $e($a['initials']) ?></span><span><b><?= $e($a['name']) ?></b><small><?= $e($a['email']?:'Guardian-managed profile · no login email') ?></small><em><?= $e($a['role_names']?:'No RBAC roles') ?></em></span><strong class="<?= $e($a['account_status']) ?>"><?= $e(ucwords(str_replace('_',' ',$a['account_status']))) ?></strong></a><?php endforeach;?></div>
        <?php elseif(!$account):?><section class="acct-empty"><h2>Account not found.</h2><a href="<?= $url('/admin/accounts') ?>">Back to accounts</a></section>
        <?php else:?><section class="acct-head"><div><small><?= $e(strtoupper(str_replace('_',' ',$account['account_status']))) ?></small><h2><?= $e($account['name']) ?></h2><p><?= $e($account['email']?:'Guardian-managed profile · no login email') ?> · <?= $e($account['display_role']) ?></p></div><a href="<?= $url('/admin/accounts') ?>">← All accounts</a></section><div class="acct-layout"><section class="acct-panel"><h3>Authorization roles</h3><p>These roles—not the display label above—control administrator permissions.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= $e((string)$_SESSION['accounts_csrf']) ?>"><input type="hidden" name="user_id" value="<?= (int)$account['id'] ?>"><?php foreach($roles as $role):?><label class="acct-role"><input type="checkbox" name="role_ids[]" value="<?= (int)$role['id'] ?>"<?= in_array($role['code'],$account['roles'],true)?' checked':'' ?>><span><b><?= $e($role['name']) ?></b><small><?= $e((string)$role['description']) ?></small></span></label><?php endforeach;?><button name="action" value="save_roles" type="submit">Save roles</button></form></section><aside class="acct-panel"><h3>Account lifecycle</h3><dl><div><dt>Status</dt><dd><?= $e(ucwords(str_replace('_',' ',$account['account_status']))) ?></dd></div><div><dt>Last login</dt><dd><?= $account['last_login_at']?$e(date('M j, Y · g:i A',strtotime($account['last_login_at']))):'Never' ?></dd></div></dl><?php if($account['email']&&$account['account_status']!=='managed'):?><form method="post"><input type="hidden" name="csrf_token" value="<?= $e((string)$_SESSION['accounts_csrf']) ?>"><input type="hidden" name="user_id" value="<?= (int)$account['id'] ?>"><button name="action" value="invite" type="submit">Issue & email new invitation</button></form><?php endif;?><?php if((int)$account['id']!==(int)$user['id']):?><form method="post"><input type="hidden" name="csrf_token" value="<?= $e((string)$_SESSION['accounts_csrf']) ?>"><input type="hidden" name="user_id" value="<?= (int)$account['id'] ?>"><button class="danger" name="action" value="toggle_status" type="submit"><?= $account['account_status']==='disabled'?'Enable account':'Disable account' ?></button></form><?php endif;?></aside></div><?php endif;?></div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php exit;
    }

    private static function audit(PDO $db,int $actor,string $event,int $subject,string $summary,array $meta=[]):void{$s=$db->prepare("INSERT INTO audit_events (actor_user_id,event_type,subject_type,subject_id,summary,metadata_json) VALUES (:actor,:event,'user',:subject,:summary,:meta)");$s->execute(['actor'=>$actor,'event'=>$event,'subject'=>$subject,'summary'=>$summary,'meta'=>$meta?json_encode($meta,JSON_THROW_ON_ERROR):null]);}
    private static function absoluteUrl(string $path):string{$configured=rtrim((string)(getenv('APP_URL')?:''),'/');if($configured!==''){ $base=(string)(getenv('APP_BASE_PATH')?:'');if($base!==''&&str_ends_with(strtolower($configured),strtolower($base)))return $configured.substr($path,strlen($base));return $configured.$path;}$scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';$host=(string)($_SERVER['HTTP_HOST']??'localhost');return $scheme.'://'.$host.$path;}
    private static function flash(string $type,string $message):void{$_SESSION['accounts_flash']=['type'=>$type,'message'=>$message];}
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
    private static function forbidden():never{http_response_code(403);exit('Restricted');}
}
