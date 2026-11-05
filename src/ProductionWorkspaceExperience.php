<?php

declare(strict_types=1);
require_once __DIR__.'/MemberUI.php';

require_once __DIR__.'/WorkspaceUI.php';

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/WorkspaceUI.php';
require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/ProductionContext.php';
require_once __DIR__ . '/VolunteerCoverageService.php';
require_once __DIR__ . '/StorageService.php';
require_once __DIR__ . '/CastingCommunicationService.php';

final class ProductionWorkspaceExperience
{
    private const ROUTE = '/production';
    private const ARTWORK_ROUTE = '/production/artwork-image';

    public static function handles(string $route): bool{return in_array($route,[self::ROUTE,self::ARTWORK_ROUTE],true);}

    public static function render(string $basePath): never
    {
        Auth::startSession();
        $db = Database::connect(dirname(__DIR__));
        $user = Auth::currentUser($db);
        if (!$user) self::redirect(($basePath ?: '') . '/login');
        $_SESSION['production_context_csrf'] ??= bin2hex(random_bytes(24));
        if(($_GET['production_artwork']??'')==='1')self::artworkImage($db,$user);
        $selected = ProductionContext::selected($db, $user);self::page($basePath, $user, $selected);
    }

    private static function artworkImage(PDO $db,array $user):never
    {
        $id=(int)($_GET['id']??0);$role=(string)($_GET['role']??'banner');
        if(!in_array($role,['banner','poster','thumbnail'],true)){http_response_code(404);exit;}
        $allowed=false;foreach(ProductionContext::activeProductions($db,$user) as $p){if((int)$p['id']===$id){$allowed=true;break;}}
        if(!$allowed){http_response_code(404);exit;}
        $s=$db->prepare('SELECT sv.* FROM production_artwork a JOIN stored_file_versions sv ON sv.stored_file_id=a.stored_file_id WHERE a.production_id=:id AND a.role=:role ORDER BY sv.version_number DESC LIMIT 1');
        $s->execute(['id'=>$id,'role'=>$role]);$image=$s->fetch();
        if(!$image&&$role==='banner'){$s->execute(['id'=>$id,'role'=>'poster']);$image=$s->fetch();}
        if(!$image||!in_array($image['mime_type'],['image/jpeg','image/png','image/webp'],true)){http_response_code(404);exit;}
        header('Cache-Control: private, no-store');StorageService::stream(dirname(__DIR__),$image,false);
    }

