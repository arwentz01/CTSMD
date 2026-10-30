<?php
declare(strict_types=1);
require_once __DIR__.'/MemberUI.php';
require_once __DIR__.'/Database.php';
require_once __DIR__.'/Auth.php';
require_once __DIR__.'/AppNavigation.php';
require_once __DIR__.'/WorkspaceUI.php';
require_once __DIR__.'/HomeDashboardService.php';
require_once __DIR__.'/OrganizationBranding.php';

final class HomeExperience
{
    private const ROUTES=['/app'];
    public static function handles(string $route):bool{return in_array($route,self::ROUTES,true);}
    public static function render(string $route,string $basePath,array $legacyData=[]):never
    {
        Auth::startSession();$db=Database::connect(dirname(__DIR__));$user=Auth::currentUser($db);
        if(!$user)self::redirect($basePath.'/login');
        $data=HomeDashboardService::build($db,$user);
        $data['artworks']=self::artworks($db,$data['productions']);
        self::page($basePath,$user,$data,OrganizationBranding::fromEnvironment());
    }
    /** Metadata only, restricted to the same authorized production list as image delivery. */
    private static function artworks(PDO $db,array $productions):array
    {
        if(!$productions)return [];
        $allowed=[];foreach($productions as $p)$allowed[(int)$p['id']]=$p;
        $ids=array_keys($allowed);$in=implode(',',array_fill(0,count($ids),'?'));
        $q=$db->prepare("SELECT a.production_id,a.role FROM production_artwork a JOIN stored_file_versions v ON v.id=(SELECT v2.id FROM stored_file_versions v2 WHERE v2.stored_file_id=a.stored_file_id ORDER BY v2.version_number DESC LIMIT 1) WHERE a.production_id IN ($in) AND a.role IN ('poster','banner') AND v.mime_type IN ('image/jpeg','image/png','image/webp') ORDER BY CASE a.role WHEN 'poster' THEN 0 ELSE 1 END,a.production_id");
        $q->execute($ids);$art=[];foreach($q->fetchAll() as $row){$id=(int)$row['production_id'];if(isset($allowed[$id])&&!isset($art[$id]))$art[$id]=$allowed[$id]+['artwork_role'=>$row['role']];}
        $ordered=[];foreach($productions as $p)if(isset($art[(int)$p['id']]))$ordered[]=$art[(int)$p['id']];
        return array_slice($ordered,0,3);
    }
    private static function page(string $basePath,array $user,array $data,array $brand):never
    {
        $u=static fn(string $p):string=>($basePath?:'').$p;
        $e=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');
        $membership=$data['membership'];$productions=$data['productions'];$art=$data['artworks'];$lead=$art[0]??null;
        $approved=(bool)$membership['approved'];$staff=AccessPolicy::isStaff($user);
        $connected=$approved||(bool)$productions;
        $operations=$staff||AccessPolicy::canModerateCommunity($user)||AccessPolicy::canManageSafeguarding($user);
        $action=$data['attention'][0]??null;$next=$data['next_event'];
        header('Content-Type:text/html; charset=utf-8');?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="<?=$e($brand['primary'])?>"><title><?=$e($brand['name'])?> · Connect</title><link rel="stylesheet" href="<?=$u('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/unified-navigation.css')?>"><?php WorkspaceUI::styles($basePath);MemberUI::styles($basePath);?><link rel="stylesheet" href="<?=$u('/assets/css/home-experience.css?v=20261030-editorial')?>"></head>
<body class="app-body ux-workspace<?=MemberUI::bodyClass()?>"><a class="ux-skip" href="#ux-main">Skip to main content</a><div class="unified-shell"><?php AppNavigation::renderSidebar('/app',$basePath,$user);?><main class="unified-main" id="ux-main" tabindex="-1"><?php AppNavigation::renderHeader('Home','Home',$basePath);?>
<div class="theatre-home" style="<?=$e(OrganizationBranding::css($brand))?>">
    <div class="theatre-home-masthead"><?php if($brand['logo']!==''):?><img class="theatre-home-logo" src="<?=$e($brand['logo'])?>" alt="" width="64" height="64"><?php endif;?><span><?=$e($brand['name'])?><span class="theatre-home-platform">Connect</span></span><?php if($brand['website']!==''):?><a href="<?=$e($brand['website'])?>">Visit our website <span aria-hidden="true">↗</span></a><?php endif;?></div>
    <section class="theatre-home-cover <?=$lead?'has-artwork':'type-only'?>" aria-labelledby="home-title">
        <div class="theatre-home-story"><h2 id="home-title">Theatre is a<br><em>shared act.</em></h2><p class="theatre-home-intro"><?=$e($brand['welcome']?:'A place for your productions, your people, and the part you play.')?></p>
            <div class="theatre-home-actions"><?php if($connected||$staff):?><a class="theatre-home-primary" href="<?=$u('/my-theatre/')?>">Enter My Theatre <span aria-hidden="true">↗</span></a><?php else:?><a class="theatre-home-primary" href="<?=$u('/account')?>">View my account <span aria-hidden="true">↗</span></a><?php endif;?>
            <?php if($connected):?><nav aria-label="Conversations"><a href="<?=$u('/channels')?>">Community</a><a href="<?=$u('/messages')?>">Messages</a></nav><?php endif;?></div>
            <?php if($brand['tagline']!==''):?><p class="theatre-home-tagline"><?=$e($brand['tagline'])?></p><?php endif;?>
        </div>
        <?php if($lead):?><figure class="theatre-home-artwork is-<?=$e($lead['artwork_role'])?>"><img src="<?=$u('/production?production_artwork=1&id='.(int)$lead['id'].'&role='.$lead['artwork_role'])?>" alt="<?=$e($lead['title'])?> — <?=$e($lead['artwork_role'])?> artwork" fetchpriority="high" decoding="async"><figcaption><strong><?=$e($lead['title'])?></strong><?php if(!empty($lead['season'])):?><span><?=$e($lead['season'])?></span><?php endif;?></figcaption></figure><?php else:?><div class="theatre-home-imprint" aria-hidden="true"><span>Made<br>together.</span></div><?php endif;?>
    </section>
    <?php if(($user['organization_membership_status']??'')==='denied'):?><section class="theatre-home-notice" aria-label="Membership"><p>General membership access is not active. Your account remains available; contact your theatre if you need help.</p><a href="<?=$u('/account')?>">Review membership</a></section><?php elseif(!$approved&&!$staff):?><section class="theatre-home-notice" aria-label="Membership"><p>Your sign-in is ready. Your theatre is reviewing membership access. Production access is assigned separately.</p><?php if(!Auth::hasRole($user,'student')):?><a href="<?=$u('/family/manage')?>">Manage household</a><?php else:?><a href="<?=$u('/account')?>">View account</a><?php endif;?></section><?php endif;?>
    <div class="theatre-home-footer">
        <?php if($action):?><section class="theatre-home-next"><h3>Your next step</h3><p><?=$e((string)$action['title'])?></p><a href="<?=$u((string)$action['href'])?>">Review details <span aria-hidden="true">↗</span></a></section><?php elseif($next):?><section class="theatre-home-next"><h3>Coming up</h3><p><?=$e((string)$next['title'])?></p><span><?=date('D, M j · g:i A',strtotime((string)$next['starts_at']))?></span><a href="<?=$u('/calendar')?>">View schedule <span aria-hidden="true">↗</span></a></section><?php endif;?>
        <?php if(count($art)>1):?><section class="theatre-home-season"><h3>Also on your stage</h3><?php foreach(array_slice($art,1) as $p):?><a href="<?=$u('/calendar?view=agenda&production='.(int)$p['id'])?>"><?=$e((string)$p['title'])?> <span aria-hidden="true">↗</span></a><?php endforeach;?></section><?php endif;?>
        <?php if($staff||$operations):?><nav class="theatre-home-workspaces" aria-label="Staff workspaces"><?php if($staff):?><a href="<?=$u('/production')?>">Production workspace <span aria-hidden="true">↗</span></a><?php endif;?><?php if($operations):?><a href="<?=$u('/admin/operations')?>">Operations <span aria-hidden="true">↗</span></a><?php endif;?></nav><?php endif;?>
    </div>
</div></main></div><script src="<?=$u('/assets/js/unified-navigation.js')?>"></script></body></html><?php exit;
    }
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
}
