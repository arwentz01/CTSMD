<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/AppNavigation.php';
require_once __DIR__.'/ProductionContext.php';
require_once __DIR__.'/ProductionSupportNeedsService.php';

final class ProductionSupportNeedsExperience
{
    public static function handles(string $route):bool{return $route==='/production/support-needs';}

    public static function render(string $basePath):never
    {
        Auth::startSession();
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');
        header('X-Robots-Tag: noindex, nofollow');
        header('Referrer-Policy: no-referrer');
        $db=Database::connect(dirname(__DIR__));
        $actor=Auth::currentUser($db);
        if(!$actor){header('Location: '.($basePath?:'').'/login',true,303);exit;}
        try{
            ProductionSupportNeedsService::assertAuthorized($db,$actor);
            $production=ProductionContext::selected($db,$actor);
            if($production){
                $data=ProductionSupportNeedsService::list($db,$actor,(int)$production['id']);
            }else{
                $data=['production'=>null,'entries'=>[]];
            }
        }catch(RuntimeException $e){http_response_code(403);header('Content-Type: text/plain; charset=utf-8');exit('Restricted: '.$e->getMessage());}
        self::page($basePath,$actor,$data);
    }

    private static function page(string $basePath,array $actor,array $data):never
    {
        $u=static fn(string $p):string=>($basePath?:'').$p;
        $e=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
        $rows=$data['entries'];
        $allergies=array_values(array_filter($rows,static fn(array $r):bool=>trim((string)$r['allergy_information'])!==''));
        $accommodations=array_values(array_filter($rows,static fn(array $r):bool=>trim((string)$r['accommodation_information'])!==''));
        header('Content-Type: text/html; charset=utf-8');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Participant support needs · CTSMD Connect</title><link rel="stylesheet" href="<?=$u('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/production-support-needs.css')?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/production/support-needs',$basePath,$actor);?><main class="unified-main"><?php AppNavigation::renderHeader('Production','Participant support needs',$basePath);?><div class="psn-page">
        <header class="psn-head"><div><small>AUTHORIZED STAFF · CONFIDENTIAL</small><h1>Participant support needs</h1><p><?=$data['production']?$e($data['production']['title']):'Select a production to review its intake responses.'?></p></div><a href="<?=$u('/production')?>">Back to production</a></header>
        <p class="psn-intro">These are voluntary responses from active audition and signup intakes for this production. Use the information only to support participants. Do not share this page, print it for general distribution, or copy answers into public rosters or Playbills. Blank answers are not confirmation of no allergies or no support needs.</p>
        <?php if(!$data['production']):?><section class="psn-empty">No production selected.</section><?php else:?>
        <div class="psn-summary"><span><b><?=count($allergies)?></b> allergy responses</span><span><b><?=count($accommodations)?></b> accommodation responses</span></div>
        <section class="psn-section"><h2>Allergies and sensitivities</h2><?php if(!$allergies):?><p class="psn-empty">No allergy information was provided in current intake submissions.</p><?php endif;?><?php foreach($allergies as $r):?><article class="psn-record"><div><strong><?=$e($r['participant_first_name'].' '.$r['participant_last_name'])?></strong><small><?=$e($r['opportunity_title'])?> · <?=$e(ucfirst($r['status']))?></small></div><p><?=nl2br($e($r['allergy_information']))?></p><a href="<?=$u('/admin/registrations/intake?submission='.(int)$r['id'])?>">Review intake</a></article><?php endforeach;?></section>
        <section class="psn-section"><h2>Accommodations and accessibility</h2><?php if(!$accommodations):?><p class="psn-empty">No accommodations were requested in current intake submissions.</p><?php endif;?><?php foreach($accommodations as $r):?><article class="psn-record"><div><strong><?=$e($r['participant_first_name'].' '.$r['participant_last_name'])?></strong><small><?=$e($r['opportunity_title'])?> · <?=$e(ucfirst($r['status']))?></small></div><p><?=nl2br($e($r['accommodation_information']))?></p><a href="<?=$u('/admin/registrations/intake?submission='.(int)$r['id'])?>">Review intake</a></article><?php endforeach;?></section>
        <?php endif;?></div></main></div><script src="<?=$u('/assets/js/unified-navigation.js')?>"></script></body></html><?php exit;
    }
}