    private static function page(string $basePath,array $user,?array $selected):never
    {
        $db=Database::connect(dirname(__DIR__));$url=static fn(string $path):string=>($basePath?:'').$path;$esc=static fn(string $value):string=>htmlspecialchars($value,ENT_QUOTES,'UTF-8');$staff=AccessPolicy::canManageProduction($user);$scheduleManager=AccessPolicy::canManageSchedule($user);
        $canManageSignups=AccessPolicy::canManageForms($user);
        $auditionOpportunities=[];$archivedAuditionOpportunities=[];
        if($selected&&$canManageSignups){
            // Auditions are deliberately absent from general Operations listings.
            // The workspace must show every audition belonging to this production.
            $auditionStmt=$db->prepare("SELECT ro.id,ro.title,ro.status,ro.slug,ro.registration_opens_at,ro.registration_closes_at,
                 (SELECT COUNT(*) FROM registration_submissions rs WHERE rs.opportunity_id=ro.id AND rs.status IN ('submitted','accepted')) registered_count,
                 (SELECT COUNT(*) FROM registration_submissions rs WHERE rs.opportunity_id=ro.id AND rs.status='waitlisted') waitlisted_count
                 FROM registration_opportunities ro WHERE ro.production_id=:production AND ro.opportunity_type='audition'
                 ORDER BY FIELD(ro.status,'published','draft','closed','archived'),ro.id DESC");
            $auditionStmt->execute(['production'=>(int)$selected['id']]);
            $allAuditions=$auditionStmt->fetchAll();
            $auditionOpportunities=array_values(array_filter($allAuditions,static fn(array $a):bool=>$a['status']!=='archived'));
            $archivedAuditionOpportunities=array_values(array_filter($allAuditions,static fn(array $a):bool=>$a['status']==='archived'));
        }
        $flash=$_SESSION['production_context_flash']??null;unset($_SESSION['production_context_flash']);header('Content-Type: text/html; charset=utf-8');?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#a6192e"><title>Production workspace · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/production-workspace.css?v=20261021-header-controls') ?>"><style>.pw-current-artwork{position:relative;overflow:hidden;isolation:isolate;min-height:190px}.pw-current-background{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center;z-index:-2}.pw-current-artwork:before{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(18,10,15,.82),rgba(18,10,15,.44) 65%,rgba(18,10,15,.18));z-index:-1;pointer-events:none}.pw-current-artwork>.pw-current-copy,.pw-current-artwork>.pw-current-aside{position:relative;z-index:1}.pw-current-artwork .pw-current-copy h2,.pw-current-artwork .pw-current-copy p,.pw-current-artwork .pw-current-copy small{color:#fff}</style><?php WorkspaceUI::operationsStyles($basePath); ?><?php MemberUI::styles($basePath,!$staff); ?></head><body class="app-body ux-operations ux-workspace<?=MemberUI::bodyClass()?>"><a class="ux-skip" href="#ux-main">Skip to main content</a><div class="unified-shell"><?php AppNavigation::renderSidebar('/production',$basePath,$user);?><main class="unified-main" id="ux-main" tabindex="-1"><?php AppNavigation::renderHeader('Production','Production workspace',$basePath,AppNavigation::productionSubnav('/production',$user)); MemberUI::wayfinding($basePath);?><div class="pw-page ops-page"><?php if($flash):?><div class="pw-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif;?><?php if(!$selected):?><section class="pw-empty"><h3>No active production available.</h3><p><?= $staff?'Activate a production in Productions &amp; seasons to create a workspace.':'Your account is not currently assigned to an active production.' ?></p><?php if($staff):?><a class="button" href="<?= $url('/admin/productions') ?>">Productions &amp; seasons</a><?php endif;?></section><?php else:?><section class="pw-current pw-current-artwork"><?php if($selected): ?><img class="pw-current-background" src="<?= $url('/production/artwork-image') ?>?production_artwork=1&amp;id=<?= (int)$selected['id'] ?>&amp;role=banner" alt="" onerror="this.style.display='none'"><?php endif; ?><div class="pw-current-copy"><small>CURRENT WORKSPACE</small><h2><?= $esc((string)$selected['title']) ?></h2><p><?= $esc((string)($selected['season']?:'Season not set')) ?> · Everything below opens in this production context. Switch shows using the Working Production selector in the sidebar.</p></div><div class="pw-current-aside"><span><?= $esc(ucwords(str_replace("_"," ",(string)($selected["lifecycle_stage"]??"planning")))) ?></span><?php if($staff):?><a href="<?= $url('/admin/productions') ?>">Manage productions →</a><?php endif;?></div></section><?php if((string)($selected['lifecycle_stage']??'')==='planning'):?><div role="status" style="font-size:12px;color:#713a16;background:#fff2de;border:1px solid #e4c798;border-radius:8px;padding:8px 12px;margin:10px 0">Planning · Production preparation is not visible to the wider community. Only separately published announcements are public.</div><?php endif;?><?php if(!empty($selected['audition_starts_at'])||!empty($selected['performance_starts_at'])):?><div style="padding:8px 12px;background:#fff;border:1px solid #ebdfce;border-radius:9px;font-size:13px;margin:10px 0"><?php if(!empty($selected['audition_starts_at'])):?><span style="margin-right:20px"><b>Auditions:</b> <?= $esc(date('M j, Y',strtotime((string)$selected['audition_starts_at']))) ?><?php if(!empty($selected['audition_ends_at'])):?> – <?= $esc(date('M j, Y',strtotime((string)$selected['audition_ends_at']))) ?><?php endif;?></span><?php endif;?><?php if(!empty($selected['performance_starts_at'])):?><span><b>Performances:</b> <?= $esc(date('M j, Y',strtotime((string)$selected['performance_starts_at']))) ?><?php if(!empty($selected['performance_ends_at'])):?> – <?= $esc(date('M j, Y',strtotime((string)$selected['performance_ends_at']))) ?><?php endif;?></span><?php endif;?></div><?php endif;?><?php foreach(['callback'=>'Callbacks','cast'=>'Cast'] as $castType=>$castLabel):$published=CastingCommunicationService::publishedForProduction($db,(int)$selected['id'],$castType);if(!$published)continue;?><section class="pw-published-cast" style="background:#fff;border:1px solid #e6d9c6;border-radius:14px;padding:18px;margin:12px 0"><h3 style="margin:0 0 8px"><?= $esc('Published '.$castLabel) ?></h3><?php if($published['headline']):?><p><b><?= $esc((string)$published['headline']) ?></b></p><?php endif;?><?php if($published['member_note']):?><p><?= nl2br($esc((string)$published['member_note'])) ?></p><?php endif;?><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px"><?php foreach($published['cast'] as $person):?><div style="padding:9px 12px;background:#fffaf3;border-radius:8px"><b><?= $esc((string)$person['name']) ?></b><?php if(!empty($person['role'])):?> · <?= $esc((string)$person['role']) ?><?php endif;?></div><?php endforeach;?></div></section><?php endforeach;?>
        <?php if($staff&&$canManageSignups&&($selected['lifecycle_stage']??'')!=='archived'):?>
        <section class="pw-auditions" id="workspace-auditions" tabindex="-1" aria-labelledby="workspace-auditions-title" style="background:#fff;border:1px solid #e4dcd5;border-radius:12px;padding:18px;margin:14px 0">
          <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
            <div><small style="font-weight:700;letter-spacing:.08em;color:#8b233b">THIS PRODUCTION</small><h3 id="workspace-auditions-title" style="margin:4px 0">Auditions</h3><p style="margin:4px 0;color:#685d59;font-size:13px">Review audition signups and submissions without leaving this production's context.</p></div>
            <a class="button" href="<?= $url('/admin/registrations/edit?production_id='.(int)$selected['id']) ?>">Create audition</a>
          </div>
          <?php if(!$auditionOpportunities):?><p style="margin:14px 0 0">No current auditions for this production.</p><?php endif;?>
          <?php foreach($auditionOpportunities as $audition):?>
            <article style="border-top:1px solid #e4dcd5;margin-top:12px;padding-top:12px">
              <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center"><strong><?= $esc((string)$audition['title']) ?></strong><span style="color:#685d59;font-size:13px"><?= $esc(ucfirst((string)$audition['status'])) ?> · <?= (int)$audition['registered_count'] ?> registered<?= (int)$audition['waitlisted_count']?' · '.(int)$audition['waitlisted_count'].' waitlisted':'' ?></span></div>
              <p style="font-size:13px;color:#685d59;margin:5px 0">Registration window: <?= $audition['registration_opens_at']?$esc(date('m/d/Y',strtotime((string)$audition['registration_opens_at']))):'No opening restriction' ?> – <?= $audition['registration_closes_at']?$esc(date('m/d/Y',strtotime((string)$audition['registration_closes_at']))):'No closing restriction' ?></p>
              <nav aria-label="<?= $esc((string)$audition['title']) ?> audition actions" style="display:flex;flex-wrap:wrap;gap:14px;font-size:13px">
                <a href="<?= $url('/admin/registrations/edit?id='.(int)$audition['id']) ?>">Edit audition</a>
                <a href="<?= $url('/admin/registrations/view?id='.(int)$audition['id']) ?>">View registrants</a>
                <a href="<?= $url('/production/casting') ?>">Casting</a>
                <?php if($audition['status']==='published'):?><a href="<?= $url('/register?slug='.rawurlencode((string)$audition['slug'])) ?>" target="_blank" rel="noopener noreferrer">Public signup ↗</a><?php endif;?>
              </nav>
            </article>
          <?php endforeach;?>
          <?php if($archivedAuditionOpportunities):?><details style="margin-top:18px;border-top:1px solid #e4dcd5;padding-top:12px"><summary style="cursor:pointer;font-weight:700;min-height:32px">Archived auditions (<?=count($archivedAuditionOpportunities)?>)</summary>
            <?php foreach($archivedAuditionOpportunities as $audition):?><article style="border-top:1px solid #e4dcd5;margin-top:10px;padding-top:10px"><strong><?= $esc((string)$audition['title']) ?></strong><span style="margin-left:8px;font-size:13px;color:#685d59"><?= (int)$audition['registered_count'] ?> registered</span><nav style="display:flex;gap:14px;flex-wrap:wrap;font-size:13px;margin-top:8px" aria-label="<?= $esc((string)$audition['title']) ?> archived audition actions"><a href="<?= $url('/admin/registrations/edit?id='.(int)$audition['id']) ?>">View / edit</a><a href="<?= $url('/admin/registrations/view?id='.(int)$audition['id']) ?>">View registrants</a></nav></article><?php endforeach;?>
          </details><?php endif;?>
        </section>
        <?php endif;?><div class="pw-modules"><section class="pw-tool-group"><h3>Run the production</h3><div class="pw-tool-links"><?php if($staff):?><?php if($canManageSignups):?><a href="<?= $url('/production?view=auditions#workspace-auditions') ?>"><i>✎</i><b>Auditions</b><span>View, edit and create this production's audition signups.</span></a><?php endif;?><a href="<?= $url('/production/casting') ?>"><i>★</i><b>Casting</b><span>Audition review, role decisions and roster finalization.</span></a><a href="<?= $url('/production/day') ?>"><i>☀</i><b>Production day</b><span>Call sheet, attendance, volunteers and live operations.</span></a><a href="<?= $url('/production/tasks') ?>"><i>✓</i><b>Tasks</b><span>Assign tasks and track each participant’s completion.</span></a><a href="<?= $url('/production/late-pickups') ?>"><i>⌚</i><b>Late</b><span>Review late arrivals, late pickups, and guardian notice follow-up.</span></a><a href="<?= $url('/production/readiness') ?>"><i>◈</i><b>Readiness</b><span>Automated system signals and production health.</span></a><?php endif;?><a href="<?= $url('/schedule') ?>"><i>◷</i><b>Schedule</b><span>Rehearsals, performances, calls and locations.</span></a><a href="<?= $url('/videos') ?>"><i>▶</i><b>Video Library</b><span>CTSMD-wide videos and rehearsal resources for this show.</span></a><a href="<?= $url('/attendance') ?>"><i>✓</i><b>Attendance</b><span><?= $staff?'Take roll, review absences and track participation.':'View relevant calls and report an absence.' ?></span></a><?php if($staff):?><a href="<?= $url('/production/people') ?>"><i>★</i><b>Production People</b><span>Cast, production team and guardians.</span></a><?php endif;?><?php if($staff&&in_array('forms.manage',Auth::permissions($db,(int)$user['id']),true)):?><a href="<?= $url('/production/support-needs') ?>"><i>♡</i><b>Participant support needs</b><span>Confidential allergies and accommodations from production intakes.</span></a><?php endif;?></div></section><section class="pw-tool-group"><h3>Connect the company</h3><div class="pw-tool-links"><a href="<?= $url('/channels') ?>"><i>#</i><b>Community</b><span>Production channels and announcements.</span></a><a href="<?= $url('/volunteer-shifts') ?>"><i>♡</i><b>Volunteer</b><span>Coverage and opportunities for this show.</span></a><a href="<?= $url('/forms') ?>"><i>✓</i><b>Forms</b><span>Requirements and assigned paperwork.</span></a><a href="<?= $url('/playbills') ?>"><i>▤</i><b>Playbill</b><span>Digital Playbill workspace.</span></a><?php if($scheduleManager):?><a href="<?= $url('/production/notices') ?>"><i>↗</i><b>Production updates</b><span>Review and publish schedule-change communications.</span></a><?php endif;?><?php if($staff):?><a href="<?= $url('/admin/volunteer-shifts') ?>"><i>♟</i><b>Volunteer Operations</b><span>Create shifts and manage staffing.</span></a><?php endif;?></div></section></div><?php endif;?></div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script><?php if(($_GET['view']??'')==='auditions'&&$staff&&$canManageSignups&&$selected&&($selected['lifecycle_stage']??'')!=='archived'):?><script>document.addEventListener('DOMContentLoaded',function(){var target=document.getElementById('workspace-auditions');if(target){target.scrollIntoView({block:'start'});target.focus({preventScroll:true});}});</script><?php endif;?></body></html><?php exit;
    }
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
}
