<?php

declare(strict_types=1);

require_once __DIR__.'/WorkspaceUI.php';
require_once __DIR__.'/ProductionTeamPublicationService.php';

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
        if($route==='/admin/playbill/media'){require_once __DIR__.'/PlaybillExperience.php';$_GET['tab']??='bios';PlaybillExperience::render('/admin/playbill',$basePath);}
        $db=Database::connect(dirname(__DIR__));
        if($route==='/playbill')self::publicPage($db,$basePath);
        if($route==='/playbill/asset')self::asset($db);
        if($route==='/playbill/qr')self::qr($db,$basePath);
        if($route==='/playbill/sign')self::sign($db,$basePath);
        http_response_code(404);exit('Playbill unavailable.');
    }

    private static function publicPage(PDO $db,string $basePath):never
    {
        $pb=self::publicPlaybill($db,trim((string)($_GET['slug']??'')));if(!$pb)PlaybillPublicRenderer::render($basePath,null,[],[],[],[]);
        require_once __DIR__.'/PlaybillStudioService.php';[$sections,$company,$staff,$sponsors]=PlaybillStudioService::presentation($db,$pb);
        PlaybillPublicRenderer::render($basePath,$pb,$sections,$company,$staff,$sponsors);
    }

    private static function qr(PDO $db,string $basePath):never{$slug=trim((string)($_GET['slug']??''));if(!self::publicPlaybill($db,$slug)){http_response_code(404);exit('Playbill unavailable.');}$url=self::absoluteUrl($basePath.'/playbill?slug='.rawurlencode($slug));header('Content-Type:image/svg+xml; charset=utf-8');header('Cache-Control:private, no-store');if(!empty($_GET['download']))header('Content-Disposition:attachment; filename="playbill-'.$slug.'-qr.svg"');echo PlaybillQrService::svg($url,($_GET['background']??'')==='transparent',16);exit;}
    private static function sign(PDO $db,string $basePath):never{$slug=trim((string)($_GET['slug']??''));$pb=self::publicPlaybill($db,$slug);if(!$pb){http_response_code(404);exit('Playbill unavailable.');}$e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');header('Content-Type:text/html; charset=utf-8');header('Cache-Control: private, no-store');?><!doctype html><html><head><meta charset="utf-8"><title><?=$e((string)$pb['display_title'])?> sign</title><style>body{font-family:Georgia,serif;text-align:center}.sign{max-width:7in;margin:auto;border:3px solid;padding:.4in}.sign img{width:4.5in;max-width:80%}h1{font-size:40px}button{padding:10px 18px}@media print{button{display:none}}</style></head><body><main class="sign"><small><?=$e(OrganizationBranding::fromEnvironment()['name'])?> · Digital Program</small><h1><?=$e((string)($pb['display_title']?:$pb['production_title']))?></h1><img src="<?=($basePath?:'')?>/playbill/qr?slug=<?=rawurlencode($slug)?>" alt="QR code"><p>Scan to open the Playbill</p><button onclick="window.print()">Print sign</button></main></body></html><?php exit;}
    private static function asset(PDO $db):never{header('Cache-Control: private, no-store');$v=PlaybillEnhancementService::publicAsset($db,(string)($_GET['slug']??''),(string)($_GET['kind']??''),(int)($_GET['id']??0));if(!$v){http_response_code(404);exit('Asset unavailable.');}StorageService::stream(dirname(__DIR__),$v,false);}
    private static function playbill(PDO $db,int $production):?array{$s=$db->prepare('SELECT pb.*,p.title production_title,p.season FROM playbills pb JOIN productions p ON p.id=pb.production_id WHERE pb.production_id=:p ORDER BY pb.id DESC LIMIT 1');$s->execute(['p'=>$production]);return$s->fetch()?:null;}
    private static function publicPlaybill(PDO $db,string $slug):?array{$s=$db->prepare("SELECT pb.*,p.title production_title,p.season FROM playbills pb JOIN productions p ON p.id=pb.production_id WHERE pb.public_slug=:slug AND pb.public_enabled=1 AND pb.editorial_status IN ('published','archived') LIMIT 1");$s->execute(['slug'=>$slug]);return$s->fetch()?:null;}
    private static function sections(PDO $db,int $id):array{$s=$db->prepare('SELECT * FROM playbill_sections WHERE playbill_id=:id AND active=1 ORDER BY sort_order,id');$s->execute(['id'=>$id]);return$s->fetchAll();}

    private static function absoluteUrl(string $path):string{return((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost').$path;}
    private static function flash(string $type,string $message):void{$_SESSION['pb2_flash']=['type'=>$type,'message'=>$message];}
    private static function takeFlash():?array{$v=$_SESSION['pb2_flash']??null;unset($_SESSION['pb2_flash']);return is_array($v)?$v:null;}
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
}
