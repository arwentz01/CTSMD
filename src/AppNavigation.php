<?php

declare(strict_types=1);

require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ProductionContext.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/CommunicationReadStateService.php';

final class AppNavigation
{
    private static array $sidebarContextCache = [];
    private static ?array $headerUser = null;
    private static array $notificationUnreadCache = [];

    public static function renderSidebar(string $route, string $basePath, array $user): void
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        self::$headerUser = $user;
        $staff = AccessPolicy::isStaff($user);
        $operations = $staff
            || AccessPolicy::canModerateCommunity($user)
            || AccessPolicy::canManageSafeguarding($user);
        $approved = Auth::isApprovedMember($user);
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION['production_context_csrf'] ??= bin2hex(random_bytes(24));
        $_SESSION['auth_csrf'] ??= bin2hex(random_bytes(24));

        $context = self::sidebarContext($user, $staff);
        $productionOptions = $context['productionOptions'];
        $selectedProduction = $context['selectedProduction'];
        $hasFamily = $context['hasFamily'];
        $hasActiveProduction = $context['hasActiveProduction'];
        $hasArchive = $context['hasArchive'];
        $unread = $context['unread'];

        $memberTheatre = $approved || $hasActiveProduction || $hasArchive || $staff;
        $communicationsAvailable = $approved || $hasActiveProduction;
        $homeRoutes = ['/app','/family/action','/notifications','/notification-preferences','/push-settings'];
        $familyRoutes = ['/family-hub','/parent','/family/manage','/onboarding','/student-profile/family'];
        $productionRoutes = ['/production','/production/casting','/production/readiness','/production/tasks','/production/people','/production/groups','/production/groups/view','/production/schedule/new','/production/schedule/import','/schedule','/production/day','/production/edit','/production/notices','/production/notice','/attendance','/attendance/take','/attendance/report','/production/late-pickups','/playbills','/admin/playbill','/admin/playbill/media','/admin/productions','/calendar','/cast'];
        $myTheatreRoutes = ['/my-theatre','/theatre-history','/archive','/resources','/volunteer-readiness','/volunteer-shifts','/volunteer/shift','/volunteer/approvals','/volunteer/training','/volunteer/history','/volunteer/service-record','/volunteer/verifications','/volunteer/verification','/forms','/forms/view'];
        $operationsPrefixes = ['/admin/','/people','/safeguarding','/staff'];

        $active = static function(string $destination) use ($route, $staff, $homeRoutes, $familyRoutes, $productionRoutes, $myTheatreRoutes, $operationsPrefixes): string {
            if ($destination === 'home') return in_array($route, $homeRoutes, true) ? ' active' : '';
            if ($destination === 'family') return in_array($route, $familyRoutes, true) ? ' active' : '';
            if ($destination === 'production') return $staff && in_array($route, $productionRoutes, true) ? ' active' : '';
            if ($destination === 'my-theatre') {
                if ($route === '/my-theatre') return ' active';
                if (!$staff && in_array($route, array_merge($productionRoutes, $myTheatreRoutes), true)) return ' active';
                return $staff && in_array($route, $myTheatreRoutes, true) ? ' active' : '';
            }
            if ($destination === 'community') return ($route === '/channels' || str_starts_with($route, '/channels/') || $route === '/teams' || str_starts_with($route, '/teams/')) ? ' active' : '';
            if ($destination === 'messages') return ($route === '/messages' || str_starts_with($route, '/messages/')) ? ' active' : '';
            if ($destination === 'operations') {
                foreach ($operationsPrefixes as $prefix) if ($route === rtrim($prefix, '/') || str_starts_with($route, $prefix)) return ' active';
            }
            return '';
        };
        ?>
        <aside class="unified-sidebar" data-unified-sidebar>
            <div class="unified-sidebar-head">
                <a class="unified-brand" href="<?=$url('/app')?>"><span>C</span><b>CTSMD <small>CONNECT</small></b></a>
                <button class="unified-close" type="button" data-nav-close aria-label="Close navigation">×</button>
            </div>
            <nav class="unified-nav" aria-label="Primary navigation">
                <a class="unified-nav-item<?=$active('home')?>" href="<?=$url('/app')?>"><i>⌂</i><span><b>Home</b><small><?=$approved?'Today & attention':'Account & membership status'?></small></span></a>

