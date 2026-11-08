<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__.'/MemberUI.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/PlatformRegistrationService.php';
require_once __DIR__ . '/RateLimiter.php';

final class PlatformOnboardingExperience
{
    private const ROUTES=['/join','/verify-email','/onboarding','/family/manage','/student/activate'];
    public static function handles(string $route):bool{return in_array($route,self::ROUTES,true);}
    public static function isPublic(string $route):bool{return in_array($route,['/join','/verify-email','/student/activate'],true);}

    public static function render(string $route,string $basePath):never
    {
        Auth::startSession();$db=Database::connect(dirname(__DIR__));$_SESSION['platform_onboarding_csrf']??=bin2hex(random_bytes(24));$user=self::isPublic($route)?null:Auth::currentUser($db);if(!self::isPublic($route)&&!$user)self::redirect($basePath.'/login?return_to='.rawurlencode($route));if($user&&Auth::hasRole($user,'student'))self::forbidden();if($user&&$route==='/family/manage'){
            $check=$db->prepare("SELECT relationship_type FROM family_relationships WHERE guardian_user_id=:id AND status='active'");
            $check->execute(['id'=>(int)$user['id']]);$types=$check->fetchAll(PDO::FETCH_COLUMN);
            if(in_array('caregiver',$types,true) && !array_intersect($types,['parent','guardian']))self::redirect($basePath.'/family/caregiver-schedule');
        }if($_SERVER['REQUEST_METHOD']==='POST')self::handlePost($db,$route,$basePath,$user);if($route==='/join')self::joinPage($basePath);if($route==='/verify-email')self::verifyPage($db,$basePath);if($route==='/student/activate')self::studentActivationPage($db,$basePath);self::householdPage($db,$basePath,$user,$route);
    }

    private static function handlePost(PDO $db,string $route,string $basePath,?array $user):never
    {
        self::csrf();try{
            if($route==='/join'){RateLimiter::enforce($db,'join',RateLimiter::requestSubject((string)($_POST['email']??'')),5,3600,3600);PlatformRegistrationService::registerAdult($db,$_POST,$basePath);$_SESSION['platform_onboarding_flash']=['type'=>'success','message'=>'Check your email for a verification link.'];self::redirect($basePath.'/join?sent=1');}
            if($route==='/student/activate'){$token=(string)($_POST['token']??'');RateLimiter::enforce($db,'student-activate',RateLimiter::requestSubject(hash('sha256',$token)),10,3600,3600);$childId=PlatformRegistrationService::activateStudent($db,$token,(string)($_POST['password']??''),(string)($_POST['password_confirm']??''));Auth::establishSession($db,$childId);self::redirect($basePath.'/app');}
            if($route==='/verify-email'){$token=(string)($_POST['token']??'');$subject=RateLimiter::requestSubject(hash('sha256',$token));RateLimiter::enforce($db,'verify-email',$subject,10,3600,3600);$userId=PlatformRegistrationService::verify($db,$token);RateLimiter::clear($db,'verify-email',$subject);Auth::establishSession($db,$userId);self::redirect($basePath.'/onboarding');}
            if($route==='/onboarding'||$route==='/family/manage'){
                if(!$user)throw new RuntimeException('Sign in to manage your household.');$action=(string)($_POST['action']??'');
                if($action==='add_child'){PlatformRegistrationService::addManagedChild($db,$user,array_merge($_POST,['base_path'=>$basePath]));$_SESSION['platform_onboarding_flash']=['type'=>'success','message'=>'Child profile added. If you provided an email address, an account setup invitation was queued.'];self::redirect($basePath.'/family/manage');}
                if($action==='complete_onboarding'){PlatformRegistrationService::completeOnboarding($db,(int)$user['id']);$_SESSION['platform_onboarding_flash']=['type'=>'success','message'=>'Household setup saved. Your CTSMD membership is pending staff approval.'];self::redirect($basePath.'/family/manage');}
            }
            throw new RuntimeException('Choose a valid account action.');
        }catch(RateLimitExceeded $e){http_response_code(429);header('Retry-After: '.$e->retryAfter);header('Content-Type: text/plain; charset=utf-8');exit($e->getMessage());
        }catch(RuntimeException $e){$_SESSION['platform_onboarding_flash']=['type'=>'error','message'=>$e->getMessage()];self::redirect($basePath.$route.($route==='/student/activate'&&preg_match('/^[a-f0-9]{64}$/',(string)($_POST['token']??''))?'?token='.rawurlencode((string)$_POST['token']):''));}
    }

