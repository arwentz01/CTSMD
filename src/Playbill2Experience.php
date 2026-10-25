<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/AccessPolicy.php';
require_once __DIR__.'/AppNavigation.php';
require_once __DIR__.'/ProductionContext.php';
require_once __DIR__.'/PlaybillEnhancementService.php';
require_once __DIR__.'/PlaybillQrService.php';
require_once __DIR__.'/PlaybillPublicRenderer.php';
require_once __DIR__.'/StorageService.php';

final class Playbill2Experience
{
    private const ROUTES=['/playbill','/playbill/asset','/playbill/qr','/playbill/sign','/admin/playbill/media'];
    public static function handles(string $route):bool{return in_array($route,self::ROUTES,true);}

    public static function render(string $route,string $basePath):never
    {
        $db=Database::connect(dirname(__DIR__));
        if($route==='/playbill')self::publicPage($db,$basePath);
        if($route==='/playbill/asset')self::asset($db);
        if($route==='/playbill/qr')self::qr($db,$basePath);
        if($route==='/playbill/sign')self::sign($db,$basePath);
        Auth::startSession();$user=Auth::currentUser($db);
        if(!$user||!AccessPolicy::canManagePlaybill($user)){http_response_code(403);exit('Playbill management permission is required.');}
        $production=ProductionContext::selected($db,$user);if(!$production)self::redirect(($basePath?:'').'/production');
        $playbill=self::playbill($db,(int)$production['id']);if(!$playbill)self::redirect(($basePath?:'').'/admin/playbill');
        $_SESSION['playbill2_csrf']??=bin2hex(random_bytes(24));
        PlaybillEnhancementService::syncCastProfiles($db,(int)$playbill['id'],(int)$production['id'],(int)$user['id']);
        if($_SERVER['REQUEST_METHOD']==='POST')self::post($db,$basePath,$user,$playbill);
        self::adminPage($db,$basePath,$user,$production,$playbill);
    }

    private static function post(PDO $db,string $basePath,array $user,array $playbill):never
    {
        if(!hash_equals((string)($_SESSION['playbill2_csrf']??''),(string)($_POST['csrf_token']??''))){self::flash('error','Your Playbill session expired.');self::redirect($basePath.'/admin/playbill/media');}
        try{
            $action=(string)($_POST['action']??'');$pid=(int)$playbill['id'];$actor=(int)$user['id'];
            if($action==='cover_display'){
                $mode=(string)($_POST['cover_display']??'artwork_and_text');
                if(!in_array($mode,['artwork_and_text','artwork_only'],true))throw new RuntimeException('Invalid cover presentation.');
                $stmt=$db->prepare('UPDATE playbills SET cover_display=:mode WHERE id=:id');
                $stmt->execute(['mode'=>$mode,'id'=>$pid]);
            }
            elseif($action==='artwork')PlaybillEnhancementService::saveArtwork($db,dirname(__DIR__),$pid,$actor,$_FILES['artwork']??[]);
            elseif($action==='save_cast'){$identity=(int)($_POST['user_id']??0);if($identity<0)PlaybillEnhancementService::saveManualCastProfile($db,$pid,-$identity,$actor,$_POST);else PlaybillEnhancementService::saveCastProfile($db,dirname(__DIR__),$pid,$identity,$actor,$_POST,$_FILES['headshot']??[]);}
            elseif($action==='save_sponsor'){$id=PlaybillEnhancementService::saveSponsor($db,dirname(__DIR__),$actor,$_POST,$_FILES['logo']??[],$_FILES['advertisement']??[]);PlaybillEnhancementService::attachSponsor($db,$pid,$id,(string)($_POST['placement_label']??''),(int)($_POST['sort_order']??0),$actor,(string)($_POST['placement_format']??'card'));}
            elseif($action==='attach_sponsor')PlaybillEnhancementService::attachSponsor($db,$pid,(int)($_POST['sponsor_id']??0),(string)($_POST['placement_label']??''),(int)($_POST['sort_order']??0),$actor,(string)($_POST['placement_format']??'card'));
            elseif($action==='remove_sponsor')PlaybillEnhancementService::removeSponsor($db,$pid,(int)($_POST['sponsor_id']??0),$actor);
            else throw new RuntimeException('Choose a valid Playbill media action.');
            self::flash('success','Playbill media updated.');
        }catch(RuntimeException $e){self::flash('error',$e->getMessage());}
        self::redirect($basePath.'/admin/playbill/media');
    }