                <?php if ($hasFamily): ?>
                    <a class="unified-nav-item<?=$active('family')?>" href="<?=$url('/family-hub')?>"><i>♟</i><span><b>Family</b><small>Children, calls & household</small></span></a>
                <?php elseif (!$approved && !$staff): ?>
                    <a class="unified-nav-item<?=$active('family')?>" href="<?=$url('/family/manage')?>"><i>✓</i><span><b>Finish setup</b><small>Household & membership</small></span></a>
                <?php endif; ?>

                <?php if ($staff && $productionOptions): ?>
                    <form class="unified-production-switcher" method="post" action="<?=$url('/production/select')?>">
                        <input type="hidden" name="csrf_token" value="<?=$esc((string)$_SESSION['production_context_csrf'])?>">
                        <input type="hidden" name="return_to" value="<?=$esc($route)?>">
                        <label for="unified-production-select">Working production</label>
                        <select id="unified-production-select" name="production_id" onchange="this.form.submit()">
                            <?php foreach($productionOptions as $production): ?>
                                <option value="<?=(int)$production['id']?>"<?=$selectedProduction&&(int)$selectedProduction['id']===(int)$production['id']?' selected':''?>><?=$esc((string)$production['title'])?><?=!empty($production['season'])?' · '.$esc((string)$production['season']):''?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                <?php endif; ?>

                <?php if ($memberTheatre): ?><span class="unified-nav-label">Theatre</span><?php endif; ?>

                <?php if ($staff): ?>
                    <a class="unified-nav-item<?=$active('production')?>" href="<?=$url('/production')?>"><i>★</i><span><b>Production</b><small><?=$selectedProduction?$esc((string)$selectedProduction['title']):'Show workspace & tools'?></small></span></a>
                <?php endif; ?>

                <?php if ($memberTheatre): ?>
                    <a class="unified-nav-item<?=$active('my-theatre')?>" href="<?=$url('/my-theatre/')?>"><i>✦</i><span><b>My Theatre</b><small>Calendar, cast, forms & more</small></span></a>
                <?php endif; ?>

                <?php if ($communicationsAvailable): ?>
                    <a class="unified-nav-item<?=$active('community')?>" href="<?=$url('/channels')?>"><i>#</i><span><b>Community<?php if((int)$unread['community']>0):?><strong class="unified-unread"><?=(int)$unread['community']?></strong><?php endif;?></b><small><?=$approved?'CTSMD + production channels':'Production channels'?></small></span></a>
                    <a class="unified-nav-item<?=$route==='/videos'?' active':''?>" href="<?=$url('/videos')?>"><i>▶</i><span><b>Video Library</b><small>CTSMD &amp; rehearsal videos</small></span></a>
                    <a class="unified-nav-item<?=$active('messages')?>" href="<?=$url('/messages')?>"><i>✉</i><span><b>Messages<?php if((int)$unread['messages']>0):?><strong class="unified-unread"><?=(int)$unread['messages']?></strong><?php endif;?></b><small>Your conversations</small></span></a>
                <?php endif; ?>

