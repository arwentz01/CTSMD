<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/MailService.php';
require_once __DIR__.'/PlatformOnboardingExperience.php';

final class AuthExperience
{
    private const ROUTES=['/login','/logout','/activate','/forgot-password','/reset-password','/join','/verify-email'];
    public static function handles(string $route):bool{return in_array($route,self::ROUTES,true);}

    public static function render(string $route,string $basePath):never
    {
        if(PlatformOnboardingExperience::isPublic($route))PlatformOnboardingExperience::render($route,$basePath);
        Auth::startSession();$db=Database::connect(dirname(__DIR__));$_SESSION['auth_csrf']??=bin2hex(random_bytes(24));
        if($route==='/logout'){if($_SERVER['REQUEST_METHOD']==='POST')self::csrf();Auth::logout();self::redirect($basePath.'/login');}
        if($_SERVER['REQUEST_METHOD']==='POST')self::handlePost($db,$route,$basePath);
        self::page($db,$route,$basePath);
    }

    private static function handlePost(PDO $db,string $route,string $basePath):never
    {
        self::csrf();
        try{
            if($route==='/login'){
                Auth::login($db,(string)($_POST['email']??''),(string)($_POST['password']??''));
                self::redirect(self::safeReturn($basePath,(string)($_POST['return_to']??'')));
            }
            if($route==='/activate'){
                $token=(string)($_POST['token']??'');$password=(string)($_POST['password']??'');$confirm=(string)($_POST['password_confirm']??'');if(!hash_equals($password,$confirm))throw new RuntimeException('Passwords do not match.');$userId=Auth::acceptInvitation($db,$token,$password);Auth::establishSession($db,$userId);self::redirect($basePath.'/app');
            }
            if($route==='/forgot-password'){
                $email=mb_strtolower(trim((string)($_POST['email']??'')));$token=Auth::createPasswordReset($db,$email);
                if($token){$s=$db->prepare("SELECT id,CONCAT(first_name,' ',last_name) name,email FROM users WHERE LOWER(email)=:email AND active=1 LIMIT 1");$s->execute(['email'=>$email]);$user=$s->fetch();if($user){$link=self::absoluteUrl($basePath.'/reset-password?token='.$token);$body="Hi {$user['name']},\n\nA password reset was requested for your CTSMD Connect account. Use this private link within 2 hours:\n{$link}\n\nIf you did not request this, you can ignore this email.\n\n— Children’s Theatre of Southern Maryland";MailService::queue($db,(int)$user['id'],(string)$user['email'],(string)$user['name'],'account_security','Reset your CTSMD Connect password',$body,null,'password-reset-'.hash('sha256',$token));if(Auth::localIdentitySwitchEnabled())$_SESSION['auth_local_reset']=$link;}}
                self::flash('success','If an active account matches that email, reset instructions have been queued for delivery.');
                self::redirect($basePath.'/forgot-password');
            }
            if($route==='/reset-password'){
                $password=(string)($_POST['password']??'');$confirm=(string)($_POST['password_confirm']??'');if(!hash_equals($password,$confirm))throw new RuntimeException('Passwords do not match.');Auth::resetPassword($db,(string)($_POST['token']??''),$password);self::flash('success','Password updated. You can sign in now.');self::redirect($basePath.'/login');
            }
        }catch(RuntimeException $e){self::flash('error',$e->getMessage());$suffix='';if(in_array($route,['/activate','/reset-password'],true))$suffix='?token='.rawurlencode((string)($_POST['token']??''));self::redirect($basePath.$route.$suffix);}
        self::redirect($basePath.'/login');
    }

