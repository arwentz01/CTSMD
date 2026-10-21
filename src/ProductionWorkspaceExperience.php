<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/AppNavigation.php';
require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/ProductionContext.php';
require_once __DIR__ . '/VolunteerCoverageService.php';

final class ProductionWorkspaceExperience
{
    private const ROUTE = '/production';

    public static function handles(string $route): bool{return $route === self::ROUTE;}

    public static function render(string $basePath): never
    {
        Auth::startSession();
        $db = Database::connect(dirname(__DIR__));
        $user = Auth::currentUser($db);
        if (!$user) self::redirect(($basePath ?: '') . '/login');
        $_SESSION['production_context_csrf'] ??= bin2hex(random_bytes(24));
        $selected = ProductionContext::selected($db, $user);self::page($basePath, $user, $selected);
    }

    private static function page(string $basePath,array $user,?array $selected):never
    {
        $url=static fn(string $path):string=>($basePath?:'').$path;$esc=static fn(string $value):string=>htmlspecialchars($value,ENT_QUOTES,'UTF-8');$staff=AccessPolicy::canManageProduction($user);$scheduleManager=AccessPolicy::canManageSchedule($user);$flash=$_SESSION['production_context_flash']??null;unset($_SESSION['production_context_flash']);header('Content-Type: text/html; charset=utf-8');?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#a6192e"><title>Production workspace · CTSMD Connect</title><link rel="stylesheet" href="<?= $url('/assets/css/app.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/unified-navigation.css') ?>"><link rel="stylesheet" href="<?= $url('/assets/css/production-workspace.css?v=20261021-header-controls') ?>"></head><body class="app-body"><div class="unified-shell"><?php AppNavigation::renderSidebar('/production',$basePath,$user);?><main class="unified-main"><?php AppNavigation::renderHeader('Production','Production workspace',$basePath,AppNavigation::productionSubnav('/production',$user));?><div class="pw-page"><?php if($flash):?><div class="pw-flash <?= $esc($flash['type']) ?>"><?= $esc($flash['message']) ?></div><?php endif;?><?php if(!$selected):?><section class="pw-empty"><h3>No active production available.</h3><p><?= $staff?'Activate a production in Productions &amp; seasons to create a workspace.':'Your account is not currently assigned to an active production.' ?></p><?php if($staff):?><a class="button" href="<?= $url('/admin/productions') ?>">Productions &amp; seasons</a><?php endif;?></section><?php else:?><section class="pw-current"><div><small>CURRENT WORKSPACE</small><h2><?= $esc((string)$selected['title']) ?></h2><p><?= $esc((string)($selected['season']?:'Season not set')) ?> · Everything below opens in this production context. Switch shows using the Working Production selector in the sidebar.</p></div><div class="pw-current-aside"><span>Active</span><?php if($staff):?><a href="<?= $url('/admin/productions') ?>">Manage productions →</a><?php endif;?></div></section><div class="pw-modules"><?php if($staff):?><a href="<?= $url('/production/casting') ?>"><i>★</i><b>Casting</b><span>Audition review, role decisions and roster finalization.</span></a><a href="<?= $url('/production/day') ?>"><i>☀</i><b>Production day</b><span>Call sheet, attendance, volunteers and live operations.</span></a><a href="<?= $url('/production/readiness') ?>"><i>◈</i><b>Readiness</b><span>System signals and the production checklist.</span></a><?php endif;?><a href="<?= $url('/schedule') ?>"><i>◷</i><b>Schedule</b><span>Rehearsals, performances, calls and locations.</span></a><a href="<?= $url('/attendance') ?>"><i>✓</i><b>Attendance</b><span><?= $staff?'Take roll, review absences and track participation.':'View relevant calls and report an absence.' ?></span></a><?php if($staff):?><a href="<?= $url('/production/people') ?>"><i>★</i><b>Cast & people</b><span>Students, guardians and production staff.</span></a><?php endif;?><a href="<?= $url('/channels') ?>"><i>#</i><b>Community</b><span>Production channels and announcements.</span></a><a href="<?= $url('/volunteer-shifts') ?>"><i>♡</i><b>Volunteer</b><span>Coverage and opportunities for this show.</span></a><a href="<?= $url('/forms') ?>"><i>✓</i><b>Forms</b><span>Requirements and assigned paperwork.</span></a><a href="<?= $url('/playbills') ?>"><i>▤</i><b>Playbill</b><span>Digital Playbill workspace.</span></a><?php if($scheduleManager):?><a href="<?= $url('/production/notices') ?>"><i>↗</i><b>Production updates</b><span>Review and publish schedule-change communications.</span></a><?php endif;?><?php if($staff):?><a href="<?= $url('/admin/volunteer-shifts') ?>"><i>♟</i><b>Volunteer Operations</b><span>Create shifts and manage staffing.</span></a><?php endif;?></div><?php endif;?></div></main></div><script src="<?= $url('/assets/js/unified-navigation.js') ?>"></script></body></html><?php exit;
    }
    private static function redirect(string $url):never{header('Location: '.$url,true,303);exit;}
}