                <?php if ($operations): ?>
                    <span class="unified-nav-label">Staff</span>
                    <a class="unified-nav-item<?=$active('operations')?>" href="<?=$url('/admin/operations')?>"><i>◎</i><span><b>Operations</b><small>Queues, people & administration</small></span></a>
                <?php endif; ?>
            </nav>
            <div class="unified-sidebar-foot">
                <?php if(AccessPolicy::localIdentitySwitchEnabled()):?><a href="<?=$url('/dev/identity')?>">Switch test identity</a><?php endif;?>
                <div class="unified-user"><i><?=$esc((string)$user['initials'])?></i><span><b><?=$esc((string)$user['name'])?></b><small><?=$esc((string)$user['role'])?><?=$approved?' · CTSMD member':' · Membership pending'?></small></span></div>
            </div>
        </aside>
        <div class="unified-nav-scrim" data-nav-scrim></div>
        <nav class="mobile-app-tabs" data-mobile-app-tabs aria-label="Mobile navigation">
            <a class="mobile-app-tab<?=$route==='/app'?' active':''?>" href="<?=$url('/app')?>"><span class="mobile-app-tab-icon">⌂</span><span class="mobile-app-tab-label">Home</span></a>
            <?php if($communicationsAvailable):?>
                <a class="mobile-app-tab<?=$route==='/calendar'?' active':''?>" href="<?=$url('/calendar')?>"><span class="mobile-app-tab-icon">◫</span><span class="mobile-app-tab-label">Calendar</span></a>
                <a class="mobile-app-tab<?=$active('community')?>" href="<?=$url('/channels')?>"><span class="mobile-app-tab-icon">#<?php if((int)$unread['community']>0):?><strong class="mobile-app-tab-badge"><?=(int)$unread['community']>99?'99+':(int)$unread['community']?></strong><?php endif;?></span><span class="mobile-app-tab-label">Channels</span></a>
                <a class="mobile-app-tab<?=$active('messages')?>" href="<?=$url('/messages')?>"><span class="mobile-app-tab-icon">✉<?php if((int)$unread['messages']>0):?><strong class="mobile-app-tab-badge"><?=(int)$unread['messages']>99?'99+':(int)$unread['messages']?></strong><?php endif;?></span><span class="mobile-app-tab-label">Messages</span></a>
            <?php else:?>
                <a class="mobile-app-tab<?=$active('family')?>" href="<?=$url($hasFamily?'/family-hub':'/family/manage')?>"><span class="mobile-app-tab-icon">♟</span><span class="mobile-app-tab-label"><?=$hasFamily?'Family':'Setup'?></span></a>
                <a class="mobile-app-tab<?=$route==='/notifications'?' active':''?>" href="<?=$url('/notifications')?>"><span class="mobile-app-tab-icon">●</span><span class="mobile-app-tab-label">Updates</span></a>
                <a class="mobile-app-tab<?=$route==='/account'?' active':''?>" href="<?=$url('/account')?>"><span class="mobile-app-tab-icon">◎</span><span class="mobile-app-tab-label">Account</span></a>
            <?php endif;?>
            <button class="mobile-app-tab mobile-app-more" type="button" data-nav-open aria-label="Open full navigation" aria-expanded="false"><span class="mobile-app-tab-icon">•••</span><span class="mobile-app-tab-label">More</span></button>
        </nav>
        <script defer src="<?=$url('/assets/js/mobile-engagement.js')?>"></script>
        <?php
    }

    private static function sidebarContext(array $user, bool $staff): array
    {
        $cacheKey=(int)$user['id'].':'.($staff?'staff':'member').':'.(AccessPolicy::canManageProduction($user)?'archive-all':'archive-own');
        if(isset(self::$sidebarContextCache[$cacheKey]))return self::$sidebarContextCache[$cacheKey];

        $context=[
            'productionOptions'=>[],
            'selectedProduction'=>null,
            'hasFamily'=>false,
            'hasActiveProduction'=>false,
            'hasArchive'=>false,
            'unread'=>['community'=>0,'messages'=>0],
        ];

        try {
            $db = Database::connect(dirname(__DIR__));
            $memberProductions = ProductionContext::activeProductions($db, $user);
            $context['hasActiveProduction'] = (bool)$memberProductions;
            if ($staff) {
                $context['productionOptions'] = $memberProductions;
                $context['selectedProduction'] = ProductionContext::selected($db, $user);
            }
            $familyStmt = $db->prepare("SELECT 1 FROM family_relationships WHERE guardian_user_id=:user AND status='active' LIMIT 1");
            $familyStmt->execute(['user'=>(int)$user['id']]);
            $context['hasFamily'] = (bool)$familyStmt->fetchColumn();
            if (AccessPolicy::canManageProduction($user)) {
                $context['hasArchive'] = (bool)$db->query("SELECT 1 FROM productions WHERE is_active=0 AND status='archived' LIMIT 1")->fetchColumn();
            } else {
                $archiveStmt = $db->prepare("SELECT 1 FROM productions p WHERE p.is_active=0 AND p.status='archived' AND (EXISTS (SELECT 1 FROM production_memberships pm WHERE pm.production_id=p.id AND pm.user_id=:viewer) OR EXISTS (SELECT 1 FROM family_relationships fr JOIN production_memberships cpm ON cpm.user_id=fr.student_user_id AND cpm.audience_type='student' WHERE fr.guardian_user_id=:guardian AND fr.status='active' AND cpm.production_id=p.id)) LIMIT 1");
                $archiveStmt->execute(['viewer'=>(int)$user['id'],'guardian'=>(int)$user['id']]);
                $context['hasArchive'] = (bool)$archiveStmt->fetchColumn();
            }
            $context['unread'] = CommunicationReadStateService::navigationCounts($db, $user);
        } catch (Throwable) {
        }

        return self::$sidebarContextCache[$cacheKey]=$context;
    }

    public static function productionSubnav(string $route, array $user): array
    {
        $canManageProduction = AccessPolicy::canManageProduction($user);
        $canManageSchedule = AccessPolicy::canManageSchedule($user);
        $canManageResources = AccessPolicy::canManageResources($user);
        $canManagePlaybill = AccessPolicy::canManagePlaybill($user);

        $active = static function(array $routes) use ($route): bool {
            foreach ($routes as $candidate) {
                if ($route === $candidate || str_starts_with($route, $candidate . '/')) return true;
            }
            return false;
        };

        $items = [
            ['label'=>'Overview','href'=>'/production','active'=>$route==='/production'],
            ['label'=>'Schedule','href'=>'/schedule','active'=>in_array($route, ['/schedule','/production/edit','/production/schedule/new','/production/schedule/import'], true)],
            ['label'=>'Attendance','href'=>'/attendance','active'=>$active(['/attendance'])],
        ];

        if ($canManageProduction) {
            $items[] = ['label'=>'Late','href'=>'/production/late-pickups','active'=>$active(['/production/late-pickups'])];
            $items[] = ['label'=>'Groups','href'=>'/production/groups','active'=>$active(['/production/groups'])];
            $items[] = ['label'=>'People','href'=>'/production/people','active'=>$active(['/production/people'])];
            $items[] = ['label'=>'Casting','href'=>'/production/casting','active'=>$active(['/production/casting'])];
        }

        $items[] = ['label'=>'Tasks','href'=>'/production/tasks','active'=>$active(['/production/tasks'])];
        $items[] = ['label'=>'Readiness','href'=>'/production/readiness','active'=>$active(['/production/readiness'])];
        $items[] = ['label'=>'Production day','href'=>'/production/day','active'=>$route==='/production/day'];
        $items[] = ['label'=>'Resources','href'=>'/resources','active'=>$active(['/resources','/admin/resources'])];
        $items[] = ['label'=>'Videos','href'=>'/videos','active'=>$active(['/videos','/production/rehearsal-library'])];

        if ($canManageResources) {
            $items[] = ['label'=>'Files','href'=>'/files','active'=>$active(['/files','/admin/files'])];
        }

        if ($canManageSchedule) {
            $items[] = ['label'=>'Updates','href'=>'/production/notices','active'=>$active(['/production/notices','/production/notice'])];
        }

        $items[] = ['label'=>'Playbill','href'=>'/playbills','active'=>$active(['/playbills','/admin/playbill'])];

        if ($canManagePlaybill) {
            $items[] = ['label'=>'Playbill media','href'=>'/admin/playbill/media','active'=>$route==='/admin/playbill/media'];
        }

        return $items;
    }

    public static function renderHeader(string $eyebrow,string $title,string $basePath,?array $subnav=null):void
    {
        $url=static fn(string $path):string=>($basePath?:'').$path;
        $esc=static fn(string $value):string=>htmlspecialchars($value,ENT_QUOTES,'UTF-8');
        $notificationUnread=0;
        try {
            if(session_status()===PHP_SESSION_ACTIVE && (int)($_SESSION['auth_user_id']??0)>0){
                $notificationUnread=self::notificationUnread((int)$_SESSION['auth_user_id']);
            }
        } catch (Throwable) {
        }
        $messageUnread=0;
        try {
            if(session_status()===PHP_SESSION_ACTIVE && (int)($_SESSION['auth_user_id']??0)>0){
                $db=Database::connect(dirname(__DIR__));
                $current=Auth::currentUser($db);
                if($current)$messageUnread=(int)(CommunicationReadStateService::navigationCounts($db,$current)['messages']??0);
            }
        } catch (Throwable) {
        }
        $account=self::$headerUser??[];
        $accountName=trim((string)($account['first_name']??'').' '.(string)($account['last_name']??''));
        if($accountName==='')$accountName=(string)($account['name']??'Your account');
        $accountEmail=(string)($account['email']??'');
        $initials=trim((string)($account['initials']??''));if($initials==='')$initials=mb_strtoupper(mb_substr($accountName,0,1));
        Auth::startSession();$_SESSION['auth_csrf']??=bin2hex(random_bytes(24));
        ?><link rel="stylesheet" href="<?=$url('/assets/css/navigation-controls.css?v=20261030-1')?>"><script defer src="<?=$url('/assets/js/account-menu.js?v=20261030-1')?>"></script><header class="unified-header"><button class="unified-menu" type="button" data-nav-open aria-label="Open navigation" aria-expanded="false">☰</button><div class="unified-title"><small><?=$esc($eyebrow)?></small><h1><?=$esc($title)?></h1></div><div class="unified-utilities"><a class="unified-inbox-link" href="<?=$url('/messages')?>" aria-label="Message inbox<?=$messageUnread>0?' · '.$messageUnread.' unread':''?>"><svg class="unified-message-icon" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7A8.4 8.4 0 0 1 4 11.5a8.5 8.5 0 0 1 4.7-7.6A8.4 8.4 0 0 1 12.5 3h.5a8.5 8.5 0 0 1 8 8v.5Z"/></svg><?php if($messageUnread>0):?><strong class="unified-unread"><?=$messageUnread>99?'99+':$messageUnread?></strong><?php endif;?></a><a class="unified-notifications-link" href="<?=$url('/notifications')?>" aria-label="Notifications<?=$notificationUnread>0?' · '.$notificationUnread.' unread':''?>"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg><?php if($notificationUnread>0):?><strong class="unified-unread"><?=$notificationUnread>99?'99+':$notificationUnread?></strong><?php endif;?></a><details class="unified-account" data-account-menu><summary class="unified-avatar" aria-label="Account menu for <?=$esc($accountName)?>" aria-expanded="false"><span aria-hidden="true"><?=$esc($initials)?></span></summary><div class="unified-account-panel"><div class="unified-account-identity"><strong><?=$esc($accountName)?></strong><?php if($accountEmail!==''):?><span><?=$esc($accountEmail)?></span><?php endif;?></div><nav aria-label="Your account"><a href="<?=$url('/account')?>">My Account</a><a href="<?=$url('/notification-preferences')?>">Notification Preferences</a><a href="<?=$url('/help')?>">Help</a></nav><form method="post" action="<?=$url('/logout')?>"><input type="hidden" name="csrf_token" value="<?=$esc((string)$_SESSION['auth_csrf'])?>"><button type="submit">Sign Out</button></form></div></details></div></header><?php if($subnav):?><nav class="unified-subnav" aria-label="Section navigation"><?php foreach($subnav as $item):?><a href="<?=$url($item['href'])?>"<?=!empty($item['active'])?' class="active" aria-current="page"':''?>><?=$esc($item['label'])?></a><?php endforeach;?></nav><?php endif;?><?php
    }

    private static function notificationUnread(int $userId): int
    {
        if(isset(self::$notificationUnreadCache[$userId]))return self::$notificationUnreadCache[$userId];
        $db=Database::connect(dirname(__DIR__));
        $stmt=$db->prepare('SELECT COUNT(*) FROM app_notifications WHERE recipient_user_id=:user AND read_at IS NULL');
        $stmt->execute(['user'=>$userId]);
        return self::$notificationUnreadCache[$userId]=(int)$stmt->fetchColumn();
    }
}
