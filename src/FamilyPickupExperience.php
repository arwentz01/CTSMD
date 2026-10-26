<?php
declare(strict_types=1);
require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/AppNavigation.php';
require_once __DIR__.'/FamilyPickupService.php';

final class FamilyPickupExperience {
    public static function handles(string $route):bool {return $route==='/student-profile/family';}
    public static function render(string $route,string $basePath):never {
        Auth::startSession();$db=Database::connect(dirname(__DIR__));$viewer=Auth::currentUser($db);
        if(!$viewer)self::go($basePath.'/login');
        $student=(int)($_POST['student_id']??$_GET['student']??0);
        if(!FamilyPickupService::canManage($db,$viewer,$student)){http_response_code(404);exit('Student family settings unavailable.');}
        $_SESSION['family_pickup_csrf']??=bin2hex(random_bytes(24));
        if($_SERVER['REQUEST_METHOD']==='POST'){
            if(!hash_equals((string)$_SESSION['family_pickup_csrf'],(string)($_POST['csrf_token']??''))){http_response_code(403);exit('Invalid session token.');}
            try{
                $action=(string)($_POST['action']??'');
                if($action==='pickup_save')FamilyPickupService::savePickup($db,$viewer,$student,$_POST);
                elseif($action==='pickup_status')FamilyPickupService::togglePickup($db,$viewer,$student,(int)($_POST['pickup_id']??0),($_POST['active']??'')==='1');
                elseif($action==='adult_request')FamilyPickupService::requestAdult($db,$viewer,$student,$_POST);
                elseif($action==='adult_remove')FamilyPickupService::deactivateAdult($db,$viewer,$student,(int)($_POST['relationship_id']??0));
                elseif($action==='request_approve')FamilyPickupService::decide($db,$viewer,$student,(int)($_POST['request_id']??0),true);
                elseif($action==='request_reject')FamilyPickupService::decide($db,$viewer,$student,(int)($_POST['request_id']??0),false);
                else throw new RuntimeException('Unknown family management action.');
                $_SESSION['family_pickup_flash']=['success','Changes saved.'];
            }catch(RuntimeException|PDOException $e){$_SESSION['family_pickup_flash']=['error',$e instanceof PDOException?'Unable to save. Check that the family/pickup migration has been applied.':$e->getMessage()];}
            self::go($basePath.'/student-profile/family?student='.$student);
        }
        $profile=StudentProfileService::profile($db,$student);
        $adults=FamilyPickupService::adults($db,$student);
        $pickup=FamilyPickupService::pickup($db,$student);
        $requests=FamilyPickupService::requests($db,$student);
        $staff=AccessPolicy::canManagePeople($viewer);
        $flash=$_SESSION['family_pickup_flash']??null;unset($_SESSION['family_pickup_flash']);
        $e=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');$url=static fn(string $p):string=>($basePath?:'').$p;
        $hidden='<input type="hidden" name="csrf_token" value="'.$e($_SESSION['family_pickup_csrf']).'"><input type="hidden" name="student_id" value="'.$student.'">';
        header('Content-Type: text/html; charset=utf-8');?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Family & pickup · CTSMD Connect</title>
<link rel="stylesheet" href="<?=$url('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$url('/assets/css/unified-navigation.css')?>">
<style>
.fp-wrap{max-width:1050px;margin:0 auto;padding:25px 22px 70px}.fp-back{display:inline-block;margin-bottom:14px;color:#a6192e;font-weight:750}.fp-hero{margin-bottom:22px}.fp-hero small{font-size:11px;font-weight:800;color:#a6192e;letter-spacing:.1em}.fp-hero h1{margin:5px 0;font:700 30px Georgia,serif;color:#292127}.fp-hero p{color:#6b6066;max-width:700px;font-size:14px}.fp-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.fp-card{background:#fff;border:1px solid #e4dce0;border-radius:15px;padding:20px;min-width:0}.fp-card h2{margin:0 0 5px;font:700 22px Georgia,serif}.fp-card>p{color:#625b60;font-size:13px;line-height:1.5}.fp-entry{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 0;border-top:1px solid #eee7ea}.fp-entry strong{display:block;font-size:14px}.fp-entry small{display:block;color:#72676e;font-size:12px;margin-top:2px}.fp-entry form{display:inline-block;margin:0}.fp-entry button{border:1px solid #d7c9cf;border-radius:8px;padding:7px 10px;background:#fff;color:#843046;cursor:pointer;font-weight:700;font-size:12px}.fp-card details{border-top:1px solid #eee7ea;margin-top:14px;padding-top:15px}.fp-card summary{color:#a6192e;font-weight:800;cursor:pointer}.fp-form{display:grid;gap:12px;margin-top:15px}.fp-form label{display:grid;gap:5px;color:#35282e;font-size:13px;font-weight:700}.fp-form input:not([type=checkbox]),.fp-form select,.fp-form textarea{width:100%;min-width:0;box-sizing:border-box;border:1px solid #d8cbd0;border-radius:9px;background:#fff;padding:10px;font:inherit;color:#292127}.fp-form label.fp-check{display:flex;align-items:center;gap:9px}.fp-form button{background:#a6192e;border:0;border-radius:10px;padding:12px 16px;color:#fff;font-weight:800;cursor:pointer}.fp-alert{padding:12px 16px;margin-bottom:16px;border-radius:9px;background:#fff3f3;border:1px solid #ecccd1;color:#772639}.fp-alert.success{background:#eff9f1;border-color:#c8e4d0;color:#28613f}.fp-tag{font-size:11px;font-weight:750;color:#77666e}.fp-muted{opacity:.65}.fp-tip{font-size:12px;color:#71666d}.fp-full{grid-column:1/-1}@media(max-width:760px){.fp-grid{grid-template-columns:1fr}.fp-wrap{padding:16px 12px 60px}.fp-card{padding:16px}}
</style></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/student-profile',$basePath,$viewer);?><main class="unified-main"><?php AppNavigation::renderHeader('Family','Family & pickup',$basePath,[]);?><div class="fp-wrap">
<a class="fp-back" href="<?=$url('/student-profile?student='.$student)?>">← Student profile</a>
<header class="fp-hero"><small>CHILD-SPECIFIC SETTINGS</small><h1><?=$e($profile['legal_name'])?></h1><p>Manage this child's connected adults and authorized pickup people. Changes here never automatically carry over to siblings.</p></header>
<?php if($flash):?><div class="fp-alert <?=$e($flash[0])?>" role="status"><?=$e($flash[1])?></div><?php endif;?>
<div class="fp-grid">
<section class="fp-card"><h2>Parents & caregivers</h2><p>Connected adults can have their own CTSMD accounts. New access requests require staff approval.</p>
<?php foreach($adults as $person):?><div class="fp-entry <?=$person['status']==='active'?'':'fp-muted'?>"><div><strong><?=$e($person['name'])?></strong><small><?=$e(ucfirst($person['relationship_type']))?><?=$person['is_primary']?' · Primary':''?> · <?=$e(ucfirst($person['status']))?></small></div>
<?php if($person['status']==='active'):?><form method="post" onsubmit="return confirm('Remove this adult’s access to this child?');"><?=$hidden?><input type="hidden" name="action" value="adult_remove"><input type="hidden" name="relationship_id" value="<?=(int)$person['id']?>"><button type="submit">Remove</button></form><?php endif;?></div><?php endforeach;?>
<details><summary>Add parent or caregiver</summary><form method="post" class="fp-form"><?=$hidden?><input type="hidden" name="action" value="adult_request"><label>Adult's email<input type="email" name="adult_email" maxlength="190" required placeholder="adult@example.com"></label><label>Relationship<label><input type="text" name="relationship_label" maxlength="100" required placeholder="Mother, father, stepfather, grandparent…"></label></label><label>Account access<select name="relationship_type"><option value="parent">Parent</option><option value="guardian">Legal guardian</option><option value="caregiver">Caregiver</option></select></label><label class="fp-check"><input type="checkbox" name="pickup_authorized" value="1" checked> Authorized for pickup?</label><p class="fp-tip">This sends a request to staff review; it does not immediately give the adult account access. An adult must already have an active account before staff can approve.</p><button type="submit">Request connection</button></form></details>
</section>
<section class="fp-card"><h2>Authorized pickup people</h2><p>These people may collect this child, but do not receive CTSMD account access. No account is required.</p>
<?php foreach($pickup as $person):?><div class="fp-entry <?=$person['active']?'':'fp-muted'?>"><div><strong><?=$e($person['name'])?></strong><small><?=$e($person['relationship_label'])?><?=$person['phone']?' · '.$e($person['phone']):''?><?=$person['active']?'':' · Inactive'?></small></div><div>
<form method="post"><?=$hidden?><input type="hidden" name="action" value="pickup_status"><input type="hidden" name="pickup_id" value="<?=(int)$person['id']?>"><input type="hidden" name="active" value="<?=$person['active']?'0':'1'?>"><button type="submit"><?=$person['active']?'Remove':'Restore'?></button></form>
<?php if(!$person['linked_guardian_user_id']):?><details><summary>Edit</summary><form class="fp-form" method="post"><?=$hidden?><input type="hidden" name="action" value="pickup_save"><input type="hidden" name="pickup_id" value="<?=(int)$person['id']?>"><label>Name<input name="name" required maxlength="190" value="<?=$e($person['name'])?>"></label><label>Relationship<input name="relationship_label" required maxlength="100" value="<?=$e($person['relationship_label'])?>"></label><label>Phone<input name="phone" maxlength="60" value="<?=$e($person['phone'])?>"></label><label>Notes<textarea name="notes" maxlength="500"><?=$e($person['notes'])?></textarea></label><button type="submit">Save person</button></form></details><?php endif;?>
</div></div><?php endforeach;?>
<details><summary>Add pickup-only person</summary><form method="post" class="fp-form"><?=$hidden?><input type="hidden" name="action" value="pickup_save"><label>Full name<input name="name" maxlength="190" required></label><label>Relationship<input name="relationship_label" maxlength="100" placeholder="Grandmother, family friend…" required></label><label>Phone (optional)<input name="phone" maxlength="60" type="tel"></label><label>Notes (optional)<textarea name="notes" maxlength="500"></textarea></label><button type="submit">Add pickup person</button></form></details>
</section>
<section class="fp-card fp-full"><h2>Relationship requests</h2><p>New adults cannot see the child's private information until an authorized staff member reviews the request.</p>
<?php if(!$requests):?><p class="fp-tip">No requests yet.</p><?php endif;?>
<?php foreach($requests as $request):?><div class="fp-entry"><div><strong><?=$e($request['adult_email'])?></strong><small><?=$e($request['relationship_label'])?> · <?=$e(ucfirst($request['status']))?><?=$request['pickup_authorized']?' · Pickup requested':''?></small></div><?php if($staff&&$request['status']==='pending'):?><div><form method="post"><?=$hidden?><input type="hidden" name="action" value="request_approve"><input type="hidden" name="request_id" value="<?=(int)$request['id']?>"><button type="submit">Approve</button></form> <form method="post"><?=$hidden?><input type="hidden" name="action" value="request_reject"><input type="hidden" name="request_id" value="<?=(int)$request['id']?>"><button type="submit">Reject</button></form></div><?php endif;?></div><?php endforeach;?>
</section></div></div></main></div><script src="<?=$url('/assets/js/unified-navigation.js')?>"></script></body></html><?php exit;
    }
    private static function go(string $path):never{header('Location: '.$path,true,303);exit;}
}