    private static function page(PDO $db,string $route,string $basePath):never
    {
        $e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');$url=static fn(string $p):string=>($basePath?:'').$p;$flash=$_SESSION['auth_flash']??null;$localReset=$_SESSION['auth_local_reset']??null;unset($_SESSION['auth_flash'],$_SESSION['auth_local_reset']);$token=(string)($_GET['token']??'');$invitation=$route==='/activate'?Auth::invitation($db,$token):null;$returnTo=(string)($_GET['return_to']??'');
        $title=match($route){'/activate'=>'Activate your account','/forgot-password'=>'Reset your password','/reset-password'=>'Choose a new password',default=>'Welcome back'};
        header('Content-Type:text/html; charset=utf-8');?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#a6192e"><title><?= $e($title) ?> · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/auth.css') ?>"></head><body><main class="auth-shell"><section class="auth-brand"><small>CHILDREN'S THEATRE OF SOUTHERN MARYLAND</small><h1>CTSMD <span>Connect</span></h1><p>One secure place for productions, families, volunteers, schedules and communication.</p></section><section class="auth-card"><header><small>CTSMD CONNECT</small><h2><?= $e($title) ?></h2></header><?php if($flash):?><div class="auth-flash <?= $e($flash['type']) ?>"><?= $e($flash['message']) ?></div><?php endif;?>
        <?php if($route==='/login'):?>
        <form method="post"><input type="hidden" name="csrf_token" value="<?= $e((string)$_SESSION['auth_csrf']) ?>"><input type="hidden" name="return_to" value="<?= $e($returnTo) ?>"><label>Email<input type="email" name="email" autocomplete="email" required></label><label>Password<input type="password" name="password" autocomplete="current-password" required></label><button type="submit">Sign in</button></form><footer><a href="<?= $url('/join') ?>">Create a parent/guardian account</a><a href="<?= $url('/forgot-password') ?>">Forgot password?</a><?php if(Auth::localIdentitySwitchEnabled()):?><a href="<?= $url('/dev/identity') ?>">Local test identities</a><?php endif;?></footer>
        <?php elseif($route==='/activate'):?>
        <?php if(!$invitation):?><div class="auth-empty"><b>This invitation is invalid or expired.</b><p>Ask a CTSMD administrator to issue a new invitation.</p></div><?php else:?><p class="auth-intro">Hi <?= $e($invitation['first_name']) ?>. Create a password to activate <b><?= $e($invitation['email']) ?></b>.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= $e((string)$_SESSION['auth_csrf']) ?>"><input type="hidden" name="token" value="<?= $e($token) ?>"><label>New password<input type="password" name="password" minlength="8" autocomplete="new-password" required><small>At least 8 characters.</small></label><label>Confirm password<input type="password" name="password_confirm" minlength="8" autocomplete="new-password" required></label><button type="submit">Activate account</button></form><?php endif;?>
        <?php elseif($route==='/forgot-password'):?>
        <p class="auth-intro">Enter your account email. If it matches an active account, CTSMD will email a time-limited reset link.</p><form method="post"><input type="hidden" name="csrf_token" value="<?= $e((string)$_SESSION['auth_csrf']) ?>"><label>Email<input type="email" name="email" autocomplete="email" required></label><button type="submit">Request password reset</button></form><?php if($localReset):?><div class="auth-dev"><b>Local development reset link</b><code><?= $e($localReset) ?></code></div><?php endif;?><footer><a href="<?= $url('/login') ?>">Back to sign in</a></footer>
        <?php else:?>
        <form method="post"><input type="hidden" name="csrf_token" value="<?= $e((string)$_SESSION['auth_csrf']) ?>"><input type="hidden" name="token" value="<?= $e($token) ?>"><label>New password<input type="password" name="password" minlength="8" autocomplete="new-password" required></label><label>Confirm password<input type="password" name="password_confirm" minlength="8" autocomplete="new-password" required></label><button type="submit">Update password</button></form>
        <?php endif;?></section></main></body></html><?php exit;
    }

    private static function csrf():void{if(!hash_equals((string)($_SESSION['auth_csrf']??''),(string)($_POST['csrf_token']??'')))throw new RuntimeException('Your session expired. Please try again.');}
    private static function absoluteUrl(string $path):string{$configured=rtrim((string)(getenv('APP_URL')?:''),'/');$base=(string)(getenv('APP_BASE_PATH')?:'');if($configured!==''){if($base!==''&&str_ends_with(strtolower($configured),strtolower($base)))return $configured.substr($path,strlen($base));return $configured.$path;}$scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';return $scheme.'://'.($_SERVER['HTTP_HOST']??'localhost').$path;}
    private static function flash(string $type,string $message):void{$_SESSION['auth_flash']=['type'=>$type,'message'=>$message];}
    private static function safeReturn(string $basePath,string $return):string{$return=trim($return);if($return!==''&&str_starts_with($return,'/')&&!str_starts_with($return,'//'))return ($basePath?:'').$return;return ($basePath?:'').'/app';}
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
}