    private static function joinPage(string $basePath):never
    {
        $flash=$_SESSION['platform_onboarding_flash']??null;$local=$_SESSION['platform_local_verification_link']??null;unset($_SESSION['platform_onboarding_flash'],$_SESSION['platform_local_verification_link']);$e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');$u=static fn(string $p):string=>($basePath?:'').$p;self::publicHead('Create your CTSMD Connect account',$basePath);?>
        <main class="onboard-public"><header class="onboard-public-nav"><a href="<?=$u('/')?>" class="onboard-brand"><span>C</span><b>CTSMD <small>CONNECT</small></b></a><a href="<?=$u('/login')?>">Already have an account? <b>Sign in</b></a></header><div class="onboard-public-grid"><section class="onboard-intro"><small>JOIN CTSMD CONNECT</small><h1>Start with the parent or guardian account.</h1><p>Create your adult account, verify your email, then build your household. CTSMD staff approves general membership before organization-wide Community access opens.</p><ul><li>Email verification confirms the account belongs to you.</li><li>CTSMD approval unlocks general member Community and resources.</li><li>Production membership separately unlocks each show’s schedule, channels, forms and files.</li><li>Children are created inside the guardian account, not through anonymous public signup.</li></ul></section><section class="onboard-card"><?php if($flash):?><div class="onboard-flash <?=$e($flash['type'])?>"><?=$e($flash['message'])?></div><?php endif;?><header><small>PARENT / GUARDIAN ACCOUNT</small><h2>Create your account</h2><p>This creates your CTSMD Connect identity, not a class or production registration. <a href="<?=$u('/privacy')?>">Privacy policy</a></p></header><form method="post"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['platform_onboarding_csrf'])?>"><input type="hidden" name="relationship_type" value="parent"><div class="pair"><label>First name<input name="first_name" maxlength="100" autocomplete="given-name" required></label><label>Last name<input name="last_name" maxlength="100" autocomplete="family-name" required></label></div><label>Email<input type="email" name="email" autocomplete="email" required></label><label>Date of birth<input type="date" name="date_of_birth" max="<?=date('Y-m-d')?>" required></label><label>Phone<input type="tel" name="contact_phone" maxlength="14" inputmode="tel" autocomplete="tel-national" placeholder="(555) 123-4567" pattern="\([0-9]{3}\) [0-9]{3}-[0-9]{4}" title="Enter a 10-digit phone number" required></label><label>Password<input type="password" name="password" minlength="8" autocomplete="new-password" required><small>At least 8 characters.</small></label><label>Confirm password<input type="password" name="password_confirm" minlength="8" autocomplete="new-password" required></label><button type="submit">Create CTSMD Connect account</button></form><script>
        (() => {
            const phone = document.querySelector('input[name="contact_phone"]');
            if (!phone) return;
            const format = value => {
                const digits = value.replace(/\D/g, '').slice(0, 10);
                if (!digits) return '';
                if (digits.length <= 3) return '(' + digits;
                if (digits.length <= 6) return '(' + digits.slice(0, 3) + ') ' + digits.slice(3);
                return '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
            };
            phone.addEventListener('input', () => { phone.value = format(phone.value); });
            phone.addEventListener('blur', () => { phone.value = format(phone.value); });
            phone.value = format(phone.value);
        })();
        </script><?php if($local):?><div class="onboard-dev"><b>Local verification link</b><code><?=$e($local)?></code></div><?php endif;?></section></div></main><?php self::publicFoot();
    }

    private static function verifyPage(PDO $db,string $basePath):never
    {
        $token=(string)($_GET['token']??'');$verification=PlatformRegistrationService::verification($db,$token);$e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');$u=static fn(string $p):string=>($basePath?:'').$p;self::publicHead('Verify your email',$basePath);?><main class="onboard-public single"><header class="onboard-public-nav"><a href="<?=$u('/')?>" class="onboard-brand"><span>C</span><b>CTSMD <small>CONNECT</small></b></a></header><section class="onboard-card verify"><?php if(!$verification):?><small>EMAIL VERIFICATION</small><h1>This verification link is invalid or expired.</h1><p>Return to account registration and enter the same email address to request a new verification email.</p><a class="onboard-link" href="<?=$u('/join')?>">Return to account registration</a><?php else:?><small>EMAIL VERIFICATION</small><h1>Hi <?=$e($verification['first_name'])?>.</h1><p>Verify <b><?=$e($verification['email'])?></b> to activate your sign-in and continue to Household Setup. Your general CTSMD membership will remain pending until staff approval.</p><form method="post"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['platform_onboarding_csrf'])?>"><input type="hidden" name="token" value="<?=$e($token)?>"><button type="submit">Verify email & continue</button></form><?php endif;?></section></main><?php self::publicFoot();
    }

    private static function studentActivationPage(PDO $db,string $basePath):never
    {
        $token=(string)($_GET['token']??'');
        $invitation=PlatformRegistrationService::studentInvitation($db,$token);
        $flash=$_SESSION['platform_onboarding_flash']??null;unset($_SESSION['platform_onboarding_flash']);
        $e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');
        self::publicHead('Finish student account setup',$basePath);
        ?><main class="onboard-public single"><section class="onboard-card verify">
        <small>CTSMD STUDENT INVITATION</small>
        <?php if($flash):?><div class="onboard-flash <?=$e((string)$flash['type'])?>"><?=$e((string)$flash['message'])?></div><?php endif;?>
        <?php if(!$invitation):?><h1>This invitation is no longer valid.</h1><p>Ask your parent or guardian to contact CTSMD staff for a new invitation.</p>
        <?php else:?><h1>Welcome, <?=$e((string)$invitation['first_name'])?>!</h1><p>Confirm your invitation for <?=$e((string)$invitation['email'])?> and set a password to activate your existing student profile.</p>
        <form method="post"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['platform_onboarding_csrf'])?>"><input type="hidden" name="token" value="<?=$e($token)?>">
        <label>Password<input type="password" name="password" autocomplete="new-password" minlength="<?=Auth::PASSWORD_MIN_LENGTH?>" required></label>
        <label>Confirm password<input type="password" name="password_confirm" autocomplete="new-password" minlength="<?=Auth::PASSWORD_MIN_LENGTH?>" required></label>
        <button type="submit">Activate my student account</button></form><?php endif;?>
        </section></main><?php self::publicFoot();
    }

    private static function householdPage(PDO $db,string $basePath,array $user,string $route):never
    {
        $flash=$_SESSION['platform_onboarding_flash']??null;unset($_SESSION['platform_onboarding_flash']);$children=self::children($db,(int)$user['id']);$e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');$u=static fn(string $p):string=>($basePath?:'').$p;$title=$route==='/onboarding'?'Set up your household':'Manage household';$subnav=[['label'=>'My family','href'=>'/family-hub','active'=>false],['label'=>'Manage household','href'=>'/family/manage','active'=>true]];$membership=(string)($user['organization_membership_status']??'pending');
        header('Content-Type:text/html; charset=utf-8');?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=$e($title)?> · CTSMD Connect</title><link rel="stylesheet" href="<?=$u('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/platform-onboarding.css')?>"><?php MemberUI::styles($basePath); ?></head><body class="app-body<?=MemberUI::bodyClass()?>"><div class="unified-shell"><?php AppNavigation::renderSidebar($route,$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Family',$title,$basePath,$subnav);MemberUI::wayfinding($basePath);?><div class="household-page"><?php if($flash):?><div class="onboard-flash <?=$e($flash['type'])?>"><?=$e($flash['message'])?></div><?php endif;?><section class="household-hero"><div><small>YOUR CTSMD HOUSEHOLD · MEMBERSHIP <?=strtoupper($e($membership))?></small><h2><?=$route==='/onboarding'?'Add the children in your household.':'The people you manage in CTSMD Connect.'?></h2><p><?= $membership==='approved'?'Your CTSMD membership is approved. General member Community access is available; production-specific access appears separately when your household is added to a show.':'Your sign-in is active, but general CTSMD membership is still pending staff approval. You can manage your household while you wait. Production membership is assigned separately.'?></p></div><?php if($route!=='/onboarding'):?><a href="<?=$u('/family-hub')?>">Open Family Hub →</a><?php endif;?></section><div class="household-grid"><section class="household-panel"><header><small>YOUR CHILDREN</small><h3><?=count($children)?> <?=count($children)===1?'child':'children'?> added</h3></header><?php if(!$children):?><div class="household-empty"><b>No children added yet.</b><p>Use the form to add a child involved with CTSMD. You can add more than one, and you can always come back later.</p></div><?php endif;?><?php foreach($children as $child):?><article class="household-child"><span><?=$e($child['initials'])?></span><div><b><?=$e($child['first_name'].' '.$child['last_name'])?></b><small><?=$e(ucfirst($child['relationship_type']))?><?=$child['is_primary']?' · Primary guardian':''?></small><p><?=$child['active_productions']?(int)$child['active_productions'].' active production'.((int)$child['active_productions']===1?'':'s'):'Not yet assigned to an active production'?></p><a class="button secondary" style="display:inline-block;margin-top:8px" href="<?=$u('/student-profile/family?student='.(int)$child['id'])?>">Manage family &amp; pickup →</a></div></article><?php endforeach;?><aside class="household-family-guide" aria-labelledby="household-guide-title">
<div class="household-guide-top"><span class="household-guide-symbol" aria-hidden="true">♥</span><div><small>MADE FOR THE WHOLE FAMILY</small><h3 id="household-guide-title">Stay in the loop, without the juggling.</h3><p>One place to keep up with everything happening at CTSMD, even when you've got more than one child in a show.</p></div></div>
<div class="household-guide-features">
<div class="household-guide-feature"><span class="household-feature-icon" aria-hidden="true">★</span><div><h4>Hear about casting</h4><p>Get your children's casting offers and updates when they're ready to share.</p></div></div>
<div class="household-guide-feature"><span class="household-feature-icon" aria-hidden="true">▦</span><div><h4>One calendar for everyone</h4><p>See rehearsals, performances, and other important dates together.</p></div></div>
<div class="household-guide-feature"><span class="household-feature-icon" aria-hidden="true">♡</span><div><h4>Stay connected, safely</h4><p>Your children can connect with their theatre friends and team. Extra protections help parents stay involved in younger members' private conversations.</p></div></div>
<div class="household-guide-feature"><span class="household-feature-icon" aria-hidden="true">✓</span><div><h4>Keep the little things organized</h4><p>Find forms, reminders, and ways to lend a hand when your family needs them.</p></div></div>
</div>
<p class="household-guide-foot">You'll see information for the shows and groups your family belongs to as access becomes available.</p></aside></section><details class="household-panel add member-editor" <?=$route==='/onboarding'||($flash&&$flash['type']==='error')?'open':''?>><summary>Add a child</summary><div class="member-editor-body"><header><h3>Add a child to your household</h3><p>Add each child involved with CTSMD, one at a time. You can add another child after saving, or return whenever you need to. Production access is assigned separately by CTSMD staff.</p></header><form method="post"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['platform_onboarding_csrf'])?>"><input type="hidden" name="action" value="add_child"><div class="pair"><label>Child first name<input name="first_name" maxlength="100" required></label><label>Child last name<input name="last_name" maxlength="100" required></label></div><label>Child date of birth<input type="date" name="date_of_birth" max="<?=date('Y-m-d')?>" required><small>Helps us match your child with auditions and activities for their age.</small></label><div class="household-email-field"><label>Child's email <span class="household-optional">(optional)</span><input type="email" name="email" maxlength="254" autocomplete="off"></label><p><b>Want your child to have their own login?</b> Add their email and we'll send an invitation to create a password. They can then see their schedule, join conversations they're allowed to participate in, and receive theatre updates. You can also add an email later.</p></div><label>Your relationship<select name="relationship_type" required><option value="parent">Parent</option><option value="guardian">Guardian</option></select></label><details class="household-role-help" style="padding:12px;margin:10px 0;border:1px solid #d9cdd2;border-radius:10px"><summary style="cursor:pointer;font-weight:700">Not sure which relationship to choose?</summary><p><b>Parent:</b> Choose this if you're the child's parent.</p><p><b>Guardian:</b> Choose this if you're legally responsible for the child but aren't their parent.</p><p><b>Helping with pickups?</b> You can add another trusted adult for pickups later. They don't need a full account just to be approved for pickup.</p></details><button type="submit">Add child to my family</button></form></div></details></div><?php if($route==='/onboarding'):?><form method="post" class="household-continue"><input type="hidden" name="csrf_token" value="<?=$e((string)$_SESSION['platform_onboarding_csrf'])?>"><input type="hidden" name="action" value="complete_onboarding"><div class="household-continue-copy"><b><?=$children?'Ready to finish?':'No children to add right now?'?></b><small><?=$children?'You can add or update children anytime from your household.':'You can complete setup now and add children later.'?> Membership approval is handled separately by CTSMD staff.</small></div><button type="submit"><?=$children?'Finish setup':'Continue without adding children'?> →</button></form><?php endif;?></div></main></div><script src="<?=$u('/assets/js/unified-navigation.js')?>"></script></body></html><?php exit;
    }

    private static function children(PDO $db,int $guardianId):array{$stmt=$db->prepare("SELECT u.id,u.first_name,u.last_name,u.initials,fr.relationship_type,fr.is_primary,(SELECT COUNT(DISTINCT pm.production_id) FROM production_memberships pm JOIN productions p ON p.id=pm.production_id AND p.is_active=1 WHERE pm.user_id=u.id AND pm.status='active') active_productions FROM family_relationships fr JOIN users u ON u.id=fr.student_user_id AND u.active=1 WHERE fr.guardian_user_id=:guardian AND fr.status='active' AND fr.relationship_type IN ('parent','guardian') ORDER BY u.first_name,u.last_name");$stmt->execute(['guardian'=>$guardianId]);return $stmt->fetchAll();}
    private static function publicHead(string $title,string $basePath):void{$u=static fn(string $p):string=>($basePath?:'').$p;header('Content-Type:text/html; charset=utf-8');?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#181416"><title><?=htmlspecialchars($title,ENT_QUOTES,'UTF-8')?> · CTSMD</title><link rel="stylesheet" href="<?=$u('/assets/css/platform-onboarding.css')?>"></head><body><?php }
    private static function publicFoot():never{?></body></html><?php exit;}
    private static function csrf():void{if(!hash_equals((string)($_SESSION['platform_onboarding_csrf']??''),(string)($_POST['csrf_token']??'')))throw new RuntimeException('Your session expired. Please try again.');}
    private static function forbidden():never{http_response_code(403);header('Content-Type:text/plain; charset=utf-8');exit('Household management is available to parents and guardians.');}
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
}