    private static function adminPage(PDO $db,string $basePath,array $user,array $production,array $playbill):never
    {
        $cast=PlaybillEnhancementService::playbillCastProfiles($db,(int)$playbill['id']);$sponsors=PlaybillEnhancementService::sponsors($db,(int)$playbill['id']);$library=PlaybillEnhancementService::sponsorLibrary($db);
        $u=static fn(string $p):string=>($basePath?:'').$p;$e=static fn(?string $v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');$csrf=$e((string)$_SESSION['playbill2_csrf']);$flash=self::takeFlash();$publicUrl=self::absoluteUrl($u('/playbill?slug='.rawurlencode((string)$playbill['public_slug'])));
        header('Content-Type:text/html; charset=utf-8');?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Playbill Media · CTSMD Connect</title><link rel="stylesheet" href="<?=$u('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/playbill2.css?v=20261022a')?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/admin/playbill',$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Production','Playbill Media & Sponsors',$basePath,AppNavigation::productionSubnav('/admin/playbill/media',$user));?><div class="pb2-admin">
<?php if($flash):?><div class="pb2-flash <?=$e($flash['type'])?>"><?=$e($flash['message'])?></div><?php endif;?>
<header class="pb2-hero"><small><?=$e(strtoupper((string)$production['title']))?></small><h2>Publication media</h2><p>Edit per-production cast details, reusable sponsors, artwork, and audience access.</p></header>
<div class="pb2-grid pb2-media-grid"><section class="pb2-card pb2-media-card"><div class="pb2-card-heading"><div><small>PRODUCTION IMAGE</small><h3>Production artwork</h3></div></div><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=$csrf?>"><input type="hidden" name="action" value="artwork"><label>Artwork image<input type="file" name="artwork" accept="image/jpeg,image/png,image/webp" required></label><footer class="pb2-form-actions"><button class="button">Upload artwork</button></footer></form><form class="pb2-cover-mode-form" method="post"><input type="hidden" name="csrf_token" value="<?=$csrf?>"><input type="hidden" name="action" value="cover_display"><fieldset><legend>Cover presentation</legend><label class="pb2-radio-option"><input type="radio" name="cover_display" value="artwork_and_text" <?=($playbill['cover_display']??'artwork_and_text')==='artwork_and_text'?'checked':''?>> Artwork with title and production text</label><label class="pb2-radio-option"><input type="radio" name="cover_display" value="artwork_only" <?=($playbill['cover_display']??'')==='artwork_only'?'checked':''?>> Full-page artwork only (no text overlay)</label></fieldset><small>Upload a complete, portrait-format cover image before choosing artwork only.</small><footer class="pb2-form-actions"><button class="button" type="submit">Save cover presentation</button></footer></form></section>
<section class="pb2-card pb2-media-card"><div class="pb2-card-heading"><div><small>AUDIENCE ACCESS</small><h3>Permanent public URL</h3></div><span class="pb2-availability"><?=$playbill['public_enabled']?'Public':'Not published'?></span></div><label class="pb2-url-field">Public Playbill URL<input type="url" value="<?=$e($publicUrl)?>" readonly></label><button class="button pb2-copy" type="button" data-copy-url="<?=$e($publicUrl)?>"><span data-copy-label>Copy URL</span></button><div class="pb2-tool-row" aria-label="QR and print tools"><a class="pb2-tool-button" href="<?=$u('/playbill/qr?slug='.rawurlencode((string)$playbill['public_slug']).'&download=1')?>">Download QR</a><a class="pb2-tool-button" href="<?=$u('/playbill/qr?slug='.rawurlencode((string)$playbill['public_slug']).'&background=transparent&download=1')?>">Transparent QR</a><a class="pb2-tool-button" target="_blank" rel="noopener" href="<?=$u('/playbill/sign?slug='.rawurlencode((string)$playbill['public_slug']))?>">Print sign</a></div></section></div>
<section class="pb2-card pb2-cast-section"><div class="pb2-section-heading"><div><small>CAST PROFILES</small><h3>Biographies and headshots</h3><p>Edits are production-specific. Public inclusion requires Student or guardian approval.</p></div><span><?=count($cast)?> cast member<?=count($cast)===1?'':'s'?></span></div><div class="pb2-cast-editor"><?php foreach($cast as $index=>$m):$publicName=trim((string)$m['display_name'])?:$m['legal_name'];$role=trim((string)$m['role_title']);?><details class="pb2-cast-card"<?=$index===0?' open':''?>><summary><span class="pb2-cast-identity"><b><?=$e($publicName)?></b><small><?=$e($role!==''?$role:'No character or credit set')?></small></span><span class="pb2-cast-indicators"><span class="pb2-status pb2-status-<?=$e((string)$m['approval_status'])?>"><?=$e(ucfirst((string)$m['approval_status']))?></span><span class="pb2-visibility <?=$m['visible']?'is-public':'is-hidden'?>"><?=$m['visible']?'Public':'Hidden'?></span><i aria-hidden="true"></i></span></summary><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=$csrf?>"><input type="hidden" name="action" value="save_cast"><input type="hidden" name="user_id" value="<?=(int)$m['user_id']?>"><div class="pb2-cast-fields"><label>Public name<input name="display_name" maxlength="190" required value="<?=$e($m['display_name'])?>"></label><label>Character / credit<input name="role_title" maxlength="190" value="<?=$e($m['role_title'])?>"></label><label class="pb2-field-wide">Biography<textarea name="biography" maxlength="3000" rows="5"><?=$e($m['biography'])?></textarea></label><label>Headshot<input type="file" name="headshot" accept="image/jpeg,image/png,image/webp"></label><label>Photographer credit<input name="photographer_credit" maxlength="190" value="<?=$e($m['photographer_credit'])?>"></label><label>Display order<input type="number" name="display_order" value="<?=(int)$m['display_order']?>"></label><label class="pb2-check"><input type="checkbox" name="visible" value="1" <?=$m['visible']?'checked':''?>><span>Include publicly</span></label></div><footer class="pb2-form-actions"><span>Approval returns to pending when public details change.</span><button class="button">Save cast profile</button></footer></form></details><?php endforeach;?></div></section>
<div class="pb2-grid"><section class="pb2-card"><h3>Add sponsor from library</h3><form method="post"><input type="hidden" name="csrf_token" value="<?=$csrf?>"><input type="hidden" name="action" value="attach_sponsor"><label>Sponsor<select name="sponsor_id" required><?php foreach($library as $s):?><option value="<?=(int)$s['id']?>"><?=$e($s['name'])?></option><?php endforeach;?></select></label><?php self::placementFields();?><button class="button">Add sponsor</button></form></section>
<section class="pb2-card"><h3>Create or edit sponsor</h3><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?=$csrf?>"><input type="hidden" name="action" value="save_sponsor"><label>Existing sponsor (optional)<select name="sponsor_id"><option value="0">Create new</option><?php foreach($library as $s):?><option value="<?=(int)$s['id']?>"><?=$e($s['name'])?></option><?php endforeach;?></select></label><label>Name<input name="sponsor_name" maxlength="190" required></label><label>Tier<select name="tier"><option value="regular">Regular</option><option value="premium">Premium</option></select></label><label>Public description<textarea name="public_description" maxlength="1000"></textarea></label><label>Website<input type="url" name="website_url" maxlength="1000"></label><label>CTA label<input name="cta_label" maxlength="80"></label><label>Logo<input type="file" name="logo" accept="image/jpeg,image/png,image/webp"></label><label>Advertisement art<input type="file" name="advertisement" accept="image/jpeg,image/png,image/webp"></label><label>Internal contact name<input name="internal_contact_name" maxlength="190"></label><label>Internal contact email<input type="email" name="internal_contact_email" maxlength="255"></label><label>Internal notes<textarea name="internal_notes"></textarea></label><?php self::placementFields();?><button class="button">Save and add sponsor</button></form></section></div>
<section class="pb2-card"><h3>Sponsors in this edition</h3><div class="pb2-sponsors-admin"><?php foreach($sponsors as $s):?><article class="<?=$s['tier']==='premium'?'premium':''?>"><span><b><?=$e($s['name'])?></b><small><?=$e(ucfirst((string)$s['tier']).' · '.($s['placement_label']?:'Sponsor'))?></small></span><?php if($s['attachment_active']):?><form method="post"><input type="hidden" name="csrf_token" value="<?=$csrf?>"><input type="hidden" name="action" value="remove_sponsor"><input type="hidden" name="sponsor_id" value="<?=(int)$s['id']?>"><button>Remove</button></form><?php else:?><em>Removed</em><?php endif;?></article><?php endforeach;?></div></section>
</div></main></div><script src="<?=$u('/assets/js/unified-navigation.js')?>"></script><script>document.querySelector('[data-copy-url]')?.addEventListener('click',async e=>{await navigator.clipboard.writeText(e.currentTarget.dataset.copyUrl);const label=e.currentTarget.querySelector('[data-copy-label]');label.textContent='Copied';window.setTimeout(()=>label.textContent='Copy URL',1600);});</script></body></html><?php exit;
    }

    private static function placementFields():void{?><label>Placement label<input name="placement_label" maxlength="100" placeholder="Presenting Sponsor"></label><label>Format<select name="placement_format"><option value="card">Card</option><option value="half_page">Half page</option><option value="full_page">Full page</option></select></label><label>Order<input type="number" name="sort_order" value="10"></label><?php }

    private static function publicPage(PDO $db,string $basePath):never
    {
        $pb=self::publicPlaybill($db,trim((string)($_GET['slug']??'')));if(!$pb)PlaybillPublicRenderer::render($basePath,null,[],[],[],[]);
        $sections=self::sections($db,(int)$pb['id']);$company=PlaybillEnhancementService::publicCastProfiles($db,(int)$pb['id']);$cast=array_values(array_filter($company,static fn(array $p):bool=>($p['participant_type']??'cast')==='cast'));$staff=self::staff($db,(int)$pb['production_id']);foreach($company as $person)if(($person['participant_type']??'cast')!=='cast')$staff[]=['name'=>$person['display_name'],'participation_role'=>$person['role_title']?:ucfirst((string)$person['participant_type'])];$sponsors=array_values(array_filter(PlaybillEnhancementService::sponsors($db,(int)$pb['id']),static fn(array $s):bool=>(int)$s['attachment_active']===1&&$s['status']==='active'));
        PlaybillPublicRenderer::render($basePath,$pb,$sections,$company,$staff,$sponsors);
    }

    private static function qr(PDO $db,string $basePath):never{$slug=trim((string)($_GET['slug']??''));if(!self::publicPlaybill($db,$slug)){http_response_code(404);exit('Playbill unavailable.');}$url=self::absoluteUrl($basePath.'/playbill?slug='.rawurlencode($slug));header('Content-Type:image/svg+xml; charset=utf-8');header('Cache-Control:public, max-age=3600');if(!empty($_GET['download']))header('Content-Disposition:attachment; filename="ctsmd-playbill-'.$slug.'-qr.svg"');echo PlaybillQrService::svg($url,($_GET['background']??'')==='transparent',16);exit;}
    private static function sign(PDO $db,string $basePath):never{$slug=trim((string)($_GET['slug']??''));$pb=self::publicPlaybill($db,$slug);if(!$pb){http_response_code(404);exit('Playbill unavailable.');}$e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');header('Content-Type:text/html; charset=utf-8');?><!doctype html><html><head><meta charset="utf-8"><title><?=$e((string)$pb['display_title'])?> sign</title><style>body{font-family:Georgia,serif;text-align:center}.sign{max-width:7in;margin:auto;border:3px solid;padding:.4in}.sign img{width:4.5in;max-width:80%}h1{font-size:40px}button{padding:10px 18px}@media print{button{display:none}}</style></head><body><main class="sign"><small>CTSMD DIGITAL PROGRAM</small><h1><?=$e((string)($pb['display_title']?:$pb['production_title']))?></h1><img src="<?=($basePath?:'')?>/playbill/qr?slug=<?=rawurlencode($slug)?>" alt="QR code"><p>Scan to open the Playbill</p><button onclick="window.print()">Print sign</button></main></body></html><?php exit;}
    private static function asset(PDO $db):never{$v=PlaybillEnhancementService::publicAsset($db,(string)($_GET['slug']??''),(string)($_GET['kind']??''),(int)($_GET['id']??0));if(!$v){http_response_code(404);exit('Asset unavailable.');}StorageService::stream(dirname(__DIR__),$v,false);}
    private static function playbill(PDO $db,int $production):?array{$s=$db->prepare('SELECT pb.*,p.title production_title,p.season FROM playbills pb JOIN productions p ON p.id=pb.production_id WHERE pb.production_id=:p ORDER BY pb.id DESC LIMIT 1');$s->execute(['p'=>$production]);return$s->fetch()?:null;}
    private static function publicPlaybill(PDO $db,string $slug):?array{$s=$db->prepare("SELECT pb.*,p.title production_title,p.season FROM playbills pb JOIN productions p ON p.id=pb.production_id WHERE pb.public_slug=:slug AND pb.public_enabled=1 AND pb.editorial_status IN ('published','archived') LIMIT 1");$s->execute(['slug'=>$slug]);return$s->fetch()?:null;}
    private static function sections(PDO $db,int $id):array{$s=$db->prepare('SELECT * FROM playbill_sections WHERE playbill_id=:id AND active=1 ORDER BY sort_order,id');$s->execute(['id'=>$id]);return$s->fetchAll();}
    private static function staff(PDO $db,int $production):array{$s=$db->prepare("SELECT CONCAT(u.first_name,' ',u.last_name) name,pm.participation_role FROM production_memberships pm JOIN users u ON u.id=pm.user_id AND u.active=1 AND u.account_status<>'disabled' WHERE pm.production_id=:p AND pm.status='active' AND pm.audience_type='staff' ORDER BY pm.participation_role,u.last_name,u.first_name");$s->execute(['p'=>$production]);return$s->fetchAll();}
    private static function absoluteUrl(string $path):string{return((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost').$path;}
    private static function flash(string $type,string $message):void{$_SESSION['pb2_flash']=['type'=>$type,'message'=>$message];}
    private static function takeFlash():?array{$v=$_SESSION['pb2_flash']??null;unset($_SESSION['pb2_flash']);return is_array($v)?$v:null;}
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
}
