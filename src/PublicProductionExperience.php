<?php
declare(strict_types=1);
require_once __DIR__.'/Database.php';
require_once __DIR__.'/OrganizationBranding.php';
require_once __DIR__.'/RegistrationService.php';

/** Publicly announced production information only. No member schedules or casting. */
final class PublicProductionExperience
{
    public static function handles(string $route):bool{return in_array($route,['/productions','/productions/view'],true);}
    private static function status(array $p):string
    {
        return match((string)$p['lifecycle_stage']){
            'upcoming','planning'=>'Coming soon',
            'in_production'=>'Rehearsing',
            'performing'=>'Performing',
            'completed','archived'=>'Past production',
            default=>'Production'
        };
    }
    public static function render(string $route,string $basePath):never
    {
        $db=Database::connect(dirname(__DIR__));$brand=OrganizationBranding::fromEnvironment();
        $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;
        $sql="SELECT p.id,p.title,p.season,p.lifecycle_stage,p.audition_starts_at,p.audition_ends_at,p.performance_starts_at,p.performance_ends_at,EXISTS(SELECT 1 FROM production_artwork a WHERE a.production_id=p.id AND a.role='poster') has_poster FROM productions p WHERE p.announcement_published_at IS NOT NULL AND p.lifecycle_stage IN ('upcoming','planning','in_production','performing','completed','archived')";
        if($route==='/productions/view'){$q=$db->prepare($sql.' AND p.id=:id LIMIT 1');$q->execute(['id'=>$id]);$rows=$q->fetchAll();if(!$rows)http_response_code(404);}
        else $rows=$db->query($sql.' ORDER BY COALESCE(p.performance_starts_at,p.audition_starts_at) DESC,p.id DESC LIMIT 100')->fetchAll();
        // Optional external program links are independently migrated; older installs
        // remain able to display public productions before migration 100 is applied.
        try{
            $ids=array_map('intval',array_column($rows,'id'));
            if($ids){
                $ph=implode(',',array_fill(0,count($ids),'?'));
                $q=$db->prepare("SELECT production_id,external_url FROM production_external_playbills WHERE production_id IN ($ph)");
                $q->execute($ids);
                $links=$q->fetchAll(PDO::FETCH_KEY_PAIR);
                foreach($rows as &$row)$row['external_playbill_url']=$links[(int)$row['id']]??null;
                unset($row);
            }
        }catch(PDOException $exception){
            // A missing optional table never makes the main public page unavailable.
            error_log('[Public productions] External Playbill lookup unavailable: '.$exception->getCode());
        }
        $opportunities=RegistrationService::publicOpportunities($db);
        $e=static fn($v):string=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
        $u=static fn(string $path):string=>$e(($basePath?:'').$path);
        $identity=OrganizationBranding::css($brand);
        header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store');
        ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= $e($route==='/productions/view'&&$rows?$rows[0]['title']:'Our Productions') ?> · <?= $e($brand['name']) ?></title><link rel="stylesheet" href="<?= $u('/assets/css/ux-workspaces.css?v=20261027') ?>"><link rel="stylesheet" href="<?= $u('/assets/css/public-home.css?v=20261030-show-dates') ?>"><link rel="stylesheet" href="<?= $u('/assets/css/public-productions.css?v=20261030-1') ?>"></head><body class="ux-workspace public-home"><main class="stage-home" style="<?= $e($identity) ?>"><header class="stage-header"><a class="stage-brand" href="<?= $u('/') ?>"><?php if($brand['logo']!==''):?><img src="<?= $e($brand['logo']) ?>" alt=""><?php else:?><span class="stage-brand-monogram" aria-hidden="true"><?= $e(mb_strtoupper(mb_substr($brand['name'],0,1))) ?></span><?php endif;?><span class="stage-brand-wordmark"><strong><?= $e($brand['name']) ?></strong><span>CONNECT</span></span></a><nav aria-label="Public navigation"><a href="<?= $u('/productions') ?>">Productions</a><a href="<?= $u('/register') ?>">Auditions &amp; signups</a><a class="stage-signin" href="<?= $u('/login') ?>">Member sign in ↗</a></nav></header>
        <?php if(!$rows):?><section class="production-public-empty"><h1>Production unavailable</h1><p>This production isn't currently published for public viewing.</p><a href="<?= $u('/productions') ?>">Explore productions ↗</a></section><?php elseif($route==='/productions'):?><section class="production-directory-intro"><p class="stage-eyebrow">FROM OUR STAGE</p><h1>Our productions.</h1><p>Discover what we're rehearsing, what's coming next, and the shows we've shared.</p></section><div class="production-directory-grid"><?php foreach($rows as $p):?><a class="production-directory-item" href="<?= $u('/productions/view?id='.(int)$p['id']) ?>"><?php if($p['has_poster']):?><img loading="lazy" alt="" src="<?= $u('/production-poster?id='.(int)$p['id']) ?>"><?php else:?><span class="production-poster-fallback"><?= $e($p['title']) ?></span><?php endif;?><span class="production-directory-copy"><small><?= $e(self::status($p)) ?></small><strong><?= $e($p['title']) ?></strong><span><?= $e($p['season']) ?></span></span></a><?php endforeach;?></div>
        <?php else:$p=$rows[0];$audition=null;foreach($opportunities as $o){if((int)($o['production_id']??0)===(int)$p['id']&&($o['opportunity_type']??'')==='audition'){$audition=$o;break;}}?>
        <nav class="production-crumb" aria-label="Breadcrumb"><a href="<?= $u('/productions') ?>">Productions</a><span aria-hidden="true">/</span><span><?= $e($p['title']) ?></span></nav>
        <article class="production-public-feature">
          <div class="production-public-art"><?php if($p['has_poster']):?><img src="<?= $u('/production-poster?id='.(int)$p['id']) ?>" alt="<?= $e($p['title']) ?> poster"><?php else:?><div class="production-poster-fallback"><small><?= $e($brand['name']) ?></small><span><?= $e($p['title']) ?></span></div><?php endif;?></div>
          <div class="production-public-copy"><span class="production-status"><?= $e(self::status($p)) ?></span><?php if($p['season']):?><p class="production-season"><?= $e($p['season']) ?></p><?php endif;?><h1><?= $e($p['title']) ?></h1>
            <dl class="production-public-dates">
            <?php if($p['audition_starts_at']&&(!$p['audition_ends_at']||strtotime($p['audition_ends_at'])>=time())):?><div><dt>Auditions</dt><dd><?= $e(date('M j, Y',strtotime($p['audition_starts_at']))) ?><?php if($p['audition_ends_at']):?> – <?= $e(date('M j, Y',strtotime($p['audition_ends_at']))) ?><?php endif;?></dd></div><?php endif;?>
            <?php if($p['performance_starts_at']):?><div><dt>Performances</dt><dd><?= $e(date('M j, Y',strtotime($p['performance_starts_at']))) ?><?php if($p['performance_ends_at']):?> – <?= $e(date('M j, Y',strtotime($p['performance_ends_at']))) ?><?php endif;?></dd></div><?php endif;?>
            </dl>
            <div class="production-public-actions"><?php if($audition):?><a class="stage-primary" href="<?= $u('/register?slug='.rawurlencode($audition['slug'])) ?>"><?= $audition['is_full']?'Join audition waitlist':'Sign up to audition' ?> ↗</a><?php elseif(in_array($p['lifecycle_stage'],['upcoming','planning'],true)):?><p>Check back soon for audition and signup details.</p><?php endif;?><?php if(!empty($p['external_playbill_url'])):?><a href="<?= $e($p['external_playbill_url']) ?>" target="_blank" rel="noopener noreferrer">View external Playbill ↗</a><?php endif;?><a href="<?= $u('/productions') ?>">View all productions ↗</a></div>
            <p class="production-private-note">Rehearsal schedules and company communications are available to authorized members in Connect.</p>
          </div>
        </article>
        <?php endif;?><footer class="stage-footer"><span><?= $e($brand['name']) ?> · Connect</span><a href="<?= $u('/') ?>">Back to welcome</a></footer></main></body></html><?php exit;
    }
}
