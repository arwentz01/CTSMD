<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/HelpGuideCatalog.php';
require_once __DIR__ . '/MemberUI.php';

final class HelpExperience
{
    public static function handles(string $route): bool{return $route==='/help';}

    public static function render(string $basePath): never
    {
        Auth::startSession();$db=Database::connect(dirname(__DIR__));$user=Auth::currentUser($db);if(!$user){header('Location: '.($basePath?:'').'/login',true,303);exit;}
        $u=static fn(string $p):string=>($basePath?:'').$p;
        $esc=static fn(string $text):string=>htmlspecialchars($text,ENT_QUOTES,'UTF-8');
        $allowed=static fn(string $permission):bool=>match($permission){
            'all'=>true,
            'production'=>AccessPolicy::canManageProduction($user),
            'accounts'=>AccessPolicy::canManageAccounts($user),
            'moderation'=>AccessPolicy::canModerateCommunity($user),
            'schedule'=>AccessPolicy::canManageSchedule($user),
            default=>false
        };
        $guides=array_values(array_filter(HelpGuideCatalog::all(),static fn(array $guide):bool=>$allowed($guide['permission'])));
        $categories=array_values(array_unique(array_column($guides,'category')));

        header('Content-Type:text/html; charset=utf-8');?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Help &amp; Guides · CTSMD Connect</title><link rel="stylesheet" href="<?=$u('/assets/css/app.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/unified-navigation.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/product-polish.css')?>"><link rel="stylesheet" href="<?=$u('/assets/css/help-guides.css?v=20261108-banner-prompt')?>"><?php MemberUI::styles($basePath); ?></head><body class="app-body<?=MemberUI::bodyClass()?>"><div class="unified-shell"><?php AppNavigation::renderSidebar('/help',$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('CTSMD Connect','Help & Guides',$basePath);?><div class="help-page">
<section class="guide-intro" aria-labelledby="guide-intro-heading">
    <small>CTSMD CONNECT HELP CENTER</small><h2 id="guide-intro-heading">What would you like to do?</h2>
    <p>Choose a task for clear, step-by-step instructions. The guides shown here match the tools available to your account. If you don't see an action, your role or production access may be different.</p>
    <label class="guide-search-label" for="guide-search">Search help guides</label>
    <input id="guide-search" type="search" placeholder="Try ‘casting’, ‘attendance’ or ‘approve an account’" autocomplete="off" aria-controls="guide-list">
    <nav class="guide-categories" aria-label="Filter help guides"><button type="button" class="is-selected" data-guide-category="all" aria-pressed="true">All guides</button><?php foreach($categories as $category):?><button type="button" data-guide-category="<?=$esc($category)?>" aria-pressed="false"><?=$esc($category)?></button><?php endforeach;?></nav>
</section>
<section class="guide-list" id="guide-list" aria-label="Step-by-step guides">
<?php foreach($guides as $guide):?>
<details class="guide-article" id="guide-<?=$esc($guide['id'])?>" data-guide-category="<?=$esc($guide['category'])?>" data-guide-search="<?=$esc(mb_strtolower($guide['title'].' '.$guide['category'].' '.$guide['intro'].' '.implode(' ',$guide['steps']).' '.(string)($guide['prompt']??'')))?>">
  <summary><span class="guide-article-summary"><small><?=$esc($guide['category'])?></small><strong><?=$esc($guide['title'])?></strong><span><?=$esc($guide['intro'])?></span></span><span class="guide-chevron" aria-hidden="true">⌄</span></summary>
  <div class="guide-detail">
    <p class="guide-audience"><b>For:</b> <?=$esc($guide['audience'])?> <span>·</span> <b>Where:</b> <?=$esc($guide['where'])?></p>
    <ol><?php foreach($guide['steps'] as $step):?><li><?=$esc($step)?></li><?php endforeach;?></ol>
    <div class="guide-tip"><b>Good to know</b><p><?=$esc($guide['tip'])?></p></div>
    <?php if(!empty($guide['prompt'])):?>
      <section class="guide-prompt" aria-label="Reusable production banner prompt">
        <div class="guide-prompt-heading"><h4>Reusable prompt</h4><button type="button" class="guide-copy-prompt" data-guide-copy-prompt>Copy prompt</button></div>
        <p>Replace the bracketed details with your show's information. The background colors should match the production, not a fixed CTSMD color.</p>
        <pre class="guide-prompt-text"><?=$esc($guide['prompt'])?></pre>
        <span class="guide-copy-status" role="status" aria-live="polite"></span>
      </section>
    <?php endif;?>
    <a class="guide-open-link" href="<?=$u($guide['route'])?>">Open the relevant page <span aria-hidden="true">↗</span></a>
  </div>
</details>
<?php endforeach;?>
<p class="guide-no-results" id="guide-no-results" hidden>No guides match that search. Try a shorter term or choose All guides.</p>
</section>
<details class="guide-reference"><summary>More help with CTSMD Connect <span>General questions, safety and everyday features</span></summary><div class="guide-reference-body">
<section class="help-hero"><small>CTSMD CONNECT · HELP &amp; GUIDE</small><h2>Your theatre, all connected.</h2><p>Find your way around production schedules, family information, conversations, volunteer opportunities, forms, and more. What appears in your account depends on your roles and active memberships.</p></section>
<nav class="help-jumps" aria-label="Help topics"><a href="#getting-started">Getting started</a><a href="#schedule">Schedules</a><a href="#messages">Messages</a><a href="#community">Community</a><a href="#family">Families</a><a href="#forms">Forms</a><a href="#volunteer">Volunteering</a><a href="#resources">Resources</a><a href="#notifications">Notifications</a><a href="#privacy">Safety &amp; access</a><?php if(AccessPolicy::canManageProduction($user)):?><a href="#staff">For production staff</a><?php endif;?></nav>
<section id="getting-started" class="help-section"><div class="help-icon">⌂</div><div><small>GETTING STARTED</small><h3>Where to begin</h3><p><a href="<?=$u('/app')?>">Today</a> brings together timely updates and items needing your attention. <a href="<?=$u('/my-theatre')?>">My Theatre</a> gathers the productions and tools available to your account. If you work across shows, choose the production you want to work on from the sidebar before opening its tools.</p><p>Some features are available only to staff, volunteers, students, or linked guardians. You won't necessarily see every tool described here.</p></div></section>
<section id="schedule" class="help-section"><div class="help-icon">◷</div><div><small>SCHEDULES &amp; CALL TIMES</small><h3>Keep up with rehearsals and performances</h3><p><a href="<?=$u('/calendar')?>">Calendar</a> brings together events from productions you can access. Use Month, Week, or Agenda views and filter by production or relevant group. <a href="<?=$u('/schedule')?>">Production Schedule</a> focuses on your selected show; past items can be expanded when you need to look back.</p><p>Check the start time, location, and any separate family call time. Staff may publish schedule changes, which appear in notifications. You can also subscribe to your private calendar feed from the Calendar page; keep that feed link private.</p></div></section>
<section id="messages" class="help-section"><div class="help-icon">☏</div><div><small>PRIVATE MESSAGING</small><h3>Conversations with safeguarding built in</h3><p><a href="<?=$u('/messages')?>">Messages</a> is for direct and small-group conversations. Student–staff conversations require an active guardian to be included; student-to-student direct messages are not available. If the required safety relationship changes, new messaging can be restricted while previous records are retained under CTSMD policy.</p><p>Use the Messages shortcut in the header to open your inbox and check unread conversations.</p></div></section>
<section id="community" class="help-section"><div class="help-icon">#</div><div><small>COMMUNITY</small><h3>Updates in the right spaces</h3><p><a href="<?=$u('/channels')?>">Community</a> channels can be organization-wide, production-specific, role-based, private, or Team-based. Access is determined by your current memberships and permissions. Posting availability varies by channel, so some are primarily for announcements.</p></div></section>
<section id="family" class="help-section"><div class="help-icon">♙</div><div><small>FAMILIES &amp; STUDENTS</small><h3>One connected household view</h3><p>Guardians can use <a href="<?=$u('/family-hub')?>">My Family</a> to review linked students and relevant participation, calls, and requirements. Family relationships determine which student-specific information is visible. If a child or guardian relationship is missing or incorrect, ask CTSMD staff to review it rather than using another person's account.</p></div></section>
<section id="forms" class="help-section"><div class="help-icon">✓</div><div><small>FORMS &amp; REQUIREMENTS</small><h3>Know what paperwork is outstanding</h3><p><a href="<?=$u('/forms')?>">Forms</a> displays assigned requirements and their progress. Open an individual assignment to review instructions, complete it, or see whether it is awaiting review. The <a href="<?=$u('/notifications')?>">Notifications</a> page also highlights open items that need attention.</p></div></section>
<section id="volunteer" class="help-section"><div class="help-icon">♡</div><div><small>VOLUNTEERING</small><h3>Sign up when you're ready</h3><p>Explore <a href="<?=$u('/volunteer-shifts')?>">Volunteer Opportunities</a>, review <a href="<?=$u('/volunteer-readiness')?>">Volunteer Readiness</a>, and check any applicable training or background-check requirements. Some shifts need approval. We'll check requirements, available spaces and schedule conflicts before confirming your signup. Your available tools depend on your volunteer access.</p></div></section>
<section id="resources" class="help-section"><div class="help-icon">▤</div><div><small>PRODUCTION RESOURCES</small><h3>Find files, casts, and Playbills</h3><p><a href="<?=$u('/resources')?>">Resources</a> holds information and files shared with your account. Production areas may also provide <a href="<?=$u('/cast')?>">Cast</a> information, <a href="<?=$u('/playbills')?>">digital Playbills</a>, and other show-specific material, subject to visibility and publication settings.</p></div></section>
<section id="notifications" class="help-section"><div class="help-icon">●</div><div><small>NOTIFICATIONS</small><h3>See what changed and what needs action</h3><p><a href="<?=$u('/notifications')?>">Notifications</a> separates action items from recent updates. Open a related item to see more context, and mark updates as read when you're finished. You can review <a href="<?=$u('/notification-preferences')?>">notification preferences</a> for available delivery settings.</p></div></section>
<?php if(AccessPolicy::canManageProduction($user)):?><section id="staff" class="help-section"><div class="help-icon">★</div><div><small>FOR PRODUCTION STAFF</small><h3>Operate your show in one workspace</h3><p>The <a href="<?=$u('/production')?>">Production Workspace</a> brings together scheduling, attendance, cast and people, groups, readiness, Playbill management, and volunteer operations according to staff permissions. Authorized schedule managers can add events or use <a href="<?=$u('/production/schedule/import')?>">Import Schedule</a>, then review <a href="<?=$u('/production/notices')?>">Production Updates</a> before publishing changes.</p><p>Always check the selected production in the sidebar before making changes.</p></div></section><?php endif;?>
<section id="privacy" class="help-section"><div class="help-icon">◇</div><div><small>PRIVACY, SAFETY &amp; ACCESS</small><h3>Who can see your information</h3><p>What you can see depends on your CTSMD membership, production involvement, family relationships and staff responsibilities. Never share login credentials or private calendar subscription links. If you can see information you should not—or cannot access information you need—contact CTSMD staff promptly.</p><p>For an immediate child-safety concern, contact the appropriate CTSMD safeguarding lead directly rather than relying on an app message.</p></div></section>
</div></details>
</div></main></div><script src="<?=$u('/assets/js/unified-navigation.js')?>"></script><script src="<?=$u('/assets/js/help-guides.js?v=20261108-banner-prompt')?>" defer></script></body></html><?php exit;
    }
}
