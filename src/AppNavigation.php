<?php

declare(strict_types=1);

require_once __DIR__ . '/AccessPolicy.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ProductionContext.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/CommunicationReadStateService.php';

final class AppNavigation
{
    public static function renderSidebar(string $route, string $basePath, array $user): void
    {
        $url = static fn(string $path): string => ($basePath ?: '') . $path;
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $staff = AccessPolicy::isStaff($user);
        $approved = Auth::isApprovedMember($user);
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION['production_context_csrf'] ??= bin2hex(random_bytes(24));
        $_SESSION['auth_csrf'] ??= bin2hex(random_bytes(24));

        $productionOptions = [];
        $selectedProduction = null;
        $hasFamily = false;
        $hasActiveProduction = false;
        $hasArchive = false;
        $unread = ['community'=>0,'messages'=>0];

        try {
            $db = Database::connect(dirname(__DIR__));
            $memberProductions = ProductionContext::activeProductions($db, $user);
            $hasActiveProduction = (bool)$memberProductions;
            if ($staff) {
                $productionOptions = $memberProductions;
                $selectedProduction = ProductionContext::selected($db, $user);
            }
            $familyStmt = $db->prepare("SELECT 1 FROM family_relationships WHERE guardian_user_id=:user AND status='active' LIMIT 1");
            $familyStmt->execute(['user'=>(int)$user['id']]);
            $hasFamily = (bool)$familyStmt->fetchColumn();
            if (AccessPolicy::canManageProduction($user)) {
                $hasArchive = (bool)$db->query("SELECT 1 FROM productions WHERE is_active=0 AND status='archived' LIMIT 1")->fetchColumn();
            } else {
                $archiveStmt = $db->prepare("SELECT 1 FROM productions p WHERE p.is_active=0 AND p.status='archived' AND (EXISTS (SELECT 1 FROM production_memberships pm WHERE pm.production_id=p.id AND pm.user_id=:viewer) OR EXISTS (SELECT 1 FROM family_relationships fr JOIN production_memberships cpm ON cpm.user_id=fr.student_user_id AND cpm.audience_type='student' WHERE fr.guardian_user_id=:guardian AND fr.status='active' AND cpm.production_id=p.id)) LIMIT 1");
                $archiveStmt->execute(['viewer'=>(int)$user['id'],'guardian'=>(int)$user['id']]);
                $hasArchive = (bool)$archiveStmt->fetchColumn();
            }
            $unread = CommunicationReadStateService::navigationCounts($db, $user);
        } catch (Throwable) {
        }

        $memberTheatre = $approved || $hasActiveProduction || $hasArchive || $staff;
        $homeRoutes = ['/app','/family/action','/notifications','/notification-preferences','/push-settings'];
        $familyRoutes = ['/family-hub','/parent','/family/manage','/onboarding'];
        $productionRoutes = ['/production','/production/casting','/production/readiness','/production/people','/production/groups','/production/groups/view','/production/schedule/new','/production/schedule/import','/schedule','/production/day','/production/edit','/production/notices','/production/notice','/attendance','/attendance/take','/attendance/report','/playbills','/admin/playbill','/admin/playbill/media','/admin/productions','/calendar','/cast'];
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

                <?php if ($approved || $hasActiveProduction): ?>
                    <a class="unified-nav-item<?=$active('community')?>" href="<?=$url('/channels')?>"><i>#</i><span><b>Community<?php if((int)$unread['community']>0):?><strong class="unified-unread"><?=(int)$unread['community']?></strong><?php endif;?></b><small><?=$approved?'CTSMD + production channels':'Production channels'?></small></span></a>
                    <a class="unified-nav-item<?=$active('messages')?>" href="<?=$url('/messages')?>"><i>✉</i><span><b>Messages<?php if((int)$unread['messages']>0):?><strong class="unified-unread"><?=(int)$unread['messages']?></strong><?php endif;?></b><small>Your conversations</small></span></a>
                <?php endif; ?>

                <?php if ($staff): ?>
                    <span class="unified-nav-label">Staff</span>
                    <a class="unified-nav-item<?=$active('operations')?>" href="<?=$url('/admin/operations')?>"><i>◎</i><span><b>Operations</b><small>Queues, people & administration</small></span></a>
                <?php endif; ?>
            </nav>
            <div class="unified-sidebar-foot">
                <a href="<?=$url('/account')?>">My account</a>
                <a href="<?=$url('/notifications')?>">Notifications</a>
                <a href="<?=$url('/notification-preferences')?>">Notification preferences</a>
                <?php if(AccessPolicy::localIdentitySwitchEnabled()):?><a href="<?=$url('/dev/identity')?>">Switch test identity</a><?php endif;?>
                <div class="unified-user"><i><?=$esc((string)$user['initials'])?></i><span><b><?=$esc((string)$user['name'])?></b><small><?=$esc((string)$user['role'])?><?=$approved?' · CTSMD member':' · Membership pending'?></small></span></div>
                <form method="post" action="<?=$url('/logout')?>"><input type="hidden" name="csrf_token" value="<?=$esc((string)$_SESSION['auth_csrf'])?>"><button type="submit">Sign out</button></form>
            </div>
        </aside>
        <div class="unified-nav-scrim" data-nav-scrim></div>
        <?php
    }

    public static function renderHeader(string $eyebrow,string $title,string $basePath,?array $subnav=null):void
    {
        $url=static fn(string $path):string=>($basePath?:'').$path;
        $esc=static fn(string $value):string=>htmlspecialchars($value,ENT_QUOTES,'UTF-8');
        $notificationUnread=0;
        try {
            if(session_status()===PHP_SESSION_ACTIVE && (int)($_SESSION['auth_user_id']??0)>0){
                $db=Database::connect(dirname(__DIR__));
                $stmt=$db->prepare('SELECT COUNT(*) FROM app_notifications WHERE recipient_user_id=:user AND read_at IS NULL');
                $stmt->execute(['user'=>(int)$_SESSION['auth_user_id']]);
                $notificationUnread=(int)$stmt->fetchColumn();
            }
        } catch (Throwable) {
        }
        ?><header class="unified-header"><button class="unified-menu" type="button" data-nav-open aria-label="Open navigation">☰</button><div class="unified-title"><small><?=$esc($eyebrow)?></small><h1><?=$esc($title)?></h1></div><div class="unified-utilities"><a href="<?=$url('/notifications')?>">Notifications<?php if($notificationUnread>0):?><strong class="unified-unread"><?=$notificationUnread?></strong><?php endif;?></a><span class="unified-avatar"><?=$esc(substr((string)$title,0,1))?></span></div></header><?php if($subnav):?><nav class="unified-subnav" aria-label="Section navigation"><?php foreach($subnav as $item):?><a href="<?=$url($item['href'])?>"<?=!empty($item['active'])?' class="active"':''?>><?=$esc($item['label'])?></a><?php endforeach;?></nav><?php endif;?><?php
    }
}
